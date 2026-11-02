/* ===========================
   עבאס מלגזות – סקריפטים כלליים
   =========================== */

/**
 * הצגת/הסתרת בלוק "קרא עוד" בעמוד הבית.
 * מצפה לאלמנטים עם מזהים:
 *   #more-info  – הבלוק שמוסתר/מוצג
 *   #arrow      – החץ בכפתור
 */
function toggleMoreInfo() {
  const moreInfo = document.getElementById("more-info");
  const arrow = document.getElementById("arrow");

  if (!moreInfo || !arrow) return;

  const isHidden =
    moreInfo.style.display === "" || moreInfo.style.display === "none";

  moreInfo.style.display = isHidden ? "block" : "none";
  arrow.textContent = isHidden ? "⬆️" : "⬇️";
}

/* אתחול ראשוני כשעמוד נטען */
document.addEventListener("DOMContentLoaded", function () {
  // ודא ש"עוד מידע" נסתר כברירת מחדל, והחץ מצביע למטה
  const moreInfo = document.getElementById("more-info");
  const arrow = document.getElementById("arrow");
  if (moreInfo && arrow) {
    moreInfo.style.display = "none";
    arrow.textContent = "⬇️";
  }

  // טיפ קטן: אם יש כפתורים עם data-toggle="more-info" – נאפשר תמיכה גם בהם
  document.body.addEventListener("click", function (e) {
    const btn = e.target.closest('[data-toggle="more-info"]');
    if (btn) {
      e.preventDefault();
      toggleMoreInfo();
    }
  });
});
