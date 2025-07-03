<?php
require_once '../config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Handle sending message
if ($_POST && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $error = null;
    
    // Insert message if there's text
    if (!empty($message)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO chat_messages (user_id, username, message, is_admin, created_at) VALUES (?, ?, ?, 0, NOW())");
            $stmt->execute([$user_id, $username, $message]);
            
            // Update online status
            $stmt = $pdo->prepare("INSERT INTO chat_online_users (user_id, username, is_admin, last_activity) VALUES (?, ?, 0, NOW()) ON DUPLICATE KEY UPDATE last_activity = NOW()");
            $stmt->execute([$user_id, $username]);
            
            header('Location: livechat.php');
            exit;
        } catch (PDOException $e) {
            $error = "Gagal menyimpan pesan. Silakan coba lagi.";
        }
    } else {
        $error = "Pesan tidak boleh kosong.";
    }
}

// Get messages for this user only (private chat with admin)
try {
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$user_id]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
    $error = "Gagal memuat pesan.";
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat dengan Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .chat-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            width: 450px;
            height: 600px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .chat-header {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .chat-header h2 {
            margin: 0;
            font-size: 18px;
        }
        
        .chat-header .subtitle {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .logout-btn {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }
        
        .message.user {
            justify-content: flex-end;
        }
        
        .message.admin {
            justify-content: flex-start;
        }
        
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            word-wrap: break-word;
            word-break: break-word;
            position: relative;
        }
        
        .message.user .message-bubble {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-bottom-right-radius: 4px;
        }
        
        .message.admin .message-bubble {
            background: #e9ecef;
            color: #333;
            border-bottom-left-radius: 4px;
        }
        
        .message-content {
            margin-bottom: 5px;
        }
        
        .message-info {
            font-size: 10px;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .message.user .message-info {
            text-align: right;
            color: rgba(255,255,255,0.8);
        }
        
        .message.admin .message-info {
            text-align: left;
            color: #666;
        }
        
        .admin-badge {
            background: #dc3545;
            color: white;
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e9ecef;
        }
        
        .input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .message-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 12px;
            outline: none;
            font-size: 14px;
            resize: vertical;
            min-height: 44px;
            max-height: 120px;
            font-family: inherit;
        }
        
        .message-input:focus {
            border-color: #667eea;
        }
        
        .send-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: bold;
            transition: transform 0.2s;
            min-width: 80px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .send-btn:hover {
            transform: scale(1.05);
        }
        
        .send-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .empty-state {
            text-align: center;
            color: #666;
            padding: 40px 20px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ddd;
            margin-bottom: 15px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
            border: 1px solid #f5c6cb;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 10px;
            border: 1px solid #c3e6cb;
        }
        
        /* Loading indicator */
        .loading {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin-left: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Auto scroll */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        /* Mobile responsiveness */
        @media (max-width: 500px) {
            .chat-container {
                width: 95%;
                height: 95vh;
                margin: 2.5vh auto;
            }
            
            .message-bubble {
                max-width: 85%;
            }
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <div class="chat-header">
            <h2>💬 Chat dengan Admin</h2>
            <div class="subtitle">Halo, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</div>
            <a href="profile.php" class="logout-btn">Keluar</a>
        </div>
        
        <div class="chat-messages" id="chatMessages">
            <?php if (isset($error)): ?>
                <div class="error-message">❌ <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            
            <?php if (empty($messages)): ?>
                <div class="empty-state">
                    <div style="font-size: 48px; margin-bottom: 15px;">💬</div>
                    <p>Belum ada pesan. Mulai percakapan dengan admin!</p>
                    <p style="font-size: 12px; color: #999; margin-top: 10px;">
                        💡 Kirimkan pesan teks Anda di bawah ini
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message <?= $msg['is_admin'] ? 'admin' : 'user' ?>">
                        <div class="message-bubble">
                            <div class="message-content">
                                <?= nl2br(htmlspecialchars($msg['message'], ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                            
                            <div class="message-info">
                                <?php if ($msg['is_admin']): ?>
                                    <span class="admin-badge">ADMIN</span>
                                <?php endif; ?>
                                <?= date('H:i', strtotime($msg['created_at'])) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="chat-input">
            <form method="POST" class="input-group" id="chatForm">
                <textarea 
                    name="message" 
                    class="message-input" 
                    placeholder="Ketik pesan Anda... (Tekan Enter untuk kirim, Shift+Enter untuk baris baru)" 
                    maxlength="2000"
                    autocomplete="off"
                    id="messageInput"
                    rows="1"
                ></textarea>
                
                <button type="submit" class="send-btn" id="sendBtn">Kirim</button>
            </form>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom
        function scrollToBottom() {
            const chatMessages = document.getElementById('chatMessages');
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Scroll to bottom on page load
        window.addEventListener('load', scrollToBottom);
        
        // Auto refresh messages every 5 seconds
        let refreshInterval = setInterval(function() {
            if (document.activeElement !== document.getElementById('messageInput')) {
                location.reload();
            }
        }, 5000);
        
        // Focus on input
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.message-input').focus();
        });
        
        // Handle textarea auto-resize
        const messageInput = document.getElementById('messageInput');
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Handle form submission
        document.getElementById('chatForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default submission first
            
            const messageInput = document.getElementById('messageInput');
            const sendBtn = document.getElementById('sendBtn');
            const message = messageInput.value.trim();
            
            // Check if there's a message
            if (!message) {
                alert('Silakan ketik pesan untuk dikirim.');
                messageInput.focus();
                return false;
            }
            
            // Disable send button to prevent double submission
            sendBtn.disabled = true;
            sendBtn.innerHTML = 'Mengirim...';
            
            // Create form data
            const formData = new FormData();
            formData.append('message', message);
            
            // Send via fetch to avoid page reload issues
            fetch('livechat.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (response.ok) {
                    // Clear the input
                    messageInput.value = '';
                    messageInput.style.height = 'auto';
                    sessionStorage.removeItem('chat_draft');
                    
                    // Reload page to show new message
                    setTimeout(() => {
                        location.reload();
                    }, 100);
                } else {
                    throw new Error('Network response was not ok');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Gagal mengirim pesan. Silakan coba lagi.');
                
                // Re-enable button
                sendBtn.disabled = false;
                sendBtn.innerHTML = 'Kirim';
            });
        });
        
        // Enter key to send (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim()) {
                    document.getElementById('chatForm').dispatchEvent(new Event('submit'));
                }
            }
        });
        
        // Auto-save draft message
        let draftTimeout;
        messageInput.addEventListener('input', function() {
            clearTimeout(draftTimeout);
            draftTimeout = setTimeout(() => {
                if (this.value.trim()) {
                    sessionStorage.setItem('chat_draft', this.value);
                } else {
                    sessionStorage.removeItem('chat_draft');
                }
            }, 1000);
        });
        
        // Restore draft on page load
        window.addEventListener('load', function() {
            const draft = sessionStorage.getItem('chat_draft');
            if (draft) {
                messageInput.value = draft;
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
            }
        });
        
        // Add copy to clipboard functionality for text messages
        document.addEventListener('dblclick', function(e) {
            if (e.target.classList.contains('message-content')) {
                navigator.clipboard.writeText(e.target.textContent).then(function() {
                    // Show feedback
                    const original = e.target.style.background;
                    e.target.style.background = 'rgba(40, 167, 69, 0.2)';
                    setTimeout(() => {
                        e.target.style.background = original;
                    }, 500);
                }).catch(function() {
                    // Fallback for older browsers
                    const textArea = document.createElement('textarea');
                    textArea.value = e.target.textContent;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                });
            }
        });
        
        // Add connection status indicator
        function checkConnection() {
            let indicator = document.getElementById('connectionStatus');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'connectionStatus';
                indicator.style.cssText = `
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    padding: 5px 10px;
                    border-radius: 15px;
                    font-size: 12px;
                    z-index: 999;
                    transition: all 0.3s;
                `;
                document.body.appendChild(indicator);
            }
            
            if (navigator.onLine) {
                indicator.textContent = '🟢 Terhubung';
                indicator.style.background = '#d4edda';
                indicator.style.color = '#155724';
                indicator.style.opacity = '1';
                
                // Auto hide after 3 seconds if connected
                setTimeout(() => {
                    if (indicator && navigator.onLine) {
                        indicator.style.opacity = '0';
                        setTimeout(() => {
                            if (indicator && indicator.parentNode) {
                                indicator.remove();
                            }
                        }, 300);
                    }
                }, 3000);
            } else {
                indicator.textContent = '🔴 Tidak terhubung';
                indicator.style.background = '#f8d7da';
                indicator.style.color = '#721c24';
                indicator.style.opacity = '1';
            }
        }
        
        window.addEventListener('online', checkConnection);
        window.addEventListener('offline', checkConnection);
        
        // Initial connection check
        checkConnection();
        
        // Cleanup interval on page unload
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>