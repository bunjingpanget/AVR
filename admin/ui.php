<?php
// Simple admin layout wrapper with inline styles (no external files)
require_once __DIR__ . '/../includes/functions.php';

function admin_layout_start(string $title='Admin'): void {
  $base = BASE_URL;
  $me = basename($_SERVER['SCRIPT_NAME']);
  $user = current_user();
  // dynamic counters (pending only; hide when zero). Unseen messages based on session marker.
  try { $cOrders = (int)db()->query("SELECT COUNT(*) c FROM orders WHERE order_status='pending'")->fetch()['c']; } catch (Throwable $e) { $cOrders = 0; }
  try { $cServices = (int)db()->query("SELECT COUNT(*) c FROM services WHERE status='Pending'")->fetch()['c']; } catch (Throwable $e) { $cServices = 0; }
  try { $cProducts = (int)db()->query('SELECT COUNT(*) c FROM products')->fetch()['c']; } catch (Throwable $e) { $cProducts = 0; }
  try { $seen = (int)($_SESSION['admin_msgs_seen'] ?? 0); $st = db()->prepare('SELECT COUNT(*) c FROM order_messages WHERE id > :s'); $st->execute([':s'=>$seen]); $cMessages = (int)$st->fetch()['c']; } catch (Throwable $e) { $cMessages = 0; }
  echo "<!DOCTYPE html><html lang=\"en\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><title>" . h($title) . " • Admin</title>\n";
  // Inline CSS
  echo '<style>
  :root{--bg:#081a2f;--bg2:#0b2340;--side:#0b2746;--panel:#0d2d52;--card:rgba(255,255,255,.06);--txt:#e5effa;--mut:#9fb3c8;--blue:#0ea5e9;--blue2:#2ea9ff}
  *{box-sizing:border-box} html,body{height:100%} body{margin:0;overflow-x:hidden;background:linear-gradient(180deg,var(--bg),var(--bg2));color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}
  img,video{max-width:100%;height:auto}
  a{color:#9fd3ff;text-decoration:none}
  .awrap{display:flex;min-height:100vh}
  .aside{width:240px;background:linear-gradient(135deg, #0c3557, #0d4873);padding:14px;position:sticky;top:0;height:100vh;display:flex;flex-direction:column}
  .aside-backdrop{display:none}
  .amenu-toggle{position:fixed;top:10px;right:10px;z-index:1002;display:none;border:1px solid rgba(255,255,255,.2);background:rgba(8,26,47,.6);color:#fff;border-radius:10px;padding:8px 10px;backdrop-filter:blur(6px);font-size:20px;line-height:1}
  .brand{font-weight:800;margin:4px 0 10px 0;letter-spacing:.5px}
  .me{display:flex;gap:10px;align-items:center;background:rgba(255,255,255,.06);padding:10px;border-radius:10px;margin:10px 0}
  .me .role{color:var(--mut);font-size:12px}
  .nav{display:flex;flex-direction:column;gap:6px;margin:10px 0;flex:1}
  .nav a, .aside-bottom a{position:relative;display:flex;align-items:center;justify-content:space-between;color:#cfe6ff;padding:10px 14px;border-radius:12px;font-weight:600;letter-spacing:.3px;background:rgba(255,255,255,.02);border:1px solid rgba(255,255,255,.04);transition:.28s}
  .nav a:hover,.nav a:focus,.aside-bottom a:hover,.aside-bottom a:focus{background:rgba(255,255,255,.08);transform:translateX(4px);color:#fff}
  .nav a.active{background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;box-shadow:0 4px 14px -2px rgba(14,165,233,.45);border-color:rgba(255,255,255,.18)}
  .pill{display:inline-block;min-width:22px;text-align:center;font-size:11px;font-weight:600;color:#fff;background:linear-gradient(135deg,#0ea5e9,#38bdf8);border-radius:32px;padding:3px 8px;margin-left:10px}
  .amain{flex:1;padding:20px}
  .h1{font-size:20px;margin:0 0 12px 0}
  .grid{display:grid;gap:12px}
  .grid.cols-3{grid-template-columns:repeat(3,1fr)}
  .card{background:linear-gradient(135deg, #04223f, #063866);border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:12px}
  .stat{font-size:28px;font-weight:800}
  .table{width:100%;border-collapse:collapse}
  .table th,.table td{padding:10px;border-bottom:1px solid rgba(255,255,255,.08);text-align:left}
  /* Responsive stacked table variant */
  .table.rtable{width:100%}
  @media (max-width: 780px){
    .table.rtable thead{display:none}
    .table.rtable tr{display:block;background:linear-gradient(135deg,#07213b,#0b2f53);border:1px solid rgba(255,255,255,.08);border-radius:12px;margin:10px 0}
    .table.rtable td{display:flex;gap:10px;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;padding:10px 12px;border-bottom:1px solid rgba(255,255,255,.08);word-break:break-word}
    .table.rtable td:last-child{border-bottom:0}
    .table.rtable td::before{content:attr(data-label);font-weight:700;color:var(--mut);min-width:120px;flex:0 0 auto}
    .table.rtable td > *{max-width:100%}
  }
  .btn{appearance:none;border:0;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--blue2));color:#fff;padding:8px 12px;cursor:pointer}
  .btn.secondary{background:#334155}
  .badge{display:inline-block;border-radius:999px;background:#113357;color:#d5ecff;padding:2px 8px;font-size:12px;text-transform:uppercase}
  .section{margin-bottom:16px}
  /* Responsive admin layout */
  @media (max-width: 980px){
    .amenu-toggle{display:block}
    .aside{position:fixed;left:0;top:0;height:100dvh;width:280px;transform:translateX(-100%);transition:transform .25s ease;z-index:1001;overflow-y:auto;-webkit-overflow-scrolling:touch}
    .awrap.nav-open .aside{transform:translateX(0)}
    .aside-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);display:none;z-index:1000}
    .awrap.nav-open .aside-backdrop{display:block}
    .amain{padding:14px}
    .grid.cols-3{grid-template-columns:1fr}
    .table{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch}
  }
  </style>';
  echo "</head><body><div class=\"awrap\">";
  // Mobile hamburger button
  echo "<button class=\"amenu-toggle\" aria-label=\"Menu\" aria-expanded=\"false\">&#9776;</button>";
  echo "<aside class=\"aside\">";
  echo "<div class=\"brand\"><img src=\"$base/assets/images/logo.png\" alt=\"FNPSS\" style=\"width:140px;display:block;margin:4px 0 10px\"></div>";
  echo "<div class=\"me\"><div><div>" . h($user['name'] ?? 'Admin') . "</div><div class=\"role\">ADMIN</div></div></div>";
  $links = [
    ['index.php','Dashboard',null],
    ['admin_orders.php','Transactions',$cOrders],
    ['admin_products.php','Products',null], // always accessible, no disappearing count needed
    ['admin_messages.php','Messages',$cMessages],
    ['admin_users.php','Users',null],
    ['admin_admins.php','Admins',null],
  ];
  echo "<nav class=\"nav\">";
  foreach ($links as [$href,$label,$count]) {
    $active = ($me === $href) ? 'active' : '';
  // show pill only if count > 0
  $pill = ($count && $count>0)?("<span class=\"pill\">". (int)$count ."</span>") : '';
    echo "<a class=\"$active\" href=\"$base/admin/$href\"><span>$label</span>$pill</a>";
  }
  echo "</nav>";
  echo "<div class=\"aside-bottom\" style=\"margin-top:auto\"><a href=\"$base/logout.php\">Logout</a></div>";
  echo "</aside>";
  // Backdrop for off-canvas sidebar
  echo "<div class=\"aside-backdrop\"></div>";
  echo "<main class=\"amain\"><h1 class=\"h1\">" . h($title) . "</h1>";
  // Live sidebar counters updater
  echo '<script>(function(){
    function upd(){ fetch("'. $base .'/admin/data.php",{cache:"no-store"}).then(r=>r.json()).then(function(j){ if(!j||!j.counts) return; var c=j.counts;
      var links=document.querySelectorAll(".nav a"); links.forEach(function(a){ var label=a.textContent.trim(); var pill=a.querySelector(".pill"); var v=0;
  if(label.indexOf("Transactions")===0 || label.indexOf("Orders")===0) v=c.pendingOrders||0; else if(label.indexOf("Services")===0) v=c.pendingServices||0; else if(label.indexOf("Messages")===0) v=c.newMsgs||0; else v=0;
        if(v>0){ if(!pill){ pill=document.createElement("span"); pill.className="pill"; a.appendChild(pill);} pill.textContent=v; } else { if(pill){ pill.remove(); } }
      });
    }).catch(function(){}); }
    setInterval(upd, 8000); setTimeout(upd, 1200);
    // Mobile sidebar toggle
    var wrap=document.querySelector(".awrap"), btn=document.querySelector(".amenu-toggle"), aside=document.querySelector(".aside"), bg=document.querySelector(".aside-backdrop");
    function closeNav(){ wrap.classList.remove("nav-open"); btn && btn.setAttribute("aria-expanded","false"); }
    function toggleNav(){
      var on=wrap.classList.toggle("nav-open"); btn && btn.setAttribute("aria-expanded", on?"true":"false");
    }
    btn && btn.addEventListener("click", toggleNav);
    bg && bg.addEventListener("click", closeNav);
    document.addEventListener("keydown", function(e){ if(e.key==="Escape") closeNav(); });
    // Close on nav link click (helps on mobile)
    document.addEventListener("click", function(e){ var a=e.target.closest && e.target.closest(".nav a"); if(a) closeNav(); });
    // Reset state when resizing to desktop
    var mql=window.matchMedia("(min-width: 981px)");
    function onWide(ev){ if(ev.matches) closeNav(); }
    if(mql.addEventListener) mql.addEventListener("change", onWide); else mql.addListener(onWide);
  })();</script>';
}

function admin_layout_end(): void {
  $base = BASE_URL;
  // Include shared client script so admin pages have AddressPicker and helpers
  echo "<script src=\"{$base}/assets/js/script.js?v=20251002\"></script>";
  echo "</main></div></body></html>";
}
?>

