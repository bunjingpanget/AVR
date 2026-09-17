<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Service';
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
$user = current_user();

// Auto-computed earliest available service date.
// Follow checkout logic for ETA (region-based min/max), but the actual service job blocks 3 days.
$customer_region = detect_user_region();
$eta = eta_for_region($customer_region);
$service_span_days = 3; // service work window stays 3 days
try {
  $suggested_date = first_available_delivery_date($service_span_days, $customer_region, date('Y-m-d'), 1, true);
  if (!$suggested_date) { $suggested_date = date('Y-m-d', strtotime('+5 days')); }
} catch (Throwable $e) {
  $suggested_date = date('Y-m-d', strtotime('+5 days'));
}

// Lightweight JSON for current user's services statuses
if (isset($_GET['json']) && $_GET['json'] === 'my') {
  header('Content-Type: application/json');
  if (!is_logged_in() || empty($user['email'])) { echo json_encode(['services'=>[]]); exit; }
  try {
    $stmt = db()->prepare('SELECT id, status FROM services WHERE email = :e ORDER BY created_at DESC');
    $stmt->execute([':e'=>$user['email']]);
    $rows = $stmt->fetchAll();
    echo json_encode(['services'=>$rows]);
  } catch (Throwable $e) {
    echo json_encode(['services'=>[]]);
  }
  exit;
}

// JSON endpoint to cancel a pending service (customer-owned) without page reload
if (isset($_GET['json']) && $_GET['json'] === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  if (!is_logged_in() || empty($user['email'])) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  $id = (int)($_POST['id'] ?? 0);
  if ($id <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid']); exit; }
  try {
    $stmt = db()->prepare("UPDATE services SET status='Cancelled' WHERE id = :id AND email = :e AND status = 'Pending'");
    $stmt->execute([':id'=>$id, ':e'=>$user['email']]);
    echo json_encode(['ok' => $stmt->rowCount() > 0]);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false]);
  }
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && is_logged_in()) {
  try {
    // Normalize service type (support "Others" custom value)
    $type = trim($_POST['type'] ?? '');
    if (strcasecmp($type, 'Others') === 0) {
      $other = trim($_POST['type_other'] ?? '');
      if ($other !== '') { $type = $other; }
    }
  // Compute the earliest available date on submit, avoiding races; keep service span = 3 days
  $customer_region = detect_user_region();
  $eta = eta_for_region($customer_region);
    $attempts = 0; $busy = true; $date = null;
    do {
        // Try to reuse an existing recent service preferred_date for the same customer email
        $date = null;
        try {
          if (!empty($user['email'])) {
            $today = date('Y-m-d'); $yest = date('Y-m-d', strtotime('-1 day'));
            $qq = db()->prepare("SELECT preferred_date, status FROM services WHERE email = :e AND preferred_date IS NOT NULL AND DATE(created_at) IN (:d1, :d2) ORDER BY created_at DESC LIMIT 1");
            $qq->execute([':e'=> $user['email'], ':d1'=>$today, ':d2'=>$yest]);
            $ex = $qq->fetch();
            if ($ex && !in_array(strtolower((string)$ex['status']), ['in transit','in_transit','intransit'], true)) {
              $date = $ex['preferred_date'];
            }
          }
        } catch (Throwable $e) { /* continue to compute fresh date */ }
        // If not reused, compute a new date (strict mode) based on service span (3 days)
        if (!$date) {
          $date = first_available_delivery_date($service_span_days, $customer_region, date('Y-m-d'), 1, true);
        }
      // Double-check no conflict at this exact moment
      $spanMinus1 = max(0, (int)$service_span_days - 1);
      $q1 = db()->prepare("SELECT 1 FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL AND :d BETWEEN delivery_date AND DATE_ADD(delivery_date, INTERVAL $spanMinus1 DAY) LIMIT 1");
      $q1->execute([':d'=>$date]);
      $q2 = db()->prepare("SELECT 1 FROM services WHERE status IN ('Confirmed','In transit') AND preferred_date IS NOT NULL AND :d BETWEEN preferred_date AND DATE_ADD(preferred_date, INTERVAL $spanMinus1 DAY) LIMIT 1");
      $q2->execute([':d'=>$date]);
      $busy = (bool)($q1->fetch() || $q2->fetch());
      $attempts++;
    } while ($busy && $attempts < 3);
    if (!$date) { $date = $suggested_date; }

    // compute end date for display (span days window)
    $stored_start = $date;
    $stored_end = date('Y-m-d', strtotime($stored_start . ' +'.max(1,$service_span_days-1).' day'));

    $stmt = db()->prepare('INSERT INTO services (customer_name,email,phone,service_type,description,preferred_date,status) VALUES (:n,:e,:p,:t,:d,:date,\'Pending\')');
    $stmt->execute([
      ':n'=>trim($user['name'] ?? ''), ':e'=>trim($user['email'] ?? ''), ':p'=>trim($user['phone'] ?? ''),
      ':t'=>$type, ':d'=>trim($_POST['desc'] ?? ''), ':date'=>$date
    ]);
    $_SESSION['flash_msg'] = 'Service request submitted. Scheduled for ' . h($stored_start . ' – ' . $stored_end) . ' (Status: Pending).';
    header('Location: ' . BASE_URL . '/service.php');
    exit;
  } catch (Throwable $e) {
    $_SESSION['flash_msg'] = 'Error: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/service.php');
    exit;
  }
}

