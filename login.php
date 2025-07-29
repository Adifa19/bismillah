<?php
require_once 'config.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $no_kk = trim($_POST['no_kk']);
    
    if (empty($username) || empty($no_kk)) {
        $error = 'Username dan No. KK harus diisi!';
    } else {
        try {
            // Cari user berdasarkan username dan no_kk
            $stmt = $pdo->prepare("
                SELECT u.id, u.username 
                FROM users u 
                WHERE u.username = ? AND u.no_kk = ? AND u.status_pengguna = 'Aktif'
            ");
            $stmt->execute([$username, $no_kk]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate password baru (8 karakter random)
                $new_password = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 8);
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update password di database
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_result = $update_stmt->execute([$hashed_password, $user['id']]);
                
                if ($update_result) {
                    $success = "Password baru telah dibuat: <strong>$new_password</strong><br>
                              Silakan login dengan password baru ini dan segera ganti password Anda.";
                } else {
                    $error = 'Gagal mereset password. Silakan coba lagi!';
                }
            } else {
                $error = 'Username dan No. KK tidak cocok atau akun tidak aktif!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem!';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Sistem Pendataan</title>
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
        
        .forgot-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 25px 45px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 480px;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .forgot-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .forgot-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(245, 87, 108, 0.3);
        }
        
        .forgot-icon i {
            font-size: 30px;
            color: white;
        }
        
        .forgot-header h1 {
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 28px;
            font-weight: 600;
        }
        
        .forgot-header p {
            color: #718096;
            font-size: 16px;
            line-height: 1.5;
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
            border-color: #f5576c;
            background: white;
            box-shadow: 0 0 0 3px rgba(245, 87, 108, 0.1);
        }
        
        .form-group input:focus + i {
            color: #f5576c;
        }
        
        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(245, 87, 108, 0.4);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
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
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            border: 1px solid #68d391;
            font-weight: 500;
            line-height: 1.6;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .info-box {
            background: linear-gradient(135deg, #bee3f8 0%, #90cdf4 100%);
            color: #2c5282;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            border: 1px solid #63b3ed;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .info-box i {
            margin-right: 8px;
            color: #3182ce;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .forgot-container {
                padding: 30px 25px;
                margin: 10px;
            }
            
            .forgot-header h1 {
                font-size: 24px;
            }
            
            .forgot-icon {
                width: 60px;
                height: 60px;
            }
            
            .forgot-icon i {
                font-size: 24px;
            }
        }
        
        /* Loading animation */
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
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-icon">
                <i class="fas fa-key"></i>
            </div>
            <h1>Lupa Password</h1>
            <p>Masukkan username dan nomor KK Anda untuk mereset password</p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            Untuk keamanan, pastikan data yang Anda masukkan sesuai dengan yang terdaftar di sistem. Password baru akan ditampilkan setelah verifikasi berhasil.
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
            
            <a href="login.php" class="btn btn-secondary">
                <i class="fas fa-sign-in-alt"></i>
                Login Sekarang
            </a>
        <?php else: ?>
            <form method="POST" id="forgotForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" required 
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="no_kk">Nomor Kartu Keluarga</label>
                    <div class="input-wrapper">
                        <input type="text" id="no_kk" name="no_kk" required maxlength="16" 
                               placeholder="Masukkan 16 digit nomor KK"
                               value="<?php echo isset($_POST['no_kk']) ? htmlspecialchars($_POST['no_kk']) : ''; ?>">
                        <i class="fas fa-id-card"></i>
                    </div>
                </div>
                
                <button type="submit" class="btn" id="forgotBtn">
                    <span class="btn-text">Reset Password</span>
                </button>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i>
                Kembali ke Login
            </a>
        </div>
    </div>

    <script>
        // Add loading animation on form submit
        document.getElementById('forgotForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('forgotBtn');
            btn.classList.add('loading');
            btn.querySelector('.btn-text').textContent = 'Memproses...';
        });
        
        // Format nomor KK input
        document.getElementById('no_kk')?.addEventListener('input', function(e) {
            // Hanya izinkan angka
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Batasi maksimal 16 digit
            if (this.value.length > 16) {
                this.value = this.value.slice(0, 16);
            }
        });
        
        // Auto focus pada input username
        document.getElementById('username')?.focus();
        
        // Auto hide success message after 10 seconds and redirect
        <?php if ($success): ?>
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>
