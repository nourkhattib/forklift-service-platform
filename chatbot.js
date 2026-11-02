console.log("🤖 Chatbot Script Loaded");

document.addEventListener("DOMContentLoaded", () => {
    // 1. Element Selectors
    const chatBox = document.getElementById("chatMessages");
    const chatWindow = document.getElementById("chatWindow");
    const chatBtn = document.getElementById("chatbotButton");
    const minimizeBtn = document.getElementById("minimizeChat");
    const closeBtn = document.querySelector("#chatWindow .btn-close");
    const inputField = document.getElementById("chatInput");

    // Get client user ID if available from PHP
    const currentUserId = window.CLIENT_USER_ID || null; 

    if (!chatBox || !chatWindow || !chatBtn) {
        console.error("Chatbot elements missing from DOM.");
        return;
    }

    // 2. State Restoration on Page Load
    const savedHistory = localStorage.getItem("abbas_chat_history");
    const isChatOpen = localStorage.getItem("abbas_chat_open");

    if (savedHistory) chatBox.innerHTML = savedHistory;
    if (isChatOpen === "true") chatWindow.style.display = "block";

    // 3. Floating Action Button
    chatBtn.onclick = () => {
        chatWindow.style.display = "block";
        localStorage.setItem("abbas_chat_open", "true");
        if (inputField) inputField.focus();
        setTimeout(() => chatBox.scrollTop = chatBox.scrollHeight, 50);
    };

    // 4. Minimize Button
    if (minimizeBtn) {
        minimizeBtn.onclick = (e) => {
            e.stopPropagation();
            chatWindow.style.display = "none";
            localStorage.setItem("abbas_chat_open", "false");
            console.log("Chat minimized: History preserved.");
        };
    }

    // 5. Close Button
    if (closeBtn) {
        closeBtn.onclick = () => {
            chatWindow.style.display = "none";
            localStorage.setItem("abbas_chat_open", "false");
            localStorage.removeItem("abbas_chat_history");
            chatBox.innerHTML = `<div class="text-start mb-2 text-muted small">Hello! I am Abbas AI. How can I help you today?</div>`;
            console.log("Chat closed: History wiped.");
        };
    }

    // 6. Hide chat when other buttons on page clicked
    document.addEventListener("click", (e) => {
        const target = e.target;
        if (target.closest("button") && !target.closest("#chatbotButton")) {
            chatWindow.style.display = "none";
            localStorage.setItem("abbas_chat_open", "false");
            console.log("Chat auto-hidden due to button click");
        }
    });

    // 7. Message Handling
    if (inputField) {
        inputField.addEventListener("keydown", async (e) => {
            if (e.key !== "Enter") return;
            const msg = inputField.value.trim();
            if (!msg) return;
            inputField.value = "";

            chatBox.innerHTML += `<div class="text-end mb-2"><b>You:</b> ${msg}</div>`;
            chatBox.scrollTop = chatBox.scrollHeight;
            localStorage.setItem("abbas_chat_history", chatBox.innerHTML);

            const loadingId = "loading-" + Date.now();
            chatBox.innerHTML += `<div id="${loadingId}" class="text-start mb-2 text-muted small italic">Abbas AI is thinking...</div>`;
            chatBox.scrollTop = chatBox.scrollHeight;

            try {
                const payload = {
                    message: msg,
                    current_page: window.location.href,
                    page_title: document.title,
                };

                // If we are in client area, send mode "client" and userId
                if (currentUserId) {
                    payload.mode = "client";
                    payload.userId = currentUserId;
                } else {
                    payload.mode = "landing";
                }

                const response = await fetch("chatbot.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                const loader = document.getElementById(loadingId);
                if (loader) loader.remove();

                const botReply = data.reply || "I'm sorry, I'm having trouble connecting right now.";
                chatBox.innerHTML += `
                    <div class="text-start mb-2 bg-light p-2 rounded text-dark">
                        <b>AI:</b> ${botReply}
                    </div>`;
                localStorage.setItem("abbas_chat_history", chatBox.innerHTML);
                chatBox.scrollTop = chatBox.scrollHeight;

            } catch (error) {
                console.error("Fetch Error:", error);
                const loader = document.getElementById(loadingId);
                if (loader) loader.remove();
                chatBox.innerHTML += `<div class="text-start mb-2 text-danger small"><b>Error:</b> Connection lost.</div>`;
            }
        });
    }
});

// 8. Fully close chat on page refresh or unload
window.addEventListener("beforeunload", () => {
    localStorage.removeItem("abbas_chat_history");
    localStorage.removeItem("abbas_chat_open");
});
