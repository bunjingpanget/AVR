<?php
require_once __DIR__ . '/../config/db.php';
// Robust session bootstrap: ensure a writable session.save_path to avoid permission issues (e.g., C:\xampp\tmp)
if (session_status() === PHP_SESSION_NONE) {
    $defaultPath = ini_get('session.save_path');
    $writable = $defaultPath && @is_dir($defaultPath) && @is_writable($defaultPath);
    if (!$writable) {
        $sessDir = dirname(__DIR__) . '/storage/sessions';
        if (!is_dir($sessDir)) { @mkdir($sessDir, 0777, true); }
        if (@is_dir($sessDir) && @is_writable($sessDir)) {
            @session_save_path($sessDir);
        }
    }
    // Start session; suppress warnings so users never see them in production
    @session_start();
}

// Basic site settings
const SITE_NAME = 'AVR Shop';
const CURRENCY = '₱';

// Ensure key assets exist by copying from legacy paths once per session
function ensure_assets(): void {
    static $done = false; if ($done) return; $done = true;
    $base = dirname(__DIR__);
    $dstLogo = $base . '/assets/images/logo.png';
    $logoSources = [
        // Prefer a logo shipped alongside the project (AVR root)
        $base . '/logo.png',
        // Fallback to legacy sibling (old structure)
        dirname($base) . '/logo.png',
    ];
    if (!file_exists($dstLogo)) {
        foreach ($logoSources as $srcLogo) {
            if (file_exists($srcLogo)) { @copy($srcLogo, $dstLogo); break; }
        }
    }
    $dstBg = $base . '/assets/images/bg.jpg';
    $bgCandidates = [dirname($base) . '/picture products/bg/bg.jpg', dirname($base) . '/picture products/bg/1.jpg'];
    if (!file_exists($dstBg)) {
        foreach ($bgCandidates as $src) { if (file_exists($src)) { @copy($src, $dstBg); break; } }
    }
}
ensure_assets();

// Ensure optional user profile columns
try { db()->exec("ALTER TABLE users ADD COLUMN dob DATE NULL"); } catch (Throwable $e) {}
try { db()->exec("ALTER TABLE users ADD COLUMN gender ENUM('male','female','other','') NULL DEFAULT ''"); } catch (Throwable $e) {}

// Ensure order_status enum includes 'shipped' (adds seamlessly if already present)
try { db()->exec("ALTER TABLE orders MODIFY order_status ENUM('pending','confirmed','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending'"); } catch(Throwable $e) {}

// Ensure additional order columns for region-aware shipping and busy-day span
try { db()->exec("ALTER TABLE orders ADD COLUMN shipping_region VARCHAR(32) NULL, ADD COLUMN delivery_span_days INT NOT NULL DEFAULT 3"); } catch(Throwable $e) {}

// Ensure warranty_receipts table exists (idempotent guard if db import missed it)
function ensure_warranty_schema(): void {
    static $done = false; if ($done) return; $done = true;
    $sql = "CREATE TABLE IF NOT EXISTS warranty_receipts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        order_item_id INT NOT NULL,
        product_id INT NULL,
        user_id INT NULL,
        serial_no VARCHAR(64) NULL,
        warranty_start_date DATE NOT NULL,
        warranty_end_date DATE NOT NULL,
        status ENUM('active','claimed','completed','expired') NOT NULL DEFAULT 'active',
        claimed_at DATETIME NULL,
        completed_at DATETIME NULL,
        claim_notes TEXT NULL,
        buyer_name VARCHAR(255) NULL,
        buyer_email VARCHAR(255) NULL,
        buyer_phone VARCHAR(50) NULL,
        product_name VARCHAR(255) NULL,
        product_image VARCHAR(255) NULL,
        unit_price DECIMAL(10,2) NULL,
        quantity INT NULL,
        subtotal DECIMAL(10,2) NULL,
        total DECIMAL(10,2) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order_item (order_item_id),
        KEY order_id (order_id), KEY product_id (product_id), KEY user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { db()->exec($sql); } catch (Throwable $e) { /* ignore: exists */ }
}

