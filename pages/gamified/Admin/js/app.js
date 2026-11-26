// Shared by every page: toast + "not part of this prototype" buttons.
var toastTimer;
function toast(msg) {
  var t = document.getElementById('toast');
  t.textContent = msg;
  t.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(function () { t.classList.remove('show'); }, 2600);
}
document.addEventListener('click', function (e) {
  var b = e.target.closest('[data-a="soon"]');
  if (b) toast(b.dataset.n + ' isn’t part of this prototype.');
});