<?php
error_reporting(0);
include('../config.php');
requireLogin();
requireAdmin();

function format_rupiah_export($angka){
    return 'Rp ' . number_format((int)$angka, 0, ',', '.');
}

function format_tanggal_indo_export($tanggal){
    $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $pecah = explode('-', $tanggal);
    return ltrim($pecah[2], '0') . ' ' . $bulan[(int)$pecah[1] - 1] . ' ' . $pecah[0];
}

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
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

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
$total_pengeluaran = array_sum(array_column($result, 'jumlah'));

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pengeluaran</title>
    <style>
    @media print {
        body { margin: 0; }
        .no-print { display: none !important; }
        @page { size: A4; margin: 1cm; }
    }

    body {
        font-family: Arial, sans-serif;
        font-size: 12px;
        line-height: 1.5;
        margin: 20px;
        color: #333;
    }

    .header {
        text-align: center;
        margin-bottom: 30px;
        border-bottom: 2px solid #5e3d9c;
        padding-bottom: 20px;
    }

    .header h1 {
        margin: 0;
        font-size: 24px;
        color: #5e3d9c;
    }

    .header p {
        margin: 5px 0;
        color: #777;
    }

    .summary {
        background-color: #f5f0fa;
        border: 1px solid #cbbce4;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .summary h3 {
        margin-top: 0;
        color: #432b7c;
    }

    .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        border-bottom: 1px dotted #bbb;
    }

    .summary-item:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 14px;
    }

    .filters {
        background-color: #eee5f9;
        border: 1px solid #cbbce4;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .filters h4 {
        margin: 0 0 10px;
        color: #432b7c;
    }

    .filters ul {
        padding-left: 20px;
        margin: 0;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 11.5px;
        margin-bottom: 20px;
    }

    th {
        background-color: #5e3d9c;
        color: white;
        padding: 10px 5px;
        text-align: center;
    }

    td {
        padding: 8px 5px;
        border: 1px solid #ddd;
        text-align: center;
    }

    tr:nth-child(even) {
        background-color: #f9f8fd;
    }

    tr:hover {
        background-color: #f0eaff;
    }

    .text-left { text-align: left; }
    .text-right { text-align: right; }

    .total-row {
        background-color: #e2d9f3;
        font-weight: bold;
        font-size: 13px;
    }

    .status-bukti {
        font-size: 10px;
        padding: 4px 7px;
        border-radius: 12px;
        display: inline-block;
        font-weight: bold;
    }

    .status-ada {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .status-tidak {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .print-button {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
    }

    .btn {
        padding: 10px 18px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-weight: bold;
        text-decoration: none;
    }

    .btn-primary {
        background-color: #5e3d9c;
        color: white;
    }

    .btn-secondary {
        background-color: #6c757d;
        color: white;
        margin-left: 10px;
    }

    .footer {
        text-align: center;
        margin-top: 40px;
        font-size: 10px;
        color: #666;
        border-top: 1px solid #ccc;
        padding-top: 15px;
    }
    </style>
</head>
<body>
    <div class="print-button no-print">
        <button class="btn btn-primary" onclick="window.print()">
            🖨 Cetak/Simpan PDF
        </button>
        <a href="javascript:history.back()" class="btn btn-secondary">
            ← Kembali
        </a>
    </div>

    <div class="header">
        <h1>LAPORAN PENGELUARAN</h1>
        <p>Tanggal Export: <?= date('d F Y H:i:s'); ?></p>
        <p>Sistem Manajemen Keuangan RT/RW</p>
    </div>

    <div class="summary">
        <h3>RINGKASAN LAPORAN</h3>
        <div class="summary-grid">
            <div class="summary-item"><span>Total Data:</span><span><?= count($result); ?> record</span></div>
            <div class="summary-item"><span>Periode Export:</span><span><?= date('F Y'); ?></span></div>
            <div class="summary-item"><span>Total Pengeluaran:</span><span><?= format_rupiah_export($total_pengeluaran); ?></span></div>
        </div>
    </div>

    <?php if ($filter_bulan || $filter_tahun): ?>
    <div class="filters">
        <h4>Filter Yang Diterapkan:</h4>
        <ul>
            <?php if ($filter_bulan && $filter_tahun): ?>
                <?php $bulan_nama = DateTime::createFromFormat('!m', $filter_bulan)->format('F'); ?>
                <li>Periode: <strong><?= $bulan_nama . ' ' . $filter_tahun; ?></strong></li>
            <?php elseif ($filter_tahun): ?>
                <li>Tahun: <strong><?= $filter_tahun; ?></strong></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <h3>DETAIL DATA PENGELUARAN</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th <?= $has_bukti_column ? 'colspan="1"' : 'colspan="2"' ?>>Deskripsi</th>
                <th>Tanggal</th>
                <th>Jumlah</th>
                <?php if ($has_bukti_column): ?><th>Status Bukti</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($result)): ?>
            <tr>
                <td colspan="<?= $has_bukti_column ? 5 : 4 ?>" style="text-align: center; color: #999; padding: 20px;">
                    Tidak ada data ditemukan
                </td>
            </tr>
        <?php else: ?>
            <?php $no = 1; foreach ($result as $row): ?>
            <tr>
                <td><?= $no++; ?></td>
                <td class="text-left"><?= htmlspecialchars($row['deskripsi']); ?></td>
                <td><?= format_tanggal_indo_export($row['tanggal']); ?></td>
                <td class="text-right"><?= format_rupiah_export($row['jumlah']); ?></td>
                <?php if ($has_bukti_column): ?>
                    <td>
                        <span class="status-bukti <?= !empty($row['bukti_file']) ? 'status-ada' : 'status-tidak'; ?>">
                            <?= !empty($row['bukti_file']) ? 'Ada' : 'Tidak Ada'; ?>
                        </span>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="<?= $has_bukti_column ? '4' : '3'; ?>" class="text-right">TOTAL KESELURUHAN:</td>
                <td class="text-right"><?= format_rupiah_export($total_pengeluaran); ?></td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
