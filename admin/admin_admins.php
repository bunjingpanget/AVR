<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $pdo = db();

  // Delete admin (guard: don't allow deleting yourself)
  if ($id && (($_POST['action'] ?? '') === 'delete')) {
    if ((int)(current_user()['id'] ?? 0) === $id) {
      $_SESSION['flash_msg'] = 'You cannot delete your own admin account.';
      header('Location: ' . BASE_URL . '/admin/admin_admins.php');
      exit;
    }
    $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'admin' LIMIT 1")->execute([':id'=>$id]);
    $_SESSION['flash_msg'] = 'Admin deleted';
    header('Location: ' . BASE_URL . '/admin/admin_admins.php');
    exit;
  }

  // Create new admin
  if (isset($_POST['create'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $age = (int)($_POST['age'] ?? 0);
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $role = 'admin';
    $status = 'active';
    $password = $_POST['password'] ?? ''; $confirm = $_POST['confirm'] ?? '';

    if ($age < 18) { $_SESSION['flash_msg'] = 'Admin must be 18 years or older.'; header('Location: ' . BASE_URL . '/admin/admin_admins.php'); exit; }
    if (empty($dob) && $age > 0) { $dob = date('Y-m-d', strtotime('-' . $age . ' years')); }
    if ($password === '') { $_SESSION['flash_msg'] = 'Password is required.'; header('Location: ' . BASE_URL . '/admin/admin_admins.php'); exit; }
    if ($password !== $confirm) { $_SESSION['flash_msg'] = 'Passwords do not match.'; header('Location: ' . BASE_URL . '/admin/admin_admins.php'); exit; }

    // Strength validation (same rules used on users)
    $pw = $password; $lc = strtolower($pw); $localEmail = $email ? explode('@', $email, 2)[0] : '';
    $bad = preg_match('/1234|2345|3456|4567|5678|6789|0123|abcd|qwerty|password|admin|welcome/i', $pw);
    $hitsPersonal = ($localEmail && str_contains($lc, $localEmail)) || ($username && str_contains($lc, strtolower($username))) || ($name && strlen($name) >= 3 && str_contains($lc, strtolower(preg_replace('/\s+/','',$name))));
    $strong = (strlen($pw) >= 8 && preg_match('/[A-Z]/', $pw) && preg_match('/[a-z]/', $pw) && preg_match('/\d/', $pw) && preg_match('/[^A-Za-z0-9]/', $pw) && !$bad && !$hitsPersonal);
    if (!$strong) { $_SESSION['flash_msg'] = 'Weak password: choose a stronger password.'; header('Location: ' . BASE_URL . '/admin/admin_admins.php'); exit; }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name,email,username,phone,location,role,status,dob,gender,password,created_at) VALUES (:name,:email,:username,:phone,:location,:role,:status,:dob,:gender,:password,NOW())');
    $stmt->execute([':name'=>$name,':email'=>$email,':username'=>$username,':phone'=>$phone,':location'=>$location,':role'=>$role,':status'=>$status,':dob'=>$dob,':gender'=>$gender,':password'=>$hash]);
    $_SESSION['flash_msg'] = 'Admin created';
    header('Location: ' . BASE_URL . '/admin/admin_admins.php');
    exit;
  }

  // Update existing admin
  if ($id && isset($_POST['update'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $password = $_POST['password'] ?? '';
    $params = [':name'=>$name,':email'=>$email,':username'=>$username,':phone'=>$phone,':location'=>$location,':dob'=>$dob,':gender'=>$gender,':id'=>$id];
    $sql = "UPDATE users SET name=:name,email=:email,username=:username,phone=:phone,location=:location,dob=:dob,gender=:gender WHERE id=:id AND role='admin'";
    if ($password) { $sql = str_replace(' WHERE',' ,password=:password WHERE',$sql); $params[':password'] = password_hash($password, PASSWORD_DEFAULT); }
    $pdo->prepare($sql)->execute($params);
    $_SESSION['flash_msg'] = 'Admin updated';
    header('Location: ' . BASE_URL . '/admin/admin_admins.php');
    exit;
  }
}

// List admins only
$admins = db()->query("SELECT id,name,email,username,phone,location,role,status,created_at,dob,gender FROM users WHERE role = 'admin' ORDER BY created_at DESC")->fetchAll();
admin_layout_start('Admins');
?>
  <?php if ($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>

  <details class="card" style="margin-bottom:12px"><summary style="font-size:18px"><strong>Add Admin</strong></summary>
    <form method="post" class="admin-form" id="addAdminForm" style="margin-top:12px">
      <div class="form-grid">
        <input name="name" id="admin_name" placeholder="Full name" required>
        <input name="email" id="admin_email" type="email" placeholder="Email" required>
        <input name="username" id="admin_username" placeholder="Username" required>
        <input name="phone" id="admin_phone" placeholder="Phone" inputmode="numeric">
        <input name="dob" id="admin_dob" type="date" placeholder="Birthday">
        <input name="age" id="admin_age" type="number" min="0" max="120" placeholder="Age" required style="position:relative;top:0;padding:8px;border-radius:6px" />
        <select name="gender" id="admin_gender"><option value="">Gender</option><option style="color:#000">Male</option><option style="color:#000">Female</option><option style="color:#000">Other</option></select>
        <div>
          <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:var(--muted,#9fb3d1)">Address</div>
          <div class="addr-grid">
            <select id="addr_province_admin"></select>
            <select id="addr_city_admin"></select>
            <select id="addr_barangay_admin"></select>
            <input id="addr_street_admin" placeholder="Street (optional)">
            <input id="addr_house_admin" placeholder="House/Unit #">
            <input name="password" id="admin_password" type="password" placeholder="Password" required>
            <div class="confirm-wrap">
              <input name="confirm" id="admin_confirm" type="password" placeholder="Confirm password" required>
              <div class="pwd-meta">
                <button type="button" id="toggle_admin_pwd" style="padding:6px;border-radius:6px;background:#eef2f7;border:1px solid #cbd5e1;cursor:pointer">Show</button>
                <div id="admin_pw_strength" style="font-weight:700;color:#ef4444">Weak</div>
              </div>
            </div>
          </div>
          <input type="hidden" name="location" id="addr_location_hidden_admin" />
        </div>
      </div>
      <div style="margin-top:10px"><button class="btn" id="createAdminBtn" name="create" value="1" disabled>Create</button></div>
    </form>
  </details>

  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Age</th><th>Gender</th><th>Location</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($admins as $u): ?>
    <tr>
      <td data-label="ID"><?= (int)$u['id']; ?></td>
      <td data-label="Name"><?= h($u['name']); ?></td>
      <td data-label="Email"><?= h($u['email']); ?></td>
      <td data-label="Username"><?= h($u['username']); ?></td>
      <?php
        $ageDisp = '—';
        try {
          if (!empty($u['dob']) && $u['dob'] !== '0000-00-00') {
            $dobObj = new DateTime($u['dob']);
            $age = $dobObj->diff(new DateTime('today'))->y;
            if ($age >= 0 && $age <= 120) { $ageDisp = (string)$age; }
          }
        } catch (Throwable $e) { /* ignore */ }
        $gender = trim((string)($u['gender'] ?? ''));
        $genderDisp = $gender === '' ? '—' : ucfirst(strtolower($gender));
      ?>
      <td data-label="Age"><?= h($ageDisp); ?></td>
      <td data-label="Gender"><?= h($genderDisp); ?></td>
      <td data-label="Location"><?= h($u['location'] ?? ''); ?></td>
      <td data-label="Action">
        <div class="action-vertical">
          <details class="inline"><summary style="cursor:pointer">Edit</summary>
            <form method="post" class="admin-form" style="display:grid;gap:8px;margin-top:8px">
              <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
              <div class="form-grid-compact">
                <input name="name" value="<?= h($u['name']); ?>">
                <input name="email" value="<?= h($u['email']); ?>">
                <input name="username" value="<?= h($u['username']); ?>">
                <input name="phone" value="<?= h($u['phone']); ?>">
                <div>
                  <div style="font-size:13px;font-weight:700;margin-bottom:6px;color:var(--muted,#9fb3d1)">Address</div>
                  <div class="addr-grid">
                    <select id="aprov-<?= (int)$u['id']; ?>"></select>
                    <select id="acity-<?= (int)$u['id']; ?>"></select>
                    <select id="abrgy-<?= (int)$u['id']; ?>"></select>
                    <input id="astreet-<?= (int)$u['id']; ?>" placeholder="Street (optional)">
                    <input id="ahouse-<?= (int)$u['id']; ?>" placeholder="House/Unit #">
                    <input type="hidden" name="location" id="aloc-hidden-<?= (int)$u['id']; ?>" value="<?= h($u['location'] ?? ''); ?>">
                  </div>
                </div>
                <input name="dob" type="date" value="<?= h($u['dob'] ?? ''); ?>">
                <select name="gender"><option value="">Gender</option><option <?= ($u['gender']==='Male')?'selected':''; ?>>Male</option><option <?= ($u['gender']==='Female')?'selected':''; ?>>Female</option><option <?= ($u['gender']==='Other')?'selected':''; ?>>Other</option></select>
                <input name="password" type="password" placeholder="New password (leave blank to keep)">
              </div>
              <div style="display:flex;gap:8px;margin-top:6px">
                <button class="btn" name="update" value="1">Save</button>
              </div>
            </form>
          </details>
          <form method="post" onsubmit="return confirm('Delete this admin account? This cannot be undone.');" style="margin:0">
            <input type="hidden" name="id" value="<?= (int)$u['id']; ?>">
            <input type="hidden" name="action" value="delete">
            <button class="btn secondary" style="background:#b91c1c">Delete</button>
          </form>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_layout_end(); ?>
<style>
  .admin-form .form-grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:8px; }
  .admin-form .form-grid input, .admin-form .form-grid select{ padding:8px 10px; border-radius:6px; border:1px solid rgba(255,255,255,.06); background:transparent; color:inherit }
  #addAdminForm input, #addAdminForm select { background: transparent !important; color:inherit !important; }
  #addAdminForm input:-webkit-autofill, #addAdminForm input:-webkit-autofill:hover, #addAdminForm input:-webkit-autofill:focus { -webkit-box-shadow: 0 0 0px 1000px transparent inset !important; box-shadow: 0 0 0px 1000px transparent inset !important; -webkit-text-fill-color: inherit !important; }
  #admin_phone{ ime-mode:disabled; }
  .addr-grid{ display:grid; grid-template-columns: repeat(7, minmax(180px,1fr)); gap:8px; align-items:center }
  .confirm-wrap{ display:flex; gap:8px; align-items:center }
  .pwd-meta{ display:flex; gap:8px; align-items:center; justify-content:flex-end }
  .admin-form .form-grid input, .admin-form .form-grid select, .addr-grid input, .addr-grid select { height:44px; padding:10px 12px; box-sizing:border-box }
  details.inline form.admin-form { display:block; max-width:320px }
  details.inline form.admin-form input, details.inline form.admin-form select, details.inline form.admin-form textarea { display:block; width:100%; box-sizing:border-box; height:30px; padding:6px 8px; margin:6px 0; background:#fff; color:#000; border:1px solid #d1d5db; border-radius:6px }
  details.inline form.admin-form .addr-grid { display:block }
  details.inline form.admin-form .addr-grid select, details.inline form.admin-form .addr-grid input { display:block; width:100%; margin:6px 0 }
  .addr-grid select, .addr-grid select option, .admin-form select[id^="aprov-"], .admin-form select[id^="acity-"], .admin-form select[id^="abrgy-"]{ background:#fff;color:#000;border:1px solid #cbd5e1 }
  .admin-form .form-grid-compact{ display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:6px }
  details.inline summary{ color:var(--muted,#9fd1ff); }
  @media (max-width:1200px){ .addr-grid{ grid-template-columns: repeat(3, minmax(140px,1fr)); } }
  @media (max-width:720px){ .admin-form .form-grid{ grid-template-columns:1fr; } .addr-grid{ grid-template-columns:1fr } }
</style>
<script>
  // Mount AddressPicker for Add Admin form and for each edit row
  (function(){
    function composeHidden(prefix){
      var prov = document.getElementById(prefix+'prov');
      var city = document.getElementById(prefix+'city');
      var brgy = document.getElementById(prefix+'brgy');
      var street = document.getElementById(prefix+'street');
      var house = document.getElementById(prefix+'house');
      var hidden = document.getElementById(prefix+'loc-hidden');
      if(!hidden) hidden = document.getElementById(prefix+'addr_location_hidden');
      if(!hidden) return;
      var parts = [];
      if(house && house.value) parts.push(house.value);
      if(street && street.value) parts.push(street.value);
      if(brgy && brgy.selectedIndex>=0 && brgy.options[brgy.selectedIndex]) parts.push(brgy.options[brgy.selectedIndex].text);
      if(city && city.selectedIndex>=0 && city.options[city.selectedIndex]) parts.push(city.options[city.selectedIndex].text);
      if(prov && prov.selectedIndex>=0 && prov.options[prov.selectedIndex]) parts.push(prov.options[prov.selectedIndex].text);
      hidden.value = parts.filter(Boolean).join(', ');
    }

    function mountFor(prefixIds){
      var cfg = {};
      cfg.province = document.getElementById(prefixIds.provSelector);
      cfg.city = document.getElementById(prefixIds.citySelector);
      cfg.barangay = document.getElementById(prefixIds.brgySelector);
      cfg.street = document.getElementById(prefixIds.streetSelector);
      cfg.house = document.getElementById(prefixIds.houseSelector);
      cfg.hiddenLocation = document.getElementById(prefixIds.hiddenSelector);
      if(window.AddressPicker && cfg.province && cfg.city && cfg.barangay){
        AddressPicker.mount({ province: cfg.province, city: cfg.city, barangay: cfg.barangay, street: cfg.street, house: cfg.house, hiddenLocation: cfg.hiddenLocation });
        [cfg.province, cfg.city, cfg.barangay, cfg.street, cfg.house].forEach(function(el){ if(!el) return; el.addEventListener('change', function(){ setTimeout(function(){ composeHidden(prefixIds.hiddenSelector.replace('aloc-hidden-','')); },50); }); if(el.tagName==='INPUT') el.addEventListener('input', function(){ setTimeout(function(){ composeHidden(prefixIds.hiddenSelector.replace('aloc-hidden-','')); },50); }); });
      }
    }

    window.addEventListener('DOMContentLoaded', function(){
      // Add form ids
      mountFor({ provSelector: 'addr_province_admin', citySelector: 'addr_city_admin', brgySelector: 'addr_barangay_admin', streetSelector: 'addr_street_admin', houseSelector: 'addr_house_admin', hiddenSelector: 'addr_location_hidden_admin' });
      // Mount for each admin row by id
      document.querySelectorAll('select[id^="aprov-"]').forEach(function(p){
        var id = p.id.split('-')[1];
        mountFor({ provSelector: 'aprov-'+id, citySelector: 'acity-'+id, brgySelector: 'abrgy-'+id, streetSelector: 'astreet-'+id, houseSelector: 'ahouse-'+id, hiddenSelector: 'aloc-hidden-'+id });
        var h = document.getElementById('aloc-hidden-'+id); if(h && h.value){ /* keep existing */ }
      });
    });
  })();
</script>
<script>
  // Password strength checker and Add Admin form helpers
  (function(){
    function evalStrength(pwd, email, username, name){
      var s = (pwd||''); var lc = s.toLowerCase(); var bad = /(1234|2345|3456|4567|5678|6789|0123|abcd|qwerty|password|admin|welcome)/i.test(s);
      var localEmail = (email||'').split('@')[0]||'';
      var hitsPersonal = (localEmail && lc.includes(localEmail)) || (username && lc.includes((username||'').toLowerCase())) || (name && name.length>=3 && lc.includes((name||'').toLowerCase().replace(/\s+/g,'')));
      var strong = (s.length>=8 && /[A-Z]/.test(s) && /[a-z]/.test(s) && /\d/.test(s) && /[^A-Za-z0-9]/.test(s) && !bad && !hitsPersonal);
      if(strong) return {level:'strong', text:'Strong', color:'#16a34a'};
      var cats = 0; if(/[A-Z]/.test(s)) cats++; if(/[a-z]/.test(s)) cats++; if(/\d/.test(s)) cats++; if(/[^A-Za-z0-9]/.test(s)) cats++;
      if(s.length>=8 && cats>=3 && !bad && !hitsPersonal) return {level:'mild', text:'Mild', color:'#f59e0b'};
      return {level:'weak', text:'Weak', color:'#ef4444'};
    }
    var pwd = document.getElementById('admin_password'); var conf = document.getElementById('admin_confirm'); var btn = document.getElementById('createAdminBtn'); var strengthEl = document.getElementById('admin_pw_strength'); var email = document.getElementById('admin_email'); var uname = document.getElementById('admin_username'); var nm = document.getElementById('admin_name'); var ageInput = document.getElementById('admin_age'); var dobInput = document.getElementById('admin_dob'); var toggle = document.getElementById('toggle_admin_pwd'); var phone = document.getElementById('admin_phone');
    function syncStrength(){ var res = evalStrength(pwd.value||'', email?.value||'', uname?.value||'', nm?.value||''); if(strengthEl){ strengthEl.textContent = res.text; strengthEl.style.color = res.color; } checkEnable(); }
    function checkEnable(){ var ag = parseInt(ageInput.value||0,10)||0; var okAge = ag>=18; var pwdMatch = pwd.value && conf.value && pwd.value===conf.value; var res = evalStrength(pwd.value||'', email?.value||'', uname?.value||'', nm?.value||''); var okPwd = res.level==='strong'; btn.disabled = !(okAge && okPwd && pwdMatch); }
    if(pwd){ pwd.addEventListener('input', syncStrength); conf.addEventListener('input', checkEnable); email && email.addEventListener('input', syncStrength); uname && uname.addEventListener('input', syncStrength); nm && nm.addEventListener('input', syncStrength); }
    if(phone){ phone.addEventListener('input', function(){ this.value = (this.value || '').replace(/\D+/g, '').slice(0,15); }); }
    if(toggle && pwd && conf){ toggle.addEventListener('click', function(){ var vis = pwd.type==='text'; pwd.type = vis? 'password':'text'; conf.type = vis? 'password':'text'; toggle.textContent = vis? 'Show':'Hide'; }); }
    if(dobInput && ageInput){ dobInput.addEventListener('change', function(){ try{ var d = new Date(dobInput.value); if(!isNaN(d)){ var now = new Date(); var age = now.getFullYear() - d.getFullYear(); var m = now.getMonth() - d.getMonth(); if(m<0 || (m===0 && now.getDate()<d.getDate())) age--; ageInput.value = age; } }catch(e){} checkEnable(); }); ageInput.addEventListener('input', function(){ var a = parseInt(ageInput.value||0,10)||0; if(a>0){ var y = (new Date()).getFullYear() - a; dobInput.value = y+'-01-01'; } checkEnable(); }); }
    setTimeout(syncStrength,200);
  })();
</script>
