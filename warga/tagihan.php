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
    if (!isset($_FILES['bukti_pembayaran'])) {
        throw new Exception('File bukti pembayaran tidak dikirim.');
    }

    $file = $_FILES['bukti_pembayaran'];

    // Cek error upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file melebihi batas php.ini.',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file melebihi batas form.',
            UPLOAD_ERR_PARTIAL    => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE    => 'Tidak ada file yang diupload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk.',
            UPLOAD_ERR_EXTENSION  => 'Ekstensi PHP menghentikan upload.',
        ];
        $err = $error_messages[$file['error']] ?? 'Terjadi kesalahan saat upload.';
        throw new Exception("Upload gagal: $err");
    }

    $allowed_types = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipe file tidak diizinkan. Gunakan JPG, JPEG, atau PNG.');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('Ukuran file terlalu besar. Maksimal 5MB.');
    }

    // Buat direktori upload jika belum ada
    $upload_dir = __DIR__ . '/uploads/bukti_pembayaran/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('Gagal membuat folder upload.');
        }
    }

    // Generate nama file unik
    $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $file_name = 'bukti_' . $user_bill_id . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;

    // Upload file
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        error_log("Gagal move_uploaded_file: TMP=" . $file['tmp_name'] . ", DEST=" . $file_path);
        throw new Exception('Gagal mengupload file. Cek permission folder uploads.');
    }

    // Simpan hanya nama file di database
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
                    <div class="stat-label">Total Tagihan</div>
                </div>
                <div class="stat-card stat-overdue">
                    <div class="stat-number"><?= $stats['terlambat'] ?></div>
                    <div class="stat-label">Terlambat</div>
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
        
        <!-- Information Section -->
        <div class="info-section">
            <h3>
                <i class="fas fa-info-circle"></i>
                Panduan Pembayaran
            </h3>
            <ul>
                <li><strong>Pembayaran Online:</strong> Klik tombol "Bayar Sekarang" untuk melakukan pembayaran online</li>
                <li><strong>Upload Bukti:</strong> Setelah pembayaran berhasil, wajib upload bukti pembayaran (screenshot/foto)</li>
                <li><strong>Verifikasi:</strong> Admin akan memverifikasi pembayaran dalam 1-2 hari kerja</li>
                <li><strong>Status Lunas:</strong> Tagihan akan berubah menjadi "Lunas" setelah diverifikasi</li>
            </ul>
        </div>
        
        <!-- Bills Container -->
        <div class="bills-container">
            <?php if (empty($bills)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Tidak Ada Tagihan</h3>
                    <p>Selamat! Anda tidak memiliki tagihan yang perlu dibayar saat ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($bills as $bill): ?>
                    <div class="bill-card <?= $bill['is_overdue'] ? 'overdue' : '' ?>">
                        <div class="bill-header">
                            <div>
                                <div class="bill-code"><?= htmlspecialchars($bill['kode_tagihan']) ?></div>
                                <div class="bill-description"><?= htmlspecialchars($bill['deskripsi']) ?></div>
                            </div>
                            <div>
                                <?php
                                $status_class = 'status-pending';
                                $status_text = 'Belum Dibayar';
                                
                                if ($bill['is_overdue']) {
                                    $status_class = 'status-overdue';
                                    $status_text = 'Terlambat';
                                }
                                
                                if ($bill['status']) {
                                    switch ($bill['status']) {
                                        case 'menunggu_pembayaran':
                                            $status_class = 'status-pending';
                                            $status_text = 'Menunggu Pembayaran';
                                            break;
                                        case 'tolak':
                                            $status_class = 'status-rejected';
                                            $status_text = 'Ditolak';
                                            break;
                                    }
                                }
                                ?>
                                <span class="status-badge <?= $status_class ?>">
                                    <?= $status_text ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="bill-info">
                            <div class="info-item">
                                <div class="info-label">Jumlah Tagihan</div>
                                <div class="info-value amount">Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Tanggal Tagihan</div>
                                <div class="info-value"><?= date('d M Y', strtotime($bill['tanggal'])) ?></div>
                            </div>
                            <?php if ($bill['tenggat_waktu']): ?>
                            <div class="info-item">
                                <div class="info-label">Tenggat Waktu</div>
                                <div class="info-value"><?= date('d M Y', strtotime($bill['tenggat_waktu'])) ?></div>
                            </div>
                            <?php endif; ?>
                            <?php if ($bill['tanggal_kirim']): ?>
                            <div class="info-item">
                                <div class="info-label">Tanggal Kirim</div>
                                <div class="info-value"><?= date('d M Y', strtotime($bill['tanggal_kirim'])) ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!$bill['status'] || $bill['status'] == 'menunggu_pembayaran' || $bill['status'] == 'tolak'): ?><div class="d-flex gap-2 justify-content-center">
                            <button class="btn btn-pay" onclick="createPayment(<?= $bill['id'] ?>, <?= $bill['user_bill_id'] ?: 0 ?>)">
                                <i class="fas fa-credit-card"></i>
                                Bayar Sekarang
                            </button>
                            <?php if ($bill['user_bill_id'] && $bill['status'] == 'tolak'): ?>
                            <button class="btn btn-success" onclick="openUploadModal(<?= $bill['user_bill_id'] ?>)">
                                <i class="fas fa-upload"></i>
                                Upload Ulang
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadModalLabel">
                        <i class="fas fa-upload"></i>
                        Upload Bukti Pembayaran
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" id="userBillId" name="user_bill_id" value="">
                        <input type="hidden" name="action" value="upload_bukti">
                        
                        <div class="upload-zone" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <h4>Pilih File Bukti Pembayaran</h4>
                            <p>Klik di sini atau drag & drop file Anda<br>
                            <small>Format yang didukung: JPG, JPEG, PNG (Max: 5MB)</small></p>
                        </div>
                        
                        <input type="file" id="fileInput" name="bukti_pembayaran" accept=".jpg,.jpeg,.png" style="display: none;">
                        
                        <div id="filePreview" class="text-center" style="display: none;">
                            <img id="previewImage" class="file-preview" src="" alt="Preview">
                            <p id="fileName" class="mt-2"></p>
                        </div>
                        
                        <div class="progress-container" style="display: none;">
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-upload" disabled>
                            <i class="fas fa-paper-plane"></i>
                            Upload Bukti Pembayaran
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-3">Memproses pembayaran...</p>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">
                        <i class="fas fa-check-circle"></i>
                        Pembayaran Berhasil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 1rem;"></i>
                    <h4>Pembayaran Berhasil!</h4>
                    <p>Tagihan Anda telah berhasil dibayar. Silakan upload bukti pembayaran untuk verifikasi.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" onclick="uploadAfterPayment()">
                        <i class="fas fa-upload"></i>
                        Upload Bukti Sekarang
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Nanti Saja</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
       let currentUserBillId = null;
