<?php
require_once __DIR__ . '/includes/functions.php';
$body_class = 'mobile-orders-header';

// Lightweight JSON endpoint: {BASE_URL}/orders.php?count=pending
if (isset($_GET['count']) && $_GET['count'] === 'pending') {
    header('Content-Type: application/json');
  try {
    $stmt = db()->query("SELECT COUNT(*) AS c FROM orders WHERE order_status IN ('pending','confirmed','pending_rating')");
        $row = $stmt->fetch();
        echo json_encode(['pending' => (int)($row['c'] ?? 0)]);
    } catch (Throwable $e) {
        echo json_encode(['pending' => 0]);
    }
    exit;
}

// Live JSON for a single order status (customer-facing)
if (isset($_GET['json']) && $_GET['json'] === 'order' && isset($_GET['id'])) {
  header('Content-Type: application/json');
  $oid = (int)$_GET['id'];
  $o = get_order($oid);
  if (!$o || !is_logged_in() || (int)$o['user_id'] !== (int)current_user()['id']) { echo json_encode(['error'=>true]); exit; }
  echo json_encode([
    'order_id' => (int)$o['order_id'],
    'order_status' => (string)$o['order_status'],
    'total_price' => (float)$o['total_price'],
    'updated_at' => (string)($o['updated_at'] ?? $o['created_at']),
  ]);
  exit;
}

// Live JSON for the current user's orders list (brief fields)
if (isset($_GET['json']) && $_GET['json'] === 'list') {
  header('Content-Type: application/json');
  if (!is_logged_in()) { echo json_encode(['orders'=>[]]); exit; }
  $rows = user_orders();
  $out = [];
  foreach ($rows as $r) {
    $out[] = [
      'order_id' => (int)$r['order_id'],
      'order_status' => (string)$r['order_status'],
      'total_price' => (float)$r['total_price'],
      'created_at' => (string)$r['created_at'],
      'quantity' => (int)$r['quantity'],
      'product_name' => (string)$r['product_name'],
    ];
  }
  echo json_encode(['orders'=>$out]);
  exit;
}

// Warranty & receipt data for an order (customer-facing)
if (isset($_GET['json']) && $_GET['json'] === 'warranty' && isset($_GET['id'])) {
  header('Content-Type: application/json');
  $oid = (int)$_GET['id'];
  $o = get_order($oid);
  if (!$o || !is_logged_in() || (int)$o['user_id'] !== (int)current_user()['id']) { echo json_encode(['error'=>true]); exit; }
  // Only allow warranty after the order is received by the customer
  if (strtolower((string)$o['order_status']) !== 'delivered') {
    echo json_encode(['items'=>[], 'error'=>'not_delivered']);
    exit;
  }
  ensure_warranty_records_for_order($oid);
  $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
  $rows = get_warranty_receipts($oid, $pid>0?$pid:null);
  // Compute order-level summary for discount and shipping fee display
  $itemsSubtotal = 0.0; $qty = 0;
  try {
    $its = get_order_items($oid) ?: [];
    foreach ($its as $it) { $itemsSubtotal += (float)($it['total_price'] ?? 0); $qty += (int)($it['quantity'] ?? 0); }
  } catch (Throwable $e) {}
  $discount = ($qty === 4) ? $itemsSubtotal * 0.05 : 0.0;
  $region = (string)($o['shipping_region'] ?? ''); if ($region === '') { $region = detect_user_region(); }
  $rate = shipping_rate_for_region($region);
  $shippingFee = ($qty >= 5) ? 0.0 : max(0.0, ($itemsSubtotal - $discount) * $rate);
  $grand = max(0.0, $itemsSubtotal - $discount) + $shippingFee;
  $out = [];
  foreach ($rows as $r) {
    $end = strtotime((string)$r['warranty_end_date']);
    $today = strtotime(date('Y-m-d'));
    $derived = strtolower((string)$r['status']);
    if (!in_array($derived, ['completed','claimed'], true)) {
      $derived = ($today > $end) ? 'expired' : 'active';
    }
    $out[] = [
      'order_id'=>(int)$r['order_id'],
      'order_item_id'=>(int)$r['order_item_id'],
      'product_id'=>(int)($r['product_id'] ?? 0),
      'product_name'=>(string)$r['product_name'],
      'product_image'=> product_image_url($r['product_image'] ?? ''),
      'serial_no'=>(string)($r['serial_no'] ?? ''),
      'start'=>(string)$r['warranty_start_date'],
      'end'=>(string)$r['warranty_end_date'],
      'status'=>$derived,
      'buyer_name'=>(string)($r['buyer_name'] ?? $o['customer_name']),
      'buyer_email'=>(string)($r['buyer_email'] ?? $o['customer_email']),
      'buyer_phone'=>(string)($r['buyer_phone'] ?? $o['contact_number']),
      'unit_price'=>(float)($r['unit_price'] ?? 0),
      'quantity'=>(int)($r['quantity'] ?? 1),
      'subtotal'=>(float)($r['subtotal'] ?? 0),
      'total'=>(float)($o['total_price'] ?? 0)
    ];
  }
  echo json_encode([
    'items'=>$out,
    'order_date'=> (string)date('Y-m-d', strtotime($o['created_at'] ?? 'now')),
    'summary'=>[
      'subtotal'=> (float)$itemsSubtotal,
      'discount'=> (float)$discount,
      'shipping'=> (float)$shippingFee,
      'total'=> (float)$grand
    ]
  ]);
  exit;
}

