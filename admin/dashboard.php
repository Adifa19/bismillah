<?php
require_once '../config.php';
requireLogin();
requireAdmin();

// Fungsi untuk mendapatkan statistik
function getStats($pdo) {
    $stats = [];
    
    // Total Users
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    $stats['total_users'] = $stmt->fetch()['total'];
    
    // Total Pendataan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pendataan WHERE status_warga = 'Aktif'");
    $stats['total_pendataan'] = $stmt->fetch()['total'];
    
    // Total Anggota Keluarga
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM anggota_keluarga");
    $stats['total_anggota'] = $stmt->fetch()['total'];
    
    // Total Kegiatan
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kegiatan");
    $stats['total_kegiatan'] = $stmt->fetch()['total'];
    
    // Total Bills (Menunggu Konfirmasi) - menggunakan user_bills
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM user_bills WHERE status = 'menunggu_konfirmasi'");
    $stats['pending_bills'] = $stmt->fetch()['total'];
    
    // Pemasukan Bulan Ini - hanya dari bills yang sudah terkonfirmasi
    $income_sql = "
        SELECT COALESCE(SUM(b.jumlah), 0) as total 
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id 
        JOIN users u ON ub.user_id = u.id
        WHERE ub.status = 'konfirmasi'
            AND MONTH(b.tanggal) = MONTH(CURRENT_DATE()) 
            AND YEAR(b.tanggal) = YEAR(CURRENT_DATE())
    ";
    $stmt = $pdo->query($income_sql);
    $stats['income_this_month'] = $stmt->fetch()['total'];
    
    // Pengeluaran Bulan Ini
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM keluaran WHERE MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())");
    $stats['expense_this_month'] = $stmt->fetch()['total'];
    
    // Total Pemasukan Keseluruhan - hanya dari bills yang sudah terkonfirmasi
    $total_income_sql = "
        SELECT COALESCE(SUM(b.jumlah), 0) as total 
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id 
        JOIN users u ON ub.user_id = u.id
        WHERE ub.status = 'konfirmasi'
    ";
    $stmt = $pdo->query($total_income_sql);
    $stats['total_income'] = $stmt->fetch()['total'];
    
    // Total Pengeluaran Keseluruhan
    $stmt = $pdo->query("SELECT COALESCE(SUM(jumlah), 0) as total FROM keluaran");
    $stats['total_expense'] = $stmt->fetch()['total'];
    
    // Hapus pending_income karena tidak menggunakan tabel income lagi
    $stats['pending_income'] = 0;
    
    return $stats;
}

// Fungsi untuk mendapatkan aktivitas terbaru
function getRecentActivities($pdo) {
    $activities = [];
    
    // Pendataan terbaru
    $stmt = $pdo->query("SELECT 'Pendataan Baru' as type, nama_lengkap as description, created_at FROM pendataan ORDER BY created_at DESC LIMIT 5");
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Kegiatan terbaru
    $stmt = $pdo->query("SELECT 'Kegiatan' as type, judul as description, tanggal_kegiatan as created_at FROM kegiatan ORDER BY id DESC LIMIT 5");
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Tagihan terkonfirmasi terbaru
    $stmt = $pdo->query("
        SELECT 'Pembayaran Tagihan' as type, 
               CONCAT(u.username, ' - ', b.deskripsi) as description, 
               b.tanggal as created_at 
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id
        JOIN users u ON ub.user_id = u.id
        WHERE ub.status = 'konfirmasi'
        ORDER BY b.tanggal DESC 
        LIMIT 5
    ");
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Urutkan berdasarkan tanggal
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, 10);
}

// Fungsi untuk mendapatkan grafik data
function getChartData($pdo) {
    // Data income dari bills yang sudah terkonfirmasi per bulan (6 bulan terakhir)
    $income_sql = "
        SELECT 
            DATE_FORMAT(b.tanggal, '%Y-%m') as month,
            COALESCE(SUM(b.jumlah), 0) as total_income
        FROM user_bills ub
        JOIN bills b ON ub.bill_id = b.id
        JOIN users u ON ub.user_id = u.id
        WHERE ub.status = 'konfirmasi'
            AND b.tanggal >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(b.tanggal, '%Y-%m')
        ORDER BY month
    ";
    $stmt = $pdo->query($income_sql);
    $income_data = $stmt->fetchAll();
    
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(tanggal, '%Y-%m') as month,
            COALESCE(SUM(jumlah), 0) as total_expense
        FROM keluaran 
        WHERE tanggal >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
        ORDER BY month
    ");
    $expense_data = $stmt->fetchAll();
    
    return ['income' => $income_data, 'expense' => $expense_data];
}

$stats = getStats($pdo);
$activities = getRecentActivities($pdo);
$chartData = getChartData($pdo);

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

// Total warga dengan data lengkap dan aktif
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM pendataan 
    WHERE status_warga = 'Aktif'
      AND nik IS NOT NULL AND nik != ''
      AND nama_lengkap IS NOT NULL AND nama_lengkap != ''
      AND tanggal_lahir IS NOT NULL
      AND jenis_kelamin IS NOT NULL AND jenis_kelamin != ''
      AND pekerjaan IS NOT NULL AND pekerjaan != ''
      AND alamat IS NOT NULL AND alamat != ''
