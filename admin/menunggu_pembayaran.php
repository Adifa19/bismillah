<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Filter berdasarkan status
$status_filter = $_GET['status'] ?? 'semua';
$search = $_GET['search'] ?? '';

// Query untuk mengambil data pembayaran
$where_conditions = [];
$params = [];

if ($status_filter !== 'semua') {
    $where_conditions[] = "ub.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.username LIKE ? OR b.deskripsi LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT ub.*, u.username, u.no_kk, b.deskripsi, b.jumlah, b.tanggal as jatuh_tempo,
           CASE 
               WHEN ub.bukti_pembayaran IS NOT NULL THEN 'Ada Bukti'
               ELSE 'Belum Upload'
           END as status_bukti
    FROM user_bills ub
    JOIN users u ON ub.user_id = u.id
    JOIN bills b ON ub.bill_id = b.id
    $where_clause
    ORDER BY 
        CASE ub.status 
            WHEN 'menunggu_konfirmasi' THEN 1
            WHEN 'menunggu_pembayaran' THEN 2 
            WHEN 'konfirmasi' THEN 3
            WHEN 'tolak' THEN 4
        END,
        ub.tanggal DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Hitung statistik
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'menunggu_pembayaran' THEN 1 ELSE 0 END) as menunggu_pembayaran,
        SUM(CASE WHEN status = 'menunggu_konfirmasi' THEN 1 ELSE 0 END) as menunggu_konfirmasi,
        SUM(CASE WHEN status = 'konfirmasi' THEN 1 ELSE 0 END) as konfirmasi,
        SUM(CASE WHEN status = 'tolak' THEN 1 ELSE 0 END) as tolak
    FROM user_bills
")->fetch();

// Function untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_num = date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