let currentOrderId = null;
let paymentCompleted = false;

// Fungsi untuk membuat pembayaran
function createPayment(billId, userBillId) {
    document.getElementById('loadingOverlay').style.display = 'block';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=create_payment&bill_id=${billId}`
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('loadingOverlay').style.display = 'none';
        
        if (data.status === 'success') {
            currentUserBillId = data.user_bill_id;
            currentOrderId = data.order_id;
            
            // Buka Midtrans Snap
            snap.pay(data.snap_token, {
                onSuccess: function(result) {
                    console.log('Payment success:', result);
                    paymentCompleted = true;
                    
                    // Langsung cek status pembayaran dan wajibkan upload
                    checkPaymentStatusAndForceUpload(data.order_id, data.user_bill_id);
                },
                onPending: function(result) {
                    console.log('Payment pending:', result);
                    alert('Pembayaran sedang diproses. Silakan cek status pembayaran Anda.');
                },
                onError: function(result) {
                    console.log('Payment error:', result);
                    alert('Pembayaran gagal. Silakan coba lagi.');
                },
                onClose: function() {
                    console.log('Payment popup closed');
                    
                    // Jika pembayaran berhasil tapi popup ditutup tanpa upload
                    if (paymentCompleted && !isUploadCompleted()) {
                        showMandatoryUploadModal();
                    }
                }
            });
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        document.getElementById('loadingOverlay').style.display = 'none';
        console.error('Error:', error);
        alert('Terjadi kesalahan saat membuat pembayaran');
    });
}

// Fungsi untuk mengecek status pembayaran dan memaksa upload
function checkPaymentStatusAndForceUpload(orderId, userBillId) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=check_payment_status&order_id=${orderId}&user_bill_id=${userBillId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success' && data.payment_status === 'paid') {
            // Tampilkan modal success dengan upload wajib
            showMandatoryUploadModal();
        } else {
            alert(data.message || 'Status pembayaran belum dapat dikonfirmasi');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Gagal mengecek status pembayaran');
    });
}

// Fungsi untuk menampilkan modal upload wajib
function showMandatoryUploadModal() {
    // Buat modal khusus yang tidak bisa ditutup
    const modalHtml = `
        <div class="modal fade" id="mandatoryUploadModal" tabindex="-1" aria-labelledby="mandatoryUploadModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="mandatoryUploadModalLabel">
                            <i class="fas fa-check-circle"></i>
                            Pembayaran Berhasil - Upload Bukti Wajib
                        </h5>
                        <!-- Tidak ada tombol close untuk memaksa upload -->
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <strong>Pembayaran Berhasil!</strong> Tagihan Anda telah berhasil dibayar.
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>WAJIB:</strong> Anda harus upload bukti pembayaran untuk menyelesaikan proses pembayaran.
                        </div>
                        
                        <form id="mandatoryUploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="user_bill_id" value="${currentUserBillId}">
                            <input type="hidden" name="action" value="upload_bukti">
                            
                            <div class="upload-zone" onclick="document.getElementById('mandatoryFileInput').click()">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <h4>Upload Bukti Pembayaran</h4>
                                <p>Klik di sini atau drag & drop file Anda<br>
                                <small>Format yang didukung: JPG, JPEG, PNG (Max: 5MB)</small></p>
                                <div class="text-danger mt-2">
                                    <strong>* Wajib diisi untuk menyelesaikan pembayaran</strong>
                                </div>
                            </div>
                            
                            <input type="file" id="mandatoryFileInput" name="bukti_pembayaran" accept=".jpg,.jpeg,.png" style="display: none;" required>
                            
                            <div id="mandatoryFilePreview" class="text-center" style="display: none;">
                                <img id="mandatoryPreviewImage" class="file-preview" src="" alt="Preview">
                                <p id="mandatoryFileName" class="mt-2"></p>
                            </div>
                            
                            <div class="progress-container" style="display: none;">
                                <div class="progress">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-upload" disabled>
                                <i class="fas fa-paper-plane"></i>
                                Upload Bukti Pembayaran (Wajib)
                            </button>
                        </form>
                        
                        <div class="mt-3 text-center">
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Modal ini akan tertutup otomatis setelah bukti pembayaran berhasil diupload
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Hapus modal lama jika ada
    const existingModal = document.getElementById('mandatoryUploadModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Tambahkan modal baru
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Tampilkan modal
    const mandatoryModal = new bootstrap.Modal(document.getElementById('mandatoryUploadModal'));
    mandatoryModal.show();
    
    // Setup event listeners untuk modal wajib
    setupMandatoryUploadEvents();
}

// Setup event listeners untuk upload wajib
function setupMandatoryUploadEvents() {
    // Handle file input change
    document.getElementById('mandatoryFileInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validasi file
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipe file tidak diizinkan. Gunakan JPG, JPEG, atau PNG');
                this.value = '';
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) { // 5MB
                alert('Ukuran file terlalu besar. Maksimal 5MB');
                this.value = '';
                return;
            }
            
            // Preview file
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('mandatoryPreviewImage').src = e.target.result;
                document.getElementById('mandatoryFileName').textContent = file.name;
                document.getElementById('mandatoryFilePreview').style.display = 'block';
                document.querySelector('#mandatoryUploadForm .btn-upload').disabled = false;
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Handle drag & drop
    const uploadZone = document.querySelector('#mandatoryUploadModal .upload-zone');
    
    uploadZone.addEventListener('dragover', function(e) {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', function(e) {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('mandatoryFileInput').files = files;
            document.getElementById('mandatoryFileInput').dispatchEvent(new Event('change'));
        }
    });
    
    // Handle form submission
    document.getElementById('mandatoryUploadForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const progressContainer = document.querySelector('#mandatoryUploadModal .progress-container');
        const progressBar = document.querySelector('#mandatoryUploadModal .progress-bar');
        const uploadBtn = document.querySelector('#mandatoryUploadModal .btn-upload');
        
        progressContainer.style.display = 'block';
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        
        // Simulate progress
        let progress = 0;
        const progressInterval = setInterval(() => {
            progress += 10;
            progressBar.style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(progressInterval);
            }
        }, 100);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            
            setTimeout(() => {
                if (data.status === 'success') {
                    // Tutup modal dan reload halaman
                    const mandatoryModal = bootstrap.Modal.getInstance(document.getElementById('mandatoryUploadModal'));
                    mandatoryModal.hide();
                    
                    // Tampilkan pesan sukses
                    alert('Bukti pembayaran berhasil diupload! Pembayaran Anda sedang diproses.');
                    
                    // Reset status
                    paymentCompleted = false;
                    currentUserBillId = null;
                    currentOrderId = null;
                    
                    // Reload halaman
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                    
                    progressContainer.style.display = 'none';
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Upload Bukti Pembayaran (Wajib)';
                }
            }, 500);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat mengupload file');
            
            progressContainer.style.display = 'none';
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Upload Bukti Pembayaran (Wajib)';
        });
    });
}

