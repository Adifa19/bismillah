<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $nik = trim($_POST['nik']);
    
    // Validasi input
    if (empty($username) || empty($password) || empty($confirm_password) || empty($nik)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($username) < 3) {
        $error = 'Username minimal 3 karakter!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Konfirmasi password tidak cocok!';
    } elseif (strlen($nik) !== 16 || !is_numeric($nik)) {
        $error = 'NIK harus 16 digit angka!';
    } else {
        try {
            // Cek apakah username sudah ada
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Username sudah digunakan!';
            } else {
                // Cek apakah NIK valid dan belum terdaftar sebagai user
                $stmt = $pdo->prepare("SELECT p.id, p.no_kk, p.is_registered, p.nama_lengkap, nk.jumlah_pengguna, nk.max_pengguna 
                                     FROM pendataan p 
                                     JOIN nomor_kk nk ON p.no_kk = nk.no_kk 
                                     WHERE p.nik = ? AND p.status_warga = 'Aktif' AND nk.status = 'Aktif'");
                $stmt->execute([$nik]);
                $pendataan_data = $stmt->fetch();
                
                if (!$pendataan_data) {
                    $error = 'NIK tidak terdaftar dalam sistem atau status tidak aktif!';
                } elseif ($pendataan_data['is_registered'] == 1) {
                    $error = 'NIK ini sudah terdaftar sebagai pengguna!';
                } elseif ($pendataan_data['jumlah_pengguna'] >= $pendataan_data['max_pengguna']) {
                    $error = 'Nomor KK untuk NIK ini sudah mencapai batas maksimal pengguna!';
                } else {
                    // Buat akun baru
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $pdo->beginTransaction();
                    
                    // Insert user baru
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, no_kk) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $hashed_password, $pendataan_data['no_kk']]);
                    
                    $user_id = $pdo->lastInsertId();
                    
                    // Update status is_registered di tabel pendataan
                    $stmt = $pdo->prepare("UPDATE pendataan SET is_registered = 1, user_id = ? WHERE nik = ?");
                    $stmt->execute([$user_id, $nik]);
                    
                    // Update jumlah pengguna di nomor_kk
                    $stmt = $pdo->prepare("UPDATE nomor_kk SET jumlah_pengguna = jumlah_pengguna + 1 WHERE no_kk = ?");
                    $stmt->execute([$pendataan_data['no_kk']]);
                    
                    $pdo->commit();
                    
                    $success = 'Akun berhasil dibuat untuk ' . $pendataan_data['nama_lengkap'] . '! Silakan login.';
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Sistem Pendataan</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #3730a3;
            --secondary-color: #f1f5f9;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --success-color: #10b981;
            --error-color: #ef4444;
            --border-color: #e2e8f0;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

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
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.05"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.05"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.03"/><circle cx="10" cy="50" r="0.5" fill="white" opacity="0.03"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: var(--shadow);
            width: 100%;
            max-width: 460px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .register-header .icon {
            width: 60px;
            height: 60px;
            background: var(--primary-color);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        .register-header .icon i {
            color: white;
            font-size: 24px;
        }

        .register-header h1 {
            color: var(--text-dark);
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .register-header p {
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .info-card {
            background: linear-gradient(135deg, #dbeafe 0%, #e0e7ff 100%);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .info-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 50%;
            transform: translate(20px, -20px);
        }

        .info-card .icon {
            color: var(--primary-color);
            margin-right: 0.5rem;
        }

        .info-card .content {
            color: #1e40af;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1rem;
            z-index: 1;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            transform: translateY(-1px);
        }

        .form-group input:focus + .input-wrapper i {
            color: var(--primary-color);
        }

        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        .alert.error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: var(--error-color);
            border-left: 4px solid var(--error-color);
        }

        .alert.success {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .login-link {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        .login-link p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }

        .login-link a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .requirements {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.75rem;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .requirements ul {
            margin: 0.25rem 0 0 1.25rem;
        }

        .requirements li {
            margin-bottom: 0.25rem;
        }

        @media (max-width: 480px) {
            .register-container {
                padding: 1.5rem;
                margin: 10px;
            }
            
            .register-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="icon">
                <i class="fas fa-user-plus"></i>
            </div>
            <h1>Buat Akun</h1>
            <p>Sistem Pendataan Warga</p>
        </div>

        <div class="info-card">
            <div class="content">
                <i class="fas fa-info-circle icon"></i>
                <strong>Informasi:</strong> Masukkan NIK yang sudah terdaftar dalam sistem untuk membuat akun pengguna.
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="nik">NIK (Nomor Induk Kependudukan)</label>
                <div class="input-wrapper">
                    <i class="fas fa-id-card"></i>
                    <input type="text" id="nik" name="nik" maxlength="16" required 
                           pattern="[0-9]{16}" title="NIK harus 16 digit angka"
                           placeholder="16 digit NIK Anda">
                </div>
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" minlength="3" maxlength="20" required
                           placeholder="Minimal 3 karakter">
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" minlength="6" required
                           placeholder="Minimal 6 karakter">
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Konfirmasi Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6" required
                           placeholder="Ulangi password">
                </div>
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
                Daftar Sekarang
            </button>
        </form>

        <div class="login-link">
            <p>Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
        </div>
    </div>

    <script>
        // Validasi konfirmasi password
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Password tidak cocok');
                this.style.borderColor = '#ef4444';
            } else {
                this.setCustomValidity('');
                this.style.borderColor = '#10b981';
            }
        });

        // Validasi NIK hanya angka
        document.getElementById('nik').addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length === 16) {
                this.style.borderColor = '#10b981';
            } else {
                this.style.borderColor = '#e2e8f0';
            }
        });

        // Validasi real-time untuk semua input
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#ef4444';
                }
            });
        });

        // Animasi loading pada submit
        document.querySelector('form').addEventListener('submit', function() {
            const btn = document.querySelector('.btn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin" style="margin-right: 0.5rem;"></i>Memproses...';
            btn.disabled = true;
        });
    </script>
</body>
</html>