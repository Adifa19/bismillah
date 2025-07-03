<?php 
error_reporting(0);
include('../config.php');

// Check if user is logged in (warga tidak perlu admin)
requireLogin();

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

// Build query untuk mendapatkan data pemasukan dari user_bills dan bills
$where_conditions = [];
$params = [];

$sql = "
    SELECT 
        bills.deskripsi, 
        SUM(bills.jumlah) as total_jumlah,
        COUNT(*) as jumlah_transaksi,
        MIN(bills.tanggal) as tanggal_pertama,
        MAX(bills.tanggal) as tanggal_terakhir
    FROM 
        user_bills 
    JOIN 
        bills ON user_bills.bill_id = bills.id 
    WHERE 
        user_bills.status = 'konfirmasi'
";

if ($filter_bulan && $filter_tahun) {
    $sql .= " AND MONTH(bills.tanggal) = ? AND YEAR(bills.tanggal) = ?";
    $params[] = $filter_bulan;
    $params[] = $filter_tahun;
} elseif ($filter_tahun) {
    $sql .= " AND YEAR(bills.tanggal) = ?";
    $params[] = $filter_tahun;
}

$sql .= "
    GROUP BY bills.deskripsi
    ORDER BY total_jumlah DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll();

// Calculate total keseluruhan
$total_pemasukan = array_sum(array_column($result, 'total_jumlah'));

// Get available years for filter - only from bills table
$year_sql = "
    SELECT DISTINCT YEAR(bills.tanggal) as tahun 
    FROM user_bills 
    JOIN bills ON user_bills.bill_id = bills.id 
    WHERE user_bills.status = 'konfirmasi'
    ORDER BY tahun DESC
";
$year_stmt = $pdo->query($year_sql);
$available_years = $year_stmt->fetchAll();

// Array nama bulan untuk dropdown
$nama_bulan = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

