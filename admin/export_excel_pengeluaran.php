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

// Buat nama file dengan timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "Data_Pengeluaran_" . $timestamp . ".xls";

// Set headers untuk download Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Mulai output HTML untuk Excel
echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Pengeluaran</title>
    <style>
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .header { text-align: center; margin-bottom: 20px; }
        .total-row { font-weight: bold; background-color: #e6f3ff; }
    </style>
</head>
<body>';

// Header laporan
echo '<div class="header">
    <h2>LAPORAN DATA PENGELUARAN</h2>
    <p>Tanggal Export: ' . date('d F Y, H:i:s') . '</p>';

if ($filter_bulan && $filter_tahun) {
    $bulan_nama = DateTime::createFromFormat('!m', $filter_bulan)->format('F');
    echo '<p>Filter: ' . $bulan_nama . ' ' . $filter_tahun . '</p>';
} elseif ($filter_tahun) {
    echo '<p>Filter: Tahun ' . $filter_tahun . '</p>';
} else {
    echo '<p>Filter: Semua Data</p>';
}

echo '</div>';

// Tabel data
echo '<table>
    <thead>
        <tr>
            <th width="5%">No</th>
            <th width="40%">Deskripsi</th>
            <th width="15%">Tanggal</th>
            <th width="20%">Jumlah</th>';

if ($has_bukti_column) {
    echo '<th width="20%">Status Bukti</th>';
}

echo '        </tr>
    </thead>
    <tbody>';

if (empty($result)) {
    $colspan = $has_bukti_column ? 5 : 4;
    echo '<tr>
        <td colspan="' . $colspan . '" class="text-center">Tidak ada data ditemukan</td>
    </tr>';
} else {
    $no = 1;
    foreach ($result as $row) {
        echo '<tr>
            <td class="text-center">' . $no++ . '</td>
            <td>' . htmlspecialchars($row['deskripsi']) . '</td>
            <td class="text-center">' . format_tanggal_indo($row['tanggal']) . '</td>
            <td class="text-right">' . format_rupiah($row['jumlah']) . '</td>';
        
        if ($has_bukti_column) {
            $status_bukti = !empty($row['bukti_file']) ? 'Ada Bukti' : 'Tanpa Bukti';
            echo '<td class="text-center">' . $status_bukti . '</td>';
        }
        
        echo '</tr>';
    }
    
    // Baris total
    echo '<tr class="total-row">
        <td colspan="3" class="text-right"><strong>TOTAL PENGELUARAN:</strong></td>
        <td class="text-right"><strong>' . format_rupiah($total_pengeluaran) . '</strong></td>';
    
    if ($has_bukti_column) {
        echo '<td></td>';
    }
    
    echo '</tr>';
}

echo '    </tbody>
</table>';

// Footer
echo '<br><br>
<div>
    <p><em>Laporan ini digenerate secara otomatis oleh sistem pada ' . date('d F Y, H:i:s') . '</em></p>
    <p><em>Total data: ' . count($result) . ' record</em></p>
</div>';

echo '</body>
</html>';
?>