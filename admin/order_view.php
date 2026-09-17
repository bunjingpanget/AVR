<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$isJson = isset($_GET['json']) && $_SERVER['REQUEST_METHOD'] === 'POST';
if ($isJson) {
  header('Content-Type: application/json');
  // Early JSON handlers to avoid page redirect interfering with AJAX (302)
  $act = (string)$_GET['json'];
  try {
    if ($act === 'cancel_item') {
      $oid = (int)($_POST['order_id'] ?? 0);
      $itemId = (int)($_POST['item_id'] ?? 0);
      if ($oid<=0 || $itemId<=0) { echo json_encode(['ok'=>false,'error'=>'invalid']); exit; }
      $res = cancel_order_item($oid, $itemId, true);
      if (!$res) { echo json_encode(['ok'=>false,'error'=>'failed']); exit; }
      echo json_encode(['ok'=>true,'new_total'=>(float)$res['new_total'],'new_qty'=>(int)$res['new_qty']]); exit;
    }
    if ($act === 'w_claim') {
      $iid = (int)($_POST['item_id'] ?? 0); $notes = '';
      $ok = $iid>0 ? admin_claim_warranty($iid, $notes) : false;
      echo json_encode(['ok'=>(bool)$ok]); exit;
    }
    if ($act === 'w_complete') {
      $iid = (int)($_POST['item_id'] ?? 0);
      $ok = $iid>0 ? admin_complete_warranty($iid) : false;
      echo json_encode(['ok'=>(bool)$ok]); exit;
    }
    echo json_encode(['ok'=>false,'error'=>'unknown_action']); exit;
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'server_error']); exit;
  }
}
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$order = $id ? get_order($id) : null;
if (!$order) { header('Location: ' . BASE_URL . '/admin/admin_orders.php'); exit; }
$items = get_order_items($id);
$msgs = function_exists('order_messages') ? order_messages($id) : [];
admin_layout_start('Transaction Details');
?>
<div style="max-width:980px;margin:0 auto">
  <a class="btn" href="<?= BASE_URL ?>/admin/admin_orders.php" style="margin-bottom:20px;display:inline-block">Back to Transactions</a>
  <div style="background:linear-gradient(135deg,#0c3557,#0d4873);border:1px solid rgba(255,255,255,.08);border-radius:10px;padding:24px;position:relative;overflow:hidden;margin-top:6px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin:0 0 20px 0;gap:12px;flex-wrap:wrap">
    <div style="font-weight:600;font-size:19px">Transaction Details</div>
    <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
      <?php if($order['order_status'] === 'pending'): ?>
        <form method="post" action="<?= BASE_URL ?>/admin/admin_orders.php" style="display:inline-block;margin:0">
          <input type="hidden" name="id" value="<?= (int)$order['order_id']; ?>">
          <input type="hidden" name="action" value="confirm">
          <button class="btn">Confirm</button>
        </form>
        <form method="post" action="<?= BASE_URL ?>/admin/admin_orders.php" onsubmit="return confirm('Cancel this order?');" style="display:inline-block;margin:0">
          <input type="hidden" name="id" value="<?= (int)$order['order_id']; ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn secondary">Cancel</button>
        </form>
      <?php elseif($order['order_status'] === 'confirmed'): ?>
        <form method="post" action="<?= BASE_URL ?>/admin/admin_orders.php" style="display:inline-block;margin:0">
          <input type="hidden" name="id" value="<?= (int)$order['order_id']; ?>">
          <input type="hidden" name="action" value="ship">
          <button class="btn">Out for Delivery</button>
        </form>
        <form method="post" action="<?= BASE_URL ?>/admin/admin_orders.php" onsubmit="return confirm('Cancel this order?');" style="display:inline-block;margin:0">
          <input type="hidden" name="id" value="<?= (int)$order['order_id']; ?>">
          <input type="hidden" name="action" value="cancel">
          <button class="btn secondary">Cancel</button>
        </form>
      <?php elseif($order['order_status'] === 'shipped'): ?>
        <form method="post" action="<?= BASE_URL ?>/admin/admin_orders.php" style="display:inline-block;margin:0">
          <input type="hidden" name="id" value="<?= (int)$order['order_id']; ?>">
          <input type="hidden" name="action" value="done">
          <button class="btn">Order received</button>
        </form>
      <?php else: ?>
        <a class="btn" href="<?= BASE_URL ?>/admin/admin_orders.php">Back</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="ov-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:760px;font-size:14px;line-height:1.55">
      <div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Order ID:</span> #<?= (int)$order['order_id']; ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Customer:</span> <?= h(strtoupper($order['customer_name'])); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Contact:</span> <?= h($order['contact_number']); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Address:</span> <?= h($order['delivery_location']); ?></div>
      </div>
      <div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Products:</span> <?= count($items); ?> item(s)</div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Total:</span> <?= CURRENCY . number_format($order['total_price'],2); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Payment Method:</span> <?= h($order['payment_method']); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Paid:</span> <?= $order['is_paid']? 'Paid':'Unpaid'; ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Order Status:</span> <?= h(strtoupper($order['order_status'])); ?></div>
        <div style="margin:0 0 10px 0"><span style="font-weight:600">Delivery Date:</span> <?= h($order['delivery_date'] ?: '-'); ?></div>
      </div>
    </div>
    
    <?php if($items): ?>
      <div style="margin-top:20px;font-weight:600;font-size:14px">Product Items</div>
      <div style="display:flex;flex-wrap:wrap;gap:18px;margin-top:10px">
        <?php foreach($items as $it): $img=product_image_url($it['product_image']); ?>
          <div style="width:108px;text-align:center;font-size:11px;line-height:1.3">
            <div style="width:98px;height:98px;background:#fff;border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;box-shadow:0 0 0 1px rgba(255,255,255,.15)">
              <img src="<?= h($img); ?>" alt="" style="max-width:84px;max-height:84px;object-fit:contain">
            </div>
            <div style="color:#e2e8f0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($it['product_name']); ?>"><?= h($it['product_name']); ?></div>
            <div style="color:#90acc2;font-size:11px">x <?= (int)$it['quantity']; ?></div>
            <?php
              // Ensure and fetch warranty row for this item
              ensure_warranty_records_for_order((int)$order['order_id']);
              $wr = get_warranty_receipts((int)$order['order_id'], (int)$it['product_id']);
              $row = $wr ? $wr[0] : null;
              $status = $row ? strtolower((string)$row['status']) : 'active';
              $end = $row ? (string)$row['warranty_end_date'] : date('Y-m-d', strtotime('+1 year', strtotime($order['created_at'])));
              if(!in_array($status,['completed','claimed'])){ $status = (strtotime(date('Y-m-d')) > strtotime($end)) ? 'expired' : 'active'; }
            ?>
            <?php if ($order['order_status'] === 'delivered'): ?>
              <div style="margin-top:6px">
                <?php $statusLabel = ($status==='claimed' ? 'In Progress' : ($status==='completed' ? 'Successful' : ucfirst($status))); ?>
                <span class="badge" style="background:<?= $status==='active'?'#16a34a':($status==='expired'?'#ef4444':($status==='completed'?'#0ea5e9':'#f59e0b')) ?>;color:#fff">Warranty: <?= h($statusLabel); ?></span>
              </div>
            <?php endif; ?>
            <?php if (in_array($order['order_status'], ['pending','confirmed'])): ?>
              <button type="button" class="btn secondary admin-cancel-item" data-item-id="<?= (int)$it['id']; ?>" data-order-id="<?= (int)$order['order_id']; ?>" style="margin-top:6px;font-size:11px">Cancel item</button>
            <?php endif; ?>
            <?php if (in_array($order['order_status'], ['delivered'])): ?>
              <div style="display:flex;flex-direction:column;gap:6px;margin-top:6px">
                <?php if ($status === 'active'): ?>
                  <button type="button" class="btn" style="font-size:11px" data-w-item="<?= (int)$it['id']; ?>">Use warranty</button>
                <?php elseif ($status === 'claimed'): ?>
                  <button type="button" class="btn secondary" style="font-size:11px" disabled>In Transit</button>
                  <button type="button" class="btn" style="font-size:11px" data-w-complete="<?= (int)$it['id']; ?>">Complete</button>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <hr style="margin:26px 0;border:0;border-top:1px solid rgba(255,255,255,.15)">
    <div style="font-weight:600;margin-bottom:8px">Messages</div>
    <?php if(!$msgs): ?>
      <div style="font-size:13px;color:#90acc2">No recent activity</div>
    <?php else: foreach($msgs as $m): ?>
      <div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,.08)">
        <div style="font-size:13px;white-space:pre-wrap;line-height:1.4;color:#e2e8f0;"><?= h($m['message']); ?></div>
        <div style="font-size:11px;color:#89a6bb;margin-top:3px;"><?= h(date('M d, Y H:i', strtotime($m['created_at']))); ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
