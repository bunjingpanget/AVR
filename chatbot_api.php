<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json');

// Ensure required columns exist and remove foreign key constraint for order_id = 0
try {
    $pdo = db();
    $pdo->exec("ALTER TABLE order_messages ADD COLUMN customer_name VARCHAR(255) NULL");
} catch (Throwable $e) {} // Column might already exist

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE order_messages ADD COLUMN from_admin TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {} // Column might already exist

try {
    $pdo = db();
    $pdo->exec("ALTER TABLE order_messages ADD COLUMN seen_by_customer TINYINT(1) NOT NULL DEFAULT 0");
} catch (Throwable $e) {} // Column might already exist

try {
    $pdo = db();
    // Drop foreign key constraint for order_id to allow special value 0 for chatbot messages
    $pdo->exec("ALTER TABLE order_messages DROP FOREIGN KEY order_messages_order_fk");
} catch (Throwable $e) {} // Constraint might not exist or already dropped

// Handle @seller message from chatbot
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'seller') {
    $message = trim((string)($_POST['message'] ?? ''));
    $userId = is_logged_in() ? (int)current_user()['id'] : null;
    $customerName = trim((string)($_POST['customer_name'] ?? ''));
    
    if (!$message) {
        echo json_encode(['success' => false, 'error' => 'Message is required']);
        exit;
    }
    
    try {
        $pdo = db();
        
        // Create/use a special order_id (0) for chatbot messages
        $orderId = 0;
        
        // Insert chatbot message
        $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, user_id, message, customer_name, from_admin, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$orderId, $userId, $message, $customerName]);
        
        echo json_encode(['success' => true, 'message' => 'Message sent to seller successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to send message']);
    }
    exit;
}

// Get chat messages for a user
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_messages') {
    $userId = is_logged_in() ? (int)current_user()['id'] : null;
    $customerName = isset($_GET['customer_name']) ? trim((string)$_GET['customer_name']) : null;
    try {
        $pdo = db();
        // Determine conversation selector
        if ($userId) {
            $selSql = "order_id = 0 AND user_id = ?"; $selParam = [$userId];
        } elseif ($customerName) {
            $selSql = "order_id = 0 AND customer_name = ?"; $selParam = [$customerName];
        } else {
            echo json_encode(['success'=>true,'messages'=>[]]);
            exit;
        }

    // Determine if conversation is closed: if the latest message is a close marker
    $lastStmt = $pdo->prepare("SELECT id, message FROM order_messages WHERE $selSql ORDER BY id DESC LIMIT 1");
    $lastStmt->execute($selParam);
    $lastRow = $lastStmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT id, message, from_admin, seen_by_customer, created_at FROM order_messages WHERE $selSql AND from_admin = 1 ORDER BY created_at ASC");
        $stmt->execute($selParam);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Optionally mark as seen when requested and user is logged in
        $markSeen = isset($_GET['mark_seen']) && $_GET['mark_seen'] === '1';
        if ($userId && $markSeen) {
            $seen = $pdo->prepare("UPDATE order_messages SET seen_by_customer = 1 WHERE order_id = 0 AND user_id = ? AND from_admin = 1");
            $seen->execute([$userId]);
        }

    $isClosed = $lastRow && strpos((string)$lastRow['message'], '[Chat closed by admin]') === 0;
    echo json_encode(['success' => true, 'messages' => $messages, 'closed' => $isClosed]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to get messages']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>