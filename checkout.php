<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Checkout (COD)';

// Direct single-product checkout (from product page) ?pid= & qty=
$direct = false; $pid = isset($_GET['pid']) ? (int)$_GET['pid'] : 0; $reqQty = isset($_GET['qty']) ? max(1,(int)$_GET['qty']) : 1;
if ($pid) {
  $p = get_product($pid);
  if ($p) {
    $direct = true;
    // When checking out directly from a product page, ensure only this product is cleared from cart
    // after successful order placement (do not wipe the whole cart).
    $selectedIds = [(int)$p['id']];
    $available = max(0, (int)($p['stock'] ?? 0));
    $finalQty = max(0, min($reqQty, $available));
    if ($finalQty <= 0) { $_SESSION['flash_msg'] = 'Sorry, this product is out of stock.'; header('Location: ' . BASE_URL . '/product.php?id='.(int)$p['id']); exit; }
    if ($finalQty < $reqQty) { $_SESSION['flash_msg'] = 'Quantity reduced to available stock ('.$finalQty.').'; }
    $items = [[
      'id'=>$p['id'],
      'name'=>$p['name'],
      'price'=>$p['price'],
      'image'=>$p['image'],
      'quantity'=>$finalQty,
      'subtotal'=>$p['price'] * $finalQty
    ]];
  }
}

if (!isset($items)) { // normal cart-based flow
  $all = get_cart();
  $selectedIds = array_map('intval', (array)($_REQUEST['sel'] ?? []));
  if (!$selectedIds && !empty($_SESSION['checkout_sel'])) { $selectedIds = array_map('intval', (array)$_SESSION['checkout_sel']); unset($_SESSION['checkout_sel']); }
  if ($selectedIds) {
    $items = array_values(array_filter($all, function($it) use ($selectedIds){ return in_array((int)$it['id'], $selectedIds, true); }));
  } else {
    $items = $all;
  }
}
if (!$items) { header('Location: ' . BASE_URL . '/cart.php'); exit; }
$totals = cart_totals($items);

