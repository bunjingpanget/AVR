<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();
$msg = null;
// AJAX-friendly CRUD: keeps session active and avoids reload/resubmission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $isAjax = isset($_POST['ajax']);
  try {
    if (isset($_POST['update'])) {
      $stmt = db()->prepare('UPDATE products SET name=:n, price=:p, stock=:s, type=:t, specification=:spec, is_active=:a WHERE id=:id');
      $stmt->execute([
        ':n'=>$_POST['name'], ':p'=>$_POST['price'], ':s'=>$_POST['stock'], ':t'=>$_POST['type'], ':spec'=>($_POST['specification'] ?? ''), ':a'=>isset($_POST['is_active'])?1:0, ':id'=>$_POST['id']
      ]);
      $msg = 'Updated.';
    } elseif (isset($_POST['delete'])) {
      $id = (int)$_POST['id'];
      db()->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
      $msg = 'Product deleted.';
    } elseif (isset($_POST['create'])) {
      $spec = trim($_POST['specification'] ?? '');
      $pdo = db();
      // Insert product first (image empty) so we have ID to store product-specific images
      $ins = $pdo->prepare('INSERT INTO products (name, price, stock, type, is_active, image, specification) VALUES (:n,:p,:s,:t,1,:img,:spec)');
      $ins->execute([':n'=>$_POST['name'], ':p'=>$_POST['price'], ':s'=>$_POST['stock'], ':t'=>$_POST['type'], ':img'=>'', ':spec'=>$spec]);
      $pid = (int)$pdo->lastInsertId();
      $imgRelPaths = [];
      $base = dirname(__DIR__);
      $dirRelBase = 'assets/images/products';
      $prodDirRel = $dirRelBase . '/product_' . $pid;
      $prodDirAbs = $base . '/' . $prodDirRel;
      @mkdir($prodDirAbs, 0777, true);
      // Handle upload[] multiple files
      if (!empty($_FILES['upload']) && is_array($_FILES['upload']['name'])) {
        $count = count($_FILES['upload']['name']);
        for ($i = 0; $i < $count; $i++) {
          if (!isset($_FILES['upload']['tmp_name'][$i])) continue;
          if ($_FILES['upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
          if (!is_uploaded_file($_FILES['upload']['tmp_name'][$i])) continue;
          $ext = pathinfo($_FILES['upload']['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = ($i+1) . '.' . $ext;
          $toAbs = $prodDirAbs . '/' . $fname;
          if (@move_uploaded_file($_FILES['upload']['tmp_name'][$i], $toAbs)) {
            $imgRelPaths[] = $prodDirRel . '/' . $fname;
          }
        }
      } else {
        // fallback for older single-field uploads
        if (!empty($_FILES['upload']['tmp_name']) && is_uploaded_file($_FILES['upload']['tmp_name'])) {
          $ext = pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = '1.' . $ext;
          $toAbs = $prodDirAbs . '/' . $fname;
          if (@move_uploaded_file($_FILES['upload']['tmp_name'], $toAbs)) { $imgRelPaths[] = $prodDirRel . '/' . $fname; }
        }
      }
      // Update main image to first uploaded file
      if (!empty($imgRelPaths)) {
        $main = $imgRelPaths[0];
        $upd = $pdo->prepare('UPDATE products SET image = :img WHERE id = :id');
        $upd->execute([':img'=>$main, ':id'=>$pid]);
      }
      $msg = 'Created.';
    } elseif (isset($_POST['upload_images'])) {
      // Upload additional images for an existing product
      $pid = (int)($_POST['id'] ?? 0);
      if ($pid <= 0) throw new Exception('Invalid product id');
      $base = dirname(__DIR__);
      $dirRelBase = 'assets/images/products';
      $prodDirRel = $dirRelBase . '/product_' . $pid;
      $prodDirAbs = $base . '/' . $prodDirRel;
      @mkdir($prodDirAbs, 0777, true);
      $added = 0;
      $newFiles = [];
      if (!empty($_FILES['upload']) && is_array($_FILES['upload']['name'])) {
        $count = count($_FILES['upload']['name']);
        for ($i = 0; $i < $count; $i++) {
          if (!isset($_FILES['upload']['tmp_name'][$i])) continue;
          if ($_FILES['upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
          if (!is_uploaded_file($_FILES['upload']['tmp_name'][$i])) continue;
          $ext = pathinfo($_FILES['upload']['name'][$i], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $toAbs = $prodDirAbs . '/' . $fname;
          if (@move_uploaded_file($_FILES['upload']['tmp_name'][$i], $toAbs)) {
            $added++;
            $newFiles[] = $prodDirRel . '/' . $fname;
          }
        }
      } else {
        if (!empty($_FILES['upload']['tmp_name']) && is_uploaded_file($_FILES['upload']['tmp_name'])) {
          $ext = pathinfo($_FILES['upload']['name'], PATHINFO_EXTENSION) ?: 'jpg';
          $fname = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
          $toAbs = $prodDirAbs . '/' . $fname;
          if (@move_uploaded_file($_FILES['upload']['tmp_name'], $toAbs)) { $added++; }
        }
      }
      // If product has no main image, set to first newly uploaded file (if any), otherwise scan folder
      $p = db()->prepare('SELECT image FROM products WHERE id = :id'); $p->execute([':id'=>$pid]); $cur = $p->fetchColumn();
      if (empty($cur)) {
        if (!empty($newFiles)) {
          $first = $newFiles[0]; db()->prepare('UPDATE products SET image = :img WHERE id = :id')->execute([':img'=>$first, ':id'=>$pid]);
        } else {
          $files = @scandir($prodDirAbs) ?: [];
          natcasesort($files);
          foreach ($files as $f) { if ($f==='.'||$f==='..') continue; if (!preg_match('/\.(jpe?g|png|webp)$/i',$f)) continue; $rel = $prodDirRel . '/' . $f; db()->prepare('UPDATE products SET image = :img WHERE id = :id')->execute([':img'=>$rel, ':id'=>$pid]); break; }
        }
      }
      $msg = ($added>0)?("Uploaded $added image(s)."):'No images uploaded.';
    } elseif (isset($_POST['set_main'])) {
      // Set the main image for a product (image is posted as full URL or relative)
      $pid = (int)($_POST['id'] ?? 0);
      $img = trim($_POST['image'] ?? '');
      if ($pid <= 0 || $img === '') throw new Exception('Invalid parameters');
      // Normalize to DB-stored relative path (no leading slash)
      $pub = BASE_URL ?: '';
      if ($pub && str_starts_with($img, $pub)) { $img = substr($img, strlen($pub)); }
      $img = ltrim(str_replace('\\','/',$img), '/');
      db()->prepare('UPDATE products SET image = :img WHERE id = :id')->execute([':img'=>$img, ':id'=>$pid]);
      $msg = 'Main image updated.';
    } elseif (isset($_POST['delete_image'])) {
      // Delete an image file for a product; if it was main, pick another or clear
      $pid = (int)($_POST['id'] ?? 0);
      $img = trim($_POST['image'] ?? '');
      if ($pid <= 0 || $img === '') throw new Exception('Invalid parameters');
      $base = dirname(__DIR__);
      $pub = BASE_URL ?: '';
      if ($pub && str_starts_with($img, $pub)) { $img = substr($img, strlen($pub)); }
      $img = ltrim(str_replace('\\','/',$img), '/');
      $abs = $base . '/' . $img;
      if (file_exists($abs) && is_file($abs)) { @unlink($abs); }
      // If the deleted image was the product main, pick another
      $prodDirRel = dirname($img);
      $prodDirAbs = $base . '/' . $prodDirRel;
      $newMain = '';
      if (is_dir($prodDirAbs)) {
        $files = @scandir($prodDirAbs) ?: [];
        natcasesort($files);
        foreach ($files as $f) { if ($f==='.'||$f==='..') continue; if (!preg_match('/\.(jpe?g|png|webp)$/i',$f)) continue; $newMain = $prodDirRel . '/' . $f; break; }
      }
      db()->prepare('UPDATE products SET image = :img WHERE id = :id')->execute([':img'=>$newMain, ':id'=>$pid]);
      $msg = 'Deleted.';
    }
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>$msg]); exit; }
  } catch (Throwable $e) {
    if ($isAjax) { header('Content-Type: application/json', true, 500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]); exit; }
    $msg = 'Error: ' . $e->getMessage();
  }
}
$q = trim($_GET['q'] ?? '');
if ($q !== '') {
  $stmt = db()->prepare('SELECT * FROM products WHERE name LIKE :q OR type LIKE :q ORDER BY id DESC');
  $stmt->execute([':q'=>"%$q%"]);
  $rows = $stmt->fetchAll();
} else {
  $rows = db()->query('SELECT * FROM products ORDER BY id DESC')->fetchAll();
  // JSON feed (admin only) to refresh grid after edits without reload
  if (isset($_GET['json']) && $_GET['json']==='list') {
    header('Content-Type: application/json');
    $out=[]; foreach($rows as $r){
      $imgs = get_product_images($r); if(!$imgs){ $imgs=[product_image_url($r['image']??'')]; }
      $out[]=[
        'id'=>(int)$r['id'], 'name'=>$r['name'], 'price'=>(float)$r['price'], 'stock'=>(int)$r['stock'], 'type'=>$r['type'], 'is_active'=>(int)$r['is_active'], 'specification'=>$r['specification'] ?? '',
        'images'=>$imgs
      ];
    }
    echo json_encode(['products'=>$out]); exit;
  }
}
admin_layout_start('Products / Stock');
?>
  <style>
    /* Compact, legible admin product styles (scoped to this page) */
    .toolbar { display:flex; align-items:center; gap:8px; margin:8px 0 12px; }
    .toolbar input[type=text] { padding:10px 16px; border-radius:8px; border:1.5px solid rgba(255,255,255,.18); background:#0e2a45; color:#fff; min-width:320px; font-size:20px; }
  .add-form { display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:10px; margin-top:12px; }
  .add-form input, .add-form select, .add-form textarea { padding:10px 12px; border-radius:7px; border:1px solid rgba(255,255,255,.15); background:#0e2a45; color:#fff; font-size:16px; }
  .add-form .btn { font-size:16px; padding:10px 16px; }
    /* Grid cards like your screenshot */
    .product-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:16px; margin-top:14px; }
    .card.product { background:#0e2740; border:1px solid rgba(255,255,255,.15); border-radius:10px; padding:10px; box-shadow: 0 4px 16px rgba(0,0,0,.25); }
  .product .cover { width:100%; height:280px; object-fit:contain; background:#0b2035; border-radius:8px; border:1px solid rgba(255,255,255,.12); }
    .thumbs { display:flex; gap:6px; margin:8px 0; flex-wrap:wrap; }
    .thumbs img { width:34px; height:34px; object-fit:contain; background:#0b2035; border:1px solid rgba(255,255,255,.18); border-radius:6px; padding:2px; cursor:pointer; opacity:.9; }
    .thumbs img.active { outline:2px solid #6ec1ff; opacity:1; }
    .product h4 { margin:6px 0 2px; font-size:14px; font-weight:700; }
    .muted { opacity:.9; font-size:12px; }
    .row { display:flex; gap:8px; align-items:center; justify-content:space-between; }
    .badge { display:inline-block; padding:2px 6px; border-radius:999px; font-size:11px; border:1px solid rgba(255,255,255,.2); }
    .badge.on { background:#103c22; color:#9ff2c0; border-color:#1e6a3a; }
    .badge.off { background:#3a1010; color:#ffb3b3; border-color:#6a1e1e; }
    details.inline > summary { cursor:pointer; color:#9fd1ff; }
    details.inline { display:inline-block; }
    .price { white-space:nowrap; font-weight:700; }
    /* Vertical, large edit form */
    .form.product-edit { display:flex; flex-direction:column; gap:10px; font-size:16px; }
    .form.product-edit label { display:flex; flex-direction:column; gap:6px; font-weight:600; }
    .form.product-edit input[type="text"],
    .form.product-edit input[type="number"],
  .form.product-edit select { width:100%; max-width:420px; padding:10px 12px; font-size:16px; border-radius:8px; border:1px solid rgba(255,255,255,.18); background:#0e2a45; color:#fff; }
  .form.product-edit textarea { width:100%; max-width:520px; min-height:120px; padding:10px 12px; font-size:16px; border-radius:8px; border:1px solid rgba(255,255,255,.18); background:#0e2a45; color:#fff; resize:vertical; }
    .form.product-edit label.check { flex-direction:row; align-items:center; gap:8px; font-weight:600; }
    .form.product-edit .btn { align-self:flex-start; padding:10px 16px; font-size:16px; }
  </style>

  <?php if ($msg): ?><div class="card"><?= h($msg); ?></div><?php endif; ?>

  <!-- Search bar removed as requested -->

  <details class="card">
    <summary style="font-size:18px;"><strong>Add Product</strong></summary>
    <form method="post" class="add-form" style="margin-top:12px" enctype="multipart/form-data">
      <input name="name" placeholder="Name" required>
      <input name="price" type="number" step="0.01" placeholder="Price" required>
      <input name="stock" type="number" placeholder="Stock" required>
      <select name="type"><option>AVR</option><option>UPS</option><option>TVSS</option><option>Other</option></select>
  <input type="file" name="upload[]" accept="image/*" multiple required>
      <textarea name="specification" placeholder="Enter product specifications..." required></textarea>
      <button class="btn" name="create" value="1">Create</button>
    </form>
  </details>

  <div class="product-grid">
  <?php foreach ($rows as $r): ?>
    <?php $imgs = get_product_images($r); if (!$imgs) { $imgs = [product_image_url($r['image'] ?? '')]; } ?>
    <div class="card product" data-id="<?= (int)$r['id']; ?>">
      <img class="cover" src="<?= h($imgs[0]) ?>" alt="product">
      <div class="thumbs">
        <?php $i=0; foreach ($imgs as $u): if (!$u) continue; $i++; if ($i>8) break; ?>
          <div style="position:relative; display:inline-block">
            <img src="<?= h($u) ?>" alt="thumb" <?= $i===1?'class="active"':''; ?> data-target="cover">
          </div>
        <?php endforeach; ?>
      </div>
      <h4><?= h($r['name']); ?></h4>
      <div class="row">
        <div class="price"><?= CURRENCY . number_format($r['price'],2); ?></div>
        <div class="muted">Stock: <?= (int)$r['stock']; ?></div>
      </div>
      <div class="row" style="margin:6px 0 8px;">
        <span class="muted">Type: <?= h($r['type']); ?></span>
        <span class="badge <?= $r['is_active']? 'on':'off'; ?>"><?= $r['is_active']? 'Active':'Inactive'; ?></span>
      </div>
      <details class="inline"><summary>Edit</summary>
        <form method="post" class="form product-edit" enctype="multipart/form-data">
          <input type="hidden" name="id" value="<?= (int)$r['id']; ?>">
          <label>Name<input name="name" value="<?= h($r['name']); ?>"></label>
          <label>Price<input name="price" type="number" step="0.01" value="<?= h($r['price']); ?>"></label>
          <label>Stock<input name="stock" type="number" value="<?= (int)$r['stock']; ?>"></label>
          <label>Type<select name="type"><option <?= $r['type']==='AVR'?'selected':''; ?>>AVR</option><option <?= $r['type']==='UPS'?'selected':''; ?>>UPS</option><option <?= $r['type']==='TVSS'?'selected':''; ?>>TVSS</option><option <?= $r['type']==='Other'?'selected':''; ?>>Other</option></select></label>
          <label>Specifications<textarea name="specification" placeholder="Enter product specifications..."><?= h($r['specification'] ?? '') ?></textarea></label>
          <label class="check"><input type="checkbox" name="is_active" <?= $r['is_active']? 'checked':''; ?>> Active</label>
          <div style="display:flex; gap:8px;">
            <button class="btn" name="update" value="1">Save</button>
            <button class="btn" name="delete" value="1" style="background:#c00; color:#fff;" onclick="return confirm('Delete this product?')">Delete</button>
          </div>
          <div style="margin-top:8px">
            <div style="font-weight:700; margin-bottom:6px">Current images</div>
            <div class="thumbs edit-thumbs" style="gap:8px">
              <?php foreach (($imgs ?? []) as $eu): if (!$eu) continue; ?>
                <div style="position:relative; display:inline-block; margin-right:6px">
                  <img src="<?= h($eu) ?>" alt="img" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.06)">
                  <div style="position:absolute;left:6px;top:6px;display:flex;gap:6px">
                    <button type="button" data-img="<?= h($eu) ?>" class="thumb-set" title="Set main" style="background:rgba(0,0,0,.6);border:0;color:#fff;border-radius:6px;padding:2px 6px;font-size:12px">✓</button>
                  </div>
                  <button type="button" data-img="<?= h($eu) ?>" class="thumb-del" title="Delete" style="position:absolute;right:6px;top:6px;background:#c33;border:0;color:#fff;border-radius:999px;padding:2px 6px;font-size:12px">✕</button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div style="display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap;">
            <label style="font-weight:600;">Upload images <input type="file" name="upload[]" accept="image/*" multiple style="margin-left:8px"></label>
            <button type="button" class="btn secondary" data-action="upload-images">Upload</button>
          </div>
          <div style="margin-top:6px; font-size:13px; color:var(--mut);">Tip: click a thumbnail to preview. Use the thumbnail actions (⋯) to set main or delete an image.</div>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
  </div>
  <script>
    // Highlight and scroll to a product if ?highlight=ID is present
    (function(){
      var params=new URLSearchParams(location.search); var hid=params.get('highlight'); if(!hid) return; hid = String(parseInt(hid,10));
      function mark(){
        var el=document.querySelector('.card.product[data-id="'+hid+'"]');
        if(!el) return;
        el.scrollIntoView({behavior:'smooth', block:'center'});
        el.style.transition='background-color .3s ease, box-shadow .3s ease';
        el.style.boxShadow='0 0 0 2px #6ec1ff, 0 8px 22px rgba(0,0,0,.35)';
        el.style.background='#0f355a';
        setTimeout(function(){ el.style.boxShadow=''; el.style.background=''; }, 1800);
      }
      // Delay slightly to ensure layout is ready (and after any grid refresh)
      if(document.readyState==='complete') setTimeout(mark, 120);
      else window.addEventListener('load', function(){ setTimeout(mark, 120); });
    })();

    // Intercept Add/Edit/Delete submits → AJAX (keeps session, no reload/resubmit)
    document.addEventListener('submit', function(e){
      var f=e.target; if(!(f instanceof HTMLFormElement)) return; var isEdit=f.classList.contains('product-edit'); var isCreate=f.classList.contains('add-form'); if(!isEdit && !isCreate) return; e.preventDefault();
      var fd=new FormData(f); fd.append('ajax','1'); if(e.submitter && e.submitter.name){ if(e.submitter.name==='delete'){ if(!e.submitter.getAttribute('onclick')){ if(!confirm('Delete this product?')) return; } } fd.append(e.submitter.name, e.submitter.value||'1'); }
      fetch(location.href,{method:'POST', body:fd})
        .then(function(r){ return r.json(); })
        .then(function(j){
          toast(j.msg||'Saved');
          // Close the editor ONLY after a successful save
          if(isEdit){ try{ var det=f.closest('details'); if(det) det.open=false; }catch(_){ }
          }
          // If this was the Add form, reset its inputs and close the details panel
          if(isCreate){ try{ f.reset(); var detc = f.closest('details'); if(detc) detc.open=false; var first = f.querySelector('input,select,textarea'); if(first) first.focus(); }catch(_){ } }
          // Force refresh after save so the grid reflects latest data
          refreshGrid(true);
        })
        .catch(function(){ alert('Request failed'); });
    });
    function toast(msg){ try{ var t=document.createElement('div'); t.textContent=msg; t.style.cssText='position:fixed;right:12px;bottom:12px;background:#0ea5e9;color:#fff;padding:10px 12px;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.25);z-index:9999'; document.body.appendChild(t); setTimeout(function(){t.remove();},1500);}catch(e){} }
    function escapeHtml(s){ if(s==null) return ''; return String(s).replace(/[&<>"']/g,function(c){return ({"&":"&amp;","<":"&lt;",
      ">":"&gt;","\"":"&quot;","'":"&#39;"})[c];}); }
    function render(items){
      var grid=document.querySelector('.product-grid'); if(!grid) return;
      grid.innerHTML = items.map(function(r){
        var img=(Array.isArray(r.images)&&r.images[0])?r.images[0]:'';
        var thumbs=(r.images||[]).slice(0,8).map(function(u,i){
          return '<div style="position:relative;display:inline-block">'+
            '<img src="'+u+'" alt="thumb" '+(i===0?'class="active"':'')+' data-target="cover">'+
          '</div>';
        }).join('');
        return '<div class="card product" data-id="'+r.id+'">'+
          '<img class="cover" src="'+img+'" alt="product">'+
          '<div class="thumbs">'+thumbs+'</div>'+
          '<h4>'+escapeHtml(r.name)+'</h4>'+
          '<div class="row"><div class="price"><?= CURRENCY ?>'+Number(r.price).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})+'</div><div class="muted">Stock: '+r.stock+'</div></div>'+
          '<div class="row" style="margin:6px 0 8px;"><span class="muted">Type: '+escapeHtml(r.type||'')+'</span><span class="badge '+(r.is_active? 'on':'off')+'">'+(r.is_active? 'Active':'Inactive')+'</span></div>'+
          '<details class="inline"><summary>Edit</summary>\
            <form method="post" class="form product-edit" enctype="multipart/form-data">\
              <input type="hidden" name="id" value="'+r.id+'">\
              <label>Name<input name="name" value="'+escapeHtml(r.name)+'"></label>\
              <label>Price<input name="price" type="number" step="0.01" value="'+r.price+'"></label>\
              <label>Stock<input name="stock" type="number" value="'+r.stock+'"></label>\
              <label>Type<select name="type"><option '+((r.type||'')==='AVR'?'selected':'')+'>AVR</option><option '+((r.type||'')==='UPS'?'selected':'')+'>UPS</option><option '+((r.type||'')==='TVSS'?'selected':'')+'>TVSS</option><option '+((r.type||'')==='Other'?'selected':'')+'>Other</option></select></label>\
              <label>Specifications<textarea name="specification" placeholder="Enter product specifications...">'+escapeHtml(r.specification||'')+'</textarea></label>\
              <label class="check"><input type="checkbox" name="is_active" '+(r.is_active?'checked':'')+'> Active</label>\
              <div style="display:flex; gap:8px;">\
                <button class="btn" name="update" value="1">Save</button>\
                <button class="btn" name="delete" value="1" style="background:#c00; color:#fff;">Delete</button>\
              </div>\
              <div style="margin-top:8px">\
                <div style="font-weight:700; margin-bottom:6px">Current images</div>\
                <div class="thumbs edit-thumbs" style="gap:10px">'+( (r.images||[]).map(function(eu){ return '<div style="position:relative; display:inline-block; margin-right:8px">\
                    <img src="'+eu+'" alt="img" style="width:64px;height:64px;object-fit:cover;border-radius:6px;border:1px solid rgba(255,255,255,.06)">\
                    <div style="position:absolute;left:6px;top:6px;display:flex;gap:6px">\
                      <button type="button" data-img="'+eu+'" class="thumb-set" title="Set main" style="background:rgba(0,0,0,.6);border:0;color:#fff;border-radius:6px;padding:2px 6px;font-size:12px">✓</button>\
                    </div>\
                    <button type="button" data-img="'+eu+'" class="thumb-del" title="Delete" style="position:absolute;right:6px;top:6px;background:#c33;border:0;color:#fff;border-radius:999px;padding:2px 6px;font-size:12px">✕</button>\
                  </div>'; }).join('') ) +'</div>\
              </div>\
              <div style="display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap;">\
                <label style="font-weight:600;">Upload images <input type="file" name="upload[]" accept="image/*" multiple style="margin-left:8px"></label>\
                <button type="button" class="btn secondary" data-action="upload-images">Upload</button>\
              </div>\
              <div style="margin-top:6px; font-size:13px; color:var(--mut);">Tip: click a thumbnail to preview. Use the thumbnail actions to set main or delete an image.</div>\
            </form>\
          </details>'+
        '</div>';
      }).join('');
    }
    // Skip auto-refresh while any edit panel is open
    function isEditingOpen(){ return !!document.querySelector('details.inline[open]'); }
    function refreshGrid(force){
      if(!force && isEditingOpen()) return; // don't disrupt an active editor
      fetch('?json=list',{cache:'no-store'})
        .then(function(r){ return r.json(); })
        .then(function(j){ if(j&&Array.isArray(j.products)) render(j.products); })
        .catch(function(){});
    }
    // Swap main image on thumbnail click
    document.addEventListener('click', function(e){
      const t = e.target; if(!(t instanceof HTMLElement)) return;
      if(t.matches('.thumbs img')){
        const card = t.closest('.card.product'); if(!card) return;
        const cover = card.querySelector('img.cover'); if(!cover) return;
        cover.src = t.getAttribute('src');
        card.querySelectorAll('.thumbs img').forEach(i=>i.classList.remove('active'));
        t.classList.add('active');
      }
      // One-click Set main (from Current images area)
      if(t.matches('.thumb-set')){
        var img = t.getAttribute('data-img'); var card = t.closest('.card.product'); if(!card) return; var pid = card.getAttribute('data-id');
        var fd = new FormData(); fd.append('ajax','1'); fd.append('set_main','1'); fd.append('id', pid); fd.append('image', img);
        fetch(location.href, {method:'POST', body:fd}).then(r=>r.json()).then(function(j){ toast(j.msg||'Saved'); refreshGrid(true); }).catch(function(){ alert('Request failed'); });
      }
      // One-click Delete (from Current images area)
      if(t.matches('.thumb-del')){
        if(!confirm('Delete this image?')) return;
        var img = t.getAttribute('data-img'); var card = t.closest('.card.product'); if(!card) return; var pid = card.getAttribute('data-id');
        var fd = new FormData(); fd.append('ajax','1'); fd.append('delete_image','1'); fd.append('id', pid); fd.append('image', img);
        fetch(location.href, {method:'POST', body:fd}).then(r=>r.json()).then(function(j){ toast(j.msg||'Deleted'); refreshGrid(true); }).catch(function(){ alert('Request failed'); });
      }
    });

    // Upload-images button in edit form
    document.addEventListener('click', function(e){
      var btn = e.target; if(!(btn instanceof HTMLElement)) return; if(!btn.matches('button[data-action="upload-images"]')) return;
      var form = btn.closest('form'); if(!form) return; var pid = form.querySelector('input[name="id"]')?.value || form.closest('.card.product')?.getAttribute('data-id');
      var fileInput = form.querySelector('input[type="file"][name="upload[]"]'); if(!fileInput || fileInput.files.length===0){ alert('Select images first'); return; }
      var fd = new FormData(); fd.append('ajax','1'); fd.append('upload_images','1'); fd.append('id', pid);
      for(var i=0;i<fileInput.files.length;i++){ fd.append('upload[]', fileInput.files[i]); }
      fetch(location.href, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(j){ toast(j.msg||'Uploaded'); // reset input
        try{ fileInput.value = ''; }catch(_){ }
        refreshGrid(true);
      }).catch(function(){ alert('Request failed'); });
    });
    // Periodic refresh to keep grid in sync
    setInterval(function(){ refreshGrid(false); }, 12000);
    if(document.visibilityState === 'visible'){ setTimeout(function(){ refreshGrid(false); }, 1500); }
    document.addEventListener('visibilitychange', function(){ if(document.visibilityState==='visible') refreshGrid(false); });
  </script>
<?php admin_layout_end(); ?>
