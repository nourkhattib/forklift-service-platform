<?php
// final_project_php/troubleshooting.php
$pageTitle  = "Troubleshooting - Abbas Forklifts";
$activePage = "troubleshooting";
$bodyClass  = "page-bg-troubleshooting";

require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";

/* --- IMPROVED BACKGROUND IMAGE LOGIC --- */
$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($baseUrl === '/') { $baseUrl = ''; }

$bgFs = __DIR__ . '/imgs/troubleshooting.png'; 
$bgUrl = $baseUrl . '/imgs/troubleshooting.png';

if (is_file($bgFs)) {
    $bgUrl .= '?v=' . filemtime($bgFs);
} else {
    $bgUrl = 'https://via.placeholder.com/1600x900?text=Image+Not+Found:+troubleshooting.png';
}
?>
<style>
  html, body { height: 100%; margin: 0; }

  /* Background setup with darker overlay */
  body.page-bg-troubleshooting {
    min-height: 100vh;
    background:
      linear-gradient(rgba(0,0,0,.70), rgba(0,0,0,.70)),
      url('<?= htmlspecialchars($bgUrl, ENT_QUOTES) ?>') center / cover no-repeat fixed !important;
    background-color: #000;
    color: #fff;
  }

  /* Hide global background logo */
  body.page-bg-troubleshooting .background-logo { display: none !important; }

  /* Reduced padding-top to shift content upwards */
  .page-content { position: relative; z-index: 1; padding-top: 40px; }

  /* Title and Text in White */
  h1 { color: #ffffff !important; font-weight: bold; }

  /* Dark Accordion Styling */
  .accordion-item { border: 0; background: transparent; margin-bottom: 10px; }
  .accordion-button {
    background-color: rgba(28, 28, 28, 0.9);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.1);
  }
  .accordion-button:not(.collapsed) {
    background-color: rgba(38, 38, 38, 0.95);
    color: #ff7700; /* Highlight active question in orange */
    box-shadow: none;
  }
  .accordion-button::after {
    filter: invert(1); /* Makes the arrow icon white */
  }
  .accordion-body {
    background-color: rgba(18, 18, 18, 0.9);
    color: #dddddd;
    border: 1px solid rgba(255, 255, 255, 0.05);
  }

  footer { background-color: #111; color: #bbb; }
</style>

<div class="container page-content">
  <h1 class="mb-5 text-center">📋 Common Forklift Troubleshooting</h1>

  <div class="accordion" id="troubleshootingAccordion">
    
    <div class="accordion-item">
      <h2 class="accordion-header" id="heading1">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1" aria-expanded="true" aria-controls="collapse1">
          The forklift won't start – what should I check?
        </button>
      </h2>
      <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#troubleshootingAccordion" aria-labelledby="heading1">
        <div class="accordion-body">
          <ul class="mb-2">
            <li>Check if the battery is properly charged and connected.</li>
            <li>Inspect battery cables for any corrosion or loose connections.</li>
            <li>Ensure the EMERGENCY STOP button is not pressed down.</li>
            <li>Check the main fuse box for any blown fuses.</li>
          </ul>
          If all these are fine and it still won't start, please contact our service team.
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="heading2">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2" aria-expanded="false" aria-controls="collapse2">
          Forklift turns on but won't move forward or backward
        </button>
      </h2>
      <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion" aria-labelledby="heading2">
        <div class="accordion-body">
          <ul class="mb-2">
            <li>Check the directional lever – is it stuck in neutral?</li>
            <li>Make sure the parking brake or foot brake is fully released.</li>
            <li>Check the battery level; low voltage can disable drive motors.</li>
            <li>Look for error codes on the dashboard display.</li>
          </ul>
          If the problem persists, a certified technician inspection is required.
        </div>
      </div>
    </div>

    <div class="accordion-item">
      <h2 class="accordion-header" id="heading3">
        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3" aria-expanded="false" aria-controls="collapse3">
          Unusual metallic noise when driving in reverse
        </button>
      </h2>
      <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#troubleshootingAccordion" aria-labelledby="heading3">
        <div class="accordion-body">
          <ul class="mb-2">
            <li>Inspect the wheels for any debris or foreign objects.</li>
            <li>Check the drive motor and transmission fluid levels.</li>
            <li>Verify that there are no loose structural parts or guards.</li>
          </ul>
          Metallic grinding noises can indicate serious mechanical failure – stop operating immediately and request service.
        </div>
      </div>
    </div>
    
  </div>

  <div class="text-center mt-5">
    <p class="text-white">❌ Still having issues? <a href="contact.php" class="btn btn-orange ms-2">Contact Support</a></p>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<?php require_once __DIR__ . "/inc/footer.php"; ?>