// Fungsi untuk mengecek apakah upload sudah selesai
function isUploadCompleted() {
    return document.getElementById('mandatoryUploadModal') === null;
}

// Fungsi untuk membuka modal upload biasa (untuk re-upload)
function openUploadModal(userBillId) {
    currentUserBillId = userBillId;
    document.getElementById('userBillId').value = userBillId;
    const uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
    uploadModal.show();
}

// Handle file input change untuk modal upload biasa
document.getElementById('fileInput').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // Validasi file
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
        if (!allowedTypes.includes(file.type)) {
            alert('Tipe file tidak diizinkan. Gunakan JPG, JPEG, atau PNG');
            return;
        }
        
        if (file.size > 5 * 1024 * 1024) { // 5MB
            alert('Ukuran file terlalu besar. Maksimal 5MB');
            return;
        }
        
        // Preview file
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImage').src = e.target.result;
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('filePreview').style.display = 'block';
            document.querySelector('#uploadForm .btn-upload').disabled = false;
        };
        reader.readAsDataURL(file);
    }
});

// Handle drag & drop untuk modal upload biasa
const uploadZone = document.querySelector('#uploadModal .upload-zone');

uploadZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    uploadZone.classList.add('dragover');
});

uploadZone.addEventListener('dragleave', function(e) {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
});

