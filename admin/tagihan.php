<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Generate kode tagihan otomatis
function generateKodeTagihan($user_id) {
    $random = str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
    return "TAG-{$random}-{$user_id}";
}

// Fungsi format tanggal Indonesia
function format_tanggal_indo($tanggal){
    $bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecah = explode('-', $tanggal);
    return ltrim($pecah[2], '0') . ' ' . $bulan[(int)$pecah[1] - 1] . ' ' . $pecah[0];
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
    $qr_data .= "=== PORTAL WARGA ===";
    
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
            ub.status
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id
        JOIN users u ON ub.user_id = u.id
        JOIN pendataan p ON u.id = p.user_id
        WHERE ub.id = ? AND ub.status = 'konfirmasi'
    ");
    
    $stmt->execute([$user_bill_id]);
    $tagihan = $stmt->fetch();
    
    if ($tagihan && !empty($tagihan['bukti_pembayaran'])) {
        $buktiPath = '../warga/uploads/bukti_pembayaran/' . $tagihan['bukti_pembayaran'];
        
        if (file_exists($buktiPath)) {
            $qrData = generateConfirmedQRData($tagihan);
            addQRCodeToImage($buktiPath, $qrData);
            exit;
        }
    }
    
    // Jika tidak ada bukti atau error
    header('HTTP/1.0 404 Not Found');
    echo 'Bukti pembayaran tidak ditemukan';
    exit;
}

