<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ui.php';
ensure_admin();

// Lightweight poll endpoint for AJAX (real-time updates)
if (isset($_GET['poll'])) {
  header('Content-Type: application/json');
  $since = (int)($_GET['since'] ?? 0);
  $forUser = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
  try {
    if ($forUser > 0) {
      $stmt = db()->prepare("SELECT id, user_id, customer_name, message, from_admin, created_at FROM order_messages WHERE order_id = 0 AND user_id = ? AND id > ? ORDER BY id ASC LIMIT 500");
      $stmt->execute([$forUser, $since]);
    } else {
      $stmt = db()->prepare("SELECT id, user_id, customer_name, message, from_admin, created_at FROM order_messages WHERE order_id = 0 AND id > ? ORDER BY id ASC LIMIT 500");
      $stmt->execute([$since]);
    }
    $rows = $stmt->fetchAll();
    echo json_encode(['ok'=>true,'messages'=>$rows,'last'=>end($rows)['id'] ?? $since]);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false,'error'=>'poll_failed']);
  }
  exit;
}

// Lightweight poll for orders/services lists (Message Seller + Service Requests)
if (isset($_GET['poll_list'])) {
  header('Content-Type: application/json');
  try {
    // Render Orders (Message Seller / Cart) rows
    ob_start();
    $orders = db()->query("SELECT o.order_id, o.customer_name,
      (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS note,
      (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS msg_time
      FROM orders o
      WHERE EXISTS (SELECT 1 FROM order_messages m2 WHERE m2.order_id = o.order_id AND m2.user_id IS NOT NULL)
      ORDER BY msg_time DESC LIMIT 50")->fetchAll();
    foreach ($orders as $r): ?>
      <tr class="msg-row">
        <td data-label="ID">#<?= (int)$r['order_id']; ?></td>
        <td data-label="Customer"><?= h($r['customer_name']); ?></td>
        <td data-label="Message" title="<?= h($r['note'] ?? ''); ?>"><?= h($r['note'] ?? ''); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($r['msg_time'] ?? ($r['created_at'] ?? 'now')))); ?></td>
      </tr>
    <?php endforeach; $ordersHtml = ob_get_clean();

    // Render Services rows
    ob_start();
    try { $services = db()->query("SELECT id, customer_name, description, created_at FROM services ORDER BY created_at DESC LIMIT 50")->fetchAll(); } catch (Throwable $e) { $services = []; }
    foreach ($services as $s): ?>
      <tr class="msg-row">
        <td data-label="ID">#<?= (int)$s['id']; ?></td>
        <td data-label="Customer"><?= h($s['customer_name']); ?></td>
        <td data-label="Description" title="<?= h($s['description']); ?>"><?= h($s['description']); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($s['created_at']))); ?></td>
      </tr>
    <?php endforeach; $servicesHtml = ob_get_clean();

    echo json_encode(['ok'=>true,'orders_html'=>$ordersHtml,'services_html'=>$servicesHtml]);
  } catch (Throwable $e) {
    echo json_encode(['ok'=>false]);
  }
  exit;
}

// Selected customer filter (from query or after select change)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Handle new message to customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_to_customer'])) {
  $userId = (int)$_POST['customer_id'];
  $message = trim((string)$_POST['new_message']);
  if ($message && $userId > 0) {
    try {
      $pdo = db();
      $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, user_id, message, from_admin, created_at) VALUES (0, ?, ?, 1, NOW())");
      $stmt->execute([$userId, $message]);
      // Redirect and keep focus on this user only
      header('Location: ' . BASE_URL . '/admin/admin_messages.php?user_id=' . $userId . '#chatbot-messages');
      exit;
    } catch (Exception $e) {
      $error = "Failed to send message.";
    }
  }
}

// Handle admin reply to chatbot message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reply_message'], $_POST['user_id']) && !isset($_POST['exit_chat'])) {
  $userId = (int)$_POST['user_id'];
  $message = trim((string)$_POST['reply_message']);
  if ($message && $userId > 0) {
    try {
      $pdo = db();
      $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, user_id, message, from_admin, created_at) VALUES (0, ?, ?, 1, NOW())");
      $stmt->execute([$userId, $message]);
  // Redirect and keep focus on this user only
  header('Location: ' . BASE_URL . '/admin/admin_messages.php?user_id=' . $userId . '#chatbot-messages');
      exit;
    } catch (Exception $e) {
      $error = "Failed to send reply.";
    }
  }
}