function formatTanggalWaktuIndonesia($tanggal) {
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April', '05' => 'Mei', '06' => 'Juni',
        '07' => 'Juli', '08' => 'Agustus', '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_num = date('m', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status Pembayaran - Admin</title>
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
    background: linear-gradient(135deg, #4f46e5 0%, #4B0082 100%);
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
    background: #eef2ff;
}

.nav-tab.active {
    color: #4f46e5;
    border-bottom-color: #FFC107;
    background: #eef2ff;
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
    color: #312e81;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-body {
    padding: 2rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: 16px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    border-left: 4px solid;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.stat-card.total { border-left-color: #4f46e5; }
.stat-card.menunggu { border-left-color: #f59e0b; }
.stat-card.konfirmasi-pending { border-left-color: #FFC107; }
.stat-card.selesai { border-left-color: #10b981; }
.stat-card.tolak { border-left-color: #ef4444; }

.stat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-card.total .stat-icon { background: #eef2ff; color: #4f46e5; }
.stat-card.menunggu .stat-icon { background: #fef3c7; color: #f59e0b; }
.stat-card.konfirmasi-pending .stat-icon { background: #fff8e1; color: #FFC107; }
.stat-card.selesai .stat-icon { background: #d1fae5; color: #10b981; }
.stat-card.tolak .stat-icon { background: #fee2e2; color: #ef4444; }

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #312e81;
    margin-bottom: 0.25rem;
}

.stat-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 500;
}

/* Filters */
.filters-form {
    display: flex;
    gap: 1.5rem;
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 200px;
}

.filter-group label {
    font-weight: 500;
    color: #312e81;
    font-size: 0.875rem;
}

.form-input {
    padding: 0.875rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s;
    background: white;
}

.form-input:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.875rem 1.5rem;
    background: linear-gradient(135deg, #4f46e5 0%, #4B0082 100%);
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
    box-shadow: 0 10px 25px rgba(75, 0, 130, 0.25);
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
    background: linear-gradient(135deg, #312e81 0%, #4B0082 100%);
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

.status-menunggu_pembayaran {
    background: #fef3c7;
    color: #92400e;
}

.status-menunggu_konfirmasi {
    background: #fed7aa;
    color: #9a3412;
}

.status-konfirmasi {
    background: #d1fae5;
    color: #065f46;
}

.status-tolak {
    background: #fee2e2;
    color: #991b1b;
}

.bukti-link {
    color: #4f46e5;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.bukti-link:hover {
    background: #eef2ff;
    text-decoration: none;
    color: #f59e0b;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
}

.empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
    color: #312e81;
}

.currency {
    font-weight: 600;
    color: #d97706;
}

.table-cell-user {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.username {
    font-weight: 600;
    color: #312e81;
}

.no-kk {
    font-size: 0.75rem;
    color: #64748b;
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
    
    .filters-form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group {
        min-width: auto;
    }
    
    .nav-tabs {
        overflow-x: auto;
        padding: 0 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
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
                    <i class="fas fa-chart-line"></i>
                    Status Pembayaran
                </h1>
                <p>Monitor pembayaran warga dan bukti transfer dengan mudah</p>
            </div>
            
            <!-- Navigation Tabs -->
            <div class="nav-tabs">
                <a href="tagihan.php" class="nav-tab">
                    <i class="fas fa-plus-circle"></i> Buat Tagihan
                </a>
                <a href="menunggu_pembayaran.php" class="nav-tab active">
                    <i class="fas fa-list-alt"></i> Status Pembayaran
                </a>
                <a href="konfirmasi.php" class="nav-tab">
                    <i class="fas fa-check-circle"></i> Konfirmasi Pembayaran
                </a>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Tagihan</div>
                </div>
                <div class="stat-card menunggu">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= $stats['menunggu_pembayaran'] ?></div>
                    <div class="stat-label">Menunggu Pembayaran</div>
                </div>
                <div class="stat-card konfirmasi-pending">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= $stats['menunggu_konfirmasi'] ?></div>
                    <div class="stat-label">Menunggu Konfirmasi</div>
                </div>
                <div class="stat-card selesai">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= $stats['konfirmasi'] ?></div>
                    <div class="stat-label">Terkonfirmasi</div>
                </div>
                <div class="stat-card tolak">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                    <div class="stat-number"><?= $stats['tolak'] ?></div>
                    <div class="stat-label">Ditolak</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-filter"></i>
                        Filter Data
                    </h2>
                </div>
                <div class="card-body">
                    <form method="GET" class="filters-form">
                        <div class="filter-group">
                            <label><i class="fas fa-list"></i> Status:</label>
                            <select name="status" class="form-input">
                                <option value="semua" <?= $status_filter === 'semua' ? 'selected' : '' ?>>Semua Status</option>
                                <option value="menunggu_pembayaran" <?= $status_filter === 'menunggu_pembayaran' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                                <option value="menunggu_konfirmasi" <?= $status_filter === 'menunggu_konfirmasi' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                                <option value="konfirmasi" <?= $status_filter === 'konfirmasi' ? 'selected' : '' ?>>Terkonfirmasi</option>
                                <option value="tolak" <?= $status_filter === 'tolak' ? 'selected' : '' ?>>Ditolak</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label><i class="fas fa-search"></i> Cari:</label>
                            <input type="text" name="search" value="<?= sanitize($search) ?>" 
                                   placeholder="Nama atau deskripsi..." class="form-input">
                        </div>
                        
                        <div class="filter-group">
                            <button type="submit" class="btn" style="color: white;">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Data Table -->
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-table"></i>
                        Data Pembayaran
                    </h2>
                </div>
                
                <?php if (empty($payments)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>Tidak ada data pembayaran</h3>
                        <p>Belum ada tagihan yang dibuat atau tidak ada yang sesuai filter</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Pengguna</th>
                                    <th><i class="fas fa-file-alt"></i> Deskripsi</th>
                                    <th><i class="fas fa-money-bill"></i> Jumlah</th>
                                    <th><i class="fas fa-calendar"></i> Jatuh Tempo</th>
                                    <th><i class="fas fa-info-circle"></i> Status</th>
                                    <th><i class="fas fa-file-image"></i> Bukti Transfer</th>
                                    <th><i class="fas fa-clock"></i> Tanggal Upload</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <div class="table-cell-user">
                                            <span class="username"><?= sanitize($payment['username']) ?></span>
                                            <span class="no-kk"><?= sanitize($payment['no_kk'] ?? 'No. KK tidak tersedia') ?></span>
                                        </div>
                                    </td>
                                    <td><?= sanitize($payment['deskripsi']) ?></td>
                                    <td>
                                        <span class="currency">Rp <?= number_format($payment['jumlah'], 0, ',', '.') ?></span>
                                    </td>
                                    <td><?= formatTanggalIndonesia($payment['jatuh_tempo']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $payment['status'] ?>">
                                            <?php
                                            switch($payment['status']) {
                                                case 'menunggu_pembayaran':
                                                    echo '<i class="fas fa-clock"></i> Menunggu Pembayaran';
                                                    break;
                                                case 'menunggu_konfirmasi':
                                                    echo '<i class="fas fa-hourglass-half"></i> Menunggu Konfirmasi';
                                                    break;
                                                case 'konfirmasi':
                                                    echo '<i class="fas fa-check"></i> Terkonfirmasi';
                                                    break;
                                                case 'tolak':
                                                    echo '<i class="fas fa-times"></i> Ditolak';
                                                    break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($payment['bukti_pembayaran']): ?>
                                            <a href="../uploads/bukti/<?= sanitize($payment['bukti_pembayaran']) ?>" 
                                               target="_blank" class="bukti-link">
                                                <i class="fas fa-file-image"></i> Lihat Bukti
                                            </a>
                                        <?php else: ?>
                                            <span style="color: #64748b; font-style: italic;">
                                                <i class="fas fa-minus-circle"></i> Belum upload
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $payment['tanggal'] ? formatTanggalWaktuIndonesia($payment['tanggal']) : '<span style="color: #64748b;">-</span>' ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>