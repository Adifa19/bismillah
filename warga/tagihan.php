<?php
require_once '../config.php';
requireLogin();

// Initialize variables
$message = '';
$message_type = '';

// Fungsi untuk generate order ID yang unik
function generateOrderId($bill_id, $user_id) {
    return 'BILL_' . $bill_id . '_' . $user_id . '_' . time();
}

// Fungsi untuk membuat atau mengupdate user_bill
function createOrUpdateUserBill($pdo, $bill_id, $user_id) {
    // Cek apakah user_bill sudah ada
    $stmt = $pdo->prepare("SELECT * FROM user_bills WHERE bill_id = ? AND user_id = ?");
    $stmt->execute([$bill_id, $user_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE user_bills 
            SET status = 'menunggu_pembayaran', 
                payment_token = NULL, 
                midtrans_transaction_id = NULL,
                midtrans_order_id = NULL,
                tanggal_bayar_online = NULL
            WHERE id = ?
        ");
        $stmt->execute([$existing['id']]);
        return $existing['id'];
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO user_bills (bill_id, user_id, status, tanggal) 
            VALUES (?, ?, 'menunggu_pembayaran', CURDATE())
        ");
        $stmt->execute([$bill_id, $user_id]);
        return $pdo->lastInsertId();
    }
}

// Fungsi upload yang disederhanakan
function uploadBuktiPembayaran($file, $user_bill_id) {
    // Validasi file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipe file tidak diizinkan. Gunakan JPG, JPEG, atau PNG');
    }
    
    if ($file['size'] > 2 * 1024 * 1024) { // 2MB untuk hosting
        throw new Exception('Ukuran file terlalu besar. Maksimal 2MB');
    }
    
    // Gunakan direktori yang sederhana
    $upload_dir = 'uploads/bukti_pembayaran/';
    
    // Buat direktori jika belum ada dengan permission yang aman
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Gagal membuat direktori upload');
        }
    }
    
    // Generate nama file yang sederhana
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_name = $user_bill_id . '_' . date('YmdHis') . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        throw new Exception('Gagal mengupload file');
    }
    
    return $file_name;
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_payment':
                $bill_id = $_POST['bill_id'];
                $user_id = $_SESSION['user_id'];
                
                // Ambil data tagihan
                $stmt = $pdo->prepare("SELECT * FROM bills WHERE id = ?");
                $stmt->execute([$bill_id]);
                $bill = $stmt->fetch();
                
                if (!$bill) {
                    throw new Exception('Tagihan tidak ditemukan');
                }
                
                // Ambil data user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                // Create atau update user_bill
                $user_bill_id = createOrUpdateUserBill($pdo, $bill_id, $user_id);
                
                // Generate order ID
                $order_id = generateOrderId($bill_id, $user_id);
                
                // Update payment token
                $stmt = $pdo->prepare("UPDATE user_bills SET payment_token = ?, midtrans_order_id = ? WHERE id = ?");
                $stmt->execute([$order_id, $order_id, $user_bill_id]);
                
                // Siapkan data untuk Midtrans
                $customer_details = array(
                    'first_name' => $user['username'],
                    'email' => $user['username'] . '@example.com',
                    'phone' => '08123456789'
                );
                
                // Dapatkan Snap Token
                $snap_token = getMidtransSnapToken($order_id, $bill['jumlah'], $customer_details);
                
                echo json_encode([
                    'status' => 'success',
                    'snap_token' => $snap_token,
                    'order_id' => $order_id,
                    'user_bill_id' => $user_bill_id
                ]);
                break;
                
            case 'check_payment_status':
                $order_id = $_POST['order_id'];
                $user_bill_id = $_POST['user_bill_id'];
                
                // Cek status dari database
                $stmt = $pdo->prepare("SELECT * FROM user_bills WHERE id = ?");
                $stmt->execute([$user_bill_id]);
                $user_bill = $stmt->fetch();
                
                if (!$user_bill) {
                    throw new Exception('Tagihan tidak ditemukan');
                }
                
                // Jika sudah dibayar online, return success
                if ($user_bill['tanggal_bayar_online']) {
                    echo json_encode([
                        'status' => 'success',
                        'payment_status' => 'paid',
                        'message' => 'Pembayaran berhasil'
                    ]);
                } else {
                    // Cek status dari Midtrans
                    try {
                        $midtrans_status = getMidtransTransactionStatus($order_id);
                        
                        if ($midtrans_status['transaction_status'] == 'settlement' || 
                            ($midtrans_status['transaction_status'] == 'capture' && $midtrans_status['fraud_status'] == 'accept')) {
                            
                            // Update status ke database
                            $stmt = $pdo->prepare("
                                UPDATE user_bills 
                                SET tanggal_bayar_online = NOW(), 
                                    midtrans_transaction_id = ?,
                                    midtrans_response = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([
                                $midtrans_status['transaction_id'],
                                json_encode($midtrans_status),
                                $user_bill_id
                            ]);
                            
                            echo json_encode([
                                'status' => 'success',
                                'payment_status' => 'paid',
                                'message' => 'Pembayaran berhasil'
                            ]);
                        } else {
                            echo json_encode([
                                'status' => 'pending',
                                'payment_status' => 'pending',
                                'message' => 'Pembayaran masih pending'
                            ]);
                        }
                    } catch (Exception $e) {
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Gagal mengecek status pembayaran'
                        ]);
                    }
                }
                break;
                
            case 'upload_bukti':
                $user_bill_id = $_POST['user_bill_id'];
                
                // Validasi upload file
                if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] != 0) {
                    throw new Exception('File bukti pembayaran tidak ditemukan');
                }
                
                $file = $_FILES['bukti_pembayaran'];
                
                // Upload file dengan fungsi yang disederhanakan
                $file_name = uploadBuktiPembayaran($file, $user_bill_id);
                
                // Update database
                $stmt = $pdo->prepare("
                    UPDATE user_bills 
                    SET bukti_pembayaran = ?, 
                        tanggal_upload = NOW(), 
                        status = 'menunggu_konfirmasi'
                    WHERE id = ?
                ");
                $stmt->execute([$file_name, $user_bill_id]);
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Bukti pembayaran berhasil diupload'
                ]);
                break;
                
            default:
                throw new Exception('Action tidak valid');
        }
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Ambil statistik
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN ub.status IS NULL OR ub.status = 'menunggu_pembayaran' THEN 1 END) as belum_bayar,
        SUM(CASE WHEN ub.status IS NULL OR ub.status = 'menunggu_pembayaran' THEN b.jumlah ELSE 0 END) as total_belum_bayar,
        COUNT(CASE WHEN ub.status = 'menunggu_konfirmasi' THEN 1 END) as menunggu_konfirmasi,
        COUNT(CASE WHEN ub.status = 'konfirmasi' THEN 1 END) as sudah_bayar,
        COUNT(CASE WHEN ub.status = 'tolak' THEN 1 END) as ditolak,
        COUNT(CASE WHEN b.tenggat_waktu < CURDATE() AND (ub.status IS NULL OR ub.status = 'menunggu_pembayaran') THEN 1 END) as terlambat
    FROM bills b 
    LEFT JOIN user_bills ub ON b.id = ub.bill_id AND ub.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Ambil daftar tagihan dengan join ke user_bills
