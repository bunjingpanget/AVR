<?php
require_once __DIR__ . '/includes/functions.php';
$page_title = 'Home';
// Fetch products including specification; implement simple pagination (10 per page)
// If q parameter present, perform a simple search on name and specification
$q = trim($_GET['q'] ?? '');
$per_page = 10;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build WHERE clause and params
$where = 'WHERE is_active=1';
$params = [];
if ($q !== '') {
  $where .= ' AND (name LIKE :q1 OR specification LIKE :q2)';
  $params[':q1'] = '%'.$q.'%';
  $params[':q2'] = '%'.$q.'%';
}

// total count for pagination
$countStmt = db()->prepare("SELECT COUNT(*) FROM products $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $per_page));

// fetch page (support simple rating-based sort)
$sort = $_GET['sort'] ?? '';
if ($sort === 'rating_desc') {
  // fetch all matching rows, attach ratings, then sort in PHP and slice for pagination
  $sql = "SELECT id, sku, name, price, short_description, specification, image, stock, type, created_at FROM products $where";
  $stmt = db()->prepare($sql);
  foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
  $stmt->execute();
  $all = $stmt->fetchAll();
  foreach ($all as $i=>&$ap) {
    $rs = product_rating_stats((int)$ap['id']);
    $ap['avg_rating'] = (float)($rs['avg'] ?? 0);
    $ap['rating_count'] = (int)($rs['count'] ?? 0);
    $ap['sold'] = product_sold_count((int)$ap['id']);
  }
  unset($ap);
  usort($all, function($a, $b){ return $b['avg_rating'] <=> $a['avg_rating']; });
  $total = count($all);
  $pages = max(1, (int)ceil($total / $per_page));
  $offset = ($page - 1) * $per_page;
  $products = array_slice($all, $offset, $per_page);
} else {
  $sql = "SELECT id, sku, name, price, short_description, specification, image, stock, type FROM products $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
  $stmt = db()->prepare($sql);
  foreach ($params as $k=>$v) $stmt->bindValue($k, $v);
  $stmt->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
  $stmt->execute();
  $products = $stmt->fetchAll();
}
// Lightweight public JSON for live refresh and live-search (first N active products)
if (isset($_GET['json']) && $_GET['json'] === 'products') {
  header('Content-Type: application/json');
  $qj = trim($_GET['q'] ?? '');
  // allow optional q to filter by name or specification
  if ($qj === '') {
    $rows = db()->query('SELECT id, name, price, image, stock, type FROM products WHERE is_active=1 ORDER BY created_at DESC')->fetchAll();
  } else {
    $stmt = db()->prepare('SELECT id, name, price, image, stock, type, specification FROM products WHERE is_active=1 AND (name LIKE :q1 OR specification LIKE :q2) ORDER BY created_at DESC');
    $like = '%' . $qj . '%';
    $stmt->bindValue(':q1', $like);
    $stmt->bindValue(':q2', $like);
    $stmt->execute();
    $rows = $stmt->fetchAll();
  }
  $out = [];
  foreach ($rows as $r) {
    $imgs = get_product_images($r); if(!$imgs){ $imgs=[product_image_url($r['image']??'')]; }
    $rs = product_rating_stats((int)$r['id']);
    $sold = product_sold_count((int)$r['id']);
    $out[] = [
      'id'=>(int)$r['id'], 'name'=>$r['name'], 'price'=>(float)$r['price'], 'stock'=>(int)$r['stock'], 'type'=>$r['type'], 'images'=>$imgs,
      'specification'=> (string)($r['specification'] ?? ''),
      'rating_avg' => round((float)($rs['avg'] ?? 0), 1), 'rating_count' => (int)($rs['count'] ?? 0), 'sold' => (int)$sold
    ];
    if (count($out) >= 20) break; // cap for live search
  }
  echo json_encode(['products'=>$out]);
  exit;
}
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container">
    <h1>Automatic Voltage Regulators, UPS and TVSS</h1>
    <p>Quality power products. Cash on Delivery (COD) available.</p>
  </div>
</section>

<section class="section">
  <div class="container wide">
    <div class="ads" id="ads">
      <div class="slides">
        <img class="adimg active" id="adimgA" src="<?= BASE_URL ?>/assets/images/bg/1.jpg" alt="Ad slide 1">
        <img class="adimg" id="adimgB" src="<?= BASE_URL ?>/assets/images/bg/2.jpg" alt="Ad slide 2" aria-hidden="true">
      </div>
      <div class="cta">
        <a class="shop-btn" href="#home-products" aria-label="Shop products now">Shop Now</a>
      </div>
      <div class="nav">
        <button type="button" onclick="adsPrev()">‹</button>
        <button type="button" onclick="adsNext()">›</button>
      </div>
    </div>
    <h2><?= $q!=='' ? 'Search results for “'.h($q).'”' : 'Products' ?></h2>
    <form method="get" style="margin:8px 0 12px;text-align:right">
      <input type="hidden" name="q" value="<?= h($q) ?>">
      <label style="margin-right:8px;font-size:14px;color:#475569">Sort:</label>
      <select name="sort" onchange="this.form.submit()" style="padding:6px 8px;border-radius:6px;border:1px solid #e2e8f0;background:#fff">
        <option value="" <?= (isset($_GET['sort']) && $_GET['sort']==='') ? 'selected' : '' ?>>Newest</option>
        <option value="rating_desc" <?= (isset($_GET['sort']) && $_GET['sort']==='rating_desc') ? 'selected' : '' ?>>Rating: High → Low</option>
      </select>
    </form>
    <?php if ($q!=='' && count($products)===0): ?>
      <div style="margin:6px 0 14px;color:#475569;font-weight:600">No results found. Try different keywords.</div>
    <?php endif; ?>
    <?php $gridClass = ($q!=='') ? 'grid products search-grid' : 'grid products home-grid'; ?>
    <div class="<?= $gridClass ?>" id="home-products">
  <?php foreach ($products as $p): ?>
        <article class="card" data-id="<?= (int)$p['id']; ?>">
          <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id']; ?>" aria-label="View <?= h($p['name']); ?>">
            <img src="<?= h(product_image_url($p['image'])); ?>" alt="<?= h($p['name']); ?>" />
          </a>
          <div class="p">
          <h3 style="margin:.25rem 0 0; font-size:16px; text-align:center;"><?= h($p['name']); ?></h3>
            <div class="price"><?= CURRENCY . number_format($p['price'], 2); ?></div>
            <?php
              $spec = trim((string)($p['specification'] ?? $p['short_description'] ?? ''));
              // sold and rating (ensure always present)
              $sold = isset($p['sold']) ? (int)$p['sold'] : product_sold_count((int)$p['id']);
              $rs = isset($p['rating_count']) ? ['count'=>$p['rating_count'],'avg'=>$p['avg_rating']??0] : product_rating_stats((int)$p['id']);
            ?>
            <div class="details" style="white-space:pre-line;">
              <?php if ($spec !== ''): ?>
                <?= nl2br(h($spec)); ?>
              <?php endif; ?>
              <div style="margin-top:8px;text-align:center;color:#334155">
                <?php $prodStock = (int)($p['stock'] ?? 0); ?>
                <div>Stock: <strong><?= $prodStock > 0 ? (int)$prodStock : 'Out of Stock'; ?></strong></div>
              </div>
            </div>
            <!-- compact meta row: always-visible Sold and Rating, centered above action buttons -->
            <div style="margin-top:8px;text-align:center;color:#334155;font-size:14px">
              <span style="margin-right:12px">Sold: <strong class="prod-sold"><?= (int)$sold; ?></strong></span>
              <span style="display:inline-flex;align-items:center;gap:6px">
                <span style="color:#f59e0b">★</span>
                <strong class="prod-rating-avg"><?= number_format(round(($rs['avg']??0)*10)/10,1); ?></strong>
                <span class="prod-rating-count" style="color:#64748b;font-size:12px">(<?= (int)($rs['count']??0); ?>)</span>
              </span>
            </div>
            <?php $inStock = (int)$p['stock'] > 0; ?>
              <div style="margin-top:8px; display:flex; gap:8px; justify-content:center;">
              <?php if (is_logged_in()): ?>
                <button class="btn" <?= $inStock? 'onclick="addToCart(' . (int)$p['id'] . ')"' : 'disabled style="opacity:.6;cursor:not-allowed"' ?>>ADD TO CART</button>
              <?php endif; ?>
              <?php if ($inStock): ?>
                <a class="btn order" href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id']; ?>" style="color:#fff">VIEW</a>
              <?php else: ?>
                <a class="btn order" href="<?= BASE_URL ?>/product.php?id=<?= (int)$p['id']; ?>" style="color:#fff;pointer-events:none;opacity:.6;cursor:not-allowed">VIEW</a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($pages > 1): ?>
      <div style="display:flex;justify-content:center;gap:8px;margin-top:18px;flex-wrap:wrap">
        <?php
          $qs = '';
          if ($q !== '') $qs .= '&q=' . urlencode($q);
          if (isset($_GET['sort']) && $_GET['sort'] !== '') $qs .= '&sort=' . urlencode($_GET['sort']);
          $baseLink = BASE_URL . '/index.php?p=';
        ?>
        <?php if ($page > 1): ?>
          <a class="btn secondary" href="<?= $baseLink . ($page-1) . $qs ?>">&larr; Prev</a>
        <?php endif; ?>
        <?php for ($pi = 1; $pi <= $pages; $pi++): ?>
          <a class="btn<?= $pi === $page ? ' primary' : '' ?>" href="<?= $baseLink . $pi . $qs ?>"><?= $pi ?></a>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <a class="btn secondary" href="<?= $baseLink . ($page+1) . $qs ?>">Next &rarr;</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
  (function(){
    var imgs=[
      '<?= BASE_URL ?>/assets/images/bg/1.jpg',
      '<?= BASE_URL ?>/assets/images/bg/2.jpg',
      '<?= BASE_URL ?>/assets/images/bg/3.jpg'
    ];
    var i=0,
        a=document.getElementById('adimgA'),
        b=document.getElementById('adimgB');

    // Preload for quality and no flicker
    imgs.forEach(function(src){ var im=new Image(); im.src=src; });

    function show(next){
      var incoming = a.classList.contains('active') ? b : a;
      var outgoing = a.classList.contains('active') ? a : b;
      incoming.src = imgs[next];
      // crossfade
      incoming.classList.add('active');
      outgoing.classList.remove('active');
      i = next;
    }
    window.adsNext=function(){ show((i+1)%imgs.length); };
    window.adsPrev=function(){ show((i-1+imgs.length)%imgs.length); };

    // Auto-rotate slideshow with pause on hover
    var ads=document.getElementById('ads');
    var timer=setInterval(window.adsNext, 4000);
    if(ads){
      ads.addEventListener('mouseenter', function(){ clearInterval(timer); });
      ads.addEventListener('mouseleave', function(){ timer=setInterval(window.adsNext, 4000); });
    }
    // Hide any floating Order Assistant button on the homepage
    function hideAssistant(){
      var sels=['#order-assistant','.order-assistant','.orderassistant','[data-order-assistant]'];
      sels.forEach(function(s){ document.querySelectorAll(s).forEach(function(el){ el.style.display='none'; }); });
      Array.from(document.querySelectorAll('a,button,div,span')).forEach(function(el){
        if(/order\s*assistant/i.test(el.textContent||'')) { el.style.display='none'; }
      });
    }
    if(document.readyState!=='loading') hideAssistant(); else document.addEventListener('DOMContentLoaded', hideAssistant);
  })();
  // Live refresh: update first 15 product cards (name, price, stock, image) without reload (disabled on search)
  (function(){ if('<?= $q!=='' ? '1' : '' ?>') return; // skip during search
    var grid = document.getElementById('home-products'); if(!grid) return;
    var endpoint = '<?= BASE_URL ?>/index.php?json=products';
    function fmt(n){ try{ return Number(n).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }catch(e){ return n; } }
    function refresh(){
      fetch(endpoint, {cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
        if(!j || !Array.isArray(j.products)) return;
        var top = j.products.slice(0, 15);
        top.forEach(function(it){
          var card = grid.querySelector('[data-id="'+it.id+'"]'); if(!card) return;
          var title = card.querySelector('h3'); if(title) title.textContent = it.name || '';
          var price = card.querySelector('.price'); if(price) price.textContent = '<?= CURRENCY ?>' + fmt(it.price||0);
          var img = card.querySelector('img'); if(img){ var u = (Array.isArray(it.images) && it.images[0]) ? it.images[0] : img.getAttribute('src'); if(u && img.getAttribute('src')!==u){ img.setAttribute('src', u); img.setAttribute('alt', it.name || ''); } }
          var stockStrong = card.querySelector('.details div span:first-child strong'); if(stockStrong) stockStrong.textContent = (it.stock!=null? it.stock : stockStrong.textContent);
          // update sold and rating if present in the live payload
          var soldEl = card.querySelector('.prod-sold'); if(soldEl) soldEl.textContent = (it.sold!=null? it.sold : soldEl.textContent);
          var ratingAvgEl = card.querySelector('.prod-rating-avg'); if(ratingAvgEl) ratingAvgEl.textContent = (it.rating_avg!=null? Number(it.rating_avg).toFixed(1) : ratingAvgEl.textContent);
          var ratingCountEl = card.querySelector('.prod-rating-count'); if(ratingCountEl) ratingCountEl.textContent = '(' + (it.rating_count!=null? it.rating_count : ratingCountEl.textContent.replace(/[^0-9]/g,'')) + ')';
        });
      }).catch(function(){ /* ignore */ });
    }
    setInterval(refresh, 10000);
    if(document.visibilityState === 'visible'){ setTimeout(refresh, 1500); }
    document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='visible') refresh(); });
  })();
  </script>
