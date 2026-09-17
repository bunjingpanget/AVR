<?php
require_once __DIR__ . '/includes/functions.php';
// Require login for any cart access (view or modification)
if (!is_logged_in()) {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['ok'=>false,'login'=>true]);
    exit;
  }
  header('Location: ' . BASE_URL . '/signin.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $pid = (int)($_POST['id'] ?? 0);
  $qty = (int)($_POST['qty'] ?? 1);
  $addedQty = null; $effectiveQty = null;
  if ($action === 'add' && $pid) { $addedQty = add_to_cart($pid, max(1,$qty)); }
  if ($action === 'update' && $pid) { $effectiveQty = update_cart($pid, $qty); }
  // Build small preview HTML for navbar hover
  $cItems = get_cart();
  // For badge: count distinct products (not total quantity)
  $cCount = count($cItems);
  $list = array_slice($cItems, 0, 5);
  $more = max(0, count($cItems) - count($list));
  $html = '';
  if ($cCount === 0) {
    $html .= '<div style="text-align:center">'
         . '<div style="width:58px;height:58px;margin:0 auto 8px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#0ea5e9">🛒</div>'
         . '<div style="font-weight:600;color:#0f172a">Your cart is empty</div>'
         . '<div style="margin-top:6px"><a href="'.BASE_URL.'/index.php#home-products" style="color:#0369a1;text-decoration:none;font-weight:600">Browse products →</a></div>'
         . '</div>';
  } else {
    $html .= '<div style="font-weight:800;color:#0f172a;margin:2px 2px 8px">Recently Added Products</div>';
    foreach ($list as $ci) {
      $img = h(product_image_url($ci['image']));
      $name = h($ci['name']);
      $price = CURRENCY . number_format($ci['price'], 2);
      $html .= '<div style="display:grid;grid-template-columns:46px 1fr auto;gap:10px;align-items:center;padding:6px 4px;border-radius:8px">'
           . '<img src="'.$img.'" alt="" style="width:46px;height:46px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:8px" />'
           . '<div style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#0f172a;font-weight:600;">'.$name.'</div>'
           . '<div style="text-align:right;color:#0f172a;font-weight:700;">'.$price.'</div>'
           . '</div>';
    }
    if ($more > 0) { $html .= '<div style="font-size:12px;color:#334155;margin:4px 2px">+ '.(int)$more.' more in cart</div>'; }
    $html .= '<div style="margin-top:10px;text-align:right"><a href="'.BASE_URL.'/cart.php" class="btn" style="text-decoration:none">View My Shopping Cart</a></div>';
  }
  $resp = ['ok'=>true,'count'=>$cCount,'preview'=>$html];
  if ($addedQty !== null) { $resp['addedQty'] = (int)$addedQty; }
  if ($effectiveQty !== null) { $resp['qty'] = (int)$effectiveQty; }
  header('Content-Type: application/json'); echo json_encode($resp); exit;
}
$page_title = 'Cart';
$items = get_cart();
$totals = cart_totals($items);
include __DIR__ . '/includes/header.php';
?>
<section class="section">
  <div class="container wide">
    <h2>Your Cart</h2>
    <style>
      /* Scoped to cart page only */
      .cart-table th, .cart-table td{ padding:18px; font-size:16px }
  .cart-table .pimg{ width:156px; height:156px; object-fit:contain; object-position:center center; border-radius:10px; background:#fff; box-shadow:var(--shadow); padding:6px }
      .cart-table .pname{ font-weight:800; font-size:18px; color:#0f172a }
      .cart-table input[type=number]{ width:88px; height:42px; font-size:16px }
      .cart-summary{ min-width:360px }
    </style>
    <?php if (!$items): ?>
    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:60px 30px;display:flex;flex-direction:column;align-items:center;gap:14px;max-width:640px;margin:40px auto;box-shadow:0 4px 24px -6px rgba(15,23,42,.08)">
      <div style="width:120px;height:120px;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border-radius:30px;display:flex;align-items:center;justify-content:center;font-size:56px;color:#0f172a">🛒</div>
      <h3 style="margin:0;font-size:26px;color:#0f172a;letter-spacing:.5px">Your cart is empty</h3>
      <p style="margin:0;font-size:15px;color:#475569;max-width:420px;text-align:center;line-height:1.5">Looks like you haven't added anything yet. Explore our products and discover great deals.</p>
      <a href="<?= BASE_URL ?>/index.php#home-products" class="btn" style="margin-top:4px">Go Shopping Now</a>
    </div>
    <?php else: ?>
      <div class="grid cart-layout" style="grid-template-columns:minmax(0,1fr) 380px; gap:16px; align-items:start;">
        <form id="selForm" method="get" action="<?= BASE_URL ?>/checkout.php" onsubmit="return ensureSelection()">
          <table class="table cart-table">
            <thead>
              <tr>
                <th style="width:44px"><input type="checkbox" id="selAll" checked /></th>
                <th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th><th style="width:100px">Action</th>
              </tr>
            </thead>
            <tbody id="cartBody">
            <?php foreach ($items as $it): $stock=(int)($it['stock']??0); $oos = $stock<=0; ?>
              <tr data-id="<?= (int)$it['id']; ?>" data-price="<?= (float)$it['price']; ?>" data-stock="<?= $stock; ?>">
                <td><input type="checkbox" class="sel" name="sel[]" value="<?= (int)$it['id']; ?>" <?= $oos? 'disabled' : 'checked'; ?>></td>
                <td>
                  <div style="display:flex;align-items:center;gap:14px">
                    <img class="pimg" src="<?= h(product_image_url($it['image'])); ?>" alt="">
                    <div class="pname">
                      <?= h($it['name']); ?>
                      <?php if($oos): ?><div style="font-size:12px;color:#b91c1c;margin-top:6px">Out of Stock</div><?php endif; ?>
                    </div>
                  </div>
                </td>
                <td class="priceCell"><?= CURRENCY . number_format($it['price'], 2); ?></td>
                <td>
                  <input type="number" <?= $oos? 'min="0" max="0" value="0" disabled title="Out of Stock"' : 'min="1" max="'.(int)$stock.'" value="'.(int)$it['quantity'].'"'; ?> onchange="updateQty(<?= (int)$it['id']; ?>, this)" />
                </td>
                <td class="subCell"><?= CURRENCY . number_format($it['subtotal'], 2); ?></td>
                <td><button type="button" class="btn secondary" onclick="removeFromCart(<?= (int)$it['id']; ?>)">Remove</button></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </form>
        <div id="summaryBox" class="form cart-summary" style="background:linear-gradient(180deg,#ffffff,#f8fbff);border-color:#e6eef8;position:relative">
          <div id="promoIndicator" style="position:absolute;top:8px;left:12px;font-size:11px;display:flex;gap:6px;flex-wrap:wrap"></div>
          <div style="display:flex; justify-content:space-between;margin-top:4px"><span>Items (<span id="sumQty"><?= (int)$totals['qty']; ?></span>)</span><strong><span id="sumTotal"><?= CURRENCY . number_format($totals['total'],2); ?></span></strong></div>
          <div style="display:flex; justify-content:space-between"><span>Discount</span><strong>- <span id="sumDiscount"><?= CURRENCY . number_format($totals['discount'],2); ?></span></strong></div>
          <div style="display:flex; justify-content:space-between"><span>Shipping (<?= h($totals['region']); ?> · <?= number_format($totals['shipping_rate']*100,0); ?>%)</span><strong><span id="sumShipping"><?= $totals['shipping']>0 ? CURRENCY . number_format($totals['shipping'],2) : 'FREE'; ?></span></strong></div>
          <hr />
          <div style="display:flex; justify-content:space-between; font-size:18px"><span>Total</span><strong><span id="sumGrand"><?= CURRENCY . number_format($totals['grand'],2); ?></span></strong></div>
          <div style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end">
            <button type="submit" form="selForm" class="btn" id="checkoutBtn">Checkout Selected</button>
          </div>
        </div>
      </div>
      <script>
        (function(){
          var BASE = (window.BASE_URL||'').replace(/\/+$/,'');
          var CUR = <?= json_encode(CURRENCY) ?>;
          var SHIP_RATE = <?= json_encode($totals['shipping_rate']) ?>; // fraction e.g., 0.05
          var selAll = document.getElementById('selAll');
          var body = document.getElementById('cartBody');
          var promoIndicator = document.getElementById('promoIndicator');
          // Lightweight navbar cart UI updaters
          function updateCartBadge(count){
            var b = document.querySelector('.cart-badge');
            if(!b) return;
            var c = parseInt(count||0);
            b.textContent = c;
            b.style.display = c>0 ? 'inline-flex' : 'none';
          }
          function updateCartPreview(html){
            var nav = document.querySelector('.cart-nav'); if(!nav) return;
            var pv = nav.querySelector('.cart-preview'); if(!pv) return;
            pv.innerHTML = html || '';
          }
          function fmt(n){ return CUR + Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
          function recalc(){
            var rows = body.querySelectorAll('tr');
            var qty=0,total=0;
            rows.forEach(function(tr){
              var checked = tr.querySelector('.sel').checked;
              if(!checked) return;
              var price = parseFloat(tr.getAttribute('data-price'))||0;
              var q = parseInt(tr.querySelector('input[type=number]').value||'0');
              qty += q; total += price * q;
            });
            // Promo: 5% off only when exactly 4 items; 5+ items -> free shipping
            var discount = qty===4 ? total*0.05 : 0;
            var shipping = (qty>=5 || qty===0) ? 0 : Math.max(0,(total-discount) * Number(SHIP_RATE||0));
            var grand = Math.max(0,total-discount)+shipping;
            document.getElementById('sumQty').textContent = qty;
            document.getElementById('sumTotal').textContent = fmt(total);
            document.getElementById('sumDiscount').textContent = fmt(discount);
            document.getElementById('sumShipping').textContent = shipping>0?fmt(shipping):'FREE';
            document.getElementById('sumGrand').textContent = fmt(grand);
            // Promo indicators & highlight
            var badges=[];
            if(discount>0 && qty===4) badges.push('<span style="background:#ecfdf5;color:#047857;padding:3px 8px;border-radius:999px;font-weight:600">5% Discount</span>');
            if(qty>=5) badges.push('<span style="background:#dcfce7;color:#065f46;padding:3px 8px;border-radius:999px;font-weight:600">Free Shipping</span>');
            promoIndicator.innerHTML = badges.join('');
            var box=document.getElementById('summaryBox');
            if(qty>=5){ box.style.boxShadow='0 0 0 3px rgba(16,185,129,.35)'; box.style.borderColor='#10b981'; }
            else if(discount>0 && qty===4){ box.style.boxShadow='0 0 0 3px rgba(52,211,153,.25)'; box.style.borderColor='#34d399'; }
            else { box.style.boxShadow=''; box.style.borderColor='#e6eef8'; }
          }
          selAll.addEventListener('change', function(){
            body.querySelectorAll('.sel').forEach(function(cb){ cb.checked = selAll.checked; });
            recalc();
          });
          body.addEventListener('change', function(e){
            if(e.target.classList.contains('sel')){
              var all = body.querySelectorAll('.sel');
              var checked = body.querySelectorAll('.sel:checked');
              selAll.checked = (all.length === checked.length);
              recalc();
            }
          });
          window.updateQty = function(pid, input){
            var q = parseInt(input.value||'1');
            if(q<=0){ return removeFromCart(pid); }
            fetch(BASE + '/cart.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'update', id:pid, qty:q})})
              .then(function(r){ return r.json(); })
              .then(function(res){
                var tr = body.querySelector('tr[data-id="'+pid+'"]');
                if(!tr) return;
                var price = parseFloat(tr.getAttribute('data-price'))||0;
                var eff = (res && typeof res.qty!== 'undefined') ? parseInt(res.qty) : q;
                if(eff !== q){ input.value = eff; /* gentle nudge */ input.style.outline='2px solid #f97316'; setTimeout(function(){input.style.outline='';},600); }
                tr.querySelector('.subCell').textContent = fmt(price*eff);
                recalc();
                // Update navbar cart badge and preview without reload
                if(res){ if(typeof res.count !== 'undefined') updateCartBadge(res.count); if(res.preview) updateCartPreview(res.preview); }
              });
          };
          window.removeFromCart = function(pid){
            fetch(BASE + '/cart.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'update', id:pid, qty:0})})
              .then(function(r){ return r.json(); })
              .then(function(res){
                var tr = body.querySelector('tr[data-id="'+pid+'"]');
                if(tr){ tr.parentNode.removeChild(tr); }
                // If empty, show inline empty state without reload
                if(body.querySelectorAll('tr').length===0){
                  var table = document.querySelector('.cart-table');
                  if(table){ table.outerHTML = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;padding:60px 30px;display:flex;flex-direction:column;align-items:center;gap:14px;max-width:640px;margin:40px auto;box-shadow:0 4px 24px -6px rgba(15,23,42,.08)">\
                    <div style="width:120px;height:120px;background:linear-gradient(135deg,#f1f5f9,#e2e8f0);border-radius:30px;display:flex;align-items:center;justify-content:center;font-size:56px;color:#0f172a">🛒</div>\
                    <h3 style="margin:0;font-size:26px;color:#0f172a;letter-spacing:.5px">Your cart is empty</h3>\
                    <p style="margin:0;font-size:15px;color:#475569;max-width:420px;text-align:center;line-height:1.5">Looks like you haven\'t added anything yet. Explore our products and discover great deals.</p>\
                    <a href="'+BASE+'/index.php#home-products" class="btn" style="margin-top:4px">Go Shopping Now</a>\
                  </div>'; }
                }
                recalc();
                // Update navbar cart badge and preview without reload
                if(res){ if(typeof res.count !== 'undefined') updateCartBadge(res.count); if(res.preview) updateCartPreview(res.preview); }
              });
          };
          window.ensureSelection = function(){
            var any = body.querySelector('.sel:checked');
            if(!any){ alert('Please select at least one product to checkout.'); return false; }
            return true;
          };
          // Initial calc
          recalc();
        })();
      </script>
    <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
