<?php
require_once '../config.php';
requireAdmin();

$error = '';
$success = '';

// Function to format date to Indonesian format (12 Juni 2025)
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
            return $tanggal; // Return original if can't parse
        }
        
        $hari = date('j', $timestamp);
        $bulan = (int)date('n', $timestamp);
        $tahun = date('Y', $timestamp);
        
        return $hari . ' ' . $bulan_indonesia[$bulan] . ' ' . $tahun;
    } catch (Exception $e) {
        return $tanggal; // Return original if error
    }
}

// Function to handle custom job input
function handleCustomJob($pekerjaan, $pekerjaan_custom) {
    if ($pekerjaan === 'Lainnya' && !empty($pekerjaan_custom)) {
        return trim($pekerjaan_custom);
    }
    return $pekerjaan;
}

// Process form data with date formatting
$tanggal_lahir = sanitize($_POST['tanggal_lahir'] ?? '');

// 1. Cek nomor KK yang belum ada di nomor_kk (debug)
$stmt = $pdo->query("SELECT DISTINCT no_kk FROM pendataan WHERE no_kk NOT IN (SELECT no_kk FROM nomor_kk)");
$no_kk_tidak_sesuai = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (count($no_kk_tidak_sesuai) > 0) {
    echo "<div style='background:#fdd; padding:10px; margin-bottom:10px;'>";
    echo "Nomor KK di pendataan yang belum ada di nomor_kk:<br>";
    foreach ($no_kk_tidak_sesuai as $no_kk) {
        echo "- " . htmlspecialchars($no_kk) . "<br>";
    }
    echo "</div>";
}

