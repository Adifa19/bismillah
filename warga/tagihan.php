<?php
require_once '../config.php';
requireLogin();

// Pastikan hanya user yang bisa akses
if (isAdmin()) {
    header('Location: profile.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// Function to format date to Indonesian
function formatIndonesianDate($date) {
    $months = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($date);
    $day = date('j', $timestamp);
    $month = $months[(int)date('n', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

// Handle upload bukti pembayaran
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_bukti'])) {
    $user_bill_id = $_POST['user_bill_id'];
    
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === 0) {
        $upload_dir = 'uploads/bukti_pembayaran/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['bukti_pembayaran']['name'], PATHINFO_EXTENSION);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
        
        // Validasi ukuran file (5MB = 5 * 1024 * 1024 bytes)
        if ($_FILES['bukti_pembayaran']['size'] > 5 * 1024 * 1024) {
            $message = 'Ukuran file terlalu besar! Maksimal 5MB.';
            $message_type = 'error';
        } elseif (in_array(strtolower($file_extension), $allowed_extensions)) {
            // Hapus file lama jika ada (untuk kasus upload ulang)
            try {
                $stmt = $pdo->prepare("SELECT bukti_pembayaran FROM user_bills WHERE id = ? AND user_id = ?");
                $stmt->execute([$user_bill_id, $user_id]);
                $old_file = $stmt->fetch();
                
                if ($old_file && $old_file['bukti_pembayaran'] && file_exists($upload_dir . $old_file['bukti_pembayaran'])) {
                    unlink($upload_dir . $old_file['bukti_pembayaran']);
                }
            } catch (PDOException $e) {
                // Lanjutkan proses meskipun gagal hapus file lama
            }
            
            $filename = 'bukti_' . $user_bill_id . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['bukti_pembayaran']['tmp_name'], $filepath)) {
                try {
                    // Simpan waktu upload yang tepat
                    $tanggal_upload = date('Y-m-d H:i:s');
                    
                    // Update dengan tanggal upload - pastikan kolom tanggal_upload sudah ada di database
                    // Jika belum ada, jalankan: ALTER TABLE user_bills ADD COLUMN tanggal_upload DATETIME DEFAULT NULL AFTER bukti_pembayaran;
                    $stmt = $pdo->prepare("UPDATE user_bills SET bukti_pembayaran = ?, status = 'menunggu_konfirmasi', tanggal_upload = ? WHERE id = ? AND user_id = ?");
                    $stmt->execute([$filename, $tanggal_upload, $user_bill_id, $user_id]);
                    
                    $message = 'Bukti pembayaran berhasil diupload pada ' . date('d/m/Y H:i:s') . '. Menunggu konfirmasi admin.';
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = 'Gagal menyimpan data ke database: ' . $e->getMessage();
                    $message_type = 'error';
                }
            } else {
                $message = 'Gagal mengupload file.';
                $message_type = 'error';
            }
        } else {
            $message = 'Format file tidak didukung. Gunakan JPG, JPEG, PNG, atau PDF.';
            $message_type = 'error';
        }
    } else {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar.',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar.',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian.',
            UPLOAD_ERR_NO_FILE => 'Pilih file bukti pembayaran terlebih dahulu.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi.'
        ];
        
        $error_code = $_FILES['bukti_pembayaran']['error'];
        $message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : 'Pilih file bukti pembayaran terlebih dahulu.';
        $message_type = 'error';
    }
}