uploadZone.addEventListener('drop', function(e) {
    e.preventDefault();
    uploadZone.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        document.getElementById('fileInput').files = files;
        document.getElementById('fileInput').dispatchEvent(new Event('change'));
    }
});

// Handle form submission untuk modal upload biasa
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const progressContainer = document.querySelector('#uploadModal .progress-container');
    const progressBar = document.querySelector('#uploadModal .progress-bar');
    const uploadBtn = document.querySelector('#uploadModal .btn-upload');
    
    progressContainer.style.display = 'block';
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    
    // Simulate progress
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += 10;
        progressBar.style.width = progress + '%';
        
        if (progress >= 90) {
            clearInterval(progressInterval);
        }
    }, 100);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        progressBar.style.width = '100%';
        
        setTimeout(() => {
            if (data.status === 'success') {
                alert('Bukti pembayaran berhasil diupload!');
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
            
            progressContainer.style.display = 'none';
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Upload Bukti Pembayaran';
        }, 500);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan saat mengupload file');
        
        progressContainer.style.display = 'none';
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Upload Bukti Pembayaran';
    });
});

// Prevent page refresh/close jika pembayaran berhasil tapi belum upload
window.addEventListener('beforeunload', function(e) {
    if (paymentCompleted && !isUploadCompleted()) {
        e.preventDefault();
        e.returnValue = 'Anda belum mengupload bukti pembayaran. Yakin ingin meninggalkan halaman?';
        return 'Anda belum mengupload bukti pembayaran. Yakin ingin meninggalkan halaman?';
    }
});

// Auto-refresh untuk mengecek status pembayaran
setInterval(() => {
    // Cek apakah ada pembayaran yang sedang pending
    const pendingPayments = document.querySelectorAll('.status-pending');
    pendingPayments.forEach(element => {
        // Implementasi auto-refresh status jika diperlukan
    });
}, 30000); // Cek setiap 30 detik

// Fungsi untuk handle keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Prevent ESC key pada modal wajib
    if (e.key === 'Escape' && document.getElementById('mandatoryUploadModal')) {
        e.preventDefault();
        e.stopPropagation();
        alert('Anda harus mengupload bukti pembayaran terlebih dahulu!');
    }
});

// Disable right-click pada modal wajib untuk mencegah inspect element
document.addEventListener('contextmenu', function(e) {
    if (document.getElementById('mandatoryUploadModal')) {
        e.preventDefault();
        alert('Silakan upload bukti pembayaran terlebih dahulu!');
    }
});
    </script>
</body>
</html>