// 2. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update status warga
    if (isset($_POST['update_status'])) {
        $id = (int)($_POST['id'] ?? 0);
        $status = sanitize($_POST['status'] ?? '');

        if ($id <= 0) {
            $error = 'ID data tidak valid!';
        } elseif (!in_array($status, ['Aktif', 'Tidak Aktif'])) {
            $error = 'Status tidak valid!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE pendataan SET status_warga = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Status warga berhasil diperbarui!';
                } else {
                    $error = 'Data warga tidak ditemukan atau status sama.';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem saat update status: ' . $e->getMessage();
            }
        }
    }
    // Update status rumah - NEW FEATURE
    elseif (isset($_POST['update_status_rumah'])) {
        $id = (int)($_POST['id'] ?? 0);
        $status_rumah = sanitize($_POST['status_rumah'] ?? '');

        if ($id <= 0) {
            $error = 'ID data tidak valid!';
        } elseif (!in_array($status_rumah, ['Pribadi', 'Mengontrak'])) {
            $error = 'Status rumah tidak valid!';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE pendataan SET status_rumah = ? WHERE id = ?");
                $stmt->execute([$status_rumah, $id]);
                if ($stmt->rowCount() > 0) {
                    $success = 'Status rumah berhasil diperbarui!';
                } else {
                    $error = 'Data warga tidak ditemukan atau status rumah sama.';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan sistem saat update status rumah: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_data'])) {
        // Validasi input utama
        $no_kk = sanitize($_POST['no_kk'] ?? '');
        $alamat = sanitize($_POST['alamat'] ?? '');
        $status_rumah = sanitize($_POST['status_rumah'] ?? ''); // NEW FIELD

        // Validasi field wajib untuk admin input
        if (empty($no_kk) || empty($alamat)) {
            $error = 'No KK dan Alamat wajib diisi!';
        } elseif (strlen($no_kk) !== 16 || !ctype_digit($no_kk)) {
            $error = 'No KK harus 16 digit angka!';
        } elseif (!empty($status_rumah) && !in_array($status_rumah, ['Pribadi', 'Mengontrak'])) {
            $error = 'Status rumah tidak valid!';
        } else {
            try {
                $pdo->beginTransaction();

                // Cek apakah no_kk sudah ada di tabel nomor_kk
                $stmt = $pdo->prepare("SELECT id FROM nomor_kk WHERE no_kk = ?");
                $stmt->execute([$no_kk]);
                $row = $stmt->fetch();

                if (!$row) {
                    // Insert no_kk baru ke tabel nomor_kk
                    $stmt = $pdo->prepare("INSERT INTO nomor_kk (no_kk) VALUES (?)");
                    $stmt->execute([$no_kk]);
                }

                // Ambil data kepala keluarga (opsional untuk admin input)
                $nik = sanitize($_POST['nik'] ?? '');
                $nama_lengkap = sanitize($_POST['nama_lengkap'] ?? '');
                $tanggal_lahir = sanitize($_POST['tanggal_lahir'] ?? '');
                $jenis_kelamin = sanitize($_POST['jenis_kelamin'] ?? '');
                
                // Handle custom job for main user
                $pekerjaan_raw = sanitize($_POST['pekerjaan'] ?? '');
                $pekerjaan_custom = sanitize($_POST['pekerjaan_custom'] ?? '');
                $pekerjaan = handleCustomJob($pekerjaan_raw, $pekerjaan_custom);
                
                $jumlah_anggota_keluarga = (int)($_POST['jumlah_anggota_keluarga'] ?? 0);
                $no_telp = sanitize($_POST['no_telp'] ?? '');

                // Validasi untuk pekerjaan custom
                if ($pekerjaan_raw === 'Lainnya' && empty($pekerjaan_custom)) {
                    $error = 'Pekerjaan lainnya wajib diisi jika memilih "Lainnya"!';
                    $pdo->rollBack();
                    return;
                }

            // Handle file uploads
$foto_ktp = '';
$foto_kk = '';

if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] === UPLOAD_ERR_OK) {
    // Path untuk menyimpan file (dari konteks admin ke folder warga/uploads)
    $upload_path = '../warga/uploads/' . $nik . '_' . time() . '.jpg';
    
    // Path untuk disimpan di database (relatif dari konteks warga)
    $foto_ktp = '../uploads/' . $nik . '_' . time() . '.jpg';
    
    // Pastikan folder uploads ada
    if (!is_dir('../warga/uploads/')) {
        mkdir('../warga/uploads/', 0755, true);
    }
    
    if (move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $upload_path)) {
        // File berhasil diupload
    } else {
        // Handle error upload
        $foto_ktp = '';
        echo "Error uploading KTP file.";
    }
}

if (isset($_FILES['foto_kk']) && $_FILES['foto_kk']['error'] === UPLOAD_ERR_OK) {
    // Path untuk menyimpan file (dari konteks admin ke folder warga/uploads)
    $upload_path = '../warga/uploads/' . $no_kk . '_' . time() . '.jpg';
    
    // Path untuk disimpan di database (relatif dari konteks warga)
    $foto_kk = '../uploads/' . $no_kk . '_' . time() . '.jpg';
    
    // Pastikan folder uploads ada
    if (!is_dir('../warga/uploads/')) {
        mkdir('../warga/uploads/', 0755, true);
    }
    
    if (move_uploaded_file($_FILES['foto_kk']['tmp_name'], $upload_path)) {
        // File berhasil diupload
    } else {
        // Handle error upload
        $foto_kk = '';
        echo "Error uploading KK file.";
    }
}
                
                // Jika ada data kepala keluarga yang diisi
                if (!empty($nama_lengkap) && !empty($nik)) {
                    // Insert user baru ke tabel users (default username = no_kk, password = 123456)
                    $username = $no_kk;
                    $default_password = password_hash('123456', PASSWORD_DEFAULT);
                    
                    // Cek apakah username sudah ada
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $existing_user = $stmt->fetch();
                    
                    if (!$existing_user) {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status_pengguna, no_kk) VALUES (?, ?, 'user', 'Aktif', ?)");
                        $stmt->execute([$username, $default_password, $no_kk]);
                        $user_id = $pdo->lastInsertId();
                    } else {
                        $user_id = $existing_user['id'];
                    }

                    // Insert data ke pendataan (kepala keluarga) dengan status_rumah - UPDATED QUERY
                    $stmt = $pdo->prepare("INSERT INTO pendataan (user_id, no_kk, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, pekerjaan, jumlah_anggota_keluarga, alamat, no_telp, foto_ktp, foto_kk, status_warga, status_rumah, is_registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Aktif', ?, 0)");
                    $stmt->execute([
                        $user_id, 
                        $no_kk, 
                        $nik, 
                        $nama_lengkap, 
                        $tanggal_lahir, 
                        $jenis_kelamin, 
                        $pekerjaan, 
                        $jumlah_anggota_keluarga, 
                        $alamat, 
                        $no_telp, 
                        $foto_ktp, 
                        $foto_kk,
                        $status_rumah // NEW FIELD
                    ]);
                    
                    $pendataan_id = $pdo->lastInsertId();

                    // Insert anggota keluarga jika ada - UPDATED WITH CUSTOM JOB HANDLING
                    for ($i = 1; $i <= $jumlah_anggota_keluarga; $i++) {
                        $nik_anggota = sanitize($_POST["nik_$i"] ?? '');
                        $nama_anggota = sanitize($_POST["nama_lengkap_$i"] ?? '');
                        $tanggal_lahir_anggota = sanitize($_POST["tanggal_lahir_$i"] ?? '');
                        $jk_anggota = sanitize($_POST["jenis_kelamin_$i"] ?? '');
                        
                        // Handle custom job for family member
                        $pekerjaan_anggota_raw = sanitize($_POST["pekerjaan_$i"] ?? '');
                        $pekerjaan_anggota_custom = sanitize($_POST["pekerjaan_custom_$i"] ?? '');
                        $pekerjaan_anggota = handleCustomJob($pekerjaan_anggota_raw, $pekerjaan_anggota_custom);
                        
                        $status_hubungan = sanitize($_POST["status_hubungan_$i"] ?? '');

                        // Validasi untuk pekerjaan custom anggota keluarga
                        if ($pekerjaan_anggota_raw === 'Lainnya' && empty($pekerjaan_anggota_custom)) {
                            $error = "Pekerjaan lainnya untuk anggota keluarga ke-$i wajib diisi jika memilih \"Lainnya\"!";
                            $pdo->rollBack();
                            return;
                        }

                        if (!empty($nik_anggota) && !empty($nama_anggota) && !empty($jk_anggota) && !empty($status_hubungan)) {
                            $stmt = $pdo->prepare("INSERT INTO anggota_keluarga (pendataan_id, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, pekerjaan, status_hubungan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$pendataan_id, $nik_anggota, $nama_anggota, $tanggal_lahir_anggota, $jk_anggota, $pekerjaan_anggota, $status_hubungan]);
                        }
                    }
                } else {
                    // Jika hanya input No KK dan Alamat saja (untuk persiapan) - UPDATED WITH status_rumah
                    // Create dummy user untuk keperluan sistem
                    $username = $no_kk;
                    $default_password = password_hash('123456', PASSWORD_DEFAULT);
                    
                    // Cek apakah username sudah ada
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                    $stmt->execute([$username]);
                    $existing_user = $stmt->fetch();
                    
                    if (!$existing_user) {
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, role, status_pengguna, no_kk) VALUES (?, ?, 'user', 'Aktif', ?)");
                        $stmt->execute([$username, $default_password, $no_kk]);
                        $user_id = $pdo->lastInsertId();
                    } else {
                        $user_id = $existing_user['id'];
                    }

                    // Insert data minimal ke pendataan dengan status_rumah - UPDATED QUERY
                    $stmt = $pdo->prepare("INSERT INTO pendataan (user_id, no_kk, alamat, status_warga, status_rumah, is_registered) VALUES (?, ?, ?, 'Aktif', ?, 0)");
                    $stmt->execute([$user_id, $no_kk, $alamat, $status_rumah]);
                }

                $pdo->commit();
                $success = 'Data berhasil ditambahkan! No KK dan alamat telah terdaftar dalam sistem.';
                
            } catch (PDOException $e) {
                $pdo->rollBack();

                if ($e->getCode() == 23000) {
                    $error = 'Data sudah terdaftar atau terjadi duplikasi data!';
                } else {
                    $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
                }
            }
        }
    }
}

