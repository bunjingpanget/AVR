<?php
require_once __DIR__ . '/includes/functions.php';
if (is_logged_in()) {
  $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : '';
  $safe = ($redir && (str_starts_with($redir, BASE_URL . '/') || str_starts_with($redir, '/')));
  $dest = $safe ? $redir : ((current_user()['role'] ?? '') === 'admin' ? (BASE_URL . '/admin/index.php') : (BASE_URL . '/index.php'));
  header('Location: ' . $dest); exit;
}
$page_title = 'Sign in';
$error = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = (string)($_POST['username'] ?? '');
  $p = (string)($_POST['password'] ?? '');
  // Attempt login as usual
  if (login($u, $p)) {
    // If the logged in account is admin, block access on this customer login page
    if ((current_user()['role'] ?? '') === 'admin') {
      unset($_SESSION['user']);
      $_SESSION['flash_err'] = 'Admin accounts must sign in via the Admin Login page.';
      $qp = isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '';
      header('Location: ' . BASE_URL . '/signin.php' . $qp); exit;
    }
    $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : (isset($_POST['redirect']) ? (string)$_POST['redirect'] : '');
    $safe = ($redir && (str_starts_with($redir, BASE_URL . '/') || str_starts_with($redir, '/')));
    $dest = $safe ? $redir : (BASE_URL . '/index.php');
    header('Location: ' . $dest); exit;
  } else {
    $_SESSION['flash_err'] = 'Invalid credentials';
    $qp = isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '';
    header('Location: ' . BASE_URL . '/signin.php' . $qp); exit;
  }
}
include __DIR__ . '/includes/header.php';
?>
<style>
  /* Lightweight left-to-right fade-in/stagger animation for auth pages */
  #auth_fx{opacity:0;transform:translateX(8px);transition:opacity .45s ease, transform .45s ease;will-change:opacity,transform}
  #auth_fx.in{opacity:1;transform:none}
  /* Default: slide slightly from right */
  #auth_fx .fx-item, #auth_fx .fx-stagger > *{opacity:0;transform:translateX(12px);transition:opacity .5s ease, transform .5s ease}
  /* From-left variant */
  #auth_fx .fx-item.from-left, #auth_fx .fx-stagger.from-left > *{transform:translateX(-14px)}
  /* From-right explicit (for clarity) */
  #auth_fx .fx-item.from-right, #auth_fx .fx-stagger.from-right > *{transform:translateX(14px)}
  #auth_fx.in .fx-item, #auth_fx.in .fx-stagger > *{opacity:1;transform:none}
  @media (prefers-reduced-motion: reduce){
    #auth_fx, #auth_fx .fx-item, #auth_fx .fx-stagger > *{transition:none !important; transform:none !important}
  }
  #auth_fx .btn{will-change:opacity,transform}
  #auth_fx .form{will-change:opacity,transform}
  /* Avoid overflow clipping subtle slide */
  .auth-grid{overflow:hidden}
  .fx-stagger{overflow:hidden}
</style>
<section class="auth-wrap" style="padding:40px 0">
  <div class="container" style="max-width:1080px">
  <div id="auth_fx" class="auth-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:18px;overflow:hidden;box-shadow:0 12px 28px -8px rgba(0,0,0,.25)">
    <div class="fx-item from-left" style="background:linear-gradient(135deg,#0a4d8f,#0e72d1);color:#eaf6ff;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative">
        <!-- Logo removed -->
        <div style="font-size:14px;letter-spacing:.5px;font-weight:600;opacity:.85;text-align:center">WELCOME BACK</div>
  <h2 style="margin:8px 0 12px;font-size:30px;line-height:1.08;color:#fff;text-align:center;letter-spacing:.5px;font-family:'Segoe UI',Arial,sans-serif;font-weight:700;">Nice to see you again</h2>
        <p style="margin:0 0 18px;max-width:380px;line-height:1.5;color:#dcefff;font-size:14px;text-align:center;margin-left:auto;margin-right:auto">Access your account, track orders and manage services easily.</p>
        <!-- Slogan container removed -->
      </div>
  <div class="fx-item from-right" style="background:#fff;padding:56px 50px;display:flex;flex-direction:column;justify-content:center">
  <h3 style="margin:0 0 22px;color:#0f172a;font-size:26px">Login Account</h3>
  <?php if ($error): ?><div class="form" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:10px 12px;margin:0 0 14px;"><?= h($error); ?></div><?php endif; ?>
  <form method="post" class="fx-stagger from-left" style="display:flex;flex-direction:column;gap:18px">
          <?php if(isset($_GET['redirect'])): ?><input type="hidden" name="redirect" value="<?= h((string)$_GET['redirect']); ?>"><?php endif; ?>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Username or Email
            <input name="username" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Password
            <div style="position:relative;margin-top:6px">
              <input id="login_pwd" type="password" name="password" required style="padding:14px 44px 14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;width:100%" />
              <button type="button" id="toggle_login_pwd" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#eef2f7;border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:12px;color:#0f172a;cursor:pointer">Show</button>
            </div>
          </label>
          <div style="display:flex;align-items:center;justify-content:flex-start;font-size:13px;color:#475569">
            <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" style="width:16px;height:16px"> <span>Keep me signed in</span></label>
          </div>
          <button class="btn" style="width:100%;font-size:15px;padding:14px 0;border-radius:10px;letter-spacing:.5px">Sign In</button>
          <div style="text-align:center;font-size:14px;color:#475569">No account? <a href="<?= BASE_URL ?>/register.php" style="color:#0e72d1;font-weight:600;text-decoration:none">Register</a></div>
        </form>
      </div>
    </div>
  </div>
</section>
<script>
  (function(){
    var p=document.getElementById('login_pwd'); var t=document.getElementById('toggle_login_pwd'); if(!p||!t) return;
    t.addEventListener('click', function(){ var vis=p.type==='text'; p.type=vis?'password':'text'; t.textContent=vis?'Show':'Hide'; t.setAttribute('aria-label', vis? 'Show password' : 'Hide password'); });
  })();
</script>
<script>
  // Staggered fade-in on load
  window.addEventListener('DOMContentLoaded', function(){
    var root = document.getElementById('auth_fx'); if(!root) return;
    var list = root.querySelectorAll('.fx-item, .fx-stagger > *');
    var base = 120, step = 60;
    for (var i=0;i<list.length;i++){ list[i].style.transitionDelay = (base + i*step) + 'ms'; }
    // double rAF to ensure styles applied before toggling
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ root.classList.add('in'); }); });
  });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