// Total service requests (current user only)
$service_total = 0;
if (is_logged_in() && !empty($user['email'])) {
  try {
    $stmt = db()->prepare('SELECT COUNT(*) c FROM services WHERE email = :e');
    $stmt->execute([':e' => $user['email']]);
    $row = $stmt->fetch();
    $service_total = isset($row['c']) ? (int)$row['c'] : 0;
  } catch (Throwable $e) { $service_total = 0; }
}

// Fetch current user's service requests (by email) if logged in
$my_services = [];
if (is_logged_in() && !empty($user['email'])) {
  try {
    $stmt = db()->prepare('SELECT id, service_type, description, preferred_date, status, created_at FROM services WHERE email = :e ORDER BY created_at DESC');
    $stmt->execute([':e'=>$user['email']]);
    $my_services = $stmt->fetchAll();
  } catch (Throwable $e) { $my_services = []; }
}

include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h2>Request Service</h2>
  <?php if (is_logged_in()): ?>
  <p style="margin-top:4px;font-size:13px;color:#475569">Your service requests submitted: <strong><?= (int)$service_total; ?></strong></p>
  <?php endif; ?>
    <?php if ($msg): ?><div class="form" style="background:#ecfeff;border-color:#a5f3fc;"><?= h($msg); ?></div><?php endif; ?>

    <?php if (!is_logged_in()): ?>
      <div style="display:flex;justify-content:center;align-items:center">
        <div class="form" style="max-width:760px;margin:20px auto;padding:26px;text-align:center">
          <div style="width:110px;height:110px;margin:0 auto 10px;border-radius:50%;background:linear-gradient(135deg,#f8fafc,#eef2f7);display:flex;align-items:center;justify-content:center;color:#0f172a;font-size:64px;line-height:1">☹</div>
          <h3 style="margin:4px 0 6px;color:#0f172a">Sign in required</h3>
          <p style="margin:0 0 14px;color:#475569">You need to log in or register an account to request a service. We’ll use your profile’s name, email, and phone.</p>
          <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn" style="min-width:140px" href="<?= BASE_URL ?>/signin.php">Sign in</a>
            <a class="btn" style="min-width:140px" href="<?= BASE_URL ?>/register.php">Register</a>
          </div>
        </div>
      </div>
    <?php else: ?>
      <?php
        // Display an ETA range like Checkout, based on customer's region min/max from the first available day
        $firstAvail = $suggested_date;
        $disp_start = date('Y-m-d', strtotime($firstAvail . ' +' . max(0, (int)($eta['min'] ?? 1) - 1) . ' day'));
        $disp_end = date('Y-m-d', strtotime($firstAvail . ' +' . max(0, (int)($eta['max'] ?? 1) - 1) . ' day'));
      ?>
      <form class="form" method="post">
        <div class="row">
          <div>
            <label>Service Type<br>
              <select name="type" required>
                <option value="">Select service type…</option>
                <option>Elevator and Escalator Parts &amp; Services</option>
                <option>Repair &amp; Servicing</option>
                <option>Design &amp; Estimation</option>
                <option>After Sales Services</option>
                <option>Others</option>
              </select>
            </label>
            <div id="svc-other-wrap" style="display:none;margin-top:6px">
              <label>Other Service<br>
                <input name="type_other" placeholder="Please specify" />
              </label>
            </div>
          </div>
          <div>
            <label>Scheduled Date<br>
              <input type="text" value="<?= h($disp_start . ' – ' . $disp_end); ?>" readonly>
            </label>
            <div style="font-size:12px;color:#64748b;margin-top:4px">We automatically assign the earliest available date.</div>
          </div>
          <div style="grid-column:1/3"><label>Description<br><textarea name="desc" rows="3" placeholder="Describe the issue or request..."></textarea></label></div>
        </div>
        <div style="margin-top:12px"><button class="btn">Submit</button></div>
      </form>
      <?php if ($my_services): ?>
        <div class="form" style="margin-top:18px">
          <h3 style="margin-top:0">My Service Requests</h3>
          <div style="overflow:auto">
            <table class="table" style="min-width:640px;font-size:14px">
              <thead><tr><th style="width:140px">Service Type</th><th>Description</th><th style="width:120px">Preferred Date</th><th style="width:100px">Status</th><th style="width:100px">Actions</th></tr></thead>
              <tbody>
              <?php foreach ($my_services as $sv): ?>
                <tr>
                  <td><?= h($sv['service_type']); ?></td>
                  <td style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($sv['description']); ?>"><?= h($sv['description']); ?></td>
                  <td><?= h($sv['preferred_date']); ?></td>
                  <td><span class="badge svc-status" data-svc-id="<?= (int)$sv['id']; ?>" style="background:#e0ecfb;color:#0b4f8f"><?= h($sv['status']); ?></span></td>
                  <td>
                    <?php if (($sv['status'] ?? '') === 'Pending'): ?>
                      <button type="button" class="btn secondary svc-cancel" data-svc-id="<?= (int)$sv['id']; ?>" onclick="cancelService(<?= (int)$sv['id']; ?>, this)">Cancel</button>
                    <?php else: ?>
                      <span style="color:#64748b;font-size:12px">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
