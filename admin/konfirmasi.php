<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Function untuk menjalankan OCR
function runOCR($imagePath, $userBillId) {
    $pythonScript = __DIR__ . '/ocr.py';
    $command = "python \"$pythonScript\" \"$imagePath\" $userBillId";
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Failed to parse OCR results'];
    }
    
    return $result;
}

// Handle konfirmasi/penolakan
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $user_bill_id = $_POST['user_bill_id'] ?? 0;
    $alasan = $_POST['alasan'] ?? '';
    
    if ($action && $user_bill_id) {
        try {
            if ($action === 'konfirmasi') {
                // Update status ke konfirmasi
                $stmt = $pdo->prepare("UPDATE user_bills SET status = 'konfirmasi' WHERE id = ?");
                $stmt->execute([$user_bill_id]);
                
                // Pindahkan ke tabel tagihan_oke
                $stmt = $pdo->prepare("
                    INSERT INTO tagihan_oke (user_bill_id, kode_tagihan, jumlah, tanggal, user_id, qr_code_hash, bukti_pembayaran)
                    SELECT ub.id, b.kode_tagihan, b.jumlah, ub.tanggal, ub.user_id, ub.qr_code_hash, ub.bukti_pembayaran
                    FROM user_bills ub
                    JOIN bills b ON ub.bill_id = b.id
                    WHERE ub.id = ?
                ");
                $stmt->execute([$user_bill_id]);
                
                $_SESSION['success'] = 'Pembayaran berhasil dikonfirmasi';
                
            } elseif ($action === 'tolak') {
                // Update status ke tolak
                $stmt = $pdo->prepare("UPDATE user_bills SET status = 'tolak' WHERE id = ?");
                $stmt->execute([$user_bill_id]);
                
                $_SESSION['success'] = 'Pembayaran ditolak';
            }
            
            // Redirect untuk mencegah resubmission
            header('Location: konfirmasi.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Gagal memproses konfirmasi: ' . $e->getMessage();
        }
    }
}

// Handle OCR request
if (isset($_GET['ocr']) && $_GET['id']) {
    $user_bill_id = $_GET['id'];
    
    // Get image path
    $stmt = $pdo->prepare("SELECT bukti_pembayaran FROM user_bills WHERE id = ?");
    $stmt->execute([$user_bill_id]);
    $result = $stmt->fetch();
    
    if ($result && $result['bukti_pembayaran']) {
        // FIX: Add missing slash between __DIR__ and '../warga/uploads/bukti_pembayaran/'
        $imagePath = __DIR__ . '/../warga/uploads/bukti_pembayaran/' . $result['bukti_pembayaran'];
        
        if (file_exists($imagePath)) {
            $ocrResult = runOCR($imagePath, $user_bill_id);
            $_SESSION['ocr_result'] = $ocrResult;
        } else {
            $_SESSION['error'] = 'File bukti pembayaran tidak ditemukan: ' . $imagePath;
        }
    } else {
        $_SESSION['error'] = 'Data bukti pembayaran tidak ditemukan di database';
    }
    
    header('Location: konfirmasi.php');
    exit;
}

// Get pending payments
$stmt = $pdo->prepare("
    SELECT ub.*, b.kode_tagihan, b.jumlah as jumlah_tagihan, b.deskripsi,
           u.username, ub.tanggal_upload, ub.ocr_jumlah, ub.ocr_kode_found, 
           ub.ocr_date_found, ub.ocr_confidence, ub.ocr_details
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
        .ocr-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .ocr-match {
            color: #198754;
            font-weight: bold;
        }
        .ocr-mismatch {
            color: #dc3545;
            font-weight: bold;
        }
        .confidence-high {
            color: #198754;
        }
        .confidence-medium {
            color: #fd7e14;
        }
        .confidence-low {
            color: #dc3545;
        }
        .image-preview {
            max-width: 300px;
            max-height: 300px;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
        }
        .detail-row {
            margin-bottom: 0.5rem;
        }
        .detail-label {
            font-weight: bold;
            width: 150px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Konfirmasi Pembayaran</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= sanitize($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= sanitize($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['ocr_result'])): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <h6>Hasil OCR:</h6>
                        <pre><?= json_encode($_SESSION['ocr_result'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['ocr_result']); ?>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h5>Pembayaran Menunggu Konfirmasi (<?= count($pending_payments) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_payments)): ?>
                            <p class="text-muted">Tidak ada pembayaran yang menunggu konfirmasi.</p>
                        <?php else: ?>
                            <?php foreach ($pending_payments as $payment): ?>
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <div class="row">
                                            <div class="col">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-user"></i> <?= sanitize($payment['username']) ?>
                                                    <span class="badge bg-warning ms-2">Menunggu Konfirmasi</span>
                                                </h6>
                                            </div>
                                            <div class="col-auto">
                                                <small class="text-muted">
                                                    Upload: <?= date('d/m/Y H:i', strtotime($payment['tanggal_upload'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="detail-row">
                                                    <span class="detail-label">Kode Tagihan:</span>
                                                    <span class="badge bg-primary"><?= sanitize($payment['kode_tagihan']) ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Deskripsi:</span>
                                                    <?= sanitize($payment['deskripsi']) ?>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Jumlah Tagihan:</span>
                                                    <span class="fw-bold">Rp <?= number_format($payment['jumlah_tagihan'], 0, ',', '.') ?></span>
                                                </div>
                                                <div class="detail-row">
                                                    <span class="detail-label">Tanggal Jatuh Tempo:</span>
                                                    <?= date('d/m/Y', strtotime($payment['tanggal'])) ?>
                                                </div>

                                                <?php if ($payment['ocr_confidence'] > 0): ?>
                                                    <div class="ocr-info">
                                                        <h6><i class="fas fa-robot"></i> Hasil OCR</h6>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Confidence:</span>
                                                            <span class="<?= $payment['ocr_confidence'] >= 0.8 ? 'confidence-high' : ($payment['ocr_confidence'] >= 0.6 ? 'confidence-medium' : 'confidence-low') ?>">
                                                                <?= round($payment['ocr_confidence'] * 100, 1) ?>%
                                                            </span>
                                                        </div>
                                                        <?php if ($payment['ocr_jumlah']): ?>
                                                            <div class="detail-row">
                                                                <span class="detail-label">Nominal OCR:</span>
                                                                <span class="<?= abs($payment['ocr_jumlah'] - $payment['jumlah_tagihan']) <= 1000 ? 'ocr-match' : 'ocr-mismatch' ?>">
                                                                    Rp <?= number_format($payment['ocr_jumlah'], 0, ',', '.') ?>
                                                                    <?php if (abs($payment['ocr_jumlah'] - $payment['jumlah_tagihan']) <= 1000): ?>
                                                                        <i class="fas fa-check-circle"></i>
                                                                    <?php else: ?>
                                                                        <i class="fas fa-times-circle"></i>
                                                                    <?php endif; ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Kode Ditemukan:</span>
                                                            <span class="<?= $payment['ocr_kode_found'] ? 'ocr-match' : 'ocr-mismatch' ?>">
                                                                <?= $payment['ocr_kode_found'] ? 'Ya' : 'Tidak' ?>
                                                                <i class="fas fa-<?= $payment['ocr_kode_found'] ? 'check-circle' : 'times-circle' ?>"></i>
                                                            </span>
                                                        </div>
                                                        <div class="detail-row">
                                                            <span class="detail-label">Tanggal Ditemukan:</span>
                                                            <span class="<?= $payment['ocr_date_found'] ? 'ocr-match' : 'ocr-mismatch' ?>">
                                                                <?= $payment['ocr_date_found'] ? 'Ya' : 'Tidak' ?>
                                                                <i class="fas fa-<?= $payment['ocr_date_found'] ? 'check-circle' : 'times-circle' ?>"></i>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-center">
                                                <?php if ($payment['bukti_pembayaran']): ?>
                                                    <img src="../warga/uploads/bukti_pembayaran/<?= sanitize($payment['bukti_pembayaran']) ?>" 
                                                         alt="Bukti Pembayaran" 
                                                         class="image-preview mb-3"
                                                         onclick="showImageModal(this.src)">
                                                    <br>
                                                    <a href="../warga/uploads/bukti_pembayaran/<?= sanitize($payment['bukti_pembayaran']) ?>" 
                                                       target="_blank" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt"></i> Lihat Full
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <div class="btn-group" role="group">
                                                    <a href="?ocr=1&id=<?= $payment['id'] ?>" 
                                                       class="btn btn-info btn-sm">
                                                        <i class="fas fa-robot"></i> Jalankan OCR
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-success btn-sm" 
                                                            onclick="confirmPayment(<?= $payment['id'] ?>, 'konfirmasi')">
                                                        <i class="fas fa-check"></i> Konfirmasi
                                                    </button>
                                                    <button type="button" 
                                                            class="btn btn-danger btn-sm" 
                                                            onclick="rejectPayment(<?= $payment['id'] ?>)">
                                                        <i class="fas fa-times"></i> Tolak
                                                    </button>
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
        </div>
    </div>

    <!-- Modal untuk konfirmasi -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Konfirmasi Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin <span id="actionText"></span> pembayaran ini?</p>
                    <div id="rejectReason" style="display:none;">
                        <label for="alasan" class="form-label">Alasan Penolakan:</label>
                        <textarea class="form-control" id="alasan" name="alasan" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-primary" id="confirmButton">Ya, Lanjutkan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal untuk melihat gambar -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bukti Pembayaran</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Bukti Pembayaran" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form untuk submit -->
    <form id="actionForm" method="POST" style="display: none;">
        <input type="hidden" name="action" id="formAction">
        <input type="hidden" name="user_bill_id" id="formUserId">
        <input type="hidden" name="alasan" id="formAlasan">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentUserId = null;
        let currentAction = null;

        function confirmPayment(userId, action) {
            currentUserId = userId;
            currentAction = action;
            
            document.getElementById('actionText').textContent = 'mengkonfirmasi';
            document.getElementById('rejectReason').style.display = 'none';
            document.getElementById('confirmButton').className = 'btn btn-success';
            document.getElementById('confirmButton').innerHTML = '<i class="fas fa-check"></i> Konfirmasi';
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }

        function rejectPayment(userId) {
            currentUserId = userId;
            currentAction = 'tolak';
            
            document.getElementById('actionText').textContent = 'menolak';
            document.getElementById('rejectReason').style.display = 'block';
            document.getElementById('confirmButton').className = 'btn btn-danger';
            document.getElementById('confirmButton').innerHTML = '<i class="fas fa-times"></i> Tolak';
            
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }

        function showImageModal(imageSrc) {
            document.getElementById('modalImage').src = imageSrc;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        document.getElementById('confirmButton').addEventListener('click', function() {
            const alasan = document.getElementById('alasan').value;
            
            if (currentAction === 'tolak' && !alasan.trim()) {
                alert('Alasan penolakan wajib diisi!');
                return;
            }
            
            document.getElementById('formAction').value = currentAction;
            document.getElementById('formUserId').value = currentUserId;
            document.getElementById('formAlasan').value = alasan;
            
            document.getElementById('actionForm').submit();
        });

        // Auto-refresh halaman setiap 30 detik
        setInterval(function() {
            // Hanya refresh jika tidak ada modal yang terbuka
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape untuk menutup modal
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    bootstrap.Modal.getInstance(openModal).hide();
                }
            }
        });

        // Tambahkan efek loading pada tombol OCR
        document.querySelectorAll('a[href*="ocr=1"]').forEach(function(link) {
            link.addEventListener('click', function(e) {
                const btn = e.target.closest('a');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
                btn.classList.add('disabled');
                
                // Restore tombol setelah 5 detik (jika masih di halaman yang sama)
                setTimeout(function() {
                    if (btn.innerHTML.includes('Memproses')) {
                        btn.innerHTML = originalHtml;
                        btn.classList.remove('disabled');
                    }
                }, 5000);
            });
        });

        // Notifikasi suara untuk pembayaran baru
        let lastPaymentCount = <?= count($pending_payments) ?>;
        
        function checkNewPayments() {
            fetch('check_new_payments.php')
                .then(response => response.json())
                .then(data => {
                    if (data.count > lastPaymentCount) {
                        // Ada pembayaran baru
                        playNotificationSound();
                        showNotification('Ada pembayaran baru menunggu konfirmasi!');
                        lastPaymentCount = data.count;
                    }
                })
                .catch(error => console.error('Error checking new payments:', error));
        }

        function playNotificationSound() {
            // Buat audio notification sederhana
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
        }

        function showNotification(message) {
            if (Notification.permission === 'granted') {
                new Notification('Konfirmasi Pembayaran', {
                    body: message,
                    icon: 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyNCIgaGVpZ2h0PSIyNCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9ImN1cnJlbnRDb2xvciIgc3Ryb2tlLXdpZHRoPSIyIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0xMiAydjIwbTktNy02LjE4QTkgOSAwIDEgMSAxMiAyLjY5WiIvPjwvc3ZnPg=='
                });
            }
        }

        // Minta permission untuk notifikasi
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Check pembayaran baru setiap 15 detik
        setInterval(checkNewPayments, 15000);
    </script>
</body>
</html>