// Proses kirim tagihan
if (isset($_POST['kirim_tagihan'])) {
    try {
        $jumlah = $_POST['jumlah'];
        $deskripsi = $_POST['deskripsi'];
        $tanggal_jatuh_tempo = $_POST['tanggal_jatuh_tempo'];
        $pengiriman = $_POST['pengiriman'];
        $admin_id = $_SESSION['user_id'];
        
        // Dapatkan daftar user yang akan dikirimi tagihan
        $target_users = [];
        
        if ($pengiriman === 'semua') {
            // Ambil semua user aktif dengan data lengkap
            $stmt = $pdo->query("
                SELECT u.id, u.username, p.nama_lengkap, p.no_telp 
                FROM users u 
                JOIN pendataan p ON u.id = p.user_id 
                WHERE u.role = 'user' 
                AND u.status_pengguna = 'Aktif' 
                AND p.status_warga = 'Aktif' 
                AND u.data_lengkap = 1
            ");
            $target_users = $stmt->fetchAll();
        } else {
            // Ambil user yang dipilih - pastikan mereka aktif dan data lengkap
            if (!empty($_POST['selected_users'])) {
                $user_ids = implode(',', array_map('intval', $_POST['selected_users']));
                $stmt = $pdo->query("
                    SELECT u.id, u.username, p.nama_lengkap, p.no_telp 
                    FROM users u 
                    JOIN pendataan p ON u.id = p.user_id 
                    WHERE u.id IN ($user_ids)
                    AND u.role = 'user' 
                    AND u.status_pengguna = 'Aktif' 
                    AND p.status_warga = 'Aktif' 
                    AND u.data_lengkap = 1
                ");
                $target_users = $stmt->fetchAll();
            }
        }
        
        $pdo->beginTransaction();
        
        foreach ($target_users as $user) {
            $kode_tagihan = generateKodeTagihan($user['id']);
            $qr_data = json_encode([
                'kode' => $kode_tagihan,
                'jumlah' => $jumlah,
                'user_id' => $user['id']
            ]);
            
            // Insert ke bills
            $stmt = $pdo->prepare("
                INSERT INTO bills (admin_id, kode_tagihan, jumlah, deskripsi, tanggal, qr_code_data) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$admin_id, $kode_tagihan, $jumlah, $deskripsi, $tanggal_jatuh_tempo, $qr_data]);
            $bill_id = $pdo->lastInsertId();
            
            // Insert ke user_bills
            $payment_token = bin2hex(random_bytes(16));
            $qr_hash = hash('sha256', $qr_data);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_bills (bill_id, user_id, tanggal, qr_code_hash, qr_code_data, payment_token) 
                VALUES (?, ?, CURDATE(), ?, ?, ?)
            ");
            $stmt->execute([$bill_id, $user['id'], $qr_hash, $qr_data, $payment_token]);
        }
        
        $pdo->commit();
        $success_message = "Tagihan berhasil dikirim ke " . count($target_users) . " warga";
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Ambil data untuk filter - hanya warga aktif dengan data lengkap
$users_query = $pdo->query("
    SELECT u.id, p.nama_lengkap 
    FROM users u 
    JOIN pendataan p ON u.id = p.user_id 
    WHERE u.role = 'user' 
    AND u.status_pengguna = 'Aktif' 
    AND p.status_warga = 'Aktif'
    AND u.data_lengkap = 1
    ORDER BY p.nama_lengkap
");
$users_for_filter = $users_query->fetchAll();

// Filter dan query data tagihan - hanya tampilkan tagihan dari warga aktif dengan data lengkap
$where_conditions = ["u.role = 'user'", "u.status_pengguna = 'Aktif'", "p.status_warga = 'Aktif'", "u.data_lengkap = 1"];
$params = [];

if (!empty($_GET['status'])) {
    $where_conditions[] = "ub.status = ?";
    $params[] = $_GET['status'];
}

if (!empty($_GET['tanggal'])) {
    $where_conditions[] = "DATE(b.tanggal) = ?";
    $params[] = $_GET['tanggal'];
}

if (!empty($_GET['bulan']) && !empty($_GET['tahun'])) {
    $where_conditions[] = "MONTH(b.tanggal) = ? AND YEAR(b.tanggal) = ?";
    $params[] = $_GET['bulan'];
    $params[] = $_GET['tahun'];
} elseif (!empty($_GET['tahun'])) {
    $where_conditions[] = "YEAR(b.tanggal) = ?";
    $params[] = $_GET['tahun'];
}

if (!empty($_GET['user_id'])) {
    $where_conditions[] = "u.id = ?";
    $params[] = $_GET['user_id'];
}

$where_clause = implode(' AND ', $where_conditions);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Query untuk menghitung total records
$count_query = "
    SELECT COUNT(*) as total
    FROM bills b
    JOIN user_bills ub ON b.id = ub.bill_id
    JOIN users u ON ub.user_id = u.id
    JOIN pendataan p ON u.id = p.user_id
    WHERE $where_clause
";

$count_stmt = $pdo->prepare($count_query);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Query data tagihan dengan pagination
$tagihan_query = "
    SELECT 
        b.kode_tagihan,
        p.nama_lengkap,
        b.jumlah,
        b.deskripsi,
        ub.status,
        ub.tanggal as tanggal_kirim,
        b.tanggal as tenggat,
        p.no_telp,
        ub.bukti_pembayaran,
        ub.id as user_bill_id
    FROM bills b
    JOIN user_bills ub ON b.id = ub.bill_id
    JOIN users u ON ub.user_id = u.id
    JOIN pendataan p ON u.id = p.user_id
    WHERE $where_clause
    ORDER BY b.tanggal DESC
    LIMIT $limit OFFSET $offset
";

$stmt = $pdo->prepare($tagihan_query);
$stmt->execute($params);
$tagihan_list = $stmt->fetchAll();

// Statistik - hanya dari warga aktif dengan data lengkap
$stats_query = "
    SELECT 
        ub.status,
        COUNT(*) as jumlah,
        SUM(b.jumlah) as total_nominal
    FROM bills b
    JOIN user_bills ub ON b.id = ub.bill_id
    JOIN users u ON ub.user_id = u.id
    JOIN pendataan p ON u.id = p.user_id
    WHERE u.role = 'user'
    AND u.status_pengguna = 'Aktif'
    AND p.status_warga = 'Aktif'
    AND u.data_lengkap = 1
    GROUP BY ub.status
";
$stats = $pdo->query($stats_query)->fetchAll();

$total_tagihan = array_sum(array_column($stats, 'jumlah'));
$total_nominal = array_sum(array_column($stats, 'total_nominal'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tagihan - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
 <style>
       * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: white;
    min-height: 100vh;
    padding: 20px;
}

.container {
    width: 100%;
    margin: 0;
    padding: 0;
}

.header {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    text-align: center;
}

.header h1 {
    color: #333;
    font-size: 2rem;
    margin-bottom: 10px;
}

.header p {
    color: #666;
    font-size: 1rem;
}

.main-content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
    margin-bottom: 20px;
}

.card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.card h2 {
    color: #333;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #555;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-control:focus {
    outline: none;
    border-color: #667eea;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 10px;
}

.radio-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.radio-item input[type="radio"] {
    margin: 0;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
}

.btn-success {
    background: linear-gradient(135deg, #56CCF2 0%, #2F80ED 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(86, 204, 242, 0.4);
}

.btn-qr {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
    color: white;
    padding: 8px 16px;
    font-size: 12px;
    border-radius: 6px;
}

.btn-qr:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(255, 107, 107, 0.4);
}

.hidden {
    display: none;
}

select[multiple] {
    height: 120px;
}

/* Statistics Grid */
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
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    text-align: center;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

/* Specific card styles */
.stat-card.total {
    border-left: 4px solid #6366f1;
}

.stat-card.nominal {
    border-left: 4px solid #f59e0b;
}

.stat-card.pending {
    border-left: 4px solid #4f46e5;
}

.stat-card.confirmation {
    border-left: 4px solid #10b981;
}

.stat-card.success {
    border-left: 4px solid #059669;
}

.stat-card.rejected {
    border-left: 4px solid #dc2626;
}

.stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    color: #1e293b;
}

.stat-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

/* Icon colors based on card type */
.stat-card.total .fas {
    color: #6366f1;
}

.stat-card.nominal .fas {
    color: #f59e0b;
}

.stat-card.pending .fas {
    color: #4f46e5;
}

.stat-card.confirmation .fas {
    color: #10b981;
}

.stat-card.success .fas {
    color: #059669;
}

.stat-card.rejected .fas {
    color: #dc2626;
}

.filter-section {
    background: white;
    padding: 20px;
    border-radius: 15px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.table-container {
    background: white;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px;
    text-align: left;
    font-weight: 500;
    font-size: 14px;
}

.table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}

.table tbody tr:hover {
    background: #f8f9ff;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-menunggu_pembayaran {
    background: #fff3cd;
    color: #856404;
}

.status-menunggu_konfirmasi {
    background: #d1ecf1;
    color: #0c5460;
}

.status-konfirmasi {
    background: #d4edda;
    color: #155724;
}

.status-tolak {
    background: #f8d7da;
    color: #721c24;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.whatsapp-btn {
    background: #25d366;
    color: white;
    text-decoration: none;
    padding: 8px 12px;
    border-radius: 5px;
    font-size: 0.8rem;
}

.whatsapp-btn:hover {
    background: #128c7e;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 10px 15px;
    text-decoration: none;
    border: 1px solid #ddd;
    color: #667eea;
    border-radius: 5px;
    transition: all 0.3s;
}

.pagination a:hover {
    background: #667eea;
    color: white;
}

.pagination .current {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.pagination .disabled {
    color: #ccc;
    cursor: not-allowed;
}

.pagination .disabled:hover {
    background: none;
    color: #ccc;
}

.pagination-info {
    text-align: center;
    color: #666;
    margin: 10px 0;
    font-size: 0.9rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    max-width: 500px;
    width: 90%;
    text-align: center;
    position: relative;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-close {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 24px;
    cursor: pointer;
    color: #999;
}

.modal-close:hover {
    color: #333;
}

.qr-code-container {
    margin: 20px 0;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
    border: 2px dashed #667eea;
}

.qr-code-info {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    text-align: left;
}

.qr-code-info h4 {
    color: #1976d2;
    margin-bottom: 10px;
}

.qr-code-info p {
    margin: 5px 0;
    color: #555;
}

@media (max-width: 768px) {
    body {
        padding: 10px;
    }
    
    .main-content {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .table-container {
        overflow-x: auto;
    }

    .table th, .table td {
        padding: 10px 8px;
        font-size: 12px;
    }

    .btn {
        padding: 8px 12px;
        font-size: 12px;
    }
}

/* Header Section */
.page-header {
    background: linear-gradient(135deg, #4f46e5 0%, #581c87 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 25px rgba(79, 70, 229, 0.15);
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
}

/* Navigation Tabs */
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
    </style>
</head>
<body>
    <div class="layout-container">
         <?php include('sidebar.php'); ?>
        <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-file-invoice-dollar"></i>
                    Kelola Tagihan
                </h1>
                <p>Buat, kelola, dan pantau tagihan untuk warga dengan sistem QR Code otomatis</p>
            </div>
            
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="tagihan.php" class="nav-tab active">
                    <i class="fas fa-plus-circle"></i> Buat Tagihan
                </a>
                <a href="konfirmasi.php" class="nav-tab">
                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                </a>
            </div>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Section -->
<div class="card-body">
    <div class="stats-grid">
        <div class="stat-card total">
            <div class="stat-number"><?= number_format($total_tagihan) ?></div>
            <div class="stat-label">
                <i class="fas fa-file-invoice"></i>
                Total Tagihan
            </div>
        </div>
        
        <div class="stat-card nominal">
            <div class="stat-number">Rp <?= number_format($total_nominal) ?></div>
            <div class="stat-label">
                <i class="fas fa-money-bill-wave"></i>
                Total Nominal
            </div>
        </div>
        
        <div class="stat-card pending">
            <div class="stat-number">
                <?php
                $menunggu_pembayaran = 0;
                foreach ($stats as $stat) {
                    if ($stat['status'] == 'menunggu_pembayaran') {
                        $menunggu_pembayaran = $stat['jumlah'];
                        break;
                    }
                }
                echo number_format($menunggu_pembayaran);
                ?>
            </div>
            <div class="stat-label">
                <i class="fas fa-clock"></i>
                Menunggu Pembayaran
            </div>
        </div>
        
        <div class="stat-card confirmation">
            <div class="stat-number">
                <?php
                $menunggu_konfirmasi = 0;
                foreach ($stats as $stat) {
                    if ($stat['status'] == 'menunggu_konfirmasi') {
                        $menunggu_konfirmasi = $stat['jumlah'];
                        break;
                    }
                }
                echo number_format($menunggu_konfirmasi);
                ?>
            </div>
            <div class="stat-label">
                <i class="fas fa-hourglass-half"></i>
                Menunggu Konfirmasi
            </div>
        </div>
        
        <div class="stat-card success">
            <div class="stat-number">
                <?php
                $konfirmasi = 0;
                foreach ($stats as $stat) {
                    if ($stat['status'] == 'konfirmasi') {
                        $konfirmasi = $stat['jumlah'];
                        break;
                    }
                }
                echo number_format($konfirmasi);
                ?>
            </div>
            <div class="stat-label">
                <i class="fas fa-check-circle"></i>
                Terkonfirmasi
            </div>
        </div>
        
        <div class="stat-card rejected">
            <div class="stat-number">
                <?php
                $tolak = 0;
                foreach ($stats as $stat) {
                    if ($stat['status'] == 'tolak') {
                        $tolak = $stat['jumlah'];
                        break;
                    }
                }
                echo number_format($tolak);
                ?>
            </div>
            <div class="stat-label">
                <i class="fas fa-times-circle"></i>
                Ditolak
            </div>
        </div>
    </div>
</div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Form Kirim Tagihan -->
            <div class="card">
                <h2><i class="fas fa-paper-plane"></i> Kirim Tagihan</h2>
                <form method="POST" id="tagihanForm">
                    <div class="form-group">
                        <label for="jumlah">Jumlah Tagihan (Rp)</label>
                        <input type="number" id="jumlah" name="jumlah" class="form-control" required min="1000">
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" required placeholder="Contoh: Iuran bulanan"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_jatuh_tempo">Tanggal Jatuh Tempo</label>
                        <input type="date" id="tanggal_jatuh_tempo" name="tanggal_jatuh_tempo" class="form-control" required>
                    </div>

                    <div class="form-group">
                        <label>Kirim Ke:</label>
                        <div class="radio-group">
                            <div class="radio-item">
                                <input type="radio" id="semua" name="pengiriman" value="semua" checked>
                                <label for="semua">Semua Warga Aktif</label>
                            </div>
                            <div class="radio-item">
                                <input type="radio" id="pilih" name="pengiriman" value="pilih">
                                <label for="pilih">Pilih Warga</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group hidden" id="userSelectGroup">
                        <label for="selected_users">Pilih Warga:</label>
                        <select name="selected_users[]" id="selected_users" class="form-control" multiple>
                            <?php foreach ($users_for_filter as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= sanitize($user['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; margin-top: 5px; display: block;">
                            Tahan Ctrl untuk memilih beberapa warga<br>
                            <em>Hanya menampilkan warga aktif dengan data lengkap</em>
                        </small>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="kirim_tagihan" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Kirim Tagihan
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter Tagihan -->
            <div class="card">
                <h2><i class="fas fa-filter"></i> Filter & Pencarian</h2>
                <form method="GET" id="filterForm">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="status">Status:</label>
                            <select name="status" id="status" class="form-control">
                                <option value="">Semua Status</option>
                                <option value="menunggu_pembayaran" <?= ($_GET['status'] ?? '') == 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                                <option value="menunggu_konfirmasi" <?= ($_GET['status'] ?? '') == 'menunggu_konfirmasi' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                                <option value="konfirmasi" <?= ($_GET['status'] ?? '') == 'konfirmasi' ? 'selected' : '' ?>>Terkonfirmasi</option>
                                <option value="tolak" <?= ($_GET['status'] ?? '') == 'tolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tanggal">Tanggal:</label>
                            <input type="date" name="tanggal" id="tanggal" class="form-control" value="<?= $_GET['tanggal'] ?? '' ?>">
                        </div>

                        <div class="form-group">
                            <label for="bulan">Bulan:</label>
                            <select name="bulan" id="bulan" class="form-control">
                                <option value="">Semua Bulan</option>
                                <?php for($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($_GET['bulan'] ?? '') == $i ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="tahun">Tahun:</label>
                            <select name="tahun" id="tahun" class="form-control">
                                <option value="">Semua Tahun</option>
                                <?php for($year = date('Y'); $year >= 2020; $year--): ?>
                                    <option value="<?= $year ?>" <?= ($_GET['tahun'] ?? '') == $year ? 'selected' : '' ?>><?= $year ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="user_id">Warga:</label>
                            <select name="user_id" id="user_id" class="form-control">
                                <option value="">Semua Warga</option>
                                <?php foreach ($users_for_filter as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= ($_GET['user_id'] ?? '') == $user['id'] ? 'selected' : '' ?>>
                                        <?= sanitize($user['nama_lengkap']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-success">
                            <i class="fas fa-refresh"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabel Tagihan -->
        <div class="table-container">
            <div style="padding: 20px; border-bottom: 1px solid #eee;">
                <h2><i class="fas fa-list"></i> Daftar Tagihan</h2>
                <p style="color: #666; margin-top: 5px;">Menampilkan tagihan dari warga aktif dengan data lengkap</p>
            </div>
            
            <?php if (empty($tagihan_list)): ?>
                <div style="padding: 40px; text-align: center; color: #666;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                    <p>Belum ada tagihan yang sesuai dengan filter</p>
                </div>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode Tagihan</th>
                            <th>Nama Warga</th>
                            <th>Jumlah</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th>Tanggal Kirim</th>
                            <th>Tenggat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tagihan_list as $tagihan): ?>
                        <tr>
                            <td>
                                <strong><?= sanitize($tagihan['kode_tagihan']) ?></strong>
                            </td>
                            <td>
                                <div>
                                    <strong><?= sanitize($tagihan['nama_lengkap']) ?></strong>
                                    <?php if (!empty($tagihan['no_telp'])): ?>
                                        <br><small style="color: #666;"><?= sanitize($tagihan['no_telp']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <strong style="color: #667eea;">Rp <?= number_format($tagihan['jumlah'], 0, ',', '.') ?></strong>
                            </td>
                            <td>
                                <div style="max-width: 200px; word-wrap: break-word;">
                                    <?= sanitize($tagihan['deskripsi']) ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge status-<?= $tagihan['status'] ?>">
                                    <?php
                                    $status_labels = [
                                        'menunggu_pembayaran' => 'Menunggu Pembayaran',
                                        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
                                        'konfirmasi' => 'Terkonfirmasi',
                                        'tolak' => 'Ditolak'
                                    ];
                                    echo $status_labels[$tagihan['status']] ?? $tagihan['status'];
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div>
                                    <strong><?= format_tanggal_indo($tagihan['tanggal_kirim']) ?></strong>
                                </div>
                            </td>
                            <td>
                                <div>
                                    <strong><?= format_tanggal_indo($tagihan['tenggat']) ?></strong>
                                    <?php
                                    $now = new DateTime();
                                    $tenggat = new DateTime($tagihan['tenggat']);
                                    $diff = $now->diff($tenggat);
                                    
                                    if ($now > $tenggat && $tagihan['status'] == 'menunggu_pembayaran') {
                                        echo '<br><small style="color: #dc3545;"><i class="fas fa-exclamation-triangle"></i> Terlambat</small>';
                                    } elseif ($diff->days <= 3 && $tagihan['status'] == 'menunggu_pembayaran') {
                                        echo '<br><small style="color: #ffc107;"><i class="fas fa-clock"></i> ' . $diff->days . ' hari lagi</small>';
                                    }
                                    ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <!-- QR Code Button -->
                                    <a href="?show_with_qr=1&user_bill_id=<?= $tagihan['user_bill_id'] ?>" 
                                        target="_blank" class="btn btn-primary">
                                            <i class="fas fa-qrcode"></i> Lihat dengan QR Code
                                        </a>
                                    
                                    <!-- WhatsApp Button -->
                                    <?php if (!empty($tagihan['no_telp'])): ?>
                                        <a href="https://wa.me/62<?= ltrim($tagihan['no_telp'], '0') ?>?text=Halo%20<?= urlencode($tagihan['nama_lengkap']) ?>,%20Anda%20memiliki%20tagihan%20dengan%20kode%20<?= urlencode($tagihan['kode_tagihan']) ?>%20sebesar%20Rp%20<?= number_format($tagihan['jumlah'], 0, ',', '.') ?>%20untuk%20<?= urlencode($tagihan['deskripsi']) ?>.%20Mohon%20segera%20lakukan%20pembayaran." 
                                           target="_blank" class="whatsapp-btn" title="Kirim via WhatsApp">
                                            <i class="fab fa-whatsapp"></i> WA
                                        </a>
                                    <?php endif; ?>
                                    
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-info">
                Menampilkan <?= ($offset + 1) ?> - <?= min($offset + $limit, $total_records) ?> dari <?= $total_records ?> tagihan
            </div>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">&laquo; Pertama</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&lsaquo; Sebelumnya</a>
                <?php else: ?>
                    <span class="disabled">&laquo; Pertama</span>
                    <span class="disabled">&lsaquo; Sebelumnya</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Selanjutnya &rsaquo;</a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">Terakhir &raquo;</a>
                <?php else: ?>
                    <span class="disabled">Selanjutnya &rsaquo;</span>
                    <span class="disabled">Terakhir &raquo;</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- QR Code Modal -->
    <div id="qrModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="closeQRModal()">&times;</span>
            <h3><i class="fas fa-qrcode"></i> QR Code Tagihan</h3>
            <div id="qrCodeInfo" class="qr-code-info"></div>
            <div id="qrCodeContainer" class="qr-code-container"></div>
            <div style="margin-top: 20px;">
                <button onclick="downloadQR()" class="btn btn-primary">
                    <i class="fas fa-download"></i> Download QR Code
                </button>
                <button onclick="printQR()" class="btn btn-success">
                    <i class="fas fa-print"></i> Print QR Code
                </button>
            </div>
        </div>
    </div>

    <script>
        // Toggle user selection based on radio button
        document.querySelectorAll('input[name="pengiriman"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const userSelectGroup = document.getElementById('userSelectGroup');
                if (this.value === 'pilih') {
                    userSelectGroup.classList.remove('hidden');
                    document.getElementById('selected_users').required = true;
                } else {
                    userSelectGroup.classList.add('hidden');
                    document.getElementById('selected_users').required = false;
                }
            });
        });

        // Set minimum date to today
        document.getElementById('tanggal_jatuh_tempo').min = new Date().toISOString().split('T')[0];

        // Auto submit filter form on change
        document.querySelectorAll('#filterForm select, #filterForm input').forEach(element => {
            element.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });

        // QR Code Modal Functions
        let currentQRData = '';

        function showQRCode(kodeTagihan, tagihan) {
            const modal = document.getElementById('qrModal');
            const qrCodeInfo = document.getElementById('qrCodeInfo');
            const qrCodeContainer = document.getElementById('qrCodeContainer');
            
            // Generate QR data based on status
            let qrData = '';
            if (tagihan.status === 'konfirmasi') {
                // QR untuk tagihan terkonfirmasi
                qrData = `=== KONFIRMASI PEMBAYARAN ===
                Kode Tagihan: ${tagihan.kode_tagihan}
                Nama: ${tagihan.nama_lengkap}
                Jumlah: Rp ${new Intl.NumberFormat('id-ID').format(tagihan.jumlah)}
                Deskripsi: ${tagihan.deskripsi}
                Status: TERKONFIRMASI
                Bukti: ${tagihan.bukti_pembayaran || 'N/A'}
                Tanggal Konfirmasi: ${new Date().toLocaleString('id-ID')}
                Verifikasi: VALID`;
            } else {
                // QR untuk tagihan belum terkonfirmasi
                qrData = JSON.stringify({
                    kode: tagihan.kode_tagihan,
                    jumlah: tagihan.jumlah,
                    user_id: tagihan.user_id || 'N/A'
                });
            }
            
            currentQRData = qrData;
            
            // Update info
            qrCodeInfo.innerHTML = `
                <h4><i class="fas fa-info-circle"></i> Informasi Tagihan</h4>
                <p><strong>Kode:</strong> ${tagihan.kode_tagihan}</p>
                <p><strong>Nama:</strong> ${tagihan.nama_lengkap}</p>
                <p><strong>Jumlah:</strong> Rp ${new Intl.NumberFormat('id-ID').format(tagihan.jumlah)}</p>
                <p><strong>Status:</strong> ${getStatusLabel(tagihan.status)}</p>
                <p><strong>Deskripsi:</strong> ${tagihan.deskripsi}</p>
            `;
            
            // Generate QR Code
            const qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(qrData);
            qrCodeContainer.innerHTML = `<img src="${qrCodeUrl}" alt="QR Code" style="max-width: 100%; height: auto;">`;
            
            modal.classList.add('show');
        }

        function closeQRModal() {
            document.getElementById('qrModal').classList.remove('show');
        }

        function getStatusLabel(status) {
            const labels = {
                'menunggu_pembayaran': 'Menunggu Pembayaran',
                'menunggu_konfirmasi': 'Menunggu Konfirmasi',
                'konfirmasi': 'Terkonfirmasi',
                'tolak': 'Ditolak'
            };
            return labels[status] || status;
        }

        function downloadQR() {
            const qrCodeImg = document.querySelector('#qrCodeContainer img');
            if (qrCodeImg) {
                const link = document.createElement('a');
                link.download = 'qr-code-tagihan.png';
                link.href = qrCodeImg.src;
                link.click();
            }
        }

        function printQR() {
            const qrCodeImg = document.querySelector('#qrCodeContainer img');
            const qrCodeInfo = document.getElementById('qrCodeInfo').innerHTML;
            
            if (qrCodeImg) {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>Print QR Code</title>
                            <style>
                                body { font-family: Arial, sans-serif; text-align: center; padding: 20px; }
                                .info { text-align: left; margin: 20px auto; max-width: 400px; }
                                img { max-width: 300px; margin: 20px 0; }
                            </style>
                        </head>
                        <body>
                            <h2>QR Code Tagihan</h2>
                            <div class="info">${qrCodeInfo}</div>
                            <img src="${qrCodeImg.src}" alt="QR Code">
                            <p style="margin-top: 30px; color: #666; font-size: 0.9em;">
                                Dicetak pada: ${new Date().toLocaleString('id-ID')}
                            </p>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                printWindow.print();
                printWindow.close();
            }
        }

        // Close modal when clicking outside
        document.getElementById('qrModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQRModal();
            }
        });

        // Form validation
        document.getElementById('tagihanForm').addEventListener('submit', function(e) {
            const pengiriman = document.querySelector('input[name="pengiriman"]:checked').value;
            
            if (pengiriman === 'pilih') {
                const selectedUsers = document.getElementById('selected_users').selectedOptions;
                if (selectedUsers.length === 0) {
                    e.preventDefault();
                    alert('Silakan pilih minimal satu warga untuk mengirim tagihan.');
                    return false;
                }
            }
            
            const jumlah = document.getElementById('jumlah').value;
            if (parseInt(jumlah) < 1000) {
                e.preventDefault();
                alert('Jumlah tagihan minimal Rp 1.000');
                return false;
            }
            
            return confirm('Apakah Anda yakin ingin mengirim tagihan ini?');
        });

        // Auto-format number input
        document.getElementById('jumlah').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });

        // Initialize date input with current date + 7 days as default
        if (!document.getElementById('tanggal_jatuh_tempo').value) {
            const defaultDate = new Date();
            defaultDate.setDate(defaultDate.getDate() + 7);
            document.getElementById('tanggal_jatuh_tempo').value = defaultDate.toISOString().split('T')[0];
        }
    </script>
</body>
</html>