// Live update my service statuses without reload
(function(){
  var hasList = document.querySelector('.svc-status'); if(!hasList) return;
  function applyRow(s){
    var el = document.querySelector('.svc-status[data-svc-id="'+s.id+'"];');
  }
  function tick(){
    fetch('<?= BASE_URL ?>/service.php?json=my',{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(!j || !Array.isArray(j.services)) return;
      j.services.forEach(function(s){
        var badge = document.querySelector('.svc-status[data-svc-id="'+s.id+'"]'); if(badge){ badge.textContent = s.status; }
        var btn = document.querySelector('.svc-cancel[data-svc-id="'+s.id+'"]'); if(btn){ btn.style.display = (s.status === 'Pending') ? '' : 'none'; }
      });
    }).catch(function(){});
  }
  setInterval(tick, 3000); setTimeout(tick, 1800);
})();

// Cancel a pending service request via JSON without reloading
function cancelService(id, btn){
  if(!id) return; if(!confirm('Cancel this service request?')) return;
  if(btn){ btn.disabled = true; }
  fetch('<?= BASE_URL ?>/service.php?json=cancel', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({ id: id })
  }).then(function(r){ return r.json(); }).then(function(j){
    if(j && j.ok){
      var badge = document.querySelector('.svc-status[data-svc-id="'+id+'"]'); if(badge){ badge.textContent = 'Cancelled'; }
      if(btn){ btn.style.display='none'; }
    } else {
      if(btn){ btn.disabled = false; }
      alert((j&&j.error)||'Unable to cancel. It may have been processed already.');
    }
  }).catch(function(){ if(btn){ btn.disabled=false; } alert('Failed to cancel. Please try again.'); });
}

// Toggle Other Service field responsively
(function(){
  var sel = document.querySelector('select[name="type"]');
  var wrap = document.getElementById('svc-other-wrap');
  if(!sel || !wrap) return;
  function sync(){ wrap.style.display = (/^others$/i.test(sel.value)) ? 'block' : 'none'; }
  sel.addEventListener('change', sync);
  sync();
})();
</script>
