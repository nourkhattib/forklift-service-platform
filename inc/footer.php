<?php /* inc/footer.php */ ?>
<footer class="custom-footer-with-image mt-5">
  <div class="container">
    <div class="row align-items-start g-4 footer-container">
      <div class="col-12 col-lg-6 footer-text">
        <h4 class="mb-3">📍 Service Areas</h4>
        <ul class="footer-list list-unstyled mb-3">
          <li><strong>North</strong> | <strong>Central</strong> | <strong>South</strong></li>
          <li>☎️ <a href="tel:048223223" class="text-decoration-none text-white">04-8223223</a></li>
          <li>✉️ <a href="mailto:info@forklift-abbas.co.il" class="text-decoration-none text-white">info@forklift-abbas.co.il</a></li>
          <li>📌 Main Road, Kfar Kanna 16930</li>
        </ul>

        <div class="social-links">
          Follow us:
          <a href="#" class="text-decoration-none text-orange ms-2" target="_blank" rel="noopener" aria-label="Facebook">Facebook</a> |
          <a href="#" class="text-decoration-none text-orange mx-2" target="_blank" rel="noopener" aria-label="Twitter">Twitter</a> |
          <a href="#" class="text-decoration-none text-orange mx-2" target="_blank" rel="noopener" aria-label="LinkedIn">LinkedIn</a>
        </div>
      </div>

      <div class="col-12 col-lg-6 footer-map">
        <iframe
          title="Location Map – Abbas Forklifts"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3355.296503725975!2d35.34238691081402!3d32.757866173561816!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x151c4979f8ba7773%3A0x515d13c06cef245c!2z16LXkdeQ16Eg157XnNeS15bXldeqINeV15HXnteV16og15TXqNee15Qg15HXoiLXng!5e0!3m2!1sen!2us!4v1751579874656!5m2!1sen!2us"
          width="100%"
          height="220"
          style="border:0; border-radius:10px;"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade">
        </iframe>
      </div>
    </div>
  </div>
</footer>

<!-- שורת תחתית קטנה -->
<footer class="text-center">
  <div class="container">
    <p class="mb-0">&copy; <?= date("Y") ?> Abbas Forklifts. All Rights Reserved.</p>
  </div>
</footer>

<!-- סקריפטים גלובליים -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="./script.js"></script>

<button id="chatbotButton" class="btn btn-primary rounded-circle"
        style="position: fixed; bottom: 25px; right: 25px; z-index: 9999; width: 60px; height: 60px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
    🤖
</button>

<div id="chatWindow" class="card shadow-lg"
     style="display:none; position:fixed; bottom:100px; right:25px; width:350px; height:450px; z-index:9999; border-radius: 15px; overflow: hidden;">
    
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <span>Abbas AI 🤖</span>
        <button onclick="document.getElementById('chatWindow').style.display='none'" class="btn-close btn-close-white small"></button>
    </div>

    <div class="card-body bg-light" id="chatMessages" style="overflow-y:auto; height:350px; padding: 15px;">
        <div class="text-start mb-2 text-muted small">Hello! I am Abbas AI. Ask me about forklifts, prices, or your service tickets.</div>
    </div>

    <div class="p-2 bg-white border-top">
        <div class="input-group">
            <input id="chatInput" class="form-control border-0" placeholder="Type a message..." style="box-shadow: none;">
            <button id="sendBtn" class="btn btn-link text-primary" style="text-decoration: none;">➤</button>
        </div>
    </div>
</div>

<script src="chatbot.js?v=2"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // 1. Hoist the Bot (Fix positioning on all pages)
        const botBtn = document.getElementById('chatbotButton');
        const botWin = document.getElementById('chatWindow');
        if (botBtn && botWin) {
            document.body.appendChild(botBtn);
            document.body.appendChild(botWin);
        }

        // 2. Handle Send Button Click
        const sendBtn = document.getElementById("sendBtn");
        if (sendBtn) {
            sendBtn.addEventListener("click", function() {
                const input = document.getElementById("chatInput");
                // Trigger the Enter key event manually
                const event = new KeyboardEvent("keydown", {
                    key: "Enter",
                    bubbles: true,
                    cancelable: true
                });
                input.dispatchEvent(event);
            });
        }
    });
</script>

</body>
</html>
