<?php
require_once '../config.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$is_admin = isAdmin();

// Filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_tanggal_dari = isset($_GET['tanggal_dari']) ? $_GET['tanggal_dari'] : '';
$filter_tanggal_sampai = isset($_GET['tanggal_sampai']) ? $_GET['tanggal_sampai'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fungsi untuk format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk badge status
function getStatusBadge($status) {
    $badges = [
        'menunggu_pembayaran' => '<span class="status-badge pending"><i class="fas fa-clock"></i> Menunggu Pembayaran</span>',
        'menunggu_konfirmasi' => '<span class="status-badge processing"><i class="fas fa-hourglass-half"></i> Menunggu Konfirmasi</span>',
        'konfirmasi' => '<span class="status-badge success"><i class="fas fa-check-circle"></i> Terkonfirmasi</span>',
        'lunas' => '<span class="status-badge success"><i class="fas fa-check-double"></i> Lunas</span>',
        'tolak' => '<span class="status-badge rejected"><i class="fas fa-times-circle"></i> Ditolak</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge unknown">Unknown</span>';
}

// Fungsi untuk menentukan apakah item masih bisa dibayar
function canBePaid($status) {
    return ($status == 'menunggu_pembayaran');
}

// Fungsi untuk menentukan status display yang konsisten
function getConsistentStatus($status) {
    return $status;
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

// Fungsi untuk menambahkan QR code ke gambar bukti transfer
function addQRCodeToImage($originalImagePath, $qrCodeData, $outputPath = null) {
    // Cek apakah file gambar ada
    if (!file_exists($originalImagePath)) {
        return false;
    }
    
    // Buat QR code URL
    $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrCodeData);
    
    // Download QR code
    $qrCodeContent = file_get_contents($qrCodeUrl);
    if ($qrCodeContent === false) {
        return false;
    }
    
    // Buat resource gambar dari QR code
    $qrCodeImage = imagecreatefromstring($qrCodeContent);
    
    // Deteksi tipe gambar asli
    $imageInfo = getimagesize($originalImagePath);
    $imageType = $imageInfo[2];
    
    // Buat resource gambar asli berdasarkan tipe
    switch ($imageType) {
        case IMAGETYPE_JPEG:
            $originalImage = imagecreatefromjpeg($originalImagePath);
            break;
        case IMAGETYPE_PNG:
            $originalImage = imagecreatefrompng($originalImagePath);
            break;
        case IMAGETYPE_GIF:
            $originalImage = imagecreatefromgif($originalImagePath);
            break;
        default:
            return false;
    }
    
    if (!$originalImage || !$qrCodeImage) {
        return false;
    }
    
    // Dapatkan dimensi gambar
    $originalWidth = imagesx($originalImage);
    $originalHeight = imagesy($originalImage);
    $qrWidth = imagesx($qrCodeImage);
    $qrHeight = imagesy($qrCodeImage);
    
    // Hitung posisi QR code (pojok kanan atas)
    $qrX = $originalWidth - $qrWidth - 10;
    $qrY = 10;
    
    // Buat background putih semi-transparan untuk QR code
    $bgColor = imagecolorallocatealpha($originalImage, 255, 255, 255, 30);
    imagefilledrectangle($originalImage, $qrX - 5, $qrY - 5, $qrX + $qrWidth + 5, $qrY + $qrHeight + 5, $bgColor);
    
    // Gabungkan QR code dengan gambar asli
    imagecopy($originalImage, $qrCodeImage, $qrX, $qrY, 0, 0, $qrWidth, $qrHeight);
    
    // Simpan gambar hasil
    if ($outputPath === null) {
        // Jika tidak ada output path, tampilkan langsung
        header('Content-Type: image/jpeg');
        imagejpeg($originalImage, null, 90);
    } else {
        // Simpan ke file
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($originalImage, $outputPath, 90);
                break;
            case IMAGETYPE_PNG:
                imagepng($originalImage, $outputPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($originalImage, $outputPath);
                break;
        }
    }
    
    // Bersihkan memory
    imagedestroy($originalImage);
    imagedestroy($qrCodeImage);
    
    return true;
}

// Fungsi untuk generate QR code data untuk tagihan terkonfirmasi
function generateConfirmedQRData($tagihan) {
    $qr_data = "=== KONFIRMASI PEMBAYARAN ===\n";
    $qr_data .= "Kode Tagihan: " . $tagihan['kode_tagihan'] . "\n";
    $qr_data .= "Nama: " . $tagihan['nama_lengkap'] . "\n";
    $qr_data .= "Jumlah: Rp " . number_format($tagihan['jumlah'], 0, ',', '.') . "\n";
    $qr_data .= "Deskripsi: " . $tagihan['deskripsi'] . "\n";
    $qr_data .= "Status: TERKONFIRMASI\n";
    $qr_data .= "Bukti: " . ($tagihan['bukti_pembayaran'] ?: 'N/A') . "\n";
    $qr_data .= "Tanggal Konfirmasi: " . date('d/m/Y H:i:s') . "\n";
    $qr_data .= "Verifikasi: VALID\n";
    
    return $qr_data;
}

// Proses jika ada permintaan untuk menampilkan bukti dengan QR
if (isset($_GET['show_with_qr']) && isset($_GET['user_bill_id'])) {
    $user_bill_id = (int)$_GET['user_bill_id'];
    
    // Ambil data tagihan terkonfirmasi
    $stmt = $pdo->prepare("
        SELECT 
            b.kode_tagihan,
            b.jumlah,
            b.deskripsi,
            p.nama_lengkap,
            ub.bukti_pembayaran,
            ub.status,
            ub.id as user_bill_id
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id
        JOIN users u ON ub.user_id = u.id
        JOIN pendataan p ON u.id = p.user_id
        WHERE ub.id = ? AND ub.status = 'konfirmasi'
    ");
    
    $stmt->execute([$user_bill_id]);
    $tagihan = $stmt->fetch();
    
    if ($tagihan && !empty($tagihan['bukti_pembayaran'])) {
        // Perbaikan path - coba beberapa kemungkinan lokasi file
        $possiblePaths = [
            __DIR__ . '/uploads/bukti_pembayaran/' . $tagihan['bukti_pembayaran'],
            './uploads/bukti_pembayaran/' . $tagihan['bukti_pembayaran'],
            '../uploads/bukti_pembayaran/' . $tagihan['bukti_pembayaran'],
            'uploads/bukti_pembayaran/' . $tagihan['bukti_pembayaran']
        ];
        
        $buktiPath = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $buktiPath = $path;
                break;
            }
        }
        
        if ($buktiPath && file_exists($buktiPath)) {
            $qrData = generateConfirmedQRData($tagihan);
            addQRCodeToImage($buktiPath, $qrData);
            exit;
        } else {
            // Debug: tampilkan path yang dicoba
            header('HTTP/1.0 404 Not Found');
            echo 'Bukti pembayaran tidak ditemukan. Path yang dicoba:<br>';
            foreach ($possiblePaths as $path) {
                echo $path . ' - ' . (file_exists($path) ? 'EXISTS' : 'NOT FOUND') . '<br>';
            }
            exit;
        }
    }
    
    // Jika tidak ada bukti atau error
    header('HTTP/1.0 404 Not Found');
    echo 'Bukti pembayaran tidak ditemukan atau belum terkonfirmasi';
    exit;
}


try {
    // Query untuk menghitung total records - Hanya dari user_bills dan bills
    $count_query = "
        SELECT COUNT(*) as total 
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = :user_id
    ";
    
    // Query utama untuk data - Hanya dari user_bills dan bills
    $main_query = "
        SELECT ub.id as record_id, ub.tanggal, ub.status, b.deskripsi, b.jumlah, ub.bukti_pembayaran
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = :user_id
    ";
    
    $params = [':user_id' => $user_id];
    
    // Tambahkan filter
    if (!empty($filter_status)) {
        $count_query .= " AND ub.status = :status";
        $main_query .= " AND ub.status = :status";
        $params[':status'] = $filter_status;
    }
    
    if (!empty($filter_tanggal_dari)) {
        $count_query .= " AND ub.tanggal >= :tanggal_dari";
        $main_query .= " AND ub.tanggal >= :tanggal_dari";
        $params[':tanggal_dari'] = $filter_tanggal_dari;
    }
    
    if (!empty($filter_tanggal_sampai)) {
        $count_query .= " AND ub.tanggal <= :tanggal_sampai";
        $main_query .= " AND ub.tanggal <= :tanggal_sampai";
        $params[':tanggal_sampai'] = $filter_tanggal_sampai;
    }
    
    // Hitung total records
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_records / $limit);
    
    // Ambil data dengan pagination
    $main_query .= " ORDER BY ub.tanggal DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($main_query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $payment_history = $stmt->fetchAll();
    
    // Hitung statistik - Hanya dari user_bills
    $stats_query = "
        SELECT 
            COUNT(CASE WHEN ub.status = 'konfirmasi' THEN 1 END) as total_terbayar,
            COUNT(CASE WHEN ub.status = 'menunggu_pembayaran' THEN 1 END) as total_menunggu,
            COUNT(CASE WHEN ub.status = 'menunggu_konfirmasi' THEN 1 END) as total_konfirmasi,
            COUNT(CASE WHEN ub.status = 'tolak' THEN 1 END) as total_ditolak,
            SUM(CASE WHEN ub.status = 'konfirmasi' THEN b.jumlah ELSE 0 END) as total_nominal_terbayar
        FROM user_bills ub 
        JOIN bills b ON ub.bill_id = b.id 
        WHERE ub.user_id = :user_id
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([':user_id' => $user_id]);
    $stats = $stats_stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pembayaran - Sistem Warga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
        :root {
            --primary-color: #6366f1;
            --primary-dark: #4f46e5;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            --light-bg: #f8fafc;
            --card-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --card-shadow-hover: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --border-radius: 12px;
            --text-color: #1f2937;
            --text-muted: #6b7280;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
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
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="80" r="1" fill="white" opacity="0.1"/><circle cx="40" cy="60" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.1;
        }

        .page-header .container {
            position: relative;
            z-index: 1;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stats-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            transition: width 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }

        .stats-card:hover::before {
            width: 8px;
        }

        .stats-card.success::before { background-color: var(--success-color); }
        .stats-card.warning::before { background-color: var(--warning-color); }
        .stats-card.info::before { background-color: var(--info-color); }
        .stats-card.danger::before { background-color: var(--danger-color); }

        .stats-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .stats-card.success .stats-icon {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .stats-card.warning .stats-icon {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .stats-card.info .stats-icon {
            background-color: rgba(6, 182, 212, 0.1);
            color: var(--info-color);
        }

        .stats-card.danger .stats-icon {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stats-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }

        .stats-amount {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .filter-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .filter-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }

        .filter-body {
            padding: 2rem;
        }

        .form-floating {
            margin-bottom: 1rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 8px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline-secondary {
            border-radius: 8px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }

        .data-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .data-header {
            background: white;
            border-bottom: 1px solid #e5e7eb;
            padding: 1.5rem 2rem;
        }

        .data-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .data-count {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .table-container {
            overflow-x: auto;
        }

        .table {
            margin: 0;
        }

        .table th {
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
            font-weight: 600;
            color: var(--text-color);
            padding: 1rem;
            white-space: nowrap;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f3f4f6;
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f9fafb;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-badge.pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }

        .status-badge.processing {
            background-color: rgba(6, 182, 212, 0.1);
            color: var(--info-color);
        }

        .status-badge.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }

        .status-badge.rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .action-btn-success:hover {
            background-color: #059669;
            color: white;
        }

        .action-btn-warning {
            background-color: var(--warning-color);
            color: white;
        }

        .action-btn-warning:hover {
            background-color: #d97706;
            color: white;
        }

        .action-btn-outline {
            border: 1px solid #d1d5db;
            color: var(--text-color);
            background-color: white;
        }

        .action-btn-outline:hover {
            background-color: #f3f4f6;
            color: var(--text-color);
        }

        .pagination {
            --bs-pagination-border-radius: 8px;
            --bs-pagination-color: var(--text-color);
            --bs-pagination-hover-color: var(--primary-color);
            --bs-pagination-hover-bg: #f3f4f6;
            --bs-pagination-active-bg: var(--primary-color);
            --bs-pagination-active-border-color: var(--primary-color);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }

        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .empty-text {
            color: var(--text-muted);
        }

        .amount-display {
            font-weight: 700;
            color: var(--text-color);
        }

        .date-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-muted);
        }

        .status-completed {
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stats-card {
                padding: 1rem;
            }

            .stats-number {
                font-size: 1.5rem;
            }

            .filter-body {
                padding: 1.5rem;
            }

            .table-container {
                font-size: 0.875rem;
            }

            .data-header {
                padding: 1rem 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .loading-skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            font-size: 1.2em;
            margin: 0;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Responsive untuk mobile */
        @media (max-width: 767px) {
            .page-header {
                margin-bottom: 20px;
                padding: 15px 10px;
            }
            
            .page-header h1 {
                font-size: 2em;
            }
            
            .page-header p {
                font-size: 1em;
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
    <?php include('sidebar.php'); ?> 

    <!-- Page Header -->
        <div class="container">
            <div class="page-header">
            <h1>💲Riwayat Pembayaran</h1>
            <p>Kelola dan pantau semua transaksi pembayaran tagihan Anda</p>
            </div>
        </div>
        

    <div class="container">
        <div class="nav-tabs-container">
                <a href="tagihan.php" class="nav-tab">
                    <i class="fas fa-plus-circle"></i> Tagihan
                </a>
                <a href="riwayat.php" class="nav-tab active">
                    <i class="fas fa-minus-circle"></i> Riwayat Pembayaran
                </a>
            </div>
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stats-card success">
                <div class="stats-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-number"><?php echo $stats['total_terbayar']; ?></div>
                <div class="stats-label">Terbayar</div>
                <div class="stats-amount"><?php echo formatRupiah($stats['total_nominal_terbayar']); ?></div>
            </div>

            <div class="stats-card warning">
                <div class="stats-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-number"><?php echo $stats['total_menunggu']; ?></div>
                <div class="stats-label">Menunggu Pembayaran</div>
                <div class="stats-amount">Belum dibayar</div>
            </div>

            <div class="stats-card info">
                <div class="stats-icon">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stats-number"><?php echo $stats['total_konfirmasi']; ?></div>
                <div class="stats-label">Menunggu Konfirmasi</div>
                <div class="stats-amount">Sedang diverifikasi</div>
            </div>

            <div class="stats-card danger">
                <div class="stats-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stats-number"><?php echo $stats['total_ditolak']; ?></div>
                <div class="stats-label">Ditolak</div>
                <div class="stats-amount">Perlu pembayaran ulang</div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-header">
                <h5 class="mb-0">
                    <i class="fas fa-filter"></i> Filter & Pencarian
                </h5>
            </div>
            <div class="filter-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <div class="form-floating">
                            <select name="status" id="status" class="form-select">
                                <option value="">Semua Status</option>
                                <option value="menunggu_pembayaran" <?php echo $filter_status == 'menunggu_pembayaran' ? 'selected' : ''; ?>>Menunggu Pembayaran</option>
                                <option value="menunggu_konfirmasi" <?php echo $filter_status == 'menunggu_konfirmasi' ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                                <option value="konfirmasi" <?php echo $filter_status == 'konfirmasi' ? 'selected' : ''; ?>>Terkonfirmasi</option>
                                <option value="tolak" <?php echo $filter_status == 'tolak' ? 'selected' : ''; ?>>Ditolak</option>
                            </select>
                            <label for="status">Status Pembayaran</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="date" name="tanggal_dari" id="tanggal_dari" class="form-control" value="<?php echo $filter_tanggal_dari; ?>">
                            <label for="tanggal_dari">Dari Tanggal</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="date" name="tanggal_sampai" id="tanggal_sampai" class="form-control" value="<?php echo $filter_tanggal_sampai; ?>">
                            <label for="tanggal_sampai">Sampai Tanggal</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex gap-2 h-100 align-items-end">
                            <button type="submit" class="btn btn-primary flex-fill">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="?" class="btn btn-outline-secondary">
                                <i class="fas fa-refresh"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Data Table -->
        <div class="data-card">
            <div class="data-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="data-title">
                        <i class="fas fa-list"></i> Daftar Pembayaran
                    </h5>
                    <div class="data-count">
                        Menampilkan <?php echo count($payment_history); ?> dari <?php echo $total_records; ?> data
                    </div>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger m-3">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php elseif (empty($payment_history)): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="empty-title">Tidak ada data pembayaran</h3>
                    <p class="empty-text">Belum ada riwayat pembayaran yang tersedia atau coba ubah filter pencarian.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Tanggal</th>
                                <th>Deskripsi</th>
                                <th>Jumlah</th>
                                <th>Status</th>
                                <th>Bukti Bayar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payment_history as $index => $item): 
                                $consistent_status = getConsistentStatus($item['status']);
                                $is_completed = in_array($consistent_status, ['konfirmasi', 'lunas']);
                            ?>
                            <tr class="<?php echo $is_completed ? 'status-completed' : ''; ?>">
                                <td><?php echo $offset + $index + 1; ?></td>
                                <td>
                                    <div class="date-display">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo formatTanggalIndonesia($item['tanggal']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 250px;" title="<?php echo sanitize($item['deskripsi']); ?>">
                                        <?php echo sanitize($item['deskripsi']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="amount-display"><?php echo formatRupiah($item['jumlah']); ?></div>
                                </td>
                                <td><?php echo getStatusBadge($consistent_status); ?></td>
                                <td>
                                    <?php if ($item['bukti_pembayaran']): ?>
                                        <a href="?show_with_qr=1&user_bill_id=<?= $item['record_id'] ?>" 
                                            target="_blank" class="action-btn action-btn-outline">
                                            <i class="fas fa-qrcode"></i> Lihat dengan QR
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">Belum upload</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (canBePaid($item['status'])): ?>
                                        <a href="tagihan.php?id=<?php echo $item['record_id']; ?>" 
                                           class="action-btn action-btn-success">
                                            <i class="fas fa-credit-card"></i> Bayar
                                        </a>
                                    <?php elseif ($item['status'] == 'tolak'): ?>
                                        <a href="tagihan.php?id=<?php echo $item['record_id']; ?>" 
                                           class="action-btn action-btn-warning">
                                            <i class="fas fa-redo"></i> Bayar Ulang
                                        </a>
                                    <?php elseif ($is_completed): ?>
                                        <span class="status-badge success">
                                            <i class="fas fa-check-circle"></i> Selesai
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge processing">
                                            <i class="fas fa-hourglass-half"></i> Proses
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo min($total_pages, $page + 1); ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>