<?php
// final_project_php/services.php
$pageTitle  = "Our Services - Abbas Forklifts";
$activePage = "services";
$bodyClass  = "page-bg-services";

require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";

$baseUrl = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($baseUrl === '/') { $baseUrl = ''; }

$bgFs = __DIR__ . '/imgs/ser.png'; 
$bgUrl = $baseUrl . '/imgs/ser.png';

if (is_file($bgFs)) {
    $bgUrl .= '?v=' . filemtime($bgFs);
} else {
    $bgUrl = 'https://via.placeholder.com/1600x900?text=Image+Not+Found:+ser.png';
}
?>

<style>
  .background-logo { display: none !important; }

  .service-box {
    background: rgba(0, 0, 0, 0.7); 
    border-radius: 15px;
    padding: 30px 20px;
    border: 1px solid rgba(255, 119, 0, 0.3); 
    height: 100%;
  }
  
  .service-icon { font-size: 45px; color: #ff7700; margin-bottom: 20px; }
  .page-content { position: relative; z-index: 1; padding-top: 40px; }
</style>

<div aria-hidden="true" style="
  position: fixed;
  inset: 0;
  background:
    linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.45)),
    url('<?= htmlspecialchars($bgUrl, ENT_QUOTES) ?>') center / cover no-repeat fixed;
  z-index: -1;
  pointer-events: none;
"></div>

<div class="page-content container">
  <div class="text-center mb-5">
  <div class="page-content container">
  <div class="text-center mb-5">
    <h1 class="display-5 fw-bold text-white" style="color: #ffffff;">🔧 Our Professional Services</h1>
    <p style="color: #ffffff;">Expert solutions for all your material handling needs</p>
  </div>

  <div class="row g-4">
    </div>
</div>
  </div>
  <div class="row g-4">
<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">🛠️</div>
    <h5 style="color:#ff7700;" class="fw-bold">Preventive Maintenance</h5>
    <p class="text-white-50">Periodic maintenance including oils, brakes, battery checks, and safety inspections to keep your fleet running.</p>
  </div>
</div>

<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">🚛</div>
    <h5 style="color:#ff7700;" class="fw-bold">On-Site Repairs</h5>
    <p class="text-white-50">Our mobile service teams reach your location quickly to minimize downtime and fix malfunctions on the spot.</p>
  </div>
</div>

<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">🎓</div>
    <h5 style="color:#ff7700;" class="fw-bold">Operator Training</h5>
    <p class="text-white-50">Comprehensive courses for forklift operators according to the latest safety regulations and maintenance standards.</p>
  </div>
</div>

<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">🔄</div>
    <h5 style="color:#ff7700;" class="fw-bold">Replacement Units</h5>
    <p class="text-white-50">Need to stay active while your machine is being fixed? We provide rental units to ensure zero work stoppage.</p>
  </div>
</div>

<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">📦</div>
    <h5 style="color:#ff7700;" class="fw-bold">Spare Parts Supply</h5>
    <p class="text-white-50">Original and high-quality parts for all major forklift brands with fast delivery from our central warehouse.</p>
  </div>
</div>

<div class="col-md-4">
  <div class="service-box text-center shadow-lg">
    <div class="service-icon">💡</div>
    <h5 style="color:#ff7700;" class="fw-bold">Fleet Consulting</h5>
    <p class="text-white-50">Expert advice on fleet optimization and selecting the right equipment for your specific warehouse needs.</p>
  </div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . "/inc/footer.php"; ?>