// Ambil data tagihan user - HANYA yang belum bayar dan yang ditolak
try {
    $stmt = $pdo->prepare("
        SELECT ub.*, b.deskripsi, b.jumlah, b.tanggal as tanggal_tagihan, b.kode_tagihan,
               ub.tanggal_upload,
               DATEDIFF(b.tanggal, CURDATE()) as hari_tersisa
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = ? 
        AND ub.status IN ('menunggu_pembayaran', 'tolak')
        ORDER BY b.tanggal ASC, ub.id DESC
    ");
    $stmt->execute([$user_id]);
    $tagihan = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = 'Gagal mengambil data tagihan: ' . $e->getMessage();
    $message_type = 'error';
    $tagihan = [];
}

// Ambil statistik lengkap untuk informasi
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN ub.status = 'menunggu_pembayaran' THEN 1 END) as belum_bayar,
            COUNT(CASE WHEN ub.status = 'menunggu_konfirmasi' THEN 1 END) as menunggu_konfirmasi,
            COUNT(CASE WHEN ub.status = 'konfirmasi' THEN 1 END) as sudah_bayar,
            COUNT(CASE WHEN ub.status = 'tolak' THEN 1 END) as ditolak,
            SUM(CASE WHEN ub.status = 'menunggu_pembayaran' THEN b.jumlah ELSE 0 END) as total_belum_bayar,
            COUNT(CASE WHEN ub.status = 'menunggu_pembayaran' AND DATEDIFF(b.tanggal, CURDATE()) < 0 THEN 1 END) as terlambat
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = [
        'belum_bayar' => 0,
        'menunggu_konfirmasi' => 0,
        'sudah_bayar' => 0,
        'ditolak' => 0,
        'total_belum_bayar' => 0,
        'terlambat' => 0
    ];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tagihan Warga</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Link ke CSS sidebar terlebih dahulu -->
    <link rel="stylesheet" href="path/to/sidebar.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: white;
            min-height: 100vh;
            color: #333;
        }

        /* Override body styles from sidebar CSS */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background: white;
        }

        /* Main content wrapper - properly adjusted for sidebar */
        .main-content {
            margin-left: 0; /* Default for mobile */
            padding: 2rem;
            min-height: 100vh;
            padding-top: 90px; /* Account for fixed header */
            transition: margin-left 0.3s ease;
        }

        /* Desktop: when sidebar is visible */
        @media screen and (min-width: 768px) {
            .main-content {
                margin-left: 68px; /* Collapsed sidebar width */
                padding-top: 100px; /* More space for header */
            }
            
            /* When sidebar is hovered/expanded */
            .nav:hover ~ .main-content {
                margin-left: 219px; /* Expanded sidebar width */
            }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

       :root {
    --primary-color: #3b82f6; /* Biru utama */
    --primary-dark: #1e3a8a;  /* Biru gelap */
}

.page-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.page-header h1 {
    color: #ffffff; /* Putih agar kontras di atas gradien biru */
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
}

.page-header p {
    color: #e0f2fe; /* Biru muda terang */
    font-size: 1.1rem;
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

        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .alert-warning {
            background: #fffbeb;
            color: #d97706;
            border: 1px solid #fed7aa;
            animation: pulse 2s infinite;
        }

        .alert-info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }

        .bills-container {
            display: grid;
            gap: 1.5rem;
        }

        .bill-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .bill-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        }

        .bill-card.rejected {
            border: 2px solid #ec4899;
            background: linear-gradient(135deg, #fff 0%, #fdf2f8 100%);
        }

        .bill-card.rejected::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #ec4899;
        }

        .bill-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .bill-amount {
            font-size: 2rem;
            font-weight: 700;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bill-code {
            font-size: 0.875rem;
            color: #64748b;
            font-family: 'Courier New', monospace;
            background: #f1f5f9;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .bill-status {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .status-menunggu_pembayaran {
            background: #fef3c7;
            color: #92400e;
        }

        .status-tolak {
            background: #fee2e2;
            color: #dc2626;
        }

        .bill-description {
            margin-bottom: 1.5rem;
            line-height: 1.6;
            color: #374151;
        }

        .bill-date {
            color: #64748b;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .upload-info {
            background: #eff6ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid #bfdbfe;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .upload-info i {
            color: #3b82f6;
            margin-top: 0.125rem;
        }

        .deadline-warning {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .deadline-soon {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .rejection-notice {
            background: #fef2f2;
            color: #dc2626;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            border: 2px solid #fecaca;
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
        }

        .upload-form {
            background: #f8fafc;
            padding: 2rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            border: 2px dashed #cbd5e1;
            transition: all 0.2s;
        }

        .upload-form:hover {
            border-color: #4f46e5;
            background: #f1f5f9;
        }

        .upload-form.reupload {
            background: linear-gradient(135deg, #fef2f2 0%, #fdf2f8 100%);
            border-color: #ec4899;
        }

        .upload-form h4 {
            color: #374151;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.125rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            justify-content: center;
            width: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.25);
        }

        .btn-reupload {
            background: linear-gradient(135deg, #ec4899 0%, #be185d 100%);
        }

        .btn-reupload:hover {
            box-shadow: 0 10px 25px rgba(236, 72, 153, 0.25);
        }

        .no-bills {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .no-bills i {
            font-size: 4rem;
            color: #10b981;
            margin-bottom: 1.5rem;
        }

        .no-bills h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #374151;
        }

        .info-section {
            background: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .info-section h3 {
            color: #4f46e5;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .info-section ul {
            margin-left: 1.5rem;
            margin-top: 1rem;
        }

        .info-section li {
            margin-bottom: 0.5rem;
            color: #64748b;
        }

        /* Mobile responsiveness */
        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 70px;
            }
            
            .page-header {
                padding: 1.5rem;
                margin-bottom: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 2rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .bill-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 1rem;
            }
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .bill-card {
                padding: 1.5rem;
            }
            
            .bill-amount {
                font-size: 1.75rem;
            }
            
            .upload-form {
                padding: 1.5rem;
            }
        }

        /* Tablet adjustments */
        @media screen and (min-width: 768px) and (max-width: 1023px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .file-info {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.5rem;
            display: block;
            font-style: italic;
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
    <?php include('sidebar.php'); ?>  
    
    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Tagihan Saya
                </h1>
                <p>Kelola pembayaran tagihan Anda dengan mudah dan aman</p>
            </div>
            <div class="nav-tabs-container">
                <a href="tagihan.php" class="nav-tab active">
                    <i class="fas fa-plus-circle"></i> Tagihan
                </a>
                <a href="riwayat.php" class="nav-tab">
                    <i class="fas fa-minus-circle"></i> Riwayat Pembayaran
                </a>
            </div>
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Status Alerts -->
            <?php if ($stats['terlambat'] > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    Anda memiliki <?php echo $stats['terlambat']; ?> tagihan yang sudah melewati tanggal jatuh tempo!
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-pending">
                    <div class="stat-number"><?php echo $stats['belum_bayar']; ?></div>
                    <div class="stat-label">Belum Bayar</div>
                </div>
                <div class="stat-card stat-total">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_belum_bayar'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Tagihan</div>
                </div>
                <div class="stat-card stat-overdue">
                    <div class="stat-number"><?php echo $stats['terlambat']; ?></div>
                    <div class="stat-label">Terlambat</div>
                </div>
                <div class="stat-card stat-success">
                    <div class="stat-number"><?php echo $stats['sudah_bayar']; ?></div>
                    <div class="stat-label">Sudah Bayar</div>
                </div>
                <div class="stat-card stat-rejected">
                    <div class="stat-number"><?php echo $stats['ditolak']; ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>
            <!-- Info Section for Payment Instructions -->
            <div class="info-section">
                <h3>
                    <i class="fas fa-university"></i>
                    Informasi Pembayaran
                </h3>
                <p><strong>Silakan lakukan pembayaran melalui M-BANKING berikut:</strong></p>
                <ul>
                    <li><strong>Bank BNI:</strong> 1234567890 a.n. Kas RT/RW</li>
                    <li><strong>Bank BCA:</strong> 0987654321 a.n. Kas RT/RW</li>
                    <li><strong>Bank Mandiri:</strong> 1122334455 a.n. Kas RT/RW</li>
                </ul>
                <p><strong>Catatan:</strong></p>
                <ul>
                    <li>Setiap Transaksi di WAJIBKAN MEMASUKKAN KODE TAGIHAN YANG SUDAH TERSEDIA KEDALAM DESKRIPSI</li>
                    <li>Setelah melakukan pembayaran, segera upload bukti pembayaran</li>
                    <li>Bukti pembayaran akan diverifikasi dalam 1-2 hari kerja</li>
                    <li>Hubungi admin jika ada kendala dalam pembayaran</li>
                </ul>
            </div>

            <!-- Bills Container -->
            <div class="bills-container">
                <?php if (empty($tagihan)): ?>
                    <div class="no-bills">
                        <i class="fas fa-check-circle"></i>
                        <h3>Tidak ada tagihan yang perlu diselesaikan!</h3>
                        <p>Semua tagihan Anda sudah dibayar atau sedang dalam proses konfirmasi.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($tagihan as $bill): ?>
                        <div class="bill-card <?php echo $bill['status'] === 'tolak' ? 'rejected' : ''; ?>">
                            <!-- Bill Header -->
                            <div class="bill-header">
                                <div class="bill-amount">
                                    <i class="fas fa-money-bill-wave"></i>
                                    Rp <?php echo number_format($bill['jumlah'], 0, ',', '.'); ?>
                                </div>
                                <div class="bill-status status-<?php echo $bill['status']; ?>">
                                    <i class="fas fa-<?php echo $bill['status'] === 'menunggu_pembayaran' ? 'clock' : 'times-circle'; ?>"></i>
                                    <?php echo $bill['status'] === 'menunggu_pembayaran' ? 'Menunggu Pembayaran' : 'Ditolak'; ?>
                                </div>
                            </div>

                            <!-- Bill Code -->
                            <div class="bill-code">
                                <i class="fas fa-barcode"></i>
                                Kode: <?php echo htmlspecialchars($bill['kode_tagihan']); ?>
                            </div>

                            <!-- Bill Description -->
                            <div class="bill-description">
                                <?php echo nl2br(htmlspecialchars($bill['deskripsi'])); ?>
                            </div>

                            <!-- Bill Date -->
                            <div class="bill-date">
                                <i class="fas fa-calendar-alt"></i>
                                Jatuh Tempo: <?php echo formatIndonesianDate($bill['tanggal_tagihan']); ?>
                            </div>

                            <!-- Deadline Warning -->
                            <?php if ($bill['hari_tersisa'] < 0): ?>
                                <div class="deadline-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    TERLAMBAT <?php echo abs($bill['hari_tersisa']); ?> HARI!
                                </div>
                            <?php elseif ($bill['hari_tersisa'] <= 3 && $bill['hari_tersisa'] >= 0): ?>
                                <div class="deadline-soon">
                                    <i class="fas fa-clock"></i>
                                    DEADLINE <?php echo $bill['hari_tersisa']; ?> HARI LAGI!
                                </div>
                            <?php endif; ?>

                            <!-- Rejection Notice (if applicable) -->
                            <?php if ($bill['status'] === 'tolak'): ?>
                                <div class="rejection-notice">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <div>
                                        <strong>Bukti pembayaran Anda ditolak.</strong><br>
                                        Silakan upload ulang bukti pembayaran yang valid.
                                        <?php if ($bill['tanggal_upload']): ?>
                                            <br><small>Upload terakhir: <?php echo date('d/m/Y H:i:s', strtotime($bill['tanggal_upload'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Upload Form -->
                            <div class="upload-form <?php echo $bill['status'] === 'tolak' ? 'reupload' : ''; ?>">
                                <h4>
                                    <i class="fas fa-upload"></i>
                                    <?php echo $bill['status'] === 'tolak' ? 'Upload Ulang Bukti Pembayaran' : 'Upload Bukti Pembayaran'; ?>
                                </h4>
                                
                                <div class="upload-info">
                                    <i class="fas fa-info-circle"></i>
                                    <div>
                                        <strong>Informasi Upload:</strong><br>
                                        • Format yang diterima: JPG, JPEG, PNG, PDF<br>
                                        • Ukuran maksimal: 5MB<br>
                                        • Pastikan bukti pembayaran jelas dan dapat dibaca
                                    </div>
                                </div>

                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="user_bill_id" value="<?php echo $bill['id']; ?>">
                                    
                                    <div class="form-group">
                                        <label for="bukti_<?php echo $bill['id']; ?>">
                                            <i class="fas fa-file-upload"></i>
                                            Pilih File Bukti Pembayaran
                                        </label>
                                        <input 
                                            type="file" 
                                            id="bukti_<?php echo $bill['id']; ?>" 
                                            name="bukti_pembayaran" 
                                            class="form-control" 
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            required
                                        >
                                        <span class="file-info">Format: JPG, JPEG, PNG, PDF (Maks. 5MB)</span>
                                    </div>
                                    
                                    <button type="submit" name="upload_bukti" class="btn <?php echo $bill['status'] === 'tolak' ? 'btn-reupload' : ''; ?>">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <?php echo $bill['status'] === 'tolak' ? 'Upload Ulang' : 'Upload Bukti'; ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript untuk enhance user experience -->
    <script>
        // Preview file yang dipilih
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                const label = this.previousElementSibling;
                
                if (file) {
                    // Validasi ukuran file
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File terlalu besar! Maksimal 5MB.');
                        this.value = '';
                        return;
                    }
                    
                    // Validasi format file
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                    if (!allowedTypes.includes(file.type)) {
                        alert('Format file tidak didukung! Gunakan JPG, JPEG, PNG, atau PDF.');
                        this.value = '';
                        return;
                    }
                    
                    // Update label dengan nama file
                    label.innerHTML = `<i class="fas fa-check-circle"></i> ${file.name}`;
                    label.style.color = '#10b981';
                } else {
                    label.innerHTML = `<i class="fas fa-file-upload"></i> Pilih File Bukti Pembayaran`;
                    label.style.color = '#374151';
                }
            });
        });

        // Konfirmasi sebelum upload
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const fileInput = this.querySelector('input[type="file"]');
                if (!fileInput.files[0]) {
                    e.preventDefault();
                    alert('Silakan pilih file bukti pembayaran terlebih dahulu!');
                    return;
                }
                
                if (!confirm('Apakah Anda yakin ingin mengupload bukti pembayaran ini?')) {
                    e.preventDefault();
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        // Smooth scroll untuk mobile
        if (window.innerWidth < 768) {
            document.querySelectorAll('.bill-card').forEach(card => {
                card.addEventListener('click', function() {
                    this.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            });
        }
    </script>
</body>
</html>