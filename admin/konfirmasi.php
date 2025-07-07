<?php
require_once '../config.php';
requireAdmin();

// Fungsi untuk format tanggal Indonesia
function format_tanggal_indo($tanggal) {
    if (empty($tanggal)) return '-';
    
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    if ($timestamp === false) return $tanggal;
    
    $hari = date('d', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Handle konfirmasi/tolak tagihan
// Handle konfirmasi/tolak tagihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status' && isset($_POST['status'], $_POST['user_bill_id'])) {

    $user_bill_id = (int)$_POST['user_bill_id'];
    $new_status = $_POST['status'];

    if (!in_array($new_status, ['konfirmasi', 'tolak'])) {
        $_SESSION['message'] = '❌ Status tidak valid.';
        header('Location: konfirmasi.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah, u.username 
                               FROM user_bills ub 
                               JOIN bills b ON ub.bill_id = b.id 
                               JOIN users u ON ub.user_id = u.id 
                               WHERE ub.id = ? AND ub.status = 'menunggu_konfirmasi'");
        $stmt->execute([$user_bill_id]);
        $bill = $stmt->fetch();

        if (!$bill) {
            $_SESSION['message'] = '❌ Tagihan tidak ditemukan atau sudah diproses.';
            header('Location: konfirmasi.php');
            exit;
        }

        $pdo->prepare("UPDATE user_bills SET status = ? WHERE id = ?")->execute([$new_status, $user_bill_id]);

        if ($new_status === 'konfirmasi') {
            $stmt = $pdo->prepare("INSERT INTO tagihan_oke (user_bill_id, kode_tagihan, jumlah, tanggal, user_id, qr_code_hash, bukti_pembayaran) 
                                   VALUES (?, ?, ?, CURDATE(), ?, ?, ?)");
            $stmt->execute([
                $user_bill_id,
                $bill['kode_tagihan'],
                $bill['jumlah'],
                $bill['user_id'],
                $bill['qr_code_hash'],
                $bill['bukti_pembayaran']
            ]);
            $_SESSION['message'] = "✅ Pembayaran dari {$bill['username']} untuk tagihan {$bill['kode_tagihan']} berhasil dikonfirmasi.";
        } else {
            $_SESSION['message'] = "❌ Pembayaran dari {$bill['username']} untuk tagihan {$bill['kode_tagihan']} ditolak.";
        }
    } catch (PDOException $e) {
        $_SESSION['message'] = '❌ Terjadi kesalahan: ' . $e->getMessage();
    }
    header('Location: konfirmasi.php');
    exit;
}

// Jalankan OCR untuk semua tagihan menunggu konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_all_ocr'])) {
    $stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL AND ub.ocr_details IS NULL");
    $bills = $stmt->fetchAll();
    
    $processed = 0;
    $failed = 0;
    
    foreach ($bills as $bill) {
        $ocr_details = json_decode($bill['ocr_details'] ?? '', true);
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        if (file_exists($image_path)) {
            $command = "HOME=/tmp python3 " . escapeshellarg(__DIR__ . '/ocr.py') . " " . escapeshellarg($image_path);
            $output = shell_exec($command . ' 2>&1');
            
            $last_json_start = strrpos($output, '{');
            if ($last_json_start !== false) {
                $json_string = substr($output, $last_json_start);
                $result = json_decode($json_string, true);
                
                if (is_array($result)) {
                    $ocr_jumlah = $result['jumlah'] ?? null;
                    $ocr_kode_found = isset($result['kode_tagihan']) && $result['kode_tagihan'] !== '' ? 1 : 0;
                    $ocr_date_found = isset($result['tanggal']) && $result['tanggal'] !== '' ? 1 : 0;
                    $ocr_confidence = 0.0;
                    $ocr_details = json_encode([
                        'extracted_text' => $result['extracted_text'] ?? '',
                        'normalized_text' => $result['normalized_text'] ?? '',
                        'extracted_code' => $result['kode_tagihan'] ?? '',
                        'extracted_date' => $result['tanggal'] ?? ''
                    ]);
                    
                    $update = $pdo->prepare("UPDATE user_bills SET ocr_jumlah = ?, ocr_kode_found = ?, ocr_date_found = ?, ocr_confidence = ?, ocr_details = ? WHERE id = ?");
                    $update->execute([$ocr_jumlah, $ocr_kode_found, $ocr_date_found, $ocr_confidence, $ocr_details, $bill['id']]);
                    $processed++;
                } else {
                    $failed++;
                }
            } else {
                $failed++;
            }
        } else {
            $failed++;
        }
    }
    
    $_SESSION['message'] = "✅ OCR selesai dijalankan. Berhasil: $processed, Gagal: $failed";
    header('Location: konfirmasi.php');
    exit;
}

// Jalankan OCR jika diminta untuk satu tagihan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_ocr'], $_POST['user_bill_id'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch();

    if ($bill && $bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        $command = "HOME=/tmp python3 " . escapeshellarg(__DIR__ . '/ocr.py') . " " . escapeshellarg($image_path);

        $output = shell_exec($command . ' 2>&1');
        
        $last_json_start = strrpos($output, '{');
        if ($last_json_start !== false) {
            $json_string = substr($output, $last_json_start);
            $result = json_decode($json_string, true);
        } else {
            $result = null;
        }

        if (is_array($result)) {
            $ocr_jumlah = $result['jumlah'] ?? null;
            $ocr_kode_found = isset($result['kode_tagihan']) && $result['kode_tagihan'] !== '' ? 1 : 0;
            $ocr_date_found = isset($result['tanggal']) && $result['tanggal'] !== '' ? 1 : 0;
            $ocr_confidence = 0.0;
            $ocr_details = json_encode([
                'extracted_text' => $result['extracted_text'] ?? '',
                'normalized_text' => $result['normalized_text'] ?? '',
                'extracted_code' => $result['kode_tagihan'] ?? '',
                'extracted_date' => $result['tanggal'] ?? ''
            ]);

            $update = $pdo->prepare("UPDATE user_bills SET ocr_jumlah = ?, ocr_kode_found = ?, ocr_date_found = ?, ocr_confidence = ?, ocr_details = ? WHERE id = ?");
            $update->execute([$ocr_jumlah, $ocr_kode_found, $ocr_date_found, $ocr_confidence, $ocr_details, $user_bill_id]);

            $_SESSION['message'] = '✅ OCR berhasil dijalankan.';
        } else {
            $_SESSION['message'] = '❌ OCR gagal memproses gambar.';
        }
    } else {
        $_SESSION['message'] = '❌ Data tagihan tidak valid atau tidak ada bukti pembayaran.';
    }

    header('Location: konfirmasi.php');
    exit;
}

// Ambil statistik untuk dashboard cards
$stats = [
    'menunggu_konfirmasi' => $pdo->query("SELECT COUNT(*) FROM user_bills WHERE status = 'menunggu_konfirmasi'")->fetchColumn(),
    'sudah_ocr' => $pdo->query("SELECT COUNT(*) FROM user_bills WHERE status = 'menunggu_konfirmasi' AND ocr_details IS NOT NULL")->fetchColumn(),
    'total_nominal' => $pdo->query("SELECT SUM(b.jumlah) FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.status = 'menunggu_konfirmasi'")->fetchColumn()
];

// Ambil tagihan menunggu konfirmasi dengan semua data yang diperlukan
$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tanggal as tenggat_waktu, u.username,
    CASE 
        WHEN ub.tanggal_upload IS NULL THEN 'Belum Upload'
        WHEN ub.tanggal_upload <= b.tanggal THEN 'Tepat Waktu'
        ELSE 'Terlambat'
    END as status_ketepatan,
    CASE 
        WHEN ub.tanggal_upload IS NULL THEN NULL
        ELSE DATEDIFF(ub.tanggal_upload, b.tanggal)
    END as selisih_hari
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'
    ORDER BY ub.tanggal_upload DESC");
$bills = $stmt->fetchAll();

// Fungsi untuk menentukan kesesuaian OCR
function checkOCRMatch($bill) {
    $ocr_details = json_decode($bill['ocr_details'], true);
    if (!$ocr_details) return 'Belum OCR';
    
    $amount_match = false;
    $date_match = false;
    
    // Cek kesesuaian jumlah (toleransi 5%)
    if ($bill['ocr_jumlah'] && $bill['jumlah']) {
        $tolerance = $bill['jumlah'] * 0.05;
        $amount_match = abs($bill['ocr_jumlah'] - $bill['jumlah']) <= $tolerance;
    }
    
    // Cek kesesuaian tanggal (apakah upload sebelum tenggat waktu)
    if ($bill['tanggal_upload'] && $bill['tenggat_waktu']) {
        $date_match = strtotime($bill['tanggal_upload']) <= strtotime($bill['tenggat_waktu']);
    }
    
    if ($amount_match && $date_match) {
        return 'Sesuai';
    } elseif ($amount_match && !$date_match) {
        return 'Terlambat';
    } elseif (!$amount_match && $date_match) {
        return 'Nominal Tidak Sesuai';
    } else {
        return 'Tidak Sesuai';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: #2c3e50 !important;
        }
        
        /* Layout Container */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding: 0;
            margin: 0;
            width: 100%;
        }
        
        /* Full Width Content */
        .full-width-content {
            width: 100%;
            margin: 0;
            padding: 0 2rem;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #4f46e5 0%, #581c87 100%);
            color: white;
            padding: 2rem;
            margin: 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Navigation Tabs */
        .nav-tabs {
            background: white;
            border-bottom: 2px solid #e2e8f0;
            padding: 0 2rem;
            display: flex;
            gap: 0;
            margin: 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .nav-tab {
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-tab:hover {
            color: #4f46e5;
            background: #f1f5f9;
            text-decoration: none;
        }

        .nav-tab.active {
            color: #4f46e5;
            border-bottom-color: #4f46e5;
            background: #f1f5f9;
        }
        
        /* Statistics Grid */
        .stats-grid {
            display: grid;         
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-card.pending {
            border-left: 4px solid #4f46e5;
        }

        .stat-card.ocr {
            border-left: 4px solid #10b981;
        }

        .stat-card.nominal {
            border-left: 4px solid #f59e0b;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .stat-label {
            color: #64748b;
            font-size: 1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        /* OCR Action Card */
        .ocr-action-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin: 2rem 0;
            padding: 2rem;
            text-align: center;
        }

        .btn-ocr {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 12px;
            padding: 15px 30px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-ocr:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin: 2rem 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #374151;
            padding: 1rem 0.75rem;
            font-size: 0.875rem;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .table tbody tr:hover {
            background-color: #f8fafc;
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
            border-radius: 20px;
            font-weight: 500;
        }

        .proof-image {
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s;
        }

        .proof-image:hover {
            transform: scale(1.05);
        }

        .avatar-sm {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Alert Styling */
        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }

        .alert-success {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: white;
        }

        /* Action Form Styling */
        .action-form {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .action-form .form-select {
            min-width: 120px;
        }

        .btn-process {
            white-space: nowrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            margin: 2rem 0;
        }

        .empty-state i {
            font-size: 4rem;
            color: #9ca3af;
            margin-bottom: 1rem;
        }

        .empty-state h4 {
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: #9ca3af;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .full-width-content {
                padding: 0 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.5rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .nav-tabs {
                padding: 0 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .action-form {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="full-width-content">
                    <h1>
                        <i class="fas fa-check-circle"></i>
                        Konfirmasi Pembayaran
                    </h1>
                    <p>Kelola dan konfirmasi pembayaran tagihan warga dengan bantuan teknologi OCR</p>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="tagihan.php" class="nav-tab">
                    <i class="fas fa-plus-circle"></i> Buat Tagihan
                </a>
                <a href="konfirmasi.php" class="nav-tab active">
                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                </a>
            </div>

            <!-- Main Content -->
            <div class="full-width-content">
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['message'])): ?>
                    <?php 
                    $is_success = strpos($_SESSION['message'], '✅') !== false;
                    $alert_class = $is_success ? 'alert-success' : 'alert-danger';
                    ?>
                    <div class="alert <?= $alert_class ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $is_success ? 'check-circle' : 'exclamation-circle' ?> me-2"></i>
                        <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Section -->
                <div class="stats-grid">
                    <div class="stat-card pending">
                        <div class="stat-number"><?php echo $stats['menunggu_konfirmasi']; ?></div>
                        <div class="stat-label">
                            <i class="fas fa-clock"></i>
                            Menunggu Konfirmasi
                        </div>
                    </div>
                    
                    <div class="stat-card ocr">
                        <div class="stat-number"><?php echo $stats['sudah_ocr']; ?></div>
                        <div class="stat-label">
                            <i class="fas fa-eye"></i>
                            Sudah di-OCR
                        </div>
                    </div>
                    
                    <div class="stat-card nominal">
                        <div class="stat-number">Rp <?php echo number_format($stats['total_nominal'], 0, ',', '.'); ?></div>
                        <div class="stat-label">
                            <i class="fas fa-money-bill-wave"></i>
                            Total Nominal
                        </div>
                    </div>
                </div>

                <!-- OCR Action Card -->
                <div class="ocr-action-card">
                    <h5 class="mb-3">
                        <i class="fas fa-robot me-2"></i>Pemrosesan OCR Otomatis
                    </h5>
                    <p class="text-muted mb-4">
                        Jalankan OCR untuk semua bukti pembayaran yang belum diproses
                    </p>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="run_all_ocr" class="btn btn-ocr" 
                                onclick="return confirm('Yakin ingin menjalankan OCR untuk semua tagihan?')">
                            <i class="fas fa-play me-2"></i>Jalankan OCR Semua
                        </button>
                    </form>
                </div>

                <!-- Main Table -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Kode Tagihan</th>
                                    <th>Deskripsi</th>
                                    <th>Jumlah</th>
                                    <th>Hasil OCR</th>
                                    <th>Status Kesesuaian</th>
                                    <th>Tanggal</th>
                                    <th>Bukti</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bills as $bill): ?>
                                    <?php 
                                    $ocr_details = json_decode($bill['ocr_details'], true);
                                    $match_status = checkOCRMatch($bill);
                                    $is_suitable = ($match_status === 'Sesuai');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle me-3">
                                                    <?= strtoupper(substr($bill['username'], 0, 1)) ?>
                                                </div>
                                                <strong><?= htmlspecialchars($bill['username']) ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($bill['kode_tagihan']) ?></span>
                                        </td>
                                        <td>
                                            <div style="max-width: 200px;">
                                                <?= nl2br(htmlspecialchars(substr($bill['deskripsi'], 0, 100))) ?>
                                                <?= strlen($bill['deskripsi']) > 100 ? '...' : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong class="text-success">Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></strong>
                                        </td>
                                        <td>
    <?php if ($ocr_details): ?>
        <div class="small">
            <strong>Jumlah:</strong> 
            <?php if ($bill['ocr_jumlah'] !== null): ?>
                <span class="<?= ($bill['ocr_jumlah'] == $bill['jumlah']) ? 'text-success' : 'text-warning' ?>">
                    Rp <?= number_format($bill['ocr_jumlah'], 0, ',', '.') ?>
                </span>
            <?php else: ?>
                <span class="text-danger">❌ Tidak terbaca</span>
            <?php endif; ?>
            <br>
            
            <strong>Kode:</strong> 
            <?php if (!empty($ocr_details['extracted_code'])): ?>
                <span class="<?= ($ocr_details['extracted_code'] == $bill['kode_tagihan']) ? 'text-success' : 'text-warning' ?>">
                    <?= htmlspecialchars($ocr_details['extracted_code']) ?>
                </span>
            <?php else: ?>
                <span class="text-danger">❌ Tidak terbaca</span>
            <?php endif; ?>
            <br>
            
            <strong>Tanggal:</strong>
            <?php if (!empty($ocr_details['extracted_date'])): ?>
                <?= format_tanggal_indo($ocr_details['extracted_date']) ?>
            <?php else: ?>
                <span class="text-danger">❌ Tidak terbaca</span>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <span class="text-muted">❌ Belum diproses atau gagal OCR</span>
    <?php endif; ?>
</td>

<td>
    <?php
    $badge_class = match($match_status) {
        'Sesuai' => 'bg-success',
        'Terlambat' => 'bg-warning',
        'Nominal Tidak Sesuai', 'Tidak Sesuai' => 'bg-danger',
        default => 'bg-secondary'
    };
    ?>
    <span class="status-badge <?= $badge_class ?>"><?= $match_status ?></span>
</td>

<td>
    <div class="small">
        <strong>Upload:</strong> <?= format_tanggal_indo($bill['tanggal_upload']) ?><br>
        <strong>Tenggat:</strong> <?= format_tanggal_indo($bill['tenggat_waktu']) ?><br>
        <?php if ($bill['selisih_hari'] !== null): ?>
            <span class="<?= $bill['selisih_hari'] <= 0 ? 'text-success' : 'text-danger' ?>">
                <?= $bill['selisih_hari'] <= 0 ? 'Tepat waktu' : $bill['selisih_hari'] . ' hari terlambat' ?>
            </span>
        <?php endif; ?>
    </div>
</td>

<td>
    <?php if ($bill['bukti_pembayaran']): ?>
        <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" 
             alt="Bukti Pembayaran" 
             class="proof-image"
             style="width: 60px; height: 60px; object-fit: cover;"
             data-bs-toggle="modal" 
             data-bs-target="#imageModal<?= $bill['id'] ?>">
    <?php else: ?>
        <span class="text-muted">Tidak ada</span>
    <?php endif; ?>
</td>

<td>
    <div class="action-form">
        <!-- OCR Button -->
<?php if ($bill['bukti_pembayaran']): ?>
    <form method="POST" style="display: inline;">
        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
        <button type="submit" name="run_ocr" class="btn btn-sm btn-outline-primary" title="Jalankan OCR ulang">
            <i class="fas fa-eye"></i>
        </button>
    </form>
    <?php if ($ocr_details): ?>
        <div class="text-muted small mt-1">✅ Sudah OCR</div>
    <?php endif; ?>
<?php endif; ?>

        
        <!-- Tombol Konfirmasi & Tolak -->
        <div class="d-flex gap-2 mt-1">
            <!-- Konfirmasi -->
            <form method="POST" action="konfirmasi.php" class="d-inline">
                <input type="hidden" name="user_bill_id" value="<?= htmlspecialchars($bill['id']) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="konfirmasi">
                <button type="submit" class="btn btn-sm btn-outline-success"
                        onclick="return confirm('Yakin ingin konfirmasi pembayaran ini?')">
                    <i class="fas fa-check"></i>
                </button>
            </form>

            <!-- Tolak -->
            <form method="POST" action="konfirmasi.php" class="d-inline">
                <input type="hidden" name="user_bill_id" value="<?= htmlspecialchars($bill['id']) ?>">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="status" value="tolak">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Yakin ingin menolak pembayaran ini?')">
                    <i class="fas fa-times"></i>
                </button>
            </form>
        </div>
    </div>
</td>
                                    </tr>
                                    
                                    <!-- Modal for image preview -->
                                    <?php if ($bill['bukti_pembayaran']): ?>
                                        <div class="modal fade" id="imageModal<?= $bill['id'] ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Bukti Pembayaran - <?= htmlspecialchars($bill['username']) ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center">
                                                        <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" 
                                                             alt="Bukti Pembayaran" 
                                                             class="img-fluid rounded">
                                                        
                                                        <!-- OCR Details -->
                                                        <?php if ($ocr_details): ?>
                                                            <div class="mt-4 text-start">
                                                                <h6><i class="fas fa-robot me-2"></i>Detail OCR:</h6>
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <small class="text-muted">Teks yang diekstrak:</small>
                                                                        <div class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                                                                            <pre class="small mb-0"><?= htmlspecialchars($ocr_details['extracted_text'] ?? 'Tidak ada') ?></pre>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <small class="text-muted">Teks yang dinormalisasi:</small>
                                                                        <div class="border rounded p-2 bg-light" style="max-height: 200px; overflow-y: auto;">
                                                                            <pre class="small mb-0"><?= htmlspecialchars($ocr_details['normalized_text'] ?? 'Tidak ada') ?></pre>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="row mt-3">
                                                                    <div class="col-md-4">
                                                                        <small class="text-muted">Jumlah yang ditemukan:</small>
                                                                        <div class="fw-bold <?= ($bill['ocr_jumlah'] == $bill['jumlah']) ? 'text-success' : 'text-warning' ?>">
                                                                            Rp <?= number_format($bill['ocr_jumlah'] ?? 0, 0, ',', '.') ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <small class="text-muted">Kode yang ditemukan:</small>
                                                                        <div class="fw-bold <?= ($ocr_details['extracted_code'] == $bill['kode_tagihan']) ? 'text-success' : 'text-warning' ?>">
                                                                            <?= htmlspecialchars($ocr_details['extracted_code'] ?? '-') ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-4">
                                                                        <small class="text-muted">Tanggal yang ditemukan:</small>
                                                                        <div class="fw-bold">
                                                                            <?= format_tanggal_indo($ocr_details['extracted_date'] ?? '-') ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Empty State -->
                <?php if (empty($bills)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>Tidak Ada Tagihan</h4>
                        <p>Belum ada tagihan yang menunggu konfirmasi.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // 1. Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(function (alert) {
            setTimeout(function () {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // 2. Enhanced image modal functionality (zoom on click)
        document.querySelectorAll('[id^="imageModal"]').forEach(function (modal) {
            modal.addEventListener('shown.bs.modal', function () {
                const img = modal.querySelector('img');
                if (img) {
                    img.style.cursor = 'zoom-in';
                    img.onclick = function () {
                        if (img.style.transform === 'scale(1.5)') {
                            img.style.transform = 'scale(1)';
                            img.style.cursor = 'zoom-in';
                        } else {
                            img.style.transform = 'scale(1.5)';
                            img.style.cursor = 'zoom-out';
                        }
                    };
                }
            });
        });

        // 3. Form submit loading state
        document.querySelectorAll('form').forEach(function (form) {
            form.addEventListener('submit', function () {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalHTML = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';

                    // Fallback: re-enable button after 3s
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHTML;
                    }, 3000);
                }
            });
        });

        // 4. Auto-submit on checkbox konfirmasi
        document.querySelectorAll('.confirm-checkbox').forEach(function (checkbox) {
            checkbox.addEventListener('change', function () {
                const billId = this.getAttribute('data-bill-id');
                const form = document.getElementById('form-konfirmasi-' + billId);
                if (this.checked && form) {
                    if (confirm('Yakin ingin mengkonfirmasi pembayaran ini?')) {
                        form.submit();
                    } else {
                        this.checked = false;
                    }
                }
            });
        });

        // 5. Keyboard shortcut Ctrl+R to refresh
        document.addEventListener('keydown', function (e) {
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                window.location.reload();
            }
        });
    });

    // 6. Dynamic confirmation (if you use it from PHP inline)
    function confirmAction(action, billCode, username) {
        const actionText = action === 'konfirmasi' ? 'mengkonfirmasi' : 'menolak';
        return confirm(`Yakin ingin ${actionText} pembayaran dari ${username} untuk tagihan ${billCode}?`);
    }
</script>

</body>
</html>
