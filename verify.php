<?php
require_once __DIR__ . '/includes/functions.php';

// Accept POST with email, code, purpose (optional). Return JSON for AJAX, or redirect back for form.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo 'Method Not Allowed'; exit;
}
$isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);
$email = strtolower(trim((string)($_POST['email'] ?? $_POST['_otp_email'] ?? '')));
$code = trim((string)($_POST['code'] ?? $_POST['otp'] ?? ''));
$purpose = trim((string)($_POST['purpose'] ?? 'registration'));
if ($email === '' || $code === '') {
    $msg = 'Email and code are required.';
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }
    $_SESSION['flash_err'] = $msg; header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
}
$res = verify_code_for_email($email, $code, $purpose);
if ($res['ok']) {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>true,'msg'=>$res['msg']]); exit; }
    // If there's a pending_reg matching the email, finalize registration if present
    if (!empty($_SESSION['pending_reg']) && (strtolower($_SESSION['pending_reg']['email'] ?? '') === $email)) {
        $reg = $_SESSION['pending_reg']; unset($_SESSION['pending_reg']);
        $err = null; $ok = register_user($reg, $err);
        if ($ok) {
            login($reg['email'] ?? '', $reg['password'] ?? '');
            $_SESSION['show_terms_after_register'] = 1;
            $_SESSION['post_register_dest'] = BASE_URL . '/index.php';
            $_SESSION['flash_ok'] = 'Registration completed.';
            header('Location: ' . BASE_URL . '/register.php?t=1'); exit;
        } else {
            $_SESSION['flash_err'] = $err ?: 'Registration failed while finalizing.';
            header('Location: ' . BASE_URL . '/register.php'); exit;
        }
    }
    // otherwise redirect home
    $_SESSION['flash_ok'] = $res['msg'] ?? 'Email verified.';
    header('Location: ' . BASE_URL . '/index.php'); exit;
} else {
    if ($isAjax) { header('Content-Type: application/json'); echo json_encode(['ok'=>false,'msg'=>$res['msg']]); exit; }
    $_SESSION['flash_err'] = $res['msg'] ?? 'Verification failed.';
    header('Location: ' . BASE_URL . '/register.php?otp=1'); exit;
}

?>
