<?php
error_reporting(0);
include('../config.php');

// Wajib login sebagai admin
requireLogin();
requireAdmin();

// Fungsi format rupiah
function format_rupiah_export($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// Fungsi format tanggal Indonesia
function format_tanggal_indo_export($tanggal) {
    $bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecah = explode('-', $tanggal);
    return ltrim($pecah[2], '0') . ' ' . $bulan[(int)$pecah[1] - 1] . ' ' . $pecah[0];
}

// Ambil parameter filter
$filter_bulan = $_GET['bulan'] ?? '';
$filter_tahun = $_GET['tahun'] ?? '';

$params = [];
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

// Filter bulan dan tahun jika ada
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

// Hitung total pemasukan
$total_pemasukan = array_sum(array_column($result, 'jumlah'));

// Siapkan nama file
$filename = 'Laporan_Pemasukan';
if ($filter_bulan && $filter_tahun) {
    $bulan_nama = DateTime::createFromFormat('!m', $filter_bulan)->format('F');
    $filename .= '_' . $bulan_nama . '_' . $filter_tahun;
} elseif ($filter_tahun) {
    $filename .= '_' . $filter_tahun;
}
$filename .= '_' . date('Y-m-d_H-i-s') . '.xls';

// Header untuk file Excel
header('Content-Type: application/vnd.ms-excel');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Pemasukan</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
        }
        th, td {
            border: 1px solid #333;
            padding: 6px 8px;
        }
        th {
            background-color: #4472C4;
            color: white;
        }
        h2, h3, p {
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>

<h2>LAPORAN PEMASUKAN</h2>
<p><strong>Tanggal Export:</strong> <?= date('d F Y H:i:s') ?></p>
<p><strong>Total Data:</strong> <?= count($result) ?></p>
<p><strong>Total Pemasukan:</strong> <?= format_rupiah_export($total_pemasukan) ?></p>

<?php if ($filter_bulan || $filter_tahun): ?>
    <h3>Filter Yang Diterapkan:</h3>
    <ul>
        <?php if ($filter_bulan && $filter_tahun): ?>
            <li>Periode: <?= DateTime::createFromFormat('!m', $filter_bulan)->format('F') . ' ' . $filter_tahun ?></li>
        <?php elseif ($filter_tahun): ?>
            <li>Tahun: <?= $filter_tahun ?></li>
        <?php endif; ?>
    </ul>
<?php endif; ?>

<br>

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
            <tr><td colspan="5" style="text-align:center;">Tidak ada data ditemukan</td></tr>
        <?php else: ?>
            <?php $no = 1; ?>
            <?php foreach ($result as $row): ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><?= htmlspecialchars($row['username']) ?></td>
                    <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                    <td><?= format_tanggal_indo_export($row['tanggal']) ?></td>
                    <td><?= format_rupiah_export($row['jumlah']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<br><br>
<p><strong>Total Keseluruhan: <?= format_rupiah_export($total_pemasukan) ?></strong></p>

</body>
</html>
