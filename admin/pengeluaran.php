<?php
require_once '../config.php';

// Cek login dan admin
requireLogin();
requireAdmin();

// Fungsi format_rupiah - disesuaikan dengan tipe data mediumint
function format_rupiah($angka) {
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
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

// Cek apakah kolom bukti_file sudah ada
function checkColumnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}

$has_bukti_column = checkColumnExists($pdo, 'keluaran', 'bukti_file');

// Proses tambah data pengeluaran
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_expense') {
    try {
        $deskripsi = trim($_POST['deskripsi']);
        $jumlah = intval($_POST['jumlah']);
        $tanggal = $_POST['tanggal'];
        
        // Pastikan jumlah valid
        if ($jumlah < 1000) {
            $error_message = 'Jumlah minimal adalah Rp 1.000';
        } elseif ($jumlah > 16777215) {
            $error_message = 'Jumlah maksimal adalah Rp 16.777.215';
        } else {
            // Handle file upload jika kolom bukti_file ada
            $bukti_file = '';
            if ($has_bukti_column && isset($_FILES['bukti']) && $_FILES['bukti']['error'] == 0) {
                $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
                $file_extension = strtolower(pathinfo($_FILES['bukti']['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_types)) {
                    $upload_dir = 'uploads/bukti/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_name = time() . '_' . $_FILES['bukti']['name'];
                    $upload_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['bukti']['tmp_name'], $upload_path)) {
                        $bukti_file = $file_name;
                    }
                }
            }

            if ($has_bukti_column) {
                $stmt = $pdo->prepare("INSERT INTO keluaran (deskripsi, jumlah, tanggal, bukti_file) VALUES (?, ?, ?, ?)");
                $stmt->execute([$deskripsi, $jumlah, $tanggal, $bukti_file]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO keluaran (deskripsi, jumlah, tanggal) VALUES (?, ?, ?)");
                $stmt->execute([$deskripsi, $jumlah, $tanggal]);
            }
            
            $success_message = 'Data berhasil ditambahkan!';
        }
    } catch(PDOException $e) {
        $error_message = 'Error: ' . $e->getMessage();
    }
}

// Get filter parameters
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Build query with filters
$query = "SELECT * FROM keluaran WHERE 1=1";
$params = [];

if ($filter_bulan && $filter_tahun) {
    $query .= " AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?";
    $params[] = $filter_bulan;
    $params[] = $filter_tahun;
} elseif ($filter_tahun) {
    $query .= " AND YEAR(tanggal) = ?";
    $params[] = $filter_tahun;
}

$query .= " ORDER BY tanggal DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$result = $stmt->fetchAll();

// Calculate total
$total_pengeluaran = array_sum(array_column($result, 'jumlah'));

