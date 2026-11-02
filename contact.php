<?php
session_start();

$pageTitle = "Contact Us";
require_once __DIR__ . "/inc/header.php";
require_once __DIR__ . "/inc/navbar.php";
require_once __DIR__ . "/db.php"; 

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = $user_id !== null;

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
function csrf_field_contact() {
    echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($_SESSION['csrf']).'">';
}
function csrf_check_contact() {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(400); die('CSRF validation failed');
    }
}

$sentOK = false;
$errMsg = null;
$login_required = false;

// --- Fetch Active Products ---
$activeProducts = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, model FROM products WHERE active = 1 ORDER BY name ASC");
    $stmt->execute();
    $activeProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("DB Error fetching products: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check_contact();

    if (!$is_logged_in) {
        $login_required = true;
        $errMsg = "Please log in to submit a request.";
    } else {
        $request_type = $_POST['request_type'] ?? 'service';
        $product_id   = null;
        $description  = null;
        $duration     = $_POST['duration'] ?? 'longer';

        if ($request_type === 'product') {
            $product_id = trim($_POST['product_id'] ?? '');
            if (!is_numeric($product_id) || $product_id <= 0) {
                $errMsg = "Please select a valid product.";
            } else {
                $productExists = false;
                foreach($activeProducts as $product) {
                    if ($product['id'] == $product_id) {
                        $productExists = true;
                        $description = "Product Purchase Request: " . htmlspecialchars($product['name'] . ' ' . $product['model']);
                        break;
                    }
                }
                if (!$productExists) $errMsg = "Selected product is currently unavailable.";
            }
        } elseif ($request_type === 'general') {
            $description = "General Inquiry: " . trim($_POST['message'] ?? '');
            if (empty(trim($_POST['message'] ?? ''))) $errMsg = "Please enter your question.";
        } else { // 'service'
            $description = trim($_POST['message'] ?? '');
            if (empty($description)) $errMsg = "Please provide a description for the service request.";
        }
        
        // Image upload logic
        $image_path = null;
        if (!$errMsg && isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . "/uploads/service_requests/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            $file_ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

            if (in_array($file_ext, $allowed_ext)) {
                $filename = uniqid('req_') . '.' . $file_ext;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                    $image_path = "uploads/service_requests/" . $filename;
                } else {
                    $errMsg = "Error uploading image.";
                }
            } else {
                $errMsg = "Unsupported file type. Use JPG, PNG or GIF.";
            }
        }

        if (!$errMsg) {
            try {
                $sql = "INSERT INTO service_requests
                         (user_id, request_type, product_id, description, image_path, duration, status, created_at)
                       VALUES
                         (:user_id, :request_type, :product_id, :description, :image_path, :duration, 'new', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':user_id'     => $user_id,
                    ':request_type'=> $request_type,
                    ':product_id'  => $product_id,
                    ':description' => $description,
                    ':image_path'  => $image_path,
                    ':duration'    => $duration,
                ]);
                $sentOK = true;
            } catch (Throwable $e) {
                error_log("DB Error: " . $e->getMessage());
                $errMsg = "Failed to save request. Please try again later.";
            }
        }
    }
}
?>

<div class="background-logo">
  <img src="imgs/logo.webp" alt="Background Logo" />
</div>

