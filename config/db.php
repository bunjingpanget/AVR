<?php
// Robust PDO connection (UTF-8, exceptions) with sensible fallbacks for XAMPP/local and production.
// You can override settings via environment variables: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
// Additionally, if present, config/secret-db.php can provide/override these values securely (not committed).

// 1) Local defaults (XAMPP)
$DB = [
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int)(getenv('DB_PORT') ?: 3307), // XAMPP often uses 3307; we will still try 3306 below
    'name' => getenv('DB_NAME') ?: 'avr_db',
    'user' => getenv('DB_USER') ?: 'root',
    'pass' => getenv('DB_PASS') !== false ? getenv('DB_PASS') : ''
];

// 2) Auto-switch to Hostinger production on fnpss-service.site when env vars are not provided
$hostHeader = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
$envProvided = (getenv('DB_HOST') !== false) || (getenv('DB_NAME') !== false) || (getenv('DB_USER') !== false) || (getenv('DB_PASS') !== false);

// Helper to check if host ends with a domain suffix (PHP 7+ compatible)
$endsWith = function(string $haystack, string $needle): bool {
    if ($needle === '') return true;
    $length = strlen($needle);
    return substr($haystack, -$length) === $needle;
};

$isHostingerDomain = ($hostHeader === 'fnpss-service.site')
    || ($hostHeader === 'www.fnpss-service.site')
    || $endsWith($hostHeader, '.fnpss-service.site');

if ($isHostingerDomain && !$envProvided) {
    // Use Hostinger MySQL defaults; password is loaded from secret or env (do not hardcode in repo)
    $DB = [
        'host' => 'localhost',     // Hostinger shared MySQL host
        'port' => 3306,
        'name' => 'u600373254_avr_db',    // From your hPanel screenshot
        'user' => 'u600373254_mel_031705',    // From your hPanel screenshot
        'pass' => 'stK/q@Z9k'                   // Filled from secret-db.php or DB_PASS env below
    ];
}

// 3) Optional secret override (not committed). Create config/secret-db.php returning an array of keys above.
$secretPath = __DIR__ . DIRECTORY_SEPARATOR . 'secret-db.php';
if (is_file($secretPath)) {
    $secret = include $secretPath;
    if (is_array($secret)) {
        $DB = array_merge($DB, $secret);
    }
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    global $DB;
    $hosts = array_unique([$DB['host'], '127.0.0.1', 'localhost']);
    $ports = array_unique([(int)$DB['port'], 3306, 3307]);
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $lastErr = null;
    foreach ($hosts as $h) {
        foreach ($ports as $p) {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $h, $p, $DB['name']);
            try {
                $pdo = new PDO($dsn, $DB['user'], $DB['pass'], $opts);
                return $pdo;
            } catch (Throwable $e) {
                $lastErr = $e;
                // try next combination
            }
        }
    }
    // If we reach here, all attempts failed.
    if ($lastErr) {
        throw ($lastErr instanceof PDOException) ? $lastErr : new PDOException($lastErr->getMessage());
    }
    throw new PDOException('Database connection failed: no hosts/ports available');
}

// Tiny helper to prepare queries (execution left to caller)
function q(string $sql, array $params = []): PDOStatement { return db()->prepare($sql); }

?>
