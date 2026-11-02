from fastapi import FastAPI, Request, HTTPException
from fastapi.responses import JSONResponse
from google import genai
from google.genai import types
import pymysql.cursors
import os
import json
import time
import hmac
import hashlib
import re
from dotenv import load_dotenv
from typing import Any, Dict, List, Optional
from pathlib import Path
import datetime

# --- 1) CONFIGURATION ---
BASE_DIR = Path(__file__).resolve().parent
load_dotenv(dotenv_path=BASE_DIR / ".env")

app = FastAPI()

GEMINI_API_KEY = os.getenv("GEMINI_API_KEY", "")
MODEL_ID = os.getenv("GEMINI_MODEL_ID", "gemini-2.5-flash")
def _load_shared_secret() -> str:
    # 1) ai-server/.env  2) fallback: ../config/config.php (both are git-ignored)
    s = os.getenv("AI_SHARED_SECRET", "")
    if s:
        return s
    try:
        cfg = (Path(__file__).resolve().parent.parent / "config" / "config.php").read_text(encoding="utf-8")
        m = re.search(r"['\"]AI_SHARED_SECRET['\"]\s*=>\s*['\"]([^'\"]+)['\"]", cfg)
        return m.group(1) if m else ""
    except Exception:
        return ""

SHARED_SECRET = _load_shared_secret().encode("utf-8")
HEBREW_RE = re.compile(r"[\u0590-\u05FF]")

if not GEMINI_API_KEY:
    print("⚠️ GEMINI_API_KEY is missing!")

client = genai.Client(api_key=GEMINI_API_KEY)

# --- LOAD JSON CONTEXT FILES ---
def load_json(name):
    try:
        with open(BASE_DIR / name, "r", encoding="utf-8") as f: return json.load(f)
    except Exception as e: 
        print(f"⚠️ Failed to load {name}: {e}")
        return {}

# Load all context files
kb_data = load_json("KB.json")
policy_data = load_json("conversation_policy.json")
schema_data = load_json("db_schema_knowledge.json")

# --- 2) DATABASE ---
def get_db():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        user=os.getenv("DB_USER", "root"),
        password=os.getenv("DB_PASS", ""),
        database=os.getenv("DB_NAME", "forklift_fixed"),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )

# --- 3) TOOL IMPLEMENTATIONS ---

# === GUEST TOOLS ===
def search_inventory(keyword: str, max_price: int = 1000000):
    try:
        if HEBREW_RE.search(keyword):
            prompt = f"Translate to English search keywords (max 3 words): {keyword}"
            r = client.models.generate_content(model=MODEL_ID, contents=prompt)
            keyword = r.text.strip()
        
        words = keyword.split()
        conditions = ["active=1", "price <= %s"]
        params = [max_price]
        
        for word in words:
            conditions.append("(name LIKE %s OR model LIKE %s)")
            params.extend([f"%{word}%", f"%{word}%"])
            
        sql = f"SELECT name, model, price, lift_capacity, image_path FROM products WHERE {' AND '.join(conditions)} LIMIT 5"
        
        conn = get_db()
        with conn.cursor() as c:
            c.execute(sql, tuple(params))
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def get_company_info(topic: str):
    # Use loaded KB data
    valid_topics = ["services", "faq", "company_overview"]
    if topic not in valid_topics: return "Topic not found. Available: services, faq, company_overview."
    return json.dumps(kb_data.get("files", {}).get(topic, "No info available"), ensure_ascii=False)