// Ensure email_verifications table exists for storing hashed OTPs and metadata
function ensure_email_verification_schema(): void {
    static $done = false; if ($done) return; $done = true;
    $sql = "CREATE TABLE IF NOT EXISTS email_verifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        purpose VARCHAR(64) NOT NULL DEFAULT 'registration',
        code_hash VARCHAR(255) NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        sent_count INT NOT NULL DEFAULT 1,
        last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX(email), INDEX(purpose), INDEX(created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    try { db()->exec($sql); } catch (Throwable $e) { /* ignore */ }
}

// Create a 6-digit code, store only its hash and send email. Enforces cooldown and hourly limits.
function create_and_send_verification_code(string $email, string $purpose = 'registration') {
    $pdo = db(); ensure_email_verification_schema();
    $email = strtolower(trim($email)); if ($email === '') return ['ok'=>false,'msg'=>'Invalid email'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    // hourly limit: max 3 sends per hour
    $st = $pdo->prepare('SELECT COUNT(*) FROM email_verifications WHERE email = :e AND purpose = :p AND created_at >= (NOW() - INTERVAL 1 HOUR)');
    $st->execute([':e'=>$email, ':p'=>$purpose]); $recent = (int)$st->fetchColumn();
    if ($recent >= 3) return ['ok'=>false,'msg'=>'Resend limit reached (3 per hour). Please try later.'];
    // cooldown: check last sent time (most recent row)
    $st2 = $pdo->prepare('SELECT last_sent_at FROM email_verifications WHERE email = :e AND purpose = :p ORDER BY id DESC LIMIT 1');
    $st2->execute([':e'=>$email, ':p'=>$purpose]); $last = $st2->fetchColumn();
    if ($last) {
        $lastTs = strtotime($last);
        if (time() - $lastTs < 60) return ['ok'=>false,'msg'=>'Please wait before resending the code (60s cooldown).'];
    }
    // generate code and insert
    try {
        $code = (string)random_int(100000, 999999);
    } catch (Throwable $e) {
        $code = str_pad((string)mt_rand(0,999999), 6, '0', STR_PAD_LEFT);
    }
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + 600); // 10 minutes
    $ins = $pdo->prepare('INSERT INTO email_verifications (email,purpose,code_hash,attempts,sent_count,last_sent_at,expires_at,ip) VALUES (:e,:p,:h,0,1,NOW(),:ex,:ip)');
    $ins->execute([':e'=>$email, ':p'=>$purpose, ':h'=>$hash, ':ex'=>$expires, ':ip'=>$ip]);
    // send email (best-effort)
    // Prefer SMTP/from config if present (config/mail.php returning array) to allow branded mailbox
    $from = null; $subject = 'Your FNPSS verification code';
    $body = "Hello,\n\nYour FNPSS verification code is: $code\n\nEnter this code on the registration page to complete your account. The code expires in 10 minutes. If you did not request this, ignore this message.\n\nThanks,\nFNPSS Team";
    $cfgPath = dirname(__DIR__) . '/config/mail.php';
    if (file_exists($cfgPath)) {
        try { $cfg = include $cfgPath; if (is_array($cfg) && !empty($cfg['from']['address'])) { $from = $cfg['from']['address']; } } catch (Throwable $e) {}
    }
    if (!$from) {
        $host = $_SERVER['SERVER_NAME'] ?? parse_url(BASE_URL, PHP_URL_HOST) ?? 'localhost';
        $from = 'no-reply@' . $host;
    }
    $headers = 'From: ' . $from . "\r\n" . 'Reply-To: ' . $from . "\r\n" . 'X-Mailer: PHP/' . phpversion();
    @mail($email, $subject, $body, $headers);
    return ['ok'=>true,'msg'=>'Verification code sent.','code_plain'=>$code];
}

// Attempt to verify a code. Returns ['ok'=>bool,'msg'=>string]. On success, mark user email as verified if exists.
function verify_code_for_email(string $email, string $code, string $purpose = 'registration') {
    $pdo = db(); ensure_email_verification_schema();
    $email = strtolower(trim($email)); if ($email === '') return ['ok'=>false,'msg'=>'Invalid email'];
    // find most recent unexpired record
    $st = $pdo->prepare('SELECT * FROM email_verifications WHERE email = :e AND purpose = :p AND expires_at >= NOW() ORDER BY id DESC LIMIT 1');
    $st->execute([':e'=>$email, ':p'=>$purpose]); $row = $st->fetch();
    if (!$row) return ['ok'=>false,'msg'=>'No active verification found or code expired.'];
    $id = (int)$row['id'];
    // rate-limit failures per-code: max 5 attempts
    $attempts = (int)$row['attempts'];
    if ($attempts >= 5) return ['ok'=>false,'msg'=>'Too many failed attempts. Request a new code.'];
    if (!password_verify($code, $row['code_hash'])) {
        $u = $pdo->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = :id'); $u->execute([':id'=>$id]);
        return ['ok'=>false,'msg'=>'Incorrect verification code.'];
    }
    // success: mark as used (delete or set expires past)
    $u2 = $pdo->prepare('UPDATE email_verifications SET expires_at = NOW() WHERE id = :id'); $u2->execute([':id'=>$id]);
    // mark user verified if exists
    try {
        $up = $pdo->prepare('UPDATE users SET email_verified_at = NOW() WHERE LOWER(email) = :e LIMIT 1');
        $up->execute([':e'=>$email]);
    } catch (Throwable $e) {}
    return ['ok'=>true,'msg'=>'Email successfully verified.'];
}


// Create warranty/receipt rows for an order if missing (one per order_item)
function ensure_warranty_records_for_order(int $orderId): void {
    ensure_warranty_schema(); $pdo = db();
    try {
        $o = get_order($orderId); if(!$o) return; $userId = (int)($o['user_id'] ?? 0) ?: null;
        $buyerName = (string)($o['customer_name'] ?? '');
        $buyerEmail = (string)($o['customer_email'] ?? '');
        $buyerPhone = (string)($o['contact_number'] ?? '');
        $items = get_order_items($orderId);
        if (!$items) return;
        $start = date('Y-m-d', strtotime($o['created_at'] ?? 'now'));
        $end = date('Y-m-d', strtotime($start . ' +1 year'));
        $ins = $pdo->prepare('INSERT IGNORE INTO warranty_receipts (order_id, order_item_id, product_id, user_id, serial_no, warranty_start_date, warranty_end_date, status, buyer_name, buyer_email, buyer_phone, product_name, product_image, unit_price, quantity, subtotal, total) VALUES (:oid,:iid,:pid,:uid,:sn,:ws,:we,\'active\',:bn,:be,:bp,:pn,:pi,:up,:q,:st,:tt)');
        foreach ($items as $it) {
            $iid = (int)($it['id'] ?? 0); if(!$iid) continue; $pid = (int)($it['product_id'] ?? 0) ?: null;
            $sn = 'AVR-' . date('Ymd') . '-' . $orderId . '-' . $iid; // simple deterministic serial seed
            $ins->execute([
                ':oid'=>$orderId, ':iid'=>$iid, ':pid'=>$pid, ':uid'=>$userId,
                ':sn'=>$sn, ':ws'=>$start, ':we'=>$end,
                ':bn'=>$buyerName, ':be'=>$buyerEmail, ':bp'=>$buyerPhone,
                ':pn'=>(string)($it['product_name'] ?? ''), ':pi'=>(string)($it['product_image'] ?? ''),
                ':up'=>(float)($it['unit_price'] ?? 0), ':q'=>(int)($it['quantity'] ?? 0),
                ':st'=>(float)($it['total_price'] ?? 0), ':tt'=>(float)($o['total_price'] ?? 0)
            ]);
        }
    } catch (Throwable $e) { /* noop */ }
}

// Get warranty rows for an order (optionally filter by product_id)
function get_warranty_receipts(int $orderId, ?int $productId = null): array {
    ensure_warranty_schema();
    $sql = 'SELECT wr.*, i.product_name, i.product_image FROM warranty_receipts wr JOIN order_items i ON i.id = wr.order_item_id WHERE wr.order_id = :oid';
    $params = [':oid'=>$orderId];
    if ($productId && $productId > 0) { $sql .= ' AND wr.product_id = :pid'; $params[':pid'] = $productId; }
    $sql .= ' ORDER BY wr.id ASC';
    $st = db()->prepare($sql); $st->execute($params); $rows = $st->fetchAll();
    // compute derived fields
    $today = strtotime(date('Y-m-d'));
    foreach ($rows as &$r) {
        $end = strtotime((string)$r['warranty_end_date']);
        $status = strtolower((string)$r['status']);
        if ($status !== 'completed' && $status !== 'claimed') {
            if ($today > $end) { $r['status'] = 'expired'; }
            else { $r['status'] = 'active'; }
        }
    }
    return $rows;
}

// Admin uses warranty for a specific order_item (mark claimed)
function admin_claim_warranty(int $orderItemId, string $notes = ''): bool {
    ensure_warranty_schema(); $st = db()->prepare("UPDATE warranty_receipts SET status = 'claimed', claimed_at = CURRENT_TIMESTAMP, claim_notes = :n WHERE order_item_id = :iid AND status = 'active'");
    return $st->execute([':iid'=>$orderItemId, ':n'=>$notes]);
}

// Admin completes a warranty service for an order_item
function admin_complete_warranty(int $orderItemId): bool {
    ensure_warranty_schema(); $st = db()->prepare("UPDATE warranty_receipts SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE order_item_id = :iid");
    return $st->execute([':iid'=>$orderItemId]);
}

// Compute project base URL (e.g., /website/AVR). Works under subfolders and Windows paths.
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base = '';
    if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
        $base = substr($projectRoot, strlen($docRoot));
    }
    $base = '/' . ltrim($base, '/');
    define('BASE_URL', rtrim($base, '/'));
}