// Get period label for display
$period_label = '';
if ($filter_bulan && $filter_tahun) {
    $period_label = $nama_bulan[$filter_bulan] . ' ' . $filter_tahun;
} elseif ($filter_tahun) {
    $period_label = 'Tahun ' . $filter_tahun;
} else {
    $period_label = 'Semua Periode';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Pemasukan - Dashboard Warga</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style> 
    :root {
        --primary-color: #10b981;
        --primary-dark: #059669;
        --secondary-color: #34d399;
        --accent-color: #06d6a0;
        --success-color: #22c55e;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --dark-color: #1f2937;
        --light-color: #f0fdf4;
        --border-color: #d1fae5;
        --text-primary: #1e293b;
        --text-secondary: #64748b;
        --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
        --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
        --gradient-primary: linear-gradient(135deg, #10b981 0%, #34d399 100%);
        --gradient-accent: linear-gradient(135deg, #06d6a0 0%, #0891b2 100%);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        background: white;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        color: var(--text-primary);
        line-height: 1.6;
        min-height: 100vh;
    }

    .main-content {
        margin-left: 0;
        padding: 2rem;
        min-height: 100vh;
    }

    .card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 1rem;
        box-shadow: var(--shadow-lg);
        margin-bottom: 1.5rem;
        transition: all 0.3s ease;
    }

    .card:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
    }

    .card-header {
        background: var(--gradient-primary);
        color: white;
        border-radius: 1rem 1rem 0 0 !important;
        padding: 1.5rem;
        border: none;
    }

    .card-header h3 {
        font-weight: 600;
        font-size: 1.5rem;
        margin-bottom: 0.25rem;
    }

    .card-header p {
        opacity: 0.9;
        font-size: 0.95rem;
        font-weight: 400;
    }

    .btn-primary {
        background: var(--gradient-primary);
        border: none;
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        background: linear-gradient(135deg, #059669 0%, #22c55e 100%);
    }

    .btn-outline-secondary {
        border: 2px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 0.75rem 1.5rem;
        font-weight: 500;
        font-size: 0.9rem;
        color: var(--text-secondary);
        transition: all 0.3s ease;
    }

    .btn-outline-secondary:hover {
        background: var(--light-color);
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-1px);
    }

    .form-control, .form-select {
        border-radius: 0.75rem;
        border: 2px solid var(--border-color);
        padding: 0.875rem 1rem;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.1);
        background: white;
    }

    .form-label {
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .table {
        border-radius: 0.75rem;
        overflow: hidden;
        font-size: 0.9rem;
    }

    .table thead th {
        background: var(--gradient-primary);
        color: white;
        border: none;
        font-weight: 600;
        padding: 1rem;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-color: var(--border-color);
        background: rgba(255, 255, 255, 0.8);
    }

    .table tbody tr {
        transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(16, 185, 129, 0.05) !important;
    }

    .stats-card {
        background: var(--gradient-primary);
        color: white;
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-lg);
        transition: all 0.3s ease;
        border: none;
    }

    .stats-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.25);
    }

    .stats-card h4 {
        font-size: 0.95rem;
        font-weight: 500;
        opacity: 0.9;
        margin-bottom: 0.5rem;
    }

    .stats-card h2 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0;
    }

    .stats-card .fs-1 {
        font-size: 2.5rem !important;
        opacity: 0.7;
    }

    .filter-section {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 1rem;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow-md);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .filter-section h5 {
        color: var(--text-primary);
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .table-responsive {
        border-radius: 0.75rem;
        box-shadow: var(--shadow-sm);
    }

    .total-row {
        background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%) !important;
        font-weight: 600;
    }

    .total-row td {
        border-top: 2px solid var(--primary-color) !important;
        padding: 1.25rem 1rem;
    }

    .amount-cell {
        font-family: 'Inter', monospace;
        font-weight: 600;
        font-size: 0.95rem;
    }

    .text-amount {
        color: var(--primary-color) !important;
    }

    .empty-state {
        padding: 3rem 1rem;
        text-align: center;
    }

    .empty-state i {
        color: var(--text-secondary);
        margin-bottom: 1rem;
    }

    .empty-state p {
        color: var(--text-secondary);
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .empty-state small {
        color: var(--text-secondary);
        opacity: 0.8;
    }

    .card-footer {
        background: rgba(240, 253, 244, 0.8);
        border-top: 1px solid var(--border-color);
        border-radius: 0 0 1rem 1rem;
        padding: 1rem 1.5rem;
    }

    .card-footer small {
        color: var(--text-secondary);
        font-size: 0.8rem;
    }

    .badge {
        font-weight: 500;
        padding: 0.5rem 0.75rem;
        border-radius: 0.5rem;
    }

    .badge.bg-info {
        background: var(--accent-color) !important;
    }

    .badge.bg-primary {
        background: var(--primary-color) !important;
    }

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    ::-webkit-scrollbar-track {
        background: var(--light-color);
    }

    ::-webkit-scrollbar-thumb {
        background: var(--border-color);
        border-radius: 4px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--primary-color);
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
        color: #64748b;
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
        color: #10b981;
        background: #f0fdf4;
        border-bottom-color: #10b981;
    }

    /* Tab aktif */
    .nav-tab.active {
        color: #10b981;
        border-bottom-color: #10b981;
        background: #f0fdf4;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }
        
        .stats-card h2 {
            font-size: 1.5rem;
        }
        
        .card-header {
            padding: 1rem;
        }
        
        .card-header h3 {
            font-size: 1.25rem;
        }
        
        .filter-section {
            padding: 1rem;
        }
        
        .table thead th,
        .table tbody td {
            padding: 0.75rem 0.5rem;
            font-size: 0.85rem;
        }
    }

    /* Loading animation */
    @keyframes pulse {
        0%, 100% {
            opacity: 1;
        }
        50% {
            opacity: 0.5;
        }
    }

    .loading {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    .page-header {
    text-align: center;
    margin-bottom: 30px;
    padding: 20px 0;
    background: linear-gradient(135deg, #34d399 0%, #059669 100%); /* hijau terang ke hijau gelap */
    color: white;
    border-radius: 15px;
    box-shadow: 0 4px 20px rgba(52, 211, 153, 0.3); /* efek bayangan hijau */
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

    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row">
                       <div class="page-header">
                            <h1>Pemasukan Keuangan</h1>
                            <p>Catat dan pantau semua pemasukan dengan mudah</p>
                        </div>
            </div>

            <div class="nav-tabs-container">
                <a href="income.php" class="nav-tab active">
                    <i class="fas fa-plus-circle"></i> Pemasukan
                </a>
                <a href="pengeluaran.php" class="nav-tab">
                    <i class="fas fa-minus-circle"></i> Pengeluaran
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">Total Pemasukan</h4>
                                <h2 class="mb-0"><?php echo format_rupiah($total_pemasukan); ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="fas fa-coins"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="background: var(--gradient-accent);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">Periode</h4>
                                <h2 class="mb-0"><?php echo $period_label; ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card" style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">Jenis Pemasukan</h4>
                                <h2 class="mb-0"><?php echo count($result); ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="fas fa-list-ul"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Filter Section -->
                <div class="col-12">
                    <div class="filter-section">
                        <h5 class="mb-3">
                            <i class="fas fa-filter me-2"></i>
                            Filter Data Pemasukan
                        </h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">
                                    <i class="fas fa-calendar me-1"></i>
                                    Bulan
                                </label>
                                <select name="bulan" class="form-select">
                                    <option value="">Semua Bulan</option>
                                    <?php foreach ($nama_bulan as $num => $nama): ?>
                                        <option value="<?= $num ?>" <?= $filter_bulan == $num ? 'selected' : '' ?>>
                                            <?= $nama ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">
                                    <i class="fas fa-calendar-alt me-1"></i>
                                    Tahun
                                </label>
                                <select name="tahun" class="form-select">
                                    <option value="">Semua Tahun</option>
                                    <?php foreach ($available_years as $year): ?>
                                        <option value="<?= $year['tahun'] ?>" <?= $filter_tahun == $year['tahun'] ? 'selected' : '' ?>>
                                            <?= $year['tahun'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-grid gap-2 w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-2"></i>
                                        Filter
                                    </button>
                                    <a href="?" class="btn btn-outline-secondary">
                                        <i class="fas fa-refresh me-2"></i>
                                        Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Rincian Pemasukan - <?php echo $period_label; ?>
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="5%">No</th>
                                            <th width="50%">Deskripsi/Kategori</th>
                                            <th width="15%" class="text-center">Transaksi</th>
                                            <th width="20%" class="text-end">Total</th>
                                            <th width="10%">Periode</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($result)): ?>
                                            <tr>
                                                <td colspan="5" class="empty-state">
                                                    <i class="fas fa-inbox fa-3x"></i>
                                                    <p class="mb-1">Tidak ada data pemasukan ditemukan</p>
                                                    <small>
                                                        <?php if ($filter_bulan || $filter_tahun): ?>
                                                            Tidak ada data untuk filter yang dipilih
                                                        <?php else: ?>
                                                            Belum ada data pemasukan yang tercatat
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $no = 1; foreach ($result as $row): ?>
                                                <tr>
                                                    <td class="text-center"><?php echo $no++; ?></td>
                                                    <td>
                                                        <i class="fas fa-hand-holding-usd me-2 text-success"></i>
                                                        <strong><?= sanitize($row['deskripsi']) ?></strong>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info"><?= $row['jumlah_transaksi'] ?> transaksi</span>
                                                    </td>
                                                    <td class="text-end amount-cell text-amount">
                                                        <?= format_rupiah($row['total_jumlah']) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['tanggal_pertama'] == $row['tanggal_terakhir']): ?>
                                                            <small><?= format_tanggal_indo($row['tanggal_pertama']) ?></small>
                                                        <?php else: ?>
                                                            <small>
                                                                <?= format_tanggal_indo($row['tanggal_pertama']) ?><br>
                                                                s/d <?= format_tanggal_indo($row['tanggal_terakhir']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <!-- Total Row -->
                                            <tr class="total-row">
                                                <td colspan="3" class="text-center">
                                                    <i class="fas fa-calculator me-2"></i>
                                                    TOTAL PEMASUKAN
                                                </td>
                                                <td class="text-end amount-cell text-amount fs-5">
                                                    <?= format_rupiah($total_pemasukan) ?>
                                                </td>
                                                <td></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add loading state when filtering
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Memfilter...';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
            
            // Re-enable after a delay (in case of slow loading)
            setTimeout(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                submitBtn.classList.remove('loading');
            }, 3000);
        });

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            // Fade in animation for cards
            const cards = document.querySelectorAll('.card, .stats-card, .filter-section');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Auto-refresh notification
        setInterval(function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.toString()) {
                console.log('Auto-refresh: Data may have been updated');
            }
        }, 300000); // 5 minutes

        // Enhanced form validation
        document.querySelector('form').addEventListener('change', function() {
            const bulan = this.querySelector('[name="bulan"]').value;
            const tahun = this.querySelector('[name="tahun"]').value;
            
            if (bulan && !tahun) {
                this.querySelector('[name="tahun"]').style.borderColor = 'var(--warning-color)';
            } else {
                this.querySelector('[name="tahun"]').style.borderColor = 'var(--border-color)';
            }
        });
    </script>

</body>
</html>