# === ADMIN TOOLS ===
def list_unassigned_tickets(limit: int = 5):
    try:
        conn = get_db()
        with conn.cursor() as c:
            sql = "SELECT id, description, status, created_at FROM service_requests WHERE technician_id IS NULL AND status IN ('new', 'open') ORDER BY created_at ASC LIMIT %s"
            c.execute(sql, (limit,))
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def list_ticket_queue(statuses: List[str], limit: int = 10):
    allowed = ["new", "open", "pending", "in_progress", "completed", "closed", "cancelled"]
    safe_statuses = [s for s in statuses if s in allowed]
    if not safe_statuses: return "Error: No valid statuses provided."
    try:
        conn = get_db()
        with conn.cursor() as c:
            format_strings = ','.join(['%s'] * len(safe_statuses))
            sql = f"SELECT id, status, description, technician_id, created_at FROM service_requests WHERE status IN ({format_strings}) ORDER BY created_at DESC LIMIT %s"
            params = tuple(safe_statuses + [limit])
            c.execute(sql, params)
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def list_technicians(limit: int = 20):
    try:
        conn = get_db()
        with conn.cursor() as c:
            today = datetime.date.today()
            sql = """
            SELECT s.id, s.name, s.email,
                (SELECT COUNT(*) FROM service_requests r WHERE r.technician_id = s.id AND r.status IN ('open', 'in_progress', 'pending')) as active_workload,
                (SELECT COUNT(*) FROM service_requests r WHERE r.technician_id = s.id AND r.status = 'completed') as score,
                EXISTS(SELECT 1 FROM technician_off_days o WHERE o.technician_id = s.id AND o.off_date = %s) as is_off_today
            FROM staff s 
            WHERE s.role = 'technician' 
            LIMIT %s
            """
            c.execute(sql, (today, limit))
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def assign_ticket(ticket_id: int, strategy: str = "balanced"):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT id FROM service_requests WHERE id=%s", (ticket_id,))
            if not c.fetchone(): return "Error: Ticket not found."

            today = datetime.date.today()
            sql = """
                SELECT s.id, s.name,
                (SELECT COUNT(*) FROM service_requests r WHERE r.technician_id = s.id AND r.status IN ('open', 'in_progress', 'pending')) as load_count,
                (SELECT COUNT(*) FROM service_requests r WHERE r.technician_id = s.id AND r.status = 'completed') as score
                FROM staff s 
                WHERE s.role = 'technician'
                AND NOT EXISTS (SELECT 1 FROM technician_off_days o WHERE o.technician_id = s.id AND o.off_date = %s)
            """
            c.execute(sql, (today,))
            techs = c.fetchall()
            
            if not techs: return "Error: No technicians available (all off-duty or none found)."

            best_tech = sorted(techs, key=lambda x: (x['load_count'], -x['score']))[0]
            
            c.execute("UPDATE service_requests SET technician_id=%s, status='open' WHERE id=%s", (best_tech['id'], ticket_id))
            conn.commit()
            return f"Success: Assigned Ticket #{ticket_id} to {best_tech['name']} (ID: {best_tech['id']}, Load: {best_tech['load_count']})"
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def assign_ticket_to_technician(ticket_id: int, technician_id: int):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT id FROM staff WHERE id=%s AND role='technician'", (technician_id,))
            if not c.fetchone(): return "Error: Invalid Technician ID."
            
            c.execute("UPDATE service_requests SET technician_id=%s, status='open' WHERE id=%s", (technician_id, ticket_id))
            conn.commit()
            return f"Success: Manually assigned Ticket #{ticket_id} to Tech #{technician_id}."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def set_ticket_status(ticket_id: int, status: str, note: str = None):
    allowed = ["new", "open", "pending", "in_progress", "completed", "closed", "cancelled"]
    if status not in allowed: return f"Error: Invalid status. Allowed: {allowed}"
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("UPDATE service_requests SET status=%s WHERE id=%s", (status, ticket_id))
            if note:
                c.execute("UPDATE service_requests SET technician_notes = CONCAT(IFNULL(technician_notes, ''), '\n[ADMIN]: ', %s) WHERE id=%s", (note, ticket_id))
            conn.commit()
            return f"Success: Ticket #{ticket_id} status set to {status}."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def create_quote(ticket_id: int, line_items: str, labor_cost: float = 0, notes: str = None):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT user_id FROM service_requests WHERE id=%s", (ticket_id,))
            req = c.fetchone()
            if not req: return "Error: Ticket not found."
            
            total_amount = labor_cost 
            
            sql_quote = """
                INSERT INTO quotes (user_id, service_id, amount, status, issue_date) 
                VALUES (%s, %s, %s, 'pending', CURDATE())
            """
            c.execute(sql_quote, (req['user_id'], ticket_id, total_amount))
            
            quote_text = f"QUOTE GENERATED: Items: {line_items} | Labor: {labor_cost} | Notes: {notes or 'None'}"
            c.execute("UPDATE service_requests SET technician_notes = CONCAT(IFNULL(technician_notes, ''), '\n[ADMIN]: ', %s) WHERE id=%s", (quote_text, ticket_id))
            
            conn.commit()
            return f"Success: Quote created for Ticket #{ticket_id}. Details saved to ticket notes."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def toggle_product_active(product_id: int, active: bool):
    try:
        conn = get_db()
        with conn.cursor() as c:
            val = 1 if active else 0
            c.execute("UPDATE products SET active=%s WHERE id=%s", (val, product_id))
            conn.commit()
            return f"Success: Product #{product_id} active={val}."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

