<?php
session_start();
require_once __DIR__ . "/inc/navbar.php";
require_once __DIR__ . "/db.php"; 

if (!isset($pageTitle)) { $pageTitle = "עבאס מלגזות"; }

$login_message = $_SESSION['login_message'] ?? null;
$login_status = $_SESSION['login_status'] ?? null;

unset($_SESSION['login_message']);
unset($_SESSION['login_status']);
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link href="style.css" rel="stylesheet">
    <link href="index.css" rel="stylesheet">
    <style>
     .carousel-indicators [data-bs-target] {
        background-color: #ff6600;
     }
    </style>
</head>
<body>

<section class="hero">
    <div class="hero-content">
        <h1>Reliable Forklifts, Parts & Support</h1>
        <p>Abbas Forklifts provides high-quality industrial solutions for your logistics needs.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3 mt-3">
            <a href="#" class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#loginModal">Login to Dashboard</a>
            <a href="./about.php" class="btn btn-orange">About Us</a>
        </div>
    </div>
</section>


<section class="container mt-5 mb-5">
    <div class="row service-box service-glass shadow-lg p-4 rounded align-items-center">
        <div class="col-md-6 text-center">
            <img src="./imgs/ser.png" alt="Forklift Repair Service" class="service-img">
        </div>
        <div class="col-md-6 text-white">
            <h3 class="mb-3">Repair Department & On-Site Service</h3>
            <p>
                We offer professional and reliable repair services for all types of forklifts – electric, diesel, and manual.
                Our team of mechanics is equipped with advanced diagnostic systems and can reach you for on-site field repairs.
                Our service includes periodic inspections, routine maintenance, emergency troubleshooting, and preparing equipment for safety certifications.
            </p>
            <a href="./contact.php" class="btn btn-orange mt-3">📅 Schedule a Repair</a>
        </div>
    </div>
</section>

<section class="py-5 bg-dark text-white text-center mt-5">
    <div class="container">
        <h3 class="fw-bold">Need Assistance or a Custom Quote?</h3>
        <p class="lead">Our expert team is ready to support your machinery and forklift needs at any time.</p>
        <button class="btn btn-orange btn-lg mt-2" data-bs-toggle="modal" data-bs-target="#signupModal">
            Register Now
        </button>
    </div>
</section>

<section class="container mt-5">
    <div class="service-box service-glass p-4 rounded text-center text-white">
        <h3 class="mb-3">Our Clients</h3>
        <p class="mb-4">Brands that choose Abbas Forklifts</p>
        <div class="brand-strip">
            <div class="brand-track-container">
                <div class="brand-track-right-to-left">
                    <img src="imgs/toyota.png" class="brand-logo" alt="Toyota">
                    <img src="imgs/hyster.png" class="brand-logo" alt="Hyster">
                    <img src="imgs/jungheinrich.jpg" class="brand-logo" alt="Jungheinrich">
                    <img src="imgs/clark.png" class="brand-logo" alt="Clark">
                    <img src="imgs/mitsubishi.png" class="brand-logo" alt="Mitsubishi">
                    <img src="imgs/toyota.png" class="brand-logo" alt="Toyota">
                    <img src="imgs/hyster.png" class="brand-logo" alt="Hyster">
                    <img src="imgs/jungheinrich.jpg" class="brand-logo" alt="Jungheinrich">
                    <img src="imgs/clark.png" class="brand-logo" alt="Clark">
                    <img src="imgs/mitsubishi.png" class="brand-logo" alt="Mitsubishi">
                </div>
            </div>
        </div>
    </div>
</section>


<div class="modal fade" id="loginModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Login</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="loginLogic.php"> 
                    <p class="welcome-line">Welcome back! Let’s lift your business higher 🚜🔥</p>
                    <input type="email" name="email" class="form-control mb-3" placeholder="Email" required>
                    <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
                    <div id="loginError" class="text-danger mb-2" style="display:none;"></div>
                    <button type="submit" class="btn btn-orange w-100">Login</button>
                </form>
                <p class="switch-text">Not a member? 
                    <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" data-bs-dismiss="modal">Register</a>
                </p>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="signupModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5>Sign Up</h5>
                <button type="button" class="btn-close position-absolute end-0 me-3" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="welcome-line">Join the Abbas family and lift your goals to the next level 💪✨</p>
                <form method="POST" action="signupLogic.php"> 
                    <input type="text" name="username" id="signupUser" class="form-control mb-3" placeholder="Username" required>
                    <input type="email" name="email" id="signupEmail" class="form-control mb-3" placeholder="Email" required>
                    <input type="password" name="password" id="signupPass" class="form-control mb-3" placeholder="Password" required>
                    <button type="submit" class="btn btn-orange w-100">Register</button>
                </form>
                <p class="switch-text">Already a member? 
                    <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login</a>
                </p>
            </div>
        </div>
    </div>
</div>


<?php require_once __DIR__ . "/inc/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>