// Pagination, search, dan filter status
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$status_filter = isset($_GET['status_filter']) ? sanitize($_GET['status_filter']) : '';
$status_rumah_filter = isset($_GET['status_rumah_filter']) ? sanitize($_GET['status_rumah_filter']) : ''; // NEW FILTER
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

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

// Filter berdasarkan status rumah - NEW FILTER
if ($status_rumah_filter) {
    if ($status_rumah_filter === 'Pribadi') {
        $where_clause .= " AND EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.status_rumah = 'Pribadi')";
    } elseif ($status_rumah_filter === 'Mengontrak') {
        $where_clause .= " AND EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.status_rumah = 'Mengontrak')";
    } elseif ($status_rumah_filter === 'Belum Diisi') {
        $where_clause .= " AND NOT EXISTS (SELECT 1 FROM pendataan p2 WHERE p2.no_kk = p.no_kk AND p2.alamat = p.alamat AND p2.status_rumah IS NOT NULL AND p2.status_rumah != '')";
    }
}

// Hitung total keluarga unik (berdasarkan kombinasi no_kk dan alamat)
$count_sql = "SELECT COUNT(DISTINCT CONCAT(p.no_kk, '|', COALESCE(p.alamat, ''))) FROM pendataan p
              LEFT JOIN nomor_kk k ON p.no_kk = k.no_kk
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_data = $count_stmt->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Ambil kombinasi no_kk dan alamat yang unik untuk pagination
$unique_sql = "SELECT DISTINCT p.no_kk, p.alamat, MIN(p.created_at) as earliest_created
               FROM pendataan p
               LEFT JOIN nomor_kk k ON p.no_kk = k.no_kk
               $where_clause
               GROUP BY p.no_kk, p.alamat
               ORDER BY earliest_created DESC
               LIMIT $limit OFFSET $offset";
$unique_stmt = $pdo->prepare($unique_sql);
$unique_stmt->execute($params);
$unique_families = $unique_stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data statistik
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE is_registered = 1");
$stmt->execute();
$registered = $stmt->fetchColumn();

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
$not_registered = max(0, $active_complete - $registered);

// Total semua data aktif
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE status_warga = 'Aktif'");
$stmt->execute();
$active_all = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE status_warga = 'Tidak Aktif'");
$stmt->execute();
$inactive = $stmt->fetchColumn();

// Statistik status rumah - NEW STATISTICS
$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE status_rumah = 'Pribadi' AND status_warga = 'Aktif'");
$stmt->execute();
$rumah_pribadi = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE status_rumah = 'Mengontrak' AND status_warga = 'Aktif'");
$stmt->execute();
$rumah_mengontrak = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM pendataan WHERE (status_rumah IS NULL OR status_rumah = '') AND status_warga = 'Aktif'");
$stmt->execute();
$rumah_belum_diisi = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pendataan - Sistem Pendataan Warga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet" />
    <style>
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
    <div class="container-fluid mt-4">
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Page Header untuk Pendataan -->
        <div class="page-header">
            <h1>
                <i class="fas fa-id-card"></i>
                Pendataan Warga
            </h1>
            <p>Kelola dan perbarui data warga secara efisien dan terstruktur</p>
        </div>

  <!-- Statistik -->
        <div class="row mb-4">
            <div class="col">
                <div class="card bg-primary text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo $total_data; ?></h4>
                            <small>Total Keluarga</small>
                        </div>
                        <i class="fas fa-home fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-success text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo $registered; ?></h4>
                            <small>Sudah Terdaftar</small>
                        </div>
                        <i class="fas fa-user-check fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-warning text-dark">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo $not_registered; ?></h4>
                            <small>Belum Terdaftar</small>
                        </div>
                        <i class="fas fa-user-plus fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-info text-white">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h4><?php echo $active_complete; ?></h4>
                            <small>Data Lengkap & Aktif</small>
                        </div>
                        <i class="fas fa-user-circle fa-2x"></i>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4><?php echo $inactive; ?></h4>
                                <small>Status Tidak Aktif</small>
                            </div>
                            <i class="fas fa-user-slash fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
<!-- Header dan Search/Filter -->
<div class="mb-4">
    <div class="row g-3 align-items-center">
        <!-- Tombol Tambah Data -->
        <div class="col-lg-3 col-md-4">
            <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#addModal">
                <i class="fas fa-plus me-2"></i> Tambah Data
            </button>
        </div>
        
        <!-- Form Search dan Filter -->
        <div class="col-lg-9 col-md-8">
            <form method="GET" class="row g-2">
                <!-- Search Input -->
                <div class="col-lg-5 col-md-12">
                    <input type="text" class="form-control" name="search"
                           placeholder="Cari NIK, nama, atau alamat..."
                           value="<?php echo sanitize($search); ?>">
                </div>
                
                <!-- Status Filter -->
                <div class="col-lg-3 col-md-6">
                    <select name="status_filter" class="form-select">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?php echo $status_filter === 'Aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="Tidak Aktif" <?php echo $status_filter === 'Tidak Aktif' ? 'selected' : ''; ?>>Tidak Aktif</option>
                        <option value="Belum Lengkap" <?php echo $status_filter === 'Belum Lengkap' ? 'selected' : ''; ?>>Belum Lengkap</option>
                    </select>
                </div>
                
                <!-- Action Buttons -->
                <div class="col-lg-4 col-md-6">
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill" type="submit">
                            <i class="fas fa-search me-1"></i> Cari
                        </button>
                        
                        <?php if ($search || $status_filter): ?>
                        <a href="pendataan.php" class="btn btn-outline-secondary flex-fill">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Action Buttons Row - Terpisah untuk tampilan yang lebih rapi -->
    <div class="row mt-3">
        <div class="col-12">
            <div class="d-flex flex-wrap gap-2 justify-content-end">
                <!-- Tombol Download PDF -->
                <a href="pdf_data.php?<?php echo http_build_query($_GET); ?>" 
                   class="btn btn-outline-danger d-flex align-items-center" target="_blank"
                   data-bs-toggle="tooltip" data-bs-placement="top" title="Download data sebagai PDF">
                    <i class="fas fa-file-pdf me-2"></i>
                    <span class="d-none d-sm-inline">Download </span> PDF
                </a>
                
                <!-- Tombol Download Excel -->
                <a href="excel_data.php?<?php echo http_build_query($_GET); ?>" 
                   class="btn btn-outline-success d-flex align-items-center"
                   data-bs-toggle="tooltip" data-bs-placement="top" title="Download data sebagai Excel">
                    <i class="fas fa-file-excel me-2"></i>
                    <span class="d-none d-sm-inline">Download </span> Excel
                </a>
            </div>
        </div>
    </div>