# === TECHNICIAN TOOLS ===
def my_assigned_tickets(actor_id: int, status: str = None, limit: int = 10):
    try:
        conn = get_db()
        with conn.cursor() as c:
            sql = "SELECT id, status, description, created_at FROM service_requests WHERE technician_id=%s"
            params = [actor_id]
            if status:
                sql += " AND status=%s"
                params.append(status)
            sql += " ORDER BY created_at DESC LIMIT %s"
            params.append(limit)
            c.execute(sql, tuple(params))
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def get_ticket_details(ticket_id: int, actor_id: int, role: str):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT * FROM service_requests WHERE id=%s", (ticket_id,))
            ticket = c.fetchone()
            if not ticket: return "Error: Not found."
            
            if role == 'technician' and ticket['technician_id'] != actor_id:
                return "Error: Access Denied. Not assigned to you."
            if role == 'client' and ticket['user_id'] != actor_id:
                return "Error: Access Denied. Not your ticket."
            
            return json.dumps(ticket, default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def add_technician_note(ticket_id: int, note: str, actor_id: int, role: str, set_status: str = None):
    try:
        conn = get_db()
        with conn.cursor() as c:
            if role == 'technician':
                c.execute("SELECT id FROM service_requests WHERE id=%s AND technician_id=%s", (ticket_id, actor_id))
                if not c.fetchone(): return "Error: Access Denied."

            prefix = "[TECH]" if role == 'technician' else "[ADMIN]"
            c.execute("UPDATE service_requests SET technician_notes = CONCAT(IFNULL(technician_notes, ''), '\n', %s, ': ', %s) WHERE id=%s", (prefix, note, ticket_id))
            
            if set_status:
                c.execute("UPDATE service_requests SET status=%s WHERE id=%s", (set_status, ticket_id))
            
            conn.commit()
            return "Success: Note added."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def mark_ticket_completed(ticket_id: int, actor_id: int, completion_note: str = None):
    return add_technician_note(ticket_id, f"COMPLETION REPORT: {completion_note}", actor_id, 'technician', set_status='completed')

def request_reassignment(ticket_id: int, reason: str, actor_id: int):
    return add_technician_note(ticket_id, f"REQUEST REASSIGNMENT: {reason}", actor_id, 'technician', set_status='pending')

# === CLIENT TOOLS ===
def check_my_tickets(actor_id: int, limit: int = 5):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT id, status, description, created_at FROM service_requests WHERE user_id=%s ORDER BY created_at DESC LIMIT %s", (actor_id, limit))
            return json.dumps(c.fetchall(), default=str)
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def add_ticket_comment(ticket_id: int, comment: str, actor_id: int):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT id FROM service_requests WHERE id=%s AND user_id=%s", (ticket_id, actor_id))
            if not c.fetchone(): return "Error: Ticket not found or access denied."
            
            c.execute("UPDATE service_requests SET technician_notes = CONCAT(IFNULL(technician_notes, ''), '\n[CLIENT]: ', %s) WHERE id=%s", (comment, ticket_id))
            conn.commit()
            return "Success: Comment added."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def create_ticket_with_context(actor_id: int, description: str, problem_category: str = None, urgency: str = "normal"):
    full_desc = f"[{urgency.upper()}] {problem_category or 'General'}: {description}"
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("INSERT INTO service_requests (user_id, request_type, description, status, created_at) VALUES (%s, 'service', %s, 'new', NOW())", (actor_id, full_desc))
            conn.commit()
            return f"Success: Ticket #{c.lastrowid} created."
    except Exception as e: return f"Error: {e}"
    finally: conn.close()

def get_my_ticket(ticket_id: int, actor_id: int):
    try:
        conn = get_db()
        with conn.cursor() as c:
            c.execute("SELECT * FROM service_requests WHERE id=%s AND user_id=%s", (ticket_id, actor_id))
            row = c.fetchone()
            if not row:
                return "Error: Ticket not found or access denied."
            return json.dumps(row, default=str)
    except Exception as e:
        return f"Error: {e}"
    finally:
        try:
            conn.close()
        except:
            pass


# --- 4) TOOL DEFINITIONS ---
tools_list = [
    # Guest
    types.Tool(function_declarations=[
        types.FunctionDeclaration(name="search_inventory", description="Search products.", parameters=types.Schema(type="OBJECT", properties={"keyword":types.Schema(type="STRING"), "max_price":types.Schema(type="INTEGER")}, required=["keyword"])),
        types.FunctionDeclaration(name="get_company_info", description="Get KB info.", parameters=types.Schema(type="OBJECT", properties={"topic":types.Schema(type="STRING")}, required=["topic"])),
    ]),
    # Admin
    types.Tool(function_declarations=[
        types.FunctionDeclaration(name="list_unassigned_tickets", description="Admin: Show new/open tickets with no tech.", parameters=types.Schema(type="OBJECT", properties={"limit":types.Schema(type="INTEGER")})),
        types.FunctionDeclaration(name="list_ticket_queue", description="Admin: List tickets by status.", parameters=types.Schema(type="OBJECT", properties={"statuses":types.Schema(type="ARRAY", items=types.Schema(type="STRING")), "limit":types.Schema(type="INTEGER")}, required=["statuses"])),
        types.FunctionDeclaration(name="assign_ticket", description="Admin: Auto-assign ticket (Balanced Strategy).", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "strategy":types.Schema(type="STRING")}, required=["ticket_id"])),
        types.FunctionDeclaration(name="assign_ticket_to_technician", description="Admin: Manual assign.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "technician_id":types.Schema(type="INTEGER")}, required=["ticket_id", "technician_id"])),
        types.FunctionDeclaration(name="set_ticket_status", description="Admin: Change status.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "status":types.Schema(type="STRING"), "note":types.Schema(type="STRING")}, required=["ticket_id", "status"])),
        types.FunctionDeclaration(name="create_quote", description="Admin: Create quote.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "line_items":types.Schema(type="STRING"), "labor_cost":types.Schema(type="NUMBER"), "notes":types.Schema(type="STRING")}, required=["ticket_id", "line_items"])),
        types.FunctionDeclaration(name="list_technicians", description="Admin: List techs & stats.", parameters=types.Schema(type="OBJECT", properties={"limit":types.Schema(type="INTEGER")})),
        types.FunctionDeclaration(name="toggle_product_active", description="Admin: Hide/Show product.", parameters=types.Schema(type="OBJECT", properties={"product_id":types.Schema(type="INTEGER"), "active":types.Schema(type="BOOLEAN")}, required=["product_id", "active"])),
    ]),
    # Technician
    types.Tool(function_declarations=[
        types.FunctionDeclaration(name="my_assigned_tickets", description="Tech: My tickets.", parameters=types.Schema(type="OBJECT", properties={"status":types.Schema(type="STRING"), "limit":types.Schema(type="INTEGER")})),
        types.FunctionDeclaration(name="get_ticket_details", description="Tech/Client: Get info.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER")}, required=["ticket_id"])),
        types.FunctionDeclaration(name="add_technician_note", description="Tech: Add note.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "note":types.Schema(type="STRING"), "set_status":types.Schema(type="STRING")}, required=["ticket_id", "note"])),
        types.FunctionDeclaration(name="mark_ticket_completed", description="Tech: Complete ticket.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "completion_note":types.Schema(type="STRING")}, required=["ticket_id"])),
        types.FunctionDeclaration(name="request_reassignment", description="Tech: Request reassignment.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "reason":types.Schema(type="STRING")}, required=["ticket_id", "reason"])),
    ]),
    # Client
    types.Tool(function_declarations=[
        types.FunctionDeclaration(name="check_my_tickets", description="Client: List tickets.", parameters=types.Schema(type="OBJECT", properties={"limit":types.Schema(type="INTEGER")})),
        types.FunctionDeclaration(name="get_my_ticket", description="Client: View ticket.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER")}, required=["ticket_id"])),
        types.FunctionDeclaration(name="add_ticket_comment", description="Client: Add comment.", parameters=types.Schema(type="OBJECT", properties={"ticket_id":types.Schema(type="INTEGER"), "comment":types.Schema(type="STRING")}, required=["ticket_id", "comment"])),
        types.FunctionDeclaration(name="create_ticket_with_context", description="Client: Create ticket.", parameters=types.Schema(type="OBJECT", properties={"description":types.Schema(type="STRING"), "problem_category":types.Schema(type="STRING"), "urgency":types.Schema(type="STRING")}, required=["description"])),
    ])
]

