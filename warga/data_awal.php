<?php
require_once '../config.php'; // koneksi dan session start
requireLogin();

$user_id = $_SESSION['user_id'];

// Ambil data user dan alamat
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userInfo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$userInfo) {
    die('Data pengguna tidak ditemukan.');
}

$no_kk = $userInfo['no_kk'];

// Ambil alamat dari berbagai sumber
$alamat = '';
if ($no_kk) {
    // Prioritas 1: Ambil dari data pendataan user ini
    $stmt = $pdo->prepare("SELECT alamat FROM pendataan WHERE user_id = ? AND alamat IS NOT NULL AND alamat != ''");
    $stmt->execute([$user_id]);
    $alamatData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($alamatData && $alamatData['alamat']) {
        $alamat = $alamatData['alamat'];
    } else {
        // Prioritas 2: Ambil dari data pendataan dengan no_kk yang sama
        $stmt = $pdo->prepare("SELECT alamat FROM pendataan WHERE no_kk = ? AND alamat IS NOT NULL AND alamat != '' LIMIT 1");
        $stmt->execute([$no_kk]);
        $alamatData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($alamatData && $alamatData['alamat']) {
            $alamat = $alamatData['alamat'];
        }
    }
}

// Jika masih kosong, set default
if (empty($alamat)) {
    $alamat = 'Alamat belum tersedia';
}

