<?php
require_once __DIR__ . '/includes/functions.php';
// Allow showing Terms overlay even when logged in immediately after registration
if (is_logged_in() && empty($_SESSION['show_terms_after_register'])) { header('Location: ' . BASE_URL . '/index.php'); exit; }
$page_title = 'Register';
$error = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']); $ok = isset($_SESSION['flash_ok']) ? (bool)$_SESSION['flash_ok'] : false; unset($_SESSION['flash_ok']);
// Handle Terms acceptance post (simple session gate right after registration)
if (($_SERVER['REQUEST_METHOD'] === 'POST') && isset($_POST['accept_terms'])) {
  if (is_logged_in() && !empty($_SESSION['show_terms_after_register'])) {
    unset($_SESSION['show_terms_after_register']);
    $_SESSION['accepted_terms'] = true; // informational flag; not enforced globally
    $dest = $_SESSION['post_register_dest'] ?? (BASE_URL . '/index.php');
    unset($_SESSION['post_register_dest']);
    header('Location: ' . $dest); exit;
  }
}
// OTP / Registration handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Use centralized helpers in includes/functions.php for creating and verifying codes

  // Verify OTP submission
  if (isset($_POST['verify_otp'])) {
    $code = trim((string)($_POST['otp'] ?? ''));
    if (empty($_SESSION['pending_reg'])) {
      $_SESSION['flash_err'] = 'No pending registration found. Please register again.';
      header('Location: ' . BASE_URL . '/register.php'); exit;
    }
    $pending_email = $_SESSION['pending_reg']['email'] ?? '';
    $res = verify_code_for_email($pending_email, $code, 'registration');
    if (!$res['ok']) {
      $_SESSION['flash_err'] = $res['msg'] ?? 'Incorrect verification code. Please try again.';
      header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
    }
    // OTP ok -> finalize registration using stored data
    $reg = $_SESSION['pending_reg'];
    unset($_SESSION['pending_reg']);
    $err = null; $ok = register_user($reg, $err);
    if ($ok) {
      login($reg['email'] ?? '', $reg['password'] ?? '');
      $redir = isset($_GET['redirect']) ? (string)$_GET['redirect'] : (isset($reg['redirect']) ? (string)$reg['redirect'] : '');
      $safe = ($redir && (str_starts_with($redir, BASE_URL . '/') || str_starts_with($redir, '/')));
      $dest = $safe ? $redir : (BASE_URL . '/index.php');
      $_SESSION['show_terms_after_register'] = 1;
      $_SESSION['post_register_dest'] = $dest;
      header('Location: ' . BASE_URL . '/register.php?t=1'); exit;
    } else {
      $_SESSION['flash_err'] = $err ?: 'Registration failed while finalizing.';
      header('Location: ' . BASE_URL . '/register.php'); exit;
    }
  }

  // Resend OTP
  if (isset($_POST['resend_otp'])) {
    if (empty($_SESSION['pending_reg'])) {
      $_SESSION['flash_err'] = 'No pending registration to resend OTP for.';
      header('Location: ' . BASE_URL . '/register.php'); exit;
    }
    $pending_email = $_SESSION['pending_reg']['email'] ?? '';
    $sent = create_and_send_verification_code($pending_email, 'registration');
    if (!$sent['ok']) {
      $_SESSION['flash_err'] = $sent['msg'] ?? 'Unable to send code. Try later.';
      header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
    }
    // success
    $_SESSION['flash_ok'] = 'A new verification code has been sent to your email.';
    header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
  }

  // Cancel pending registration
  if (isset($_POST['cancel_otp'])) {
    unset($_SESSION['pending_reg']);
    $_SESSION['flash_err'] = 'Registration cancelled.';
    header('Location: ' . BASE_URL . '/register.php'); exit;
  }

  // Initial registration submission -> validate and create pending_reg + send OTP
  // Server-side: enforce age >= 18 based on dob if provided
  $dob_raw = trim((string)($_POST['dob'] ?? ''));
  if ($dob_raw !== '') {
    $dob_ts = strtotime($dob_raw);
    if ($dob_ts === false) {
      $_SESSION['flash_err'] = 'Enter a valid Date of Birth.';
      header('Location: ' . BASE_URL . '/register.php' . (isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '')); exit;
    }
    $age = (int) floor((time() - $dob_ts) / (365.2425 * 24 * 60 * 60));
    if ($age < 18) {
      $_SESSION['flash_err'] = 'You must be 18 years or older to register an account.';
      header('Location: ' . BASE_URL . '/register.php' . (isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '')); exit;
    }
  }

  if (($_POST['password'] ?? '') !== ($_POST['confirm'] ?? '')) {
        $_SESSION['flash_err'] = 'Passwords do not match';
        header('Location: ' . BASE_URL . '/register.php' . (isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '')); exit;
  } else {
    // Strong password validation (server-side)
    $pwd = (string)($_POST['password'] ?? '');
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $name = strtolower(preg_replace('/\s+/', '', (string)($_POST['name'] ?? '')));
    $username = strtolower(trim((string)($_POST['username'] ?? '')));
    $lc = strtolower($pwd);
    $localEmail = $email ? explode('@', $email, 2)[0] : '';
    $weak = '';
    if (strlen($pwd) < 8) { $weak = 'Password must be at least 8 characters.'; }
    elseif (!preg_match('/[A-Z]/', $pwd)) { $weak = 'Include at least one uppercase letter.'; }
    elseif (!preg_match('/[a-z]/', $pwd)) { $weak = 'Include at least one lowercase letter.'; }
    elseif (!preg_match('/\d/', $pwd)) { $weak = 'Include at least one number.'; }
    elseif (!preg_match('/[^A-Za-z0-9]/', $pwd)) { $weak = 'Include at least one symbol.'; }
    elseif (preg_match('/1234|2345|3456|4567|5678|6789|0123|abcd|qwerty|password|admin|welcome/i', $pwd)) { $weak = 'Avoid common or sequential patterns.'; }
    elseif ($localEmail && str_contains($lc, $localEmail)) { $weak = 'Avoid using your email in the password.'; }
    elseif ($username && str_contains($lc, $username)) { $weak = 'Avoid using your username in the password.'; }
    elseif ($name && strlen($name) >= 3 && str_contains($lc, $name)) { $weak = 'Avoid using your name in the password.'; }
    if ($weak) {
      $_SESSION['flash_err'] = 'Weak password: ' . $weak;
      header('Location: ' . BASE_URL . '/register.php' . (isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '')); exit;
    }
        // Normalize and validate phone: digits only, 8-15 length
        $rawPhone = (string)($_POST['phone'] ?? '');
        $digitsPhone = preg_replace('/\D+/', '', $rawPhone);
        if (strlen($digitsPhone) < 8 || strlen($digitsPhone) > 15) {
          $_SESSION['flash_err'] = 'Enter a valid phone number (numbers only, 8–15 digits).';
          header('Location: ' . BASE_URL . '/register.php' . (isset($_GET['redirect']) ? ('?redirect=' . urlencode((string)$_GET['redirect'])) : '')); exit;
        }
        // Prepare pending registration payload (do NOT finalize yet)
        $pending = $_POST;
        $pending['phone'] = $digitsPhone;
        // Keep only fields needed by register_user
        $allowed = ['name','username','dob','gender','email','phone','password','confirm','province','location','redirect'];
        $clean = [];
        foreach ($allowed as $k) { if (isset($pending[$k])) $clean[$k] = $pending[$k]; }
        // store pending data in session for OTP verification
        $_SESSION['pending_reg'] = $clean;
        $sent = create_and_send_verification_code($clean['email'] ?? '', 'registration');
        if (!$sent['ok']) {
          $_SESSION['flash_err'] = $sent['msg'] ?? 'Unable to send verification code. Try again later.';
          unset($_SESSION['pending_reg']);
          header('Location: ' . BASE_URL . '/register.php'); exit;
        }
        $_SESSION['flash_ok'] = 'A verification code was sent to your email. Enter it to complete registration.';
        header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
  }
}
include __DIR__ . '/includes/header.php';
?>
<?php if (!empty($_SESSION['show_terms_after_register'])): ?>
  <style>
    html, body{overflow:hidden}
    .tc-wrap{position:relative;min-height:calc(100vh - 0px);}
    .tc-bg{position:fixed;inset:0;z-index:0;}
    .tc-bg iframe{position:absolute;inset:0;width:100%;height:100%;border:0;filter:blur(4px) saturate(0.9) brightness(0.92);pointer-events:none}
    .tc-overlay{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:28px}
    .tc-card{width:100%;max-width:900px;background:#ffffffeb;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 12px 28px -8px rgba(2,23,52,.35);overflow:hidden}
    .tc-head{padding:16px 18px;background:linear-gradient(135deg,#0a4d8f,#0e72d1);color:#fff;display:flex;align-items:center;gap:10px}
    .tc-head img{width:34px;height:34px;border-radius:8px;background:#fff}
    .tc-head h3{margin:0;font-size:18px;letter-spacing:.3px}
    .tc-body{padding:16px 18px;max-height:60vh;overflow:auto;color:#0f172a}
    .tc-body h4{margin:12px 0 8px;font-size:16px;color:#0b3c74}
    .tc-body p, .tc-body li{font-size:14px;line-height:1.55;color:#334155}
    .tc-body ul{margin:6px 0 12px 18px}
    .tc-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 16px;border-top:1px solid #e5e7eb;background:#f8fafc}
    .tc-agree{display:flex;align-items:center;gap:8px;color:#0f172a;font-weight:600}
    .tc-btn{appearance:none;border:0;border-radius:10px;background:linear-gradient(135deg,#024787,#0475d2);color:#fff;padding:10px 16px;font-weight:800;cursor:not-allowed;opacity:.65}
    .tc-btn.enabled{cursor:pointer;opacity:1}
    @media (max-width: 900px){ .tc-body{max-height:64vh} }
  </style>
  <div class="tc-wrap">
    <div class="tc-bg" aria-hidden="true">
      <iframe src="<?= BASE_URL ?>/index.php" title="Shop Preview"></iframe>
    </div>
    <div class="tc-overlay">
      <div class="tc-card" role="dialog" aria-modal="true" aria-labelledby="tc-title">
        <div class="tc-head">
          <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="FNPSS">
          <h3 id="tc-title">FNPSS Terms and Conditions</h3>
        </div>
        <div class="tc-body">
          <p><strong>FNPSS</strong> (Fpb Network and Power Solutions Services) offers high‑quality products like AVRs, UPS, Solar solutions, and LED lighting, plus professional services such as repair, installation, and after‑sales support.</p>

          <h4>1) Account and Password</h4>
          <ul>
            <li>You create your password once during registration. For your security, it cannot be changed later.</li>
            <li>Keep your login details confidential and use your own account only.</li>
          </ul>

          <h4>2) How to Order</h4>
          <ul>
            <li>Browse products, open a product page, and click “Add to Cart” or “Order Now”.</li>
            <li>Open Cart and proceed to Checkout to confirm delivery details.</li>
            <li>The earliest possible delivery date is shown on the Checkout page and depends on your address and current schedule.</li>
          </ul>

          <h4>3) Chatbot and Contacting the Seller</h4>
          <ul>
            <li>Chatbot: Click the ✉ chat  in the top navigation to open the assistant. Type <code>@seller</code> followed by your message to contact the seller.</li>
            <li>Other channels: Email admin@fnpss.com or message our Facebook page “Fpb Network and Power Solutions Services”. You can also call 0917 836 5017.</li>
          </ul>

          <h4>4) Payment</h4>
          <ul>
            <li>Default payment method is Cash on Delivery (COD).</li>
            <li>If you prefer online payment, contact us via email, Facebook page, or the chatbot to arrange it before delivery.</li>
          </ul>

          <h4>5) Coupons and Discounts</h4>
          <ul>
            <li>Automatic promos: Buy 4 pieces of any product to get 5% OFF; buy 5+ pieces for FREE shipping (where applicable).</li>
            <li>If you have an FNPSS coupon, share the code via the chatbot or email so we can validate and apply it to your order.</li>
          </ul>

          <h4>6) Services and Scheduling</h4>
          <ul>
            <li>Service requests follow the agreed schedule. Busy dates may shift the earliest delivery or service date; the Checkout page shows the soonest available date.</li>
          </ul>

          <p style="margin-top:12px;color:#0f172a"><strong>By continuing, you acknowledge these Terms and agree to shop under these rules.</strong></p>
        </div>
        <form method="post" class="tc-foot" onsubmit="return TCAgree.submit()">
          <label class="tc-agree">
            <input type="checkbox" id="tc_chk" /> I agree to the Terms and Conditions
          </label>
          <input type="hidden" name="accept_terms" value="1" />
          <button type="submit" id="tc_btn" class="tc-btn" disabled>Continue to Shop</button>
        </form>
      </div>
    </div>
  </div>
  <script>
    var TCAgree = (function(){
      var chk, btn; function sync(){ if(!chk||!btn) return; var on = !!chk.checked; btn.disabled = !on; btn.classList.toggle('enabled', on); btn.style.cursor = on ? 'pointer' : 'not-allowed'; }
      function submit(){ if(!chk||!chk.checked){ return false; } return true; }
      window.addEventListener('DOMContentLoaded', function(){ chk=document.getElementById('tc_chk'); btn=document.getElementById('tc_btn'); if(chk){ chk.addEventListener('change', sync); sync(); } });
      return { submit: submit };
    })();
  </script>
  <?php include __DIR__ . '/includes/footer.php'; ?>
<?php else: ?>
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
  .auth-grid{overflow:hidden}
  .fx-stagger{overflow:hidden}
</style>
<section class="auth-wrap" style="padding:40px 0">
  <div class="container" style="max-width:1080px">
  <div id="auth_fx" class="auth-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0;border-radius:18px;overflow:hidden;box-shadow:0 12px 28px -8px rgba(0,0,0,.25)">
    <div class="fx-item from-left" style="background:linear-gradient(135deg,#0a4d8f,#0e72d1);color:#eaf6ff;display:flex;flex-direction:column;justify-content:center;padding:56px 48px;position:relative">
        <div style="font-size:14px;letter-spacing:.5px;font-weight:600;opacity:.85;text-align:center">WELCOME</div>
        <h2 style="margin:8px 0 12px;font-size:30px;line-height:1.08;color:#fff;text-align:center;letter-spacing:.5px;font-family:'Segoe UI',Arial,sans-serif;font-weight:700;">Create your account</h2>
        <p style="margin:0 0 18px;max-width:380px;line-height:1.5;color:#dcefff;font-size:14px;text-align:center;margin-left:auto;margin-right:auto">Sign up to access your account, track orders and manage services easily.</p>
      </div>
  <div class="fx-item from-right" style="background:#fff;padding:56px 50px;display:flex;flex-direction:column;justify-content:center">
        <h3 style="margin:0 0 22px;color:#0f172a;font-size:26px">Register Account</h3>
        <?php if ($error): ?><div class="form" style="background:#fee2e2;border-color:#fecaca;margin:0 0 14px;"><?= h($error); ?></div><?php endif; ?>
  <form method="post" class="fx-stagger from-left" style="display:flex;flex-direction:column;gap:18px" onsubmit="return handleRegisterSubmit()">
          <?php if(isset($_GET['redirect'])): ?><input type="hidden" name="redirect" value="<?= h((string)$_GET['redirect']); ?>"><?php endif; ?>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Name
            <input name="name" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Username
            <input name="username" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <div class="grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px">
            <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Date of Birth
            <input type="date" name="dob" required max="<?= date('Y-m-d'); ?>" style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
            <small id="dob_msg" style="display:block;margin-top:6px;color:#ef4444; font-weight:700;">You must be 18 years or older to register an account.</small>
            </label>
            <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Gender
              <select name="gender" style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px">
                <option value="">Prefer not to say</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
              </select>
            </label>
          </div>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Email
            <input type="email" name="email" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Phone
            <input name="phone" inputmode="numeric" pattern="[0-9]{8,15}" maxlength="15" required style="margin-top:6px;padding:14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px" />
          </label>
          <div>
            <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:6px">Address</div>
            <div class="grid" style="display:grid;grid-template-columns:1fr;gap:10px">
              <select id="addr_province" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px" required></select>
              <input type="hidden" name="province" id="addr_province_name" />
              <select id="addr_city" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px" required></select>
              <select id="addr_barangay" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px" required></select>
              <input id="addr_street" placeholder="Street (optional)" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px" />
              <input id="addr_house" placeholder="House/Unit #" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:14px" required />
              <input type="hidden" name="location" id="addr_location_hidden" />
            </div>
          </div>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Password
            <div style="position:relative;margin-top:6px">
              <input id="reg_pwd" type="password" name="password" required style="padding:14px 44px 14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;width:100%" autocomplete="new-password" />
              <button type="button" id="toggle_reg_pwd" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#eef2f7;border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:12px;color:#0f172a;cursor:pointer">Show</button>
            </div>
            <small id="pwd_hint" style="display:block;margin-top:6px;color:#64748b">Use at least 8 characters with upper & lower case, numbers and symbols. Avoid names, email, or common words. Note: password is set once and cannot be changed.</small>
            <div id="pwd_strength" aria-live="polite" style="margin-top:6px;font-size:13px;font-weight:700;color:#ef4444">Weak</div>
          </label>
          <label style="display:flex;flex-direction:column;font-size:14px;font-weight:600;color:#0f172a">Confirm Password
            <div style="position:relative;margin-top:6px">
              <input id="reg_pwd2" type="password" name="confirm" required style="padding:14px 44px 14px 16px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px;width:100%" autocomplete="new-password" />
              <button type="button" id="toggle_reg_pwd2" aria-label="Show password" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:#eef2f7;border:1px solid #cbd5e1;border-radius:8px;padding:6px 10px;font-size:12px;color:#0f172a;cursor:pointer">Show</button>
            </div>
          </label>
          <button id="reg_btn" class="btn" style="width:100%;font-size:15px;padding:14px 0;border-radius:10px;letter-spacing:.5px" disabled>Register</button>
        </form>
      </div>
    </div>
  </div>
</section>
<script>
  // Staggered fade-in on load
  window.addEventListener('DOMContentLoaded', function(){
    var root = document.getElementById('auth_fx'); if(!root) return;
    var list = root.querySelectorAll('.fx-item, .fx-stagger > *');
    var base = 120, step = 60;
    for (var i=0;i<list.length;i++){ list[i].style.transitionDelay = (base + i*step) + 'ms'; }
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ root.classList.add('in'); }); });
  });
