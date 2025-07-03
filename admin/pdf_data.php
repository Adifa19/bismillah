<?php
error_reporting(0);
include('../config.php');

// Cek login dan admin
requireLogin();
requireAdmin();

// Format fungsi
function formatTanggalIndonesia($tanggal) {
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }
    
    $bulan = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $pecah = explode('-', $tanggal);
    return ltrim($pecah[2], '0') . ' ' . $bulan[(int)$pecah[1] - 1] . ' ' . $pecah[0];
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Query utama: ambil data pendataan
$sql = "
    SELECT 
        p.no_kk,
        p.nik,
        p.nama_lengkap,
        p.tanggal_lahir,
        p.jenis_kelamin,
        p.pekerjaan,
        p.alamat,
        p.no_telp,
        p.jumlah_anggota_keluarga,
        p.status_warga,
        p.is_registered,
        p.created_at
    FROM pendataan p
    WHERE p.nama_lengkap IS NOT NULL 
    AND p.nama_lengkap != ''
";

$params = [];

if ($search) {
    $sql .= " AND (p.nik LIKE ? OR p.nama_lengkap LIKE ? OR p.alamat LIKE ? OR p.no_kk LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    if ($status_filter === 'Aktif') {
        $sql .= " AND p.status_warga = 'Aktif'";
    } elseif ($status_filter === 'Tidak Aktif') {
        $sql .= " AND p.status_warga = 'Tidak Aktif'";
    } elseif ($status_filter === 'Belum Lengkap') {
        $sql .= " AND (p.nik IS NULL OR p.nik = '' OR p.nama_lengkap IS NULL OR p.nama_lengkap = '')";
    }
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll();

// Ambil statistik
$stmt_stats = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE is_registered = 1");
$stmt_stats->execute();
$registered = $stmt_stats->fetchColumn();

$stmt_stats = $pdo->prepare("
    SELECT COUNT(*) FROM pendataan 
    WHERE status_warga = 'Aktif'
      AND nik IS NOT NULL AND nik != ''
      AND nama_lengkap IS NOT NULL AND nama_lengkap != ''
      AND tanggal_lahir IS NOT NULL
      AND jenis_kelamin IS NOT NULL AND jenis_kelamin != ''
      AND pekerjaan IS NOT NULL AND pekerjaan != ''
      AND alamat IS NOT NULL AND alamat != ''
");
$stmt_stats->execute();
$active_complete = $stmt_stats->fetchColumn();

$stmt_stats = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE status_warga = 'Tidak Aktif'");
$stmt_stats->execute();
$inactive = $stmt_stats->fetchColumn();

$not_registered = max(0, $active_complete - $registered);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan Data Penduduk</title>
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
            border-bottom: 2px solid #28a745;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #28a745;
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
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-left: 10px;
        }
        .summary, .filters {
            background: #fdfdfd;
            border-left: 5px solid #28a745;
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 0 5px rgba(0,0,0,0.05);
        }
        .summary h3, .filters h4 {
            margin: 0 0 10px;
            font-size: 16px;
            color: #28a745;
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
            font-size: 10px;
        }
        table th {
            background: #28a745;
            color: white;
            padding: 8px;
            text-align: center;
        }
        table td {
            padding: 6px;
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
        .status-aktif {
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
        .status-tidak-aktif {
            background: #f8d7da;
            color: #721c24;
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
            <h1>LAPORAN DATA PENDUDUK</h1>
            <p>Tanggal Export: <?= date('d F Y H:i:s') ?></p>
            <p>Sistem Informasi Pendataan Penduduk RT/RW</p>
        </div>
    </div>

    <div class="summary">
        <h3>STATISTIK DATA PENDUDUK</h3>
        <div class="summary-grid">
            <div class="summary-item"><span>Total Data Export</span><span><?= count($result) ?> record</span></div>
            <div class="summary-item"><span>Warga Aktif Lengkap</span><span><?= $active_complete ?> orang</span></div>
            <div class="summary-item"><span>Sudah Registrasi</span><span><?= $registered ?> orang</span></div>
            <div class="summary-item"><span>Belum Registrasi</span><span><?= $not_registered ?> orang</span></div>
            <div class="summary-item"><span>Status Tidak Aktif</span><span><?= $inactive ?> orang</span></div>
        </div>
    </div>

    <?php if ($search || $status_filter): ?>
    <div class="filters">
        <h4>Filter Yang Diterapkan:</h4>
        <ul style="margin:0 0 0 20px; padding:0;">
            <?php if ($search): ?>
                <li>Pencarian: <strong><?= htmlspecialchars($search) ?></strong></li>
            <?php endif; ?>
            <?php if ($status_filter): ?>
                <li>Status: <strong><?= htmlspecialchars($status_filter) ?></strong></li>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; ?>

    <h3 style="border-bottom:2px solid #28a745; padding-bottom:5px;">DETAIL DATA PENDUDUK</h3>
    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>No KK</th>
                <th>NIK</th>
                <th>Nama Lengkap</th>
                <th>Tgl Lahir</th>
                <th>L/P</th>
                <th>Pekerjaan</th>
                <th>Alamat</th>
                <th>Jml AK</th>
                <th>Status</th>
                <th>Registrasi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($result)): ?>
                <tr><td colspan="11">Tidak ada data ditemukan</td></tr>
            <?php else: ?>
                <?php $no = 1; foreach ($result as $row): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td><?= htmlspecialchars($row['no_kk']) ?></td>
                        <td><?= htmlspecialchars($row['nik'] ?: '-') ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                        <td><?= formatTanggalIndonesia($row['tanggal_lahir']) ?></td>
                        <td><?= $row['jenis_kelamin'] ? substr($row['jenis_kelamin'], 0, 1) : '-' ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($row['pekerjaan'] ?: '-') ?></td>
                        <td style="text-align:left;"><?= htmlspecialchars($row['alamat'] ?: '-') ?></td>
                        <td><?= $row['jumlah_anggota_keluarga'] ?: '0' ?></td>
                        <td class="<?= $row['status_warga'] === 'Aktif' ? 'status-aktif' : 'status-tidak-aktif' ?>">
                            <?= htmlspecialchars($row['status_warga']) ?>
                        </td>
                        <td><?= $row['is_registered'] ? 'Ya' : 'Belum' ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Laporan ini digenerate secara otomatis pada <?= date('d F Y \p\u\k\u\l H:i:s') ?> WIB</p>
        <p>© <?= date('Y') ?> Sistem Informasi Pendataan Penduduk RT/RW</p>
    </div>
</body>
</html>