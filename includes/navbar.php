<?php require_once __DIR__ . '/functions.php'; ?>
<style>
  /* Scoped header typography bump */
  .site-header { --hdr-base: clamp(15px, 1.2vw + 10px, 18px); font-size: var(--hdr-base); }
  .site-header .brand span { font-size: calc(var(--hdr-base) + 6px); line-height: 1.1; }
  .site-header .nav a,
  .site-header .user-toggle,
  .site-header .chat-icon { font-size: calc(var(--hdr-base) + 1px); }
  .site-header .menu a { font-size: calc(var(--hdr-base) - 1px); }
  .site-header .subbar .subitem { font-size: calc(var(--hdr-base) - 1px); }
  /* Ensure icon button stays vertically centered with larger font */
  .site-header .chat-icon { line-height: 1; }
  /* Prevent wrap jitter if nav grows slightly */
  .site-header .nav { white-space: nowrap; }
  /* Make header containers span full width so brand sits at the far left */
  .site-header > .container,
  .site-header .subbar > .container { max-width: 90%; }
  /* Responsive logo sizing */
  .site-header .logo{ height:64px }
  @media (max-width: 980px){ .site-header .logo{ height:54px } }
  @media (max-width: 520px){ .site-header .logo{ height:46px } }
  @media (max-width: 520px) {
    /* Slightly tighten on very small screens */
    .site-header { --hdr-base: clamp(14px, 3.2vw + 6px, 17px); }
  }
  /* Subbar layout: 3 columns (left promo, centered support, right search) */
  .site-header .subbar .subgrid{ display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:12px }
  .site-header .subbar .subitem.left{ justify-self:start }
  .site-header .subbar .subitem.mid{ justify-self:center; text-align:center }
  .site-header .subbar .subitem.right{ justify-self:end }
  .site-header .subbar .searchbar{ display:flex; align-items:center; gap:6px }
  .site-header .subbar .searchbar input{ height:30px; padding:0 10px; border-radius:999px; border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.15); color:#fff; width:220px }
  .site-header .subbar .searchbar input::placeholder{ color:rgba(255,255,255,.8) }
  .site-header .subbar .searchbar button{ height:30px; padding:0 10px; border:0; border-radius:999px; background:linear-gradient(135deg,#024787,#0475d2); color:#fff; font-weight:800; cursor:pointer }
  /* Promo banner styles (as per screenshot) */
  .site-header .subbar .promo-banner{
    display:inline-flex; align-items:center; gap:10px; padding:8px 14px; border-radius:999px;
    background: radial-gradient(120% 120% at 10% 0%, rgba(255,255,255,.16), rgba(255,255,255,.04) 60%),
                linear-gradient(135deg, rgba(2,71,135,.92), rgba(4,117,210,.65));
    border:1px solid rgba(255,255,255,.22);
    box-shadow:0 8px 22px rgba(4,117,210,.28), inset 0 0 0 1px rgba(255,255,255,.06);
    color:#eaf4ff; font-weight:800; letter-spacing:.2px; text-shadow:0 1px 0 rgba(0,0,0,.25);
    font-size:calc(var(--hdr-base) + 2px); line-height:1.2;
    white-space:nowrap;
    cursor:pointer;
    transition:transform .2s ease, box-shadow .2s ease, filter .2s ease
  }
  .site-header .subbar .promo-banner .promo-badge{
    display:inline-flex; align-items:center; gap:8px; padding:6px 10px; border-radius:999px;
    background: linear-gradient(180deg,#ffb703,#ff9900);
    color:#0b2e4e; font-weight:900; letter-spacing:.3px; box-shadow:inset 0 0 0 1px rgba(0,0,0,.08);
  }
  .site-header .subbar .promo-banner .promo-badge .bolt{ filter:drop-shadow(0 1px 0 rgba(0,0,0,.25)); }
  .site-header .subbar .promo-banner .promo-text strong{ color:#ffea9a }
  .site-header .subbar .promo-banner:hover{
    transform:translateY(-1px);
    box-shadow:0 12px 28px rgba(4,117,210,.38), inset 0 0 0 1px rgba(255,255,255,.12);
    filter:brightness(1.03)
  }
  .site-header .subbar .promo-banner:hover .promo-badge{ filter:brightness(1.05) }
  .site-header .subbar .promo-banner:active{ transform:translateY(0) }
  .site-header .subbar .promo-banner:focus-visible{ outline:2px solid #ffb703; outline-offset:2px }
  @media (prefers-reduced-motion: reduce){ .site-header .subbar .promo-banner{ transition:none } }
  @media (max-width: 700px){ .site-header .subbar .promo-banner{ font-size:calc(var(--hdr-base) + 0px); padding:6px 10px } }
  /* Mobile-specific header search (index only) */
  .site-header .hdr-right{ display:flex; align-items:center; gap:8px; margin-left:auto }
  .mob-search{ display:none; align-items:center; gap:6px; margin-left:auto }
  .mob-search input{ height:32px; padding:0 12px; border-radius:999px; border:1px solid rgba(255,255,255,.35); background:rgba(255,255,255,.15); color:#fff; width:clamp(120px, 45vw, 260px) }
  .mob-search input::placeholder{ color:rgba(255,255,255,.8) }
  .mob-search button{ height:32px; padding:0 10px; border:0; border-radius:999px; background:linear-gradient(135deg,#024787,#0475d2); color:#fff; font-weight:800; cursor:pointer }
  @media (max-width: 980px){
    /* Hide subbar on mobile; show compact search next to menu */
    .site-header .subbar{ display:none }
    .site-header .container{ gap:8px }
    .site-header .hdr-right{ flex:1 1 auto; justify-content:flex-end; gap:8px }
    .mob-search{ display:flex; flex:1 1 auto }
    .mob-search input{ width:100%; min-width:0 }
  }
  @media (max-width: 420px){
    .mob-search button{ display:none }
  }
  /* live-search suggestions dropdown */
  .live-suggestions{ position:absolute; z-index:1200; background:#fff; color:#0f172a; border-radius:8px; box-shadow:0 8px 30px rgba(2,71,135,.18); overflow:hidden; min-width:220px; max-width:420px; }
  .live-suggestions .item{ display:flex; gap:8px; align-items:center; padding:8px 10px; cursor:pointer; border-bottom:1px solid #eef2ff }
  .live-suggestions .item:last-child{ border-bottom:0 }
  .live-suggestions .item img{ width:48px;height:36px;object-fit:contain;border-radius:6px; background:#fff }
  .live-suggestions .meta{ font-size:13px; line-height:1.1 }
  .live-suggestions .name{ font-weight:700; color:#06233b }
  .live-suggestions .spec{ color:#374151; font-size:12px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis }
  .live-suggestions .nores{ padding:10px;color:#64748b }
  /* remove underlines for suggestion links and product cards in search results */
  .live-suggestions .item, .live-suggestions .item:link, .live-suggestions .item:visited { text-decoration: none; color: inherit; }
  .grid.products .card a, .grid.products .card a:link, .grid.products .card a:visited { text-decoration: none; color: inherit; }
  .grid.products .card .p h3 a, .grid.products .card .p h3 a:link, .grid.products .card .p h3 a:visited { text-decoration: none; color: inherit; }
</style>
<header class="site-header">
  <div class="container flex between center">
    <a href="<?= BASE_URL ?>/index.php" class="brand">
      <img src="<?= BASE_URL ?>/assets/images/logo.png" alt="Logo" class="logo" />
      <span style="text-shadow:0 1px 1px rgba(0,0,0,.35)">Fpb Network and Power Solutions Services </span>
    </a>
    <div class="hdr-right">
      <?php $onIndex = basename($_SERVER['SCRIPT_NAME']) === 'index.php'; if ($onIndex): ?>
        <form class="mob-search" action="<?= BASE_URL ?>/index.php" method="get" role="search" aria-label="Search products">
          <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Search products..." />
          <button type="submit">Search</button>
        </form>
      <?php endif; ?>
      <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false">☰</button>
      <!-- Chat icon moved to header: sits beside the menu toggle -->
      <button class="chat-icon" title="Chat" aria-label="Open chat" onclick="try{OrderAssistant.open()}catch(e){}" style="padding:6px;border-radius:8px;border:0;background:transparent;display:inline-flex;align-items:center;justify-content:center">
        <img src="<?= BASE_URL ?>/assets/images/chatbort.png" alt="Chat" style="width:34px;height:34px;object-fit:contain;display:block" />
      </button>
    </div>
    <nav class="nav">
      <a href="<?= BASE_URL ?>/index.php">Home</a>
      <?php if (is_logged_in()): ?>
        <a href="<?= BASE_URL ?>/service.php">Service</a>
      <?php endif; ?>
  <?php
    $cartItems = [];
    $cartQty = 0;
    if (is_logged_in()) {
      $cartItems = get_cart();
      // Badge should show number of DISTINCT products, not total quantity
      $cartQty = count($cartItems);
    }
  ?>
  <?php if (is_logged_in()): ?>
    <div class="cart-nav" style="position:relative;display:inline-block">
      <a href="<?= BASE_URL ?>/cart.php" class="cart-link" style="position:relative;display:inline-flex;align-items:center;gap:6px">Cart <span class="cart-badge" style="display:<?= $cartQty>0?'inline-flex':'none' ?>;background:#ff6b00;color:#fff;font-size:11px;font-weight:700;min-width:20px;height:20px;align-items:center;justify-content:center;border-radius:999px;padding:0 6px;line-height:1;box-shadow:0 2px 6px rgba(0,0,0,.2)"><?= (int)$cartQty; ?></span></a>
      <div class="cart-preview" style="display:none;position:absolute;right:0;top:100%;margin-top:10px;width:320px;background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;box-shadow:0 8px 24px -4px rgba(15,23,42,.15);padding:14px;font-size:13px;color:#475569">
        <?php if ($cartQty===0): ?>
          <div style="text-align:center">
            <div style="width:58px;height:58px;margin:0 auto 8px;background:linear-gradient(135deg,#f0f9ff,#e0f2fe);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:26px;color:#0ea5e9">🛒</div>
            <div style="font-weight:600;color:#0f172a">Your cart is empty</div>
            <div style="margin-top:6px"><a href="<?= BASE_URL ?>/index.php#home-products" style="color:#0369a1;text-decoration:none;font-weight:600">Browse products →</a></div>
          </div>
        <?php else: ?>
          <div style="font-weight:800;color:#0f172a;margin:2px 2px 8px">Recently Added Products</div>
          <div>
            <?php $list = array_slice($cartItems, 0, 5); foreach ($list as $ci): ?>
              <div style="display:grid;grid-template-columns:46px 1fr auto;gap:10px;align-items:center;padding:6px 4px;border-radius:8px">
                <img src="<?= h(product_image_url($ci['image'])); ?>" alt="" style="width:46px;height:46px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;border-radius:8px" />
                <div style="overflow:hidden;white-space:nowrap;text-overflow:ellipsis;color:#0f172a;font-weight:600;"><?= h($ci['name']); ?></div>
                <div style="text-align:right;color:#0f172a;font-weight:700;"><?= CURRENCY . number_format($ci['price'], 2); ?></div>
              </div>
            <?php endforeach; ?>
            <?php $more = max(0, count($cartItems) - count($list)); if ($more > 0): ?>
              <div style="font-size:12px;color:#334155;margin:4px 2px">+ <?= (int)$more; ?> more in cart</div>
            <?php endif; ?>
          </div>
          <div style="margin-top:10px;text-align:right"><a href="<?= BASE_URL ?>/cart.php" class="btn" style="text-decoration:none">View My Shopping Cart</a></div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
      <?php if (is_logged_in()): ?>
        <?php $u = current_user(); $avatar = user_avatar_url((int)($u['id'] ?? 0)); $uname = trim($u['username'] ?? ''); ?>
        <div class="user-menu">
          <button class="user-toggle" aria-haspopup="true" aria-expanded="false" style="display:inline-flex;align-items:center;gap:8px">
            <?php if ($avatar): ?>
              <img src="<?= h($avatar); ?>" alt="<?= h($uname ?: 'Profile'); ?>" style="width:28px;height:28px;border-radius:999px;object-fit:cover;border:1px solid #e5e7eb"> ▾
            <?php else: ?>
              <?= h($uname !== '' ? $uname : 'Account'); ?> ▾
            <?php endif; ?>
          </button>
          <div class="menu" role="menu">
            <a href="<?= BASE_URL ?>/account.php" role="menuitem">My Account</a>
            <a href="<?= BASE_URL ?>/orders.php" role="menuitem">My Orders</a>
          </div>
        </div>
        <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
          <a href="<?= BASE_URL ?>/admin/index.php">Admin</a>
        <?php endif; ?>
  <a href="<?= BASE_URL ?>/logout.php" onclick="return confirm('Are you sure you want to sign out and exit this website? If you continue, you will be logged out and redirected. Click OK to proceed, or Cancel to stay.');">Logout</a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>/signin.php">Sign in</a>
        <a href="<?= BASE_URL ?>/register.php">Register</a>
      <?php endif; ?>
      
    </nav>
  </div>
  <div class="subbar">
    <div class="container subgrid">
      <?php $onIndex = basename($_SERVER['SCRIPT_NAME']) === 'index.php'; if ($onIndex): ?>
        <div class="subitem left">
          <div class="promo-banner" role="note" aria-label="Promo">
            <span class="promo-badge"><span class="bolt">⚡</span> PROMO</span>
            <span class="promo-text">4 items → <strong>5% OFF</strong> • 5+ items → <strong>FREE Shipping</strong></span>
          </div>
        </div>
  <div class="subitem mid">Operational: Monday-Sunday</div>
        <form class="subitem right searchbar" action="<?= BASE_URL ?>/index.php" method="get" role="search">
          <input type="search" name="q" value="<?= h($_GET['q'] ?? '') ?>" placeholder="Search products..." aria-label="Search products by name or specs" />
          <button type="submit">Search</button>
        </form>
      <?php else: ?>
        <div class="subitem left">Cash on Delivery available</div>
        <div class="subitem mid">
          <div class="promo-banner" role="note" aria-label="Promo">
            <span class="promo-badge"><span class="bolt">⚡</span> PROMO</span>
            <span class="promo-text">4 items → <strong>5% OFF</strong> • 5+ items → <strong>FREE Shipping</strong></span>
          </div>
        </div>
  <div class="subitem right">Operational: Monday-Sunday</div>
      <?php endif; ?>
    </div>
  </div>
</header>
<script>
(function(){
  var nav=document.querySelector('.cart-nav'); if(!nav) return; var preview=nav.querySelector('.cart-preview'); if(!preview) return;
  function show(){ preview.style.display='block'; }
  function hide(){ preview.style.display='none'; }
  nav.addEventListener('mouseenter',show); nav.addEventListener('mouseleave',hide);
})();
// Improve Account menu hover: add a tiny intent delay to prevent flicker
(function(){
  var um = document.querySelector('.nav .user-menu'); if(!um) return;
  var menu = um.querySelector('.menu'); var btn = um.querySelector('.user-toggle'); if(!menu||!btn) return;
  var hideT, showT;
  function show(){ clearTimeout(hideT); showT = setTimeout(function(){ menu.style.display='block'; btn.setAttribute('aria-expanded','true'); }, 80); }
  function hide(){ clearTimeout(showT); hideT = setTimeout(function(){ menu.style.display='none'; btn.setAttribute('aria-expanded','false'); }, 120); }
  um.addEventListener('mouseenter', show);
  um.addEventListener('mouseleave', hide);
  // Also keep open on focus within (keyboard navigation)
  um.addEventListener('focusin', show);
  um.addEventListener('focusout', function(e){ if(!um.contains(e.relatedTarget)) hide(); });
})();
// Mobile menu toggle with click; also collapse when clicking a link
(function(){
  var btn=document.querySelector('.nav-toggle'); var nav=document.querySelector('.site-header .nav'); if(!btn||!nav) return;
  function toggle(){ var open=nav.classList.toggle('open'); btn.setAttribute('aria-expanded', open?'true':'false'); }
  btn.addEventListener('click', toggle);
  nav.addEventListener('click', function(e){ if(e.target.tagName==='A'){ nav.classList.remove('open'); btn.setAttribute('aria-expanded','false'); } });
})();
// Enable tap to open account menu on touch devices
(function(){
  var btn=document.querySelector('.nav .user-toggle'); var menu=document.querySelector('.nav .user-menu .menu'); if(!btn||!menu) return;
  btn.addEventListener('click', function(e){ e.preventDefault(); var shown=menu.style.display==='block'; menu.style.display = shown?'none':'block'; this.setAttribute('aria-expanded', shown?'false':'true'); });
  document.addEventListener('click', function(e){ if(!menu.contains(e.target)&&e.target!==btn){ menu.style.display='none'; btn.setAttribute('aria-expanded','false'); } });
})();
// Fixed header on small devices with hide-on-scroll behavior
(function(){
  var hdr=document.querySelector('.site-header'); if(!hdr) return;
  function applyPadding(){ var h=hdr.offsetHeight||58; document.body.style.setProperty('--hdrH', h+'px'); document.body.classList.add('has-fixed-header'); }
  var lastY=window.scrollY||0, ticking=false;
  function onScroll(){ if(window.matchMedia('(max-width: 980px)').matches){
      var y=window.scrollY||0, dir=y>lastY?'down':'up'; lastY=y;
      if(!ticking){ window.requestAnimationFrame(function(){ hdr.classList.toggle('hide', dir==='down' && y>10); document.body.classList.toggle('header-hidden', hdr.classList.contains('hide')); ticking=false; }); ticking=true; }
    } else { hdr.classList.remove('hide'); document.body.classList.remove('header-hidden'); document.body.classList.remove('has-fixed-header'); document.body.style.removeProperty('--hdrH'); }
  }
  applyPadding();
  window.addEventListener('resize', applyPadding, {passive:true});
  window.addEventListener('scroll', onScroll, {passive:true});
  window.addEventListener('load', applyPadding);
  // Observe header size changes (e.g., when subbar hides on small screens)
  if('ResizeObserver' in window){ try{ var ro=new ResizeObserver(applyPadding); ro.observe(hdr); }catch(e){} }
  // Recompute after mobile menu toggle changes height
  var toggle=document.querySelector('.nav-toggle'); if(toggle){ toggle.addEventListener('click', function(){ setTimeout(applyPadding, 50); }); }
})();
</script>
<script>
(function(){
  // Minimal live search implementation: debounce + fetch suggestions
  var debounce = function(fn, wait){ var t; return function(){ var args=arguments, ctx=this; clearTimeout(t); t=setTimeout(function(){ fn.apply(ctx,args); }, wait); }; };
  function createBox(){ var el=document.createElement('div'); el.className='live-suggestions'; el.style.display='none'; document.body.appendChild(el); return el; }
  var box = createBox();
  function posBox(inp){ var r = inp.getBoundingClientRect(); box.style.minWidth = Math.max(200, r.width) + 'px'; box.style.left = (window.scrollX + r.left) + 'px'; box.style.top = (window.scrollY + r.bottom + 6) + 'px'; }
  function render(items, forInput){ box.innerHTML = ''; if(!items || items.length===0){ box.innerHTML = '<div class="nores">No matching products</div>'; box.style.display='block'; posBox(forInput); return; }
    items.slice(0,10).forEach(function(it){ var a=document.createElement('a'); a.className='item'; a.href = '<?= BASE_URL ?>/product.php?id=' + encodeURIComponent(it.id);
      a.innerHTML = '<img src="'+ (it.images && it.images[0] ? it.images[0] : '<?= BASE_URL ?>/assets/images/products/uploads/placeholder.png') +'" alt="">'
        + '<div class="meta"><div class="name">'+ (it.name || '') +'</div><div class="spec">'+ (it.specification ? it.specification : '') +'</div></div>';
      box.appendChild(a);
    });
    box.style.display='block'; posBox(forInput);
  }
  function hideBox(){ box.style.display='none'; }
  // find both search inputs (desktop and mobile). They only exist on index.php.
  var inputs = Array.from(document.querySelectorAll('.subbar .searchbar input[type="search"], .mob-search input[type="search"]'));
  if(!inputs.length) return;
  var activeIdx = -1; // for keyboard nav
  var currentItems = [];
  function attach(inp){
    inp.setAttribute('autocomplete','off');
    inp.addEventListener('input', debounce(function(){ var q = this.value.trim(); if(q===''){ hideBox(); return; }
      var url = '<?= BASE_URL ?>/index.php?json=products&q=' + encodeURIComponent(q);
      fetch(url, {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){ if(!j || !Array.isArray(j.products)){ currentItems = []; render([], inp); return; } currentItems = j.products; render(currentItems, inp); }).catch(function(){ render([], inp); });
    }, 220));
    inp.addEventListener('focus', function(){ if(box.innerHTML.trim() !== ''){ posBox(this); box.style.display='block'; } });
    inp.addEventListener('keydown', function(e){ if(box.style.display==='none') return; if(e.key==='ArrowDown' || e.key==='ArrowUp' || e.key==='Enter' || e.key==='Escape'){ e.preventDefault(); var links = box.querySelectorAll('.item'); if(e.key==='ArrowDown'){ activeIdx = Math.min(activeIdx+1, links.length-1); } else if(e.key==='ArrowUp'){ activeIdx = Math.max(activeIdx-1, 0); } else if(e.key==='Enter'){ if(links[activeIdx]) window.location = links[activeIdx].href; return; } else if(e.key==='Escape'){ hideBox(); return; }
        links.forEach(function(l,i){ l.style.background = (i===activeIdx)?'#f1f5f9':''; });
    } });
    // click outside to hide
    document.addEventListener('click', function(ev){ if(ev.target===inp || box.contains(ev.target)) return; hideBox(); });
    // ensure box repositions on resize/scroll
    window.addEventListener('resize', function(){ if(box.style.display!=='none') posBox(inp); });
    window.addEventListener('scroll', function(){ if(box.style.display!=='none') posBox(inp); });
  }
  inputs.forEach(attach);
})();
</script>
