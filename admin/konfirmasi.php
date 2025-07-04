<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Ambil data pembayaran yang menunggu konfirmasi
$stmt = $pdo->prepare("
    SELECT ub.*, b.kode_tagihan, b.jumlah, b.deskripsi, b.tanggal as tanggal_tagihan,
           u.username, u.no_kk,
           CASE 
               WHEN ub.tanggal_upload <= b.tenggat_waktu THEN 'Tepat Waktu'
               ELSE 'Terlambat'
           END as status_ketepatan,
           DATEDIFF(ub.tanggal_upload, b.tenggat_waktu) as selisih_hari
    FROM user_bills ub
    JOIN bills b ON ub.bill_id = b.id
    JOIN users u ON ub.user_id = u.id
    WHERE ub.status = 'menunggu_konfirmasi'
    AND ub.bukti_pembayaran IS NOT NULL
    ORDER BY ub.tanggal_upload DESC
");
$stmt->execute();
$pending_payments = $stmt->fetchAll();

// Handle konfirmasi/tolak
if ($_POST && isset($_POST['action']) && isset($_POST['user_bill_id'])) {
    $action = $_POST['action'];
    $user_bill_id = $_POST['user_bill_id'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    try {
        $pdo->beginTransaction();
        
        if ($action === 'konfirmasi') {
            // Update status menjadi konfirmasi
            $stmt = $pdo->prepare("UPDATE user_bills SET status = 'konfirmasi' WHERE id = ?");
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
            $stmt = $pdo->prepare("UPDATE user_bills SET status = 'tolak' WHERE id = ?");
            $stmt->execute([$user_bill_id]);
            
            $message = "Pembayaran ditolak!";
            $message_type = "danger";
        }
        
        $pdo->commit();
        
        // Redirect untuk menghindari resubmit
        header("Location: konfirmasi.php?msg=" . urlencode($message) . "&type=" . $message_type);
        exit;
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = "Terjadi kesalahan: " . $e->getMessage();
    }
}

// Jalankan OCR untuk yang belum diproses
if (isset($_GET['run_ocr'])) {
    $ocr_command = "python ocr.py process_pending";
    exec($ocr_command, $output, $return_code);
    
    if ($return_code === 0) {
        $ocr_message = "OCR processing completed successfully";
        $ocr_type = "success";
    } else {
        $ocr_message = "OCR processing failed";
        $ocr_type = "danger";
    }
    
    header("Location: konfirmasi.php?ocr_msg=" . urlencode($ocr_message) . "&ocr_type=" . $ocr_type);
    exit;
}
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
        .ocr-info {
            font-size: 0.9em;
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        .confidence-bar {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }
        .confidence-fill {
            height: 100%;
            transition: width 0.3s ease;
        }
        .confidence-low { background-color: #dc3545; }
        .confidence-medium { background-color: #ffc107; }
        .confidence-high { background-color: #28a745; }
        .payment-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .bukti-image {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .status-badge {
            font-size: 0.85em;
            padding: 0.5em 1em;
        }
        .modal-img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
        }
        .ocr-status {
            font-size: 0.8em;
            font-weight: bold;
        }
        .ocr-match {
            color: #28a745;
        }
        .ocr-mismatch {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-dark text-white p-3">
                <h5><i class="fas fa-user-shield"></i> Admin Panel</h5>
                <ul class="nav flex-column mt-4">
                    <li class="nav-item">
                        <a class="nav-link text-white" href="dashboard.php">
                            <i class="fas fa-chart-bar"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white active" href="konfirmasi.php">
                            <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="tagihan.php">
                            <i class="fas fa-file-invoice"></i> Kelola Tagihan
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link text-white" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-check-circle"></i> Konfirmasi Pembayaran</h2>
                    <div>
                        <button class="btn btn-info me-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                        <a href="?run_ocr=1" class="btn btn-primary">
                            <i class="fas fa-robot"></i> Jalankan OCR
                        </a>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert alert-<?= sanitize($_GET['type']) ?> alert-dismissible fade show">
                        <?= sanitize($_GET['msg']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['ocr_msg'])): ?>
                    <div class="alert alert-<?= sanitize($_GET['ocr_type']) ?> alert-dismissible fade show">
                        <i class="fas fa-robot"></i> <?= sanitize($_GET['ocr_msg']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= sanitize($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <h5><?= count($pending_payments) ?></h5>
                                <p class="mb-0">Menunggu Konfirmasi</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <h5><?= count(array_filter($pending_payments, fn($p) => $p['status_ketepatan'] === 'Tepat Waktu')) ?></h5>
                                <p class="mb-0">Tepat Waktu</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <h5><?= count(array_filter($pending_payments, fn($p) => $p['status_ketepatan'] === 'Terlambat')) ?></h5>
                                <p class="mb-0">Terlambat</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <h5><?= count(array_filter($pending_payments, fn($p) => ($p['ocr_confidence'] ?? 0) > 0)) ?></h5>
                                <p class="mb-0">Dengan OCR</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Cards -->
                <?php if (empty($pending_payments)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">Tidak ada pembayaran yang menunggu konfirmasi</h4>
                        <p class="text-muted">Semua pembayaran sudah dikonfirmasi atau ditolak</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($pending_payments as $payment): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="payment-card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">
                                            <i class="fas fa-user"></i> <?= sanitize($payment['username']) ?>
                                            <span class="badge bg-<?= $payment['status_ketepatan'] === 'Tepat Waktu' ? 'success' : 'warning' ?> float-end status-badge">
                                                <?= $payment['status_ketepatan'] ?>
                                            </span>
                                        </h6>
                                        <small class="text-muted">
                                            <i class="fas fa-id-card"></i> KK: <?= sanitize($payment['no_kk']) ?>
                                        </small>
                                    </div>
                                    <div class="card-body">
                                        <!-- Informasi Tagihan -->
                                        <div class="mb-3">
                                            <strong>Kode Tagihan:</strong> <?= sanitize($payment['kode_tagihan']) ?><br>
                                            <strong>Jumlah:</strong> Rp <?= number_format($payment['jumlah'], 0, ',', '.') ?><br>
                                            <strong>Deskripsi:</strong> <?= sanitize($payment['deskripsi']) ?><br>
                                            <strong>Upload:</strong> <?= date('d/m/Y H:i', strtotime($payment['tanggal_upload'])) ?>
                                            <?php if ($payment['status_ketepatan'] === 'Terlambat'): ?>
                                                <br><small class="text-danger">Terlambat <?= abs($payment['selisih_hari']) ?> hari</small>
                                            <?php endif; ?>
                                        </div>

                                        <!-- OCR Information -->
                                        <?php if (isset($payment['ocr_confidence']) && $payment['ocr_confidence'] > 0): ?>
                                            <div class="ocr-info">
                                                <strong><i class="fas fa-robot"></i> Hasil OCR:</strong>
                                                <div class="row mt-2">
                                                    <div class="col-12">
                                                        <small>Confidence Score:</small>
                                                        <div class="confidence-bar">
                                                            <div class="confidence-fill <?= $payment['ocr_confidence'] >= 70 ? 'confidence-high' : ($payment['ocr_confidence'] >= 40 ? 'confidence-medium' : 'confidence-low') ?>" 
                                                                 style="width: <?= $payment['ocr_confidence'] ?>%"></div>
                                                        </div>
                                                        <small><?= number_format($payment['ocr_confidence'], 1) ?>%</small>
                                                    </div>
                                                </div>
                                                <div class="mt-2">
                                                    <small>
                                                        <i class="fas fa-<?= isset($payment['ocr_jumlah']) && $payment['ocr_jumlah'] ? 'check text-success' : 'times text-danger' ?>"></i>
                                                        Nominal: <?= isset($payment['ocr_jumlah']) && $payment['ocr_jumlah'] ? 'Rp ' . number_format($payment['ocr_jumlah'], 0, ',', '.') : 'Tidak terdeteksi' ?>
                                                        <?php if (isset($payment['ocr_jumlah']) && $payment['ocr_jumlah'] && $payment['ocr_jumlah'] == $payment['jumlah']): ?>
                                                            <span class="ocr-status ocr-match">✓ Cocok</span>
                                                        <?php elseif (isset($payment['ocr_jumlah']) && $payment['ocr_jumlah']): ?>
                                                            <span class="ocr-status ocr-mismatch">✗ Tidak cocok</span>
                                                        <?php endif; ?>
                                                    </small><br>
                                                    <small>
                                                        <i class="fas fa-<?= isset($payment['ocr_kode_found']) && $payment['ocr_kode_found'] ? 'check text-success' : 'times text-danger' ?>"></i>
                                                        Kode Tagihan: <?= isset($payment['ocr_kode_found']) && $payment['ocr_kode_found'] ? 'Ditemukan' : 'Tidak ditemukan' ?>
                                                    </small><br>
                                                    <small>
                                                        <i class="fas fa-<?= isset($payment['ocr_date_found']) && $payment['ocr_date_found'] ? 'check text-success' : 'times text-danger' ?>"></i>
                                                        Tanggal: <?= isset($payment['ocr_date_found']) && $payment['ocr_date_found'] ? 'Ditemukan' : 'Tidak ditemukan' ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="ocr-info">
                                                <small class="text-muted">
                                                    <i class="fas fa-robot"></i> OCR belum diproses
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Bukti Pembayaran -->
                                        <div class="mt-3">
                                            <strong>Bukti Pembayaran:</strong>
                                            <div class="mt-2">
                                                <img src="../warga/uploads/bukti_pembayaran/<?= sanitize($payment['bukti_pembayaran']) ?>" 
                                                     class="bukti-image" 
                                                     alt="Bukti Pembayaran"
                                                     style="cursor: pointer;"
                                                     onclick="showImageModal('<?= sanitize($payment['bukti_pembayaran']) ?>', '<?= sanitize($payment['username']) ?>', '<?= sanitize($payment['kode_tagihan']) ?>')">
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="mt-3 d-flex gap-2">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="user_bill_id" value="<?= $payment['id'] ?>">
                                                <input type="hidden" name="action" value="konfirmasi">
                                                <button type="submit" class="btn btn-success btn-sm" 
                                                        onclick="return confirm('Konfirmasi pembayaran ini?')">
                                                    <i class="fas fa-check"></i> Konfirmasi
                                                </button>
                                            </form>
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="user_bill_id" value="<?= $payment['id'] ?>">
                                                <input type="hidden" name="action" value="tolak">
                                                <button type="submit" class="btn btn-danger btn-sm" 
                                                        onclick="return confirm('Tolak pembayaran ini?')">
                                                    <i class="fas fa-times"></i> Tolak
                                                </button>
                                            </form>
                                            <button type="button" class="btn btn-info btn-sm" 
                                                    onclick="showImageModal('<?= sanitize($payment['bukti_pembayaran']) ?>', '<?= sanitize($payment['username']) ?>', '<?= sanitize($payment['kode_tagihan']) ?>')">
                                                <i class="fas fa-eye"></i> Lihat
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal untuk menampilkan gambar -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="modal-payment-info" class="mb-3"></div>
                    <img id="modal-image" class="modal-img" alt="Bukti Pembayaran">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="button" class="btn btn-primary" onclick="downloadImage()">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-3">Memproses OCR...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentImageSrc = '';

        function showImageModal(imageName, username, kodeTagihan) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            const modalImage = document.getElementById('modal-image');
            const modalPaymentInfo = document.getElementById('modal-payment-info');
            
            currentImageSrc = '../warga/uploads/bukti_pembayaran/' + imageName;
            modalImage.src = currentImageSrc;
            
            modalPaymentInfo.innerHTML = `
                <div class="alert alert-info">
                    <strong>User:</strong> ${username}<br>
                    <strong>Kode Tagihan:</strong> ${kodeTagihan}
                </div>
            `;
            
            modal.show();
        }

        function downloadImage() {
            if (currentImageSrc) {
                const link = document.createElement('a');
                link.href = currentImageSrc;
                link.download = 'bukti_pembayaran_' + Date.now() + '.jpg';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }

        // Auto refresh setiap 30 detik
        setInterval(function() {
            // Hanya refresh jika tidak ada modal yang terbuka
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);

        // Show loading modal saat OCR dijalankan
        document.addEventListener('DOMContentLoaded', function() {
            const ocrLink = document.querySelector('a[href*="run_ocr"]');
            if (ocrLink) {
                ocrLink.addEventListener('click', function(e) {
                    const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
                    loadingModal.show();
                });
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + R untuk refresh
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                location.reload();
            }
            
            // Escape untuk tutup modal
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });
            }
        });

        // Tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
