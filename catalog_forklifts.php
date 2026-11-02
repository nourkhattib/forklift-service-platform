<?php
// catalog_forklifts.php
$pageTitle  = "Forklift Catalog";
$activePage = "catalog_forklifts";
$bodyClass  = "page-bg-forklifts page-with-overlay";


require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";
require_once __DIR__ . "/db.php"; 

// --- 1. CAPTURE FILTER INPUTS ---
$filter_condition = $_GET['condition'] ?? 'all';
$filter_engine    = $_GET['engine_type'] ?? 'all';
$filter_max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 200000;

// --- 2. BUILD DYNAMIC SQL QUERY ---
$query = "SELECT id, name, model, price, description, image_path,
                 lift_capacity, mast_length, engine_type, `condition`
          FROM products
          WHERE type='forklifts' AND active=1";

$params = [];

if ($filter_condition !== 'all') {
    $query .= " AND `condition` = ?";
    $params[] = $filter_condition;
}

if ($filter_engine !== 'all') {
    $query .= " AND engine_type = ?";
    $params[] = $filter_engine;
}

if ($filter_max_price > 0) {
    $query .= " AND price <= ?";
    $params[] = $filter_max_price;
}

$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

$engineLabels = [
  'electric'     => 'Electric',
  'diesel'       => 'Diesel',
  'turbo_diesel' => 'Turbo Diesel',
];
$condLabels = [
  'new'  => 'New',
  'used' => 'Used',
];
?>

<style>
.card-img-top {
    width: 100%;
    height: 220px; 
    object-fit: cover;
    border-top-left-radius: .5rem;
    border-top-right-radius: .5rem;
}
.catalog-card {
    transition: transform 0.3s;
    border: none;
}
.catalog-card:hover {
    transform: translateY(-5px);
}
</style>

<div id="layout" style="min-height:100vh; display:flex; flex-direction:column;">

  <?php $bgImage = "imgs/fork.png"; ?>
  <div aria-hidden="true" style="
    position: fixed; inset: 0;
    background:
      linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.45)),
      url('<?= htmlspecialchars($bgImage, ENT_QUOTES) ?>') center / cover no-repeat fixed;
    z-index: -1; pointer-events: none;"></div>

  <main class="container-fluid mt-3 pt-2" style="flex:1 0 auto;">
    <div class="row">
      <section class="col-12 p-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h2 class="mb-0 text-white fw-bold">Forklift Catalog</h2>
          <button class="btn btn-orange shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
            🔍 Filter Products
          </button>
        </div>

        <?php if($filter_condition != 'all' || $filter_engine != 'all'): ?>
            <div class="mb-3">
                <a href="catalog_forklifts.php" class="btn btn-sm btn-light">Clear All Filters X</a>
            </div>
        <?php endif; ?>

        <div class="row g-4">
          <?php if (!$items): ?>
            <div class="col-12 text-center py-5">
                <p class="text-white-50 fs-4">No forklifts found matching your criteria.</p>
            </div>
          <?php else: foreach ($items as $it): ?>
            <div class="col-md-4 col-sm-6">
              <div class="card catalog-card shadow-lg h-100">
                <img
                  src="<?= $it['image_path'] ? htmlspecialchars($it['image_path']) : 'imgs/fork.png' ?>"
                  alt="Forklift"
                  class="card-img-top"
                  loading="lazy"
                  onerror="this.onerror=null;this.src='imgs/fork.png';">
                <div class="card-body d-flex flex-column">
                  <h5 class="card-title fw-bold text-dark"><?= htmlspecialchars($it['name']) ?></h5>

                  <?php if (!empty($it['model'])): ?>
                    <div class="small text-muted mb-2">
                      <strong>Model:</strong> <?= htmlspecialchars($it['model']) ?>
                    </div>
                  <?php endif; ?>

                  <ul class="list-unstyled mb-3 small">
                    <?php if (!empty($it['lift_capacity'])): ?>
                      <li><strong>Capacity:</strong> <?= htmlspecialchars($it['lift_capacity']) ?></li>
                    <?php endif; ?>

                    <?php if (!empty($it['mast_length'])): ?>
                      <li><strong>Mast:</strong> <?= htmlspecialchars($it['mast_length']) ?></li>
                    <?php endif; ?>

                    <?php
                      $engine = $it['engine_type'] ?? null;
                      $engineTxt = ($engine && isset($engineLabels[$engine])) ? $engineLabels[$engine] : null;
                      if ($engineTxt):
                    ?>
                      <li><strong>Engine:</strong> <?= $engineTxt ?></li>
                    <?php endif; ?>

                    <?php
                      $cond = $it['condition'] ?? null;
                      $condTxt = ($cond && isset($condLabels[$cond])) ? $condLabels[$cond] : null;
                      if ($condTxt):
                    ?>
                      <li><strong>Status:</strong> <?= $condTxt ?></li>
                    <?php endif; ?>
                  </ul>

                  <?php if (!empty($it['description'])): ?>
                    <p class="card-text text-secondary flex-grow-1" style="font-size: 0.9rem;">
                        <?= nl2br(htmlspecialchars($it['description'])) ?>
                    </p>
                  <?php endif; ?>

                  <div class="mt-auto border-top pt-2 d-flex justify-content-between align-items-center">
                    <?php if ($it['price'] !== null): ?>
                      <span class="fs-5 fw-bold text-orange">$<?= number_format((float)$it['price'], 2) ?></span>
                    <?php endif; ?>
                    <a href="contact.php?interest=<?= urlencode($it['name']) ?>" class="btn btn-sm btn-outline-orange">Inquire</a>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </section>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <?php require_once __DIR__ . "/inc/footer.php"; ?>
</div>

<div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="filterCanvas">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title">Refine Search</h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <form action="catalog_forklifts.php" method="GET">
        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Condition</label>
          <select name="condition" class="form-select bg-dark text-white border-secondary">
            <option value="all" <?= $filter_condition == 'all' ? 'selected' : '' ?>>All Conditions</option>
            <option value="new" <?= $filter_condition == 'new' ? 'selected' : '' ?>>New</option>
            <option value="used" <?= $filter_condition == 'used' ? 'selected' : '' ?>>Used</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Engine Type</label>
          <select name="engine_type" class="form-select bg-dark text-white border-secondary">
            <option value="all" <?= $filter_engine == 'all' ? 'selected' : '' ?>>All Engines</option>
            <option value="electric" <?= $filter_engine == 'electric' ? 'selected' : '' ?>>Electric</option>
            <option value="diesel" <?= $filter_engine == 'diesel' ? 'selected' : '' ?>>Diesel</option>
            <option value="turbo_diesel" <?= $filter_engine == 'turbo_diesel' ? 'selected' : '' ?>>Turbo Diesel</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Max Price ($)</label>
          <input type="range" name="max_price" class="form-range" min="1000" max="200000" step="5000" 
                 value="<?= $filter_max_price ?>" id="priceRange" oninput="priceOutput.value = this.value">
          <div class="d-flex justify-content-between">
              <span>$1k</span>
              <output id="priceOutput" class="fw-bold text-white">$<?= number_format($filter_max_price) ?></output>
              <span>$200k</span>
          </div>
        </div>

        <button type="submit" class="btn btn-orange w-100 py-2 fw-bold">Apply Filters</button>
        <a href="catalog_forklifts.php" class="btn btn-link w-100 text-white-50 mt-2">Reset All</a>
    </form>
  </div>
</div>