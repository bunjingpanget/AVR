<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = $_SESSION['flash_msg'] ?? null; unset($_SESSION['flash_msg']);
$services = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $action = $_POST['action'] ?? '';
  $target = $_POST['target'] ?? 'order';
  // service actions (moved from admin_services.php)
  if ($target === 'service') {
    // New service workflow: confirm -> In transit -> Complete
    $map = ['confirm'=>'Confirmed','proceed'=>'In transit','complete'=>'Complete','cancel'=>'Cancelled'];
    if ($id && isset($map[$action])) {
      $stmt = db()->prepare('UPDATE services SET status=:s WHERE id=:id');
      $stmt->execute([':s'=>$map[$action], ':id'=>$id]);
      $_SESSION['flash_msg'] = 'Service #' . $id . ' updated to ' . $map[$action];
      header('Location: ' . BASE_URL . '/admin/admin_orders.php');
      exit;
    }
  }
  // order actions (existing behavior)
  if ($target === 'order') {
    $map = [ 'confirm' => 'confirmed', 'ship' => 'shipped', 'done' => 'delivered', 'cancel' => 'cancelled' ];
    if ($id && isset($map[$action])) {
      $ok = false;
      if ($action === 'cancel') {
        // Use helper that also restores stock when cancelling
        $ok = admin_cancel_order($id);
      } else {
        $stmt = db()->prepare('UPDATE orders SET order_status = :s WHERE order_id = :id');
        $ok = $stmt->execute([':s'=>$map[$action], ':id'=>$id]);
      }
      $_SESSION['flash_msg'] = 'Transaction #' . $id . ' updated to ' . $map[$action];
      header('Location: ' . BASE_URL . '/admin/admin_orders.php');
      exit;
    }
  }
}
$orders = db()->query('SELECT * FROM orders ORDER BY created_at DESC')->fetchAll();
// fetch services to display below orders
$services = db()->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
// Lightweight JSON feed for real-time updates (orders + services + busy blocks)
if (isset($_GET['json']) && $_GET['json'] === 'list') {
  // optional filter: all | complete | inprogress | canceled. Default to 'all' per request
  $filter = strtolower((string)($_GET['filter'] ?? 'all'));
  $out = [];
  foreach ($orders as $o) {
    $st = strtolower((string)$o['order_status']);
    $include = false;
    if ($filter === 'all') { $include = true; }
    elseif ($filter === 'complete') { $include = $st === 'delivered'; }
    elseif ($filter === 'canceled') { $include = $st === 'cancelled'; }
    else { // inprogress
      $include = in_array($st, ['confirmed','shipped'], true);
    }
    if (!$include) continue;
    $label = [
      'pending'=>'Pending',
      'confirmed'=>'Order Placed',
      'shipped'=>'Order Shipped Out',
      'delivered'=>'Order Received',
      'cancelled'=>'Cancelled'
    ][$o['order_status']] ?? $o['order_status'];
    $out[] = [
      'order_id'=>(int)$o['order_id'],
      'customer_name'=>$o['customer_name'],
      'contact_number'=>$o['contact_number'],
      'product_name'=>$o['product_name'],
      'quantity'=>(int)$o['quantity'],
      'total_price'=>(float)$o['total_price'],
      'order_status'=>$o['order_status'],
      'status_label'=>$label,
      'payment_method'=>$o['payment_method'],
      'is_paid'=>(int)$o['is_paid']
    ];
  }
  $slist = [];
  foreach ($services as $s) {
    $sst = strtolower((string)$s['status']);
    $include = false;
    if ($filter === 'all') { $include = true; }
    elseif ($filter === 'complete') { $include = $sst === 'complete'; }
    elseif ($filter === 'canceled') { $include = $sst === 'cancelled'; }
    else { // inprogress
      $include = in_array($sst, ['confirmed','in transit','in_transit','intransit'], true) || $sst === 'in transit';
    }
    if (!$include) continue;
    $slist[] = [
      'id'=>(int)$s['id'],
      'customer_name'=>$s['customer_name'],
      'service_type'=>$s['service_type'],
      'preferred_date'=>$s['preferred_date'],
      'description'=>$s['description'],
      'status'=>$s['status']
    ];
  }
  // Recompute busy blocks for side panels (orders + confirmed services)
  $blocks = [];
  try {
    // Only confirmed and shipped orders block admin schedule; delivered (received) orders free the schedule
    $ord = db()->query("SELECT order_id, customer_name, shipping_region, delivery_date, COALESCE(delivery_span_days,3) AS span FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL ORDER BY delivery_date ASC")->fetchAll();
    foreach ($ord as $r) {
      $start = date('Y-m-d', strtotime($r['delivery_date']));
      $end = date('Y-m-d', strtotime($start . ' +' . (max(1,(int)$r['span'])-1) . ' day'));
      $blocks[] = [
        'type' => 'Order', 'start' => $start, 'end' => $end,
        'label' => 'Order #' . (int)$r['order_id'],
        'who' => (string)$r['customer_name'],
        'region' => (string)($r['shipping_region'] ?? '')
      ];
    }
  } catch (Throwable $e) {}
  try {
  $srv = db()->query("SELECT id, preferred_date, customer_name FROM services WHERE status IN ('Confirmed','In transit') AND preferred_date IS NOT NULL ORDER BY preferred_date ASC")->fetchAll();
    foreach ($srv as $s) {
      $start = date('Y-m-d', strtotime($s['preferred_date']));
      $end = date('Y-m-d', strtotime($start . ' +2 day'));
      $blocks[] = [ 'type'=>'Service', 'start'=>$start, 'end'=>$end, 'label'=>'Service #' . (int)$s['id'], 'who'=>(string)($s['customer_name'] ?? ''), 'region'=>'' ];
    }
  } catch (Throwable $e) {}
  header('Content-Type: application/json'); echo json_encode(['orders'=>$out, 'services'=>$slist, 'blocks'=>$blocks]); exit;
}
// Build busy blocks summary for side panels (orders + confirmed services)
$blocks = [];
try {
  $ord = db()->query("SELECT order_id, customer_name, shipping_region, delivery_date, COALESCE(delivery_span_days,3) AS span FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL ORDER BY delivery_date ASC")->fetchAll();
  foreach ($ord as $r) {
    $start = date('Y-m-d', strtotime($r['delivery_date']));
    $end = date('Y-m-d', strtotime($start . ' +' . (max(1,(int)$r['span'])-1) . ' day'));
    $blocks[] = [
      'type' => 'Order', 'start' => $start, 'end' => $end,
      'label' => 'Order #' . (int)$r['order_id'],
      'who' => (string)$r['customer_name'],
      'region' => (string)($r['shipping_region'] ?? '')
    ];
  }
} catch (Throwable $e) {}
try {
  $srv = db()->query("SELECT id, preferred_date, customer_name FROM services WHERE status='Confirmed' AND preferred_date IS NOT NULL ORDER BY preferred_date ASC")->fetchAll();
  foreach ($srv as $s) {
    $start = date('Y-m-d', strtotime($s['preferred_date']));
    $end = date('Y-m-d', strtotime($start . ' +2 day')); // default 3-day span
    $blocks[] = [ 'type'=>'Service', 'start'=>$start, 'end'=>$end, 'label'=>'Service #' . (int)$s['id'], 'who'=>'', 'region'=>'' ];
  }
} catch (Throwable $e) {}
admin_layout_start('All Transactions');
?>
  <div class="card" style="margin-bottom:16px">
    <div class="admin-cal-wrap" style="display:grid;grid-template-columns:1fr minmax(260px,360px) 1fr;gap:16px;align-items:start">
      <aside id="calInfoLeft" class="card" style="margin:0;background:linear-gradient(180deg,#0b2138,#0f2d4d);color:#d2e7ff;border-color:#143a61;display:flex;flex-direction:column;justify-content:center">
        <h3 style="margin-top:0;color:#e6f3ff;font-size:18px">What do red days mean?</h3>
        <div style="font-size:15px;line-height:1.9;color:#cfe3ff">
          - Red days are blocked for delivery 
          <br>- Violet days are blocked for service.
          <br>- Transactions: confirmed transactions (orders) are shown on the calendar and can accept bundled transactions until they are marked "Out for Delivery".
          <br>- Only transactions marked "Out for Delivery" (shipped) block scheduling for new transactions; orders marked "Delivered" (received) free the schedule immediately.
          <br>- Services: confirmed service jobs block 3 days by default.
        </div>
      </aside>
      <div id="adminCalWrap" style="display:flex;flex-direction:column;align-items:center">
        <h3 style="margin:0 0 6px">Delivery Calendar</h3>
        <div id="adminCal" style="width:100%;max-width:360px"></div>
  <small style="color:#64748b">Red days are busy (transactions and services)</small>
      </div>
      <aside id="calInfoRight" class="card" style="margin:0;background:linear-gradient(180deg,#0b2138,#0f2d4d);color:#fff;border-color:#143a61">
        <h3 style="margin-top:0;color:#ffffff">Busy Spans</h3>
        <div id="busyList" style="display:flex;flex-direction:column;gap:8px;color:#ffffff"></div>
      </aside>
    </div>
  </div>
  <?php if ($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>
  <div style="display:flex;gap:8px;margin:10px 0;align-items:center;flex-wrap:wrap">
    <div style="font-weight:700;color:#e6f3ff;margin-right:8px">Filter:</div>
  <button id="fAll" class="btn" data-filter="all">All</button>
  <button id="fIn" class="btn secondary" data-filter="inprogress">In progress</button>
    <button id="fComp" class="btn secondary" data-filter="complete">Complete</button>
    <button id="fCan" class="btn secondary" data-filter="canceled">Cancelled</button>
  </div>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Payment</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td data-label="ID">#<?= (int)$o['order_id']; ?></td>
      <td data-label="Customer"><?= h($o['customer_name']); ?><br><small><?= h($o['contact_number']); ?></small></td>
      <td data-label="Items"><?= h($o['product_name']); ?> (<?= (int)$o['quantity']; ?>)</td>
      <td data-label="Amount"><?= CURRENCY . number_format($o['total_price'],2); ?></td>
      <?php $label = [
        'pending'=>'Pending',
        'confirmed'=>'Order Placed',
        'shipped'=>'Order Shipped Out',
        'delivered'=>'Order Received',
        'cancelled'=>'Cancelled'
      ][$o['order_status']] ?? $o['order_status']; ?>
      <td data-label="Status"><span class="badge"><?= h($label); ?></span></td>
      <td data-label="Payment"><?= $o['payment_method']; ?><?= $o['is_paid']? ' (paid)':''; ?></td>
      <td data-label="Actions">
        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?php if ($o['order_status']==='pending'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="confirm">
              <button class="btn" title="Confirm">Confirm</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
            <form method="post" onsubmit="return confirm('Cancel this order?');">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn secondary">Cancel</button>
            </form>
          <?php elseif ($o['order_status']==='confirmed'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="ship">
              <button class="btn" title="Out for Delivery">Out for Delivery</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
            <form method="post" onsubmit="return confirm('Cancel this order?');">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="cancel">
              <button class="btn secondary">Cancel</button>
            </form>
          <?php elseif ($o['order_status']==='shipped'): ?>
            <form method="post">
              <input type="hidden" name="id" value="<?= (int)$o['order_id']; ?>">
              <input type="hidden" name="action" value="done">
              <button class="btn" title="Order received">Order received</button>
            </form>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
          <?php else: ?>
            <a class="btn secondary" href="<?= BASE_URL ?>/admin/order_view.php?id=<?= (int)$o['order_id']; ?>">View</a>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
  
  <!-- Services: moved below Transactions -->
  <div style="margin-top:18px"><h2 style="margin:0 0 8px 0">Service Requests</h2></div>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Name</th><th>Type</th><th>Date</th><th>Description / Concern</th><th>Status</th><th>Actions</th></tr></thead><tbody class="services-body">
  <?php foreach ($services as $s): ?>
    <tr>
      <td data-label="ID"><?= (int)$s['id']; ?></td>
      <td data-label="Name"><?= h($s['customer_name']); ?></td>
      <td data-label="Type"><?= h($s['service_type']); ?></td>
      <td data-label="Date"><?= h($s['preferred_date']); ?></td>
      <td data-label="Description / Concern" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= h($s['description']); ?>"><?= h($s['description']); ?></td>
      <td data-label="Status"><span class="badge"><?= h($s['status']); ?></span></td>
      <td data-label="Actions">
        <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?php if($s['status']==='Pending'): ?>
          <form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="confirm"><button class="btn">Confirm</button></form>
          <form method="post" onsubmit="return confirm('Cancel this request?');"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>
        <?php elseif($s['status']==='Confirmed'): ?>
          <form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="proceed"><button class="btn">Proceeding</button></form>
          <form method="post" onsubmit="return confirm('Cancel this request?');"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>
        <?php elseif($s['status']==='In transit' || $s['status'] === 'In transit'): ?>
          <form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="<?= (int)$s['id']; ?>"><input type="hidden" name="action" value="complete"><button class="btn">Complete</button></form>
        <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php admin_layout_end(); ?>
<script>
(function(){
  var root=document.getElementById('adminCal'); if(!root) return; var busy = {}; // map date->type (Order|Service)
  var BLOCKS = <?= json_encode($blocks) ?>;
  // expose lightweight hooks to update side panels and calendar
  window.setAdminBlocks = function(blocks){ try{ BLOCKS = Array.isArray(blocks)? blocks : []; renderBusyList(); }catch(e){} };
  window.adminCalReload = function(){ try{ fetch('<?= BASE_URL ?>/busy_dates.php').then(function(r){return r.json();}).then(function(j){ busy=j.busy||[]; draw(new Date()); }); }catch(e){} };
  function draw(base){
    var y=base.getFullYear(),m=base.getMonth();
    var first=new Date(y,m,1), start=first.getDay(), days=new Date(y,m+1,0).getDate();
    var html='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">'+
      "<button data-nav='-1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer'>‹</button>"+
      '<strong>'+first.toLocaleString(undefined,{month:'long'})+' '+y+'</strong>'+"<button data-nav='1' style='background:#e2e8f0;border:0;width:26px;height:26px;border-radius:4px;cursor:pointer'>›</button></div>";
    html+='<table style="width:100%;border-collapse:collapse;font-size:13px;text-align:center"><thead><tr>'+['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>'<th style="padding:2px 0;font-size:12px;color:#8fbbe2">'+d+'</th>').join('')+'</tr></thead><tbody>';
    var d=1;
    for(var w=0; w<6 && d<=days; w++){
      html+='<tr>';
      for(var i=0;i<7;i++){
          if(w===0 && i<start || d>days){ html+='<td></td>'; }
          else {
            var ds=y+'-'+String(m+1).padStart(2,'0')+'-'+String(d).padStart(2,'0');
            var busyType = null;
            if(Array.isArray(busy)){
              busyType = busy.indexOf(ds) > -1 ? 'Order' : null; // fallback
            } else if(busy && typeof busy === 'object'){
              busyType = busy[ds] || null;
            }
            // 60/30/10 rule: primary (60%) = light background, 30% = mid accent, 10% = dark text/badge
            var bg = '#ffffff', txt = '#0c3557', accent = '';
            if(busyType === 'Order'){ bg = '#fee2e2'; accent = '#fca5a5'; txt = '#991b1b'; }
            else if(busyType === 'Service'){ bg = '#f3e8ff'; accent = '#d6bbff'; txt = '#7c3aed'; }
            html+='<td style="padding:3px"><div style="padding:8px 0;border-radius:6px;font-size:15px;font-weight:700;'+
              'background:'+bg+';color:'+txt+';border:1px solid '+(accent||'rgba(0,0,0,0.04)')+'">'+d+'</div></td>';
            d++;
          }
        }
      html+='</tr>';
    }
    html+='</tbody></table>';
    root.innerHTML=html;
    root.querySelectorAll('[data-nav]').forEach(btn=>btn.onclick=function(){ var dir=parseInt(this.getAttribute('data-nav')); draw(new Date(y,m+dir,1)); });
  }
  function fmtRange(isoStart, isoEnd){
    var s=new Date(isoStart+'T00:00:00'); var e=new Date(isoEnd+'T00:00:00');
    var sameMonth = s.getMonth()===e.getMonth() && s.getFullYear()===e.getFullYear();
    var sm=s.toLocaleString(undefined,{month:'short'}), em=e.toLocaleString(undefined,{month:'short'});
    var sd=String(s.getDate()), ed=String(e.getDate());
    var sy=s.getFullYear(), ey=e.getFullYear();
    if(isoStart===isoEnd) return sm+' '+sd+', '+sy;
    if(sameMonth) return sm+' '+sd+'–'+ed+', '+sy;
    return sm+' '+sd+', '+sy+' – '+em+' '+ed+', '+ey;
  }
  function renderBusyList(){
    var box=document.getElementById('busyList'); if(!box) return; box.innerHTML='';
    if(!Array.isArray(BLOCKS) || !BLOCKS.length){ box.innerHTML='<div style="color:#64748b">No busy spans.</div>'; return; }
    BLOCKS.forEach(function(b){
      var badgeColor = b.type==='Order' ? '#ef4444' : '#8b5cf6'; // red / violet primary
      var who = b.who && b.who.trim()? ('<div style="font-size:12px;color:#eaf2ff">Customer: '+b.who+(b.region? ' <span class="badge" style="background:'+ (b.type==='Order' ? '#ef4444':'#8b5cf6') +';color:#fff;margin-left:6px">'+b.region+'</span>':'')+'</div>') : '';
      var el=document.createElement('div');
      el.className='card';
      el.style.margin='0'; el.style.background='transparent'; el.style.borderColor='#1d4470';
      el.innerHTML='<div style="display:flex;justify-content:space-between;gap:8px;align-items:center;color:#fff">'
        +'<div><div style="font-weight:700;color:#ffffff">'+fmtRange(b.start,b.end)+'</div>'
        + who + '</div>'
        +'<span class="badge" style="background:'+badgeColor+';color:#fff">'+b.type+'</span>'
        +'</div>';
      box.appendChild(el);
    });
  }
  fetch('<?= BASE_URL ?>/busy_dates.php').then(r=>r.json()).then(j=>{ try{ busy = j.busy || {}; }catch(e){ busy = {}; } draw(new Date()); });
  renderBusyList();
  // Responsive fallback: stack panels on small screens
  (function(){
    var wrap=document.querySelector('.admin-cal-wrap'); if(!wrap) return;
    function apply(){ if(window.innerWidth<900){ wrap.style.gridTemplateColumns='1fr'; } else { wrap.style.gridTemplateColumns='1fr minmax(260px,360px) 1fr'; }
      // sync side panel heights with calendar height to reduce wasted space
      var calWrap=document.getElementById('adminCalWrap'); var L=document.getElementById('calInfoLeft'); var R=document.getElementById('calInfoRight');
      var h = calWrap ? calWrap.offsetHeight : 0; if(h && L && R){ L.style.minHeight = h+'px'; R.style.minHeight = h+'px'; }
    }
    apply(); window.addEventListener('resize', apply);
  })();
})();
// Orders real-time updater (polling)
(function(){
  var BASE = '<?= BASE_URL ?>';
  var tbody = document.querySelector('.rtable tbody'); if(!tbody) return;
  function esc(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[c];}); }
  function rowHtml(o){
    var actions='';
    if(o.order_status==='pending'){
      actions = '<form method="post">\
        <input type="hidden" name="id" value="'+o.order_id+'">\
        <input type="hidden" name="action" value="confirm">\
        <button class="btn" title="Confirm">Confirm</button>\
      </form>\
      <a class="btn secondary" href="'+BASE+'/admin/order_view.php?id='+o.order_id+'">View</a>\
      <form method="post" onsubmit="return confirm(\'Cancel this order?\');">\
        <input type="hidden" name="id" value="'+o.order_id+'">\
        <input type="hidden" name="action" value="cancel">\
        <button class="btn secondary">Cancel</button>\
      </form>';
    } else if(o.order_status==='confirmed'){
      actions = '<form method="post">\
        <input type="hidden" name="id" value="'+o.order_id+'">\
        <input type="hidden" name="action" value="ship">\
        <button class="btn" title="Out for Delivery">Out for Delivery</button>\
      </form>\
      <a class="btn secondary" href="'+BASE+'/admin/order_view.php?id='+o.order_id+'">View</a>\
      <form method="post" onsubmit="return confirm(\'Cancel this order?\');">\
        <input type="hidden" name="id" value="'+o.order_id+'">\
        <input type="hidden" name="action" value="cancel">\
        <button class="btn secondary">Cancel</button>\
      </form>';
    } else if(o.order_status==='shipped'){
      actions = '<form method="post">\
        <input type="hidden" name="id" value="'+o.order_id+'">\
        <input type="hidden" name="action" value="done">\
        <button class="btn" title="Mark as Done">Order Received</button>\
      </form>\
      <a class="btn secondary" href="'+BASE+'/admin/order_view.php?id='+o.order_id+'">View</a>';
    } else {
      actions = '<a class="btn secondary" href="'+BASE+'/admin/order_view.php?id='+o.order_id+'">View</a>';
    }
    return '<tr>'+
      '<td data-label="ID">#'+o.order_id+'</td>'+
      '<td data-label="Customer">'+esc(o.customer_name)+'<br><small>'+esc(o.contact_number)+'</small></td>'+
      '<td data-label="Items">'+esc(o.product_name)+' ('+o.quantity+')</td>'+
      '<td data-label="Amount"><?= CURRENCY ?>'+Number(o.total_price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+'</td>'+
      '<td data-label="Status"><span class="badge">'+esc(o.status_label)+'</span></td>'+
      '<td data-label="Payment">'+esc(o.payment_method)+(o.is_paid?' (paid)':'')+'</td>'+
      '<td data-label="Actions"><div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">'+actions+'</div></td>'+
      '</tr>';
  }
  function render(list){ tbody.innerHTML = (list||[]).map(rowHtml).join(''); }
  // services renderer
  var sbody = document.querySelector('.services-body');
  function servEsc(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g,function(c){return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'})[c];}); }
  function serviceRowHtml(s){
    var actions='';
    if(s.status==='Pending'){
      actions = '<form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="'+s.id+'"><input type="hidden" name="action" value="confirm"><button class="btn">Confirm</button></form>'+
                '<form method="post" onsubmit="return confirm(\'Cancel this request?\');"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="'+s.id+'"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>';
    } else if(s.status==='Confirmed'){
      actions = '<form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="'+s.id+'"><input type="hidden" name="action" value="proceed"><button class="btn">Proceeding</button></form>'+
                '<form method="post" onsubmit="return confirm(\'Cancel this request?\');"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="'+s.id+'"><input type="hidden" name="action" value="cancel"><button class="btn secondary">Cancel</button></form>';
    } else if(s.status==='In transit' || s.status==='In transit'){
      actions = '<form method="post"><input type="hidden" name="target" value="service"><input type="hidden" name="id" value="'+s.id+'"><input type="hidden" name="action" value="complete"><button class="btn">Complete</button></form>';
    }
    return '<tr>'+
      '<td data-label="ID">'+s.id+'</td>'+
      '<td data-label="Name">'+servEsc(s.customer_name)+'</td>'+
      '<td data-label="Type">'+servEsc(s.service_type)+'</td>'+
      '<td data-label="Date">'+servEsc(s.preferred_date||'')+'</td>'+
      '<td data-label="Description / Concern" style="max-width:260px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="'+servEsc(s.description||'')+'">'+servEsc(s.description||'')+'</td>'+
      '<td data-label="Status"><span class="badge">'+servEsc(s.status)+'</span></td>'+
      '<td data-label="Actions"><div style="display:flex;gap:6px;flex-wrap:wrap">'+actions+'</div></td>'+
    '</tr>';
  }
  function renderServices(list){ if(!sbody) return; sbody.innerHTML = (list||[]).map(serviceRowHtml).join(''); }

  // current filter state (all | inprogress | complete | canceled)
  var currentFilter = 'all';
  function fetchList(){ return fetch('?json=list&filter='+encodeURIComponent(currentFilter),{cache:'no-store'}).then(function(r){return r.json();}); }
  function tick(){ fetchList().then(function(j){ if(j&&Array.isArray(j.orders)){ render(j.orders); }
      if(j&&Array.isArray(j.services)){ renderServices(j.services); }
      if(j&&Array.isArray(j.blocks)){ if(window.setAdminBlocks) window.setAdminBlocks(j.blocks); if(window.adminCalReload) window.adminCalReload(); }
    }).catch(function(){}); }
  // Wire filter buttons
  function setFilterActive(which){
    currentFilter = which;
    var ids = ['fAll','fIn','fComp','fCan'];
    ids.forEach(function(id){ var el=document.getElementById(id); if(!el) return; el.classList.add('secondary'); el.classList.remove('btn'); });
    var map = { all:'fAll', inprogress:'fIn', complete:'fComp', canceled:'fCan' };
    var activeId = map[which]; var a=document.getElementById(activeId);
    if(a){ a.classList.remove('secondary'); a.classList.add('btn'); }
    tick();
  }
  try{ document.getElementById('fAll').addEventListener('click', function(){ setFilterActive('all'); }); }catch(e){}
  try{ document.getElementById('fIn').addEventListener('click', function(){ setFilterActive('inprogress'); }); }catch(e){}
  try{ document.getElementById('fComp').addEventListener('click', function(){ setFilterActive('complete'); }); }catch(e){}
  try{ document.getElementById('fCan').addEventListener('click', function(){ setFilterActive('canceled'); }); }catch(e){}
  // Ensure initial visual state is consistent
  try{ setFilterActive(currentFilter||'all'); }catch(e){}
  var iv = setInterval(tick, 5000);
  if(document.visibilityState==='visible'){ setTimeout(tick, 1000); }
  document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='visible') tick(); });
})();
// Global confirmation for admin action forms (orders & services)
document.addEventListener('submit', function(e){
  try{
    var form = e.target; if(!(form instanceof HTMLFormElement)) return;
    // only intercept forms that carry an action or target input (our action forms)
    if(!form.querySelector('input[name="action"], input[name="target"]')) return;
    // skip if form already has an onsubmit confirmation to avoid double prompts
    var on = form.getAttribute('onsubmit') || '';
    if(/confirm\s*\(/i.test(on)) return;
    if(!confirm('Are you sure you want to continue this action?')){ e.preventDefault(); e.stopImmediatePropagation(); }
  }catch(err){}
}, true);
</script>