</script>
<script>
  // Password strength and visibility helpers (simple, fast, responsive)
  function evalStrength(pwd, email, username, name){
    var s = (pwd||''); var lc = s.toLowerCase(); var score = 0; var msg = '';
    if(s.length >= 8) score++;
    if(/[A-Z]/.test(s)) score++;
    if(/[a-z]/.test(s)) score++;
    if(/\d/.test(s)) score++;
    if(/[^A-Za-z0-9]/.test(s)) score++;
    var bad = /(1234|2345|3456|4567|5678|6789|0123|abcd|qwerty|password|admin|welcome)/i.test(s);
    var localEmail = (email||'').split('@')[0]||'';
    var hitsPersonal = (localEmail && lc.includes(localEmail)) || (username && lc.includes((username||'').toLowerCase())) || (name && name.length>=3 && lc.includes((name||'').toLowerCase().replace(/\s+/g,'')));
    var strong = (s.length>=8 && /[A-Z]/.test(s) && /[a-z]/.test(s) && /\d/.test(s) && /[^A-Za-z0-9]/.test(s) && !bad && !hitsPersonal);
    if(strong) return {level:'strong', text:'Strong', color:'#16a34a'};
    // mild if length>=8 and at least 3 categories but not strong
    var cats = 0; if(/[A-Z]/.test(s)) cats++; if(/[a-z]/.test(s)) cats++; if(/\d/.test(s)) cats++; if(/[^A-Za-z0-9]/.test(s)) cats++;
    if(s.length>=8 && cats>=3 && !bad && !hitsPersonal) return {level:'mild', text:'Mild', color:'#f59e0b'};
    return {level:'weak', text:'Weak', color:'#ef4444'};
  }
  function setPwdVisible(input, btn){ if(!input||!btn) return; var vis = input.type==='text'; input.type = vis? 'password':'text'; btn.textContent = vis? 'Show':'Hide'; btn.setAttribute('aria-label', vis? 'Show password':'Hide password'); }
  function composeAddress(){
    // copy visible selected option labels for province
    var pv = document.getElementById('addr_province');
    var pvName = pv && pv.options[pv.selectedIndex] ? pv.options[pv.selectedIndex].text : '';
    document.getElementById('addr_province_name').value = pvName;
    if (window.AddressPicker) {
      // ensure hidden location up-to-date
      var h = document.getElementById('addr_location_hidden');
      if(!h || !h.value){
        var parts=[document.getElementById('addr_house')?.value, document.getElementById('addr_street')?.value, document.getElementById('addr_barangay')?.options[document.getElementById('addr_barangay').selectedIndex]?.text, document.getElementById('addr_city')?.options[document.getElementById('addr_city').selectedIndex]?.text, pvName].filter(Boolean);
        h.value = parts.join(', ');
      }
    }
  }
  function handleRegisterSubmit(){
    composeAddress();
    var pwd = document.getElementById('reg_pwd')?.value || '';
    var pwd2 = document.getElementById('reg_pwd2')?.value || '';
    if (pwd !== pwd2) { alert('Passwords do not match.'); return false; }
    var email = document.querySelector('input[name="email"]')?.value||'';
    var username = document.querySelector('input[name="username"]')?.value||'';
    var name = document.querySelector('input[name="name"]')?.value||'';
    var res = evalStrength(pwd, email, username, name);
    if (res.level !== 'strong') { alert('Please choose a stronger password: 8+ chars with upper, lower, number, and symbol, avoiding common words.'); return false; }
    // DOB/age check
    var dob = document.querySelector('input[name="dob"]')?.value || '';
    if (!dob) { alert('Please enter your Date of Birth. You must be 18 years or older to register.'); return false; }
    var d = new Date(dob); if (isNaN(d.getTime())) { alert('Enter a valid Date of Birth.'); return false; }
    var now = new Date(); var age = now.getFullYear() - d.getFullYear(); var m = now.getMonth() - d.getMonth(); if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--; if (age < 18) { alert('You must be 18 years or older to register an account.'); return false; }
    return true;
  }
  window.addEventListener('DOMContentLoaded', function(){
    // Numeric-only guard for phone
    var ph = document.querySelector('input[name="phone"]');
    if (ph) {
      var f = function(){ ph.value = (ph.value || '').replace(/\D+/g,'').slice(0,15); };
      ph.addEventListener('input', f);
      ph.addEventListener('blur', f);
    }
    if (window.AddressPicker) AddressPicker.mount({
      province:'#addr_province', city:'#addr_city', barangay:'#addr_barangay',
      street:'#addr_street', house:'#addr_house', hiddenLocation:'#addr_location_hidden'
    });
    // Hook up password toggles and live strength
    var p = document.getElementById('reg_pwd'); var p2 = document.getElementById('reg_pwd2');
    var t1 = document.getElementById('toggle_reg_pwd'); var t2 = document.getElementById('toggle_reg_pwd2');
    if(t1 && p) t1.addEventListener('click', function(){ setPwdVisible(p,t1); });
    if(t2 && p2) t2.addEventListener('click', function(){ setPwdVisible(p2,t2); });
    var sEl = document.getElementById('pwd_strength'); var btn = document.getElementById('reg_btn');
      var dobInput = document.querySelector('input[name="dob"]');
      var dobMsg = document.getElementById('dob_msg');
      function calcDobValid(){
        if(!dobInput) return false;
        var v = dobInput.value || '';
        if(!v) return false;
        var d = new Date(v); if (isNaN(d.getTime())) return false;
        var now = new Date(); var age = now.getFullYear() - d.getFullYear(); var m = now.getMonth() - d.getMonth(); if (m < 0 || (m === 0 && now.getDate() < d.getDate())) age--; return age >= 18;
      }
    function syncStrength(){
      if(!p) return; var email = document.querySelector('input[name="email"]')?.value||'';
      var username = document.querySelector('input[name="username"]')?.value||'';
      var name = document.querySelector('input[name="name"]')?.value||'';
      var res = evalStrength(p.value||'', email, username, name);
      if(sEl){ sEl.textContent = res.text; sEl.style.color = res.color; }
      if(btn){ btn.disabled = (res.level !== 'strong'); btn.style.opacity = btn.disabled? .6 : 1; btn.style.cursor = btn.disabled? 'not-allowed' : 'pointer'; }
    }
    if(p){ p.addEventListener('input', syncStrength); p.addEventListener('blur', syncStrength); setTimeout(syncStrength, 0); }
    if(dobInput){ dobInput.addEventListener('change', syncStrength); dobInput.addEventListener('blur', syncStrength); }
  });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>