</div>


<!-- Tabel data warga dengan anggota keluarga -->
<div class="table-responsive mb-4">
    <table class="table table-bordered table-striped align-middle">
        <thead class="table-primary text-center">
            <tr>
                <th style="width: 4%;">No</th>
                <th style="width: 13%;">No KK</th>
                <th style="width: 18%;">Alamat</th>
                <th style="width: 22%;">Kepala Keluarga</th>
                <th style="width: 18%;">Anggota Keluarga</th>
                <th style="width: 6%;">Total</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 8%;">Status Rumah</th>
                <th style="width: 6%;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($unique_families)): ?>
                <tr>
                    <td colspan="9" class="text-center py-4">
                        <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                        <br>Data tidak ditemukan
                    </td>
                </tr>
            <?php else: ?>
                <?php 
                $no = ($page - 1) * $limit + 1;
                foreach ($unique_families as $family): 
                ?>
                    <?php
                    // Ambil data kepala keluarga dengan data lengkap
                    $stmt_kepala = $pdo->prepare("
                        SELECT * FROM pendataan 
                        WHERE no_kk = ? 
                        AND alamat = ? 
                        AND nik IS NOT NULL AND nik != ''
                        AND nama_lengkap IS NOT NULL AND nama_lengkap != ''
                        ORDER BY 
                            CASE WHEN is_registered = 1 THEN 0 ELSE 1 END,
                            created_at DESC
                    ");
                    $stmt_kepala->execute([$family['no_kk'], $family['alamat']]);
                    $kepala_keluarga_list = $stmt_kepala->fetchAll(PDO::FETCH_ASSOC);

                    // Jika tidak ada kepala keluarga dengan data lengkap, ambil data minimal
                    if (empty($kepala_keluarga_list)) {
                        $stmt_minimal = $pdo->prepare("
                            SELECT * FROM pendataan 
                            WHERE no_kk = ? 
                            AND alamat = ? 
                            ORDER BY created_at DESC
                            LIMIT 1
                        ");
                        $stmt_minimal->execute([$family['no_kk'], $family['alamat']]);
                        $minimal_data = $stmt_minimal->fetch(PDO::FETCH_ASSOC);
                        
                        if ($minimal_data) {
                            $kepala_keluarga_list = [$minimal_data];
                        }
                    }
                    
                    if (empty($kepala_keluarga_list)) {
                        continue;
                    }
                    
                    // Ambil anggota keluarga - UPDATED QUERY WITH NEW FIELDS
                    $pendataan_ids = array_column($kepala_keluarga_list, 'id');
                    $anggota_keluarga_list = [];
                    
                    if (!empty($pendataan_ids)) {
                        $placeholders = str_repeat('?,', count($pendataan_ids) - 1) . '?';
                        $stmt_anggota = $pdo->prepare("SELECT *, COALESCE(tanggal_lahir, '') as tanggal_lahir, COALESCE(pekerjaan, '') as pekerjaan FROM anggota_keluarga WHERE pendataan_id IN ($placeholders) ORDER BY nama_lengkap");
                        $stmt_anggota->execute($pendataan_ids);
                        $anggota_keluarga_list = $stmt_anggota->fetchAll(PDO::FETCH_ASSOC);
                    }
                    
                    // Hitung total
                    $total_kepala = count(array_filter($kepala_keluarga_list, function($kepala) {
                        return !empty(trim($kepala['nik'] ?? ''));
                    }));
                    
                    $total_anggota_keluarga = count(array_filter($anggota_keluarga_list, function($anggota) {
                        return !empty(trim($anggota['nik'] ?? ''));
                    }));
                    
                    $total_anggota = $total_kepala + $total_anggota_keluarga;
                    
                    // Cek status
                    $all_active = true;
                    $has_complete_data = false;
                    $status_rumah_display = '';
                    
                    foreach ($kepala_keluarga_list as $kepala) {
                        if ($kepala['status_warga'] !== 'Aktif') {
                            $all_active = false;
                        }
                        if (!empty($kepala['nik']) && !empty($kepala['nama_lengkap'])) {
                            $has_complete_data = true;
                        }
                        // Ambil status rumah dari data kepala keluarga
                        if (!empty($kepala['status_rumah'])) {
                            $status_rumah_display = $kepala['status_rumah'];
                            break; // Ambil status rumah pertama yang ditemukan
                        }
                    }
                    ?>
                    <tr>
                        <td class="text-center align-top"><?php echo $no; ?></td>
                        <td class="text-center align-top">
                            <?php echo sanitize($family['no_kk']); ?>
                        </td>
                        <td class="align-top"><?php echo sanitize($family['alamat']); ?></td>
                        <td class="align-top">
                            <?php if ($has_complete_data): ?>
                                <?php foreach ($kepala_keluarga_list as $k_index => $kepala): 
                                    if (empty($kepala['nama_lengkap'])) continue;
                                ?>
                                    <div class="mb-2">
                                        <div class="fw-bold text-primary"><?php echo sanitize($kepala['nama_lengkap']); ?></div>
                                        <small class="text-muted">
                                            NIK: <?php echo sanitize($kepala['nik'] ?? '-'); ?><br>
                                            Tanggal Lahir: <?php echo formatTanggalIndonesia($kepala['tanggal_lahir']); ?><br>
                                            <?php if (!empty($kepala['jenis_kelamin'])): ?>
                                                <?php echo sanitize($kepala['jenis_kelamin']); ?>
                                                <?php if (!empty($kepala['pekerjaan'])): ?>
                                                    | <?php echo sanitize($kepala['pekerjaan']); ?>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (!empty($kepala['no_telp'])): ?>
                                                <br>Telp: <?php echo sanitize($kepala['no_telp']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">Data kepala keluarga belum lengkap</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($anggota_keluarga_list)): ?>
                                <?php foreach ($anggota_keluarga_list as $a_index => $anggota): ?>
                                    <div class="mb-2">
                                        <div class="fw-bold"><?php echo sanitize($anggota['nama_lengkap']); ?></div>
                                        <small class="text-muted">
                                            NIK: <?php echo sanitize($anggota['nik']); ?><br>
                                            <?php echo formatTanggalIndonesia($anggota['tanggal_lahir']); ?> | <?php echo sanitize($anggota['jenis_kelamin']) ?> <br>
                                            <?php echo sanitize($anggota['pekerjaan']); ?> | <?php echo ucfirst(sanitize($anggota['status_hubungan'])); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <small class="text-muted">Belum ada anggota keluarga</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top">
                            <span class="badge bg-info"><?php echo $total_anggota; ?> orang</span>
                        </td>
                        <td class="text-center align-top">
                            <?php if ($has_complete_data): ?>
                                <span class="badge <?php echo $all_active ? 'bg-success' : 'bg-danger'; ?>">
                                    <?php echo $all_active ? 'Aktif' : 'Tidak Aktif'; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Lengkap</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top">
                            <?php if (!empty($status_rumah_display)): ?>
                                <span class="badge <?php echo $status_rumah_display === 'Pribadi' ? 'bg-success' : 'bg-warning'; ?>">
                                    <?php echo sanitize($status_rumah_display); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Belum Diisi</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center align-top">
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" 
                                    data-bs-target="#detailModal<?php echo $no; ?>">
                                <i class="fas fa-eye"></i> Detail
                            </button>
                        </td>
                    </tr>

                    <!-- Modal Detail -->
                    <div class="modal fade" id="detailModal<?php echo $no; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detail Keluarga - No KK: <?php echo sanitize($family['no_kk']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <!-- Informasi Umum -->
                                    <div class="row mb-3">
                                        <div class="col-md-3">
                                            <strong>No KK:</strong> <?php echo sanitize($family['no_kk']); ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Alamat:</strong> <?php echo sanitize($family['alamat']); ?>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Total Anggota:</strong> 
                                            <span class="badge bg-info"><?php echo $total_anggota; ?> orang</span>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Status Rumah:</strong>
                                            <?php if (!empty($status_rumah_display)): ?>
                                                <span class="badge <?php echo $status_rumah_display === 'Pribadi' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo sanitize($status_rumah_display); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Belum Diisi</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                   <!-- Foto Dokumen -->
<hr>
<h6 class="text-primary mb-3">Dokumen</h6>
<div class="row">
    <!-- Foto KTP -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <small class="fw-bold">Foto KTP</small>
            </div>
            <div class="card-body text-center">
                <?php 
                $ktp_path = '';
                if (!empty($kepala['foto_ktp'])) {
                    $ktp_path = $kepala['foto_ktp'];
                    
                    // Konversi path untuk admin
                    // Dari '../uploads/filename.jpg' menjadi '../warga/uploads/filename.jpg'
                    if (strpos($ktp_path, '../uploads/') === 0) {
                        $ktp_path = str_replace('../uploads/', '../warga/uploads/', $ktp_path);
                    } elseif (strpos($ktp_path, 'uploads/') === 0) {
                        $ktp_path = '../warga/' . $ktp_path;
                    }
                }
                ?>
                <?php if ($ktp_path && file_exists($ktp_path)): ?>
                    <img src="<?= htmlspecialchars($ktp_path) ?>" 
                        alt="Foto KTP" class="img-fluid img-thumbnail mb-2"
                        style="max-height: 200px;"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                        <p class="text-muted mb-1">Foto KTP tidak dapat ditampilkan</p>
                        <small class="text-muted"><?= htmlspecialchars($kepala['foto_ktp']) ?></small>
                    </div>
                    <br>
                    <a href="<?= htmlspecialchars($ktp_path) ?>" target="_blank" 
                    class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Lihat Full
                    </a>
                <?php elseif (!empty($kepala['foto_ktp'])): ?>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 5px;">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted mb-1">File foto KTP tidak ditemukan</p>
                        <small class="text-muted">Path: <?= htmlspecialchars($kepala['foto_ktp']) ?></small>
                    </div>
                <?php else: ?>
                    <i class="fas fa-image fa-3x text-muted mb-2"></i>
                    <br><small class="text-muted">Foto KTP tidak tersedia</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Foto KK -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-light">
                <small class="fw-bold">Foto Kartu Keluarga</small>
            </div>
            <div class="card-body text-center">
                <?php 
                $kk_path = '';
                if (!empty($kepala['foto_kk'])) {
                    $kk_path = $kepala['foto_kk'];
                    
                    // Konversi path untuk admin
                    // Dari '../uploads/filename.jpg' menjadi '../warga/uploads/filename.jpg'
                    if (strpos($kk_path, '../uploads/') === 0) {
                        $kk_path = str_replace('../uploads/', '../warga/uploads/', $kk_path);
                    } elseif (strpos($kk_path, 'uploads/') === 0) {
                        $kk_path = '../warga/' . $kk_path;
                    }
                }
                ?>
                <?php if ($kk_path && file_exists($kk_path)): ?>
                    <img src="<?= htmlspecialchars($kk_path) ?>" 
                        alt="Foto KK" class="img-fluid img-thumbnail mb-2"
                        style="max-height: 200px;"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div style="display:none; padding: 20px; background: #f8f9fa; border-radius: 5px;">
                        <p class="text-muted mb-1">Foto KK tidak dapat ditampilkan</p>
                        <small class="text-muted"><?= htmlspecialchars($kepala['foto_kk']) ?></small>
                    </div>
                    <br>
                    <a href="<?= htmlspecialchars($kk_path) ?>" target="_blank" 
                    class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Lihat Full
                    </a>
                <?php elseif (!empty($kepala['foto_kk'])): ?>
                    <div style="padding: 20px; background: #f8f9fa; border-radius: 5px;">
                        <i class="fas fa-exclamation-triangle fa-2x text-warning mb-2"></i>
                        <p class="text-muted mb-1">File foto KK tidak ditemukan</p>
                        <small class="text-muted">Path: <?= htmlspecialchars($kepala['foto_kk']) ?></small>
                    </div>
                <?php else: ?>
                    <i class="fas fa-image fa-3x text-muted mb-2"></i>
                    <br><small class="text-muted">Foto KK tidak tersedia</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

                                    <!-- Data Anggota Keluarga -->
                                    <?php if (!empty($anggota_keluarga_list)): ?>
                                        <hr>
                                        <h6 class="mb-3">Anggota Keluarga:</h6>
                                        <?php foreach ($anggota_keluarga_list as $anggota): ?>
                                            <div class="card mb-3">
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <p><strong>Nama:</strong> <?php echo sanitize($anggota['nama_lengkap']); ?></p>
                                                            <p><strong>NIK:</strong> <?php echo sanitize($anggota['nik']); ?></p>
                                                            <p><strong>Tanggal Lahir:</strong> <?php echo formatTanggalIndonesia($anggota['tanggal_lahir']); ?></p>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <p><strong>Jenis Kelamin:</strong> <?php echo sanitize($anggota['jenis_kelamin']); ?></p>
                                                            <p><strong>Status Hubungan:</strong> <?php echo ucfirst(sanitize($anggota['status_hubungan'])); ?></p>
                                                            <p><strong>Pekerjaan:</strong> <?php echo sanitize($anggota['pekerjaan']); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $no++; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">Prev</a>
                        </li>
                    <?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php echo ($i === $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Modal tambah data -->
    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" enctype="multipart/form-data" class="modal-content needs-validation" novalidate>
          <div class="modal-header">
            <h5 class="modal-title" id="addModalLabel">Tambah Data Warga</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Data Kepala Keluarga -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="fas fa-user-tie"></i> Data Kepala Keluarga</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="no_kk" class="form-label">No KK (16 digit) *</label>
                            <input type="text" class="form-control" id="no_kk" name="no_kk" maxlength="16" pattern="\d{16}" required>
                            <div class="invalid-feedback">No KK harus 16 digit angka.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nik" class="form-label">NIK (16 digit) *</label>
                            <input type="text" class="form-control" id="nik" name="nik" maxlength="16" pattern="\d{16}" required>
                            <div class="invalid-feedback">NIK harus 16 digit angka.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap *</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                            <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="tanggal_lahir" class="form-label">Tanggal Lahir *</label>
                            <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" 
                                   max="2008-12-31" required>
                            <div class="invalid-feedback">Tanggal lahir wajib diisi.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="jenis_kelamin" class="form-label">Jenis Kelamin *</label>
                            <select class="form-select" id="jenis_kelamin" name="jenis_kelamin" required>
                                <option value="">Pilih</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                            <div class="invalid-feedback">Pilih jenis kelamin.</div>
                        </div>
                        <!-- Status Rumah Field - NEW -->
                    <div class="col-md-6">
                        <label for="status_rumah" class="form-label">Status Rumah</label>
                        <select class="form-select" id="status_rumah" name="status_rumah">
                            <option value="">Pilih Status Rumah</option>
                            <option value="Pribadi">Pribadi</option>
                            <option value="Mengontrak">Mengontrak</option>
                        </select>
                        <div class="invalid-feedback">Pilih status rumah.</div>
                        <div class="form-text text-muted">
                            <small><i class="fas fa-info-circle"></i> Opsional - dapat diisi nanti</small>
                        </div>
                    </div>
                        <div class="col-md-6">
                <label for="pekerjaan">Pekerjaan *</label>
                <select class="form-control" id="pekerjaan" name="pekerjaan" required onchange="toggleCustomJob()">
                    <option value="">Pilih Pekerjaan</option>
                    <option value="PNS">PNS</option>
                    <option value="TNI">TNI</option>
                    <option value="Polri">Polri</option>
                    <option value="Karyawan Swasta">Karyawan Swasta</option>
                    <option value="Wiraswasta">Wiraswasta</option>
                    <option value="Buruh">Buruh</option>
                    <option value="Ibu Rumah Tangga">Ibu Rumah Tangga</option>
                    <option value="Pengajar">Pengajar</option>
                    <option value="Lainnya">Lainnya</option>
                </select>
                <div class="invalid-feedback">Pekerjaan wajib diisi.</div>
            </div>

            <!-- Custom job input field -->
            <div class="col-md-6">
                <div class="form-group mb-3" id="custom-job-field" style="display: none;">
                    <label for="pekerjaan_custom">Pekerjaan Lainnya *</label>
                    <input type="text" class="form-control" id="pekerjaan_custom" name="pekerjaan_custom" placeholder="Masukkan pekerjaan">
                    <div class="invalid-feedback">Pekerjaan lainnya wajib diisi.</div>
                </div>
            </div>

            <div class="col-md-6">
                <label for="jumlah_anggota_keluarga" class="form-label">Jumlah Anggota Keluarga *</label>
                <input type="number" min="1" max="20" class="form-control" id="jumlah_anggota_keluarga" name="jumlah_anggota_keluarga" onchange="generateFamilyMembers()" required>
                <div class="invalid-feedback">Masukkan jumlah anggota keluarga</div>
            </div>

            <div class="col-md-6">
                <label for="alamat" class="form-label">Alamat Lengkap *</label>
                <textarea class="form-control" id="alamat" name="alamat" rows="2" required></textarea>
                <div class="invalid-feedback">Alamat wajib diisi.</div>
            </div>

            <div class="col-md-6">
                <label for="no_telp" class="form-label">No. Telp/HP</label>
                <input type="tel" class="form-control" id="no_telp" name="no_telp" pattern="^\+?\d{8,15}$">
                <div class="invalid-feedback">Masukkan nomor telepon yang valid (8-15 digit).</div>
            </div>

                    <!-- Upload Documents Section -->
                    <div class="col-12">
                        <hr class="my-3">
                        <h6 class="text-secondary"><i class="fas fa-upload"></i> Upload Dokumen (Opsional)</h6>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="foto_ktp" class="form-label">Foto KTP</label>
                        <input type="file" class="form-control" id="foto_ktp" name="foto_ktp" accept="image/*">
                        <div class="form-text text-muted">
                            <small>Format: JPG, PNG, maksimal 2MB</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="foto_kk" class="form-label">Foto Kartu Keluarga</label>
                        <input type="file" class="form-control" id="foto_kk" name="foto_kk" accept="image/*">
                        <div class="form-text text-muted">
                            <small>Format: JPG, PNG, maksimal 2MB</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

            <!-- Data Anggota Keluarga -->
            <div class="card mt-3">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0"><i class="fas fa-users"></i> Data Anggota Keluarga</h6>
                </div>
                <div class="card-body">
                    <div id="family-members-container">
                        <p class="text-muted">Pilih jumlah anggota keluarga terlebih dahulu untuk menampilkan form.</p>
                    </div>
                </div>
            </div>

            <div class="col-12 mt-3">
                <button type="submit" name="add_data" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Data
                </button>
            </div>
        </div>
    </form>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Existing family members data (initialize with empty array or your data)
const existingMembers = []; // Replace with your actual data

/**
 * Toggle custom job input field for main user
 */
function toggleCustomJob() {
    const pekerjaanSelect = document.getElementById('pekerjaan');
    const customJobField = document.getElementById('custom-job-field');
    const customJobInput = document.getElementById('pekerjaan_custom');
    
    if (pekerjaanSelect && customJobField && customJobInput) {
        if (pekerjaanSelect.value === 'Lainnya') {
            customJobField.style.display = 'block';
            customJobInput.required = true;
        } else {
            customJobField.style.display = 'none';
            customJobInput.required = false;
            customJobInput.value = '';
        }
    }
}

/**
 * Toggle custom job input field for family member
 * @param {number} memberIndex - Index of the family member
 */
function toggleCustomJobMember(memberIndex) {
    const pekerjaanSelect = document.querySelector(`select[name="pekerjaan_${memberIndex}"]`);
    const customJobField = document.getElementById(`custom-job-field-${memberIndex}`);
    const customJobInput = document.querySelector(`input[name="pekerjaan_custom_${memberIndex}"]`);
    
    if (pekerjaanSelect && customJobField && customJobInput) {
        if (pekerjaanSelect.value === 'Lainnya') {
            customJobField.style.display = 'block';
            customJobInput.required = true;
        } else {
            customJobField.style.display = 'none';
            customJobInput.required = false;
            customJobInput.value = '';
        }
    }
}

/**
 * Generate family member form fields based on selected count
 */
function generateFamilyMembers() {
    const jumlah = parseInt(document.getElementById('jumlah_anggota_keluarga').value) || 0;
    const container = document.getElementById('family-members-container');
    
    // Clear container
    container.innerHTML = '';
    
    if (jumlah === 0) {
        container.innerHTML = '<p class="text-muted">Pilih jumlah anggota keluarga terlebih dahulu untuk menampilkan form.</p>';
        return;
    }

    // Predefined job options
    const predefinedJobs = ['PNS', 'TNI', 'Polri', 'Karyawan Swasta', 'Wiraswasta', 'Buruh', 
                          'Ibu Rumah Tangga', 'Pengajar', 'Mahasiswa', 'Pelajar', 'Tidak Bekerja', 'Lainnya'];

    for (let i = 1; i <= jumlah; i++) {
        const existingMember = existingMembers[i-1] || {};
        
        // Check if existing member has custom job
        const hasCustomJob = existingMember.pekerjaan && !predefinedJobs.includes(existingMember.pekerjaan);
        const selectedJob = hasCustomJob ? 'Lainnya' : (existingMember.pekerjaan || '');
        const customJobValue = hasCustomJob ? existingMember.pekerjaan : '';
        
        const memberCard = document.createElement('div');
        memberCard.className = 'card mb-3';
        memberCard.innerHTML = `
            <div class="card-body">
                <h6 class="card-title">Anggota Keluarga ${i}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">NIK Anggota ${i} *</label>
                            <input type="text" class="form-control" name="nik_${i}" maxlength="16" 
                                   value="${existingMember.nik || ''}" pattern="\\d{16}" required>
                            <div class="invalid-feedback">NIK harus 16 digit angka.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Nama Lengkap Anggota ${i} *</label>
                            <input type="text" class="form-control" name="nama_lengkap_${i}" 
                                   value="${existingMember.nama_lengkap || ''}" required>
                            <div class="invalid-feedback">Nama lengkap wajib diisi.</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Tanggal Lahir Anggota ${i} *</label>
                            <input type="date" class="form-control" name="tanggal_lahir_${i}" 
                                   value="${existingMember.tanggal_lahir || ''}" required>
                            <div class="invalid-feedback">Tanggal lahir wajib diisi.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Jenis Kelamin Anggota ${i} *</label>
                            <select class="form-control" name="jenis_kelamin_${i}" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="Laki-laki" ${existingMember.jenis_kelamin === 'Laki-laki' ? 'selected' : ''}>Laki-laki</option>
                                <option value="Perempuan" ${existingMember.jenis_kelamin === 'Perempuan' ? 'selected' : ''}>Perempuan</option>
                            </select>
                            <div class="invalid-feedback">Jenis kelamin wajib dipilih.</div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Pekerjaan Anggota ${i} *</label>
                            <select class="form-control" name="pekerjaan_${i}" required onchange="toggleCustomJobMember(${i})">
                                <option value="">Pilih Pekerjaan</option>
                                <option value="PNS" ${selectedJob === 'PNS' ? 'selected' : ''}>PNS</option>
                                <option value="TNI" ${selectedJob === 'TNI' ? 'selected' : ''}>TNI</option>
                                <option value="Polri" ${selectedJob === 'Polri' ? 'selected' : ''}>Polri</option>
                                <option value="Karyawan Swasta" ${selectedJob === 'Karyawan Swasta' ? 'selected' : ''}>Karyawan Swasta</option>
                                <option value="Wiraswasta" ${selectedJob === 'Wiraswasta' ? 'selected' : ''}>Wiraswasta</option>
                                <option value="Buruh" ${selectedJob === 'Buruh' ? 'selected' : ''}>Buruh</option>
                                <option value="Ibu Rumah Tangga" ${selectedJob === 'Ibu Rumah Tangga' ? 'selected' : ''}>Ibu Rumah Tangga</option>
                                <option value="Pengajar" ${selectedJob === 'Pengajar' ? 'selected' : ''}>Pengajar</option>
                                <option value="Mahasiswa" ${selectedJob === 'Mahasiswa' ? 'selected' : ''}>Mahasiswa</option>
                                <option value="Pelajar" ${selectedJob === 'Pelajar' ? 'selected' : ''}>Pelajar</option>
                                <option value="Tidak Bekerja" ${selectedJob === 'Tidak Bekerja' ? 'selected' : ''}>Tidak Bekerja</option>
                                <option value="Lainnya" ${selectedJob === 'Lainnya' ? 'selected' : ''}>Lainnya</option>
                            </select>
                            <div class="invalid-feedback">Pekerjaan wajib dipilih.</div>
                        </div>
                        
                        <!-- Custom job input for family member -->
                        <div class="form-group mb-3" id="custom-job-field-${i}" style="display: ${hasCustomJob ? 'block' : 'none'};">
                            <label class="form-label">Pekerjaan Lainnya Anggota ${i} *</label>
                            <input type="text" class="form-control" name="pekerjaan_custom_${i}" 
                                   placeholder="Masukkan pekerjaan" value="${customJobValue}" 
                                   ${hasCustomJob ? 'required' : ''}>
                            <div class="invalid-feedback">Pekerjaan lainnya wajib diisi.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Status Hubungan Anggota ${i} *</label>
                            <select class="form-control" name="status_hubungan_${i}" required>
                                <option value="">Pilih Status</option>
                                <option value="anak" ${existingMember.status_hubungan === 'anak' ? 'selected' : ''}>Anak</option>
                                <option value="istri" ${existingMember.status_hubungan === 'istri' ? 'selected' : ''}>Istri</option>
                                <option value="saudara" ${existingMember.status_hubungan === 'saudara' ? 'selected' : ''}>Saudara</option>
                                <option value="Orangtua" ${existingMember.status_hubungan === 'orangtua' ? 'selected' : ''}>Orang Tua</option>
                                <option value="cucu" ${existingMember.status_hubungan === 'cucu' ? 'selected' : ''}>Cucu</option>
                                <option value="menantu" ${existingMember.status_hubungan === 'menantu' ? 'selected' : ''}>Menantu</option>
                                <option value="mertua" ${existingMember.status_hubungan === 'mertua' ? 'selected' : ''}>Mertua</option>
                                <option value="famililain" ${existingMember.status_hubungan === 'famililain' ? 'selected' : ''}>Famili Lain</option>
                            </select>
                            <div class="invalid-feedback">Status hubungan wajib dipilih.</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(memberCard);
    }
}

/**
 * Set main user job to custom if not in predefined list
 * @param {string} jobName - The job name to check and set
 */
function setMainCustomJob(jobName) {
    const predefinedJobs = ['PNS', 'TNI', 'Polri', 'Karyawan Swasta', 'Wiraswasta', 'Buruh', 'Ibu Rumah Tangga', 'Pengajar', 'Lainnya'];
    const hasCustomMainJob = jobName && !predefinedJobs.includes(jobName);
    
    if (hasCustomMainJob) {
        const mainJobSelect = document.getElementById('pekerjaan');
        const mainJobCustomInput = document.getElementById('pekerjaan_custom');
        if (mainJobSelect && mainJobCustomInput) {
            mainJobSelect.value = 'Lainnya';
            mainJobCustomInput.value = jobName;
            toggleCustomJob();
        }
    }
}

/**
 * Initialize the form on page load
 */
function initializeForm() {
    // Initialize main job field
    toggleCustomJob();
    
    // Generate family member fields if jumlah is already set
    const jumlahInput = document.getElementById('jumlah_anggota_keluarga');
    if (jumlahInput && jumlahInput.value) {
        generateFamilyMembers();
    }
    
    // Add event listener for family member count change
    if (jumlahInput) {
        jumlahInput.addEventListener('change', generateFamilyMembers);
    }
    
    // Add event listener for main job change
    const mainJobSelect = document.getElementById('pekerjaan');
    if (mainJobSelect) {
        mainJobSelect.addEventListener('change', toggleCustomJob);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeForm);

// Alternative initialization for jQuery users
// $(document).ready(function() {
//     initializeForm();
// });
</script>

    <!-- Custom CSS -->
    <style>
        .table td, .table th {
            vertical-align: middle;
        }
        
        .table img {
            border-radius: 4px;
            object-fit: cover;
        }
        
        .card {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        
        .modal-xl {
            max-width: 1200px;
        }
        
        .was-validated .form-control:invalid {
            border-color: #dc3545;
        }
        
        .was-validated .form-control:valid {
            border-color: #198754;
        }
        
        .alert {
            border-left: 4px solid;
        }
        
        .alert-danger {
            border-left-color: #dc3545;
        }
        
        .alert-success {
            border-left-color: #198754;
        }
        
        .pagination .page-link {
            color: #0d6efd;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .dropdown-menu {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        @media (max-width: 768px) {
            .modal-xl {
                max-width: 95%;
            }
            
            .table-responsive {
                font-size: 0.875rem;
            }
            
            .card-body h4 {
                font-size: 1.5rem;
            }
        }
        
        /* Loading animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Smooth transitions */
        .btn, .card, .alert {
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        /* Custom scrollbar for modal */
        .modal-dialog-scrollable .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-dialog-scrollable .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .modal-dialog-scrollable .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</body>
</html>