<?php admin_layout_end(); ?>
<style>@media(max-width:900px){ .ov-grid{ grid-template-columns:1fr !important } }</style>
<script>
// Global confirmation for admin action forms (orders & services) on the transaction page
document.addEventListener('submit', function(e){
  try{
    var form = e.target; if(!(form instanceof HTMLFormElement)) return;
    if(!form.querySelector('input[name="action"], input[name="target"]')) return;
    var on = form.getAttribute('onsubmit') || '';
    if(/confirm\s*\(/i.test(on)) return;
    if(!confirm('Are you sure you want to continue this action?')){ e.preventDefault(); e.stopImmediatePropagation(); }
  }catch(err){}
}, true);
</script>
<script>
(function attachAdminOrderViewHandlers(){
  function bindHandlers(){
    // Admin: cancel single order item via AJAX and update totals
    document.querySelectorAll('.admin-cancel-item').forEach(function(btn){
      if (btn.__bound) return; btn.__bound = true;
      btn.addEventListener('click', function(){
        if(!confirm('Cancel this item and restock?')) return;
        var itemId = this.getAttribute('data-item-id'); var oid = this.getAttribute('data-order-id'); var b = this; b.disabled=true;
        fetch('<?= BASE_URL ?>/admin/order_view.php?json=cancel_item', {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({order_id:oid, item_id:itemId})})
          .then(function(r){ return r.text().then(function(t){ try{ return JSON.parse(t); }catch(e){ throw new Error('HTTP '+r.status+' '+(r.redirected?'(redirected) ':'')+t.slice(0,200)); } }); })
          .then(function(j){
            if(!j || !j.ok){ alert((j&&j.error)||'Failed to cancel'); b.disabled=false; return; }
            // remove the product tile and update total label
            var tile = b.closest('div[style]'); if (tile) tile.remove();
            var els = document.querySelectorAll('div');
            els.forEach(function(el){ if(el.textContent && el.textContent.match(/Total\:/)) { el.textContent = el.textContent.replace(/Total\:[\s\S]*$/, 'Total: <?= CURRENCY ?>' + Number(j.new_total||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})); } });
          }).catch(function(){ alert('Failed'); b.disabled=false; });
      });
    });
    // Warranty claim
    document.querySelectorAll('[data-w-item]').forEach(function(btn){
      if (btn.__bound) return; btn.__bound = true;
      btn.addEventListener('click', function(){
        var iid = this.getAttribute('data-w-item');
        // Find a container that actually contains the badge
        var tile = this.parentElement; 
        while (tile && !tile.querySelector('.badge')) { tile = tile.parentElement; }
        if(!confirm('Are you sure you want to proceed?')) return;
        var b = this; b.disabled = true;
        fetch('<?= BASE_URL ?>/admin/order_view.php?json=w_claim', {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({item_id:iid})})
          .then(function(r){ return r.text().then(function(t){ try{ return JSON.parse(t); }catch(e){ throw new Error('HTTP '+r.status+' '+(r.redirected?'(redirected) ':'')+t.slice(0,200)); } }); })
          .then(function(j){
            if(!j||!j.ok){ alert('Failed to start warranty'); b.disabled=false; return; }
            // Update badge to In Progress (claimed)
            var badge = tile && tile.querySelector('.badge');
            if (badge) {
              badge.textContent = 'Warranty: In Progress';
              badge.style.background = '#f59e0b'; // same color used for claimed
            }
            // Replace buttons: show In Transit (disabled) and Complete
            var container = b.parentElement;
            if (container) {
              container.innerHTML = '';
              var inTransit = document.createElement('button');
              inTransit.type = 'button'; inTransit.className = 'btn secondary'; inTransit.style.fontSize = '11px';
              inTransit.textContent = 'In Transit'; inTransit.disabled = true;
              var complete = document.createElement('button');
              complete.type = 'button'; complete.className = 'btn'; complete.style.fontSize = '11px';
              complete.setAttribute('data-w-complete', iid);
              complete.textContent = 'Complete';
              container.appendChild(inTransit);
              container.appendChild(complete);
              // attach handler for the newly created Complete button
              complete.addEventListener('click', function(){
                if(!confirm('Mark warranty service as completed?')) return;
                fetch('<?= BASE_URL ?>/admin/order_view.php?json=w_complete', {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({item_id:iid})})
                  .then(function(r){ return r.text().then(function(t){ try{ return JSON.parse(t); }catch(e){ throw new Error('HTTP '+r.status+' '+(r.redirected?'(redirected) ':'')+t.slice(0,200)); } }); })
                  .then(function(j2){
                    if(!j2||!j2.ok){ alert('Failed to mark as completed'); return; }
                    if (badge) { badge.textContent = 'Warranty: Successful'; badge.style.background = '#0ea5e9'; }
                    container.innerHTML = '';
                  }).catch(function(){ alert('Network error'); });
              });
            }
          }).catch(function(){ alert('Network error'); b.disabled=false; });
      });
    });
    // Warranty complete (for already-rendered claimed items)
    document.querySelectorAll('[data-w-complete]').forEach(function(btn){
      if (btn.__bound) return; btn.__bound = true;
      btn.addEventListener('click', function(){
        var iid = this.getAttribute('data-w-complete');
        var tile = this.parentElement; while (tile && !tile.querySelector('.badge')) { tile = tile.parentElement; }
        if(!confirm('Mark warranty service as completed?')) return;
        var b = this; b.disabled = true;
        fetch('<?= BASE_URL ?>/admin/order_view.php?json=w_complete', {method:'POST', credentials:'same-origin', cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({item_id:iid})})
          .then(function(r){ return r.text().then(function(t){ try{ return JSON.parse(t); }catch(e){ throw new Error('HTTP '+r.status+' '+(r.redirected?'(redirected) ':'')+t.slice(0,200)); } }); })
          .then(function(j){
            if(!j||!j.ok){ alert('Failed to mark as completed'); b.disabled=false; return; }
            var badge = tile && tile.querySelector('.badge');
            if (badge) { badge.textContent = 'Warranty: Successful'; badge.style.background = '#0ea5e9'; }
            var container = b.parentElement; if (container) container.innerHTML = '';
          }).catch(function(err){ alert('Network error: '+(err && err.message ? err.message : '')); b.disabled=false; });
      });
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindHandlers, { once: true });
  } else { bindHandlers(); }
})();
</script>
