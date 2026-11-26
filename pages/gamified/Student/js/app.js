// Shared by student pages: toast notifications & prototype buttons.
var toastTimer;
function toast(msg) {
  var t = document.getElementById('toast');
  if (!t) {
    // Kung walang toast element, gumawa ng pansamantala sa dulo ng body
    t = document.createElement('div');
    t.id = 'toast';
    t.style.cssText = 'position:fixed; bottom:24px; right:24px; background:#202124; color:#fff; padding:12px 20px; border-radius:12px; font-size:13px; z-index:9999; opacity:0; transition:opacity 0.3s;';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.style.opacity = '1';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function () { t.style.opacity = '0'; }, 2600);
}

document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-a="soon"]');
  if (b) toast(b.dataset.n + ' isn’t part of this prototype yet.');
});