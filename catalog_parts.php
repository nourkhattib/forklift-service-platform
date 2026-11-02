<?php
// final_project_php/catalog_parts.php
$pageTitle  = "Parts Catalog";
$activePage = "catalog_parts";
$bodyClass  = "page-bg-parts"; 

require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";
require_once __DIR__ . "/db.php"; 

/* --- 1. CAPTURE FILTER INPUTS --- */
$filter_category = $_GET['category'] ?? 'all';
$filter_brand    = $_GET['brand'] ?? 'all';
$filter_max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 1000;

/* --- 2. BUILD DYNAMIC SQL QUERY --- */
$query = "SELECT id, name, compatible_models, price, description, image_path
          FROM products
          WHERE type='parts' AND active=1";

$params = [];

if ($filter_category !== 'all') {
    $query .= " AND description LIKE ?";
    $params[] = "%$filter_category%";
}

if ($filter_brand !== 'all') {
    $query .= " AND compatible_models LIKE ?";
    $params[] = "%$filter_brand%";
}

if ($filter_max_price > 0) {
    $query .= " AND price <= ?";
    $params[] = $filter_max_price;
}

$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

/* ---------- Background Image Logic ---------- */
$baseUrl = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($baseUrl === '/') { $baseUrl = ''; }

$bgFs  = __DIR__ . '/imgs/parts.png';
$bgUrl = $baseUrl . '/imgs/parts.png';
if (is_file($bgFs)) {
  $bgUrl .= '?v=' . filemtime($bgFs);
} else {
  $bgUrl = 'https://via.placeholder.com/1600x900?text=Missing:+/imgs/parts.png';
}
?>

<style>
.background-logo { display:none !important; }
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

<div aria-hidden="true" style="
  position: fixed;
  inset: 0;
  background:
    linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.45)),
    url('<?= htmlspecialchars($bgUrl, ENT_QUOTES) ?>') center / cover no-repeat fixed;
  z-index: -1;
  pointer-events: none;
"></div>

<main class="container-fluid mt-2 pt-2">
  <div class="row">
    <section class="col-12 p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0 text-white fw-bold">Spare Parts Catalog</h2>
        <button class="btn btn-orange shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
          🔍 Filter Parts
        </button>
      </div>

      <div class="row g-4">
        <?php if (!$items): ?>
          <div class="col-12 text-center py-5">
            <p class="text-white-50 fs-5">No parts found matching your selection.</p>
          </div>
        <?php else: foreach ($items as $it): ?>
          <div class="col-md-4 col-sm-6">
            <div class="card catalog-card shadow-lg h-100">
              <img
                src="<?= $it['image_path'] ? htmlspecialchars($it['image_path']) : 'imgs/parts.png' ?>"
                class="card-img-top"
                alt="<?= htmlspecialchars($it['name']) ?>"
                loading="lazy"
                onerror="this.onerror=null;this.src='imgs/parts.png';">
              <div class="card-body d-flex flex-column">
                <h5 class="card-title fw-bold"><?= htmlspecialchars($it['name']) ?></h5>

                <?php if (!empty($it['compatible_models'])): ?>
                  <div class="small text-muted mb-2">
                    <strong>Compatible with:</strong>
                    <?= htmlspecialchars($it['compatible_models']) ?>
                  </div>
                <?php endif; ?>

                <?php if (!empty($it['description'])): ?>
                  <p class="card-text text-secondary small flex-grow-1">
                    <?= nl2br(htmlspecialchars($it['description'])) ?>
                  </p>
                <?php endif; ?>

                <div class="mt-auto border-top pt-2 d-flex justify-content-between align-items-center">
                    <?php if ($it['price'] !== null): ?>
                      <span class="fs-5 fw-bold text-orange">$<?= number_format((float)$it['price'], 2) ?></span>
                    <?php endif; ?>
                    <a href="contact.php?part=<?= urlencode($it['name']) ?>" class="btn btn-sm btn-outline-orange">Inquire</a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </section>
  </div>
</main>

<div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="filterCanvas" aria-labelledby="filterCanvasLabel">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="filterCanvasLabel">Filter Parts</h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <form action="catalog_parts.php" method="GET">
        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Category</label>
          <select name="category" class="form-select bg-dark text-white border-secondary">
            <option value="all" <?= $filter_category == 'all' ? 'selected' : '' ?>>All Categories</option>
            <option value="Filters" <?= $filter_category == 'Filters' ? 'selected' : '' ?>>Filters</option>
            <option value="Brakes" <?= $filter_category == 'Brakes' ? 'selected' : '' ?>>Brakes</option>
            <option value="Electrical" <?= $filter_category == 'Electrical' ? 'selected' : '' ?>>Electrical System</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Brand Compatibility</label>
          <select name="brand" class="form-select bg-dark text-white border-secondary">
            <option value="all" <?= $filter_brand == 'all' ? 'selected' : '' ?>>All Brands</option>
            <option value="TOYOTA" <?= $filter_brand == 'TOYOTA' ? 'selected' : '' ?>>TOYOTA</option>
            <option value="WECAN" <?= $filter_brand == 'WECAN' ? 'selected' : '' ?>>WECAN</option>
            <option value="STILL" <?= $filter_brand == 'STILL' ? 'selected' : '' ?>>STILL</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Max Price ($)</label>
          <input type="range" name="max_price" class="form-range" min="50" max="2000" step="50" 
                 value="<?= $filter_max_price ?>" oninput="priceVal.value = this.value">
          <div class="d-flex justify-content-between">
              <span class="small">$50</span>
              <output id="priceVal" class="fw-bold">$<?= number_format($filter_max_price) ?></output>
              <span class="small">$2,000</span>
          </div>
        </div>

        <button type="submit" class="btn btn-orange w-100 py-2 fw-bold">Apply Filters</button>
        <a href="catalog_parts.php" class="btn btn-link w-100 text-white-50 mt-2">Reset All</a>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . "/inc/footer.php"; ?>