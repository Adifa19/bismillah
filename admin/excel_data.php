<?php
require_once '../config.php';
requireAdmin();

// Function to format date to Indonesian format
function formatTanggalIndonesia($tanggal) {
    if (empty($tanggal) || $tanggal === '0000-00-00') {
        return '-';
    }
    
    $bulan_indonesia = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    try {
        $timestamp = strtotime($tanggal);
        if ($timestamp === false) {
            return $tanggal;
        }
        
        $hari = date('j', $timestamp);
        $bulan = (int)date('n', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return $hari . ' ' . $bulan_indonesia[$bulan] . ' ' . $tahun;
    } catch (Exception $e) {
        return $tanggal;
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize($_GET['status_filter']) : '';

$where_clause = "WHERE 1=1";
$params = [];

if ($search) {
    $where_clause .= " AND (p.nik LIKE ? OR p.nama_lengkap LIKE ? OR p.alamat LIKE ? OR p.no_kk LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

// Filter berdasarkan status
if ($status_filter) {
    if ($status_filter === 'Aktif') {
        $where_clause .= " AND EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.status_warga = 'Aktif')";
    } elseif ($status_filter === 'Tidak Aktif') {
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.status_warga = 'Aktif')";
    } elseif ($status_filter === 'Belum Lengkap') {
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.nik IS NOT NULL AND p2.nik != '' AND p2.nama_lengkap IS NOT NULL AND p2.nama_lengkap != '')";
    }
}

// Get all data without pagination for export
$sql = "SELECT DISTINCT p.no_kk, p.alamat, MIN(p.created_at) as earliest_created
        FROM pendataan p
        LEFT JOIN nomor_kk k ON p.no_kk = k.no_kk
        $where_clause
        GROUP BY p.no_kk, p.alamat
        ORDER BY earliest_created DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$families = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Data_Pendataan_Warga_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// Start output buffering
ob_start();
?>

<html>
<head>
    <meta charset="UTF-8">
    <title>Data Pendataan Warga</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .center {
            text-align: center;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>DATA PENDATAAN WARGA</h2>
        <p>Tanggal Export: <?php echo formatTanggalIndonesia(date('Y-m-d')); ?></p>
        <?php if ($search): ?>
            <p>Filter Pencarian: <?php echo htmlspecialchars($search); ?></p>
        <?php endif; ?>
        <?php if ($status_filter): ?>
            <p>Filter Status: <?php echo htmlspecialchars($status_filter); ?></p>
        <?php endif; ?>
    </div>

    <table>
        <tr>
            <th>No</th>
            <th>No. KK</th>
            <th>Alamat</th>
            <th>Kepala Keluarga</th>
            <th>NIK</th>
            <th>Tanggal Lahir</th>
            <th>Jenis Kelamin</th>
            <th>Pekerjaan</th>
            <th>No. Telepon</th>
            <th>Jumlah Anggota</th>
            <th>Status</th>
            <th>Tanggal Daftar</th>
        </tr>
        
        <?php 
        $no = 1;
        foreach ($families as $family): 
            // Get kepala keluarga data
            $stmt = $pdo->prepare("
                SELECT p.*, u.created_at as user_created_at 
                FROM pendataan p 
                LEFT JOIN users u ON p.user_id = u.id 
                WHERE p.no_kk = ? AND p.alamat = ? 
                ORDER BY p.created_at ASC 
                LIMIT 1
            ");
            $stmt->execute([$family['no_kk'], $family['alamat']]);
            $kepala_keluarga = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Count anggota keluarga
            if ($kepala_keluarga) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM anggota_keluarga WHERE pendataan_id = ?");
                $stmt->execute([$kepala_keluarga['id']]);
                $jumlah_anggota = $stmt->fetchColumn();
            } else {
                $jumlah_anggota = 0;
            }
            
            // Determine status
            $status = 'Belum Lengkap';
            if ($kepala_keluarga && !empty($kepala_keluarga['nik']) && !empty($kepala_keluarga['nama_lengkap'])) {
                $status = $kepala_keluarga['status_warga'];
            }
        ?>
        <tr>
            <td class="center"><?php echo $no++; ?></td>
            <td><?php echo htmlspecialchars($family['no_kk']); ?></td>
            <td><?php echo htmlspecialchars($family['alamat']); ?></td>
            <td><?php echo $kepala_keluarga ? htmlspecialchars($kepala_keluarga['nama_lengkap']) : '-'; ?></td>
            <td><?php echo $kepala_keluarga ? htmlspecialchars($kepala_keluarga['nik']) : '-'; ?></td>
            <td><?php echo $kepala_keluarga ? formatTanggalIndonesia($kepala_keluarga['tanggal_lahir']) : '-'; ?></td>
            <td><?php echo $kepala_keluarga ? htmlspecialchars($kepala_keluarga['jenis_kelamin']) : '-'; ?></td>
            <td><?php echo $kepala_keluarga ? htmlspecialchars($kepala_keluarga['pekerjaan']) : '-'; ?></td>
            <td><?php echo $kepala_keluarga ? htmlspecialchars($kepala_keluarga['no_telp']) : '-'; ?></td>
            <td class="center"><?php echo $jumlah_anggota; ?></td>
            <td class="center"><?php echo $status; ?></td>
            <td><?php echo formatTanggalIndonesia($family['earliest_created']); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <br><br>
    <div style="margin-top: 50px;">
        <p><strong>Keterangan Status:</strong></p>
        <ul>
            <li><strong>Aktif:</strong> Data lengkap dan status warga aktif</li>
            <li><strong>Tidak Aktif:</strong> Data lengkap tapi status warga tidak aktif</li>
            <li><strong>Belum Lengkap:</strong> Data kepala keluarga belum lengkap</li>
        </ul>
    </div>

    <div style="margin-top: 30px;">
        <p><strong>Total Data:</strong> <?php echo count($families); ?> Keluarga</p>
        <p><strong>Dicetak pada:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    </div>

</body>
</html>

<?php
// Send the output
$output = ob_get_clean();
echo $output;
exit;
?>