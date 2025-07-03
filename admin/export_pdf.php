<?php
error_reporting(0);
include('../config.php');

// Cek login dan admin
requireLogin();
requireAdmin();

// Format fungsi
function format_rupiah_export($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}
function format_tanggal_indo_export($tanggal) {
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

// Query utama: ambil data dari user_bills yang sudah konfirmasi
$sql = "
    SELECT 
        users.username, 
        bills.deskripsi, 
        bills.tanggal, 
        bills.jumlah
    FROM user_bills 
    JOIN users ON user_bills.user_id = users.id 
    JOIN bills ON user_bills.bill_id = bills.id 
    WHERE user_bills.status = 'konfirmasi'
";

$params = [];

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

$total_pemasukan = array_sum(array_column($result, 'jumlah'));
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pemasukan</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            @page { size: A4; margin: 1cm; }
        }
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 12px;
            margin: 20px;
            color: #212529;
            background: #fff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #007bff;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #007bff;
        }
        .header p {
            margin: 5px 0;
            color: #777;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
        }
        .btn {
            padding: 10px 16px;
            font-size: 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .summary, .filters {
            background: #fdfdfd;
            border-left: 5px solid #007bff;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .summary h3, .filters h4 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #007bff;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px dashed #ccc;
            padding: 4px 0;
        }
        .summary-item:last-child {
            font-weight: bold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        table th {
            background: #007bff;
            color: white;
            padding: 8px;
            text-align: center;
        }
        table td {
            padding: 7px;
            border: 1px solid #ddd;
            text-align: center;
        }
        table tr:nth-child(even) {
            background: #f8f9fa;
        }
        table tr:hover {
            background: #eef5ff;
            transition: 0.3s ease;
        }
        .total-row {
            background: #d4edda;
            font-weight: bold;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            font-size: 11px;
            color: #888;
            border-top: 1px solid #ccc;
            padding-top: 15px;
        }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button class="btn btn-primary" onclick="window.print()">Cetak/Simpan PDF</button>
        <a href="javascript:history.back()" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="header">
        <div>
            <h1>LAPORAN PEMASUKAN</h1>
            <p>Tanggal Export: <?= date('d F Y H:i:s') ?></p>
            <p>Sistem Manajemen Keuangan RT/RW</p>
        </div>
    </div>

    <div class="summary">
        <h3>RINGKASAN</h3>
        <div class="summary-grid">
            <div class="summary-item"><span>Total Data</span><span><?= count($result) ?> record</span></div>
            <div class="summary-item"><span>Periode Export</span><span><?= date('F Y') ?></span></div>
            <div class="summary-item"><span>Total Pemasukan</span><span><?= format_rupiah_export($total_pemasukan) ?></span></div>
        </div>
    </div>

    <?php if ($filter_bulan || $filter_tahun): ?>
    <div class="filters">
        <h4>Filter Yang Diterapkan:</h4>
        <ul style="margin:0 0 0 20px; padding:0;">
            <?php if ($filter_bulan && $filter_tahun): ?>
                <?php $bulan_nama = DateTime::createFromFormat('!m', $filter_bulan)->format('F'); ?>
                <li>Periode: <strong><?= $bulan_nama . ' ' . $filter_tahun ?></strong></li>
            <?php elseif ($filter_tahun): ?>
                <li>Tahun: <strong><?= $filter_tahun ?></strong></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <h3 style="border-bottom:2px solid #007bff; padding-bottom:5px;">DETAIL DATA PEMASUKAN</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>Username</th>
                <th>Deskripsi</th>
                <th>Tanggal</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($result)): ?>
                <tr><td colspan="5">Tidak ada data ditemukan</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($result as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['username']) ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($row['deskripsi']) ?></td>
                        <td><?= format_tanggal_indo_export($row['tanggal']) ?></td>
                        <td style="text-align:right;"><?= format_rupiah_export($row['jumlah']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="4" style="text-align:right;">TOTAL KESELURUHAN</td>
                    <td style="text-align:right;"><?= format_rupiah_export($total_pemasukan) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