$stmt = $pdo->prepare("
    SELECT b.*, 
           ub.status, 
           ub.bukti_pembayaran, 
           ub.tanggal_upload, 
           ub.tanggal_bayar_online, 
           ub.id as user_bill_id,
           ub.tanggal as tanggal_kirim,
           CASE 
               WHEN b.tenggat_waktu < CURDATE() AND (ub.status IS NULL OR ub.status = 'menunggu_pembayaran') THEN 1 
               ELSE 0 
           END as is_overdue
    FROM bills b 
    LEFT JOIN user_bills ub ON b.id = ub.bill_id AND ub.user_id = ?
    WHERE ub.status IN ('menunggu_pembayaran', 'tolak') OR ub.status IS NULL
    ORDER BY b.tanggal DESC
");
$stmt->execute([$_SESSION['user_id']]);
$bills = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan Warga - Sistem Keuangan RT/RW</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="<?php echo MIDTRANS_SNAP_URL; ?>" data-client-key="<?php echo MIDTRANS_CLIENT_KEY; ?>"></script>
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #ffffff;
            color: #1a1a1a;
            line-height: 1.6;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .page-header {
            background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 50%, #0f172a 100%);
            padding: 3rem 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(30, 64, 175, 0.3);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(59, 130, 246, 0.1) 0%, rgba(30, 64, 175, 0.1) 100%);
            z-index: 1;
        }

        .page-header > * {
            position: relative;
            z-index: 2;
        }

        .page-header h1 {
            color: #ffffff;
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            color: #dbeafe;
            font-size: 1.2rem;
            font-weight: 400;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--color);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--color);
        }

        .stat-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-pending { --color: #ef4444; }
        .stat-total { --color: #3b82f6; }
        .stat-overdue { --color: #f97316; }
        .stat-waiting { --color: #8b5cf6; }
        .stat-success { --color: #10b981; }
        .stat-rejected { --color: #ec4899; }
        .stat-upload { --color: #06b6d4; }
        
        .bills-container {
            display: grid;
            gap: 2rem;
        }
        
        .bill-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f3f4;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .bill-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .bill-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .bill-card.overdue::before {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }
        
        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1.5rem;
        }
        
        .bill-code {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .bill-description {
            color: #6c757d;
            margin-bottom: 1rem;
        }
        
        .bill-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .info-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .info-value.amount {
            color: #28a745;
            font-size: 1.3rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-overdue {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
        }
        
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        
        .modal-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .upload-zone {
            border: 3px dashed #28a745;
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }
        
        .upload-zone:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            border-color: #20c997;
            transform: scale(1.02);
        }
        
        .upload-zone.dragover {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border-color: #155724;
        }
        
        .upload-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1rem;
        }
        
        .file-preview {
            max-width: 300px;
            max-height: 300px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 1rem auto;
        }
        
        .progress-container {
            margin-top: 1rem;
        }
        
        .progress {
            height: 8px;
            border-radius: 10px;
            background: #e9ecef;
        }
        
        .progress-bar {
            border-radius: 10px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .btn-upload {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            margin-top: 1rem;
        }
        
        .btn-upload:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }
        
        .info-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 3rem;
        }
        
        .info-section h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .info-section ul {
            list-style: none;
            padding: 0;
        }
        
        .info-section li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .info-section li:last-child {
            border-bottom: none;
        }
        
        .info-section strong {
            color: #495057;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 2rem 1.5rem;
            }
            
            .page-header h1 {
                font-size: 2.2rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .bill-card {
                padding: 1.5rem;
            }
            
            .bill-info {
                grid-template-columns: 1fr;
            }
        }
         /* Container untuk tab navigasi */
.nav-tabs-container {
    display: flex;
    gap: 1rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

/* Gaya untuk setiap tab navigasi */
.nav-tab {
    padding: 0.75rem 1.25rem;
    text-decoration: none;
    color: #1e40af; /* Biru navy */
    font-weight: 500;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background-color: #ffffff;
    border-radius: 0.5rem 0.5rem 0 0;
}

/* Saat hover */
.nav-tab:hover {
    color: #2563eb;
    background: #eff6ff;
    border-bottom-color: #2563eb;
}

/* Tab aktif */
.nav-tab.active {
    color: #2563eb;
    background: #eff6ff;
    border-bottom-color: #2563eb;
}

/* Gaya untuk modal wajib upload */
.modal-mandatory {
    background: rgba(0, 0, 0, 0.8) !important;
}

.modal-mandatory .modal-header {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    position: relative;
}

.modal-mandatory .modal-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: repeating-linear-gradient(
        45deg,
        transparent,
        transparent 10px,
        rgba(255, 255, 255, 0.1) 10px,
        rgba(255, 255, 255, 0.1) 20px
    );
}

.modal-mandatory .modal-header > * {
    position: relative;
    z-index: 1;
}

.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
    }
    70% {
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

.warning-text {
    color: #dc3545;
    font-weight: 600;
    text-align: center;
    padding: 1rem;
    background: #f8d7da;
    border-radius: 8px;
    margin-bottom: 1rem;
    border: 2px solid #dc3545;
}

.countdown-timer {
    font-size: 1.2rem;
    font-weight: 700;
    color: #dc3545;
    text-align: center;
    margin-bottom: 1rem;
}

    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-container">
        <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Tagihan Warga
                </h1>
                <p>Kelola pembayaran tagihan komunitas Anda</p>
            </div>
            <div class="nav-tabs-container">
                <a href="tagihan.php" class="nav-tab active">
                    <i class="fas fa-plus-circle"></i> Tagihan
                </a>
                <a href="riwayat.php" class="nav-tab ">
                    <i class="fas fa-minus-circle"></i> Riwayat Pembayaran
                </a>
            </div>
        
        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-pending">
                    <div class="stat-number"><?= $stats['belum_bayar'] ?></div>
                    <div class="stat-label">Belum Bayar</div>
                </div>
                <div class="stat-card stat-total">
                    <div class="stat-number">Rp <?= number_format($stats['total_belum_bayar'], 0, ',', '.') ?></div>
                    <div class="stat-label">Total</div>
                <div class="stat-card stat-overdue">
                    <div class="stat-number"><?= $stats['terlambat'] ?></div>
                    <div class="stat-label">Terlambat</div>
                </div>
                <div class="stat-card stat-waiting">
                    <div class="stat-number"><?= $stats['menunggu_konfirmasi'] ?></div>
                    <div class="stat-label">Menunggu Konfirmasi</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?= $stats['sudah_bayar'] ?></div>
                    <div class="stat-label">Sudah Bayar</div>
                </div>
                <div class="stat-card stat-rejected">
                    <div class="stat-number"><?= $stats['ditolak'] ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>
        
        <!-- Bills List -->
        <div class="bills-container">
            <?php if (count($bills) > 0): ?>
                <?php foreach ($bills as $bill): ?>
                    <div class="bill-card <?= $bill['is_overdue'] ? 'overdue' : '' ?>">
                        <div class="bill-header">
                            <div>
                                <div class="bill-code"><?= htmlspecialchars($bill['kode_tagihan']) ?></div>
                                <div class="bill-description"><?= htmlspecialchars($bill['deskripsi']) ?></div>
                            </div>
                            <div>
                                <?php if ($bill['is_overdue']): ?>
                                    <span class="status-badge status-overdue">
                                        <i class="fas fa-exclamation-triangle"></i> Terlambat
                                    </span>
                                <?php elseif ($bill['status'] == 'tolak'): ?>
                                    <span class="status-badge status-rejected">
                                        <i class="fas fa-times-circle"></i> Ditolak
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-pending">
                                        <i class="fas fa-clock"></i> Menunggu Pembayaran
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="bill-info">
                            <div class="info-item">
                                <div class="info-label">Jumlah</div>
                                <div class="info-value amount">Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tanggal Tagihan</div>
                                <div class="info-value"><?= date('d/m/Y', strtotime($bill['tanggal'])) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tenggat Waktu</div>
                                <div class="info-value"><?= date('d/m/Y', strtotime($bill['tenggat_waktu'])) ?></div>
                            </div>
                            <?php if ($bill['tanggal_kirim']): ?>
                                <div class="info-item">
                                    <div class="info-label">Tanggal Kirim</div>
                                    <div class="info-value"><?= date('d/m/Y', strtotime($bill['tanggal_kirim'])) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-pay" onclick="showPaymentModal(<?= $bill['id'] ?>, <?= $bill['user_bill_id'] ?>, '<?= addslashes($bill['kode_tagihan']) ?>', <?= $bill['jumlah'] ?>)">
                                <i class="fas fa-credit-card"></i> Bayar Sekarang
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>Tidak ada tagihan yang perlu dibayar</h3>
                    <p>Semua tagihan Anda sudah lunas atau belum ada tagihan baru.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="paymentModalLabel">
                        <i class="fas fa-credit-card"></i> Pembayaran Tagihan
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="paymentContent">
                        <!-- Payment content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">
                        <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="warning-text">
                        <i class="fas fa-exclamation-triangle"></i>
                        Upload bukti pembayaran yang jelas dan dapat dibaca. Format yang diterima: JPG, JPEG, PNG (maksimal 2MB)
                    </div>
                    
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" id="uploadUserBillId" name="user_bill_id">
                        <input type="hidden" name="action" value="upload_bukti">
                        
                        <div class="upload-zone" id="uploadZone">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h4>Klik atau drag file ke sini</h4>
                            <p>File gambar (JPG, JPEG, PNG) maksimal 2MB</p>
                            <input type="file" id="fileInput" name="bukti_pembayaran" accept="image/*" style="display: none;">
                        </div>
                        
                        <div id="filePreview" style="display: none;">
                            <img id="previewImage" class="file-preview" alt="Preview">
                            <div class="text-center">
                                <button type="button" class="btn btn-secondary" onclick="resetUpload()">
                                    <i class="fas fa-times"></i> Ganti File
                                </button>
                            </div>
                        </div>
                        
                        <div class="progress-container" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-upload" id="uploadBtn" disabled>
                            <i class="fas fa-upload"></i> Upload Bukti Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Spinner -->
    <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Memproses pembayaran...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPaymentData = {};
        let paymentCheckInterval;

        function showPaymentModal(billId, userBillId, kodeTagihan, jumlah) {
            currentPaymentData = {
                billId: billId,
                userBillId: userBillId,
                kodeTagihan: kodeTagihan,
                jumlah: jumlah
            };

            const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
            
            // Set content
            document.getElementById('paymentContent').innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h4>Detail Tagihan</h4>
                        <table class="table table-bordered">
                            <tr>
                                <td><strong>Kode Tagihan:</strong></td>
                                <td>${kodeTagihan}</td>
                            </tr>
                            <tr>
                                <td><strong>Jumlah:</strong></td>
                                <td><strong>Rp ${new Intl.NumberFormat('id-ID').format(jumlah)}</strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h4>Pilih Metode Pembayaran</h4>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg" onclick="processPayment('online')">
                                <i class="fas fa-credit-card"></i> Bayar Online
                            </button>
                            <button class="btn btn-success btn-lg" onclick="processPayment('upload')">
                                <i class="fas fa-upload"></i> Upload Bukti Transfer
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            modal.show();
        }

        function processPayment(method) {
            if (method === 'online') {
                payOnline();
            } else if (method === 'upload') {
                showUploadModal();
            }
        }

        function payOnline() {
            // Show loading
            document.getElementById('loadingSpinner').style.display = 'block';
            
            // Create payment
            fetch('tagihan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=create_payment&bill_id=${currentPaymentData.billId}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('loadingSpinner').style.display = 'none';
                
                if (data.status === 'success') {
                    // Close payment modal
                    bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
                    
                    // Open Midtrans Snap
                    snap.pay(data.snap_token, {
                        onSuccess: function(result) {
                            checkPaymentStatus(data.order_id, data.user_bill_id);
                        },
                        onPending: function(result) {
                            startPaymentStatusCheck(data.order_id, data.user_bill_id);
                        },
                        onError: function(result) {
                            alert('Pembayaran gagal! Silakan coba lagi.');
                        },
                        onClose: function() {
                            // Start checking payment status when modal is closed
                            startPaymentStatusCheck(data.order_id, data.user_bill_id);
                        }
                    });
                } else {
                    alert('Gagal membuat pembayaran: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('loadingSpinner').style.display = 'none';
                alert('Terjadi kesalahan saat memproses pembayaran');
                console.error('Error:', error);
            });
        }

        function startPaymentStatusCheck(orderId, userBillId) {
            // Clear existing interval
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
            }
            
            // Check every 5 seconds
            paymentCheckInterval = setInterval(() => {
                checkPaymentStatus(orderId, userBillId);
            }, 5000);
        }

        function checkPaymentStatus(orderId, userBillId) {
            fetch('tagihan.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_payment_status&order_id=${orderId}&user_bill_id=${userBillId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.payment_status === 'paid') {
                    // Clear interval
                    if (paymentCheckInterval) {
                        clearInterval(paymentCheckInterval);
                    }
                    
                    // Show success message and reload page
                    alert('Pembayaran berhasil! Halaman akan dimuat ulang.');
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error checking payment status:', error);
            });
        }

        function showUploadModal() {
            // Close payment modal
            bootstrap.Modal.getInstance(document.getElementById('paymentModal')).hide();
            
            // Set user bill ID
            document.getElementById('uploadUserBillId').value = currentPaymentData.userBillId;
            
            // Show upload modal
            const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
            uploadModal.show();
        }

        // Upload functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadZone = document.getElementById('uploadZone');
            const fileInput = document.getElementById('fileInput');
            const filePreview = document.getElementById('filePreview');
            const previewImage = document.getElementById('previewImage');
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadForm = document.getElementById('uploadForm');

            // Click to select file
            uploadZone.addEventListener('click', () => {
                fileInput.click();
            });

            // Drag and drop
            uploadZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });

            uploadZone.addEventListener('dragleave', () => {
                uploadZone.classList.remove('dragover');
            });

            uploadZone.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFileSelect(files[0]);
                }
            });

            // File input change
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFileSelect(e.target.files[0]);
                }
            });

            function handleFileSelect(file) {
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('File harus berupa gambar!');
                    return;
                }

                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('Ukuran file maksimal 2MB!');
                    return;
                }

                // Show preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    previewImage.src = e.target.result;
                    uploadZone.style.display = 'none';
                    filePreview.style.display = 'block';
                    uploadBtn.disabled = false;
                };
                reader.readAsDataURL(file);
            }

            // Upload form submit
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const formData = new FormData(uploadForm);
                const progressContainer = document.querySelector('.progress-container');
                const progressBar = document.querySelector('.progress-bar');
                
                // Show progress
                progressContainer.style.display = 'block';
                uploadBtn.disabled = true;
                
                fetch('tagihan.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Bukti pembayaran berhasil diupload!');
                        bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                        location.reload();
                    } else {
                        alert('Gagal upload: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Terjadi kesalahan saat upload');
                    console.error('Error:', error);
                })
                .finally(() => {
                    progressContainer.style.display = 'none';
                    uploadBtn.disabled = false;
                });
            });
        });

        function resetUpload() {
            document.getElementById('uploadZone').style.display = 'block';
            document.getElementById('filePreview').style.display = 'none';
            document.getElementById('fileInput').value = '';
            document.getElementById('uploadBtn').disabled = true;
        }

        // Clear payment check interval when page unloads
        window.addEventListener('beforeunload', () => {
            if (paymentCheckInterval) {
                clearInterval(paymentCheckInterval);
            }
        });
    </script>
</body>
</html>
