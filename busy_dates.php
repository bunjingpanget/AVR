<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');
// Return busy dates with type information so the calendar can render Orders (red) vs Services (violet)
$rows = db()->query("SELECT delivery_date, COALESCE(delivery_span_days,3) AS span FROM orders WHERE order_status IN ('confirmed','shipped') AND delivery_date IS NOT NULL")->fetchAll();
$busy = [];
foreach ($rows as $r) {
    $d = $r['delivery_date']; $span = max(1,(int)$r['span']); if(!$d) continue;
    for ($i=0;$i<$span;$i++) {
        $date = date('Y-m-d', strtotime($d." +$i day"));
        // Orders take precedence if both types fall on same day
        $busy[$date] = 'Order';
    }
}
// Services with status Confirmed or In transit use a default 3-day span window (Complete/Cancelled excluded)
try {
    $srows = db()->query("SELECT preferred_date FROM services WHERE status IN ('Confirmed','In transit')")->fetchAll();
    foreach ($srows as $sr){ $d=$sr['preferred_date']; if(!$d) continue; for($i=0;$i<3;$i++){ $date = date('Y-m-d', strtotime($d." +$i day")); if(!isset($busy[$date])) $busy[$date] = 'Service'; }}
} catch(Throwable $e) {}
ksort($busy);
echo json_encode(['busy'=>$busy]);
