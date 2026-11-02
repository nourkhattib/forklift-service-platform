<?php
  // about.php
  $pageTitle = "אודות - עבאס מלגזות";
  require_once __DIR__ . "/inc/header.php";
  require_once __DIR__ . "/inc/navbar.php";
?>

<!-- CSS קטן מקומי לגלריה של העמוד -->
<style>
  .gallery-container{display:flex;flex-wrap:wrap;justify-content:center}
  .gallery-img{width:100%;height:160px;object-fit:cover;border-radius:12px;transition:transform .3s ease}
  .gallery-img:hover{transform:scale(1.05)}
</style>

<!-- כותרת העמוד (Hero) -->
<header class="bg-dark text-white text-center py-5">
  <div class="container">
    <h1 class="display-5">ברוכים הבאים לעבאס מלגזות</h1>
    <p class="lead">פתרונות חכמים, שירות אישי וניסיון של עשרות שנים</p>
  </div>
</header>

<!-- מי אנחנו + גלריה -->
<section class="container mt-5">
  <div class="row service-box service-glass shadow-lg p-4 rounded align-items-center">
    <div class="col-md-6 text-white">
      <h3 class="mb-3">מי אנחנו?</h3>
      <p>
        עבאס מלגזות היא חברה משפחתית המתמחה במכירה, השכרה, תחזוקה ושירות למלגזות, עגלות, חלקים וצינורות.
        אנו מציעים פתרונות מותאמים אישית לעסקים, כולל שירותי טרייד-אין, שירות עד לבית הלקוח ועוזר חכם מבוסס בינה מלאכותית.
      </p>
      <p>
        המוניטין שלנו נבנה על מקצועיות, אמינות, ושירות מכל הלב. לקוחותינו נהנים מיחס אישי ומליווי טכני גם לאחר הרכישה.
      </p>
    </div>
    <div class="col-md-6">
      <div class="gallery-container row g-2">
        <div class="col-4"><img src="imgs/1.jpg" alt="תמונה 1" class="gallery-img img-fluid shadow-sm"></div>
        <div class="col-4"><img src="imgs/2.jpg" alt="תמונה 2" class="gallery-img img-fluid shadow-sm"></div>
        <div class="col-4"><img src="imgs/3.jpg" alt="תמונה 3" class="gallery-img img-fluid shadow-sm"></div>
        <div class="col-4"><img src="imgs/4.jpg" alt="תמונה 4" class="gallery-img img-fluid shadow-sm"></div>
        <div class="col-4"><img src="imgs/5.jpg" alt="תמונה 5" class="gallery-img img-fluid shadow-sm"></div>
        <div class="col-4"><img src="imgs/6.jpg" alt="תמונה 6" class="gallery-img img-fluid shadow-sm"></div>
      </div>
    </div>
  </div>
</section>

<!-- מה מייחד אותנו -->
<section class="container mt-5">
  <div class="service-box service-glass shadow-lg p-4 rounded text-center text-white">
    <h3 class="mb-3">מה מייחד אותנו?</h3>
    <div class="row">
      <div class="col-md-4">
        <h5>🚚 שירות עד הלקוח</h5>
        <p>ניידות שירות שמגיעות עד אליכם במהירות ובאמינות.</p>
      </div>
      <div class="col-md-4">
        <h5>🤖 בינה מלאכותית</h5>
        <p>עוזר חכם באתר שממליץ, מזהה תקלות ועוזר בבחירת מוצרים.</p>
      </div>
      <div class="col-md-4">
        <h5>💬 ליווי אישי</h5>
        <p>הלקוח תמיד במרכז – ייעוץ, שירות, ואחריות מלאה.</p>
      </div>
    </div>
  </div>
</section>

<?php
  // פוטר אחיד עם המפה קיים כבר בקובץ ה־footer.php
  require_once __DIR__ . "/inc/footer.php";
?>
