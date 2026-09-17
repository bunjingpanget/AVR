<?php
require_once __DIR__ . '/includes/functions.php';
if (!is_logged_in()) { header('Location: ' . BASE_URL . '/signin.php'); exit; }
$user = get_user((int)current_user()['id']);
$page_title = 'My Profile';
$ok = isset($_SESSION['flash_ok']) ? (bool)$_SESSION['flash_ok'] : false; unset($_SESSION['flash_ok']);
$err = $_SESSION['flash_err'] ?? null; unset($_SESSION['flash_err']);
$avatar_msg = $_SESSION['flash_avatar'] ?? null; unset($_SESSION['flash_avatar']);

// Resolve current avatar path (jpg/png) if exists
$avatarDir = __DIR__ . '/assets/images/avatars';
@mkdir($avatarDir, 0777, true);
$jpg = $avatarDir . '/user_' . (int)$user['id'] . '.jpg';
$png = $avatarDir . '/user_' . (int)$user['id'] . '.png';
$avatarUrl = user_avatar_url((int)$user['id']);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  // Handle profile fields (keep existing behavior)
  $data = [
    'email'=>$_POST['email'] ?? '',
    'name'=>$_POST['name'] ?? '',
    'location'=>$_POST['location'] ?? '',
    'phone'=>$_POST['phone'] ?? '',
    'dob'=>$_POST['dob'] ?? '',
    'gender'=>$_POST['gender'] ?? ''
  ];
  if (update_user_profile((int)$user['id'], $data)) {
    // Refresh session-safe fields
    $_SESSION['user'] = array_merge($_SESSION['user'], [
      'email'=>$data['email'], 'name'=>$data['name'], 'location'=>$data['location'], 'phone'=>preg_replace('/\D+/', '', (string)$data['phone'])
    ]);
    $user = get_user((int)$user['id']);
    $_SESSION['flash_ok'] = true;
  } else { $_SESSION['flash_err'] = 'Unable to update profile'; }

  // Handle avatar upload (optional; <= 1MB; jpg/png)
  if (!empty($_FILES['avatar']['tmp_name']) && is_uploaded_file($_FILES['avatar']['tmp_name'])) {
    $file = $_FILES['avatar'];
    $max = 1024*1024; // 1MB
    $type = mime_content_type($file['tmp_name']);
  if ($file['size'] > $max) { $_SESSION['flash_avatar'] = 'Image too large (max 1MB).'; }
  elseif (!in_array($type, ['image/jpeg','image/png'], true)) { $_SESSION['flash_avatar'] = 'Invalid image type. Use JPEG or PNG.'; }
    else {
      // Remove older formats then save new
      @unlink($jpg); @unlink($png);
      $ext = $type === 'image/png' ? 'png' : 'jpg';
      $dest = $avatarDir . '/user_' . (int)$user['id'] . '.' . $ext;
      if (move_uploaded_file($file['tmp_name'], $dest)) {
        $avatarUrl = BASE_URL . '/assets/images/avatars/user_' . (int)$user['id'] . '.' . $ext;
        $_SESSION['flash_avatar'] = 'Profile image updated.';
      } else { $_SESSION['flash_avatar'] = 'Failed to save image.'; }
    }
  }
  header('Location: ' . BASE_URL . '/account.php');
  exit;
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h2>My Profile</h2>
    <p style="color:#64748b;margin-top:-6px">Manage and protect your account</p>
    <?php if ($ok): ?><div class="form" style="background:#ecfdf5;border-color:#d1fae5">Profile updated.</div><?php endif; ?>
    <?php if ($err): ?><div class="form" style="background:#fee2e2;border-color:#fecaca"><?= h($err); ?></div><?php endif; ?>
    <?php if ($avatar_msg): ?><div class="form" style="background:#eff6ff;border-color:#bfdbfe"><?= h($avatar_msg); ?></div><?php endif; ?>
    <form class="form" method="post" enctype="multipart/form-data">
  <div class="grid account-grid" style="grid-template-columns:2fr 1fr; gap:24px; align-items:start;">
        <div>
          <div class="row">
            <div>
              <label>Username<br>
                <input value="<?= h($user['username']); ?>" disabled>
              </label>
            </div>
            <div>
              <label>Name<br>
                <input name="name" value="<?= h($user['name']); ?>" required>
              </label>
            </div>
            <div>
              <label>Email<br>
                <input name="email" type="email" value="<?= h($user['email']); ?>" required>
              </label>
            </div>
            <div>
              <label>Phone<br>
                <input name="phone" inputmode="numeric" pattern="[0-9]{8,15}" maxlength="15" value="<?= h($user['phone']); ?>">
              </label>
            </div>
            <div>
              <label>Location<br>
                <input name="location" value="<?= h($user['location']); ?>">
              </label>
            </div>
            <div>
              <label>Gender<br>
                <div style="display:flex;gap:12px;padding:10px 0;color:#334155">
                  <?php $g=strtolower((string)($user['gender']??'')); ?>
                  <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="gender" value="Male" <?= $g==='male'?'checked':''; ?>> Male</label>
                  <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="gender" value="Female" <?= $g==='female'?'checked':''; ?>> Female</label>
                  <label style="display:inline-flex;align-items:center;gap:6px"><input type="radio" name="gender" value="Prefer not to say" <?= $g==='other'?'checked':''; ?>> Other</label>
                </div>
              </label>
            </div>
            <div>
              <label>Date of birth<br>
                <input type="date" name="dob" value="<?= h($user['dob'] ?? ''); ?>">
              </label>
            </div>
          </div>
          <div style="margin-top:12px"><button class="btn">Save</button></div>
          <p style="color:#64748b;margin-top:6px">Password changes are disabled here.</p>
        </div>
        <div>
          <div style="border-left:1px solid #e5e7eb;height:100%;margin-left:-12px;padding-left:24px">
            <div style="display:flex;flex-direction:column;gap:10px;align-items:flex-start">
              <div id="drop" style="width:156px;height:156px;border:2px dashed #cbd5e1;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#fff;position:relative;overflow:hidden">
                <?php if ($avatarUrl): ?>
                  <img id="avatarPreview" src="<?= h($avatarUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <div id="avatarPlaceholder" style="width:96px;height:96px;border-radius:50%;background:#f1f5f9;border:2px solid #e2e8f0"></div>
                <?php endif; ?>
              </div>
              <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png" hidden>
              <button type="button" class="btn secondary" onclick="document.getElementById('avatar').click()">Select Image</button>
              <div style="color:#64748b;font-size:12px">File size: maximum 1 MB<br>File extension: .JPEG, .PNG</div>
            </div>
          </div>
        </div>
      </div>
    </form>
  </div>
