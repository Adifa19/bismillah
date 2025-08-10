<?php
require_once '../config.php';
requireLogin();
requireAdmin();

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_nik':
                // Validasi apakah semua field ada
                if (!isset($_POST['nik']) || !isset($_POST['nama_lengkap']) || 
                    !isset($_POST['no_kk']) || !isset($_POST['jenis_kelamin']) || 
                    !isset($_POST['alamat'])) {
                    $error = "Semua field harus diisi";
                    break;
                }
                
                $nik = trim($_POST['nik']);
                $nama_lengkap = trim($_POST['nama_lengkap']);
                $no_kk = trim($_POST['no_kk']);
                $jenis_kelamin = trim($_POST['jenis_kelamin']);
                $alamat = trim($_POST['alamat']);
                
                // Validasi field tidak kosong
                if (empty($nik) || empty($nama_lengkap) || empty($no_kk) || 
                    empty($jenis_kelamin) || empty($alamat)) {
                    $error = "Semua field harus diisi";
                    break;
                }
                
                // Validasi format NIK dan No. KK
                if (!preg_match('/^[0-9]{16}$/', $nik)) {
                    $error = "NIK harus berupa 16 digit angka";
                    break;
                }
                
                if (!preg_match('/^[0-9]{16}$/', $no_kk)) {
                    $error = "No. KK harus berupa 16 digit angka";
                    break;
                }
                
                // Validasi jenis kelamin
                if (!in_array($jenis_kelamin, ['Laki-laki', 'Perempuan'])) {
                    $error = "Jenis kelamin tidak valid";
                    break;
                }
                
                if (strlen($nik) == 16 && strlen($no_kk) == 16) {
                    try {
                        $pdo->beginTransaction();
                        
                        // Check if NIK already exists
                        $stmt = $pdo->prepare("SELECT id FROM pendataan WHERE nik = ?");
                        $stmt->execute([$nik]);
                        if ($stmt->fetch()) {
                            throw new Exception("NIK sudah terdaftar dalam sistem");
                        }
                        
                        // Check if no_kk exists, if not create it
                        $stmt = $pdo->prepare("SELECT id FROM nomor_kk WHERE no_kk = ?");
                        $stmt->execute([$no_kk]);
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("INSERT INTO nomor_kk (no_kk) VALUES (?)");
                            $stmt->execute([$no_kk]);
                        }
                        
                        // Create temporary user for this NIK - FIXED: Shorter username
                        // Use only last 8 digits of NIK to keep username shorter
                        $temp_username = 'u' . substr($nik, -8); // This creates username like 'u12345678' (9 chars max)
                        
                        // Check if username already exists, if so add suffix
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                        $stmt->execute([$temp_username]);
                        if ($stmt->fetch()) {
                            // Add random suffix if username exists
                            $temp_username = 'u' . substr($nik, -6) . rand(10, 99); // Still keeps it short
                        }
                        
                        $temp_password = password_hash($nik, PASSWORD_DEFAULT); // Default password is NIK
                        
                        // MODIFIKASI: Status pengguna langsung 'Aktif'
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, no_kk, status_pengguna) VALUES (?, ?, ?, 'Aktif')");
                        $stmt->execute([$temp_username, $temp_password, $no_kk]);
                        $user_id = $pdo->lastInsertId();
                        
                        // MODIFIKASI: Status warga juga langsung 'Aktif'
                        $stmt = $pdo->prepare("INSERT INTO pendataan (user_id, no_kk, nik, nama_lengkap, jenis_kelamin, alamat, status_warga, is_registered) VALUES (?, ?, ?, ?, ?, ?, 'Aktif', 0)");
                        $stmt->execute([$user_id, $no_kk, $nik, $nama_lengkap, $jenis_kelamin, $alamat]);
                        
                        $pdo->commit();
                        $message = "NIK berhasil ditambahkan dengan status AKTIF. Username: $temp_username (Password default: NIK)";
                    } catch (Exception $e) {
                        $pdo->rollback();
                        $error = "Error: " . $e->getMessage();
                    }
                } else {
                    $error = "Format data tidak valid";
                }
                break;
                
            case 'toggle_status':
                // Validasi field untuk toggle status
                if (!isset($_POST['user_id']) || !isset($_POST['current_status'])) {
                    $error = "Data tidak lengkap untuk mengubah status";
                    break;
                }
                
                $user_id = (int)$_POST['user_id'];
                $current_status = trim($_POST['current_status']);
                
                if ($user_id <= 0 || !in_array($current_status, ['Aktif', 'Tidak Aktif'])) {
                    $error = "Data tidak valid";
                    break;
                }
                
                $new_status = ($current_status === 'Aktif') ? 'Tidak Aktif' : 'Aktif';
                
                try {
                    $pdo->beginTransaction();
                    
                    // Update user status
                    $stmt = $pdo->prepare("UPDATE users SET status_pengguna = ? WHERE id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    
                    // Update pendataan status
                    $stmt = $pdo->prepare("UPDATE pendataan SET status_warga = ? WHERE user_id = ?");
                    $stmt->execute([$new_status, $user_id]);
                    
                    if ($new_status === 'Tidak Aktif') {
                        $message = "Pengguna berhasil dinonaktifkan";
                    } else {
                        $message = "Pengguna berhasil diaktifkan";
                    }
                    
                    $pdo->commit();
                } catch (Exception $e) {
                    $pdo->rollback();
                    $error = "Error: " . $e->getMessage();
                }
                break;
        }
    }
}