// Cancel entire order via AJAX (customer) - use cancel_order helper
if (isset($_GET['json']) && $_GET['json'] === 'cancel_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  if (!is_logged_in()) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  $oid = (int)($_POST['order_id'] ?? 0);
  if ($oid <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid']); exit; }
  $ok = cancel_order($oid);
  echo json_encode(['ok'=> (bool)$ok]);
  exit;
}

// Submit a rating (AJAX)
if (isset($_GET['json']) && $_GET['json'] === 'rate' && $_SERVER['REQUEST_METHOD']==='POST') {
  header('Content-Type: application/json');
  if (!is_logged_in()) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  $oid = (int)($_POST['order_id'] ?? 0);
  $pid = (int)($_POST['product_id'] ?? 0);
  $rating = (int)($_POST['rating'] ?? 0);
  $comment = trim((string)($_POST['comment'] ?? ''));
  $ok = save_product_rating($oid, $pid, (int)current_user()['id'], $rating, $comment);
  echo json_encode(['ok'=>$ok]);
  exit;
}

// Cancel a single order item via AJAX (customer)
if (isset($_GET['json']) && $_GET['json'] === 'cancel_item' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json');
  if (!is_logged_in()) { echo json_encode(['ok'=>false,'error'=>'auth']); exit; }
  $oid = (int)($_POST['order_id'] ?? 0);
  $itemId = (int)($_POST['item_id'] ?? 0);
  if ($oid <= 0 || $itemId <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid']); exit; }
  $res = cancel_order_item($oid, $itemId, false);
  if (!$res) { echo json_encode(['ok'=>false,'error'=>'failed']); exit; }
  echo json_encode(['ok'=>true,'new_total'=> (float)$res['new_total'], 'new_qty'=> (int)$res['new_qty']]);
  exit;
}

if (!is_logged_in()) { header('Location: ' . BASE_URL . '/signin.php'); exit; }
$order_id = (int)($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel']) && $order_id>0) {
  cancel_order($order_id);
  header('Location: ' . BASE_URL . '/orders.php?id=' . $order_id);
  exit;
}
$detail = $order_id > 0;
if ($detail) {
  $o = get_order($order_id);
  if (!$o || (int)$o['user_id'] !== (int)current_user()['id']) { header('Location: ' . BASE_URL . '/orders.php'); exit; }
  $items = get_order_items($order_id);
  // Fallback: if there are no order_items (older data/partial import),
  // reconstruct a single item from the order summary by matching a product by name.
  if (!$items) {
    $nameHint = trim((string)($o['product_name'] ?? ''));
    if ($nameHint !== '') {
      $baseName = preg_replace('/\s+and others$/i', '', $nameHint);
      try {
        $st = db()->prepare('SELECT id, name, price, image FROM products WHERE name = :n OR name LIKE :like LIMIT 1');
        $st->execute([':n' => $baseName, ':like' => $baseName . '%']);
        $p = $st->fetch();
        if ($p) {
          $qty = max(1, (int)($o['quantity'] ?? 1));
          $items = [[
            'product_id'   => (int)$p['id'],
            'product_name' => (string)$p['name'],
            'product_image'=> (string)($p['image'] ?? ''),
            'quantity'     => $qty,
            'unit_price'   => (float)$p['price'],
            'total_price'  => (float)$p['price'] * $qty,
          ]];
        }
      } catch (Throwable $e) { /* ignore */ }
    }
  }
  // Optional item filter: when coming from list, only show the clicked product
  $pidFilter = (int)($_GET['pid'] ?? 0);
  if ($pidFilter > 0 && is_array($items) && $items) {
    $items = array_values(array_filter($items, function($it) use ($pidFilter){ return (int)($it['product_id'] ?? 0) === $pidFilter; }));
    if (!$items) { // if nothing matched, keep original items as a safety net
      $items = get_order_items($order_id);
    }
  }
  $page_title = 'Order Details'; // hide internal ID from page title
} else {
  $page_title = 'My Orders';
  $orders = user_orders();
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <?php if ($detail): ?>
  <a href="<?= BASE_URL ?>/orders.php" class="btn-back">Back</a>
  <?php $custLabel = [ 'pending'=>'Pending', 'confirmed'=>'Order Placed', 'shipped'=>'Order Shipped Out', 'delivered'=>'Order Received', 'cancelled'=>'Cancelled' ][$o['order_status']] ?? ucfirst($o['order_status']); ?>
  <h2 style="margin-top:8px">Order Details <span id="detailStatus" class="badge" style="background:#e0ecfb;color:#0b4f8f"><?= h($custLabel); ?></span></h2>

      <!-- Simple 4-step tracker: Pending → Placed → Shipped → Received -->
      <?php
        $st = strtolower((string)$o['order_status']);
        $pending = ($st==='pending');
        $placed = in_array($st, ['confirmed','shipped','delivered']);
        $shipped = in_array($st, ['shipped','delivered']);
        $received = in_array($st, ['delivered']);
        function step($label,$active,$key){ ?>
          <div class="stp" data-step="<?= h($key); ?>" style="display:flex;align-items:center;gap:10px">
            <div class="stp-dot" style="width:30px;height:30px;border-radius:999px;border:3px solid <?= $active?'#16a34a':'#94a3b8' ?>;display:flex;align-items:center;justify-content:center;color:<?= $active?'#16a34a':'#94a3b8' ?>;font-weight:800">✔</div>
            <div class="stp-label" style="color:<?= $active?'#111827':'#64748b' ?>;font-weight:600;min-width:110px"><?= $label ?></div>
          </div>
        <?php } ?>
      <div id="stepper" style="display:flex;gap:24px;align-items:center;margin:10px 0 16px">
        <?php step('Pending', $pending || (!$placed && !$shipped && !$received), 'pending'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Placed', $placed, 'placed'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Shipped Out', $shipped, 'shipped'); ?>
        <div style="flex:1;height:2px;background:#e5e7eb"></div>
        <?php step('Order Received', $received, 'received'); ?>
      </div>

      <div class="form" style="margin-top:12px">
        <div style="display:flex;flex-direction:column;gap:12px">
          <?php foreach ($items as $it): ?>
            <div class="order-item-row" data-item-id="<?= (int)($it['id'] ?? 0); ?>" style="display:flex;gap:12px;align-items:center">
              <img src="<?= h(product_image_url($it['product_image'])); ?>" alt="" style="width:72px;height:72px;object-fit:contain;border:1px solid #e5e7eb;border-radius:8px;background:#fff"/>
              <div style="flex:1">
                <div style="font-weight:600"><a href="<?= BASE_URL ?>/product.php?id=<?= (int)$it['product_id']; ?>" style="text-decoration:none;color:inherit"><?= h($it['product_name']); ?></a></div>
                <div style="color:#64748b;font-size:13px">Qty: <?= (int)$it['quantity']; ?> × <?= CURRENCY . number_format($it['unit_price'],2); ?></div>
                <?php $already = rating_exists((int)$o['order_id'], (int)$it['product_id'], (int)(current_user()['id'] ?? 0)); ?>
                <div class="rate-box" data-pid="<?= (int)$it['product_id']; ?>" data-oid="<?= (int)$o['order_id']; ?>" data-done="<?= $already? '1':'0' ?>" style="margin-top:6px;display:<?= $o['order_status']==='delivered' ? 'flex' : 'none' ?>;align-items:center;gap:8px;flex-wrap:wrap">
                  <?php if ($already): ?>
                    <span class="rate-ok" style="color:#059669;font-size:13px;font-weight:600">Thanks for your rating!</span>
                  <?php else: ?>
                    <div class="stars" role="radiogroup" aria-label="Rate this product" style="display:flex;gap:4px">
                      <?php for($s=1;$s<=5;$s++): ?>
                        <button type="button" data-val="<?= $s ?>" aria-label="Rate <?= $s ?>" style="background:none;border:0;cursor:pointer;font-size:18px;color:#f59e0b">★</button>
                      <?php endfor; ?>
                    </div>
                    <input type="text" class="rate-comment" placeholder="Optional comment" style="flex:1;min-width:200px;padding:6px 8px;border:1px solid #e2e8f0;border-radius:8px" />
                    <button type="button" class="btn" onclick="submitRating(this)">Submit</button>
                    <span class="rate-ok" style="display:none;color:#059669;font-size:13px;font-weight:600">Thanks for your rating!</span>
                  <?php endif; ?>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:8px">
                <div style="font-weight:700;white-space:nowrap"><?= CURRENCY . number_format($it['total_price'],2); ?></div>
                <?php if ($o['order_status']==='pending' && !empty($it['id'])): ?>
                  <button type="button" class="btn secondary btn-cancel-item" data-item-id="<?= (int)$it['id']; ?>" data-order-id="<?= (int)$o['order_id']; ?>">Cancel item</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <hr />
        <?php
          // Always show both: items Subtotal (sum of item totals) and overall Total Payment (order grand)
          $itemsSubtotal = 0.0; foreach ($items as $x) { $itemsSubtotal += (float)($x['total_price'] ?? 0); }
          $orderGrand = (float)$o['total_price'];
        ?>
        <div style="display:flex;justify-content:space-between;gap:16px;align-items:center;flex-wrap:wrap">
          <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap">
            <div style="font-size:16px;font-weight:700;color:#024787">Subtotal: <?= CURRENCY . number_format($itemsSubtotal,2); ?></div>
            <div style="font-size:18px;font-weight:800;color:#024787">Total Payment: <?= CURRENCY . number_format($orderGrand,2); ?></div>
          </div>
          <?php /* Removed the full-order cancel form from detail view. Customers should cancel an entire order from the orders list page. */ ?>
        </div>
      </div>
    <?php else: ?>
      <h2>My Orders</h2>
      <?php if (!$orders): ?>
        <div class="no-orders" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;box-shadow:var(--shadow);display:flex;flex-direction:column;gap:12px">
          <div style="display:flex;flex-direction:column;gap:8px">
            <h3 style="margin:0;font-size:20px;color:#0b4f8f">You haven't placed any orders yet</h3>
            <p style="margin:0;color:#475569">Discover our best-sellers and place your first order.</p>
            <a class="btn" href="<?= BASE_URL ?>/index.php#home-products" style="align-self:flex-start;background:#16a34a;border-color:#16a34a">Start shopping</a>
          </div>
          <div class="ads-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;align-items:stretch">
            <a href="<?= BASE_URL ?>/index.php#home-products" class="ad-card" style="position:relative;display:flex;align-items:flex-end;min-height:clamp(220px,32vw,460px);background:#0b4f8f;border-radius:12px;overflow:hidden;text-decoration:none;color:#ffffff">
              <img src="<?= BASE_URL ?>/assets/images/bg/4.jpg" alt="Total surge protection" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:contrast(0.95) brightness(0.92)" />
              <div style="position:relative;width:100%;padding:14px;background:linear-gradient(180deg,rgba(11,79,143,0),rgba(11,79,143,0.78))">
                <span style="background:#16a34a;color:#fff;font-size:12px;padding:4px 8px;border-radius:999px">Featured</span>
                <div style="font-weight:800;font-size:18px;margin-top:8px">Total Surge Protection</div>
                <div style="opacity:0.9;font-size:13px">Safeguard your home & equipment</div>
              </div>
            </a>
            <a href="<?= BASE_URL ?>/index.php#home-products" class="ad-card" style="position:relative;display:flex;align-items:flex-end;min-height:clamp(220px,32vw,460px);background:#0b4f8f;border-radius:12px;overflow:hidden;text-decoration:none;color:#ffffff">
              <img src="<?= BASE_URL ?>/assets/images/bg/5.jpg" alt="Protect your power" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:contrast(0.95) brightness(0.92)" />
              <div style="position:relative;width:100%;padding:14px;background:linear-gradient(180deg,rgba(11,79,143,0),rgba(11,79,143,0.78))">
                <span style="background:#16a34a;color:#fff;font-size:12px;padding:4px 8px;border-radius:999px">Limited Offer</span>
                <div style="font-weight:800;font-size:18px;margin-top:8px">Protect Your Power</div>
                <div style="opacity:0.9;font-size:13px">Reliable. Efficient. Essential.</div>
              </div>
            </a>
          </div>
          <!-- Color balance: 60% white container, 30% brand blue overlays (#0b4f8f), 10% accent green (#16a34a) -->
        </div>
      <?php else: ?>
  <div id="ordersList" style="display:flex;flex-direction:column;gap:14px">
          <?php foreach ($orders as $o): ?>
            <?php
              $orderId = (int)$o['order_id'];
              $custLabel = [ 'pending'=>'Pending','confirmed'=>'Order Placed','shipped'=>'Order Shipped Out','delivered'=>'Order Received','cancelled'=>'Cancelled' ][$o['order_status']] ?? ucfirst($o['order_status']);
              $items = get_order_items($orderId);
              if (!$items || !is_array($items)) {
                // Build a single fallback item from the order summary
                $nm = trim((string)($o['product_name'] ?? ''));
                $baseName = preg_replace('/\s+and others$/i', '', $nm);
                $fallback = [];
                try {
                  $st = db()->prepare('SELECT id, name, price, image FROM products WHERE name = :n OR name LIKE :like LIMIT 1');
                  $st->execute([':n'=>$baseName, ':like'=>$baseName.'%']);
                  $p = $st->fetch();
                  if ($p) {
                    $qty = max(1, (int)($o['quantity'] ?? 1));
                    $fallback = [[ 'product_id'=>(int)$p['id'], 'product_name'=>(string)$p['name'], 'product_image'=>(string)($p['image']??''), 'quantity'=>$qty, 'unit_price'=>(float)$p['price'], 'total_price'=>(float)$p['price']*$qty ]];
                  }
                } catch (Throwable $e) { $fallback = []; }
                $items = $fallback ?: [];
              }
            ?>
            <article style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;box-shadow:var(--shadow);overflow:hidden">
              <div style="padding:12px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                <div style="font-weight:700;color:#0b4f8f">Order #<?= (int)$orderId; ?> • <?= h(date('Y-m-d', strtotime($o['created_at']))); ?></div>
                <span class="badge order-status" data-order-id="<?= (int)$o['order_id']; ?>" style="background:#e0ecfb;color:#0b4f8f"><?= h($custLabel); ?></span>
              </div>
              <div style="display:flex;flex-direction:column;gap:10px;padding:12px">
                <?php foreach ($items as $it): ?>
                  <div style="display:flex;align-items:stretch;gap:12px">
                    <div style="width:92px;height:92px;border-radius:10px;overflow:hidden;background:#fff;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center">
                      <img src="<?= h(product_image_url($it['product_image'])); ?>" alt="" style="width:100%;height:100%;object-fit:contain" />
                    </div>
                    <div style="flex:1;min-width:0">
                      <div style="font-size:16px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= h($it['product_name']); ?></div>
                      <div style="color:#64748b;font-size:13px;margin-top:4px">Qty: <?= (int)$it['quantity']; ?> × <?= CURRENCY . number_format((float)$it['unit_price'], 2); ?></div>
                    </div>
                    <div style="text-align:right;min-width:160px;display:flex;flex-direction:column;justify-content:center;gap:6px">
                      <div style="font-weight:800;color:#024787;">Subtotal: <?= CURRENCY . number_format((float)$it['total_price'], 2); ?></div>
                      <div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">
                        <a class="btn link" href="<?= BASE_URL ?>/orders.php?id=<?= (int)$o['order_id']; ?>&pid=<?= (int)$it['product_id']; ?>">View item</a>
                        <?php if (($o['order_status'] ?? '') === 'delivered'): ?>
                          <button type="button" class="btn secondary btn-warranty" data-order-id="<?= (int)$o['order_id']; ?>" data-product-id="<?= (int)$it['product_id']; ?>">View Warranty & Receipt</button>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="order-footer" style="padding:12px;border-top:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
                <div></div>
                <div style="display:flex;align-items:center;gap:12px">
                  <div class="order-total" data-order-id="<?= (int)$o['order_id']; ?>" style="font-size:18px;font-weight:800;color:#024787">Total Payment: <?= CURRENCY . number_format((float)$o['total_price'], 2); ?></div>
                  <?php if ($o['order_status'] === 'pending'): ?>
                    <button type="button" class="btn secondary btn-cancel-order" data-order-id="<?= (int)$o['order_id']; ?>">Cancel Order</button>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<!-- Warranty & Receipt Modal -->
<div id="wrModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:center;justify-content:center;padding:16px">
  <div role="dialog" aria-modal="true" aria-labelledby="wrTitle" style="background:#fff;max-width:920px;width:100%;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.2);overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e7eb">
      <div id="wrTitle" style="font-weight:800;color:#0b4f8f">Warranty & Receipt</div>
      <div style="display:flex;gap:6px;align-items:center">
        <button type="button" class="btn secondary" id="wrCloseBtn">Close</button>
      </div>
    </div>
  <div id="wrBody" style="padding:16px"></div>
  </div>
  </div>
<script>
(function(){
  // Auto-refresh for order detail page
  // Cancel entire order from the orders list via AJAX
  document.querySelectorAll('.btn-cancel-order').forEach(function(btn){
    btn.addEventListener('click', function(){
      var oid = this.getAttribute('data-order-id'); if(!oid) return; if(!confirm('Cancel this entire order? This will cancel all items.')) return;
      var b = this; b.disabled = true;
      fetch('<?= BASE_URL ?>/orders.php?json=cancel_order', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({order_id: oid})})
        .then(function(r){ return r.json(); }).then(function(j){
          if(!j || !j.ok){ alert((j&&j.error) || 'Unable to cancel order'); b.disabled=false; return; }
          // Update UI: mark any order-status badge and total, remove item blocks
          var badge = document.querySelector('.order-status[data-order-id="'+oid+'"]'); if(badge) badge.textContent = 'Cancelled';
          var totalEl = document.querySelector('.order-total[data-order-id="'+oid+'"]'); if(totalEl) totalEl.textContent = 'Total Payment: <?= CURRENCY ?>0.00';
          // Remove item area
          var article = badge ? badge.closest('article') : null; if(article){ article.querySelectorAll('.order-item-row, .order-item-row, .order-item-row').forEach(function(r){ r.remove(); }); }
        }).catch(function(){ alert('Failed to cancel order.'); b.disabled=false; });
    });
  });
  var detailBadge = document.getElementById('detailStatus');
  if(detailBadge){
    var m = location.search.match(/id=(\d+)/); var oid = m? parseInt(m[1],10):0;
    function label(st){
      var map={pending:'Pending',confirmed:'Order Placed',shipped:'Order Shipped Out',delivered:'Order Received',cancelled:'Cancelled'}; return map[st]||String(st||'').replace(/^./,c=>c.toUpperCase());
    }
    function updateSteps(st){
      var stepper=document.getElementById('stepper'); if(!stepper) return;
      var state=String(st||'').toLowerCase();
      var act={ pending: state==='pending', placed: ['confirmed','shipped','delivered'].includes(state), shipped: ['shipped','delivered'].includes(state), received: state==='delivered' };
      stepper.querySelectorAll('.stp').forEach(function(node){
        var key=node.getAttribute('data-step'); var on=!!act[key];
        var dot=node.querySelector('.stp-dot'); var lb=node.querySelector('.stp-label');
        if(dot){ dot.style.borderColor = on?'#16a34a':'#94a3b8'; dot.style.color = on?'#16a34a':'#94a3b8'; }
        if(lb){ lb.style.color = on?'#111827':'#64748b'; }
      });
    }
    function tick(){ fetch('<?= BASE_URL ?>/orders.php?json=order&id='+oid,{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(j && !j.error && j.order_status){
        detailBadge.textContent = label(j.order_status);
        updateSteps(j.order_status);
        var cf = document.getElementById('cancelForm');
        if(cf){ var isPending = String(j.order_status||'').toLowerCase()==='pending'; cf.style.display = isPending ? '' : 'none'; }
        // If order is delivered, reveal any hidden rating boxes without reload
        if(String(j.order_status||'').toLowerCase()==='delivered'){
          document.querySelectorAll('.rate-box').forEach(function(box){ if(box && getComputedStyle(box).display==='none'){ box.style.display='flex'; }});
        }
      }
    }).catch(function(){}); }
    setInterval(tick, 3000); setTimeout(tick, 800);
  }
  // Rating UI handlers
  window.scrollToRatings = function(){ var box = document.querySelector('.rate-box'); if(!box) return; box.scrollIntoView({behavior:'smooth', block:'center'}); };
  document.querySelectorAll('.rate-box').forEach(function(box){
    if(box.getAttribute('data-done')==='1'){
      // Already rated: ensure only thanks message remains
      var starsGone = box.querySelector('.stars'); if(starsGone) starsGone.remove();
      var inp = box.querySelector('.rate-comment'); if(inp) inp.remove();
      var btn = box.querySelector('button'); if(btn) btn.remove();
      var ok = box.querySelector('.rate-ok'); if(ok) ok.style.display='inline';
      return; // skip binding
    }
    var stars = box.querySelectorAll('.stars button'); var val = 5; // default 5
    function paint(){ stars.forEach(function(btn){ var v=parseInt(btn.getAttribute('data-val'),10); btn.textContent = v<=val ? '★' : '☆'; }); }
    stars.forEach(function(btn){ btn.addEventListener('click', function(){ val = parseInt(btn.getAttribute('data-val'),10)||5; paint(); }); });
    paint();
  });
  window.submitRating = function(btn){
    var box = btn.closest('.rate-box'); if(!box) return;
    var oid = parseInt(box.getAttribute('data-oid'),10)||0;
    var pid = parseInt(box.getAttribute('data-pid'),10)||0;
    var val = 5; box.querySelectorAll('.stars button').forEach(function(b){ if(b.textContent==='★') val = parseInt(b.getAttribute('data-val'),10); });
    var comment = (box.querySelector('.rate-comment')||{}).value||'';
    fetch('<?= BASE_URL ?>/orders.php?json=rate', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({order_id:oid, product_id:pid, rating:val, comment:comment})})
      .then(function(r){ return r.json(); })
      .then(function(j){ if(j && j.ok){
          // Replace controls with a persistent thanks message; mark as done
          var controls = box.querySelector('.stars'); if(controls) controls.remove();
          var inp = box.querySelector('.rate-comment'); if(inp) inp.remove();
          btn.remove();
          var ok = box.querySelector('.rate-ok'); if(ok) ok.style.display='inline';
          box.setAttribute('data-done','1');
        } });
  };
  // Auto-refresh for orders list page
  var listRoot = document.getElementById('ordersList');
  if(listRoot){
    function label(st){ var map={pending:'Pending',confirmed:'Order Placed',shipped:'Order Shipped Out',delivered:'Order Received',cancelled:'Cancelled'}; return map[st]||String(st||'').replace(/^./,c=>c.toUpperCase()); }
    function tick(){ fetch('<?= BASE_URL ?>/orders.php?json=list',{cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(!j || !Array.isArray(j.orders)) return;
      // Only update the status badges in-place to avoid layout flicker
      j.orders.forEach(function(o){
        var el = document.querySelector('.order-status[data-order-id="'+o.order_id+'"]'); if(el){ el.textContent = label(o.order_status); }
      });
    }).catch(function(){}); }
    setInterval(tick, 4000); setTimeout(tick, 1000);
  }
})();
</script>
<script>
// Warranty & Receipt modal logic
(function(){
  var modal = document.getElementById('wrModal'); if(!modal) return; var body = document.getElementById('wrBody');
  function open(){ modal.style.display='flex'; }
  function close(){ modal.style.display='none'; body.innerHTML=''; }
  var btnClose = document.getElementById('wrCloseBtn'); if(btnClose) btnClose.onclick = close;
  // Print button removed per request
  document.addEventListener('click', function(e){
    var t = e.target; if(!t || !t.classList) return;
    if(t.classList.contains('btn-warranty')){
      var oid = t.getAttribute('data-order-id'); var pid = t.getAttribute('data-product-id')||'';
      fetch('<?= BASE_URL ?>/orders.php?json=warranty&id='+encodeURIComponent(oid)+'&pid='+encodeURIComponent(pid), {cache:'no-store'})
        .then(function(r){ return r.json(); })
        .then(function(j){
          if(!j){ alert('Failed to load warranty details'); return; }
          if(j.error === 'not_delivered'){ alert('Warranty is available after the order is received.'); return; }
          if(!Array.isArray(j.items) || j.items.length===0){ alert('No warranty record found.'); return; }
          var html = j.items.map(function(it){
            var badge = it.status==='active' ? '<span class="badge" style="background:#16a34a;color:#fff">Active</span>' : (it.status==='expired'? '<span class="badge" style="background:#ef4444;color:#fff">Expired</span>': (it.status==='completed'? '<span class="badge" style="background:#0ea5e9;color:#fff">Completed</span>':'<span class="badge" style="background:#f59e0b;color:#fff">Claimed</span>'));
            return '<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">'
              +'<div class="card" style="margin:0;padding:18px">'
                +'<div style="font-weight:800;margin-bottom:12px">Warranty</div>'
                +'<div style="margin-bottom:12px">'+badge+' <small style="opacity:.7">Valid: '+it.start+' — '+it.end+'</small></div>'
                +'<div style="display:flex;gap:18px;align-items:center;margin-top:10px">'
                   +'<img src="'+it.product_image+'" alt="" style="width:170px;height:170px;object-fit:contain;border:1px solid #e5e7eb;border-radius:10px;background:#fff">'
                   +'<div><div style="font-weight:700">'+it.product_name+'</div>'
                   +'<div style="font-size:12px;color:#64748b">Serial: '+(it.serial_no||'—')+'</div></div>'
                +'</div>'
                +'<div style="margin-top:14px;font-size:14px;line-height:1.5;color:#374151">Claim: To claim warranty within its validity, please present this receipt and the product with serial number at our service center. You may also contact support via email. Sellers can contact the buyer using the chatbot by mentioning "@seller" in the chat to notify the seller directly</div>'
              +'</div>'
              +'<div class="card" style="margin:0;padding:18px">'
                +'<div style="font-weight:800;margin-bottom:10px">Receipt</div>'
                +'<div style="display:grid;grid-template-columns:auto 1fr;gap:8px 14px">'
                  +'<div style="color:#64748b">Order No.:</div><div>#'+it.order_id+'</div>'
                  +'<div style="color:#64748b">Date:</div><div>'+ (j.order_date||'') +'</div>'
                  +'<div style="color:#64748b">Buyer:</div><div>'+ (it.buyer_name||'') +' • '+ (it.buyer_email||'') +'</div>'
                  +'<div style="color:#64748b">Contact:</div><div>'+ (it.buyer_phone||'') +'</div>'
                  +'<div style="color:#64748b">Item:</div><div>'+ it.product_name +'</div>'
                  +'<div style="color:#64748b">Quantity:</div><div>'+ it.quantity +'</div>'
                  +'<div style="color:#64748b">Price:</div><div><?= CURRENCY ?>'+ Number(it.unit_price||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) +'</div>'
                  +'<div style="color:#64748b">Subtotal:</div><div><?= CURRENCY ?>'+ Number(it.subtotal||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) +'</div>'
                  +'<div style="color:#64748b">Discounts (5% if 4 items):</div><div><?= CURRENCY ?>'+ Number((j.summary&&j.summary.discount)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) +'</div>'
                  +'<div style="color:#64748b">Shipping Fee:</div><div><?= CURRENCY ?>'+ Number((j.summary&&j.summary.shipping)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) +'</div>'
                  +'<div style="color:#111827;font-weight:800">Total:</div><div style="font-weight:800"><?= CURRENCY ?>'+ Number((j.summary&&j.summary.total)||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) +'</div>'
                +'</div>'
              +'</div>'
            +'</div>';
          }).join('');
          body.innerHTML = html; open();
        }).catch(function(){ alert('Failed to load warranty details'); });
    }
  });
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.btn-cancel-item').forEach(function(btn){
    btn.addEventListener('click', function(){
      var itemId = this.getAttribute('data-item-id'); var oid = this.getAttribute('data-order-id');
      if(!itemId||!oid) return; if(!confirm('Cancel this item from the order?')) return;
      var b = this; b.disabled = true;
      fetch('<?= BASE_URL ?>/orders.php?json=cancel_item', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({order_id:oid, item_id:itemId})})
        .then(function(r){ return r.json(); }).then(function(j){
          if(!j || !j.ok){ alert((j&&j.error) || 'Unable to cancel item'); b.disabled=false; return; }
          var row = document.querySelector('.order-item-row[data-item-id="'+itemId+'"]'); if(row) row.remove();
          var val = Number(j.new_total || 0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2});
          document.querySelectorAll('div[style*="font-size:16px;font-weight:700;color:#024787"]').forEach(function(el){ el.textContent = 'Subtotal: <?= CURRENCY ?>' + val; });
          document.querySelectorAll('div[style*="font-size:18px;font-weight:800;color:#024787"]').forEach(function(el){ el.textContent = 'Total Payment: <?= CURRENCY ?>' + val; });
        }).catch(function(){ alert('Failed to cancel. Try again.'); b.disabled=false; });
    });
  });
});
</script>
