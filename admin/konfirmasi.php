<?php
require_once '../config.php';
requireAdmin();

// Jalankan OCR untuk semua tagihan menunggu konfirmasi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_all_ocr'])) {
    $stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.status = 'menunggu_konfirmasi' AND ub.bukti_pembayaran IS NOT NULL AND ub.ocr_details IS NULL");
    $bills = $stmt->fetchAll();
    
    $processed = 0;
    $failed = 0;
    
    foreach ($bills as $bill) {
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
$stmt = $pdo->query("SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tenggat_waktu, 
    ub.tanggal as tanggal_kirim,
    b.tanggal as tenggat,
    u.username,
    CASE 
        WHEN ub.tanggal_upload IS NULL THEN 'Belum Upload'
        WHEN ub.tanggal_upload <= b.tenggat_waktu THEN 'Tepat Waktu'
        ELSE 'Terlambat'
    END as status_ketepatan,
    CASE 
        WHEN ub.tanggal_upload IS NULL THEN NULL
        ELSE DATEDIFF(ub.tanggal_upload, b.tenggat_waktu)
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
        .navbar-brand {
            font-weight: bold;
            color: #2c3e50 !important;
        }
        .stat-card {
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-icon {
            font-size: 2.5rem;
            opacity: 0.8;
        }
        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .btn-ocr {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            border-radius: 8px;
            padding: 12px 24px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-ocr:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-gradient-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .bg-gradient-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
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
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-shield-alt me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
                <a class="nav-link active" href="konfirmasi.php">
                    <i class="fas fa-check-circle me-1"></i>Konfirmasi
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-check-circle me-3"></i>Konfirmasi Pembayaran
                    </h1>
                    <p class="mb-0 mt-2 opacity-75">Kelola dan verifikasi pembayaran tagihan warga</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex justify-content-end">
                        <div class="text-white">
                            <small>Terakhir diperbarui</small><br>
                            <strong><?= date('d M Y, H:i') ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-primary text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0"><?= number_format($stats['menunggu_konfirmasi']) ?></h3>
                                <p class="mb-0">Menunggu Konfirmasi</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-clock stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-success text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0"><?= number_format($stats['sudah_ocr']) ?></h3>
                                <p class="mb-0">Sudah di-OCR</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-eye stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card bg-gradient-warning text-white">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="mb-0">Rp <?= number_format($stats['total_nominal'] ?? 0, 0, ',', '.') ?></h3>
                                <p class="mb-0">Total Nominal</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-money-bill-wave stat-icon"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- OCR Action Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <h5 class="card-title">
                    <i class="fas fa-robot me-2"></i>Pemrosesan OCR Otomatis
                </h5>
                <p class="text-muted mb-3">
                    Jalankan OCR untuk semua bukti pembayaran yang belum diproses
                </p>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="run_all_ocr" class="btn btn-ocr" 
                            onclick="return confirm('Yakin ingin menjalankan OCR untuk semua tagihan?')">
                        <i class="fas fa-play me-2"></i>Jalankan OCR Semua
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
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
                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px;">
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
                                            <span class="<?= ($bill['ocr_jumlah'] == $bill['jumlah']) ? 'text-success' : 'text-warning' ?>">
                                                Rp <?= number_format($bill['ocr_jumlah'] ?? 0, 0, ',', '.') ?>
                                            </span><br>
                                            <strong>Kode:</strong> 
                                            <span class="<?= ($ocr_details['extracted_code'] == $bill['kode_tagihan']) ? 'text-success' : 'text-warning' ?>">
                                                <?= htmlspecialchars($ocr_details['extracted_code'] ?? '-') ?>
                                            </span><br>
                                            <strong>Tanggal:</strong> <?= htmlspecialchars($ocr_details['extracted_date'] ?? '-') ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Belum diproses</span>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                        <button name="run_ocr" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-search"></i> OCR
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php
                                    $badge_class = match($match_status) {
                                        'Sesuai' => 'bg-success',
                                        'Terlambat' => 'bg-warning',
                                        'Tidak Sesuai' => 'bg-danger',
                                        'Nominal Tidak Sesuai' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badge_class ?> status-badge">
                                        <?= $match_status ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="small">
                                        <strong>Tenggat:</strong><br>
                                        <?= date('d M Y', strtotime($bill['tenggat_waktu'])) ?><br>
                                        <strong>Terkirim:</strong><br>
                                        <?php if ($bill['tanggal_kirim']): ?>
                                            <span class="text-info">
                                                <?= date('d M Y', strtotime($bill['tanggal_kirim'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Belum terkirim</span>
                                        <?php endif; ?><br>
                                        <strong>Upload:</strong><br>
                                        <?php if ($bill['tanggal_upload']): ?>
                                            <span class="<?= ($bill['status_ketepatan'] === 'Tepat Waktu') ? 'text-success' : 'text-danger' ?>">
                                                <?= date('d M Y H:i', strtotime($bill['tanggal_upload'])) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Belum upload</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($bill['bukti_pembayaran']): ?>
                                        <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" 
                                             class="proof-image" 
                                             width="80" 
                                             height="80" 
                                             style="object-fit: cover;"
                                             onclick="window.open(this.src, '_blank')">
                                    <?php else: ?>
                                        <span class="text-muted">Belum upload</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" action="proses_konfirmasi.php">
                                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                        <select name="status" class="form-select form-select-sm mb-2">
                                            <?php if ($is_suitable): ?>
                                                <option value="konfirmasi" selected>Konfirmasi</option>
                                                <option value="tolak">Tolak</option>
                                            <?php else: ?>
                                                <option value="tolak" selected>Tolak</option>
                                                <option value="konfirmasi">Konfirmasi</option>
                                            <?php endif; ?>
                                        </select>
                                        <button class="btn btn-sm <?= $is_suitable ? 'btn-success' : 'btn-warning' ?> w-100" 
                                                onclick="return confirm('Yakin ingin memproses ini?')">
                                            <i class="fas fa-check"></i> Proses
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (empty($bills)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">Tidak ada tagihan menunggu konfirmasi</h4>
                <p class="text-muted">Semua pembayaran telah diproses</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
