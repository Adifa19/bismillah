<?php
require_once '../config.php';
requireAdmin();

// Fungsi untuk cek status Midtrans
function getMidtransStatus($order_id) {
    if (empty($order_id)) return null;
    
    $server_key = MIDTRANS_SERVER_KEY;
    $url = MIDTRANS_IS_PRODUCTION ? 
        "https://api.midtrans.com/v2/{$order_id}/status" : 
        "https://api.sandbox.midtrans.com/v2/{$order_id}/status";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($server_key . ':')
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

// Fungsi untuk format status Midtrans
function formatMidtransStatus($status) {
    if (!$status) return '<span class="badge badge-tidak-ada">Tidak Ada</span>';
    
    $status_map = [
        'capture' => ['text' => 'Berhasil', 'class' => 'badge-success', 'icon' => 'check-circle'],
        'settlement' => ['text' => 'Selesai', 'class' => 'badge-success', 'icon' => 'check-circle'],
        'pending' => ['text' => 'Pending', 'class' => 'badge-warning', 'icon' => 'clock'],
        'deny' => ['text' => 'Ditolak', 'class' => 'badge-danger', 'icon' => 'times-circle'],
        'cancel' => ['text' => 'Dibatalkan', 'class' => 'badge-secondary', 'icon' => 'ban'],
        'expire' => ['text' => 'Kadaluarsa', 'class' => 'badge-danger', 'icon' => 'exclamation-triangle'],
        'failure' => ['text' => 'Gagal', 'class' => 'badge-danger', 'icon' => 'times-circle'],
        'authorize' => ['text' => 'Otorisasi', 'class' => 'badge-info', 'icon' => 'shield-alt']
    ];
    
    $config = $status_map[strtolower($status)] ?? ['text' => ucfirst($status), 'class' => 'badge-secondary', 'icon' => 'question'];
    
    return sprintf('<span class="badge %s"><i class="fas fa-%s me-1"></i>%s</span>', 
        $config['class'], $config['icon'], $config['text']);
}

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

// Fungsi untuk format tanggal Indonesia dengan waktu
function format_tanggal_waktu_indo($tanggal) {
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
    $jam = date('H:i', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun . ' ' . $jam;
}

// Proses refresh status Midtrans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_midtrans'], $_POST['user_bill_id'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];
    
    // Ambil data order_id dari database
    $stmt = $pdo->prepare("SELECT midtrans_order_id FROM user_bills WHERE id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch();
    
    if ($bill && $bill['midtrans_order_id']) {
        $midtrans_status = getMidtransStatus($bill['midtrans_order_id']);
        
        if ($midtrans_status) {
            $status = $midtrans_status['transaction_status'] ?? null;
            $payment_type = $midtrans_status['payment_type'] ?? null;
            $transaction_time = $midtrans_status['transaction_time'] ?? null;
            $settlement_time = $midtrans_status['settlement_time'] ?? null;
            
            // Update status Midtrans di database
            $update = $pdo->prepare("UPDATE user_bills SET 
                midtrans_status = ?, 
                midtrans_payment_type = ?, 
                midtrans_transaction_time = ?, 
                midtrans_settlement_time = ?,
                midtrans_last_check = NOW()
                WHERE id = ?");
            $update->execute([$status, $payment_type, $transaction_time, $settlement_time, $user_bill_id]);
            
            $_SESSION['message'] = '✅ Status Midtrans berhasil diperbarui: ' . ucfirst($status);
        } else {
            $_SESSION['message'] = '❌ Gagal mendapatkan status dari Midtrans.';
        }
    } else {
        $_SESSION['message'] = '❌ Order ID Midtrans tidak ditemukan.';
    }
    
    header('Location: konfirmasi.php');
    exit;
}

// Proses konfirmasi/tolak pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['proses_konfirmasi'], $_POST['user_bill_id'], $_POST['status'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];
    $status = $_POST['status'];
    
    if (in_array($status, ['konfirmasi', 'tolak'])) {
        $stmt = $pdo->prepare("UPDATE user_bills SET status = ? WHERE id = ?");
        if ($stmt->execute([$status, $user_bill_id])) {
            if ($status === 'konfirmasi') {
                $_SESSION['message'] = '✅ Pembayaran berhasil dikonfirmasi.';
            } else {
                $_SESSION['message'] = '❌ Pembayaran berhasil ditolak.';
            }
        } else {
            $_SESSION['message'] = '❌ Gagal memproses pembayaran.';
        }
    } else {
        $_SESSION['message'] = '❌ Status tidak valid.';
    }
    
    header('Location: konfirmasi.php');
    exit;
}

// Jalankan OCR jika diminta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_ocr'], $_POST['user_bill_id'])) {
    $user_bill_id = (int)$_POST['user_bill_id'];

    $stmt = $pdo->prepare("SELECT ub.*, b.kode_tagihan, b.jumlah FROM user_bills ub JOIN bills b ON ub.bill_id = b.id WHERE ub.id = ?");
    $stmt->execute([$user_bill_id]);
    $bill = $stmt->fetch();

    if ($bill && $bill['bukti_pembayaran']) {
        $image_path = '../warga/uploads/bukti_pembayaran/' . $bill['bukti_pembayaran'];
        $command = "HOME=/tmp python3 " . escapeshellarg(__DIR__ . '/ocr.py') . " " . escapeshellarg($image_path);

        $output = shell_exec($command . ' 2>&1');
        file_put_contents(__DIR__ . '/ocr_debug.txt', $output);

        $last_json_start = strrpos($output, '{');
        if ($last_json_start !== false) {
            $json_string = substr($output, $last_json_start);
            file_put_contents(__DIR__ . '/ocr_debug_json.txt', $json_string);
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

// Ambil statistik dengan Midtrans
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_menunggu,
        SUM(CASE WHEN ocr_details IS NOT NULL AND ocr_details != '' THEN 1 ELSE 0 END) as total_ocr_processed,
        SUM(CASE WHEN midtrans_status IN ('settlement', 'capture') THEN 1 ELSE 0 END) as total_midtrans_success,
        SUM(b.jumlah) as total_nominal
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    WHERE ub.status = 'menunggu_konfirmasi'
")->fetch();

// Ambil tagihan menunggu konfirmasi dengan detail lengkap termasuk Midtrans
$stmt = $pdo->query("
    SELECT 
        ub.id,
        ub.tanggal_upload,
        ub.bukti_pembayaran,
        ub.ocr_jumlah,
        ub.ocr_kode_found,
        ub.ocr_date_found,
        ub.ocr_details,
        ub.tanggal AS tanggal_kirim,
        ub.midtrans_order_id,
        ub.midtrans_status,
        ub.midtrans_payment_type,
        ub.midtrans_transaction_time,
        ub.midtrans_settlement_time,
        ub.midtrans_last_check,
        b.kode_tagihan,
        b.jumlah,
        b.deskripsi,
        b.tanggal AS tenggat,
        b.tenggat_waktu,
        u.username,
        
        -- Hitung selisih hari upload vs tenggat waktu
        CASE 
            WHEN ub.tanggal_upload IS NULL THEN NULL
            ELSE DATEDIFF(ub.tanggal_upload, b.tenggat_waktu)
        END AS selisih_hari,
        -- Status ketepatan upload
        CASE 
            WHEN ub.tanggal_upload IS NULL THEN 'Belum Upload'
            WHEN ub.tanggal_upload <= b.tenggat_waktu THEN 'Tepat Waktu'
            ELSE 'Terlambat'
        END AS status_ketepatan
        
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'
    ORDER BY ub.tanggal_upload DESC
");
$bills = $stmt->fetchAll();

// Fungsi untuk menentukan status kesesuaian
function getMatchStatus($bill) {
    $ocr_details = json_decode($bill['ocr_details'], true);
    if (!$ocr_details) return 'Belum OCR';
    
    $kode_match = ($ocr_details['extracted_code'] ?? '') === $bill['kode_tagihan'];
    $jumlah_match = ($bill['ocr_jumlah'] ?? 0) == $bill['jumlah'];
    
    $total_checks = 0;
    $passed_checks = 0;
    
    // Cek kode tagihan
    if (!empty($ocr_details['extracted_code'])) {
        $total_checks++;
        if ($kode_match) $passed_checks++;
    }
    
    // Cek jumlah
    if ($bill['ocr_jumlah']) {
        $total_checks++;
        if ($jumlah_match) $passed_checks++;
    }
    
    if ($total_checks === 0) return 'Belum OCR';
    if ($passed_checks === $total_checks) return 'Sesuai';
    return 'Tidak Sesuai';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
.main-container {
    max-width: 1600px; /* Increased width for more columns */
    margin: 0 auto;
    padding: 20px;
}
.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px;
    border-radius: 15px;
    margin-bottom: 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
.stats-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    transition: transform 0.3s ease;
    border: none;
}
.stats-card:hover {
    transform: translateY(-5px);
}
.stats-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 10px;
}
.stats-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.table-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.08);
}
.table th {
    background-color: #f8f9fa;
    border: none;
    font-weight: 600;
    color: #495057;
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px 10px;
}
.table td {
    border: none;
    padding: 15px 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f1f3f4;
}
.table tbody tr:hover {
    background-color: #f8f9fa;
}

/* === Badge Status (STRONG Colors) === */
.badge {
    padding: 0.5em 0.75em;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 1rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.badge-sesuai {
    background-color: #16a34a; /* green-600 */
    color: white;
}
.badge-tidak-sesuai {
    background-color: #dc2626; /* red-600 */
    color: white;
}
.badge-belum-ocr {
    background-color: #facc15; /* yellow-400 */
    color: #78350f;
}

.badge-tepat-waktu {
    background-color: #22c55e; /* green-500 */
    color: white;
}
.badge-terlambat {
    background-color: #ef4444; /* red-500 */
    color: white;
}
.badge-belum-upload {
    background-color: #a1a1aa; /* zinc-400 */
    color: white;
}

/* === Midtrans Status Badges === */
.badge-success {
    background-color: #059669; /* emerald-600 */
    color: white;
}
.badge-warning {
    background-color: #d97706; /* amber-600 */
    color: white;
}
.badge-danger {
    background-color: #dc2626; /* red-600 */
    color: white;
}
.badge-secondary {
    background-color: #6b7280; /* gray-500 */
    color: white;
}
.badge-info {
    background-color: #0891b2; /* cyan-600 */
    color: white;
}
.badge-tidak-ada {
    background-color: #e5e7eb; /* gray-200 */
    color: #6b7280;
}

/* === Button & Components === */
.btn-action {
    border-radius: 8px;
    padding: 8px 16px;
    font-size: 0.8rem;
    font-weight: 500;
    transition: all 0.3s ease;
}
.btn-ocr {
    background: linear-gradient(45deg, #f39c12, #f1c40f);
    color: white;
    border: none;
}
.btn-ocr:hover {
    background: linear-gradient(45deg, #e67e22, #f39c12);
    color: white;
}
.btn-midtrans {
    background: linear-gradient(45deg, #3498db, #2980b9);
    color: white;
    border: none;
}
.btn-midtrans:hover {
    background: linear-gradient(45deg, #2980b9, #1f618d);
    color: white;
}
.image-preview {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e9ecef;
    cursor: pointer;
    transition: transform 0.3s ease;
}
.image-preview:hover {
    transform: scale(1.1);
}
.ocr-details {
    font-size: 0.8rem;
    line-height: 1.4;
}
.midtrans-details {
    font-size: 0.8rem;
    line-height: 1.4;
}
.alert-custom {
    border-radius: 10px;
    border: none;
    padding: 15px 20px;
}
.dropdown-auto {
    min-width: 120px;
}
.text-muted-small {
    font-size: 0.8rem;
    color: #6c757d;
}

/* === Navigation Tabs === */
.nav-tabs {
    background: white;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 2rem;
    display: flex;
    gap: 0;
    margin-bottom: 2rem;
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
}
.nav-tab.active {
    color: #4f46e5;
    border-bottom-color: #4f46e5;
    background: #f1f5f9;
}

/* Responsive table */
@media (max-width: 1200px) {
    .table-responsive {
        font-size: 0.85rem;
    }
    .main-container {
        max-width: 100%;
        padding: 15px;
    }
}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-clipboard-check me-3"></i>
                        Konfirmasi Pembayaran
                    </h1>
                    <p class="mb-0 opacity-90">Kelola dan verifikasi pembayaran dari warga dengan tracking Midtrans</p>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info alert-custom mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <?= $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <a href="tagihan.php" class="nav-tab ">
                <i class="fas fa-plus-circle"></i> Buat Tagihan
            </a>
            <a href="konfirmasi.php" class="nav-tab active">
                <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
            </a>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-primary">
                        <i class="fas fa-clock me-2"></i>
                        <?= $stats['total_menunggu'] ?? 0 ?>
                    </div>
                    <div class="stats-label">Menunggu Konfirmasi</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-success">
                        <i class="fas fa-robot me-2"></i>
                        <?= $stats['total_ocr_processed'] ?? 0 ?>
                    </div>
                    <div class="stats-label">Sudah di OCR</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-info">
                        <i class="fas fa-credit-card me-2"></i>
                        <?= $stats['total_midtrans_success'] ?? 0 ?>
                    </div>
                    <div class="stats-label">Midtrans Success</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="stats-number text-warning">
                        <i class="fas fa-money-bill-wave me-2"></i>
                        <?= number_format($stats['total_nominal'] ?? 0, 0, ',', '.') ?>
                    </div>
                    <div class="stats-label">Total Nominal (Rp)</div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">
                    <i class="fas fa-list me-2 text-primary"></i>
                    Daftar Pembayaran
                </h4>
                <div class="text-muted-small">
                    Total: <?= count($bills) ?> pembayaran
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user me-1"></i> Username</th>
                            <th><i class="fas fa-barcode me-1"></i> Kode Tagihan</th>
                            <th><i class="fas fa-align-left me-1"></i> Deskripsi</th>
                            <th><i class="fas fa-money-bill me-1"></i> Jumlah</th>
                            <th><i class="fas fa-robot me-1"></i> Hasil OCR</th>
                            <th><i class="fas fa-check-circle me-1"></i> Status</th>
                            <th><i class="fas fa-credit-card me-1"></i> Midtrans</th>
                            <th><i class="fas fa-calendar me-1"></i> Tanggal</th>
                            <th><i class="fas fa-clock me-1"></i> Ketepatan</th>
                            <th><i class="fas fa-image me-1"></i> Bukti</th>
                            <th><i class="fas fa-cogs me-1"></i> Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <?php 
                            $ocr_details = json_decode($bill['ocr_details'], true);
                            $match_status = getMatchStatus($bill);
                            $auto_action = ($match_status === 'Sesuai') ? 'konfirmasi' : 'tolak';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($bill['username']) ?></strong>
                                </td>
                                <td>
                                    <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($bill['kode_tagihan']) ?></code>
                                </td>
                                <td>
                                    <div style="max-width: 150px;">
                                        <?= nl2br(htmlspecialchars(substr($bill['deskripsi'], 0, 80))) ?>
                                        <?= strlen($bill['deskripsi']) > 80 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <strong class="text-primary">Rp <?= number_format($bill['jumlah'], 0, ',', '.') ?></strong>
                                </td>
                                <td>
                                    <?php if ($ocr_details): ?>
                                        <div class="ocr-details">
                                            <div><strong>Jumlah:</strong> 
                                                <span class="<?= ($bill['ocr_jumlah'] == $bill['jumlah']) ? 'text-success' : 'text-danger' ?>">
                                                    Rp <?= number_format($bill['ocr_jumlah'] ?? 0, 0, ',', '.') ?>
                                                </span>
                                            </div>
                                            <div><strong>Kode:</strong> 
                                                <span class="<?= (($ocr_details['extracted_code'] ?? '') === $bill['kode_tagihan']) ? 'text-success' : 'text-danger' ?>">
                                                    <?= htmlspecialchars($ocr_details['extracted_code'] ?? '-') ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted"><em>Belum diproses</em></span>
                                    <?php endif; ?>
                                    
                                    <form method="POST" class="mt-2">
                                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                        <button name="run_ocr" class="btn btn-ocr btn-sm">
                                            <i class="fas fa-search me-1"></i> OCR
                                        </button>
                                    </form>
                                </td>
                                <td>
                                    <?php if ($match_status === 'Sesuai'): ?>
                                        <span class="badge badge-sesuai">
                                            <i class="fas fa-check me-1"></i> Sesuai
                                        </span>
                                    <?php elseif ($match_status === 'Tidak Sesuai'): ?>
                                        <span class="badge badge-tidak-sesuai">
                                            <i class="fas fa-times me-1"></i> Tidak Sesuai
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-belum-ocr">
                                            <i class="fas fa-hourglass-half me-1"></i> Belum OCR
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <!-- Midtrans Status Column -->
                                    <div class="midtrans-details">
                                        <?php if ($bill['midtrans_order_id']): ?>
                                            <div class="mb-2">
                                                <?= formatMidtransStatus($bill['midtrans_status']) ?>
                                            </div>
                                            
                                            <?php if ($bill['midtrans_payment_type']): ?>
                                                <div class="text-muted-small">
                                                    <strong>Metode:</strong> <?= htmlspecialchars(ucfirst($bill['midtrans_payment_type'])) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['midtrans_transaction_time']): ?>
                                                <div class="text-muted-small">
                                                    <strong>Transaksi:</strong><br><?= format_tanggal_waktu_indo($bill['midtrans_transaction_time']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['midtrans_settlement_time']): ?>
                                                <div class="text-muted-small">
                                                    <strong>Settlement:</strong><br><?= format_tanggal_waktu_indo($bill['midtrans_settlement_time']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($bill['midtrans_last_check']): ?>
                                                <div class="text-muted-small mt-1">
                                                    <em>Cek terakhir: <?= format_tanggal_waktu_indo($bill['midtrans_last_check']) ?></em>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <form method="POST" class="mt-2">
                                                <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                                <button name="refresh_midtrans" class="btn btn-midtrans btn-sm">
                                                    <i class="fas fa-sync-alt me-1"></i> Refresh
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-tidak-ada">
                                                <i class="fas fa-times me-1"></i> Tidak Ada Order ID
                                            </span>
                                            <div class="text-muted-small mt-1">
                                                <em>Pembayaran manual/transfer</em>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-muted-small">
                                        <div><strong>Upload:</strong><br><?= format_tanggal_indo($bill['tanggal_upload']) ?></div>
                                        <div><strong>Kirim:</strong><br><?= format_tanggal_indo($bill['tanggal_kirim']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($bill['status_ketepatan'] === 'Tepat Waktu'): ?>
                                        <span class="badge badge-tepat-waktu">
                                            <i class="fas fa-check me-1"></i> Tepat Waktu
                                        </span>
                                    <?php elseif ($bill['status_ketepatan'] === 'Terlambat'): ?>
                                        <span class="badge badge-terlambat">
                                            <i class="fas fa-exclamation-triangle me-1"></i> Terlambat
                                        </span>
                                        <div class="text-muted-small mt-1">
                                            <?= abs($bill['selisih_hari']) ?> hari
                                        </div>
                                    <?php else: ?>
                                        <span class="badge badge-belum-upload">
                                            <i class="fas fa-hourglass-half me-1"></i> Belum Upload
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($bill['bukti_pembayaran']): ?>
                                        <img src="../warga/uploads/bukti_pembayaran/<?= htmlspecialchars($bill['bukti_pembayaran']) ?>" 
                                             class="image-preview" 
                                             onclick="window.open(this.src, '_blank')"
                                             title="Klik untuk memperbesar">
                                    <?php else: ?>
                                        <span class="text-muted"><em>Belum upload</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                        <select name="status" class="form-select form-select-sm dropdown-auto mb-2">
                                            <?php 
                                            // Auto-suggest based on OCR match and Midtrans status
                                            $should_approve = false;
                                            
                                            // If OCR matches perfectly
                                            if ($match_status === 'Sesuai') {
                                                $should_approve = true;
                                            }
                                            
                                            // If Midtrans shows successful payment
                                            if (in_array($bill['midtrans_status'], ['settlement', 'capture'])) {
                                                $should_approve = true;
                                            }
                                            ?>
                                            
                                            <?php if ($should_approve): ?>
                                                <option value="konfirmasi" selected>✅ Konfirmasi</option>
                                                <option value="tolak">❌ Tolak</option>
                                            <?php else: ?>
                                                <option value="tolak" selected>❌ Tolak</option>
                                                <option value="konfirmasi">✅ Konfirmasi</option>
                                            <?php endif; ?>
                                        </select>
                                        <button name="proses_konfirmasi" class="btn btn-primary btn-sm w-100" 
                                                onclick="return confirm('Yakin ingin memproses pembayaran ini?')">
                                            <i class="fas fa-check me-1"></i> Proses
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (empty($bills)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3 text-muted">Tidak ada pembayaran yang menunggu konfirmasi</h5>
                    <p class="text-muted">Semua pembayaran sudah diproses atau belum ada yang diupload</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Info Panel -->
        <div class="row mt-4">
            <div class="col-md-6">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Status Midtrans</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">
                                    <strong>Settlement/Capture:</strong> Pembayaran berhasil<br>
                                    <strong>Pending:</strong> Menunggu pembayaran<br>
                                    <strong>Deny:</strong> Pembayaran ditolak
                                </small>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">
                                    <strong>Cancel:</strong> Dibatalkan<br>
                                    <strong>Expire:</strong> Kadaluarsa<br>
                                    <strong>Failure:</strong> Gagal diproses
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h6 class="mb-0"><i class="fas fa-lightbulb me-2"></i>Tips Konfirmasi</h6>
                    </div>
                    <div class="card-body">
                        <small class="text-muted">
                            • Sistem otomatis menyarankan <strong>Konfirmasi</strong> jika OCR cocok atau Midtrans berhasil<br>
                            • Gunakan tombol <strong>Refresh</strong> untuk update status Midtrans terbaru<br>
                            • Periksa bukti pembayaran secara visual untuk verifikasi tambahan<br>
                            • Status "Settlement" atau "Capture" menandakan pembayaran valid
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Auto refresh Midtrans status every 30 seconds for pending transactions
    document.addEventListener('DOMContentLoaded', function() {
        const pendingBills = <?= json_encode(array_filter($bills, function($bill) { 
            return $bill['midtrans_status'] === 'pending'; 
        })) ?>;
        
        if (pendingBills.length > 0) {
            console.log('Found ' + pendingBills.length + ' pending transactions, will auto-refresh every 30 seconds');
            
            setInterval(function() {
                // Add subtle indication that auto-refresh is happening
                const indicators = document.querySelectorAll('.badge-warning');
                indicators.forEach(function(indicator) {
                    if (indicator.textContent.includes('Pending')) {
                        indicator.style.opacity = '0.5';
                        setTimeout(() => indicator.style.opacity = '1', 500);
                    }
                });
                
                // You could implement silent AJAX refresh here if needed
                console.log('Auto-refreshing pending transactions...');
            }, 30000);
        }
    });
    
    // Add loading state to refresh buttons
    document.querySelectorAll('button[name="refresh_midtrans"]').forEach(function(button) {
        button.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Loading...';
            this.disabled = true;
        });
    });
    </script>
</body>
</html>