// Handle exit chat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exit_chat'], $_POST['user_id'])) {
  $userId = (int)$_POST['user_id'];
  try {
    $pdo = db();
    // Mark chat as closed by adding a system message
    $stmt = $pdo->prepare("INSERT INTO order_messages (order_id, user_id, message, from_admin, created_at) VALUES (0, ?, '[Chat closed by admin]', 1, NOW())");
    $stmt->execute([$userId]);
  header('Location: ' . BASE_URL . '/admin/admin_messages.php?user_id=' . $userId . '#chatbot-messages');
    exit;
  } catch (Exception $e) {}
}

// mark all messages as seen for counter reset
try { $lastId = (int)db()->query('SELECT IFNULL(MAX(id),0) m FROM order_messages')->fetch()['m']; $_SESSION['admin_msgs_seen']=$lastId; } catch(Throwable $e) {}

admin_layout_start('Messages');
// Message Seller (customer notes at checkout/cart): latest per order
$orders = db()->query("SELECT o.order_id, o.customer_name,
  (SELECT m.message FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS note,
  (SELECT m.created_at FROM order_messages m WHERE m.order_id = o.order_id AND m.user_id IS NOT NULL ORDER BY m.id DESC LIMIT 1) AS msg_time
  FROM orders o
  WHERE EXISTS (SELECT 1 FROM order_messages m2 WHERE m2.order_id = o.order_id AND m2.user_id IS NOT NULL)
  ORDER BY msg_time DESC LIMIT 50")->fetchAll();
// Service descriptions: recent submissions
try {
  $services = db()->query("SELECT id, customer_name, description, created_at FROM services ORDER BY created_at DESC LIMIT 50")->fetchAll();
} catch (Throwable $e) { $services = []; }

// Get all customers for message selector
try {
  // Exclude admin accounts (cannot message self / other admins) & current admin id
  $adminId = (int)(current_user()['id'] ?? 0);
  $st = db()->prepare("SELECT id, name, email FROM users WHERE status = 'active' AND role <> 'admin' AND id <> :id ORDER BY name ASC");
  $st->execute([':id'=>$adminId]);
  $customers = $st->fetchAll();
} catch (Throwable $e) { $customers = []; }

// Chatbot messages list
try {
  if ($selectedUserId > 0) {
    // Focus on one user only (even if no prior messages)
    $uStmt = db()->prepare("SELECT id, name FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$selectedUserId]);
    $urow = $uStmt->fetch();
    $chatbotConversations = [];
    if ($urow) {
      $chatbotConversations[] = [
        'user_id' => (int)$urow['id'],
        'customer_name' => $urow['name'] ?? 'Customer',
        'last_activity' => date('Y-m-d H:i:s'),
        'unread_count' => 0,
      ];
    }
  } else {
    // Show only conversations that still need admin reply (last message from customer)
    $sql = "SELECT m1.user_id, COALESCE(m1.customer_name, u.name, 'Anonymous') AS customer_name, m1.created_at AS last_activity
            FROM order_messages m1
            LEFT JOIN users u ON m1.user_id = u.id
            WHERE m1.order_id = 0
              AND m1.id IN (
                SELECT MAX(m2.id) FROM order_messages m2 WHERE m2.order_id = 0 GROUP BY COALESCE(m2.user_id, m2.customer_name)
              )
              AND m1.from_admin = 0
            ORDER BY m1.created_at DESC LIMIT 50";
    $chatbotConversations = db()->query($sql)->fetchAll();
  }
} catch (Throwable $e) { $chatbotConversations = []; }

?>
<style>
/* Professional Admin Chat UI - 60-30-10 Color Rule */
.admin-chat-container { background: #f8fafc; padding: 20px; border-radius: 12px; }
.chat-header { background: #1e40af; color: white; padding: 16px; border-radius: 8px 8px 0 0; font-weight: 600; }
.chat-body { background: white; border: 1px solid #e5e7eb; max-height: 400px; overflow-y: auto; overflow-x: hidden; padding: 16px; }
.message-bubble { margin: 8px 0; display: flex; }
.message-bubble.admin { justify-content: flex-end; }
.message-bubble.customer { justify-content: flex-start; }
.bubble { max-width: 70%; padding: 10px 14px; border-radius: 18px; font-size: 14px; line-height: 1.4; word-break: break-word; overflow-wrap: anywhere; }
.bubble.admin { background: #1e40af; color: white; margin-left: auto; }
.bubble.customer { background: #f1f5f9; color: #1e293b; margin-right: auto; }
.chat-input { background: #f8fafc; padding: 12px; border-radius: 0 0 8px 8px; border: 1px solid #e5e7eb; border-top: none; }
.new-message-section { background: #3b82f6; color: white; padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.conversation-card { background: white; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
.conversation-header { background: #f8fafc; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; border-radius: 8px 8px 0 0; }
.unread-badge { background: #ef4444; color: white; font-size: 11px; padding: 2px 6px; border-radius: 10px; margin-left: 8px; }
/* Responsive adjustments for chat layout */
@media (max-width: 860px){
  .admin-chat-container{ padding: 12px }
  .chat-body{ max-height: 60vh }
  .bubble{ max-width: 85%; font-size: 13px }
  .new-message-section form{ flex-direction: column; align-items: stretch }
  .new-message-section select,
  .new-message-section input,
  .new-message-section button{ width: 100% }
}
</style>

<style>
/* Enhanced sections: 60-30-10 palette and larger type */
.msg-card { background: linear-gradient(135deg,#0a2747,#0d325a); border:1px solid rgba(255,255,255,.08); }
.msg-card .title { color:#dbeafe; font-weight:800; font-size:18px; margin:0 0 10px 0; }
.msg-card .table { width:100%; border-collapse:separate; border-spacing:0 8px; font-size:15px; }
.msg-card thead th { color:#e2e8f0; font-weight:700; text-transform:uppercase; letter-spacing:.3px; background:rgba(56,189,248,.12); padding:12px 14px; border-top-left-radius:10px; border-top-right-radius:10px; }
.msg-card tbody td { background:rgba(255,255,255,.06); color:#e5effa; padding:12px 14px; border-top:1px solid rgba(255,255,255,.08); border-bottom:1px solid rgba(255,255,255,.08); }
.msg-card tbody tr.msg-row { box-shadow: 0 4px 10px -4px rgba(0,0,0,.35); }
.msg-card tbody tr.msg-row td:first-child { border-left:1px solid rgba(255,255,255,.08); border-top-left-radius:10px; border-bottom-left-radius:10px; }
.msg-card tbody tr.msg-row td:last-child { border-right:1px solid rgba(255,255,255,.08); border-top-right-radius:10px; border-bottom-right-radius:10px; }
.msg-card tbody tr.msg-row:hover td { background:linear-gradient(135deg, rgba(2,71,135,.35), rgba(4,117,210,.35)); }
.msg-card .accent { color:#38bdf8; font-weight:800; }
@media (max-width: 780px){ .msg-card .table{ border-spacing:0; } }
/* Convert .rtable into card rows on small screens */
@media (max-width: 760px){
  .rtable thead{ display:none }
  .rtable tbody{ display:flex; flex-direction:column; gap:10px }
  .rtable tr{ display:grid; grid-template-columns: 1fr; gap:6px; padding:10px; border-radius:10px; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1) }
  .rtable td{ display:flex; justify-content:space-between; align-items:center; gap:10px; border:0 }
  .rtable td::before{ content: attr(data-label); font-weight:800; color:#c7e1ff }
}
</style>

<div id="chatbot-messages" class="admin-chat-container">
  <div class="new-message-section">
    <h3 style="margin: 0 0 12px 0; font-size: 16px;">📩 Send Message to Customer</h3>
    <form method="post" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
      <select name="customer_id" required style="padding: 8px 12px; border: none; border-radius: 6px; background: white; color: #1e293b; min-width: 200px;" onchange="location.href='<?= BASE_URL ?>/admin/admin_messages.php?user_id='+this.value">
        <option value="">Select Customer...</option>
        <?php foreach ($customers as $customer): ?>
          <option value="<?= (int)$customer['id']; ?>" <?= $selectedUserId==(int)$customer['id']?'selected':''; ?>><?= h($customer['name']); ?> (<?= h($customer['email']); ?>)</option>
        <?php endforeach; ?>
      </select>
      <input type="text" name="new_message" placeholder="Type your message..." required style="flex: 1; min-width: 250px; padding: 8px 12px; border: none; border-radius: 6px;">
      <button type="submit" name="send_to_customer" value="1" style="padding: 8px 16px; background: #10b981; color: white; border: none; border-radius: 6px; font-weight: 500; cursor: pointer;">Send Message</button>
    </form>
  </div>

  <h3 style="color: #1e40af; margin-bottom: 16px; font-size: 18px;">💬 Active Conversations</h3>
  <?php if (!$chatbotConversations): ?>
    <p style="color: #64748b; font-style: italic; text-align: center; padding: 20px;">No conversations yet.</p>
  <?php else: ?>
    <?php foreach ($chatbotConversations as $conv): ?>
      <?php
        // Get all messages for this conversation
        try {
          if ($conv['user_id'] > 0) {
            $stmt = db()->prepare("SELECT id, message, from_admin, created_at FROM order_messages WHERE order_id = 0 AND user_id = ? ORDER BY created_at ASC");
            $stmt->execute([$conv['user_id']]);
          } else {
            $stmt = db()->prepare("SELECT id, message, from_admin, created_at FROM order_messages WHERE order_id = 0 AND customer_name = ? ORDER BY created_at ASC");
            $stmt->execute([$conv['customer_name']]);
          }
          $messages = $stmt->fetchAll();
        } catch (Exception $e) { $messages = []; }
        // Skip entirely if last message is a close marker
        if ($messages) {
          $lastMsg = end($messages);
          if (strpos($lastMsg['message'], '[Chat closed by admin]') !== false) { continue; }
        }
  $convKey = $conv['user_id'] > 0 ? ('u'.$conv['user_id']) : ('g'.md5($conv['customer_name']));
      ?>
      
      <div class="conversation-card" data-conv-key="<?= h($convKey); ?>">
        <div class="conversation-header">
          <div style="display: flex; justify-content: space-between; align-items: center; gap:12px;">
            <div style="font-weight: 600; color: #1e293b;">
              👤 <?= h($conv['customer_name']); ?>
              <?php if ($conv['user_id'] > 0): ?>
                <span style="color: #64748b; font-size: 12px; font-weight: normal;">(ID: <?= (int)$conv['user_id']; ?>)</span>
              <?php endif; ?>
              <?php $uc = (int)($conv['unread_count'] ?? 0); if ($uc > 0): ?>
                <span class="unread-badge"><?= $uc; ?> new</span>
              <?php endif; ?>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
              <div style="color: #64748b; font-size: 12px;">📅 <?= h(date('M d, H:i', strtotime($conv['last_activity']))); ?></div>
              <form method="post" class="exit-form" style="margin:0">
                <input type="hidden" name="user_id" value="<?= (int)$conv['user_id']; ?>">
                <button type="submit" name="exit_chat" value="1" class="exit-btn" title="Close conversation" aria-label="Close conversation" onclick="return confirm('Close this chat?')"
                  style="border:none;background:#fee2e2;color:#dc2626;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-weight:700;">×</button>
              </form>
            </div>
          </div>
        </div>
        
        <div class="chat-body" data-messages>
          <?php foreach ($messages as $msg): ?>
            <?php if (strpos($msg['message'], '[Chat closed by admin]') !== false) continue; ?>
            <div class="message-bubble <?= $msg['from_admin'] ? 'admin' : 'customer' ?>" data-msg-id="<?= (int)$msg['id']; ?>">
              <div class="bubble <?= $msg['from_admin'] ? 'admin' : 'customer' ?>">
                <div style="margin-bottom: 4px; word-break:break-word;"><?= h($msg['message']); ?></div>
                <div style="font-size: 10px; opacity: 0.7; display:flex; gap:4px; align-items:center;">
                  <span><?= $msg['from_admin'] ? '👨‍💼 Admin' : '👤 Customer'; ?></span>
                  <span>•</span>
                  <span><?= h(date('H:i', strtotime($msg['created_at']))); ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="chat-input">
          <form method="post" class="reply-form" data-conv-form style="display: flex; gap: 8px; align-items: center;">
            <input type="hidden" name="user_id" value="<?= (int)$conv['user_id']; ?>">
            <input type="text" name="reply_message" placeholder="Type your reply..." style="flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 20px; outline: none;" required>
            <button type="submit" style="padding: 8px 16px; background: #1e40af; color: white; border: none; border-radius: 20px; cursor: pointer; font-weight: 500;">Send</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<div class="card msg-card">
  <div class="title">Message Seller <span class="accent">(Checkout / Cart)</span></div>
  <div style="overflow:auto">
    <table class="table rtable">
      <thead><tr><th>ID</th><th>Customer</th><th>Message</th><th>Date</th></tr></thead>
      <tbody id="orders-msg-rows">
    <?php foreach ($orders as $r): ?>
      <tr class="msg-row">
        <td data-label="ID">#<?= (int)$r['order_id']; ?></td>
        <td data-label="Customer"><?= h($r['customer_name']); ?></td>
        <td data-label="Message" title="<?= h($r['note'] ?? ''); ?>"><?= h($r['note'] ?? ''); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($r['msg_time'] ?? ($r['created_at'] ?? 'now')))); ?></td>
      </tr>
    <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card msg-card" style="margin-top:16px">
  <div class="title">Service Requests <span class="accent">(Descriptions)</span></div>
  <div style="overflow:auto"><table class="table rtable"><thead><tr><th>ID</th><th>Customer</th><th>Description</th><th>Date</th></tr></thead><tbody id="service-rows">
    <?php foreach ($services as $s): ?>
      <tr class="msg-row">
        <td data-label="ID">#<?= (int)$s['id']; ?></td>
        <td data-label="Customer"><?= h($s['customer_name']); ?></td>
        <td data-label="Description" title="<?= h($s['description']); ?>"><?= h($s['description']); ?></td>
        <td data-label="Date"><?= h(date('M d, Y H:i', strtotime($s['created_at']))); ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</div>
<script>
// --- Real-time Admin Chat Polling ---
(function(){
  const POLL_MS = 3000;
  let lastId = 0;
  // Initialize lastId from existing DOM
  document.querySelectorAll('[data-msg-id]').forEach(el=>{ const id = parseInt(el.getAttribute('data-msg-id'))||0; if(id>lastId) lastId=id; });
  function esc(s){ return (s||'').replace(/[&<>]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])); }
  function convKey(row){ return row.user_id && Number(row.user_id)>0 ? 'u'+row.user_id : 'g'+(row.customer_name? btoa(unescape(encodeURIComponent(row.customer_name))).slice(0,10):'anon'); }
  function ensureCard(row){
    const key = convKey(row);
    let card = document.querySelector('.conversation-card[data-conv-key="'+key+'"]');
    if(card) return card;
    // Build minimal new card
    const wrap = document.createElement('div');
    wrap.className='conversation-card';
    wrap.setAttribute('data-conv-key', key);
    wrap.innerHTML = '<div class="conversation-header">'
      +  '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px">'
      +    '<div style="font-weight:600;color:#1e293b">👤 '+esc(row.customer_name||'Anonymous')+'</div>'
      +    '<div style="display:flex;align-items:center;gap:10px">'
      +      '<div style="color:#64748b;font-size:12px">Just now</div>'
      +      '<form method="post" class="exit-form" style="margin:0">'
      +        '<input type="hidden" name="user_id" value="'+(row.user_id||0)+'">'
      +        '<button type="submit" name="exit_chat" value="1" class="exit-btn" title="Close conversation" aria-label="Close conversation" style="border:none;background:#fee2e2;color:#dc2626;width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;font-weight:700">×</button>'
      +      '</form>'
      +    '</div>'
      +  '</div>'
      +'</div>'
      +'<div class="chat-body" data-messages></div>'
      +'<div class="chat-input"><form method="post" class="reply-form" data-conv-form style="display:flex;gap:8px;align-items:center">'
      +'<input type="hidden" name="user_id" value="'+(row.user_id||0)+'">'
      +'<input type="text" name="reply_message" placeholder="Type your reply..." style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:20px;outline:none" required>'
      +'<button type="submit" style="padding:8px 16px;background:#1e40af;color:#fff;border:none;border-radius:20px;cursor:pointer;font-weight:500">Send</button>'
      +'</form></div>';
    document.querySelector('#chatbot-messages').appendChild(wrap);
    bindForm(wrap.querySelector('form'));
    bindExit(wrap.querySelector('.exit-form'));
    return wrap;
  }
  function appendMessage(row){
    if(row.message && row.message.indexOf('[Chat closed by admin]')!==-1){ // remove conversation
      const key = convKey(row); const card=document.querySelector('.conversation-card[data-conv-key="'+key+'"]'); if(card) card.remove(); return; }
    const key=convKey(row); const card=ensureCard(row); const body=card.querySelector('[data-messages]');
    if(body.querySelector('[data-msg-id="'+row.id+'"]')) return; // already
    const div=document.createElement('div');
    div.className='message-bubble '+(row.from_admin==1?'admin':'customer');
    div.setAttribute('data-msg-id', row.id);
    div.innerHTML='<div class="bubble '+(row.from_admin==1?'admin':'customer')+'">'
      +'<div style="margin-bottom:4px;word-break:break-word">'+esc(row.message)+'</div>'
      +'<div style="font-size:10px;opacity:.7;display:flex;gap:4px;align-items:center"><span>'+(row.from_admin==1?'👨‍💼 Admin':'👤 Customer')+'</span><span>•</span><span>'+new Date(row.created_at).toLocaleTimeString([], {hour:"2-digit", minute:"2-digit"})+'</span></div>'
      +'</div>';
    body.appendChild(div); body.scrollTop=body.scrollHeight;
  }
  function poll(){
    var sel = '<?= (int)$selectedUserId ?>';
    var url = window.location.pathname+'?poll=1&since='+lastId+(sel>0?'&user_id='+sel:'');
    fetch(url, {credentials:'same-origin'}).then(r=>r.json()).then(data=>{
      if(!data||!data.ok) return; (data.messages||[]).forEach(m=>{ appendMessage(m); if(m.id>lastId) lastId=m.id; });
    }).catch(()=>{});
  }
  // Intercept reply forms for AJAX send
  function bindForm(f){ if(!f||f.__bound) return; f.__bound=true; f.addEventListener('submit', function(e){ e.preventDefault(); const fd=new FormData(f); fetch(window.location.pathname,{method:'POST',body:fd}).then(()=>{ poll(); const inp=f.querySelector('input[name=reply_message]'); if(inp) inp.value=''; }); }); }
  function bindExit(form){ if(!form||form.__b) return; form.__b=true; form.addEventListener('submit', function(e){ e.preventDefault(); const fd=new FormData(form); fetch(window.location.pathname,{method:'POST',body:fd}).then(()=>{ const card=form.closest('.conversation-card'); if(card) card.remove(); }); }); }
  document.querySelectorAll('form[data-conv-form]').forEach(bindForm);
  document.querySelectorAll('.exit-form').forEach(bindExit);
  setInterval(poll, POLL_MS);
})();
</script>

<script>
// --- Realtime refresh for Orders/Services sections ---
(function(){
  const MS = 5000;
  function refresh(){
    fetch(window.location.pathname+'?poll_list=1', {credentials:'same-origin'}).then(r=>r.json()).then(j=>{
      if(!j||!j.ok) return;
      var o=document.getElementById('orders-msg-rows'); if(o && typeof j.orders_html==='string') o.innerHTML=j.orders_html;
      var s=document.getElementById('service-rows'); if(s && typeof j.services_html==='string') s.innerHTML=j.services_html;
    }).catch(()=>{});
  }
  setInterval(refresh, MS);
})();
</script>
<?php admin_layout_end(); ?>