<main class="container mt-5 pt-5">
  <h1 class="mb-4 text-center text-white">🛠️ Submit a Request</h1>

  <?php if ($sentOK): ?>
    <div class="alert alert-success shadow-sm" role="alert">
      ✅ Your request has been successfully submitted. A representative will contact you shortly.
    </div>
  <?php elseif ($errMsg): ?>
    <div class="alert alert-danger shadow-sm" role="alert">
      ❌ <?= $errMsg ?>
      <?php if ($login_required): ?>
        <hr>
        <p class="mb-0">
          <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="alert-link">
            Click here to login and submit your request.
          </a>
        </p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="contact.php" enctype="multipart/form-data">
    <?php csrf_field_contact(); ?>

    <?php if (!$is_logged_in): ?>
      <div class="alert alert-warning text-center">
        <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" class="alert-link">
          Please login to submit a service or product request.
        </a>
      </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label d-block fw-bold">Request Type</label>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="request_type" id="typeService" value="service" checked>
            <label class="form-check-label" for="typeService">Service</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="request_type" id="typeProduct" value="product">
            <label class="form-check-label" for="typeProduct">Purchase Product</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="request_type" id="typeGeneral" value="general">
            <label class="form-check-label" for="typeGeneral">General Inquiry</label>
        </div>
    </div>

    <div class="mb-3" id="descriptionField">
      <label for="message" id="messageLabel" class="form-label">Message Details</label>
      <textarea class="form-control" id="message" name="message" rows="4" 
        placeholder="Please describe your needs in detail"></textarea>
    </div>

    <div class="mb-3" id="productCatalogueField" style="display: none;">
        <label for="product_id" class="form-label">Select Product from Catalog</label>
        <select class="form-select" id="product_id" name="product_id">
            <option value="">Choose a product...</option>
            <?php foreach ($activeProducts as $product): ?>
                <option value="<?= htmlspecialchars($product['id']) ?>">
                    <?= htmlspecialchars($product['name']) ?> (<?= htmlspecialchars($product['model']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($activeProducts)): ?>
            <div class="text-danger mt-2">No products currently available for purchase.</div>
        <?php endif; ?>
    </div>

    <div class="mb-3">
      <label for="image" class="form-label">Upload Relevant Image (Optional)</label>
      <input type="file" class="form-control" id="image" name="image" accept="image/*">
    </div>

    <div class="mb-3">
      <label for="duration" class="form-label">Preferred Response/Completion Time</label>
      <select class="form-select" id="duration" name="duration" required>
        <option value="">Select timing...</option>
        <option value="1_day">Within 1 Day</option>
        <option value="3_days">Within 3 Days</option>
        <option value="7_days">Within 1 Week</option>
        <option value="longer">Flexible / Not Urgent</option>
      </select>
    </div>

    <button type="submit" class="btn btn-orange mt-3 px-5">Submit Request</button>
  </form>
</main>

<style>
  main.container .form-label { color: #000000 !important; }
  .btn-orange { background-color: #ff8c00; color: white; border: none; }
  .btn-orange:hover { background-color: #e67e00; color: white; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeService = document.getElementById('typeService');
    const typeProduct = document.getElementById('typeProduct');
    const typeGeneral = document.getElementById('typeGeneral');
    
    const descField = document.getElementById('descriptionField');
    const productField = document.getElementById('productCatalogueField');
    
    const messageLabel = document.getElementById('messageLabel');
    const messageTextarea = document.getElementById('message');
    const productSelect = document.getElementById('product_id');

    function toggleFields() {
        if (typeService.checked) {
            descField.style.display = 'block';
            productField.style.display = 'none';
            messageLabel.innerText = "Problem Description / Service Details";
            messageTextarea.setAttribute('required', 'required');
            productSelect.removeAttribute('required');
        } else if (typeProduct.checked) {
            descField.style.display = 'none';
            productField.style.display = 'block';
            messageTextarea.removeAttribute('required');
            productSelect.setAttribute('required', 'required');
        } else if (typeGeneral.checked) {
            descField.style.display = 'block';
            productField.style.display = 'none';
            messageLabel.innerText = "General Inquiry / Questions";
            messageTextarea.setAttribute('required', 'required');
            productSelect.removeAttribute('required');
        }
    }

    [typeService, typeProduct, typeGeneral].forEach(radio => {
        radio.addEventListener('change', toggleFields);
    });

    toggleFields(); 

    // Handle form submission for guests
    const form = document.querySelector('form');
    const isLoggedIn = <?= json_encode($is_logged_in) ?>;
    
    form.addEventListener('submit', function(e) {
        if (!isLoggedIn) {
            e.preventDefault();
            const loginModalEl = document.getElementById('loginModal');
            if (loginModalEl) {
                const loginModal = new bootstrap.Modal(loginModalEl);
                loginModal.show();
            }
        }
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . "/inc/footer.php"; ?>