");
$stmt->execute();
$active_complete = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.css" rel="stylesheet">
    <style>
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #333;
        }
        .stat-card-danger {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
        }
        .activity-item {
            border-left: 3px solid #667eea;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .top-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .finance-card-income {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
        }
        .finance-card-expense {
            background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            color: white;
        }
        .balance-card {
            background: linear-gradient(135deg, #fdbb2d 0%, #22c1c3 100%);
            color: white;
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
    </style>
</head>
<body class="bg-light">
    <?php include('sidebar.php'); ?>
    <div class="container-fluid">
         <div class="page-header">
                <h1>
                    <i class="bi bi-speedometer2"></i>
                    Dashboard Admin
                </h1>
                <p>Sistem Manajemen RT 007</p>
            </div>
        <div class="container-fluid px-4">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Pengguna</h6>
                                    <h3><?= count($all_users) ?></h3>
                                </div>
                                <i class="bi bi-people fs-1 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Data Pendataan</h6>
                                    <h4><?php echo $active_complete; ?></h4>
                                </div>
                                <i class="bi bi-clipboard-data fs-1 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Pending Konfirmasi</h6>
                                    <h3><?= number_format($stats['pending_bills']) ?></h3>
                                </div>
                                <i class="bi bi-clock fs-1 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="card stat-card-danger">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h6 class="card-title">Total Kegiatan</h6>
                                    <h3><?= number_format($stats['total_kegiatan']) ?></h3>
                                </div>
                                <i class="bi bi-calendar-event fs-1 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Financial Stats -->
            <div class="row mb-4">
                <div class="col-md-4 mb-3">
                    <div class="card finance-card-income">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-arrow-up-circle"></i> Total Pemasukan</h5>
                            <h3>Rp <?= number_format($stats['total_income'], 0, ',', '.') ?></h3>
                            <small class="opacity-75">Bulan ini: Rp <?= number_format($stats['income_this_month'], 0, ',', '.') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card finance-card-expense">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-arrow-down-circle"></i> Total Pengeluaran</h5>
                            <h3>Rp <?= number_format($stats['total_expense'], 0, ',', '.') ?></h3>
                            <small class="opacity-75">Bulan ini: Rp <?= number_format($stats['expense_this_month'], 0, ',', '.') ?></small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card balance-card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-calculator"></i> Saldo</h5>
                            <h3>Rp <?= number_format($stats['total_income'] - $stats['total_expense'], 0, ',', '.') ?></h3>
                            <small class="opacity-75">
                                <?php 
                                $balance = $stats['total_income'] - $stats['total_expense'];
                                echo $balance >= 0 ? 'Surplus' : 'Defisit';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Chart -->
                <div class="col-md-8 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Grafik Keuangan (6 Bulan Terakhir)</h5>
                            <canvas id="financeChart" width="400" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Aktivitas Terbaru</h5>
                            <div class="activity-list" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach($activities as $activity): ?>
                                <div class="activity-item">
                                    <strong><?= sanitize($activity['type']) ?></strong><br>
                                    <small><?= sanitize($activity['description']) ?></small><br>
                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Aksi Cepat</h5>
                            <div class="row">
                                <div class="col-md-2 mb-2">
                                    <a href="akun_warga.php" class="btn btn-primary w-100">
                                        <i class="bi bi-person-plus"></i> Tambah Pengguna
                                    </a>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <a href="kegiatan.php" class="btn btn-success w-100">
                                        <i class="bi bi-calendar-plus"></i> Tambah Kegiatan
                                    </a>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <a href="tagihan.php" class="btn btn-warning w-100">
                                        <i class="bi bi-receipt"></i> Buat Tagihan
                                    </a>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <a href="pengeluaran.php" class="btn btn-danger w-100">
                                        <i class="bi bi-plus-circle"></i> Kelola Pengeluaran
                                    </a>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <a href="income.php" class="btn btn-info w-100">
                                        <i class="bi bi-cash-stack"></i> Kelola Pemasukan
                                    </a>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <a href="livechat.php" class="btn btn-secondary w-100">
                                        <i class="bi bi-chat"></i> Livechat
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <script>
        // Finance Chart
        const ctx = document.getElementById('financeChart').getContext('2d');
        
        // Prepare data for chart
        const months = [];
        const incomeData = [];
        const expenseData = [];
        
        // Get last 6 months
        for(let i = 5; i >= 0; i--) {
            const date = new Date();
            date.setMonth(date.getMonth() - i);
            const monthStr = date.toISOString().slice(0, 7);
            months.push(date.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' }));
            
            // Find income for this month
            const income = <?= json_encode($chartData['income']) ?>.find(item => item.month === monthStr);
            incomeData.push(income ? parseInt(income.total_income) : 0);
            
            // Find expense for this month
            const expense = <?= json_encode($chartData['expense']) ?>.find(item => item.month === monthStr);
            expenseData.push(expense ? parseInt(expense.total_expense) : 0);
        }
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [{
                    label: 'Pemasukan',
                    data: incomeData,
                    backgroundColor: 'rgba(17, 153, 142, 0.8)',
                    borderColor: 'rgba(17, 153, 142, 1)',
                    borderWidth: 1
                }, {
                    label: 'Pengeluaran',
                    data: expenseData,
                    backgroundColor: 'rgba(252, 70, 107, 0.8)',
                    borderColor: 'rgba(252, 70, 107, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Rp ' + value.toLocaleString('id-ID');
                            }
                        }
                    }
                }
            }
        });

        // Auto refresh setiap 5 menit
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>