</section>
<script>
  (function(){
    var drop = document.getElementById('drop');
    var input = document.getElementById('avatar');
    var preview = document.getElementById('avatarPreview');
    function prevent(e){ e.preventDefault(); e.stopPropagation(); }
    function over(){ drop.style.borderColor = '#0475d2'; drop.style.background = '#f0f9ff'; }
    function leave(){ drop.style.borderColor = '#cbd5e1'; drop.style.background = '#fff'; }
    ['dragenter','dragover','dragleave','drop'].forEach(function(ev){ drop.addEventListener(ev, prevent); });
    ['dragenter','dragover'].forEach(function(ev){ drop.addEventListener(ev, over); });
    ['dragleave','drop'].forEach(function(ev){ drop.addEventListener(ev, leave); });
    drop.addEventListener('drop', function(e){
      var f = e.dataTransfer.files && e.dataTransfer.files[0];
      if(!f) return; input.files = e.dataTransfer.files; showPreview(f);
    });
    input.addEventListener('change', function(){ if(this.files && this.files[0]) showPreview(this.files[0]); });
    function showPreview(file){
      if(!/image\/(jpeg|png)/.test(file.type)) return;
      var r = new FileReader();
      r.onload = function(){
        if(!preview){ preview = document.createElement('img'); preview.id='avatarPreview'; preview.style.width='100%'; preview.style.height='100%'; preview.style.objectFit='cover'; drop.innerHTML=''; drop.appendChild(preview); }
        preview.src = r.result;
      };
      r.readAsDataURL(file);
    }
  })();
  // numeric-only phone
  (function(){
    var ph = document.querySelector('input[name="phone"]');
    if(!ph) return; var f=function(){ ph.value=(ph.value||'').replace(/\D+/g,'').slice(0,15); };
    ph.addEventListener('input', f); ph.addEventListener('blur', f);
  })();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
