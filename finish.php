<?php
// File: finish.php
// Halaman redirect setelah pembayaran selesai

require_once 'config.php';
requireLogin();

$transaction_status = $_GET['transaction_status'] ?? 'unknown';
$order_id = $_GET['order_id'] ?? '';

// Verifikasi status transaksi dari Midtrans
if ($order_id) {
    try {
        $status = getMidtransTransactionStatus($order_id);
        $transaction_status = $status['transaction_status'] ?? 'unknown';
    } catch (Exception $e) {
        error_log("Error getting transaction status: " . $e->getMessage());
    }
}

// Tentukan pesan berdasarkan status
$message = '';
$message_type = 'info';

switch ($transaction_status) {
    case 'settlement':
    case 'capture':
        $message = 'Pembayaran berhasil! Tagihan Anda telah lunas.';
        $message_type = 'success';
        break;
    case 'pending':
        $message = 'Pembayaran sedang diproses. Silakan cek status pembayaran Anda secara berkala.';
        $message_type = 'warning';
        break;
    case 'deny':
    case 'cancel':
    case 'expire':
        $message = 'Pembayaran dibatalkan atau gagal. Silakan coba lagi.';
        $message_type = 'error';
        break;
    default:
        $message = 'Status pembayaran tidak diketahui. Silakan hubungi admin jika ada masalah.';
        $message_type = 'info';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        
        .success { color: #10b981; }
        .warning { color: #f59e0b; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        
        h1 {
            color: #1f2937;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        
        .message {
            color: #6b7280;
            margin-bottom: 2rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.25);
        }
        
        .order-info {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon <?php echo $message_type; ?>">
            <i class="fas fa-<?php 
                echo $message_type === 'success' ? 'check-circle' : 
                     ($message_type === 'warning' ? 'hourglass-half' : 
                      ($message_type === 'error' ? 'times-circle' : 'info-circle')); 
            ?>"></i>
        </div>
        
        <h1>Status Pembayaran</h1>
        
        <?php if ($order_id): ?>
            <div class="order-info">
                <strong>Order ID:</strong> <?php echo htmlspecialchars($order_id); ?>
            </div>
        <?php endif; ?>
        
        <p class="message">
            <?php echo htmlspecialchars($message); ?>
        </p>
        
        <a href="tagihan.php" class="btn">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Tagihan
        </a>
    </div>
    
    <script>
        // Auto redirect after 10 seconds for success
        <?php if ($message_type === 'success'): ?>
            setTimeout(function() {
                window.location.href = 'tagihan.php';
            }, 10000);
        <?php endif; ?>
    </script>
</body>
</html>