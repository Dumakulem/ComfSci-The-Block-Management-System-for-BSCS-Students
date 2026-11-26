<?php

declare(strict_types=1);

/*
 * Top-right notification ("Successfully logged in" / "Successfully logged out").
 * Include it once, just before </body>, on any page:
 *   <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/group-project/auth/toast.php'; ?>
 * It shows $toast if the page set one, otherwise the one-time message in
 * $_SESSION['flash'] (set by login.php), then clears it.
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$authToastMessage = (string) (($toast ?? '') ?: ($_SESSION['flash'] ?? ''));
unset($_SESSION['flash']);

if ($authToastMessage !== ''):
?>
<style>
  .auth-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: min(360px, calc(100vw - 40px));
    padding: 14px 16px;
    font: 500 14px/1.3 'Poppins', system-ui, sans-serif;
    color: #fff;
    background: #141414;
    border-left: 5px solid #ff6b00;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .3);
    animation: auth-toast-in .35s ease-out;
  }
  .auth-toast.is-leaving { opacity: 0; transform: translateX(24px); transition: opacity .3s, transform .3s; }
  .auth-toast__icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    font-weight: 700;
    color: #141414;
    background: #ff6b00;
    border-radius: 50%;
  }
  .auth-toast__close {
    flex: none;
    margin-left: auto;
    padding: 0 2px;
    font-size: 20px;
    line-height: 1;
    color: #fff;
    background: none;
    border: 0;
    cursor: pointer;
  }
  @keyframes auth-toast-in { from { opacity: 0; transform: translateX(24px); } }
  @media (prefers-reduced-motion: reduce) { .auth-toast { animation: none; } }
</style>
<div class="auth-toast" id="auth-toast" role="status" aria-live="polite">
  <span class="auth-toast__icon" aria-hidden="true">&#10003;</span>
  <span><?= htmlspecialchars($authToastMessage, ENT_QUOTES, 'UTF-8') ?></span>
  <button type="button" class="auth-toast__close" aria-label="Close">&times;</button>
</div>
<script>
  (function () {
    var toast = document.getElementById('auth-toast');
    var hide = function () {
      toast.classList.add('is-leaving');
      setTimeout(function () { toast.remove(); }, 350);
    };
    toast.querySelector('.auth-toast__close').addEventListener('click', hide);
    setTimeout(hide, 4000);
    // Stops the message from coming back on refresh.
    if (location.search.indexOf('logged_out') !== -1) {
      history.replaceState(null, '', location.pathname);
    }
  })();
</script>
<?php endif; ?>
