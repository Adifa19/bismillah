<?php
require_once '../config.php';

// Pastikan user sudah login dan memiliki role 'user'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../login.php");
    exit();
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];

    $user_id = $_SESSION['user_id'];

    // Ambil password lama dari database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
    $stmt->execute(['id' => $user_id]);
    $user = $stmt->fetch();

    if ($user && password_verify($old_password, $user['password'])) {
        $new_password_hashed = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password di database
        $update_stmt = $pdo->prepare("UPDATE users SET password = :new_password WHERE id = :id");
        $update_success = $update_stmt->execute([
            'new_password' => $new_password_hashed,
            'id' => $user_id
        ]);

        if ($update_success) {
            $message = 'Password berhasil diubah';
            $message_type = 'success';
        } else {
            $message = 'Terjadi kesalahan saat mengubah password';
            $message_type = 'error';
        }
    } else {
        $message = 'Password lama salah';
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ganti Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
    :root {
        --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        --error-gradient: linear-gradient(135deg, #ff6b6b 0%, #ffa8a8 100%);
        --card-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        --input-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    body {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        min-height: 100vh;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .main-container {
        padding: 2rem 0;
    }

    .password-card {
        background: white;
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        border: none;
        overflow: hidden;
        backdrop-filter: blur(10px);
    }

    .card-header {
        background: var(--primary-gradient);
        color: white;
        border: none;
        padding: 2rem;
        text-align: center;
        position: relative;
        z-index: 1;
    }

    .card-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Cdefs%3E%3Cpattern id='grain' width='100' height='100' patternUnits='userSpaceOnUse'%3E%3Ccircle cx='25' cy='25' r='1' fill='white' opacity='0.1'/%3E%3Ccircle cx='75' cy='75' r='1' fill='white' opacity='0.1'/%3E%3Ccircle cx='25' cy='75' r='1' fill='white' opacity='0.05'/%3E%3Ccircle cx='75' cy='25' r='1' fill='white' opacity='0.05'/%3E%3C/pattern%3E%3C/defs%3E%3Crect width='100' height='100' fill='url(%23grain)'/%3E%3C/svg%3E");
        z-index: 0;
    }

    .card-header h1 {
        margin: 0;
        font-size: 1.8rem;
        font-weight: 600;
        position: relative;
        z-index: 2;
    }

    .card-header .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
        opacity: 0.9;
        position: relative;
        z-index: 2;
    }

    .card-body {
        padding: 2.5rem;
    }

    .form-floating {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-control {
        border: 2px solid #e9ecef;
        border-radius: 12px;
        padding: 1rem 1.2rem;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: var(--input-shadow);
        background: #fafbfc;
    }

    .form-control:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25), var(--input-shadow);
        background: white;
        transform: translateY(-2px);
    }

    .form-floating > label {
        color: #6c757d;
        font-weight: 500;
    }

    .btn-primary {
        background: var(--primary-gradient);
        border: none;
        border-radius: 12px;
        padding: 0.875rem 2rem;
        font-weight: 600;
        font-size: 1rem;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        position: relative;
        overflow: hidden;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
    }

    .btn-primary:active {
        transform: translateY(0);
    }

    .alert {
        border: none;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        font-weight: 500;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .alert-success {
        background: var(--success-gradient);
        color: white;
        box-shadow: 0 4px 15px rgba(86, 171, 47, 0.3);
    }

    .alert-danger {
        background: var(--error-gradient);
        color: white;
        box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
    }

    .alert .icon {
        margin-right: 0.5rem;
    }

    .password-strength {
        margin-top: 0.5rem;
        font-size: 0.875rem;
    }

    .strength-bar {
        height: 4px;
        border-radius: 2px;
        background: #e9ecef;
        overflow: hidden;
        margin-top: 0.25rem;
    }

    .strength-fill {
        height: 100%;
        transition: all 0.3s ease;
        border-radius: 2px;
    }

    @media (max-width: 768px) {
        .main-container {
            padding: 1rem;
        }

        .card-header, .card-body {
            padding: 1.5rem;
        }

        .card-header h1 {
            font-size: 1.5rem;
        }
    }

    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    </style>
</head>
<body>
    <div class="container main-container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card password-card fade-in">
                    <div class="card-header">
                        <div class="icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <h1>Ganti Password</h1>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-danger'; ?>">
                                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> icon"></i>
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <form action="#" method="POST" id="passwordForm">
                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="old_password" 
                                       name="old_password" 
                                       placeholder="Password Lama"
                                       required>
                                <label for="old_password">
                                    <i class="fas fa-lock me-2"></i>Password Lama
                                </label>
                            </div>

                            <div class="form-floating">
                                <input type="password" 
                                       class="form-control" 
                                       id="new_password" 
                                       name="new_password" 
                                       placeholder="Password Baru"
                                       required>
                                <label for="new_password">
                                    <i class="fas fa-key me-2"></i>Password Baru
                                </label>
                                <div class="password-strength">
                                    <div class="strength-bar">
                                        <div class="strength-fill" id="strengthBar"></div>
                                    </div>
                                    <small class="text-muted" id="strengthText">Masukkan password baru</small>
                                </div>
                            </div>

                           <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync-alt me-2"></i>
                                    Ganti Password
                                </button>
                                <a href="profile.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>
                                    Kembali ke Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password strength indicator
        document.getElementById('new_password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            let strength = 0;
            let text = '';
            let color = '';
            
            if (password.length >= 8) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    text = 'Password sangat lemah';
                    color = '#ff6b6b';
                    break;
                case 2:
                    text = 'Password lemah';
                    color = '#ffa726';
                    break;
                case 3:
                    text = 'Password sedang';
                    color = '#ffeb3b';
                    break;
                case 4:
                    text = 'Password kuat';
                    color = '#66bb6a';
                    break;
                case 5:
                    text = 'Password sangat kuat';
                    color = '#4caf50';
                    break;
            }
            
            strengthBar.style.width = (strength * 20) + '%';
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = text;
            strengthText.style.color = color;
        });

        // Form submission with loading state
        document.getElementById('passwordForm').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memproses...';
            submitBtn.disabled = true;
            
            // Re-enable after a delay (in case of errors)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });
    </script>
</body>
</html>