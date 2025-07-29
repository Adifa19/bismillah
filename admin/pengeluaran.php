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
        
        /* Layout Container */
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.15);
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
            border-radius: 12px;
            padding: 0.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
            display: flex;
            gap: 0.5rem;
        }
        
        .nav-tab {
            flex: 1;
            padding: 1rem 1.5rem;
            text-decoration: none;
            color: #64748b;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }
        
        .nav-tab:hover {
            color: #dc2626;
            background: #fef2f2;
            text-decoration: none;
            transform: translateY(-1px);
        }

        .nav-tab.active {
            color: white;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.15);
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
        
        /* Main Layout Grid */
        .main-grid {
            display: grid;
            gap: 2rem;
        }
        
        .form-section {
            order: 1;
        }
        
        .data-section {
            order: 2;
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
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
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
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
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
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.25);
            color: white;
            text-decoration: none;
        }

        .btn-outline {
            background: white;
            color: #dc2626;
            border: 2px solid #dc2626;
        }

        .btn-outline:hover {
            background: #dc2626;
            color: white;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        }
        
        .btn-export:hover {
            box-shadow: 0 10px 25px rgba(59, 130, 246, 0.25);
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
        
        /* Export Section */
        .export-section {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
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
            min-width: 800px;
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
        
        .table-empty {
            text-align: center;
            padding: 3rem 2rem;
            color: #64748b;
        }
        
        .table-empty i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            opacity: 0.5;
        }
        
        .table-empty p {
            font-size: 1.1rem;
            margin: 0;
        }
        
        /* Responsive Design */
        @media (min-width: 1200px) {
            .main-grid {
                grid-template-columns: 400px 1fr;
            }
            
            .form-section {
                order: 1;
            }
            
            .data-section {
                order: 2;
            }
        }
        
        @media (max-width: 1199px) {
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions, .export-section {
                flex-direction: column;
            }
            
            .nav-tabs {
                flex-direction: column;
                gap: 0.25rem;
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

            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="income.php" class="nav-tab">
                    <i class="fas fa-plus-circle"></i> Pemasukan
                </a>
                <a href="pengeluaran.php" class="nav-tab active">
                    <i class="fas fa-minus-circle"></i> Pengeluaran
                </a>
            </div>

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
            
            <!-- Main Content Grid -->
            <div class="main-grid">
                <!-- Form Section -->
                <div class="form-section">
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
                                    <button type="submit" class="btn">
                                        <i class="fas fa-save"></i>
                                        Simpan Data
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Data Section -->
                <div class="data-section">
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
                                <button type="submit" class="btn">
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
                        <a href="export_excel_pengeluaran.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export">
                            <i class="fas fa-file-excel"></i>
                            Export Excel
                        </a>
                        <a href="export_pdf_pengeluaran.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export">
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
                                            <td colspan="<?php echo $has_bukti_column ? '5' : '4'; ?>" class="table-empty">
                                                <i class="fas fa-inbox"></i>
                                                <p>Tidak ada data pengeluaran ditemukan</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = 1; foreach ($result as $row): ?>
                                            <tr>
                                                <td><?php echo $no++; ?></td>
                                                <td><?php echo htmlspecialchars($row['deskripsi']); ?></td>
                                                <td><?php echo format_tanggal_indo($row['tanggal']); ?></td>
                                                <td style="font-weight: 600; color: #dc2626;"><?php echo format_rupiah($row['jumlah']); ?></td>
                                                <?php if ($has_bukti_column): ?>
                                                <td>
                                                    <?php if (!empty($row['bukti_file'])): ?>
                                                        <a href="uploads/bukti/<?php echo $row['bukti_file']; ?>" target="_blank" class="btn-view">
                                                            <i class="fas fa-eye"></i>Lihat
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="color: #9ca3af;">-</span>
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
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
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
        document.querySelector('#expenseForm').addEventListener('submit', function(e) {
            const jumlah = parseInt(document.getElementById('jumlah').value);
            const deskripsi = document.getElementById('deskripsi').value.trim();
            
            if (!deskripsi) {
                e.preventDefault();
                alert('Deskripsi harus diisi');
                return false;
            }
            
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

        // Enhanced animations for alerts
        document.querySelectorAll('.alert').forEach(function(alert) {
            alert.style.transition = 'all 0.3s ease';
        });

        // Smooth form submission feedback
        document.querySelector('#expenseForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Menyimpan...';
            submitBtn.disabled = true;
            
            // Reset button after 3 seconds if form doesn't redirect
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        });

        // Filter form auto-submit on change
        document.getElementById('filter_bulan').addEventListener('change', function() {
            // Optional: auto-submit filter form when selection changes
            // document.getElementById('filterForm').submit();
        });

        document.getElementById('filter_tahun').addEventListener('change', function() {
            // Optional: auto-submit filter form when selection changes
            // document.getElementById('filterForm').submit();
        });
    </script>
</body>
</html>
