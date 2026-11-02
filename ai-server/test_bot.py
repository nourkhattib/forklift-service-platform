import json
import time
import hmac
import hashlib
import urllib.request
import urllib.error
from dataclasses import dataclass
from typing import Optional, List, Dict, Any, Tuple

PY_URL = "http://127.0.0.1:8000/chat"
import os
SHARED_SECRET = os.getenv("AI_SHARED_SECRET", "")  # set the same value as in ai-server/.env

@dataclass
class Actor:
    name: str
    role: str
    actor_type: str
    actor_id: Optional[int]
    username: str

@dataclass
class TestCase:
    title: str
    actor: Actor
    message: str
    expect: List[str]
    forbid: List[str]

def call_python(actor: Actor, message: str):
    payload = {
        "message": message, "actor_type": actor.actor_type,
        "actor_id": actor.actor_id, "username": actor.username,
        "role": actor.role, "ts": int(time.time()),
    }
    body = json.dumps(payload).encode("utf-8")
    sig = hmac.new(SHARED_SECRET.encode("utf-8"), body, hashlib.sha256).hexdigest()
    
    req = urllib.request.Request(
        PY_URL, data=body, method="POST",
        headers={"Content-Type": "application/json", "X-AI-Signature": sig}
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return r.status, json.loads(r.read().decode("utf-8"))["reply"]
    except Exception as e:
        return 0, str(e)

def main():
    guest = Actor("Guest", "guest", "guest", None, "Guest")
    client = Actor("Jake", "client", "client", 1, "Jake")
    tech = Actor("Charlie", "technician", "staff", 3, "Charlie")
    admin = Actor("Alice", "admin", "staff", 1, "Alice")

    tests = [
        TestCase("GUEST: Services", guest, "מה השירותים?", ["שירות", "מכירה"], ["Ticket #"]),
        # Updated Expectation: "חשבונך" (Your account) OR "לקוח" (Client) is fine
        TestCase("GUEST: Troubleshoot Refusal", guest, "המלגזה לא עובדת", ["התחבר", "חשבונך", "לקוח"], ["בדוק חיבורים"]),
        TestCase("GUEST: Search (Tools)", guest, "יש מלגזה טויוטה?", ["מחיר", "ש\"ח"], ["Error"]),
        
        TestCase("CLIENT: Troubleshoot Allowed", client, "המלגזה לא עובדת", ["בדוק", "האם"], ["התחבר"]),
        TestCase("CLIENT: Tickets", client, "בדוק פניות שלי", ["Ticket", "status"], ["Not authorized"]),
        
        TestCase("TECH: Tickets", tech, "בדוק פניות שלי", ["Ticket", "status"], ["Not authorized"]),
    ]

    print(f"--- Testing {PY_URL} ---")
    for i, t in enumerate(tests, 1):
        print(f"\nTest {i}: {t.title}")
        status, reply = call_python(t.actor, t.message)
        
        # Loose matching for Hebrew (if ANY expected word is present, it passes)
        passed_expect = any(e in reply for e in t.expect)
        failed_forbid = any(f in reply for f in t.forbid)
        
        if status == 200 and passed_expect and not failed_forbid:
            print("✅ PASS")
        else:
            print(f"❌ FAIL\nReply: {reply}\nExpected: {t.expect}\nForbidden: {t.forbid}")

if __name__ == "__main__":
    main()