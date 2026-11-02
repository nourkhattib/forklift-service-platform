<?php
// Ensure session is started to access user data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Check Login Status
$isLoggedIn = isset($_SESSION['user_id']);
$username = $isLoggedIn ? $_SESSION['username'] : 'Guest';
$role = $isLoggedIn ? $_SESSION['role'] : '';
$roleDisplay = $isLoggedIn ? " (" . ucfirst($role) . ")" : "";
?>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <img src="imgs/logo.webp" alt="Logo" height="60">
      <span class="fw-bold">Abbas Forklifts</span>
    </a>

    <button class="navbar-toggler d-lg-none" id="navbarToggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <button type="button" class="btn-close btn-close-white d-lg-none" id="navbarClose" style="display: none;"></button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto gap-lg-3"> 
        <li class="nav-item">
            <a class="nav-link" href="index.php">Home</a>
        </li>
        
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle pe-3" href="#" id="catalogDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            Catalog
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="catalog_forklifts.php">Forklifts</a></li>
            <li><a class="dropdown-item" href="catalog_parts.php">Spare Parts</a></li>
            <li><a class="dropdown-item" href="catalog_pallets.php">Pallet Jacks</a></li>
            <li><a class="dropdown-item" href="catalog_hoses.php">Hydraulic Hoses</a></li>
            <li><a class="dropdown-item" href="catalog_accessories.php">Accessories</a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
        <li class="nav-item"><a class="nav-link" href="troubleshooting.php">Troubleshooting</a></li>
        <li class="nav-item"><a class="nav-link" href="contact.php">Contact Us</a></li>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center gap-2">
         <li class="nav-item">
            <span class="nav-link text-white fst-italic">
               Hello, <strong><?= htmlspecialchars($username) ?></strong>
               <span class="text-warning" style="font-size: 0.9em;"><?= htmlspecialchars($roleDisplay) ?></span>
            </span>
         </li>
         
         <?php if ($isLoggedIn): ?>
             <li class="nav-item">
                 <a class="nav-link btn btn-sm btn-outline-danger px-3 text-white border-white" href="logout.php">Logout</a>
             </li>
         <?php endif; ?>
      </ul>
      
    </div>
  </div>
</nav>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const toggler = document.getElementById('navbarToggler');
    const closeBtn = document.getElementById('navbarClose');
    const nav = document.getElementById('navbarNav');

    // Toggle Hamburger / Close Icon
    toggler.addEventListener('click', () => {
      setTimeout(() => {
        if (toggler.getAttribute('aria-expanded') === 'true') {
          toggler.style.display = 'none';   
          closeBtn.style.display = 'block';   
        }
      }, 150);
    });

    closeBtn.addEventListener('click', () => {
      const bsCollapse = bootstrap.Collapse.getInstance(nav) || new bootstrap.Collapse(nav, { toggle: false });
      bsCollapse.hide();
      closeBtn.style.display = 'none';
      toggler.style.display = 'block';
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      const isInside = nav.contains(e.target) || toggler.contains(e.target) || closeBtn.contains(e.target);
      if (!isInside && nav.classList.contains('show')) {
        const bsCollapse = bootstrap.Collapse.getInstance(nav) || new bootstrap.Collapse(nav, { toggle: false });
        bsCollapse.hide();
        closeBtn.style.display = 'none';
        toggler.style.display = 'block';
      }
    });

    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992) {
        closeBtn.style.display = 'none';
        toggler.style.display = 'block';
      }
    });
  });
</script>