// Resolve a user's avatar URL if uploaded (jpg or png), else return null
function user_avatar_url(int $userId): ?string {
    if ($userId <= 0) return null;
    $baseFs = dirname(__DIR__);
    $jpg = $baseFs . '/assets/images/avatars/user_' . $userId . '.jpg';
    $png = $baseFs . '/assets/images/avatars/user_' . $userId . '.png';
    if (file_exists($jpg)) return BASE_URL . '/assets/images/avatars/user_' . $userId . '.jpg';
    if (file_exists($png)) return BASE_URL . '/assets/images/avatars/user_' . $userId . '.png';
    return null;
}

// Image path normalization: map legacy DB paths like
// "picture products/product 1/1.jpg" to self-contained assets under
// {BASE_URL}/assets/images/products/product 1/1.jpg, copying on first access.
function product_image_url($path): string {
    $base = dirname(__DIR__); // project root (filesystem)
    $public_prefix = BASE_URL ?: '';
    if (!$path) return $public_prefix . '/assets/images/logo.png';
    // Normalize slashes and trim known prefixes
    $p = str_replace('\\', '/', trim((string)$path));
    if ($p === '') return $public_prefix . '/assets/images/logo.png';
    // If already absolute under project base, just return
    if (BASE_URL && str_starts_with($p, BASE_URL . '/')) return $p;
    // If already under assets/images, prefix /avr
    if (preg_match('#^(assets/|/assets/)#i', $p)) {
        $p = ltrim($p, '/');
        return $public_prefix . '/' . $p;
    }
    // Strip possible leading legacy folder name
    $sub = preg_replace('#^(?:/?picture products/)+#i', '', $p);
    $sub = ltrim($sub, '/');
    // Reject path traversal
    $sub = str_replace(['..', '\\'], ['', '/'], $sub);
    // 1) If a file already exists at assets/images/{sub}, use it directly (your current assets layout)
    $existingRel = 'assets/images/' . $sub;
    $existingAbs = $base . '/' . $existingRel;
    if (file_exists($existingAbs) && is_file($existingAbs)) {
        return $public_prefix . '/' . $existingRel;
    }
    // 2) Otherwise, destination under assets/images/products/{sub}
    $destRel = 'assets/images/products/' . $sub;
    $destAbs = $base . '/' . $destRel;
    // If not present yet, try to copy from legacy location if it exists
    if (!file_exists($destAbs)) {
        $srcAbs = dirname($base) . '/picture products/' . $sub; // legacy root sibling
        if (file_exists($srcAbs) && is_file($srcAbs)) {
            @mkdir(dirname($destAbs), 0777, true);
            @copy($srcAbs, $destAbs);
        }
    }
    // If still not present, fall back to original relative path under /avr (may work if user copied folder)
    if (!file_exists($destAbs)) {
        $fallback = $public_prefix . '/' . ltrim($p, '/');
        return $fallback;
    }
    return $public_prefix . '/' . $destRel;
}

function is_logged_in(): bool { return !empty($_SESSION['user']); }
function current_user() { return $_SESSION['user'] ?? null; }

function login(string $usernameOrEmail, string $password): bool {
    // Use two distinct named params (MySQL PDO doesn't allow reusing the same name twice)
    $sql = 'SELECT * FROM users WHERE (email = :email OR username = :username) AND status = "active" LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute([':email' => $usernameOrEmail, ':username' => $usernameOrEmail]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        return true;
    }
    return false;
}

function register_user(array $data, &$error = null): bool {
    try {
        $stmt = db()->prepare('INSERT INTO users (email, name, location, phone, username, password, role, status, dob, gender) VALUES (:email,:name,:location,:phone,:username,:password, "customer", "active", :dob, :gender)');
        $stmt->execute([
            ':email' => trim($data['email'] ?? ''),
            ':name' => trim($data['name'] ?? ''),
            ':location' => trim($data['location'] ?? ''),
            ':phone' => trim($data['phone'] ?? ''),
            ':username' => trim($data['username'] ?? ''),
            ':password' => password_hash($data['password'] ?? '', PASSWORD_BCRYPT),
            ':dob' => !empty($data['dob']) ? $data['dob'] : null,
            ':gender' => isset($data['gender']) ? (string)$data['gender'] : ''
        ]);
        return true;
    } catch (PDOException $e) {
        $error = $e->getMessage();
        return false;
    }
}

function logout(): void { $_SESSION = []; session_destroy(); }

// Products
function get_products(): array {
    $stmt = db()->query('SELECT id, sku, name, price, short_description, image, stock, type FROM products WHERE is_active = 1 ORDER BY created_at DESC');
    return $stmt->fetchAll();
}