// Function untuk format tanggal Indonesia - DIPERBAIKI
function formatTanggalIndonesia($tanggal) {
    $bulan = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_nama = $bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan_nama . ' ' . $tahun;
}

// Function untuk format tanggal dengan jam (opsional)
function formatTanggalIndonesiaLengkap($tanggal) {
    $bulan = array(
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    );
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_nama = $bulan[date('n', $timestamp)];
    $tahun = date('Y', $timestamp);
    $jam = date('H:i', $timestamp);
    
    return $hari . ' ' . $bulan_nama . ' ' . $tahun . ' ' . $jam;
}

// PERTAMA: Ambil semua data untuk stats cards (tanpa filter)
$stmt_all = $pdo->prepare("
    SELECT DISTINCT
        u.id, 
        u.username, 
        u.status_pengguna, 
        u.created_at as user_created_at,
        u.no_kk,
        p.nik, 
        p.nama_lengkap, 
        p.jenis_kelamin, 
        p.alamat, 
        p.status_warga,
        p.is_registered,
        p.created_at as pendataan_created_at
    FROM users u 
    INNER JOIN pendataan p ON u.id = p.user_id 
    WHERE u.role = 'user'
        AND p.nik IS NOT NULL 
        AND p.no_kk IS NOT NULL
    GROUP BY u.id, p.nik, p.no_kk
    ORDER BY u.created_at DESC
");
$stmt_all->execute();
$all_users_raw = $stmt_all->fetchAll();

// Proses untuk menghilangkan duplikasi dari data semua users
$all_users_unique = [];
$all_processed_niks = [];

foreach ($all_users_raw as $user) {
    $nik = $user['nik'];
    $no_kk = $user['no_kk'];
    
    // Cek apakah kombinasi NIK dan No. KK sudah diproses
    $combination_key = $nik . '_' . $no_kk;
    
    if (!isset($all_processed_niks[$combination_key])) {
        $all_users_unique[] = $user;
        $all_processed_niks[$combination_key] = true;
    }
}

// Data lengkap untuk stats cards
$all_users = $all_users_unique;

// KEDUA: Ambil parameter filter dari GET
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_registered = isset($_GET['registered']) ? $_GET['registered'] : '';
$filter_gender = isset($_GET['gender']) ? $_GET['gender'] : '';
$filter_search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Modifikasi query untuk menambahkan filter pada tampilan tabel
$whereConditions = [
    "u.role = 'user'",
    "p.nik IS NOT NULL",
    "p.no_kk IS NOT NULL"
];

if (!empty($filter_status)) {
    $whereConditions[] = "u.status_pengguna = :filter_status";
}

if (!empty($filter_registered)) {
    $whereConditions[] = "p.is_registered = :filter_registered";
}

if (!empty($filter_gender)) {
    $whereConditions[] = "p.jenis_kelamin = :filter_gender";
}

if (!empty($filter_search)) {
    $whereConditions[] = "(p.nama_lengkap LIKE :search OR p.nik LIKE :search OR u.username LIKE :search OR p.no_kk LIKE :search)";
}

$whereClause = implode(' AND ', $whereConditions);

$stmt = $pdo->prepare("
    SELECT DISTINCT
        u.id, 
        u.username, 
        u.status_pengguna, 
        u.created_at as user_created_at,
        u.no_kk,
        p.nik, 
        p.nama_lengkap, 
        p.jenis_kelamin, 
        p.alamat, 
        p.status_warga,
        p.is_registered,
        p.created_at as pendataan_created_at
    FROM users u 
    INNER JOIN pendataan p ON u.id = p.user_id 
    WHERE $whereClause
    GROUP BY u.id, p.nik, p.no_kk
    ORDER BY u.created_at DESC
");

// Bind parameters
if (!empty($filter_status)) {
    $stmt->bindParam(':filter_status', $filter_status);
}
if (!empty($filter_registered)) {
    $stmt->bindParam(':filter_registered', $filter_registered);
}
if (!empty($filter_gender)) {
    $stmt->bindParam(':filter_gender', $filter_gender);
}
if (!empty($filter_search)) {
    $search_param = "%$filter_search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$filtered_users = $stmt->fetchAll();

// Proses untuk menghilangkan duplikasi secara manual untuk data filtered
$unique_filtered_users = [];
$processed_filtered_niks = [];

foreach ($filtered_users as $user) {
    $nik = $user['nik'];
    $no_kk = $user['no_kk'];
    
    // Cek apakah kombinasi NIK dan No. KK sudah diproses
    $combination_key = $nik . '_' . $no_kk;
    
    if (!isset($processed_filtered_niks[$combination_key])) {
        $unique_filtered_users[] = $user;
        $processed_filtered_niks[$combination_key] = true;
    }
}

// Data yang ditampilkan di tabel (dengan filter)
$users = $unique_filtered_users;

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengguna - Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Full Screen Layout */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: #312e81;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-x: auto;
            background: #f8fafc;
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
            border-radius: 16px 16px 0 0;
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

        /* Alert Messages */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
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

        /* Card Components */
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Styles */
        .form-grid {
            display: grid;
            gap: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        label {
            font-weight: 500;
            color: #312e81;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        input, textarea, select {
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #4f46e5 0%, #581c87 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(79, 70, 229, 0.25);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn-success:hover {
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.25);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .btn-warning:hover {
            box-shadow: 0 10px 25px rgba(245, 158, 11, 0.25);
        }

        /* Table Styles */
        .table-container {
            overflow: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        th {
            background: linear-gradient(135deg, #312e81 0%, #581c87 100%);
            color: white;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8fafc;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.375rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .status-aktif {
            background: #dcfce7;
            color: #166534;
        }

        .status-tidak-aktif {
            background: #fee2e2;
            color: #dc2626;
        }

        .status-registered {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-not-registered {
            background: #fef3c7;
            color: #92400e;
        }

        /* Actions */
        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .layout-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                padding: 0 1rem;
                flex-direction: column;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card {
            animation: slideIn 0.4s ease-out;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            color: #64748b;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Filter Card Styles */
.filter-card {
    margin-bottom: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.filter-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    background-color: #f8fafc;
}

.filter-card .card-header h2 {
    margin: 0;
    color: #1e293b;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-content {
    padding: 1.5rem;
    transition: all 0.3s ease;
}

.filter-content.hidden {
    display: none;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.filter-group label {
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-group input,
.filter-group select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: border-color 0.2s ease;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-actions {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    padding-top: 1rem;
    border-top: 1px solid #e5e7eb;
}

.filter-info {
    margin-left: auto;
    color: #6b7280;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn {
    padding: 0.75rem 1rem;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    text-decoration: none;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background-color: #2563eb;
}

.btn-secondary {
    background-color: #6b7280;
    color: white;
}

.btn-secondary:hover {
    background-color: #4b5563;
}

.btn-sm {
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-info {
        margin-left: 0;
        text-align: center;
    }
}

/* Animation for toggle */
.filter-content {
    max-height: 500px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.filter-content.collapsed {
    max-height: 0;
    padding-top: 0;
    padding-bottom: 0;
}
    </style>
</head>
<body>
    <div class="layout-container">
        <?php include('sidebar.php'); ?>
        
        <div class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <i class="fas fa-users-cog"></i>
                    Kelola Pengguna
                </h1>
                <p>Manajemen NIK dan Status Pengguna Warga</p>
            </div>
            
            <!-- Alert Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= sanitize($message) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards - Menggunakan $all_users (data lengkap tanpa filter) -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?= count($all_users) ?></h3>
                    <p>Total Pengguna</p>
                </div>
                <div class="stat-card">
                    <h3><?= count(array_filter($all_users, function($u) { return $u['status_pengguna'] === 'Aktif'; })) ?></h3>
                    <p>Pengguna Aktif</p>
                </div>
                <div class="stat-card">
                    <h3><?= count(array_filter($all_users, function($u) { return $u['is_registered'] == 1; })) ?></h3>
                    <p>Sudah Terdaftar</p>
                </div>
                <div class="stat-card">
                    <h3><?= count(array_filter($all_users, function($u) { return $u['is_registered'] == 0; })) ?></h3>
                    <p>Belum Terdaftar</p>
                </div>
            </div>
            
            <!-- Add NIK Form -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-user-plus"></i>
                        Tambah NIK Warga Baru
                    </h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="addNikForm">
                        <input type="hidden" name="action" value="add_nik">
                        
                        <div class="form-grid">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nik">
                                        <i class="fas fa-id-card"></i>
                                        NIK (16 digit)
                                    </label>
                                    <input type="text" id="nik" name="nik" maxlength="16" 
                                           pattern="[0-9]{16}" placeholder="Masukkan 16 digit NIK" required>
                                </div>
                                <div class="form-group">
                                    <label for="no_kk">
                                        <i class="fas fa-home"></i>
                                        No. KK (16 digit)
                                    </label>
                                    <input type="text" id="no_kk" name="no_kk" maxlength="16" 
                                           pattern="[0-9]{16}" placeholder="Masukkan 16 digit No. KK" required>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nama_lengkap">
                                        <i class="fas fa-user"></i>
                                        Nama Lengkap
                                    </label>
                                    <input type="text" id="nama_lengkap" name="nama_lengkap" 
                                           maxlength="100" placeholder="Masukkan nama lengkap" required>
                                </div>
                                <div class="form-group">
                                    <label for="jenis_kelamin">
                                        <i class="fas fa-venus-mars"></i>
                                        Jenis Kelamin
                                    </label>
                                    <select id="jenis_kelamin" name="jenis_kelamin" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="Laki-laki">Laki-laki</option>
                                        <option value="Perempuan">Perempuan</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="alamat">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Alamat
                                </label>
                                <textarea id="alamat" name="alamat" rows="3" maxlength="100" 
                                         placeholder="Masukkan alamat lengkap" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn" style="color: white;">
                                <i class="fas fa-plus"></i>
                                Tambah NIK
                            </button>
                        </div>
                    </form>
                </div>
            </div>

           <!-- Filter Section - Letakkan setelah Stats Cards dan sebelum Add NIK Form -->
<div class="card filter-card">
    <div class="card-header">
        <h2>
            <i class="fas fa-filter"></i>
            Filter & Pencarian
        </h2>
    </div>
    <div class="filter-content" id="filterContent">
        <form method="GET" id="filterForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="search">
                        <i class="fas fa-search"></i>
                        Pencarian
                    </label>
                    <input type="text" id="search" name="search" 
                           placeholder="Cari nama, NIK, username, atau No. KK..."
                           value="<?= htmlspecialchars($filter_search) ?>">
                </div>
                
                <div class="filter-group">
                    <label for="status">
                        <i class="fas fa-toggle-on"></i>
                        Status Pengguna
                    </label>
                    <select id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?= $filter_status === 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="Tidak Aktif" <?= $filter_status === 'Tidak Aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="registered">
                        <i class="fas fa-user-check"></i>
                        Status Registrasi
                    </label>
                    <select id="registered" name="registered">
                        <option value="">Semua</option>
                        <option value="1" <?= $filter_registered === '1' ? 'selected' : '' ?>>Sudah Terdaftar</option>
                        <option value="0" <?= $filter_registered === '0' ? 'selected' : '' ?>>Belum Terdaftar</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="gender">
                        <i class="fas fa-venus-mars"></i>
                        Jenis Kelamin
                    </label>
                    <select id="gender" name="gender">
                        <option value="">Semua</option>
                        <option value="Laki-laki" <?= $filter_gender === 'Laki-laki' ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="Perempuan" <?= $filter_gender === 'Perempuan' ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i>
                    Terapkan Filter
                </button>
                <button type="button" id="resetFilter" class="btn btn-secondary">
                    <i class="fas fa-undo"></i>
                    Reset Filter
                </button>
                <div class="filter-info">
                    <i class="fas fa-info-circle"></i>
                    Menampilkan <?= count($users) ?> dari total <?= count($all_users) ?> pengguna
                </div>
            </div>
        </form>
    </div>
</div>

            
            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Daftar Pengguna
                    </h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-id-card"></i> NIK</th>
                                <th><i class="fas fa-user"></i> Nama Lengkap</th>
                                <th><i class="fas fa-user-tag"></i> Username</th>
                                <th><i class="fas fa-home"></i> No. KK</th>
                                <th><i class="fas fa-venus-mars"></i> Jenis Kelamin</th>
                                <th><i class="fas fa-toggle-on"></i> Status</th>
                                <th><i class="fas fa-user-check"></i> Terdaftar</th>
                                <th><i class="fas fa-calendar"></i> Dibuat</th>
                                <th><i class="fas fa-cogs"></i> Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= sanitize($user['nik'] ?? '-') ?></td>
                                <td><?= sanitize($user['nama_lengkap'] ?? '-') ?></td>
                                <td><?= sanitize($user['username']) ?></td>
                                <td><?= sanitize($user['no_kk'] ?? '-') ?></td>
                                <td><?= sanitize($user['jenis_kelamin'] ?? '-') ?></td>
                                <td>
                                    <span class="status-badge <?= $user['status_pengguna'] === 'Aktif' ? 'status-aktif' : 'status-tidak-aktif' ?>">
                                        <i class="fas fa-circle"></i>
                                        <?= sanitize($user['status_pengguna']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= $user['is_registered'] ? 'status-registered' : 'status-not-registered' ?>">
                                        <i class="fas <?= $user['is_registered'] ? 'fa-check' : 'fa-times' ?>"></i>
                                        <?= $user['is_registered'] ? 'Ya' : 'Belum' ?>
                                    </span>
                                </td>
                                <td><?= formatTanggalIndonesiaLengkap($user['user_created_at']) ?></td>
                                <td>
                                    <div class="actions">
                                        <!-- Toggle Status Button -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <input type="hidden" name="current_status" value="<?= $user['status_pengguna'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $user['status_pengguna'] === 'Aktif' ? 'btn-warning' : 'btn-success' ?>"
                                                    onclick="return confirm('Yakin ingin <?= $user['status_pengguna'] === 'Aktif' ? 'menonaktifkan' : 'mengaktifkan' ?> pengguna ini?')">
                                                <i class="fas <?= $user['status_pengguna'] === 'Aktif' ? 'fa-lock' : 'fa-unlock' ?>"></i>
                                                <?= $user['status_pengguna'] === 'Aktif' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #64748b; padding: 2rem;">
                                    <i class="fas fa-users" style="font-size: 2rem; margin-bottom: 1rem; display: block;"></i>
                                    Belum ada pengguna yang terdaftar
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sidebar toggle function
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Auto format NIK and No. KK input to numbers only
const nikInput = document.getElementById('nik');
const noKkInput = document.getElementById('no_kk');

if (nikInput) {
    nikInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

if (noKkInput) {
    noKkInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

// Form validation
const mainForm = document.querySelector('form[method="POST"]');
if (mainForm) {
    mainForm.addEventListener('submit', function(e) {
        const nik = document.getElementById('nik');
        const no_kk = document.getElementById('no_kk');
        
        if (nik && no_kk) {
            if (nik.value.length !== 16) {
                alert('NIK harus 16 digit!');
                e.preventDefault();
                return;
            }
            
            if (no_kk.value.length !== 16) {
                alert('No. KK harus 16 digit!');
                e.preventDefault();
                return;
            }
        }
    });
}

// Handle window resize
window.addEventListener('resize', function() {
    const sidebar = document.getElementById('sidebar');
    if (window.innerWidth > 768) {
        sidebar.classList.remove('show');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    // Toggle filter visibility
    const toggleBtn = document.getElementById('toggleFilter');
    const filterContent = document.getElementById('filterContent');
    
    if (toggleBtn && filterContent) {
        const toggleIcon = toggleBtn.querySelector('i');
        
        toggleBtn.addEventListener('click', function() {
            if (filterContent.classList.contains('collapsed')) {
                filterContent.classList.remove('collapsed');
                if (toggleIcon) toggleIcon.className = 'fas fa-chevron-down';
            } else {
                filterContent.classList.add('collapsed');
                if (toggleIcon) toggleIcon.className = 'fas fa-chevron-right';
            }
        });
    }
    
    // Reset filter - DIPERBAIKI
    const resetBtn = document.getElementById('resetFilter');
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            // Redirect ke halaman tanpa parameter query string
            window.location.href = window.location.pathname;
        });
    }
    
    // Real-time search (optional)
    const searchInput = document.getElementById('search');
    if (searchInput) {
        let searchTimeout;
        
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                // Auto-submit form after 1 second of no typing
                // Uncomment next line if you want real-time search
                // document.getElementById('filterForm').submit();
            }, 1000);
        });
    }
    
    // Form validation
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            // Optional: Add form validation here
        });
    }
});
</script>
</body>
</html>



