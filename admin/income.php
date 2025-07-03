<?php 
error_reporting(0);
include('../config.php');

// Check if user is admin
requireLogin();
requireAdmin();

// Fungsi format rupiah
function format_rupiah($angka){
    return 'Rp ' . number_format($angka, 0, ',', '.');
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

// Get filter parameters
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

// Build query with filters - only show confirmed bills
$params = [];

$sql = "
    SELECT 
        user_bills.id,
        users.username, 
        bills.deskripsi AS deskripsi, 
        bills.tanggal AS tanggal, 
        bills.jumlah AS jumlah,
        user_bills.status
    FROM 
        user_bills 
    JOIN 
        users ON user_bills.user_id = users.id 
    JOIN 
        bills ON user_bills.bill_id = bills.id 
    WHERE user_bills.status = 'konfirmasi'
";

// Add filters
if ($filter_bulan && $filter_tahun) {
    $sql .= " AND MONTH(bills.tanggal) = ? AND YEAR(bills.tanggal) = ?";
    $params[] = $filter_bulan;
    $params[] = $filter_tahun;
} elseif ($filter_tahun) {
    $sql .= " AND YEAR(bills.tanggal) = ?";
    $params[] = $filter_tahun;
}

$sql .= " ORDER BY bills.tanggal DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll();

// Calculate total (all results are confirmed)
$total_pemasukan = 0;
foreach ($result as $row) {
    $total_pemasukan += $row['jumlah'];
}

// Get available years for filter
$year_stmt = $pdo->query("
    SELECT DISTINCT YEAR(bills.tanggal) as tahun 
    FROM user_bills 
    JOIN bills ON user_bills.bill_id = bills.id 
    WHERE user_bills.status = 'konfirmasi'
    ORDER BY tahun DESC
");
$available_years = $year_stmt->fetchAll();

// Get status counts for stats
$stats_stmt = $pdo->query("
    SELECT 
        user_bills.status,
        COUNT(*) as count,
        SUM(bills.jumlah) as total
    FROM user_bills 
    JOIN bills ON user_bills.bill_id = bills.id 
    GROUP BY user_bills.status
");
$stats_data = $stats_stmt->fetchAll();

$confirmed_count = 0;
$pending_count = 0;
$confirmed_total = 0;

foreach ($stats_data as $stat) {
    if ($stat['status'] == 'konfirmasi') {
        $confirmed_count = $stat['count'];
        $confirmed_total = $stat['total'];
    } elseif ($stat['status'] == 'pending') {
        $pending_count = $stat['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Pemasukan - Admin Dashboard</title>
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
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.15);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stats-card.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .stats-card.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
        
        select {
            padding: 0.875rem 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
            background: white;
        }
        
        select:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
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
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.25);
        }
        
        .btn-outline {
            background: white;
            color: #6366f1;
            border: 2px solid #6366f1;
        }
        
        .btn-outline:hover {
            background: #6366f1;
            color: white;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
        
        .badge-confirmed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
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
        
        /* Nav Tabs */
        .nav-tabs {
            background: white;
            border-bottom: 2px solid #e2e8f0;
            padding: 0 2rem;
            display: flex;
            gap: 0;
            margin-bottom: 0;
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
            color: #3b82f6;
            background: #f1f5f9;
        }
        
        .nav-tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
            background: #f1f5f9;
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
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .export-section {
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
                    Manajemen Pemasukan
                </h1>
                <p>Monitor dan kelola data tagihan terkonfirmasi dari user</p>
            </div>

            <div class="nav-tabs">
                <a href="income.php" class="nav-tab active">
                    <i class="fas fa-plus-circle"></i> Pemasukan
                </a>
                <a href="pengeluaran.php" class="nav-tab">
                    <i class="fas fa-minus-circle"></i> Pengeluaran
                </a>
            </div>
            <br><br>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stats-card success">
                    <div class="stats-content">
                        <h3>Total Pemasukan</h3>
                        <h2><?php echo format_rupiah($total_pemasukan); ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                </div>
                <div class="stats-card">
                    <div class="stats-content">
                        <h3>Data Terkonfirmasi</h3>
                        <h2><?php echo count($result); ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-database"></i>
                    </div>
                </div>
                <div class="stats-card warning">
                    <div class="stats-content">
                        <h3>Menunggu Konfirmasi</h3>
                        <h2><?php echo $pending_count; ?></h2>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section">
                <h3>
                    <i class="fas fa-filter"></i>
                    Filter Data
                </h3>
                <form method="GET" class="filter-form" id="filterForm">
                    <div class="form-group">
                        <label>Bulan</label>
                        <select name="bulan">
                            <option value="">Semua Bulan</option>
                            <?php for($i = 1; $i <= 12; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $filter_bulan == $i ? 'selected' : ''; ?>>
                                    <?php echo DateTime::createFromFormat('!m', $i)->format('F'); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tahun</label>
                        <select name="tahun">
                            <option value="">Semua Tahun</option>
                            <?php foreach($available_years as $year): ?>
                                <option value="<?php echo $year['tahun']; ?>" <?php echo $filter_tahun == $year['tahun'] ? 'selected' : ''; ?>>
                                    <?php echo $year['tahun']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
                <div class="filter-actions">
                    <button type="submit" form="filterForm" class="btn" style="color: white;">
                        <i class="fas fa-search"></i>
                        Terapkan Filter
                    </button>
                    <a href="?" class="btn btn-outline">
                        <i class="fas fa-refresh"></i>
                        Reset Filter
                    </a>
                </div>
            </div>

            <!-- Export Buttons -->
            <div class="export-section">
                <a href="export_excel.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export" style="color: white;">
                    <i class="fas fa-file-excel"></i>
                    Export Excel
                </a>
                <a href="export_pdf.php?<?php echo http_build_query($_GET); ?>" class="btn btn-export" style="color: white;">
                    <i class="fas fa-file-pdf"></i>
                    Export PDF
                </a>
            </div>

            <!-- Data Table -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Data Pemasukan Terkonfirmasi
                    </h2>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th><i class="fas fa-hashtag"></i> No</th>
                                <th><i class="fas fa-user"></i> Username</th>
                                <th><i class="fas fa-file-alt"></i> Deskripsi</th>
                                <th><i class="fas fa-calendar"></i> Tanggal</th>
                                <th><i class="fas fa-money-bill"></i> Jumlah</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($result)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-inbox"></i>
                                            <p>Tidak ada data pemasukan terkonfirmasi ditemukan</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $no = 1; foreach ($result as $row): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo sanitize($row['username']); ?></td>
                                        <td><?php echo sanitize($row['deskripsi']); ?></td>
                                        <td><?php echo format_tanggal_indo($row['tanggal']); ?></td>
                                        <td style="font-weight: 600; color: #10b981;"><?php echo format_rupiah($row['jumlah']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto submit form when filter changes
        document.querySelectorAll('#filterForm select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
    </script>
</body>
</html>