$message = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']); $order_id = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = current_user() ?: [];
  $customer = [
    'name' => $u['name'] ?? trim($_POST['name'] ?? ''),
    'email' => $u['email'] ?? trim($_POST['email'] ?? ''),
    'phone' => $u['phone'] ?? trim($_POST['phone'] ?? ''),
    'address' => trim($_POST['address'] ?? ($u['location'] ?? '')),
  ];
  try {
    // Re-sync items with latest stock and current selection before placing order
    if ($direct) {
      $pnow = get_product($pid);
      $avail = max(0, (int)($pnow['stock'] ?? 0));
      $qfinal = max(0, min($items[0]['quantity'] ?? 1, $avail));
      if ($qfinal <= 0) { $_SESSION['flash_msg'] = 'Sorry, this product became out of stock. Please choose another item.'; header('Location: ' . BASE_URL . '/product.php?id='.(int)$pid); exit; }
      $items = [[
        'id'=>$pnow['id'],'name'=>$pnow['name'],'price'=>$pnow['price'],'image'=>$pnow['image'],
        'quantity'=>$qfinal,'subtotal'=>$pnow['price'] * $qfinal
      ]];
      // Keep only the direct product selected for post-order cart cleanup
      $selectedIds = [(int)$pnow['id']];
      $totals = cart_totals($items);
    } else {
      $allFresh = get_cart();
      $selPost = array_map('intval', (array)($_POST['sel'] ?? []));
      if ($selPost) {
        $items = array_values(array_filter($allFresh, function($it) use ($selPost){ return in_array((int)$it['id'], $selPost, true); }));
        $selectedIds = $selPost;
      } else {
        $items = $allFresh;
      }
      if (!$items) { $_SESSION['flash_msg'] = 'Your cart is empty or items are unavailable.'; header('Location: ' . BASE_URL . '/cart.php'); exit; }
      foreach ($items as $ix=>&$it) { $it['subtotal'] = (float)$it['price'] * (int)$it['quantity']; }
      unset($it);
      $totals = cart_totals($items);
    }
    $order_id = place_order_cod($customer, $items, $totals, isset($selectedIds) ? ($selectedIds ?: null) : null);
  if(!empty($_POST['note'])) save_order_message($order_id, (string)$_POST['note'], $u['id']??null);
    header('Location: ' . BASE_URL . '/orders.php?id=' . (int)$order_id);
    exit;
  } catch (Throwable $e) {
    $_SESSION['flash_msg'] = 'Failed to place order: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/checkout.php');
    exit;
  }
}
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container">
    <h2>Checkout</h2>
    <?php if ($message): ?>
      <div class="form" style="margin-bottom:16px; background:#ecfeff; border-color:#a5f3fc;">
        <?= h($message); ?>
        <?php if ($order_id): ?>
          <div style="margin-top:8px"><a class="btn" href="<?= BASE_URL ?>/orders.php">Go to My Orders</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if ($direct): ?>
      <form class="form" method="post" style="padding:0;border:none;background:transparent">
        <style>
          .ck-row{display:flex;gap:16px;align-items:flex-start}
          .ck-left{flex:1 1 60%;min-width:280px}
          .ck-right{width:360px;flex:0 0 360px}
          @media(max-width:960px){ .ck-row{flex-direction:column} .ck-right{width:100%;flex-basis:auto} }
        </style>
        <div class="ck-row">
          <div class="ck-left">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Delivery Address</h3>
          <div style="font-weight:600; color:#0f172a;"><?= h((current_user()['name'] ?? '')); ?> <span style="font-weight:400">(<?= h(current_user()['phone'] ?? ''); ?>)</span></div>
          <div style="margin-top:4px; color:#475569; line-height:1.4;"><?= h(current_user()['location'] ?? ''); ?></div>
          <div style="margin-top:10px; max-width:420px">
            <label style="display:block;font-size:13px;color:#475569">Change Address<br>
              <textarea name="address" rows="3" style="width:100%;margin-top:4px" required><?= h(current_user()['location'] ?? ''); ?></textarea>
            </label>
          </div>
          <?php
            $reg = $totals['region'];
            $eta = eta_for_region($reg);
            // Compute displayed delivery date range from the first available date
            $first = first_available_delivery_date($eta['span'], $reg, date('Y-m-d'), 1, true);
            $startDate = date('F j, Y', strtotime($first . ' +' . max(0, $eta['min'] - 1) . ' day'));
            $endDate = date('F j, Y', strtotime($first . ' +' . max(0, $eta['max'] - 1) . ' day'));
            // Admin busy window: firstAvailable minus 1 day through end (kept server-side)
          ?>
          <div style="margin-top:10px;font-size:14px;color:#0f172a">
            <strong>Delivery estimate:</strong> <?= (int)$eta['min']; ?>–<?= (int)$eta['max']; ?> days (Region: <?= h($reg); ?>). Estimated delivery: <span class="badge"><?= h($startDate); ?>–<?= h($endDate); ?></span>
          </div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Products Ordered</h3>
          <?php $it = $items[0]; ?>
          <div class="po-grid-head">
            <div></div>
            <div>Item</div>
            <div style="text-align:right">Unit Price</div>
            <div style="text-align:right">Qty</div>
            <div style="text-align:right">Subtotal</div>
          </div>
          <div class="po-row">
            <div class="po-img"><img src="<?= h(product_image_url($it['image'])); ?>" alt=""></div>
            <div class="po-title"><?= h($it['name']); ?></div>
            <div class="po-meta">
              <div class="po-price"><?= CURRENCY . number_format($it['price'],2); ?></div>
              <div class="po-qty">× <?= (int)$it['quantity']; ?></div>
              <div class="po-sub"><strong><?= CURRENCY . number_format($it['subtotal'],2); ?></strong></div>
            </div>
          </div>
            </div>
          </div>
          <div class="ck-right">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">
          <h3 style="margin-top:0">Payment Method</h3>
          <div style="display:flex;justify-content:space-between;align-items:center"><div>Cash on Delivery</div><span class="badge">COD</span></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
          <h3 style="margin-top:0">Order Summary</h3>
          <div style="margin:0 0 10px">
            <label style="display:block;font-size:13px;color:#475569">Message to Seller (optional)<br>
              <textarea name="note" rows="2" style="width:100%;margin-top:4px" placeholder="Add instructions or requests..."></textarea>
            </label>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:0"><span>Merchandise Subtotal</span><strong id="ckSubtotalVal"><?= CURRENCY . number_format($totals['total'],2); ?></strong></div>
          <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Shipping (<span id="ckShipRegion"><?= h($totals['region']); ?></span> · <span id="ckShipRatePerc"><?= number_format($totals['shipping_rate']*100,0); ?></span>%)</span><strong id="ckShippingVal"><?= $totals['shipping']>0 ? CURRENCY . number_format($totals['shipping'],2) : 'FREE'; ?></strong></div>
          <?php if ($totals['discount']>0): ?>
            <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Discount</span><strong id="ckDiscountVal">- <?= CURRENCY . number_format($totals['discount'],2); ?></strong></div>
          <?php endif; ?>
          <hr />
          <div style="display:flex;justify-content:space-between;font-size:20px;color:black"><span>Total Payment:</span><strong id="ckGrandVal"><?= CURRENCY . number_format($totals['grand'],2); ?></strong></div>
          <div style="margin-top:8px;color:#64748b;font-size:12px">Note: Shipping fee and delivery date adjust based on your address region.</div>
          <div style="text-align:right;margin-top:16px"><button class="btn">Place Order</button></div>
            </div>
          </div>
        </div>
      </form>
    <?php else: ?>
      <form class="form" method="post" style="padding:0;border:none;background:transparent">
        <style>
          .ck-row{display:flex;gap:16px;align-items:flex-start}
          .ck-left{flex:1 1 60%;min-width:280px}
          .ck-right{width:360px;flex:0 0 360px}
          @media(max-width:960px){ .ck-row{flex-direction:column} .ck-right{width:100%;flex-basis:auto} }
        </style>
        <div class="ck-row">
          <div class="ck-left">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Delivery Address</h3>
          <div style="font-weight:600; color:#0f172a;"><?= h((current_user()['name'] ?? '')); ?> <span style="font-weight:400">(<?= h(current_user()['phone'] ?? ''); ?>)</span></div>
          <div style="margin-top:4px; color:#475569; line-height:1.4;">&nbsp;<?= h(current_user()['location'] ?? ''); ?></div>
          <div style="margin-top:10px; max-width:420px">
            <label style="display:block;font-size:13px;color:#475569">Change Address<br>
              <textarea name="address" rows="3" style="width:100%;margin-top:4px" required><?= h(current_user()['location'] ?? ''); ?></textarea>
            </label>
          </div>
          <?php
            $reg = $totals['region'];
            $eta = eta_for_region($reg);
            $first = first_available_delivery_date($eta['span'], $reg, date('Y-m-d'), 1, true);
            $startDate = date('F j, Y', strtotime($first . ' +' . max(0, $eta['min'] - 1) . ' day'));
            $endDate = date('F j, Y', strtotime($first . ' +' . max(0, $eta['max'] - 1) . ' day'));
          ?>
          <div style="margin-top:10px;font-size:14px;color:#0f172a">
            <strong>Delivery estimate:</strong> <?= (int)$eta['min']; ?>–<?= (int)$eta['max']; ?> days (Region: <?= h($reg); ?>). Estimated delivery: <span class="badge"><?= h($startDate); ?>–<?= h($endDate); ?></span>
          </div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:16px;padding:16px">
          <h3 style="margin-top:0">Products Ordered</h3>
          <div class="po-grid-head">
            <div></div>
            <div>Item</div>
            <div style="text-align:right">Unit Price</div>
            <div style="text-align:right">Qty</div>
            <div style="text-align:right">Subtotal</div>
          </div>
          <?php foreach ($items as $it): ?>
            <div class="po-row">
              <div class="po-img"><img src="<?= h(product_image_url($it['image'])); ?>" alt=""></div>
              <div class="po-title"><?= h($it['name']); ?></div>
              <div class="po-meta">
                <div class="po-price"><?= CURRENCY . number_format($it['price'],2); ?></div>
                <div class="po-qty">× <?= (int)$it['quantity']; ?></div>
                <div class="po-sub"><strong><?= CURRENCY . number_format($it['subtotal'],2); ?></strong></div>
              </div>
            </div>
          <?php endforeach; ?>
            </div>
          </div>
          <div class="ck-right">
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px;margin-bottom:16px">
          <h3 style="margin-top:0">Payment Method</h3>
          <div style="display:flex;justify-content:space-between;align-items:center"><div>Cash on Delivery</div><span class="badge">COD</span></div>
        </div>
        <div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:16px">
          <h3 style="margin-top:0">Order Summary</h3>
          <div style="margin:0 0 10px">
            <label style="display:block;font-size:13px;color:#475569">Message to Seller (optional)<br>
              <textarea name="note" rows="2" style="width:100%;margin-top:4px" placeholder="Add instructions or requests..."></textarea>
            </label>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:0"><span>Merchandise Subtotal</span><strong id="ckSubtotalVal"><?= CURRENCY . number_format($totals['total'],2); ?></strong></div>
          <div style="display:flex;justify-content:space-between;margin-top:4px"><span>Shipping (<span id="ckShipRegion"><?= h($totals['region']); ?></span> · <span id="ckShipRatePerc"><?= number_format($totals['shipping_rate']*100,0); ?></span>%)</span><strong id="ckShippingVal"><?= $totals['shipping']>0 ? CURRENCY . number_format($totals['shipping'],2) : 'FREE'; ?></strong></div>
          <?php if ($totals['discount']>0): ?><div style="display:flex;justify-content:space-between;margin-top:4px"><span>Discount</span><strong id="ckDiscountVal">- <?= CURRENCY . number_format($totals['discount'],2); ?></strong></div><?php endif; ?>
          <hr />
          <div style="display:flex;justify-content:space-between;font-size:20px;color:black"><span>Total Payment:</span><strong id="ckGrandVal"><?= CURRENCY . number_format($totals['grand'],2); ?></strong></div>
          <div style="margin-top:8px;color:#64748b;font-size:12px">Note: Shipping fee and delivery date adjust based on your address region.</div>
          <div style="text-align:right;margin-top:16px"><button class="btn">Place Order</button></div>
            </div>
            <?php foreach (($selectedIds ?? []) as $sid): ?><input type="hidden" name="sel[]" value="<?= (int)$sid; ?>" /><?php endforeach; ?>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
