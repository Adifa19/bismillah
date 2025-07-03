<?php
include('../config.php');

// Cek apakah user sudah login dan merupakan admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login.php");
    exit();
}

// Proses konfirmasi pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $user_bill_id = (int)$_POST['user_bill_id'];
        $action = $_POST['action'];
        
        try {
            $pdo->beginTransaction();
            
            if ($action === 'konfirmasi') {
                // Update status menjadi konfirmasi
                $stmt = $pdo->prepare("
                    UPDATE user_bills 
                    SET status = 'konfirmasi' 
                    WHERE id = ? AND status = 'menunggu_konfirmasi'
                ");
                $stmt->execute([$user_bill_id]);
                
                // Pindahkan ke tabel tagihan_oke
                $stmt = $pdo->prepare("
                    INSERT INTO tagihan_oke (user_bill_id, kode_tagihan, jumlah, tanggal, user_id, qr_code_hash, bukti_pembayaran)
                    SELECT ub.id, b.kode_tagihan, b.jumlah, b.tanggal, ub.user_id, ub.qr_code_hash, ub.bukti_pembayaran
                    FROM user_bills ub
                    JOIN bills b ON ub.bill_id = b.id
                    WHERE ub.id = ?
                ");
                $stmt->execute([$user_bill_id]);
                
                $message = "Pembayaran berhasil dikonfirmasi!";
                $message_type = "success";
                
            } elseif ($action === 'tolak') {
                // Update status menjadi tolak
                $stmt = $pdo->prepare("
                    UPDATE user_bills 
                    SET status = 'tolak' 
                    WHERE id = ? AND status = 'menunggu_konfirmasi'
                ");
                $stmt->execute([$user_bill_id]);
                
                $message = "Pembayaran berhasil ditolak!";
                $message_type = "warning";
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// Proses OCR
if (isset($_POST['run_ocr'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];
    
    // Ambil data user_bill dan bill
    $stmt = $pdo->prepare("
        SELECT ub.*, b.kode_tagihan, b.jumlah as expected_amount, b.tanggal as expected_date
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.id = ?
    ");
    $stmt->execute([$user_bill_id]);
    $user_bill = $stmt->fetch();
    
    if ($user_bill && $user_bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        
        if (file_exists($image_path)) {
            // Jalankan OCR
            $command = "python3 ocr.py " . escapeshellarg($image_path) . " " . 
                      escapeshellarg($user_bill['kode_tagihan']) . " " . 
                      escapeshellarg($user_bill['expected_amount']);
            
            $output = shell_exec($command);
            
            if ($output) {
                // Parse output OCR
                $ocr_data = [
                    'jumlah' => null,
                    'tanggal' => null,
                    'kode' => null,
                    'confidence' => 0,
                    'text' => ''
                ];
                
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    if (strpos($line, 'AMOUNT:') === 0) {
                        $ocr_data['jumlah'] = (int)str_replace('AMOUNT:', '', $line);
                    } elseif (strpos($line, 'DATE:') === 0) {
                        $ocr_data['tanggal'] = str_replace('DATE:', '', $line);
                    } elseif (strpos($line, 'CODE:') === 0) {
                        $ocr_data['kode'] = str_replace('CODE:', '', $line);
                    } elseif (strpos($line, 'CONFIDENCE:') === 0) {
                        $ocr_data['confidence'] = (float)str_replace('CONFIDENCE:', '', $line);
                    } elseif (strpos($line, 'TEXT:') === 0) {
                        $ocr_data['text'] = str_replace('TEXT:', '', $line);
                    }
                }
                
                // Update database dengan hasil OCR
                $stmt = $pdo->prepare("
                    UPDATE user_bills 
                    SET ocr_jumlah = ?, 
                        ocr_kode_found = ?, 
                        ocr_date_found = ?, 
                        ocr_confidence = ?, 
                        ocr_details = ?
                    WHERE id = ?
                ");
                
                $kode_found = !empty($ocr_data['kode']) && 
                             stripos($ocr_data['kode'], substr($user_bill['kode_tagihan'], 0, 6)) !== false;
                $date_found = !empty($ocr_data['tanggal']);
                
                $stmt->execute([
                    $ocr_data['jumlah'],
                    $kode_found ? 1 : 0,
                    $date_found ? 1 : 0,
                    $ocr_data['confidence'],
                    json_encode($ocr_data),
                    $user_bill_id
                ]);
                
                $ocr_message = "OCR berhasil dijalankan! Confidence: " . number_format($ocr_data['confidence'], 2) . "%";
                $ocr_message_type = "info";
            } else {
                $ocr_message = "OCR gagal dijalankan. Pastikan Python dan library yang diperlukan sudah terinstall.";
                $ocr_message_type = "error";
            }
        } else {
            $ocr_message = "File bukti pembayaran tidak ditemukan.";
            $ocr_message_type = "error";
        }
    }
}

// Ambil data pembayaran yang menunggu konfirmasi
$stmt = $pdo->prepare("
    SELECT ub.*, b.kode_tagihan, b.jumlah as expected_amount, b.deskripsi, b.tanggal as expected_date,
           u.username, ub.tanggal_upload, ub.ocr_jumlah, ub.ocr_kode_found, ub.ocr_date_found, 
           ub.ocr_confidence, ub.ocr_details
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'
    ORDER BY ub.tanggal_upload DESC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .payment-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .payment-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .payment-body {
            padding: 20px;
        }
        .proof-image {
            max-width: 100%;
            max-height: 400px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .ocr-results {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 15px;
        }
        .match-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-left: 10px;
            text-align: center;
            line-height: 20px;
            font-size: 12px;
            color: white;
        }
        .match-true { background-color: #28a745; }
        .match-false { background-color: #dc3545; }
        .match-partial { background-color: #ffc107; }
        .confidence-bar {
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, #dc3545 0%, #ffc107 50%, #28a745 100%);
            border-radius: 10px;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h5>Admin Panel</h5>
                <nav class="nav flex-column">
                    <a class="nav-link text-white" href="dashboard.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a class="nav-link text-white active" href="konfirmasi.php">
                        <i class="fas fa-check"></i> Konfirmasi
                    </a>
                    <a class="nav-link text-white" href="tagihan.php">
                        <i class="fas fa-file-invoice"></i> Kelola Tagihan
                    </a>
                    <a class="nav-link text-white" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-check-circle"></i> Konfirmasi Pembayaran</h2>
                    <span class="badge bg-warning text-dark fs-6">
                        <?= count($pending_payments) ?> Menunggu Konfirmasi
                    </span>
                </div>
                
                <!-- Alert Messages -->
                <?php if (isset($message)): ?>
                    <div class="alert alert-<?= $message_type === 'success' ? 'success' : ($message_type === 'error' ? 'danger' : 'warning') ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($ocr_message)): ?>
                    <div class="alert alert-<?= $ocr_message_type === 'info' ? 'info' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($ocr_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Payment Cards -->
                <?php if (empty($pending_payments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        <h4 class="mt-3">Tidak ada pembayaran yang menunggu konfirmasi</h4>
                        <p class="text-muted">Semua pembayaran telah diproses.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pending_payments as $payment): ?>
                        <?php
                        $ocr_data = json_decode($payment['ocr_details'], true);
                        $amount_match = $payment['ocr_jumlah'] == $payment['expected_amount'];
                        $code_match = $payment['ocr_kode_found'];
                        $date_match = $payment['ocr_date_found'];
                        ?>
                        
                        <div class="payment-card">
                            <div class="payment-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-1">
                                            <i class="fas fa-user"></i> <?= htmlspecialchars($payment['username']) ?>
                                            <span class="badge bg-secondary ms-2"><?= htmlspecialchars($payment['kode_tagihan']) ?></span>
                                        </h5>
                                        <small class="text-muted">
                                            Upload: <?= date('d/m/Y H:i', strtotime($payment['tanggal_upload'])) ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <div class="btn-group">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_bill_id" value="<?= $payment['id'] ?>">
                                                <button type="submit" name="run_ocr" class="btn btn-info btn-sm">
                                                    <i class="fas fa-eye"></i> Jalankan OCR
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="payment-body">
                                <div class="row">
                                    <!-- Informasi Tagihan -->
                                    <div class="col-md-4">
                                        <h6><i class="fas fa-file-invoice"></i> Informasi Tagihan</h6>
                                        <table class="table table-sm">
                                            <tr>
                                                <td>Kode:</td>
                                                <td><strong><?= htmlspecialchars($payment['kode_tagihan']) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td>Jumlah:</td>
                                                <td><strong>Rp <?= number_format($payment['expected_amount'], 0, ',', '.') ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td>Tanggal:</td>
                                                <td><strong><?= date('d/m/Y', strtotime($payment['expected_date'])) ?></strong></td>
                                            </tr>
                                            <tr>
                                                <td>Deskripsi:</td>
                                                <td><?= htmlspecialchars($payment['deskripsi']) ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                    
                                    <!-- Bukti Pembayaran -->
                                    <div class="col-md-4">
                                        <h6><i class="fas fa-image"></i> Bukti Pembayaran</h6>
                                        <?php if ($payment['bukti_pembayaran']): ?>
                                            <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($payment['bukti_pembayaran']) ?>"
                                                 class="proof-image" alt="Bukti Pembayaran">
                                        <?php else: ?>
                                            <p class="text-muted">Tidak ada bukti pembayaran</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Hasil OCR -->
                                    <div class="col-md-4">
                                        <h6><i class="fas fa-robot"></i> Hasil OCR</h6>
                                        
                                        <?php if ($payment['ocr_confidence'] > 0): ?>
                                            <div class="ocr-results">
                                                <!-- Confidence Score -->
                                                <div class="mb-3">
                                                    <small class="text-muted">Confidence Score:</small>
                                                    <div class="confidence-bar">
                                                        <div class="confidence-fill" style="width: <?= $payment['ocr_confidence'] ?>%"></div>
                                                    </div>
                                                    <small><?= number_format($payment['ocr_confidence'], 2) ?>%</small>
                                                </div>
                                                
                                                <!-- OCR vs Expected -->
                                                <table class="table table-sm">
                                                    <tr>
                                                        <td>Jumlah OCR:</td>
                                                        <td>
                                                            <?php if ($payment['ocr_jumlah']): ?>
                                                                Rp <?= number_format($payment['ocr_jumlah'], 0, ',', '.') ?>
                                                                <span class="match-indicator <?= $amount_match ? 'match-true' : 'match-false' ?>">
                                                                    <i class="fas fa-<?= $amount_match ? 'check' : 'times' ?>"></i>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tidak terdeteksi</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Kode Tagihan:</td>
                                                        <td>
                                                            <?php if ($ocr_data && !empty($ocr_data['kode'])): ?>
                                                                <?= htmlspecialchars($ocr_data['kode']) ?>
                                                                <span class="match-indicator <?= $code_match ? 'match-true' : 'match-false' ?>">
                                                                    <i class="fas fa-<?= $code_match ? 'check' : 'times' ?>"></i>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tidak terdeteksi</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <td>Tanggal OCR:</td>
                                                        <td>
                                                            <?php if ($ocr_data && !empty($ocr_data['tanggal'])): ?>
                                                                <?= htmlspecialchars($ocr_data['tanggal']) ?>
                                                                <span class="match-indicator <?= $date_match ? 'match-true' : 'match-false' ?>">
                                                                    <i class="fas fa-<?= $date_match ? 'check' : 'times' ?>"></i>
                                                                </span>
                                                            <?php else: ?>
                                                                <span class="text-muted">Tidak terdeteksi</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                </table>
                                                
                                                <!-- OCR Text Preview -->
                                                <?php if ($ocr_data && !empty($ocr_data['text'])): ?>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Teks terdeteksi:</small>
                                                        <div class="small text-muted" style="max-height: 100px; overflow-y: auto;">
                                                            <?= htmlspecialchars(substr($ocr_data['text'], 0, 200)) ?>
                                                            <?= strlen($ocr_data['text']) > 200 ? '...' : '' ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-3">
                                                <i class="fas fa-robot text-muted" style="font-size: 2rem;"></i>
                                                <p class="text-muted mt-2">OCR belum dijalankan</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_bill_id" value="<?= $payment['id'] ?>">
                                                <input type="hidden" name="action" value="tolak">
                                                <button type="submit" class="btn btn-danger" 
                                                        onclick="return confirm('Yakin ingin menolak pembayaran ini?')">
                                                    <i class="fas fa-times"></i> Tolak
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_bill_id" value="<?= $payment['id'] ?>">
                                                <input type="hidden" name="action" value="konfirmasi">
                                                <button type="submit" class="btn btn-success" 
                                                        onclick="return confirm('Yakin ingin mengkonfirmasi pembayaran ini?')">
                                                    <i class="fas fa-check"></i> Konfirmasi
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
