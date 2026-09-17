<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();

// Quick stats
$pendingOrders = (int)db()->query("SELECT COUNT(*) c FROM orders WHERE order_status='pending'")->fetch()['c'];
$pendingServices = 0; try { $pendingServices = (int)db()->query("SELECT COUNT(*) c FROM services WHERE status='Pending'")->fetch()['c']; } catch(Throwable $e) {}
// Pending messages: count distinct orders that have at least one message while order still pending
try { $pendingMsgs = (int)db()->query("SELECT COUNT(DISTINCT o.order_id) c FROM order_messages om JOIN orders o ON o.order_id=om.order_id WHERE o.order_status='pending'")->fetch()['c']; } catch(Throwable $e) { $pendingMsgs = 0; }
$products = (int)db()->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
// Estimated Revenue depends only on orders marked as Done (delivered)
$revenue = (float)db()->query("SELECT IFNULL(SUM(total_price),0) t FROM orders WHERE order_status = 'delivered'")->fetch()['t'];

// Recent orders list for Transactions Overview: only delivered (Order Received)
$recent = db()->query("SELECT order_id,customer_name,total_price,order_status,created_at,delivery_date FROM orders WHERE order_status = 'delivered' ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Top customers for Transactions Overview (only count delivered orders)
$topCustomers = db()->query("SELECT u.id,u.name,u.email,COUNT(o.order_id) cnt,SUM(o.total_price) amt FROM users u JOIN orders o ON o.user_id=u.id WHERE (u.role IS NULL OR u.role<>'admin') AND o.order_status = 'delivered' GROUP BY u.id,u.name,u.email ORDER BY cnt DESC, amt DESC LIMIT 5")->fetchAll();

// Order overview aggregated by day (last 14 days): only delivered orders for Transactions Overview
$overview = db()->query("SELECT DATE(created_at) d, SUM(total_price) sales, COUNT(*) orders, COUNT(DISTINCT user_id) customers FROM orders WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) AND order_status = 'delivered' GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
$ovMap = []; foreach($overview as $o){ $ovMap[$o['d']]=$o; }
$days=[]; for($i=13;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $row=$ovMap[$d]??['d'=>$d,'sales'=>0,'orders'=>0,'customers'=>0]; $days[]=$row; }

admin_layout_start('Dashboard');
?>
<div class="card dash-card" style="background:linear-gradient(135deg,#04223f,#063866);padding:24px;display:flex;flex-direction:column;gap:20px">
  <div class="dash-head" style="display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start">
    <div class="dash-left" style="flex:1">
      <div class="dash-welcome" style="font-size:16px;color:#8fbbe2;font-weight:600">WELCOME BACK SIR, <?= h(current_user()['name'] ?? 'Admin'); ?></div>
  <div class="dash-subtitle" style="font-size:18px;margin-top:6px;color:#b5d4ec;letter-spacing:.5px">YOUR CONTROL CENTER RECENT TRANSACTIONS & INSIGHTS</div>
    </div>
    <div class="dash-right" style="text-align:right">
      <div style="font-size:16px;color:#8fbbe2;text-transform:uppercase">Estimated Revenue</div>
      <div id="revVal" style="font-size:30px;font-weight:800;margin-top:4px;letter-spacing:1px"><?= CURRENCY . number_format($revenue,2); ?></div>
    </div>
  </div>
  <?php $statStyle='background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.1);padding:18px 16px;border-radius:12px;min-height:90px;display:flex;flex-direction:column;justify-content:space-between'; ?>
  <div class="dash-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px">
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Orders</div><div id="statPendingOrders" style="font-size:26px;font-weight:700;line-height:1;margin-top:4px"><?= $pendingOrders; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Services</div><div id="statPendingServices" style="font-size:26px;font-weight:700;margin-top:4px"><?= $pendingServices; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Pending Messages</div><div id="statPendingMsgs" style="font-size:26px;font-weight:700;margin-top:4px"><?= $pendingMsgs; ?></div></div>
    <div style="<?= $statStyle ?>"><div style="font-size:11px;color:#8fbbe2;text-transform:uppercase">Total Products</div><div id="statProducts" style="font-size:26px;font-weight:700;margin-top:4px"><?= $products; ?></div></div>
  </div>
</div>

<div class="adm-two" style="display:grid;grid-template-columns:1fr 300px;gap:16px;margin-top:16px">
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card" style="position:relative">
      <div style="font-weight:600;margin-bottom:6px">Sales Ratio (Last 14 Days)</div>
      <div style="display:flex;flex-direction:column;gap:12px">
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <div style="font-size:12px;color:#8fbbe2;margin:0 0 4px 2px">Sales (PHP)</div>
            <div id="ovSalesToday" style="font-size:13px;font-weight:700;color:#e6f7ff" aria-label="Today Sales"></div>
          </div>
          <canvas id="ovSales" height="600" style="width:100%;max-height:680px;cursor:pointer" title="Click for details"></canvas>
        </div>
        <div style="border-top:1px solid rgba(255,255,255,.08);padding-top:8px">
          <div style="display:flex;align-items:center;justify-content:space-between;gap:8px">
            <div style="font-size:12px;color:#8fbbe2;margin:0 0 4px 2px">Customers (unique per day)</div>
            <div id="ovCustomersToday" style="font-size:13px;font-weight:700;color:#e6f7ff" aria-label="Today Customers"></div>
          </div>
          <canvas id="ovCustomers" height="160" style="width:100%;max-height:280px;cursor:pointer" title="Click for details"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div style="display:flex;flex-direction:column;gap:16px">
    <div class="card low-stock-card" style="min-height:500px">
      <div style="font-weight:600;margin-bottom:8px">Low Stock (Below 5)</div>
      <table class="table" style="width:100%">
        <thead><tr><th style="text-align:left">Product</th><th style="text-align:right">Stock</th><th style="text-align:right">Price</th></tr></thead>
        <tbody id="lowStockTbody"><tr><td colspan="3" style="padding:14px;color:#7ea7c6">Loading…</td></tr></tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Orders Overview: split into two simple charts for clarity (Sales line, Customers bars).
(function(){
  var data = <?php echo json_encode($days); ?>;
  var cvSales = document.getElementById('ovSales');
  var cvCustomers = document.getElementById('ovCustomers');
  if(!cvSales || !cvCustomers) return;
  var ctxSales = cvSales.getContext('2d');
  var ctxCustomers = cvCustomers.getContext('2d');

  function roundUpNice(v){ var steps=[1000,2000,5000,10000,20000,25000,50000,100000,200000,500000,1000000]; for(var i=0;i<steps.length;i++){ if(v<=steps[i]) return steps[i]; } return Math.pow(10, Math.ceil(Math.log10(v))); }

  function drawSales(){
    var w=cvSales.width=cvSales.clientWidth*2, h=cvSales.height=cvSales.clientHeight*2; ctxSales.setTransform(1,0,0,1,0,0); ctxSales.scale(2,2); w/=2; h/=2; ctxSales.clearRect(0,0,w,h);
    // Increased paddings for clearer separation from labels and edges
    var padTop=24, padBottom=16, left=86, right=w-20; var plotH=h-padTop-padBottom;
    var step = 100000; // 100k
    var topSales = 2000000; // 2M fixed max as requested
    // grid + y labels every 100k
    ctxSales.strokeStyle='rgba(255,255,255,.12)'; ctxSales.fillStyle='#7ea7c6'; ctxSales.font='11px system-ui'; ctxSales.textAlign='right';
    for(var v=0; v<=topSales; v+=step){ var y=padTop+(1-v/topSales)*plotH; ctxSales.beginPath(); ctxSales.moveTo(left,y); ctxSales.lineTo(right,y); ctxSales.stroke(); ctxSales.fillText('₱'+Math.round(v).toLocaleString(), left-6, y+4); }
    // line (clamp values into range)
    var bw=(right-left)/Math.max(1,data.length);
    var pts=data.map(function(r,i){ var x=left + bw*i + bw/2; var val=Math.max(0, Math.min(topSales, Number(r.sales)||0)); var y=padTop + (1-(val/topSales))*plotH; return {x:x,y:y}; });
    ctxSales.strokeStyle='#48c7ff'; ctxSales.lineWidth=2; ctxSales.beginPath();
    pts.forEach(function(p,i){ if(i===0) ctxSales.moveTo(p.x,p.y); else { var prev=pts[i-1]; var mx=(prev.x+p.x)/2, my=(prev.y+p.y)/2; ctxSales.quadraticCurveTo(prev.x, prev.y, mx, my); if(i===pts.length-1) ctxSales.quadraticCurveTo(p.x,p.y,p.x,p.y); }});
    ctxSales.stroke();
    pts.forEach(function(p){ ctxSales.fillStyle='#48c7ff'; ctxSales.beginPath(); ctxSales.arc(p.x,p.y,3,0,Math.PI*2); ctxSales.fill(); });
  }

  function drawCustomers(){
    var w=cvCustomers.width=cvCustomers.clientWidth*2, h=cvCustomers.height=cvCustomers.clientHeight*2; ctxCustomers.setTransform(1,0,0,1,0,0); ctxCustomers.scale(2,2); w/=2; h/=2; ctxCustomers.clearRect(0,0,w,h);
    var padTop=10, padBottom=30, left=40, right=w-20; var plotH=h-padTop-padBottom;
    var maxCustomers=0; data.forEach(function(r){ maxCustomers=Math.max(maxCustomers, Number(r.customers)||0); });
    var topCustomers=Math.max(1, maxCustomers); // integer ticks
    // grid + y labels
    ctxCustomers.strokeStyle='rgba(255,255,255,.12)'; ctxCustomers.fillStyle='#a8d4f7'; ctxCustomers.font='11px system-ui'; ctxCustomers.textAlign='right';
    for(var v=0; v<=topCustomers; v++){ var y=padTop+(1-(v/topCustomers))*plotH; ctxCustomers.beginPath(); ctxCustomers.moveTo(left,y); ctxCustomers.lineTo(right,y); ctxCustomers.stroke(); ctxCustomers.fillText(String(v), left-6, y+4); }
    // bars
    var bw=(right-left)/data.length; ctxCustomers.fillStyle='#1d84d4';
    data.forEach(function(r,i){ var x=left + bw*i + 4; var barW=bw-8; var bh=((Number(r.customers)||0)/topCustomers)*plotH; ctxCustomers.fillRect(x, padTop + (plotH-bh), Math.max(2,barW), bh); });
    // x labels (dates) every 2
    ctxCustomers.fillStyle='#7ea7c6'; ctxCustomers.textAlign='center';
    for(var i=0;i<data.length;i+=2){ var x=left + bw*i + bw/2; var d=new Date(data[i].d+'T00:00:00'); ctxCustomers.fillText(d.toLocaleDateString(undefined,{month:'short',day:'numeric'}), x, h-10); }
  }

  function drawAll(){ drawSales(); drawCustomers(); }
  drawAll(); window.addEventListener('resize', drawAll);
  // Update quick today badges
  function updateTodayBadges(){
    try{
      if(!Array.isArray(data) || data.length===0) return;
      var last = data[data.length-1]||{};
      var s = Number(last.sales||0);
      var c = Number(last.customers||0);
      var sEl = document.getElementById('ovSalesToday');
      var cEl = document.getElementById('ovCustomersToday');
      if(sEl) sEl.textContent = 'Today: <?= CURRENCY ?>' + (new Intl.NumberFormat(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})).format(s);
      if(cEl) cEl.textContent = 'Today: ' + c;
    }catch(e){}
  }
  updateTodayBadges();

  // Simple analytics modal on chart click
  var modal;
  function openModal(){
    if(modal){ modal.remove(); modal=null; }
    modal=document.createElement('div');
    modal.innerHTML = `
      <div class="adm-modal-backdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);z-index:1000"></div>
  <div class="adm-modal" style="position:fixed;inset:auto 20px 20px 20px;top:10%;max-width:1100px;max-height:92dvh;overflow:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;margin:0 auto;background:linear-gradient(135deg,#06345d,#0a477e);border:1px solid rgba(255,255,255,.15);border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.4);z-index:1001;padding:16px;color:#e6f2ff">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <div style="font-weight:600;letter-spacing:.5px">TRANSACTIONS OVERVIEW</div>
          <button id="admClose" class="btn secondary" style="padding:6px 10px">Close</button>
        </div>
        <div class="adm-grid" style="display:grid;grid-template-columns:1.2fr 1fr;gap:14px">
          <div class="panel" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:12px">
            <div style="font-weight:600;margin-bottom:8px">Top Products (by quantity)</div>
            <div id="admTopProducts" style="display:grid;grid-template-columns:1fr auto auto;gap:8px;font-size:13px"></div>
          </div>
          <div class="panel" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:12px">
            <div style="font-weight:600;margin-bottom:8px">Customers</div>
            <div id="admCustomers" style="font-size:14px"></div>
          </div>
        </div>
        <div class="adm-grid2" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:12px">
          <div class="panel" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:12px;min-height:360px">
            <div style="font-weight:600;margin-bottom:8px;font-size:17px">Top Regions (Sales)</div>
            <canvas id="admRegionsPie" height="320" style="width:100%;height:320px;max-height:480px"></canvas>
          </div>
          <div class="panel" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);border-radius:12px;padding:12px;min-height:480px;display:flex;flex-direction:column;gap:10px">
            <div style="font-weight:600;margin-bottom:2px;font-size:17px">Customer Satisfaction</div>
            <div id="admRatings" style="font-size:14px"></div>
            <div style="font-weight:600;margin-top:6px">Sales Entered</div>
            <div id="admSalesPeriods" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
              <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;min-height:96px;display:flex;flex-direction:column;justify-content:space-between">
                <div style="font-size:13px;color:#b7d6f3">Today</div>
                <div id="spToday" style="font-size:22px;font-weight:800;letter-spacing:.3px;line-height:1.2"></div>
              </div>
              <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;min-height:96px;display:flex;flex-direction:column;justify-content:space-between">
                <div style="font-size:13px;color:#b7d6f3">This Week</div>
                <div id="spWeek" style="font-size:22px;font-weight:800;letter-spacing:.3px;line-height:1.2"></div>
              </div>
              <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;min-height:96px;display:flex;flex-direction:column;justify-content:space-between">
                <div style="font-size:13px;color:#b7d6f3">This Month</div>
                <div id="spMonth" style="font-size:22px;font-weight:800;letter-spacing:.3px;line-height:1.2"></div>
              </div>
              <div style="background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:12px;padding:14px;min-height:96px;display:flex;flex-direction:column;justify-content:space-between">
                <div style="font-size:13px;color:#b7d6f3">This Year</div>
                <div id="spYear" style="font-size:22px;font-weight:800;letter-spacing:.3px;line-height:1.2"></div>
              </div>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(modal);
    modal.querySelector('.adm-modal-backdrop').addEventListener('click', closeModal);
    modal.querySelector('#admClose').addEventListener('click', closeModal);

    // small helper to load analytics for a given period and update modal + main page
    function loadPeriod(period){
      fetch('<?= BASE_URL ?>/admin/data.php?period='+encodeURIComponent(period), {cache:'no-store'})
        .then(r=>r.json()).then(function(j){
          if(!j) return;
          // update modal customers summary
          var cc=(j.analytics && j.analytics.customers) || {};
          var el=document.getElementById('admCustomers'); if(el) el.innerHTML = '<div><strong>Total Registered:</strong> '+(cc.total||0)+'</div><div><strong>Have Ordered:</strong> '+(cc.buyers||0)+'</div>';
          // top products
          var tp=(j.analytics && j.analytics.topProducts) || [];
          var tpEl=document.getElementById('admTopProducts'); if(tpEl) tpEl.innerHTML = tp.map(function(r){ return '<div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escapeHtml(r.name||'')+'</div><div style="text-align:right">× '+(r.qty||0)+'</div><div style="text-align:right">₱'+Number(r.amt||0).toLocaleString()+'</div>'; }).join('');
          // ratings
          var rt=(j.analytics && j.analytics.ratings) || {};
          var rEl=document.getElementById('admRatings'); if(rEl){
            var dist=rt.dist||{};
            function starRow(n){ var cnt=dist[n]||0; var barW=Math.min(100, cnt>0? (cnt/Math.max(1,(rt.count||1)))*100 : 0); return '<div style="display:flex;align-items:center;gap:8px;margin:6px 0"><span style="min-width:42px">'+n+'★</span><div style="flex:1;height:12px;background:rgba(255,255,255,.08);border-radius:8px;overflow:hidden"><div style="width:'+barW+'%;height:100%;background:#22c55e"></div></div><span style="min-width:28px;text-align:right">'+cnt+'</span></div>'; }
            rEl.innerHTML = '<div><strong>Average:</strong> '+ (rt.avg? rt.avg.toFixed(2):'0.00') +' ('+(rt.count||0)+' ratings)</div>'+ [5,4,3,2,1].map(starRow).join('');
          }
          // regions pie
          var pie = document.getElementById('admRegionsPie'); if(pie){ drawPie(pie.getContext('2d'), (j.analytics && j.analytics.topRegions) || []); }
          // sales periods values in tiles (useful for showing amounts on the tiles themselves)
          var sp=(j.analytics && j.analytics.salesPeriods) || {};
          var fmt=function(v){ try{return '<?= CURRENCY ?>'+(new Intl.NumberFormat(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})).format(Number(v||0));}catch(e){return '<?= CURRENCY ?>'+Number(v||0).toFixed(2);} };
          var d=document.getElementById('spToday'); if(d) d.textContent=fmt(sp.today||0);
          d=document.getElementById('spWeek'); if(d) d.textContent=fmt(sp.week||0);
          d=document.getElementById('spMonth'); if(d) d.textContent=fmt(sp.month||0);
          d=document.getElementById('spYear'); if(d) d.textContent=fmt(sp.year||0);

          // Update main page areas: overview charts only (Recent/TopCustomers removed from dashboard)
          if(Array.isArray(j.overview)) { data = j.overview; drawAll(); updateTodayBadges(); }
        }).catch(function(){});
    }

    // attach click handlers to the period tiles - make them interactive and accessible
    ['spToday','spWeek','spMonth','spYear'].forEach(function(id){
      var el = modal.querySelector('#'+id);
      if(!el) return;
      el.style.cursor = 'pointer';
      el.addEventListener('click', function(){
        var p = id==='spToday' ? 'today' : id==='spWeek' ? 'week' : id==='spMonth' ? 'month' : 'year';
        loadPeriod(p);
        // highlight selection briefly
        el.style.outline = '2px solid rgba(255,255,255,.06)'; setTimeout(function(){ el.style.outline=''; }, 400);
      });
    });

    // initial load: today
    loadPeriod('today');
  }
  function closeModal(){ if(modal){ modal.remove(); modal=null; } }
  cvSales.addEventListener('click', openModal);
  cvCustomers.addEventListener('click', openModal);

  function drawPie(pctx, rows){
    var W = pctx.canvas.width = pctx.canvas.clientWidth*2; var H = pctx.canvas.height = pctx.canvas.clientHeight*2; pctx.setTransform(1,0,0,1,0,0); pctx.scale(2,2); W/=2; H/=2;
    pctx.clearRect(0,0,W,H);
    var total = rows.reduce(function(a,r){ return a + (Number(r.amt)||0); },0);
    // Lower the pie a bit and leave a slightly larger margin so the circle is fully visible
    var pad = 14; var cx = W/2, cy = H/2 + 10, r = Math.min(W,H)/2 - pad; var start= -Math.PI/2; var colors=['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#84cc16','#f97316'];
    if(!rows || rows.length===0 || total<=0){ pctx.fillStyle='#7ea7c6'; pctx.fillText('No data', cx-20, cy); return; }
    rows.slice(0,8).forEach(function(row, idx){ var val = Number(row.amt)||0; var ang = (val/total) * Math.PI * 2; var end = start + ang; pctx.beginPath(); pctx.moveTo(cx,cy); pctx.arc(cx,cy,r,start,end); pctx.closePath(); pctx.fillStyle = colors[idx % colors.length]; pctx.fill(); start = end; });
    // Legend
    var y=10; pctx.font='12px system-ui'; pctx.fillStyle='#e6f2ff'; rows.slice(0,8).forEach(function(row, idx){ var lbl = (row.region||'UNKNOWN') + ' (' + '₱' + Number(row.amt||0).toLocaleString() + ')'; pctx.fillStyle=colors[idx%colors.length]; pctx.fillRect(W-180, y-8, 10, 10); pctx.fillStyle='#e6f2ff'; pctx.fillText(lbl, W-165, y); y+=16; });
  }

  // Live updates: poll admin/data.php every 8s
  function fmtCurrency(v){ try{ return (new Intl.NumberFormat(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})).format(v); }catch(e){ return Number(v).toFixed(2); } }
  function renderLowStock(rows){
    var tb = document.getElementById('lowStockTbody'); if(!tb) return;
    if(!rows || rows.length===0){ tb.innerHTML = '<tr><td colspan="3" style="padding:14px;color:#7ea7c6">All stocks are above 5.</td></tr>'; return; }
    tb.innerHTML = rows.map(function(r){
      var price = '<?= CURRENCY ?>' + fmtCurrency(r.price||0);
      var href = '<?= BASE_URL ?>/admin/admin_products.php?highlight=' + encodeURIComponent(r.id||'');
      var name = '<a href="'+href+'" style="color:inherit;text-decoration:none"><span class="ls-name" style="display:inline-block;max-width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+escapeHtml(r.name||'')+'</span></a>';
      return '<tr><td>'+name+'</td><td style="text-align:right">'+(r.stock||0)+'</td><td style="text-align:right">'+price+'</td></tr>';
    }).join('');
  }
  function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g,function(c){return ({"&":"&amp;","<":"&lt;",
      ">":"&gt;","\"":"&quot;","'":"&#39;"})[c];}); }
  
  function updateDash(){
    fetch('<?= BASE_URL ?>/admin/data.php', {cache:'no-store'}).then(r=>r.json()).then(function(j){
      if(j.counts){
        var c=j.counts; var el;
        if(el=document.getElementById('statPendingOrders')) el.textContent = c.pendingOrders;
        if(el=document.getElementById('statPendingServices')) el.textContent = c.pendingServices;
        if(el=document.getElementById('statPendingMsgs')) el.textContent = c.pendingMsgs;
        if(el=document.getElementById('statProducts')) el.textContent = c.products;
        if(el=document.getElementById('revVal')) el.textContent = '<?= CURRENCY ?>' + fmtCurrency(c.revenue||0);
      }
      if(Array.isArray(j.lowStock)) renderLowStock(j.lowStock);
  if(Array.isArray(j.overview)) { data = j.overview; drawAll(); updateTodayBadges(); }
    }).catch(function(){});
  }
  setInterval(updateDash, 8000);
  // Gentle initial refresh after 1s for perceived responsiveness
  setTimeout(updateDash, 1000);
})();
</script>
<?php admin_layout_end(); ?>
<style>
/* Dashboard-only responsive tweaks */
@media (max-width: 980px){
  .adm-two{ grid-template-columns:1fr !important }
  .dash-card{ padding:16px !important; gap:12px !important }
  .dash-head{ gap:12px !important }
  .dash-left{ min-width:0 !important }
  .dash-welcome{ font-size:12px !important }
  .dash-subtitle{ font-size:12px !important; letter-spacing:.2px !important }
  .dash-right #revVal{ font-size:22px !important; letter-spacing:.5px !important }
  .dash-stats-grid{ grid-template-columns:repeat(auto-fit,minmax(140px,1fr)) !important; gap:10px !important }
}
@media (max-width: 640px){
  .dash-card{ padding:12px !important }
  .dash-right #revVal{ font-size:20px !important }
}
/* Modal responsiveness: stack columns and scale canvases on small screens */
@media (max-width: 980px){
  .adm-modal{ inset:auto 12px 12px 12px !important; top:6% !important; max-width:unset !important; max-height:92dvh !important; overflow:auto !important; -webkit-overflow-scrolling:touch }
  .adm-grid{ grid-template-columns:1fr !important }
  .adm-grid2{ grid-template-columns:1fr !important }
}
@media (max-width: 640px){
  .adm-modal{ inset:auto 8px 8px 8px !important; top:4% !important; max-height:92dvh !important; overflow:auto !important }
  #ovSales{ height:200px !important }
  #ovCustomers{ height:120px !important }
  #admSalesPeriods{ grid-template-columns:1fr 1fr !important }
}
@media (max-width: 420px){
  #admSalesPeriods{ grid-template-columns:1fr !important }
}
/* Keep low stock panel reasonable on small screens */
@media (max-width: 640px){
  .low-stock-card{ min-height:320px !important }
}
</style>