// Get available years for filter
$year_stmt = $pdo->query("SELECT DISTINCT YEAR(tanggal) as tahun FROM keluaran ORDER BY tahun DESC");
$available_years = $year_stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pengeluaran - Admin Dashboard</title>
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
            background: #1e293b;
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
            background: linear-gradient(135deg, #6B21A8 0%, #4C1D95 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(107, 33, 168, 0.15);
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
        
        .alert-info {
            background: #f0f9ff;
            color: #0c4a6e;
            border: 1px solid #bae6fd;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #6B21A8 0%, #4C1D95 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(107, 33, 168, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-card .stats-content h3 {
            font-size: 1.1rem;
            font-weight: 500;
            opacity: 0.9;
            margin-bottom: 0.5rem;
        }
        
        .stats-card .stats-content h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stats-card .stats-icon {
            font-size: 2.5rem;
            opacity: 0.7;
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
            color: #1e293b;
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
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        label {
            font-weight: 500;
            color: #374151;
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
            border-color: #6B21A8;
            box-shadow: 0 0 0 3px rgba(107, 33, 168, 0.1);
        }
        
        .currency-input {
            position: relative;
        }
        
        .currency-prefix {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }
        
        .currency-input input {
            padding-left: 45px;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #6B21A8 0%, #4C1D95 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            justify-content: center;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(107, 33, 168, 0.25);
        }

        .btn-outline {
            background: white;
            color: #6B21A8;
            border: 2px solid #6B21A8;
        }

        .btn-outline:hover {
            background: #6B21A8;
            color: white;
        }
        
        /* Layout Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .filter-section h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        /* Export Buttons */
        .export-section {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .btn-export:hover {
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.25);
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: white;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }
        
        th {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
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
        
        /* Badge Styles */
        .badge {
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
        
        .btn-view {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            transition: all 0.2s;
        }
        
        .btn-view:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25);
            color: white;
            text-decoration: none;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin: 0;
        }
        
        /* Navigation Tabs */
        .nav-tabs {
            background: white;
            border-bottom: 2px solid #e2e8f0;
            padding: 0 2rem;
            display: flex;
            gap: 0;
            margin-bottom: 0;
            overflow-x: auto;
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
            white-space: nowrap;
        }
        
        .nav-tab:hover {
            color: #6B21A8;
            background: #f1f5f9;
            text-decoration: none;
        }

        .nav-tab.active {
            color: #6B21A8;
            border-bottom-color: #6B21A8;
            background: #f1f5f9;
        }
        
        /* Text utilities */
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6b7280;
        }
        
        .fw-bold {
            font-weight: bold;
        }
        
        .text-danger {
            color: #dc2626;
        }
        
        .py-4 {
            padding: 2rem 0;
        }
        
        .mb-2 {
            margin-bottom: 0.5rem;
        }
        
        .mb-0 {
            margin-bottom: 0;
        }
        
        .me-1 {
            margin-right: 0.25rem;
        }
        
        /* Close button for alerts */
        .alert-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
            opacity: 0.7;
        }
        
        .alert-close:hover {
            opacity: 1;
        }
        
        /* Responsive Design */
        @media (max-width: 1400px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 1200px) {
            .layout-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .main-content {
                padding: 1.5rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h1 {
                font-size: 1.75rem;
            }
            
            .stats-card {
                padding: 1.5rem;
            }
            
            .stats-card .stats-content h2 {
                font-size: 1.5rem;
            }
            
            .stats-card .stats-icon {
                font-size: 2rem;
            }
            
            .card-body {
                padding: 1.5rem;
            }
            
            .filter-section {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.25rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }
            
            .page-header p {
                font-size: 1rem;
                text-align: center;
            }
            
            .nav-tabs {
                padding: 0 1rem;
            }
            
            .nav-tab {
                padding: 0.875rem 1rem;
                font-size: 0.9rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .stats-card {
                padding: 1.25rem;
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .stats-card .stats-content h2 {
                font-size: 1.4rem;
            }
            
            .card-header {
                padding: 1.25rem 1.5rem;
            }
            
            .card-header h2 {
                font-size: 1.125rem;
                flex-wrap: wrap;
                justify-content: center;
                text-align: center;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .filter-section {
                padding: 1.25rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .filter-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .export-section {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.9rem;
                width: 100%;
                justify-content: center;
            }
            
            table {
                font-size: 0.875rem;
                min-width: 500px;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
            
            th {
                font-size: 0.75rem;
            }
            
            .alert {
                padding: 1rem;
                flex-direction: column;
                align-items: stretch;
                text-align: center;
                gap: 0.5rem;
            }
            
            .alert-close {
                align-self: flex-end;
                margin-left: 0;
                margin-top: -0.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .page-header h1 {
                font-size: 1.375rem;
            }
            
            .stats-card .stats-content h2 {
                font-size: 1.25rem;
            }
            
            .stats-card .stats-icon {
                font-size: 1.75rem;
            }
            
            .card-header {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .filter-section {
                padding: 1rem;
            }
            
            .nav-tabs {
                padding: 0 0.5rem;
            }
            
            .nav-tab {
                padding: 0.75rem;
                font-size: 0.85rem;
            }
            
            table {
                min-width: 450px;
            }
            
            th, td {
                padding: 0.625rem 0.375rem;
            }
            
            .btn-view {
                padding: 0.375rem 0.75rem;
                font-size: 0.8rem;
            }
        }
        
        @media (max-width: 480px) {
            .main-content {
                padding: 0.75rem;
            }
            
            .page-header {
                padding: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .stats-grid {
                gap: 0.75rem;
                margin-bottom: 1.5rem;
            }
            
            .card {
                margin-bottom: 1.5rem;
            }
            
            table {
                font-size: 0.8rem;
                min-width: 400px;
            }
            
            th, td {
                padding: 0.5rem 0.25rem;
            }
            
            th {
                font-size: 0.7rem;
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
        
        /* Mobile scroll indicators */
        @media (max-width: 768px) {
            .table-container {
                position: relative;
            }
            
            .table-container:before {
                content: "← Geser untuk melihat lebih banyak →";
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(107, 33, 168, 0.9);
                color: white;
                text-align: center;
                padding: 0.5rem;
                font-size: 0.8rem;
                z-index: 10;
                pointer-events: none;
            }
            
            .nav-tabs:after {
                content: "← Geser →";
                position: absolute;
                right: 0.5rem;
                top: 50%;
                transform: translateY(-50%);
                background: rgba(107, 33, 168, 0.9);
                color: white;
                padding: 0.25rem 0.5rem;
                border-radius: 4px;
                font-size: 0.7rem;
                pointer-events: none;
            }
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
                    <i class="fas fa-money-bill-wave"></i>
                    Manajemen Pengeluaran
                </h1>
                <p>Kelola dan monitor semua pengeluaran keuangan dengan mudah</p>
            </div>

            <div class="nav-tabs">
                <a href="income.php" class="nav-tab">
                    <i class="fas fa-plus-circle"></i> Pemasukan
                </a>
                <a href="pengeluaran.php" class="nav-tab active">
                    <i class="fas fa-minus-circle"></i> Pengeluaran
                </a>
            </div>
            <br><br>

            <!-- Alert for database upgrade -->
            <?php if (!$has_bukti_column): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <strong>Info:</strong> Untuk mengaktifkan fitur upload bukti kwitansi, jalankan query SQL yang disediakan terlebih dahulu.
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <!-- Alert Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()">×</button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stats-card">
                    <div class="stats-content">
                        <h3>Total Pengeluaran</h3>
                        <h2><?php echo format_rupiah($total_pengeluaran); ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-chart-line-down"></i>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-content">
                        <h3>Total Data</h3>
                        <h2><?php echo count($result); ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-content">
                        <h3>Bulan Ini</h3>
                        <h2><?php echo date('F Y'); ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>
            
            <div class="content-grid">
                <!-- Form Input -->
                <div class="card">
                    <div class="card-header">
                        <h2>
                            <i class="fas fa-plus-circle"></i>
                            Tambah Pengeluaran
                        </h2>
                    </div>
                    <div class="card-body">
                        <form action="" method="POST" <?php echo $has_bukti_column ? 'enctype="multipart/form-data"' : ''; ?> id="expenseForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="deskripsi">
                                        <i class="fas fa-edit"></i>
                                        Deskripsi
                                    </label>
                                    <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" required placeholder="Contoh: Kerja Bakti"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="tanggal">
                                        <i class="fas fa-calendar"></i>
                                        Tanggal
                                    </label>
                                    <input type="date" class="form-control" id="tanggal" name="tanggal" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="jumlah">
                                        <i class="fas fa-money-bill"></i>
                                        Jumlah
                                    </label>
                                    <div class="currency-input">
                                        <span class="currency-prefix">Rp</span>
                                        <input type="number" class="form-control" id="jumlah" name="jumlah" min="1000" max="16777215" step="1000" required placeholder="30000">
                                    </div>
                                    <div class="form-text">Masukkan jumlah dalam rupiah penuh (contoh: 40000 untuk Rp 40.000)</div>
                                </div>
                                <?php if ($has_bukti_column): ?>
                                <div class="form-group">
                                    <label for="bukti">
                                        <i class="fas fa-image"></i>
                                        Bukti Kwitansi/Foto
                                    </label>
                                    <input type="file" class="form-control" id="bukti" name="bukti" accept=".jpg,.jpeg,.png,.pdf">
                                    <div class="form-text">Format: JPG, JPEG, PNG, PDF (Max 5MB)</div>
                                </div>
                                <?php endif; ?>
                                <input type="hidden" name="action" value="add_expense">
                                <button type="submit" class="btn" style="color: white;">
                                    <i class="fas fa-save"></i>
                                    Simpan Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Filters and Data -->
                <div>
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <h3>
                            <i class="fas fa-filter"></i>
                            Filter Data
                        </h3>
                        <form method="GET" id="filterForm">
                            <div class="filter-form">
                                <div class="form-group">
                                    <label>Bulan</label>
                                    <select name="bulan" id="filter_bulan">
                                        <option value="">Semua Bulan</option>
                                        <?php 
                                        $bulan_names = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                                       'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                                        for($i = 1; $i <= 12; $i++): 
                                        ?>
                                            <option value="<?php echo $i; ?>" <?php echo $filter_bulan == $i ? 'selected' : ''; ?>>
                                                <?php echo $bulan_names[$i-1]; ?>
                                            </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Tahun</label>
                                    <select name="tahun" id="filter_tahun">
                                        <option value="">Semua Tahun</option>
                                        <?php foreach($available_years as $year): ?>
                                            <option value="<?php echo $year['tahun']; ?>" <?php echo $filter_tahun == $year['tahun'] ? 'selected' : ''; ?>>
                                                <?php echo $year['tahun']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn" style="color: white;">
                                    <i class="fas fa-search"></i>
                                    Terapkan Filter
                                </button>
                                <a href="?" class="btn btn-outline">
                                    <i class="fas fa-refresh"></i>
                                    Reset Filter
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Export Buttons -->
                    <div class="export-section">
                        <a href="export_excel_pengeluaran.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export" style="color: white;">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </a>
                        <a href="export_pdf_pengeluaran.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export" style="color: white;">
                            <i class="fas fa-file-pdf"></i>
                            Export PDF
                        </a>
                    </div>

                    <!-- Data Table -->
                    <div class="card">
                        <div class="card-header">
                            <h2>
                                <i class="fas fa-table"></i>
                                Data Pengeluaran
                            </h2>
                        </div>
                        <div class="table-container">
                            <table>
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-hashtag"></i> No</th>
                                        <th><i class="fas fa-file-alt"></i> Deskripsi</th>
                                        <th><i class="fas fa-calendar"></i> Tanggal</th>
                                        <th><i class="fas fa-money-bill"></i> Jumlah</th>
                                        <?php if ($has_bukti_column): ?>
                                        <th><i class="fas fa-image"></i> Bukti</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($result)): ?>
                                        <tr>
                                            <td colspan="<?php echo $has_bukti_column ? '5' : '4'; ?>" class="text-center py-4">
                                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                                <p class="text-muted mb-0">Tidak ada data ditemukan</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; foreach ($result as $row): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo sanitize($row['deskripsi']); ?></td>
                                                <td><?php echo format_tanggal_indo($row['tanggal']); ?></td>
                                                <td class="fw-bold text-danger"><?php echo format_rupiah($row['jumlah']); ?></td>
                                                <?php if ($has_bukti_column): ?>
                                                <td>
                                                    <?php if (!empty($row['bukti_file'])): ?>
                                                        <a href="uploads/bukti/<?php echo $row['bukti_file']; ?>" target="_blank" class="btn-view">
                                                            <i class="fas fa-eye me-1"></i>Lihat
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);

        // Format currency input
        document.getElementById('jumlah').addEventListener('input', function(e) {
            // Remove any non-digit characters except for the input value
            let value = e.target.value.replace(/[^\d]/g, '');
            
            // Update the input value
            e.target.value = value;
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const jumlah = parseInt(document.getElementById('jumlah').value);
            if (jumlah < 1000) {
                e.preventDefault();
                alert('Jumlah minimal adalah Rp 1.000');
                return false;
            }
            if (jumlah > 16777215) {
                e.preventDefault();
                alert('Jumlah maksimal adalah Rp 16.777.215');
                return false;
            }
            if (isNaN(jumlah) || jumlah <= 0) {
                e.preventDefault();
                alert('Jumlah harus berupa angka yang valid');
                return false;
            }
        });

        <?php if ($has_bukti_column): ?>
        // File input validation
        document.getElementById('bukti').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // in MB
                if (fileSize > 5) {
                    alert('Ukuran file terlalu besar! Maksimal 5MB');
                    e.target.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Format file tidak didukung! Gunakan JPG, JPEG, PNG, atau PDF');
                    e.target.value = '';
                    return;
                }
            }
        });
        <?php endif; ?>

        // Add thousand separator for better readability (optional)
        document.getElementById('jumlah').addEventListener('blur', function(e) {
            const value = parseInt(e.target.value);
            if (!isNaN(value) && value > 0) {
                console.log('Input value:', value);
            }
        });

        // Mobile table scroll hint
        let tableScrollHintShown = false;
        const tableContainer = document.querySelector('.table-container');
        
        if (tableContainer && window.innerWidth <= 768) {
            tableContainer.addEventListener('scroll', function() {
                if (!tableScrollHintShown) {
                    tableScrollHintShown = true;
                    const hint = tableContainer.querySelector(':before');
                    if (hint) {
                        setTimeout(() => {
                            hint.style.opacity = '0';
                        }, 3000);
                    }
                }
            });
        }

        // Smooth scroll for mobile navigation
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    const navTabs = document.querySelector('.nav-tabs');
                    navTabs.scrollLeft = this.offsetLeft - (navTabs.offsetWidth / 2) + (this.offsetWidth / 2);
                }
            });
        });

        // Enhanced button feedback for touch devices
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('touchstart', function() {
                this.style.transform = 'scale(0.98)';
            });
            
            btn.addEventListener('touchend', function() {
                this.style.transform = '';
            });
        });

        // Responsive table helper
        function handleTableResponsive() {
            const table = document.querySelector('table');
            const container = document.querySelector('.table-container');
            
            if (table && container && window.innerWidth <= 768) {
                // Add swipe gesture hint
                let startX = 0;
                container.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].clientX;
                });
                
                container.addEventListener('touchmove', function(e) {
                    if (Math.abs(e.touches[0].clientX - startX) > 10) {
                        // User is swiping, hide hint
                        const hint = container.querySelector(':before');
                        if (hint) {
                            hint.style.display = 'none';
                        }
                    }
                });
            }
        }

        // Initialize responsive features
        window.addEventListener('load', handleTableResponsive);
        window.addEventListener('resize', handleTableResponsive);
    </script>
</body>
</html>
