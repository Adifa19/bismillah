<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$admin_id = $_SESSION['user_id'];
$admin_username = $_SESSION['username'] ?? 'Admin';

// Handle sending reply
if ($_POST && isset($_POST['message']) && isset($_POST['reply_to_user']) && !empty(trim($_POST['message']))) {
    $message = trim($_POST['message']);
    $reply_to_user = (int)$_POST['reply_to_user'];
    
    $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, username, message, is_admin) VALUES (?, ?, ?, 1)");
    $stmt->execute([$reply_to_user, $admin_username, $message]);
    
    // Update admin online status
    $stmt = $pdo->prepare("INSERT INTO chat_online_users (user_id, username, is_admin) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE last_activity = CURRENT_TIMESTAMP");
    $stmt->execute([$admin_id, $admin_username]);
    
    header('Location: livechat.php?user=' . $reply_to_user);
    exit;
}

// Mark messages as read when admin views a conversation
if (isset($_GET['user'])) {
    $user_id = (int)$_GET['user'];
    $stmt = $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE user_id = ? AND is_admin = 0 AND is_read = 0");
    $stmt->execute([$user_id]);
}

// Get list of users who have sent messages with unread count
$stmt = $pdo->query("
    SELECT DISTINCT cm.user_id, cm.username, 
           MAX(cm.created_at) as last_message_time,
           (SELECT message FROM chat_messages WHERE user_id = cm.user_id ORDER BY created_at DESC LIMIT 1) as last_message,
           (SELECT is_admin FROM chat_messages WHERE user_id = cm.user_id ORDER BY created_at DESC LIMIT 1) as last_message_is_admin,
           (SELECT COUNT(*) FROM chat_messages WHERE user_id = cm.user_id AND is_admin = 0 AND is_read = 0) as unread_count
    FROM chat_messages cm 
    WHERE is_admin = 0 
    GROUP BY cm.user_id, cm.username 
    ORDER BY unread_count DESC, last_message_time DESC
");
$users = $stmt->fetchAll();

// Get total unread messages count
$stmt = $pdo->query("SELECT COUNT(*) as total_unread FROM chat_messages WHERE is_admin = 0 AND is_read = 0");
$total_unread = $stmt->fetch()['total_unread'];

// Get selected user for chat
$selected_user_id = isset($_GET['user']) ? (int)$_GET['user'] : (count($users) > 0 ? $users[0]['user_id'] : null);
$selected_user = null;
$messages = [];

if ($selected_user_id) {
    // Get selected user info
    foreach ($users as $user) {
        if ($user['user_id'] == $selected_user_id) {
            $selected_user = $user;
            break;
        }
    }
    
    // Get messages for selected user
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$selected_user_id]);
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Dashboard <?= $total_unread > 0 ? '(' . $total_unread . ')' : '' ?></title>
    
    <!-- Favicon dengan notifikasi -->
    <link rel="icon" type="image/png" id="favicon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'><?= $total_unread > 0 ? '🔴' : '💬' ?></text></svg>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            overflow: hidden;
        }
        
        .chat-container {
            display: flex;
            height: 100vh;
            backdrop-filter: blur(10px);
        }
        
        /* Sidebar */
        .sidebar {
            width: 340px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(102, 126, 234, 0.1);
            display: flex;
            flex-direction: column;
            box-shadow: 0 0 30px rgba(102, 126, 234, 0.1);
        }
        
        .sidebar-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 24px;
            position: relative;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .sidebar-header h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header .subtitle {
            font-size: 13px;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .total-unread-badge {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            font-size: 11px;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            0% { 
                box-shadow: 0 0 5px rgba(255, 255, 255, 0.3);
                transform: scale(1);
            }
            100% { 
                box-shadow: 0 0 15px rgba(255, 255, 255, 0.5);
                transform: scale(1.05);
            }
        }
        
        .logout-btn {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 10px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-50%) translateX(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .user-list {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        .user-item {
            padding: 18px 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            margin: 4px 16px;
            border-radius: 16px;
        }
        
        .user-item:hover {
            background: rgba(102, 126, 234, 0.08);
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }
        
        .user-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border: 1px solid rgba(102, 126, 234, 0.2);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.15);
        }
        
        .user-item.has-unread {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-left: 4px solid #667eea;
        }
        
        .user-item.has-unread.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.12), rgba(118, 75, 162, 0.12));
        }
        
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .user-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
        }
        
        .last-message {
            font-size: 13px;
            color: #718096;
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            line-height: 1.4;
        }
        
        .user-item.has-unread .last-message {
            font-weight: 600;
            color: #4a5568;
        }
        
        .message-time {
            font-size: 11px;
            color: #a0aec0;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }
        
        .admin-indicator {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 9px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .unread-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 11px;
            padding: 4px 8px;
            border-radius: 16px;
            font-weight: 700;
            min-width: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); box-shadow: 0 4px 16px rgba(102, 126, 234, 0.6); }
            100% { transform: scale(1); }
        }
        
        .new-message-indicator {
            position: absolute;
            left: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            background: #667eea;
            border-radius: 50%;
            animation: blink 1.5s infinite;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.6);
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        /* Main Chat Area */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
        }
        
        .chat-header {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            padding: 24px;
            border-bottom: 1px solid rgba(102, 126, 234, 0.1);
            box-shadow: 0 2px 20px rgba(102, 126, 234, 0.08);
        }
        
        .chat-user-info {
            display: flex;
            align-items: center;
        }
        
        .chat-user-avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            margin-right: 16px;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .chat-user-name {
            font-size: 20px;
            font-weight: 600;
            color: #2d3748;
        }
        
        .chat-messages {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02), rgba(118, 75, 162, 0.02));
        }
        
        .message {
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.user {
            justify-content: flex-start;
        }
        
        .message.admin {
            justify-content: flex-end;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 16px 20px;
            border-radius: 24px;
            word-wrap: break-word;
            position: relative;
            font-size: 14px;
            line-height: 1.5;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        
        .message.user .message-bubble {
            background: rgba(102, 126, 234, 0.08);
            color: #2d3748;
            border-bottom-left-radius: 8px;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .message.admin .message-bubble {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 8px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .message-info {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 6px;
            font-weight: 500;
        }
        
        .message.user .message-info {
            text-align: left;
            color: #718096;
        }
        
        .message.admin .message-info {
            text-align: right;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .user-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 9px;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .chat-input {
            padding: 24px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border-top: 1px solid rgba(102, 126, 234, 0.1);
        }
        
        .input-group {
            display: flex;
            gap: 16px;
            align-items: flex-end;
        }
        
        .input-wrapper {
            flex: 1;
            position: relative;
        }
        
        .message-input {
            width: 100%;
            padding: 16px 20px;
            border: 2px solid rgba(102, 126, 234, 0.1);
            border-radius: 24px;
            outline: none;
            font-size: 14px;
            resize: none;
            min-height: 52px;
            max-height: 120px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .message-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 24px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            min-width: 80px;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
        }
        
        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .send-btn:active {
            transform: translateY(0);
        }
        
        .empty-state {
            text-align: center;
            color: #718096;
            padding: 60px 40px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .empty-state .icon {
            font-size: 64px;
            margin-bottom: 24px;
            opacity: 0.6;
        }
        
        .empty-state h3 {
            font-size: 24px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            font-size: 16px;
            line-height: 1.6;
            max-width: 400px;
        }
        
        .notification-highlight {
            margin-top: 24px;
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 16px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            color: #667eea;
            font-weight: 600;
        }
        
        /* Notification Toast */
        .notification-toast {
            position: fixed;
            top: 24px;
            right: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 16px 24px;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-weight: 500;
        }
        
        .notification-toast.show {
            transform: translateX(0);
        }
        
        .notification-toast.error {
            background: linear-gradient(135deg, #e53e3e, #c53030);
        }
        
        /* Scrollbar */
        .user-list::-webkit-scrollbar,
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .user-list::-webkit-scrollbar-track,
        .chat-messages::-webkit-scrollbar-track {
            background: rgba(102, 126, 234, 0.05);
            border-radius: 3px;
        }
        
        .user-list::-webkit-scrollbar-thumb,
        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(102, 126, 234, 0.3);
            border-radius: 3px;
        }
        
        .user-list::-webkit-scrollbar-thumb:hover,
        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: rgba(102, 126, 234, 0.5);
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 300px;
            }
            
            .sidebar-header {
                padding: 20px;
            }
            
            .user-item {
                padding: 16px 20px;
                margin: 4px 12px;
            }
            
            .chat-header,
            .chat-input {
                padding: 20px;
            }
            
            .chat-messages {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>
                    🛡️ Admin Chat
                    <?php if ($total_unread > 0): ?>
                        <span class="total-unread-badge"><?= $total_unread ?> baru</span>
                    <?php endif; ?>
                </h2>
                <div class="subtitle">Kelola percakapan warga</div>
                <a href="dashboard.php" class="logout-btn">Keluar</a>
            </div>
            
            <div class="user-list">
                <?php if (empty($users)): ?>
                    <div style="padding: 40px 20px; text-align: center; color: #718096;">
                        <div class="icon">💬</div>
                        <h3 style="color: #2d3748; margin-bottom: 8px;">Belum ada pesan</h3>
                        <p>Belum ada pesan dari warga</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <a href="?user=<?= $user['user_id'] ?>" 
                           class="user-item <?= $selected_user_id == $user['user_id'] ? 'active' : '' ?> <?= $user['unread_count'] > 0 ? 'has-unread' : '' ?>">
                            
                            <?php if ($user['unread_count'] > 0): ?>
                                <div class="new-message-indicator"></div>
                            <?php endif; ?>
                            
                            <div class="user-info">
                                <div>
                                    <div class="user-name">
                                        <?= sanitize($user['username']) ?>
                                        <?php if (!$user['last_message_is_admin']): ?>
                                            <span class="user-badge">USER</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="last-message">
                                        <?php if ($user['last_message_is_admin']): ?>
                                            <span class="admin-indicator">Anda</span>
                                        <?php endif; ?>
                                        <?= sanitize(substr($user['last_message'], 0, 50)) ?><?= strlen($user['last_message']) > 50 ? '...' : '' ?>
                                    </div>
                                </div>
                                <div class="message-time">
                                    <?php if ($user['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?= $user['unread_count'] ?></span>
                                    <?php endif; ?>
                                    <span><?= date('H:i', strtotime($user['last_message_time'])) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Main Chat Area -->
        <div class="chat-main">
            <?php if ($selected_user): ?>
                <div class="chat-header">
                    <div class="chat-user-info">
                        <div class="chat-user-avatar">
                            <?= strtoupper(substr($selected_user['username'], 0, 1)) ?>
                        </div>
                        <div class="chat-user-name">
                            <?= sanitize($selected_user['username']) ?>
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <div class="icon">💬</div>
                            <h3>Belum ada percakapan</h3>
                            <p>Belum ada percakapan dengan user ini</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['is_admin'] ? 'admin' : 'user' ?>">
                                <div class="message-bubble">
                                    <?= sanitize($msg['message']) ?>
                                    <div class="message-info">
                                        <?= date('H:i', strtotime($msg['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-input">
                    <form method="POST" class="input-group">
                        <input type="hidden" name="reply_to_user" value="<?= $selected_user_id ?>">
                        <div class="input-wrapper">
                            <textarea 
                                name="message" 
                                class="message-input" 
                                placeholder="Balas pesan..." 
                                required
                                maxlength="500"
                                autocomplete="off"
                                rows="1"
                            ></textarea>
                        </div>
                        <button type="submit" class="send-btn">Kirim</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">💬</div>
                    <h3>Selamat datang di Admin Chat</h3>
                    <p>Pilih pengguna dari sidebar untuk memulai percakapan</p>
                    <?php if ($total_unread > 0): ?>
                        <div class="notification-highlight">
                            <strong>🔔 Ada <?= $total_unread ?> pesan belum dibaca!</strong>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification Toast -->
    <div id="notificationToast" class="notification-toast">
        <span id="notificationMessage"></span>
    </div>

    <script>
        // Variables for tracking
        let lastUnreadCount = <?= $total_unread ?>;
        let currentUserId = <?= $selected_user_id ?: 'null' ?>;
        let notificationSound = null;
        
        // Create notification sound
        try {
            notificationSound = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjir1/LLeSsFLoHQ8diJNQgaaLvt559NEAwYaLvt559NEAwYaLvt559NEAwYaLvt559NEAwYaLvt559NEAwYaLvt559NEAwYaLvt559NEAw=');
            notificationSound.volume = 0.3;
        } catch (e) {
            console.log('Audio not supported');
        }
        
        // Auto-scroll to bottom
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }
        
        // Show notification toast
        function showNotification(message, type = 'success') {
            const toast = document.getElementById('notificationToast');
            const messageEl = document.getElementById('notificationMessage');
            
            messageEl.textContent = message;
            toast.className = `notification-toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }
        
        // Play notification sound
        function playNotificationSound() {
            if (notificationSound && document.visibilityState !== 'visible') {
                notificationSound.play().catch(e => console.log('Cannot play sound:', e));
            }
        }
        
        // Update page title with unread count
        function updatePageTitle(unreadCount) {
            const baseTitle = 'Admin Chat Dashboard';
            document.title = unreadCount > 0 ? `${baseTitle} (${unreadCount})` : baseTitle;
            
            // Update favicon
            const favicon = document.getElementById('favicon');
            const iconEmoji = unreadCount > 0 ? '🔴' : '💬';
            favicon.href = `data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>${iconEmoji}</text></svg>`;
        }
        
        // Check for new messages
        async function checkForNewMessages() {
            try {
                const response = await fetch('check_unread.php');
                const data = await response.json();
                
                if (data.unread_count > lastUnreadCount) {
                    // New message received
                    const newMessages = data.unread_count - lastUnreadCount;
                    showNotification(`${newMessages} pesan baru diterima!`, 'success');
                    playNotificationSound();
                    
                    // Reload page to show new messages
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                }
                
                lastUnreadCount = data.unread_count;
                updatePageTitle(data.unread_count);
                
            } catch (error) {
                console.log('Error checking for new messages:', error);
            }
        }
        
        // Auto refresh every 10 seconds to check for new messages
        setInterval(checkForNewMessages, 10000);
        
        // Scroll to bottom on page load
        window.addEventListener('load', scrollToBottom);
        
        // Focus on input if exists
        const messageInput = document.querySelector('.message-input');
        if (messageInput) {
            messageInput.focus();
            
            // Auto-resize textarea
            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });
        }
        
        // Handle form submission
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
                setTimeout(scrollToBottom, 100);
            });
        }
        
        // Handle Enter key in textarea
        if (messageInput) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    if (this.value.trim()) {
                        form.submit();
                    }
                }
            });
        }
        
        // Initialize page title
        updatePageTitle(lastUnreadCount);
        
        // Show welcome notification if there are unread messages
        if (lastUnreadCount > 0) {
            setTimeout(() => {
                showNotification(`Ada ${lastUnreadCount} pesan belum dibaca!`, 'success');
            }, 1000);
        }
    </script>
</body>
</html>