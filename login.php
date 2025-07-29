<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'forgot_password') {
        // Handle forgot password
        $username = trim($_POST['forgot_username']);
        
        if (empty($username)) {
            $error = 'Username harus diisi!';
        } else {
            try {
                // Get user data with join to pendataan table
                $stmt = $pdo->prepare("
                    SELECT u.id, u.username, p.nama, p.no_hp 
                    FROM users u 
                    LEFT JOIN pendataan p ON u.id = p.user_id 
                    WHERE u.username = ? AND u.status_pengguna = 'Aktif'
                ");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && !empty($user['no_hp'])) {
                    // Generate new password
                    $new_password = 'PASS' . rand(1000, 9999);
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password in database
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $update_stmt->execute([$hashed_password, $user['id']]);
                    
                    // Prepare WhatsApp message
                    $phone = $user['no_hp'];
                    // Remove leading zero and add country code if needed
                    if (substr($phone, 0, 1) === '0') {
                        $phone = '62' . substr($phone, 1);
                    }
                    
                    // Use nama from pendataan table, fallback to username if nama is empty
                    $nama_display = !empty($user['nama']) ? $user['nama'] : $user['username'];
                    
                    $message = "Halo " . $nama_display . ",\n\n";
                    $message .= "Password baru untuk akun Anda:\n";
                    $message .= "Username: " . $user['username'] . "\n";
                    $message .= "Password: " . $new_password . "\n\n";
                    $message .= "Silakan login dan ubah password Anda segera.\n\n";
                    $message .= "Terima kasih,\nSistem Pendataan Warga";
                    
                    // Create WhatsApp URL
                    $wa_url = "https://wa.me/" . $phone . "?text=" . urlencode($message);
                    
                    $success = "Password baru telah dibuat. Silakan cek WhatsApp Anda.";
                    echo "<script>window.open('" . $wa_url . "', '_blank');</script>";
                    
                } else {
                    $error = 'Username tidak ditemukan atau nomor HP belum terdaftar!';
                }
            } catch (PDOException $e) {
                // Log error untuk debugging
                error_log("Database error in forgot password: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
            }
        }
    } else {
        // Handle regular login
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        
        if (empty($username) || empty($password)) {
            $error = 'Username dan password harus diisi!';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, username, password, role, status_pengguna, data_lengkap FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if ($user['status_pengguna'] === 'Tidak Aktif') {
                        $error = 'Akun Anda telah dinonaktifkan!';
                    } else {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['data_lengkap'] = $user['data_lengkap'];
                        
                        // Redirect berdasarkan role dan status data
                        if ($user['role'] === 'admin') {
                            header('Location: admin/dashboard.php');
                        } else {
                            if ($user['data_lengkap']) {
                                header('Location: warga/dashboard.php');
                            } else {
                                header('Location: warga/data_awal.php');
                            }
                        }
                        exit;
                    }
                } else {
                    $error = 'Username atau password salah!';
                }
            } catch (PDOException $e) {
                error_log("Database error in login: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi nanti.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem Pendataan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 25px 45px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 420px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .login-icon i {
            font-size: 30px;
            color: white;
        }
        
        .login-header h1 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .login-header p {
            color: #718096;
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 16px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            background: #f7fafc;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input:focus + i {
            color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            box-shadow: 0 15px 35px rgba(56, 161, 105, 0.4);
        }
        
        .error {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #c53030;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #fbb6ce;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }
        
        .success {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #2f855a;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #68d391;
            font-weight: 500;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .register-link {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        
        .register-link p {
            color: #718096;
            font-size: 14px;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .forgot-password-link {
            text-align: center;
            margin: 20px 0;
        }
        
        .forgot-password-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        
        .forgot-password-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 25px 45px rgba(0,0,0,0.2);
            position: relative;
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .modal-header h2 {
            color: #2d3748;
            margin-bottom: 10px;
        }
        
        .modal-header p {
            color: #718096;
            font-size: 14px;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            font-weight: bold;
            color: #a0aec0;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: #2d3748;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .login-header h1 {
                font-size: 24px;
            }
            
            .login-icon {
                width: 60px;
                height: 60px;
            }
            
            .login-icon i {
                font-size: 24px;
            }
            
            .modal-content {
                margin: 20% auto;
                padding: 25px;
            }
        }
        
        /* Loading animation untuk button */
        .btn.loading {
            pointer-events: none;
        }
        
        .btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: translate(-50%, -50%) rotate(0deg); }
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-icon">
                <i class="fas fa-users"></i>
            </div>
            <h1>Selamat Datang</h1>
            <p>Sistem Pendataan Warga</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input type="text" id="username" name="username" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <i class="fas fa-user"></i>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input type="password" id="password" name="password" required>
                    <i class="fas fa-lock"></i>
                </div>
            </div>
            
            <button type="submit" class="btn" id="loginBtn">
                <span class="btn-text">Masuk</span>
            </button>
        </form>
        
        <div class="forgot-password-link">
            <a href="#" id="forgotPasswordLink">
                <i class="fab fa-whatsapp"></i> Lupa Password? Kirim ke WhatsApp
            </a>
        </div>
        
        <div class="register-link">
            <p>Belum punya akun? <a href="regist.php">Daftar di sini</a></p>
        </div>
    </div>

    <!-- Modal Lupa Password -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <div class="modal-header">
                <h2><i class="fab fa-whatsapp" style="color: #25D366;"></i> Lupa Password</h2>
                <p>Masukkan username Anda. Password baru akan dikirim ke WhatsApp yang terdaftar.</p>
            </div>
            
            <form method="POST" id="forgotPasswordForm">
                <input type="hidden" name="action" value="forgot_password">
                <div class="form-group">
                    <label for="forgot_username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="forgot_username" name="forgot_username" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-secondary" id="forgotPasswordBtn">
                    <span class="btn-text">
                        <i class="fab fa-whatsapp"></i> Kirim ke WhatsApp
                    </span>
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const modal = document.getElementById('forgotPasswordModal');
        const forgotLink = document.getElementById('forgotPasswordLink');
        const closeBtn = document.getElementsByClassName('close')[0];

        forgotLink.onclick = function(e) {
            e.preventDefault();
            modal.style.display = 'block';
            document.getElementById('forgot_username').focus();
        }

        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });

        // Add loading animation on form submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.querySelector('.btn-text').textContent = 'Memproses...';
        });
        
        document.getElementById('forgotPasswordForm').addEventListener('submit', function() {
            const btn = document.getElementById('forgotPasswordBtn');
            btn.classList.add('loading');
            btn.querySelector('.btn-text').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
        });
        
        // Auto focus pada input username jika tidak ada error/success message
        <?php if (!$error && !$success): ?>
        document.getElementById('username').focus();
        <?php endif; ?>

        // Auto close success message after 5 seconds
        <?php if ($success): ?>
        setTimeout(function() {
            const successDiv = document.querySelector('.success');
            if (successDiv) {
                successDiv.style.opacity = '0';
                successDiv.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    successDiv.remove();
                }, 500);
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