# --- 5) ENDPOINT ---
@app.post("/chat")
async def chat_handler(req: Request):
    try:
        body = await req.body()
        sig = req.headers.get("X-AI-Signature")
        expected = hmac.new(SHARED_SECRET, body, hashlib.sha256).hexdigest()
        if not hmac.compare_digest(sig, expected): raise HTTPException(401)
        
        data = json.loads(body)
        msg = data.get("message", "")
        role = data.get("role", "guest")
        actor_id = data.get("actor_id")
        history = data.get("history", [])

        # RBAC Filter
        active_tools = [tools_list[0]] # Guest
        if role == 'admin': active_tools.extend([tools_list[1]])
        if role == 'technician': active_tools.extend([tools_list[2]])
        if role == 'client': active_tools.extend([tools_list[3]])
        
        # --- PREPARE CONTEXT STRINGS ---
        kb_context = json.dumps(kb_data, ensure_ascii=False)
        schema_context = json.dumps(schema_data, ensure_ascii=False)
        policy_context = json.dumps(policy_data, ensure_ascii=False)

        sys_prompt = f"""
        You are Abbas AI. Role: {role} (ID: {actor_id}).
        
        --- SYSTEM KNOWLEDGE ---
        [DATABASE SCHEMA]:
        {schema_context}
        
        [CONVERSATION POLICY]:
        {policy_context}
        
        [STATIC INFO]:
        {kb_context}
        
        --- RULES ---
        1. Speak Hebrew by default.
        2. Technical terms in English (e.g. 'Pending', 'Toyota').
        3. Lists must use bullet points 🔹.
        4. ONLY use tools allowed for this role.
        """
        
        chat_history = []
        for turn in history:
            r = "user" if turn.get("role") == "user" else "model"
            t = turn.get("content", "")
            if t: chat_history.append(types.Content(role=r, parts=[types.Part(text=t)]))
        
        chat_history.append(types.Content(role="user", parts=[types.Part(text=msg)]))
        
        response = client.models.generate_content(
            model=MODEL_ID,
            contents=chat_history,
            config=types.GenerateContentConfig(tools=active_tools, system_instruction=sys_prompt)
        )
        
        if response.function_calls:
            fc = response.function_calls[0]
            fn = fc.name
            args = fc.args
            print(f"DEBUG: AI Call {fn} args={args}")

            res = "Error: Permission Denied or Unknown Tool."
            
            # --- EXECUTION ROUTER ---
            if fn == "search_inventory": res = search_inventory(args.get("keyword"), int(args.get("max_price", 1000000)))
            elif fn == "get_company_info": res = get_company_info(args.get("topic"))
            
            # Admin
            elif role == 'admin':
                if fn == "list_unassigned_tickets": res = list_unassigned_tickets(int(args.get("limit", 5)))
                elif fn == "list_ticket_queue": res = list_ticket_queue(args.get("statuses", []), int(args.get("limit", 10)))
                elif fn == "assign_ticket": res = assign_ticket(int(args["ticket_id"]), args.get("strategy", "balanced"))
                elif fn == "assign_ticket_to_technician": res = assign_ticket_to_technician(int(args["ticket_id"]), int(args["technician_id"]))
                elif fn == "set_ticket_status": res = set_ticket_status(int(args["ticket_id"]), args["status"], args.get("note"))
                elif fn == "create_quote": res = create_quote(int(args["ticket_id"]), args["line_items"], float(args.get("labor_cost", 0)), args.get("notes"))
                elif fn == "list_technicians": res = list_technicians(int(args.get("limit", 20)))
                elif fn == "toggle_product_active": res = toggle_product_active(int(args["product_id"]), args["active"])

            # Technician
            elif role == 'technician' and actor_id:
                if fn == "my_assigned_tickets": res = my_assigned_tickets(actor_id, args.get("status"), int(args.get("limit", 10)))
                elif fn == "get_ticket_details": res = get_ticket_details(int(args["ticket_id"]), actor_id, role)
                elif fn == "add_technician_note": res = add_technician_note(int(args["ticket_id"]), args["note"], actor_id, role, args.get("set_status"))
                elif fn == "mark_ticket_completed": res = mark_ticket_completed(int(args["ticket_id"]), actor_id, args.get("completion_note"))
                elif fn == "request_reassignment": res = request_reassignment(int(args["ticket_id"]), args["reason"], actor_id)

            # Client
            elif role == 'client' and actor_id:
                if fn == "check_my_tickets": res = check_my_tickets(actor_id, int(args.get("limit", 5)))
                elif fn == "get_my_ticket": res = get_my_ticket(int(args["ticket_id"]), actor_id) 
                elif fn == "add_ticket_comment": res = add_ticket_comment(int(args["ticket_id"]), args["comment"], actor_id)
                elif fn == "create_ticket_with_context": res = create_ticket_with_context(actor_id, args["description"], args.get("problem_category"), args.get("urgency"))

            chat_history.append(types.Content(role="model", parts=[types.Part(function_call=fc)]))
            chat_history.append(types.Content(role="user", parts=[types.Part(function_response=types.FunctionResponse(name=fn, response={"result": res}))]))
            
            final_resp = client.models.generate_content(
                model=MODEL_ID,
                contents=chat_history,
                config=types.GenerateContentConfig(tools=active_tools, system_instruction=sys_prompt)
            )
            return JSONResponse({"reply": final_resp.text})
        
        else:
            return JSONResponse({"reply": response.text})

    except Exception as e:
        print(f"🔥 Error: {e}")
        return JSONResponse({"reply": "System Error"})

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8000)