function get_product(int $id) {
    $stmt = db()->prepare('SELECT * FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// Total quantity sold for a product (from order_items)
function product_sold_count(int $productId): int {
    try {
        $stmt = db()->prepare('SELECT COALESCE(SUM(quantity),0) AS sold FROM order_items WHERE product_id = :id');
        $stmt->execute([':id' => $productId]);
        $row = $stmt->fetch();
        return (int)($row['sold'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

// Return multiple image URLs for a product by scanning the product folder under assets/images
function get_product_images(array $product): array {
    $images = [];
    $first = product_image_url($product['image'] ?? '');
    if ($first) $images[] = $first;
    // Try sibling numbered images in a product-specific folder derived from the first image path
    $base = dirname(__DIR__);
    $pubBase = BASE_URL;
    // Heuristic: if first is like /.../assets/images/product X/1.jpg, scan that folder
        $firstPath = str_replace('\\','/',$first);
    if (preg_match('#(.*/assets/images/[^\n]*/)(?:[0-9]+\.[a-zA-Z]+)$#',$firstPath,$m)) {
        $folderPub = rtrim($m[1],'/');
        $folderAbs = $base . substr($folderPub, strlen($pubBase));
        if (is_dir($folderAbs)) {
            $files = @scandir($folderAbs) ?: [];
            natcasesort($files);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..') continue;
                if (!preg_match('/\.(jpe?g|png|webp)$/i',$f)) continue;
                $url = $folderPub . '/' . $f;
                if (!in_array($url, $images, true)) $images[] = $url;
            }
        }
    }
    return array_values(array_unique($images));
}

// Cart stored per user (if logged) or per session token
function cart_token(): string {
    if (!isset($_SESSION['cart_token'])) {
        $_SESSION['cart_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_token'];
}

function add_to_cart(int $product_id, int $qty = 1): int {
    // Returns the actual quantity added (capped by available stock and existing quantity in cart)
    $qty = max(1, (int)$qty);
    $pdo = db();
    $user_id = is_logged_in() ? (int)current_user()['id'] : 0;
    $token = cart_token();
    try {
        // Current stock
        $chk = $pdo->prepare('SELECT stock FROM products WHERE id = :id');
        $chk->execute([':id' => $product_id]);
        $stock = (int)($chk->fetchColumn() ?: 0);
        if ($stock <= 0) return 0;
        // Current quantity in cart for this product
        $qStmt = $pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM cart WHERE product_id = :p AND (session_token = :t OR user_id = :u)');
        $qStmt->execute([':p'=>$product_id, ':t'=>$token, ':u'=>$user_id]);
        $already = (int)$qStmt->fetchColumn();
        $canAdd = max(0, min($qty, $stock - $already));
        if ($canAdd <= 0) return 0;
        if ($user_id) {
            $sql = 'INSERT INTO cart (user_id, product_id, quantity, session_token) VALUES (:u,:p,:q,:t)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity), updated_at = CURRENT_TIMESTAMP';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':u'=>$user_id, ':p'=>$product_id, ':q'=>$canAdd, ':t'=>$token]);
        } else {
            // session-only row; allow multiple same product rows by aggregating later
            $stmt = $pdo->prepare('INSERT INTO cart (user_id, product_id, quantity, session_token) VALUES (0, :p, :q, :t)');
            $stmt->execute([':p'=>$product_id, ':q'=>$canAdd, ':t'=>$token]);
        }
        return (int)$canAdd;
    } catch (Throwable $e) {
        return 0;
    }
}

function update_cart(int $product_id, int $qty): int {
    // Sets quantity, clamped to available stock; returns the effective quantity after update (0 if removed)
    $user_id = is_logged_in() ? (int)current_user()['id'] : 0;
    $token = cart_token();
    $qty = (int)$qty;
    try {
        $chk = db()->prepare('SELECT stock FROM products WHERE id = :id');
        $chk->execute([':id' => $product_id]);
        $stock = (int)($chk->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $stock = 0; // fail safe: don't allow increasing if unknown
    }
    if ($qty <= 0 || $stock <= 0) {
        $sql = 'DELETE FROM cart WHERE product_id = :p AND (session_token = :t OR user_id = :u)';
        $stmt = db()->prepare($sql);
        $stmt->execute([':p'=>$product_id, ':t'=>$token, ':u'=>$user_id]);
        return 0;
    }
    $eff = min($qty, $stock);
    $sql = 'UPDATE cart SET quantity = :q, updated_at = CURRENT_TIMESTAMP WHERE product_id = :p AND (session_token = :t OR user_id = :u)';
    $stmt = db()->prepare($sql);
    $stmt->execute([':q'=>$eff, ':p'=>$product_id, ':t'=>$token, ':u'=>$user_id]);
    return (int)$eff;
}

function get_cart(): array {
    $user_id = is_logged_in() ? current_user()['id'] : 0;
    $token = cart_token();
    $sql = 'SELECT c.product_id as id, p.name, p.price, p.image, p.stock, SUM(c.quantity) as quantity
            FROM cart c JOIN products p ON p.id = c.product_id
            WHERE (c.session_token = :t OR c.user_id = :u)
        GROUP BY c.product_id, p.name, p.price, p.image, p.stock';
    $stmt = db()->prepare($sql);
    $stmt->execute([':t'=>$token, ':u'=>$user_id]);
    $items = $stmt->fetchAll();
    foreach ($items as &$it) {
        // Ensure quantity doesn't exceed current stock (including zero)
        $q = (int)$it['quantity'];
        $st = (int)($it['stock'] ?? 0);
        $q = max(0, min($q, $st));
        $it['quantity'] = $q;
        $it['subtotal'] = (float)$it['price'] * $q;
    }
    return $items;
}

function cart_totals(array $items, ?string $regionOverride = null): array {
    $total = 0.0; $qty = 0;
    foreach ($items as $it) { $total += (float)$it['subtotal']; $qty += (int)$it['quantity']; }
    // Promo rule retained: 5% off ONLY when exactly 4 items
    $discount = ($qty === 4) ? $total * 0.05 : 0.0;
    // Region-based shipping rate (percentage of merchandise total after discount), with promo: 5+ items = FREE shipping
    $region = $regionOverride ? strtoupper($regionOverride) : detect_user_region();
    $rate = shipping_rate_for_region($region);
    $shipping = ($qty >= 5) ? 0.0 : max(0.0, ($total - $discount) * $rate);
    $grand = max(0, $total - $discount) + $shipping;
    return ['qty'=>$qty,'total'=>$total,'discount'=>$discount,'shipping'=>$shipping,'grand'=>$grand,'region'=>$region,'shipping_rate'=>$rate];
}

// Orders (COD only)
function place_order_cod(array $customer, array $cartItems, array $totals, ?array $selectedIds = null): int {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $user_id = is_logged_in() ? current_user()['id'] : null;
        // Region + ETA
    $region = detect_region_from_address($customer['address'] ?? (current_user()['location'] ?? ''));
    $eta = eta_for_region($region); // ['min'=>2,'max'=>3,'span'=>3]
        // Bundle rule: if the customer is logged in and already has a recent (today or yesterday)
        // order that has a delivery_date assigned and is not yet shipped/delivered/cancelled,
        // reuse that delivery_date so multiple orders placed by the same account share the same estimate.
        $firstDate = null;
        if ($user_id) {
            try {
                $ordDay = date('Y-m-d');
                $prev = date('Y-m-d', strtotime('-1 day'));
                $q = $pdo->prepare('SELECT delivery_date, order_status FROM orders WHERE user_id = :uid AND delivery_date IS NOT NULL AND order_status IN ("pending","confirmed") AND DATE(created_at) IN (:d1, :d2) ORDER BY created_at DESC LIMIT 1');
                $q->execute([':uid'=>$user_id, ':d1'=>$ordDay, ':d2'=>$prev]);
                $r = $q->fetch();
                if ($r && !in_array(strtolower((string)$r['order_status']), ['shipped','delivered','cancelled'], true)) {
                    $firstDate = $r['delivery_date'];
                }
            } catch (Throwable $e) { /* fallthrough to compute fresh date */ }
        }
        if (!$firstDate) {
            // Use strict mode so both Confirmed and Shipped windows block scheduling
            $firstDate = first_available_delivery_date($eta['span'], $region, date('Y-m-d'), 1, true);
        }
    // Recompute totals with region override to reflect shipping for this address
    $totals = cart_totals($cartItems, $region);
        $stmt = $pdo->prepare('INSERT INTO orders (customer_name, contact_number, delivery_location, payment_method, product_name, quantity, total_price, delivery_date, user_id, customer_email, is_paid, order_status, shipping_region, delivery_span_days) VALUES (:name,:phone,:addr, "COD", :pname, :qty, :amount, :ddate, :uid, :email, 0, "pending", :region, :span)');
        $summaryName = count($cartItems) === 1 ? $cartItems[0]['name'] : ($cartItems[0]['name'] . ' and others');
        $totalQty = $totals['qty'];
        $stmt->execute([
            ':name'=>$customer['name'], ':phone'=>$customer['phone'], ':addr'=>$customer['address'],
            ':pname'=>$summaryName, ':qty'=>$totalQty, ':amount'=>$totals['grand'], ':ddate'=>$firstDate,
            ':uid'=>$user_id, ':email'=>$customer['email'], ':region'=>$region, ':span'=>$eta['span']
        ]);
        $order_id = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, product_image, quantity, unit_price, total_price) VALUES (:oid,:pid,:name,:img,:qty,:price,:total)');
        foreach ($cartItems as $it) {
            if ((int)$it['quantity'] <= 0) { continue; }
            $itemStmt->execute([
                ':oid'=>$order_id, ':pid'=>$it['id'], ':name'=>$it['name'], ':img'=>$it['image'], ':qty'=>$it['quantity'], ':price'=>$it['price'], ':total'=>$it['subtotal']
            ]);
            // stock reduce
            $upd = $pdo->prepare('UPDATE products SET stock = GREATEST(0, stock - :q) WHERE id = :id');
            $upd->execute([':q'=>$it['quantity'], ':id'=>$it['id']]);
        }

        // Clear cart: all or only selected
        $token = cart_token();
        $uid = is_logged_in() ? current_user()['id'] : 0;
        if ($selectedIds && count($selectedIds) > 0) {
            // Delete only chosen product_ids
            $in = implode(',', array_fill(0, count($selectedIds), '?'));
            $params = $selectedIds;
            array_push($params, $token, $uid);
            $sql = 'DELETE FROM cart WHERE product_id IN (' . $in . ') AND (session_token = ? OR user_id = ?)';
            $del = $pdo->prepare($sql);
            $del->execute($params);
        } else {
            $del = $pdo->prepare('DELETE FROM cart WHERE session_token = :t OR user_id = :u');
            $del->execute([':t'=>$token, ':u'=>$uid]);
        }

        $pdo->commit();
        return $order_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function user_orders(): array {
    if (!is_logged_in()) return [];
    $stmt = db()->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY created_at DESC');
    $stmt->execute([':u'=>current_user()['id']]);
    return $stmt->fetchAll();
}

function order_first_item(int $orderId): ?array {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC LIMIT 1');
    $stmt->execute([':oid'=>$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function get_order(int $orderId) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_id = :oid LIMIT 1');
    $stmt->execute([':oid'=>$orderId]);
    return $stmt->fetch();
}

function get_order_items(int $orderId): array {
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :oid ORDER BY id ASC');
    $stmt->execute([':oid'=>$orderId]);
    return $stmt->fetchAll();
}

// Restore product stock for an order by adding back quantities for each item.
// Returns number of product rows updated (restocked). Uses a fallback path for legacy
// orders without order_items by matching the summary product_name.
function restock_items_for_order(int $orderId, ?PDO $pdo = null): int {
    $pdo = $pdo ?: db();
    $restocked = 0;
    try {
        // Preferred: restock from order_items
        $it = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :oid');
        $it->execute([':oid'=>$orderId]);
        $rows = $it->fetchAll();
        if ($rows && is_array($rows)) {
            $upd = $pdo->prepare('UPDATE products SET stock = stock + :q WHERE id = :pid');
            foreach ($rows as $r) {
                $pid = (int)($r['product_id'] ?? 0); $q = (int)($r['quantity'] ?? 0);
                if ($pid > 0 && $q > 0) { $upd->execute([':q'=>$q, ':pid'=>$pid]); $restocked += $upd->rowCount(); }
            }
            return $restocked;
        }
        // Fallback: attempt to infer a single product from orders table summary
        $o = $pdo->prepare('SELECT product_name, quantity FROM orders WHERE order_id = :oid LIMIT 1');
        $o->execute([':oid'=>$orderId]);
        $ord = $o->fetch();
        if ($ord) {
            $nameHint = trim((string)($ord['product_name'] ?? ''));
            $qty = max(1, (int)($ord['quantity'] ?? 0));
            if ($nameHint !== '' && $qty > 0) {
                $base = preg_replace('/\s+and others$/i', '', $nameHint);
                $fp = $pdo->prepare('SELECT id FROM products WHERE name = :n OR name LIKE :like LIMIT 1');
                $fp->execute([':n'=>$base, ':like'=>$base.'%']);
                $p = $fp->fetch();
                if ($p && (int)$p['id'] > 0) {
                    $upd = $pdo->prepare('UPDATE products SET stock = stock + :q WHERE id = :pid');
                    $upd->execute([':q'=>$qty, ':pid'=>(int)$p['id']]);
                    $restocked += $upd->rowCount();
                }
            }
        }
    } catch (Throwable $e) {
        // ignore, keep idempotent behavior
    }
    return $restocked;
}

function cancel_order(int $orderId): bool {
    if (!is_logged_in()) return false;
    $pdo = db();
    try {
        $pdo->beginTransaction();
        // Verify ownership and status
        $chk = $pdo->prepare('SELECT user_id, order_status FROM orders WHERE order_id = :oid LIMIT 1');
        $chk->execute([':oid'=>$orderId]);
        $row = $chk->fetch();
        if (!$row || (int)$row['user_id'] !== (int)current_user()['id'] || strtolower((string)$row['order_status']) !== 'pending') {
            $pdo->rollBack();
            return false;
        }
        // Mark cancelled
        $upd = $pdo->prepare('UPDATE orders SET order_status = "cancelled" WHERE order_id = :oid AND order_status = "pending"');
        $upd->execute([':oid'=>$orderId]);
        if ($upd->rowCount() <= 0) { $pdo->rollBack(); return false; }
        // Restock items
        restock_items_for_order($orderId, $pdo);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $e2) {}
        return false;
    }
}

/**
 * Cancel a single order item (by order_items.id) and adjust order totals.
 * If $asAdmin is false, the owner must be the current user and the order must be 'pending'.
 * Returns array on success with updated totals, or false on failure.
 */
function cancel_order_item(int $orderId, int $itemId, bool $asAdmin = false) {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        // Load item
        $it = $pdo->prepare('SELECT id, product_id, quantity, total_price FROM order_items WHERE id = :id AND order_id = :oid LIMIT 1');
        $it->execute([':id'=>$itemId, ':oid'=>$orderId]);
        $row = $it->fetch();
        if (!$row) { $pdo->rollBack(); return false; }
        $prodId = (int)($row['product_id'] ?? 0); $qty = (int)($row['quantity'] ?? 0); $itemTotal = (float)($row['total_price'] ?? 0.0);

        // Load order and validate
        $o = $pdo->prepare('SELECT user_id, order_status FROM orders WHERE order_id = :oid LIMIT 1');
        $o->execute([':oid'=>$orderId]);
        $ord = $o->fetch();
        if (!$ord) { $pdo->rollBack(); return false; }
        $status = strtolower((string)($ord['order_status'] ?? ''));
        if (!$asAdmin) {
            if (!is_logged_in() || (int)($ord['user_id'] ?? 0) !== (int)current_user()['id']) { $pdo->rollBack(); return false; }
            // Customers can only cancel items while order is pending
            if ($status !== 'pending') { $pdo->rollBack(); return false; }
        }

        // Remove the item row
        $del = $pdo->prepare('DELETE FROM order_items WHERE id = :id AND order_id = :oid');
        $del->execute([':id'=>$itemId, ':oid'=>$orderId]);

        // Restock the product
        if ($prodId > 0 && $qty > 0) {
            $u = $pdo->prepare('UPDATE products SET stock = stock + :q WHERE id = :pid');
            $u->execute([':q'=>$qty, ':pid'=>$prodId]);
        }

        // Recompute order totals from remaining items
        $sum = $pdo->prepare('SELECT COALESCE(SUM(total_price),0) AS total, COALESCE(SUM(quantity),0) AS qty FROM order_items WHERE order_id = :oid');
        $sum->execute([':oid'=>$orderId]);
        $s = $sum->fetch();
        $newTotal = (float)($s['total'] ?? 0.0);
        $newQty = (int)($s['qty'] ?? 0);

        if ($newQty <= 0) {
            // If no remaining items, mark order cancelled (customer) or cancelled (admin)
            $upd = $pdo->prepare('UPDATE orders SET total_price = 0, quantity = 0, product_name = "", order_status = "cancelled" WHERE order_id = :oid');
            $upd->execute([':oid'=>$orderId]);
        } else {
            // Build a short summary name (first item, then "and others" when >1)
            $first = $pdo->prepare('SELECT product_name FROM order_items WHERE order_id = :oid ORDER BY id ASC LIMIT 1');
            $first->execute([':oid'=>$orderId]);
            $f = $first->fetch(); $firstName = trim((string)($f['product_name'] ?? ''));
            $pname = $firstName;
            if ($newQty > 1 && $firstName !== '') { $pname = $firstName . ' and others'; }
            $upd = $pdo->prepare('UPDATE orders SET total_price = :total, quantity = :qty, product_name = :pname, updated_at = CURRENT_TIMESTAMP WHERE order_id = :oid');
            $upd->execute([':total'=>$newTotal, ':qty'=>$newQty, ':pname'=>$pname, ':oid'=>$orderId]);
        }

        $pdo->commit();
        return ['ok'=>true, 'order_id'=>$orderId, 'item_id'=>$itemId, 'new_total'=>$newTotal, 'new_qty'=>$newQty];
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $e2) {}
        return false;
    }
}

// Admin-initiated cancel that also restocks items. Allowed when order is in
// 'pending' or 'confirmed' state. Returns true when status changed and stock restored.
function admin_cancel_order(int $orderId): bool {
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $chk = $pdo->prepare('SELECT order_status FROM orders WHERE order_id = :oid LIMIT 1');
        $chk->execute([':oid'=>$orderId]);
        $row = $chk->fetch();
        if (!$row) { $pdo->rollBack(); return false; }
        $st = strtolower((string)$row['order_status']);
        if (!in_array($st, ['pending','confirmed'], true)) { $pdo->rollBack(); return false; }
        $upd = $pdo->prepare('UPDATE orders SET order_status = "cancelled" WHERE order_id = :oid AND order_status IN ("pending","confirmed")');
        $upd->execute([':oid'=>$orderId]);
        if ($upd->rowCount() <= 0) { $pdo->rollBack(); return false; }
        restock_items_for_order($orderId, $pdo);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        try { $pdo->rollBack(); } catch (Throwable $e2) {}
        return false;
    }
}

function get_user(int $id) {
    $stmt = db()->prepare('SELECT id, email, name, location, phone, username, role, status, dob, gender FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id'=>$id]);
    return $stmt->fetch();
}

function update_user_profile(int $id, array $data): bool {
    // Normalize values
    $email = trim($data['email'] ?? '');
    $name = trim($data['name'] ?? '');
    $location = trim($data['location'] ?? '');
    $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
    $dob = trim((string)($data['dob'] ?? ''));
    $gd = strtolower(trim((string)($data['gender'] ?? '')));
    $gender = in_array($gd, ['male','female','other',''], true) ? $gd : '';
    // date format guard YYYY-MM-DD
    $dobValid = ($dob && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) ? $dob : null;
    $stmt = db()->prepare('UPDATE users SET email = :email, name = :name, location = :location, phone = :phone, dob = :dob, gender = :gender WHERE id = :id');
    return $stmt->execute([
        ':email'=>$email,
        ':name'=>$name,
        ':location'=>$location,
        ':phone'=>$phone,
        ':dob'=>$dobValid,
        ':gender'=>$gender,
        ':id'=>$id
    ]);
}

function ensure_admin(): void {
    if (!is_logged_in() || (current_user()['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/admin/login.php');
        exit;
    }
}

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// --- Simple order message support (customer note at checkout) ---
function save_order_message(int $orderId, string $msg, ?int $userId = null): void {
    $msg = trim($msg); if($msg==='') return; $pdo = db();
    // Create table if it doesn't exist (idempotent cheap check)
    static $checked=false; if(!$checked){
        $pdo->exec("CREATE TABLE IF NOT EXISTS order_messages (id INT AUTO_INCREMENT PRIMARY KEY, order_id INT NOT NULL, user_id INT NULL, message TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(order_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $checked=true;
    }
    $stmt=$pdo->prepare('INSERT INTO order_messages (order_id,user_id,message) VALUES (:o,:u,:m)');
    $stmt->execute([':o'=>$orderId, ':u'=>$userId, ':m'=>$msg]);
}

// --- Product Ratings (1-5 stars + optional comment) ---
function ensure_ratings_schema(): void {
    static $done = false; if ($done) return; $done = true; $pdo = db();
    // Create table and unique constraint to allow one rating per (order,user,product)
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        order_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_order_user_product (order_id, user_id, product_id),
        INDEX(product_id), INDEX(user_id), INDEX(order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function user_can_rate_order_product(int $orderId, int $productId, int $userId): bool {
    if ($orderId<=0 || $productId<=0 || $userId<=0) return false; ensure_ratings_schema();
    $sql = 'SELECT 1
            FROM orders o
            JOIN order_items i ON i.order_id = o.order_id
            WHERE o.order_id = :oid AND o.user_id = :uid AND o.order_status = "delivered" AND i.product_id = :pid
            LIMIT 1';
    $st = db()->prepare($sql); $st->execute([':oid'=>$orderId, ':uid'=>$userId, ':pid'=>$productId]);
    return (bool)$st->fetchColumn();
}

function save_product_rating(int $orderId, int $productId, int $userId, int $rating, string $comment=''): bool {
    ensure_ratings_schema();
    $rating = max(1, min(5, (int)$rating));
    if (!user_can_rate_order_product($orderId, $productId, $userId)) return false;
    $sql = 'INSERT INTO product_ratings (order_id, product_id, user_id, rating, comment) VALUES (:oid,:pid,:uid,:r,:c)
            ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = CURRENT_TIMESTAMP';
    $st = db()->prepare($sql);
    return $st->execute([':oid'=>$orderId, ':pid'=>$productId, ':uid'=>$userId, ':r'=>$rating, ':c'=>trim($comment)]);
}

function product_rating_stats(int $productId): array {
    ensure_ratings_schema();
    $st = db()->prepare('SELECT AVG(rating) AS avg_rating, COUNT(*) AS cnt FROM product_ratings WHERE product_id = :p');
    $st->execute([':p'=>$productId]); $row = $st->fetch();
    $avg = (float)($row['avg_rating'] ?? 0); $cnt = (int)($row['cnt'] ?? 0);
    if ($cnt <= 0) { return ['avg'=>5.0, 'count'=>0]; } // default 5 when no ratings
    return ['avg'=>$avg, 'count'=>$cnt];
}

function product_ratings(int $productId, int $limit = 100): array {
    ensure_ratings_schema(); $limit = max(1, min(500, (int)$limit));
    $sql = 'SELECT r.*, u.name AS user_name
            FROM product_ratings r LEFT JOIN users u ON u.id = r.user_id
            WHERE r.product_id = :p ORDER BY r.created_at DESC LIMIT ' . $limit;
    $st = db()->prepare($sql); $st->execute([':p'=>$productId]);
    return $st->fetchAll();
}

// Check if a user has already rated a product for a given order
function rating_exists(int $orderId, int $productId, int $userId): bool {
    ensure_ratings_schema();
    if ($orderId<=0 || $productId<=0 || $userId<=0) return false;
    $st = db()->prepare('SELECT 1 FROM product_ratings WHERE order_id = :o AND product_id = :p AND user_id = :u LIMIT 1');
    $st->execute([':o'=>$orderId, ':p'=>$productId, ':u'=>$userId]);
    return (bool)$st->fetchColumn();
}

function order_messages(int $orderId): array {
    $stmt = db()->prepare('SELECT * FROM order_messages WHERE order_id = :o ORDER BY id ASC');
    $stmt->execute([':o'=>$orderId]);
    return $stmt->fetchAll();
}

// ================= Region + Shipping helpers =================
// Provinces mapping (simplified; add more as needed)
function province_region_map(): array {
    static $map = null; if ($map !== null) return $map;
    $map = [
        // NCR (Metro Manila)
        'METRO MANILA'=>'NCR','NCR'=>'NCR','MANILA'=>'NCR','MAKATI'=>'NCR','MANDALUYONG'=>'NCR','MARIKINA'=>'NCR','MUNTINLUPA'=>'NCR','NAVOTAS'=>'NCR','PARAÑAQUE'=>'NCR','PASAY'=>'NCR','PASIG'=>'NCR','QUEZON CITY'=>'NCR','SAN JUAN'=>'NCR','TAGUIG'=>'NCR','VALENZUELA'=>'NCR','LAS PIÑAS'=>'NCR','CALOOCAN'=>'NCR','PATEROS'=>'NCR',
        // Region IV-A (CALABARZON)
        'CAVITE'=>'R4A','LAGUNA'=>'R4A','BATANGAS'=>'R4A','RIZAL'=>'R4A','QUEZON'=>'R4A',
        // Region IV-B (MIMAROPA)
        'OCCIDENTAL MINDORO'=>'R4B','ORIENTAL MINDORO'=>'R4B','MARINDUQUE'=>'R4B','ROMBLON'=>'R4B','PALAWAN'=>'R4B',
        // Region V (Bicol)
        'ALBAY'=>'R5','CAMARINES NORTE'=>'R5','CAMARINES SUR'=>'R5','CATANDUANES'=>'R5','MASBATE'=>'R5','SORSOGON'=>'R5',
        // Visayas (group all provinces broadly)
        'AKLAN'=>'VISAYAS','ANTIQUE'=>'VISAYAS','CAPIZ'=>'VISAYAS','ILOILO'=>'VISAYAS','GUIMARAS'=>'VISAYAS','NEGROS OCCIDENTAL'=>'VISAYAS','CEBU'=>'VISAYAS','BOHOL'=>'VISAYAS','SIQUIJOR'=>'VISAYAS','NEGROS ORIENTAL'=>'VISAYAS','EASTERN SAMAR'=>'VISAYAS','NORTHERN SAMAR'=>'VISAYAS','SAMAR'=>'VISAYAS','LEYTE'=>'VISAYAS','SOUTHERN LEYTE'=>'VISAYAS','BILIRAN'=>'VISAYAS',
        // Luzon others (fallback within Luzon mainland)
        'ABRA'=>'LUZON','APAYAO'=>'LUZON','BENGUET'=>'LUZON','IFUGAO'=>'LUZON','KALINGA'=>'LUZON','MOUNTAIN PROVINCE'=>'LUZON',
        'ILOCOS NORTE'=>'LUZON','ILOCOS SUR'=>'LUZON','LA UNION'=>'LUZON','PANGASINAN'=>'LUZON',
        'BATAAN'=>'LUZON','BULACAN'=>'LUZON','NUEVA ECIJA'=>'LUZON','PAMPANGA'=>'LUZON','TARLAC'=>'LUZON','ZAMBALES'=>'LUZON','AURORA'=>'LUZON',
        'BATANES'=>'LUZON','CAGAYAN'=>'LUZON','ISABELA'=>'LUZON','NUEVA VIZCAYA'=>'LUZON','QUIRINO'=>'LUZON',
        'BICOL REGION'=>'R5','CALABARZON'=>'R4A','MIMAROPA'=>'R4B'
    ];
    // Mindanao (broad, add common provinces/cities)
    $mindanao = [
        'DAVAO'=>'MINDANAO','DAVAO DEL SUR'=>'MINDANAO','DAVAO DEL NORTE'=>'MINDANAO','DAVAO ORIENTAL'=>'MINDANAO','DAVAO DE ORO'=>'MINDANAO',
        'COTABATO'=>'MINDANAO','SULTAN KUDARAT'=>'MINDANAO','AGUSAN'=>'MINDANAO','SURIGAO'=>'MINDANAO','ZAMBOANGA'=>'MINDANAO','MISAMIS'=>'MINDANAO'
    ];
    foreach ($mindanao as $k=>$v) { $map[$k]=$v; }
    return $map;
}


function normalize_str($s): string { return strtoupper(trim(preg_replace('/\s+/', ' ', (string)$s))); }

function detect_region_from_address($address): string {
    $map = province_region_map();
    // If array provided with province
    if (is_array($address)) {
        $prov = normalize_str($address['province'] ?? '');
        if ($prov && isset($map[$prov])) return $map[$prov];
        $city = normalize_str($address['city'] ?? ''); if ($city && isset($map[$city])) return $map[$city];
    }
    // If string, search for any known province/city tokens
    $str = normalize_str(is_string($address) ? $address : json_encode($address));
    foreach ($map as $k=>$reg) { if ($k && strpos($str, $k) !== false) return $reg; }
    return 'LUZON'; // sensible default
}

function detect_user_region(): string {
    $u = current_user();
    $addr = $u['location'] ?? '';
    return detect_region_from_address($addr);
}

function shipping_rate_for_region(string $region): float {
    switch (strtoupper($region)) {
        case 'NCR': return 0.05;       // +5%
        case 'R4A': return 0.03;       // +3%
        case 'R4B': return 0.05;       // +5%
        case 'R5':  return 0.10;       // +10%
        case 'VISAYAS': return 0.15;   // +15%
        case 'LUZON': default: return 0.08; // +8%
    }
}

function eta_for_region(string $region): array {
    $region = strtoupper($region);
    // Per requirements: NCR – 2–3 days, Luzon – 5–7 days, Visayas – 15–20 days, Mindanao – 15–20 days
    if ($region === 'NCR') return ['min'=>2,'max'=>3,'span'=>3];
    // Region IV-A specifically uses 2-3 days per example
    if ($region === 'R4A') return ['min'=>2,'max'=>3,'span'=>3];
    if ($region === 'VISAYAS') return ['min'=>15,'max'=>20,'span'=>20];
    if ($region === 'MINDANAO') return ['min'=>15,'max'=>20,'span'=>20];
    // Fallback: treat other Luzon provinces as Luzon (5-7)
    return ['min'=>5,'max'=>7,'span'=>7];
}

// Find the first date (YYYY-MM-DD) such that the next span days are not busy.
// Region-aware bundling rules (global: all regions, all months):
// 1) Same-day + same-region orders bundle (not blocking each other)
// 2) If there is an existing CONFIRMED (not yet SHIPPED) busy window for the same region and
//    the current order day falls INSIDE that window (but NOT on its last day), allow bundling
//    by not treating that window as busy for the new order. If the order day is the last day of
//    that window, we block and pick the next free date after the window.
function first_available_delivery_date(int $spanDays, ?string $region = null, ?string $orderDay = null, int $minLeadDays = 1, bool $strict = false): string {
    $spanDays = max(1, $spanDays);
    $minLeadDays = max(1, (int)$minLeadDays);
    // Build set of busy days from confirmed/shipped orders and confirmed services
    $busy = [];
    try {
    // Include all orders that already have a delivery_date assigned (pending/confirmed/shipped).
    // We'll treat non-shipped orders as bundling anchors (allow other same-region orders placed inside
    // their window to share the same estimate) and only mark busy days from orders that are already
    // 'shipped' (Out for Delivery) or 'delivered'. This lets new orders bundle until admin ships them.
    $rows = db()->query("SELECT delivery_date, COALESCE(delivery_span_days,3) AS span, COALESCE(shipping_region,'') AS reg, DATE(created_at) AS cday, order_status AS st FROM orders WHERE delivery_date IS NOT NULL")->fetchAll();
        $ordDay = $orderDay ? date('Y-m-d', strtotime($orderDay)) : null;
        $regWant = $region ? strtoupper($region) : null;
        foreach ($rows as $r) {
            $d = $r['delivery_date']; $s = (int)$r['span']; if(!$d) continue;
            $regRow = strtoupper((string)($r['reg'] ?? ''));
            $cday = $r['cday'] ?? null;
            $st = strtoupper((string)($r['st'] ?? ''));
            // If strict mode is requested (used for scheduling services), treat confirmed and
            // shipped orders as busy so admin won't be double-booked for transactions or services.
            // Orders already marked delivered/received should free the schedule immediately.
            if ($strict) {
                if (in_array($st, ['CONFIRMED','SHIPPED'], true)) {
                    for ($i=0;$i<$s;$i++) { $busy[date('Y-m-d', strtotime($d." +$i day"))] = true; }
                }
                continue;
            }
            // Non-strict: previous bundling rules (allow bundling unless an order is shipped/delivered)
            // If same region and ordered the same day, do not block (bundle deliveries)
            if ($ordDay && $regWant && $regRow === $regWant && $cday === $ordDay) { continue; }
            // If same region and this existing order is NOT shipped (e.g., pending or confirmed), allow
            // bundling when the new order's creation day falls inside that existing window (except last day).
            if ($ordDay && $regWant && $regRow === $regWant && strtoupper($st) !== 'SHIPPED') {
                $start = strtotime($d);
                $endIncl = strtotime('+'.max(0,$s-1).' day', $start);
                $ordTs = strtotime($ordDay);
                if ($ordTs >= $start && $ordTs < $endIncl) {
                    // Inside window but not last day: bundle -> do not mark this window busy for this order
                    continue;
                }
                // If $ordTs == $endIncl (last day), fall through to mark as busy
            }
            // Only mark busy days for orders that are shipped (Out for Delivery).
            // Orders that are 'delivered' (received) no longer block scheduling.
            if (strtoupper($st) === 'SHIPPED') {
                for ($i=0;$i<$s;$i++) { $busy[date('Y-m-d', strtotime($d." +$i day"))] = true; }
            }
        }
        // Services: default 3-day span (keep light if schema unknown)
        try {
            $srows = db()->query("SELECT preferred_date FROM services WHERE status IN ('Confirmed','In transit') AND preferred_date IS NOT NULL")->fetchAll();
            foreach ($srows as $sr) { $d=$sr['preferred_date']; if(!$d) continue; for($i=0;$i<3;$i++) $busy[date('Y-m-d', strtotime($d." +$i day"))]=true; }
        } catch (Throwable $e) {}
    } catch (Throwable $e) {}
    // Start searching from the day after the order day (or tomorrow) so admin busy windows don't include the order day
    $today = strtotime(date('Y-m-d'));
    $ordTs = $ordDay ? strtotime($ordDay) : null;
    if ($ordTs) {
        // start at max(tomorrow, orderDay + minLeadDays)
        $searchBase = max($today, strtotime('+' . $minLeadDays . ' day', $ordTs));
    } else {
        $searchBase = strtotime('+1 day', $today);
    }
    for ($offset=0; $offset<180; $offset++) {
        $cand = strtotime("+".$offset." day", $searchBase);
        $ok = true;
        for ($i=0; $i<$spanDays; $i++) {
            $ds = date('Y-m-d', strtotime("+".$i." day", $cand));
            if (!empty($busy[$ds])) { $ok = false; break; }
        }
        if ($ok) return date('Y-m-d', $cand);
    }
    return date('Y-m-d', strtotime('+7 days')); // fallback
}

?>