<?php
// OTP overlay HTML injection: show when ?otp=1 or a pending_reg exists in session
$show_otp = (isset($_GET['otp']) && $_GET['otp'] === '1') || !empty($_SESSION['pending_reg']);
if ($show_otp):
  $pending_email = $_SESSION['pending_reg']['email'] ?? '';
  ?>
  <style>
    /* Redesigned OTP overlay to match screenshot */
    .otp-overlay{position:fixed;inset:0;display:flex;align-items:center;justify-content:center;padding:28px;z-index:20000;background:rgba(10,18,34,0.45);backdrop-filter:blur(4px)}
    .otp-card{width:100%;max-width:640px;background:#fff;border-radius:12px;box-shadow:0 22px 50px rgba(3,9,23,0.45);overflow:hidden}
    .otp-head{display:flex;align-items:center;gap:14px;padding:14px 18px;background:linear-gradient(90deg,#0b4d8f,#0e72d1);color:#fff;border-top-left-radius:12px;border-top-right-radius:12px}
    .otp-head .logo{width:44px;height:44px;border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;padding:6px}
    .otp-head .logo img{max-width:100%;height:auto}
    .otp-head h3{margin:0;font-size:16px;letter-spacing:.2px}
    .otp-body{padding:22px}
    .otp-message{font-size:14px;color:#0f172a;line-height:1.45;margin-bottom:18px}
    .otp-message strong{color:#0b3c74}
    .otp-input{display:block;width:100%;padding:18px 20px;font-size:20px;border:1px solid #e6eef8;border-radius:10px;text-align:center;letter-spacing:10px;color:#0b3c74;background:#fbfdff}
    .otp-row{display:flex;align-items:center;gap:12px;margin-top:16px}
    .otp-actions{display:flex;align-items:center;gap:12px}
    .otp-verify{background:linear-gradient(90deg,#0b66ab,#0e72d1);color:#fff;padding:10px 18px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
    .otp-resend{background:#0f172a;color:#fff;padding:10px 14px;border-radius:8px;border:0;cursor:pointer}
    .otp-expiry{font-size:13px;color:#64748b;margin-left:8px}
    .otp-footer{padding:12px 22px;border-top:1px solid #f1f5f9;background:#fff;display:flex;justify-content:space-between;align-items:center;font-size:13px;color:#475569}
    @media (max-width:640px){ .otp-card{margin:0 8px} .otp-input{font-size:18px;padding:14px;letter-spacing:8px} }
    .otp-close{position:absolute;right:12px;top:10px;width:48px;height:48px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.18);box-shadow:0 6px 18px rgba(2,6,23,0.25);font-size:20px;cursor:pointer}
    .otp-close:hover{background:rgba(255,255,255,0.18);transform:translateY(-1px)}
  </style>

  <div class="otp-overlay" role="dialog" aria-modal="true" aria-labelledby="otp-title">
    <div class="otp-card">
      <div class="otp-head" style="position:relative">
        <div class="logo"><img src="<?= BASE_URL ?>/assets/images/logo.png" alt="FNPSS"></div>
        <h3 id="otp-title">Verify your Email</h3>
        <form method="post" id="otp_cancel_form" style="position:absolute;right:12px;top:10px;margin:0">
          <button aria-label="Close" title="Close" type="submit" name="cancel_otp" class="otp-close">&times;</button>
        </form>
      </div>
      <div class="otp-body">
        <div class="otp-message">We sent a 6-digit verification code to <strong><?= h($pending_email); ?></strong>. Enter the code below to complete your registration.</div>

        <form method="post" id="otp_form" onsubmit="return submitOtp()" aria-describedby="otp_help" novalidate>
          <input type="email" name="_otp_email" id="otp_email_hidden" value="<?= h($pending_email); ?>" hidden />
          <input type="text" name="otp" id="otp_input" class="otp-input" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="Enter 6-digit code" required aria-required="true" />

          <div class="otp-row">
            <div class="otp-actions">
              <button type="submit" name="verify_otp" class="otp-verify">Verify Email Address</button>
              <div class="otp-expiry" id="otp_help">Code expires in 10 minutes.</div>
            </div>
            <div style="margin-left:auto">
              <!-- Resend is part of same form so native validation triggers when otp field is empty -->
              <button type="submit" name="resend_otp" class="otp-resend" id="otp_resend_btn">Resend Code</button>
            </div>
          </div>
        </form>
      </div>

      <div class="otp-footer">
        <div>Secure verification • 2‑factor sign-up</div>
        <div>Need help? Type <strong>@seller</strong> in chat, or <a href="#" id="otp-open-terms">View Terms &amp; Conditions</a> • <a href="mailto:admin@fnpss.com">admin@fnpss.com</a> • <a href="tel:09178365017">0917 836 5017</a></div>
      </div>
    </div>
  </div>

  <script>
    function submitOtp(){
      var form = document.getElementById('otp_form');
      var input = document.getElementById('otp_input');
      var v = (input.value || '').replace(/\s+/g,'');
      // If user pressed Resend (name= resends) we still want native validation to run when OTP empty
      // The browser will display built-in "Please fill out this field." when required is present
      if(!/^[0-9]{6}$/.test(v)){
        // Let browser show native validation message where possible
        input.focus();
        if (typeof input.reportValidity === 'function') { input.reportValidity(); }
        else { alert('Enter the 6-digit code'); }
        return false;
      }
      input.value = v; // trimmed
      return true;
    }
    // auto-focus, numeric-only guard and ARIA live updates + cooldown UI
    window.addEventListener('DOMContentLoaded', function(){
      setTimeout(function(){
        var el = document.getElementById('otp_input');
        var resendBtn = document.getElementById('otp_resend_btn');
        var live = document.createElement('div'); live.setAttribute('aria-live','polite'); live.setAttribute('id','otp_aria_live'); live.style.position='absolute'; live.style.left='-9999px'; live.style.height='1px'; live.style.overflow='hidden'; document.body.appendChild(live);
        if (el){ el.focus(); el.addEventListener('input', function(){ this.value = (this.value||'').replace(/\D+/g,'').slice(0,6); }); }
        // when form submitted due to resend, if server returns success we'll disable resend for 60s via server-side but also provide client feedback
        if (resendBtn) {
          // nothing to bind here—server enforces cooldown. Keep accessible label
          resendBtn.addEventListener('click', function(e){
            // if otp empty, let required validation surface
            if (!el || !(el.value||'').match(/^[0-9]{6}$/)) {
              // Let submitOtp handle validation
              return true;
            }
            return true;
          });
        }
      },120);
    });
      // Link OTP "View Terms" to footer terms modal
      window.addEventListener('load', function(){
        var t = document.getElementById('otp-open-terms');
        if (t) t.addEventListener('click', function(e){ e.preventDefault(); var b = document.getElementById('open-terms'); if(b) b.click(); });
      });
  </script>
  <?php
endif;
?>
