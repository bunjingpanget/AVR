<?php
require_once __DIR__ . '/../includes/functions.php';
ensure_admin();

header('Content-Type: application/json');

try {
    // Counts
    $pdo = db();
    $pendingOrders = (int)$pdo->query("SELECT COUNT(*) c FROM orders WHERE order_status='pending'")->fetch()['c'];
    $pendingServices = 0; try { $pendingServices = (int)$pdo->query("SELECT COUNT(*) c FROM services WHERE status='Pending'")->fetch()['c']; } catch(Throwable $e) {}
    $pendingMsgs = 0; try { $pendingMsgs = (int)$pdo->query("SELECT COUNT(DISTINCT o.order_id) c FROM order_messages om JOIN orders o ON o.order_id=om.order_id WHERE o.order_status='pending'")->fetch()['c']; } catch(Throwable $e) {}
    // Unseen messages since last visit to admin_messages.php (session-based)
    $newMsgs = 0; try { $seen = (int)($_SESSION['admin_msgs_seen'] ?? 0); $st = $pdo->prepare('SELECT COUNT(*) c FROM order_messages WHERE id > :s'); $st->execute([':s'=>$seen]); $newMsgs = (int)$st->fetch()['c']; } catch(Throwable $e) {}
    $products = (int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'];
    $revenue = (float)$pdo->query("SELECT IFNULL(SUM(total_price),0) t FROM orders WHERE order_status = 'delivered'")->fetch()['t'];

    // Recent orders for Transactions Overview: only include orders that are completed (delivered / Order Received)
    $recent = $pdo->query("SELECT order_id,customer_name,total_price,order_status,created_at,delivery_date FROM orders WHERE order_status = 'delivered' ORDER BY created_at DESC LIMIT 8")->fetchAll();

    // Top customers for Transactions Overview (only count customers with delivered orders)
    $topCustomers = $pdo->query("SELECT u.id,u.name,u.email,COUNT(o.order_id) cnt,SUM(o.total_price) amt FROM users u JOIN orders o ON o.user_id=u.id WHERE (u.role IS NULL OR u.role<>'admin') AND o.order_status = 'delivered' GROUP BY u.id,u.name,u.email ORDER BY cnt DESC, amt DESC LIMIT 5")->fetchAll();

    // Overview (last 14 days) — only include completed (delivered) orders for the Transactions Overview
    $overview = $pdo->query("SELECT DATE(created_at) d, SUM(total_price) sales, COUNT(*) orders, COUNT(DISTINCT user_id) customers FROM orders WHERE created_at >= DATE_SUB(CURDATE(),INTERVAL 13 DAY) AND order_status = 'delivered' GROUP BY DATE(created_at) ORDER BY d ASC")->fetchAll();
    $ovMap = []; foreach($overview as $o){ $ovMap[$o['d']]=$o; }
    $days=[]; for($i=13;$i>=0;$i--){ $d=date('Y-m-d',strtotime("-$i day")); $row=$ovMap[$d]??['d'=>$d,'sales'=>0,'orders'=>0,'customers'=>0]; $days[]=$row; }

    // Analytics extras
    // 1) Customers: total registered (non-admin) and distinct buyers (exclude cancelled orders)
    $customersCount = 0; try { $customersCount = (int)$pdo->query("SELECT COUNT(*) c FROM users WHERE (role IS NULL OR role<>'admin')")->fetch()['c']; } catch(Throwable $e) {}
    $buyersCount = 0; try { $buyersCount = (int)$pdo->query("SELECT COUNT(DISTINCT user_id) c FROM orders WHERE user_id IS NOT NULL AND order_status = 'delivered'")->fetch()['c']; } catch(Throwable $e) {}
    // 2) Top products by quantity sold (from order_items)
    $topProducts = [];
    try {
        // Top products should reflect completed (delivered) transactions only
        $topProducts = $pdo->query("SELECT p.id, p.name, SUM(oi.quantity) qty, SUM(oi.total_price) amt
            FROM order_items oi JOIN products p ON p.id=oi.product_id
            JOIN orders o ON o.order_id = oi.order_id
            WHERE o.order_status = 'delivered'
            GROUP BY p.id, p.name ORDER BY qty DESC, amt DESC LIMIT 6")->fetchAll();
    } catch(Throwable $e) {}
    // 3) Top regions by sales (pie) — exclude cancelled orders
    $topRegions = [];
    try {
        // Regions by sales — only delivered orders count toward the Transactions Overview
        $topRegions = $pdo->query("SELECT UPPER(COALESCE(shipping_region,'UNKNOWN')) region, SUM(total_price) amt, COUNT(*) orders
            FROM orders
            WHERE order_status = 'delivered'
            GROUP BY UPPER(COALESCE(shipping_region,'UNKNOWN'))
            ORDER BY amt DESC")->fetchAll();
    } catch(Throwable $e) {}
    // 4) Ratings distribution + average
    $ratings = ['avg'=>0, 'count'=>0, 'dist'=>[1=>0,2=>0,3=>0,4=>0,5=>0]];
    try {
        $row = $pdo->query("SELECT AVG(rating) avg_rating, COUNT(*) cnt FROM product_ratings")->fetch();
        $ratings['avg'] = (float)($row['avg_rating'] ?? 0);
        $ratings['count'] = (int)($row['cnt'] ?? 0);
        $rs = $pdo->query("SELECT rating, COUNT(*) cnt FROM product_ratings GROUP BY rating")->fetchAll();
        foreach($rs as $r){ $k=(int)$r['rating']; if($k>=1 && $k<=5) $ratings['dist'][$k] = (int)$r['cnt']; }
    } catch(Throwable $e) {}

    // 5) Sales entered totals: today, this week, this month (based on created_at, aligns with overview) — exclude cancelled
    $salesPeriods = ['today'=>0.0,'week'=>0.0,'month'=>0.0,'year'=>0.0];
    try {
        // Sales entered totals: only count delivered orders for Transactions Overview
        $q = $pdo->query("SELECT 
            IFNULL(SUM(CASE WHEN DATE(created_at)=CURDATE() THEN total_price ELSE 0 END),0) AS tdy,
            IFNULL(SUM(CASE WHEN YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1) THEN total_price ELSE 0 END),0) AS wk,
            IFNULL(SUM(CASE WHEN YEAR(created_at)=YEAR(CURDATE()) AND MONTH(created_at)=MONTH(CURDATE()) THEN total_price ELSE 0 END),0) AS mon,
            IFNULL(SUM(CASE WHEN YEAR(created_at)=YEAR(CURDATE()) THEN total_price ELSE 0 END),0) AS yr
        FROM orders WHERE order_status = 'delivered'");
        $r = $q->fetch();
        $salesPeriods = ['today'=>(float)$r['tdy'], 'week'=>(float)$r['wk'], 'month'=>(float)$r['mon'], 'year'=>(float)$r['yr']];
    } catch(Throwable $e) {}

    // 6) Low stock products (stock <= 5)
    $lowStock = [];
    try {
        $lowStock = $pdo->query("SELECT id, name, stock, price FROM products WHERE stock <= 5 ORDER BY stock ASC, id DESC LIMIT 50")->fetchAll();
    } catch (Throwable $e) {}

    echo json_encode([
        'counts' => [
            'pendingOrders' => $pendingOrders,
            'pendingServices' => $pendingServices,
            'pendingMsgs' => $pendingMsgs,
            'products' => $products,
            'revenue' => $revenue,
            'newMsgs' => $newMsgs,
        ],
        'recent' => $recent,
        'topCustomers' => $topCustomers,
        'overview' => $days,
        'analytics' => [
            'customers' => ['total'=>$customersCount, 'buyers'=>$buyersCount],
            'topProducts' => $topProducts,
            'topRegions' => $topRegions,
            'ratings' => $ratings,
            'salesPeriods' => $salesPeriods,
        ],
        'lowStock' => $lowStock,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => true]);
}

?>