// Ambil data pendataan user saat ini jika ada
$stmt = $pdo->prepare("SELECT * FROM pendataan WHERE user_id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$pendataan_id = $userData['id'] ?? null;

$error = '';
$success = '';

// Ambil data lengkap jika ada
$data = $userData; // Gunakan data yang sudah diambil sebelumnya

// Ambil data anggota keluarga jika ada
$anggota_keluarga_existing = [];
if ($pendataan_id) {
    $stmt = $pdo->prepare("SELECT * FROM anggota_keluarga WHERE pendataan_id = ?");
    $stmt->execute([$pendataan_id]);
    $anggota_keluarga_existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Data dari form
    $nik = trim($_POST['nik']);
    $nama_lengkap = trim($_POST['nama_lengkap']);
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    
    // Handle custom job input
    $pekerjaan = $_POST['pekerjaan'];
    if ($pekerjaan === 'Lainnya' && !empty($_POST['pekerjaan_custom'])) {
        $pekerjaan = trim($_POST['pekerjaan_custom']);
    }
    
    $jumlah_anggota_keluarga = (int)$_POST['jumlah_anggota_keluarga'];
    $no_telp = trim($_POST['no_telp']);

    // Validasi dasar
    if (empty($nik) || empty($nama_lengkap) || empty($tanggal_lahir) || empty($jenis_kelamin) || empty($pekerjaan)
        || empty($alamat) || empty($no_telp) || empty($no_kk)) {
        $error = "Semua field wajib diisi.";
    } elseif (strlen($nik) !== 16 || !is_numeric($nik)) {
        $error = "NIK harus 16 digit angka.";
    } elseif (strlen($no_kk) !== 16 || !is_numeric($no_kk)) {
        $error = "No KK harus 16 digit angka.";
    } elseif ($jumlah_anggota_keluarga < 0 || $jumlah_anggota_keluarga > 10) {
        $error = "Jumlah anggota keluarga harus antara 0-10.";
    }

    // Validasi anggota keluarga - HANYA jika ada anggota keluarga
    $anggota_keluarga = [];
    $status_used = [];

    if ($jumlah_anggota_keluarga > 0) {
        for ($i = 1; $i <= $jumlah_anggota_keluarga; $i++) {
            $nik_anggota = isset($_POST["nik_$i"]) ? trim($_POST["nik_$i"]) : '';
            $nama_anggota = isset($_POST["nama_lengkap_$i"]) ? trim($_POST["nama_lengkap_$i"]) : '';
            $tanggal_lahir_anggota = isset($_POST["tanggal_lahir_$i"]) ? $_POST["tanggal_lahir_$i"] : '';
            $jenis_kelamin_anggota = isset($_POST["jenis_kelamin_$i"]) ? $_POST["jenis_kelamin_$i"] : '';
            
            // Handle custom job for family members
            $pekerjaan_anggota = isset($_POST["pekerjaan_$i"]) ? $_POST["pekerjaan_$i"] : '';
            if ($pekerjaan_anggota === 'Lainnya' && !empty($_POST["pekerjaan_custom_$i"])) {
                $pekerjaan_anggota = trim($_POST["pekerjaan_custom_$i"]);
            }
            
            $status_hubungan = isset($_POST["status_hubungan_$i"]) ? $_POST["status_hubungan_$i"] : '';

            if (empty($nik_anggota) || empty($nama_anggota) || empty($tanggal_lahir_anggota) || empty($jenis_kelamin_anggota) || empty($pekerjaan_anggota) || empty($status_hubungan)) {
                $error = "Anggota keluarga ke-$i tidak lengkap. Pastikan semua field terisi.";
                break;
            }

            if (strlen($nik_anggota) !== 16 || !is_numeric($nik_anggota)) {
                $error = "NIK anggota ke-$i harus 16 digit angka.";
                break;
            }

            // Validasi status hubungan (kecuali anak dan saudara yang bisa lebih dari satu)
            if (!in_array($status_hubungan, ['anak', 'saudara']) && in_array($status_hubungan, $status_used)) {
                $error = "Status hubungan '$status_hubungan' tidak boleh duplikat.";
                break;
            }

            if (!in_array($status_hubungan, ['anak', 'saudara'])) {
                $status_used[] = $status_hubungan;
            }

            $anggota_keluarga[] = [
                'nik' => $nik_anggota,
                'nama_lengkap' => $nama_anggota,
                'tanggal_lahir' => $tanggal_lahir_anggota,
                'jenis_kelamin' => $jenis_kelamin_anggota,
                'pekerjaan' => $pekerjaan_anggota,
                'status' => $status_hubungan
            ];
        }
    }

    // Upload file
    $foto_ktp = $data['foto_ktp'] ?? null; // Gunakan foto lama jika tidak ada upload baru
    $foto_kk = $data['foto_kk'] ?? null;   // Gunakan foto lama jika tidak ada upload baru
    $upload_dir = '../uploads/';

    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    if (isset($_FILES['foto_ktp']) && $_FILES['foto_ktp']['error'] === 0) {
        $foto_ktp = $upload_dir . time() . '_ktp_' . basename($_FILES['foto_ktp']['name']);
        move_uploaded_file($_FILES['foto_ktp']['tmp_name'], $foto_ktp);
    }

    if (isset($_FILES['foto_kk']) && $_FILES['foto_kk']['error'] === 0) {
        $foto_kk = $upload_dir . time() . '_kk_' . basename($_FILES['foto_kk']['name']);
        move_uploaded_file($_FILES['foto_kk']['tmp_name'], $foto_kk);
    }

    // Jika validasi lolos, simpan ke database
    if (!$error) {
        try {
            $pdo->beginTransaction();

            if ($pendataan_id) {
                // Update data lama
                $stmt = $pdo->prepare("UPDATE pendataan SET nik=?, nama_lengkap=?, tanggal_lahir=?, jenis_kelamin=?, pekerjaan=?, jumlah_anggota_keluarga=?, no_telp=?, foto_ktp=?, foto_kk=?, is_registered=1 WHERE id=?");
                $stmt->execute([$nik, $nama_lengkap, $tanggal_lahir, $jenis_kelamin, $pekerjaan, $jumlah_anggota_keluarga, $no_telp, $foto_ktp, $foto_kk, $pendataan_id]);

                // Hapus anggota lama
                $stmt = $pdo->prepare("DELETE FROM anggota_keluarga WHERE pendataan_id = ?");
                $stmt->execute([$pendataan_id]);
            } else {
                // Insert baru
                $stmt = $pdo->prepare("INSERT INTO pendataan (user_id, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, pekerjaan, jumlah_anggota_keluarga, no_kk, alamat, no_telp, foto_ktp, foto_kk, is_registered) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $stmt->execute([$user_id, $nik, $nama_lengkap, $tanggal_lahir, $jenis_kelamin, $pekerjaan, $jumlah_anggota_keluarga, $no_kk, $alamat, $no_telp, $foto_ktp, $foto_kk]);
                $pendataan_id = $pdo->lastInsertId();
            }

            // Simpan anggota keluarga - HANYA jika ada anggota keluarga
            if ($jumlah_anggota_keluarga > 0 && !empty($anggota_keluarga)) {
                $stmt = $pdo->prepare("INSERT INTO anggota_keluarga (pendataan_id, nik, nama_lengkap, tanggal_lahir, jenis_kelamin, pekerjaan, status_hubungan) VALUES (?, ?, ?, ?, ?, ?, ?)");
                foreach ($anggota_keluarga as $anggota) {
                    $stmt->execute([$pendataan_id, $anggota['nik'], $anggota['nama_lengkap'], $anggota['tanggal_lahir'], $anggota['jenis_kelamin'], $anggota['pekerjaan'], $anggota['status']]);
                }
            }

            // Update users
            $stmt = $pdo->prepare("UPDATE users SET data_lengkap = 1 WHERE id = ?");
            $stmt->execute([$user_id]);

            $pdo->commit();

            $_SESSION['data_lengkap'] = true;
            $success = "Data berhasil disimpan!";
            
            // Refresh data setelah berhasil disimpan
            header('Location: profile.php');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Terjadi kesalahan: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Pendataan Warga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4>Profile Warga</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <!-- Data yang sudah terisi otomatis dari admin -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="no_kk">No KK</label>
                                        <input type="text" class="form-control" id="no_kk" name="no_kk" value="<?php echo htmlspecialchars($no_kk); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="alamat">Alamat Lengkap</label>
                                        <input type="text" class="form-control" id="alamat" name="alamat" value="<?php echo htmlspecialchars($alamat); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <!-- Data yang perlu diisi user -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="nik">NIK *</label>
                                        <input type="text" class="form-control" id="nik" name="nik" maxlength="16" value="<?php echo htmlspecialchars($data['nik'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="nama_lengkap">Nama Lengkap *</label>
                                        <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo htmlspecialchars($data['nama_lengkap'] ?? ''); ?>" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="tanggal_lahir">Tanggal Lahir *</label>
                                        <input type="date" class="form-control" id="tanggal_lahir" name="tanggal_lahir" value="<?php echo $data['tanggal_lahir'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="jenis_kelamin">Jenis Kelamin *</label>
                                        <select class="form-control" id="jenis_kelamin" name="jenis_kelamin" readonly>
                                            <option value="">Pilih Jenis Kelamin</option>
                                            <option value="Laki-laki" <?php echo ($data['jenis_kelamin'] ?? '') === 'Laki-laki' ? 'selected' : ''; ?>>Laki-laki</option>
                                            <option value="Perempuan" <?php echo ($data['jenis_kelamin'] ?? '') === 'Perempuan' ? 'selected' : ''; ?>>Perempuan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="pekerjaan">Pekerjaan *</label>
                                        <select class="form-control" id="pekerjaan" name="pekerjaan" required onchange="toggleCustomJob()">
                                            <option value="">Pilih Pekerjaan</option>
                                            <option value="PNS" <?php echo ($data['pekerjaan'] ?? '') === 'PNS' ? 'selected' : ''; ?>>PNS</option>
                                            <option value="TNI" <?php echo ($data['pekerjaan'] ?? '') === 'TNI' ? 'selected' : ''; ?>>TNI</option>
                                            <option value="Polri" <?php echo ($data['pekerjaan'] ?? '') === 'Polri' ? 'selected' : ''; ?>>Polri</option>
                                            <option value="Karyawan Swasta" <?php echo ($data['pekerjaan'] ?? '') === 'Karyawan Swasta' ? 'selected' : ''; ?>>Karyawan Swasta</option>
                                            <option value="Wiraswasta" <?php echo ($data['pekerjaan'] ?? '') === 'Wiraswasta' ? 'selected' : ''; ?>>Wiraswasta</option>
                                            <option value="Buruh" <?php echo ($data['pekerjaan'] ?? '') === 'Buruh' ? 'selected' : ''; ?>>Buruh</option>
                                            <option value="Ibu Rumah Tangga" <?php echo ($data['pekerjaan'] ?? '') === 'Ibu Rumah Tangga' ? 'selected' : ''; ?>>Ibu Rumah Tangga</option>
                                            <option value="Pengajar" <?php echo ($data['pekerjaan'] ?? '') === 'Pengajar' ? 'selected' : ''; ?>>Pengajar</option>
                                            <option value="Lainnya" <?php echo ($data['pekerjaan'] ?? '') === 'Lainnya' ? 'selected' : ''; ?>>Lainnya</option>
                                        </select>
                                    </div>
                                     <!-- Custom job input field -->
                                    <div class="form-group mb-3" id="custom-job-field" style="display: none;">
                                            <label for="pekerjaan_custom">Pekerjaan Lainnya *</label>
                                            <input type="text" class="form-control" id="pekerjaan_custom" name="pekerjaan_custom" placeholder="Masukkan pekerjaan">
                                        </div>
                                    </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="no_telp">No Telepon *</label>
                                        <input type="text" class="form-control" id="no_telp" name="no_telp" value="<?php echo htmlspecialchars($data['no_telp'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Files -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="foto_ktp">Foto KTP</label>
                                        <input type="file" class="form-control" id="foto_ktp" name="foto_ktp" accept="image/*">
                                        <?php if (isset($data['foto_ktp']) && $data['foto_ktp']): ?>
                                            <small class="text-muted">File saat ini: <?php echo basename($data['foto_ktp']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-3">
                                        <label for="foto_kk">Foto Kartu Keluarga</label>
                                        <input type="file" class="form-control" id="foto_kk" name="foto_kk" accept="image/*">
                                        <?php if (isset($data['foto_kk']) && $data['foto_kk']): ?>
                                            <small class="text-muted">File saat ini: <?php echo basename($data['foto_kk']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label for="jumlah_anggota_keluarga">Jumlah Anggota Keluarga *</label>
                                <input type="number" class="form-control" id="jumlah_anggota_keluarga" name="jumlah_anggota_keluarga" min="0" max="10" value="<?php echo $data['jumlah_anggota_keluarga'] ?? '0'; ?>" required>
                                <small class="text-muted">Isi 0 jika tidak ada anggota keluarga lain</small>
                            </div>

                            <!-- Dynamic Family Members Section -->
                            <div id="anggota_keluarga_section" style="display: none;">
                                <h5>Data Anggota Keluarga</h5>
                                <div id="anggota_container">
                                    <!-- Dynamic content will be inserted here -->
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary">Simpan Data</button>
                                <a href="profile.php" class="btn btn-secondary">Kembali</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
        
        // Existing family members data from PHP
        const existingMembers = <?php echo json_encode($anggota_keluarga_existing); ?>;
        
        function toggleCustomJob() {
            const pekerjaanSelect = document.getElementById('pekerjaan');
            const customJobField = document.getElementById('custom-job-field');
            const customJobInput = document.getElementById('pekerjaan_custom');
            
            if (pekerjaanSelect.value === 'Lainnya') {
                customJobField.style.display = 'block';
                customJobInput.required = true;
            } else {
                customJobField.style.display = 'none';
                customJobInput.required = false;
                customJobInput.value = '';
            }
        }
        
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
        
        function generateAnggotaKeluarga() {
            const jumlah = parseInt(document.getElementById('jumlah_anggota_keluarga').value) || 0;
            const container = document.getElementById('anggota_container');
            const section = document.getElementById('anggota_keluarga_section');
            
            container.innerHTML = '';
            
            if (jumlah === 0) {
                section.style.display = 'none';
                return;
            }
            
            section.style.display = 'block';

            for (let i = 1; i <= jumlah; i++) {
                const existingMember = existingMembers[i-1] || {};
                
                // Check if existing member has custom job (not in predefined list)
                const predefinedJobs = ['PNS', 'TNI', 'Polri', 'Karyawan Swasta', 'Wiraswasta', 'Buruh', 
                                      'Ibu Rumah Tangga', 'Pengajar', 'Mahasiswa', 'Pelajar', 'Tidak Bekerja', 'Lainnya'];
                const hasCustomJob = existingMember.pekerjaan && !predefinedJobs.includes(existingMember.pekerjaan);
                const selectedJob = hasCustomJob ? 'Lainnya' : (existingMember.pekerjaan || '');
                const customJobValue = hasCustomJob ? existingMember.pekerjaan : '';
                
                const anggotaDiv = document.createElement('div');
                anggotaDiv.className = 'card mb-3 anggota-card';
                anggotaDiv.setAttribute('data-index', i);
                anggotaDiv.innerHTML = `
                    <div class="card-body">
                        <h6>Anggota Keluarga ${i}</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>NIK Anggota ${i} *</label>
                                    <input type="text" class="form-control" name="nik_${i}" maxlength="16" value="${existingMember.nik || ''}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Nama Lengkap Anggota ${i} *</label>
                                    <input type="text" class="form-control" name="nama_lengkap_${i}" value="${existingMember.nama_lengkap || ''}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Tanggal Lahir Anggota ${i} *</label>
                                    <input type="date" class="form-control" name="tanggal_lahir_${i}" value="${existingMember.tanggal_lahir || ''}" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Jenis Kelamin Anggota ${i} *</label>
                                    <select class="form-control" name="jenis_kelamin_${i}" required>
                                        <option value="">Pilih Jenis Kelamin</option>
                                        <option value="Laki-laki" ${existingMember.jenis_kelamin === 'Laki-laki' ? 'selected' : ''}>Laki-laki</option>
                                        <option value="Perempuan" ${existingMember.jenis_kelamin === 'Perempuan' ? 'selected' : ''}>Perempuan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Pekerjaan Anggota ${i} *</label>
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
                                </div>
                                
                                <!-- Custom job input for family member -->
                                <div class="form-group mb-3" id="custom-job-field-${i}" style="display: ${hasCustomJob ? 'block' : 'none'};">
                                    <label>Pekerjaan Lainnya Anggota ${i} *</label>
                                    <input type="text" class="form-control" name="pekerjaan_custom_${i}" placeholder="Masukkan pekerjaan" value="${customJobValue}" ${hasCustomJob ? 'required' : ''}>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group mb-3">
                                    <label>Status Hubungan Anggota ${i} *</label>
                                    <select class="form-control" name="status_hubungan_${i}" required>
                                        <option value="">Pilih Status</option>
                                        <option value="anak" ${existingMember.status_hubungan === 'anak' ? 'selected' : ''}>Anak</option>
                                        <option value="istri" ${existingMember.status_hubungan === 'istri' ? 'selected' : ''}>Istri</option>
                                        <option value="suami" ${existingMember.status_hubungan === 'suami' ? 'selected' : ''}>Suami</option>
                                        <option value="ayah" ${existingMember.status_hubungan === 'ayah' ? 'selected' : ''}>Ayah</option>
                                        <option value="ibu" ${existingMember.status_hubungan === 'ibu' ? 'selected' : ''}>Ibu</option>
                                        <option value="orangtua" ${existingMember.status_hubungan === 'orangtua' ? 'selected' : ''}>Orang Tua</option>
                                        <option value="cucu" ${existingMember.status_hubungan === 'cucu' ? 'selected' : ''}>Cucu</option>
                                        <option value="menantu" ${existingMember.status_hubungan === 'menantu' ? 'selected' : ''}>Menantu</option>
                                        <option value="mertua" ${existingMember.status_hubungan === 'mertua' ? 'selected' : ''}>Mertua</option>
                                        <option value="famililain" ${existingMember.status_hubungan === 'famililain' ? 'selected' : ''}>Famili Lain</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(anggotaDiv);
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize main job field
            toggleCustomJob();
            
            // Generate family member fields
            generateAnggotaKeluarga();
            
            // Check if main user has custom job
            <?php 
            $predefined_jobs = ['PNS', 'TNI', 'Polri', 'Karyawan Swasta', 'Wiraswasta', 'Buruh', 'Ibu Rumah Tangga', 'Pengajar', 'Lainnya'];
            $has_custom_main_job = isset($data['pekerjaan']) && !in_array($data['pekerjaan'], $predefined_jobs);
            ?>
            
            <?php if ($has_custom_main_job): ?>
                // Set main job to custom
                document.getElementById('pekerjaan').value = 'Lainnya';
                document.getElementById('pekerjaan_custom').value = '<?php echo htmlspecialchars($data['pekerjaan']); ?>';
                toggleCustomJob();
            <?php endif; ?>
        });

        // Update when jumlah anggota keluarga changes
        document.getElementById('jumlah_anggota_keluarga').addEventListener('change', generateAnggotaKeluarga);
</script>    
</body>
</html>