(function(){
  var rateMap = {NCR:0.05,R4A:0.03,R4B:0.05,R5:0.10,LUZON:0.08,VISAYAS:0.15};
  var etaMap = {NCR:[2,3],R4A:[2,3],R4B:[3,4],R5:[5,7],LUZON:[5,7],VISAYAS:[15,20]};
  var sub = <?= json_encode((float)$totals['total']) ?>;
  var disc = <?= json_encode((float)$totals['discount']) ?>;
  var qty = <?= json_encode((int)$totals['qty']) ?>; // total items, used for free shipping rule
  function detectRegionFromText(t){
    t = String(t||'').toUpperCase();
    if(/METRO MANILA|NCR|QUEZON CITY|MANILA|MAKATI|MANDALUYONG|MARIKINA|MUNTINLUPA|NAVOTAS|PARAÑAQUE|PASAY|PASIG|SAN JUAN|TAGUIG|VALENZUELA|LAS PIÑAS|CALOOCAN|PATEROS/.test(t)) return 'NCR';
    if(/CAVITE|LAGUNA|BATANGAS|RIZAL|\bQUEZON\b/.test(t)) return 'R4A';
    if(/OCCIDENTAL MINDORO|ORIENTAL MINDORO|MARINDUQUE|ROMBLON|PALAWAN/.test(t)) return 'R4B';
    if(/ALBAY|CAMARINES NORTE|CAMARINES SUR|CATANDUANES|MASBATE|SORSOGON/.test(t)) return 'R5';
    if(/AKLAN|ANTIQUE|CAPIZ|ILOILO|GUIMARAS|NEGROS|CEBU|BOHOL|SIQUIJOR|EASTERN SAMAR|NORTHERN SAMAR|SAMAR|LEYTE|SOUTHERN LEYTE|BILIRAN/.test(t)) return 'VISAYAS';
    return 'LUZON';
  }
  function fmt(n){ return '<?= CURRENCY ?>' + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function recalc(addr){
    var reg = detectRegionFromText(addr);
    var rate = rateMap[reg]||0.08;
    // Match cart_totals() server logic: 5+ items => FREE shipping
    var ship = (qty >= 5) ? 0 : Math.max(0,(sub-disc)*rate);
    document.getElementById('ckShipRegion').textContent = reg;
    document.getElementById('ckShipRatePerc').textContent = Math.round(rate*100);
    document.getElementById('ckShippingVal').textContent = ship>0? fmt(ship):'FREE';
    document.getElementById('ckGrandVal').textContent = fmt(Math.max(0,sub-disc)+ship);
    // ETA preview removed (server already shows authoritative estimated delivery range)
    var eta = etaMap[reg]||[5,7];
  }
  window.addEventListener('DOMContentLoaded', function(){
  var ta = document.querySelector('textarea[name=address]'); if(!ta) return;
  // No client-side delivery badge: server displays the authoritative estimate. Still update shipping/price on address edits.
  recalc(ta.value);
  ta.addEventListener('input', function(){ recalc(ta.value); });
  });
})();
</script>
