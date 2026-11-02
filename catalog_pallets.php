<?php
// catalog_pallets.php
$pageTitle  = "Pallet Jack Catalog";
$activePage = "catalog_pallets";
$bodyClass  = "page-bg-pallets";

require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";
require_once __DIR__ . "/db.php"; 

// --- 1. CAPTURE FILTER INPUTS ---
$filter_subtype = $_GET['subtype'] ?? 'all';
$filter_max_price = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000;

// --- 2. BUILD DYNAMIC SQL QUERY ---
$query = "SELECT id, name, model, price, description, image_path,
                 lift_capacity, subtype, `condition`
          FROM products
          WHERE type='pallets' AND active=1";

$params = [];

if ($filter_subtype !== 'all') {
    $query .= " AND subtype = ?";
    $params[] = $filter_subtype;
}

if ($filter_max_price > 0) {
    $query .= " AND price <= ?";
    $params[] = $filter_max_price;
}

$query .= " ORDER BY id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll();

$subtypeMap = ['electric' => 'Electric', 'manual' => 'Manual', 'semi' => 'Semi-Electric'];
$condMap    = ['new' => 'New', 'used' => 'Used'];
?>

<style>
  .background-logo{display:none!important}
  .page-overlay{display:none!important}
  .card-img-top {
      width: 100%;
      height: 220px; 
      object-fit: cover;
      border-top-left-radius: .5rem;
      border-top-right-radius: .5rem;
  }
  .catalog-card { transition: transform 0.3s; border: none; }
  .catalog-card:hover { transform: translateY(-5px); }
</style>

<?php
$bgImage = "imgs/pallet.png";
?>
<div aria-hidden="true" style="
  position: fixed; inset: 0;
  background:
    linear-gradient(rgba(0,0,0,.45), rgba(0,0,0,.45)),
    url('<?= htmlspecialchars($bgImage, ENT_QUOTES) ?>') center / cover no-repeat fixed;
  z-index: -1; pointer-events: none;"></div>

<main class="container-fluid mt-2 pt-2">
  <div class="row">
    <section class="col-12 p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0 text-white fw-bold">Pallet Jack Catalog</h2>
        <button class="btn btn-orange shadow-sm" data-bs-toggle="offcanvas" data-bs-target="#filterCanvas">
          🔍 Filter Results
        </button>
      </div>

      <div class="row g-4">
        <?php if (!$items): ?>
          <div class="col-12 text-center py-5">
            <p class="text-white-50 fs-5">No pallet jacks found matching your criteria.</p>
          </div>
        <?php else: foreach ($items as $it): ?>
          <div class="col-md-4 col-sm-6">
            <div class="card catalog-card shadow-lg h-100">
              <img
                src="<?= $it['image_path'] ? htmlspecialchars($it['image_path']) : 'imgs/pallet.png' ?>"
                class="card-img-top"
                alt="Pallet Jack"
                loading="lazy"
                onerror="this.onerror=null;this.src='imgs/pallet.png';">
              <div class="card-body d-flex flex-column">
                <h5 class="card-title fw-bold"><?= htmlspecialchars($it['name']) ?></h5>

                <div class="mb-2 small">
                    <?php if (!empty($it['model'])): ?>
                      <p class="mb-1"><strong>Model:</strong> <?= htmlspecialchars($it['model']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($it['lift_capacity'])): ?>
                      <p class="mb-1"><strong>Capacity:</strong> <?= htmlspecialchars($it['lift_capacity']) ?></p>
                    <?php endif; ?>

                    <?php if (!empty($it['subtype']) && isset($subtypeMap[$it['subtype']])): ?>
                      <p class="mb-1"><strong>Type:</strong> <?= $subtypeMap[$it['subtype']] ?></p>
                    <?php endif; ?>

                    <?php if (!empty($it['condition']) && isset($condMap[$it['condition']])): ?>
                      <p class="mb-1"><strong>Status:</strong> <?= $condMap[$it['condition']] ?></p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($it['description'])): ?>
                  <p class="card-text text-muted flex-grow-1" style="font-size: 0.9rem;"><?= nl2br(htmlspecialchars($it['description'])) ?></p>
                <?php endif; ?>

                <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
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

<div class="offcanvas offcanvas-end text-bg-dark" tabindex="-1" id="filterCanvas">
  <div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title">Filter Options</h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>

  <div class="offcanvas-body">
    <form action="catalog_pallets.php" method="GET">
        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Operation Type</label>
          <select name="subtype" class="form-select bg-dark text-white border-secondary">
            <option value="all" <?= $filter_subtype == 'all' ? 'selected' : '' ?>>All Types</option>
            <option value="electric" <?= $filter_subtype == 'electric' ? 'selected' : '' ?>>Electric</option>
            <option value="manual" <?= $filter_subtype == 'manual' ? 'selected' : '' ?>>Manual</option>
            <option value="semi" <?= $filter_subtype == 'semi' ? 'selected' : '' ?>>Semi-Electric</option>
          </select>
        </div>

        <div class="mb-4">
          <label class="form-label text-orange fw-bold">Max Price ($)</label>
          <input type="range" name="max_price" class="form-range" min="500" max="10000" step="500" 
                 value="<?= $filter_max_price ?>" oninput="priceVal.value = this.value">
          <div class="d-flex justify-content-between">
              <span class="small">$500</span>
              <output id="priceVal" class="fw-bold">$<?= number_format($filter_max_price) ?></output>
              <span class="small">$10k</span>
          </div>
        </div>

        <button type="submit" class="btn btn-orange w-100 py-2 fw-bold">Apply Filters</button>
        <a href="catalog_pallets.php" class="btn btn-link w-100 text-white-50 mt-2">Clear All</a>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . "/inc/footer.php"; ?>