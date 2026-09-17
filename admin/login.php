<?php
require_once __DIR__ . '/../includes/functions.php';

// If already logged in
if (is_logged_in()) {
  $role = (string) (current_user()['role'] ?? '');
  if ($role === 'admin') { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
  // Logged in as customer should not access admin login
  header('Location: ' . BASE_URL . '/index.php'); exit;
}

$page_title = 'Admin Login';
$error = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = (string)($_POST['username'] ?? '');
  $p = (string)($_POST['password'] ?? '');
  if (login($u, $p)) {
    if ((current_user()['role'] ?? '') === 'admin') {
      // Optional redirect param (allow only relative within site)
      $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : (isset($_POST['redirect']) ? (string)$_POST['redirect'] : '');
      $safe = ($redir && (str_starts_with($redir, BASE_URL . '/admin/') || str_starts_with($redir, '/admin/')));
      $dest = $safe ? $redir : (BASE_URL . '/admin/index.php');
      header('Location: ' . $dest); exit;
    }
  // Not an admin: clear user and show error
  unset($_SESSION['user']);
    $_SESSION['flash_err'] = 'Only admin accounts can sign in here.';
    header('Location: ' . BASE_URL . '/admin/login.php'); exit;
  } else {
    $_SESSION['flash_err'] = 'Invalid credentials';
    $qp = isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '';
    header('Location: ' . BASE_URL . '/admin/login.php' . $qp); exit;
  }
}

include __DIR__ . '/../includes/header.php';
?>
<section class="auth-wrap" style="padding:40px 0">
  <div class="container" style="max-width:920px">
    <div class="auth-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:18px;overflow:hidden;box-shadow:0 12px 28px -8px rgba(0,0,0,.25)">
      <div style="background:linear-gradient(135deg,#111827,#0b2a4a);color:#eaf6ff;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative">
        <div style="font-size:14px;letter-spacing:.5px;font-weight:700;opacity:.85;text-align:center">ADMIN ACCESS</div>
        <h2 style="margin:8px 0 12px;font-size:28px;line-height:1.08;color:#fff;text-align:center;letter-spacing:.5px;font-family:'Segoe UI',Arial,sans-serif;font-weight:800;">Sign in to Dashboard</h2>
        <p style="margin:0 0 18px;max-width:380px;line-height:1.5;color:#dcefff;font-size:14px;text-align:center;margin-left:auto;margin-right:auto">This area is restricted. Customer accounts are not permitted.</p>
      </div>
      <div style="background:#fff;padding:56px 50px;display:flex;flex-direction:column;justify-content:center">
        <h3 style="margin:0 0 22px;color:#0f172a;font-size:26px">Admin Login</h3>
        <?php if ($error): ?><div class="form" style="background:#fee2e2;border:1px solid #fecaca;color:#991b1b;border-radius:8px;padding:10px 12px;margin:0 0 14px;"><?= h($error); ?></div><?php endif; ?>
        <form method="post" style="display:flex;flex-direction:column;gap:18px">
          <?php if(isset($_GET['redirect'])): ?><input type="hidden" name="redirect" value="<?= h((string)$_GET['redirect']); ?>"><?php endif; ?>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Username or Email
            <input name="username" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Password
            <div style="position:relative;margin-top:6px">
              <input id="admin_pwd" type="password" name="password" required style="padding:14px 44px 14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;width:100%" />
              <button type="button" id="toggle_admin_pwd" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#eef2f7;border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:12px;color:#0f172a;cursor:pointer">Show</button>
            </div>
          </label>
          <button class="btn" style="width:100%;font-size:15px;padding:14px 0;border-radius:10px;letter-spacing:.5px">Sign In</button>
          <div style="text-align:center;font-size:13px;color:#64748b;margin-top:6px">Not an admin? <a href="<?= BASE_URL ?>/signin.php" style="color:#0e72d1;font-weight:600;text-decoration:none">Customer Sign in</a></div>
        </form>
      </div>
    </div>
  </div>
</section>
<script>
  (function(){
    var p=document.getElementById('admin_pwd'); var t=document.getElementById('toggle_admin_pwd'); if(!p||!t) return;
    t.addEventListener('click', function(){ var vis=p.type==='text'; p.type=vis?'password':'text'; t.textContent=vis?'Show':'Hide'; t.setAttribute('aria-label', vis? 'Show password' : 'Hide password'); });
  })();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
