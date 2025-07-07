<?php
require_once '../config.php';
requireAdmin();

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

// Ambil statistik
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_menunggu,
        SUM(CASE WHEN ocr_details IS NOT NULL AND ocr_details != '' THEN 1 ELSE 0 END) as total_ocr_processed,
        SUM(b.jumlah) as total_nominal
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    WHERE ub.status = 'menunggu_konfirmasi'
")->fetch();

// Ambil tagihan menunggu konfirmasi dengan detail lengkap
$stmt = $pdo->query("
    SELECT 
        ub.id,
        ub.tanggal_upload,
        ub.bukti_pembayaran,
        ub.ocr_jumlah,
        ub.ocr_kode_found,
        ub.ocr_date_found,
        ub.ocr_details,
        b.kode_tagihan,
        b.jumlah,
        b.deskripsi,
        b.tenggat_waktu,
        b.tanggal AS tanggal_tagihan,
        u.username
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
            max-width: 1400px;
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
        .badge-status {
            padding: 8px 12px;
            font-size: 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .badge-sesuai {
            background-color: #d4edda;
            color: #155724;
        }
        .badge-tidak-sesuai {
            background-color: #f8d7da;
            color: #721c24;
        }
        .badge-belum-ocr {
            background-color: #fff3cd;
            color: #856404;
        }
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
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-clipboard-check me-3"></i>
                        Konfirmasi Pembayaran
                    </h1>
                    <p class="mb-0 opacity-90">Kelola dan verifikasi pembayaran dari warga</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-white-50">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?= date('d F Y') ?>
                    </div>
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

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number text-primary">
                        <i class="fas fa-clock me-2"></i>
                        <?= $stats['total_menunggu'] ?? 0 ?>
                    </div>
                    <div class="stats-label">Menunggu Konfirmasi</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="stats-number text-success">
                        <i class="fas fa-robot me-2"></i>
                        <?= $stats['total_ocr_processed'] ?? 0 ?>
                    </div>
                    <div class="stats-label">Sudah di OCR</div>
                </div>
            </div>
            <div class="col-md-4">
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
                            <th><i class="fas fa-calendar me-1"></i> Tanggal</th>
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
                                    <div style="max-width: 200px;">
                                        <?= nl2br(htmlspecialchars(substr($bill['deskripsi'], 0, 100))) ?>
                                        <?= strlen($bill['deskripsi']) > 100 ? '...' : '' ?>
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
                                            <div><strong>Tanggal:</strong> <?= htmlspecialchars($ocr_details['extracted_date'] ?? '-') ?></div>
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
                                    <div class="text-muted-small">
                                        <div><strong>Upload:</strong><br><?= date('d/m/Y H:i', strtotime($bill['tanggal_upload'])) ?></div>
                                        <div><strong>Tenggat:</strong><br><?= date('d/m/Y', strtotime($bill['tenggat_waktu'])) ?></div>
                                    </div>
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
                                    <form method="POST" action="konfirmasi.php">
                                        <input type="hidden" name="user_bill_id" value="<?= $bill['id'] ?>">
                                        <select name="status" class="form-select form-select-sm dropdown-auto mb-2">
                                            <?php if ($match_status === 'Sesuai'): ?>
                                                <option value="konfirmasi" selected>✅ Konfirmasi</option>
                                                <option value="tolak">❌ Tolak</option>
                                            <?php else: ?>
                                                <option value="tolak" selected>❌ Tolak</option>
                                                <option value="konfirmasi">✅ Konfirmasi</option>
                                            <?php endif; ?>
                                        </select>
                                        <button class="btn btn-primary btn-sm w-100" 
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
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
