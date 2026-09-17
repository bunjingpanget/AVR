<?php require_once __DIR__ . '/functions.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= isset($page_title) ? h($page_title) . ' - ' : '' ?>AVR Shop</title>
  <link rel="icon" type="image/png" href="<?= BASE_URL ?>/assets/images/logo.png" />
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=20250927" />
  <script>window.BASE_URL = '<?= BASE_URL ?>';</script>
  <?php if (is_logged_in()): ?>
  <script>window.currentUserName = <?= json_encode(current_user()['name'] ?? 'User') ?>;</script>
  <?php endif; ?>
  <?php if (is_logged_in()): ?>
  <script>window.currentUserName = <?= json_encode(current_user()['name'] ?? 'User') ?>;</script>
  <?php endif; ?>
</head>
<body>
  <?php include __DIR__ . '/navbar.php'; ?>
