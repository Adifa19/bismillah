<?php
require_once '../config.php';
requireLogin();

// Redirect admin ke dashboard admin
if (isAdmin()) {
    header('Location: admin.php');
    exit;
}

// Redirect jika belum lengkap data
if (!$_SESSION['data_lengkap']) {
    header('Location: profile.php');
    exit;
}

// Ambil data user dan pendataan
$stmt = $pdo->prepare("
    SELECT u.username, u.no_kk, p.*, 
           (SELECT COUNT(*) FROM anggota_keluarga WHERE pendataan_id = p.id) as total_anggota
    FROM users u 
    LEFT JOIN pendataan p ON u.id = p.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$data = $stmt->fetch();

// Ambil data anggota keluarga
$stmt = $pdo->prepare("SELECT * FROM anggota_keluarga WHERE pendataan_id = ? ORDER BY status_hubungan");
$stmt->execute([$data['id']]);
$anggota_keluarga = $stmt->fetchAll();

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    
    $timestamp = strtotime($tanggal);
    $hari = date('j', $timestamp);
    $bulan_num = date('n', $timestamp);
    $tahun = date('Y', $timestamp);
    
    return $hari . ' ' . $bulan[$bulan_num] . ' ' . $tahun;
}

var_dump($data['foto_ktp']);
var_dump($data['foto_kk']);
exit;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Pendataan</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-brand {
            font-size: 24px;
            font-weight: bold;
        }
        
        .nav-user {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-user span {
            font-size: 16px;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .welcome-card h1 {
            color: #333;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            color: #666;
            font-size: 18px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-card.success {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        
        .stat-card.info {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .stat-label {
            font-size: 16px;
            opacity: 0.9;
        }
        
        /* Style untuk tombol edit profile */
        .edit-profile-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .edit-profile-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        
        .edit-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
            background: linear-gradient(135deg, #ff5252 0%, #e91e63 100%);
        }
        
        .edit-profile-btn:active {
            transform: translateY(0);
        }
        
        .edit-icon {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        
        .data-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .data-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .card-title {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .data-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .data-item:last-child {
            border-bottom: none;
        }
        
        .data-label {
            font-weight: bold;
            color: #333;
        }
        
        .data-value {
            color: #666;
        }
        
        .family-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }
        
        .family-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .family-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .family-name {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            margin-bottom: 8px;
        }
        
        .family-info {
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .family-info .label {
            font-weight: 600;
            color: #555;
            min-width: 65px;
            font-size: 13px;
        }
        
        .family-info .value {
            color: #666;
            font-size: 14px;
        }
        
        .family-status {
            background: #667eea;
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            text-transform: capitalize;
            margin-top: 10px;
        }
        
        .gender-male {
            color: #2196F3;
            font-weight: 600;
        }
        
        .gender-female {
            color: #E91E63;
            font-weight: 600;
        }
        
        .photo-section {
            grid-column: 1 / -1;
        }
        
        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .photo-card {
            text-align: center;
        }
        
        .photo-card img {
            max-width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 5px;
            border: 2px solid #ddd;
        }
        
        .photo-label {
            margin-top: 10px;
            font-weight: bold;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .data-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .family-grid {
                grid-template-columns: 1fr;
            }
            
            .photo-grid {
                grid-template-columns: 1fr;
            }
            
            .edit-profile-btn {
                padding: 12px 24px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>    
    <div class="container">
        <div class="welcome-card">
            <h1>Profile Warga</h1>
            <p>Berikut adalah informasi lengkap data Anda dalam sistem</p>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-number"><?php echo $data['total_anggota']; ?></div>
                <div class="stat-label">Anggota Keluarga</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-number"><?php echo $data['status_warga']; ?></div>
                <div class="stat-label">Status Warga</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-number"><?php echo substr($data['no_kk'], -4); ?></div>
                <div class="stat-label">Nomor KK (4 digit terakhir)</div>
            </div>
        </div>
        
        <!-- Tombol Edit Profile -->
        <div class="edit-profile-section">
            <a href="data_awal.php" class="edit-profile-btn">
                <svg class="edit-icon" viewBox="0 0 24 24">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                </svg>
                Edit Profile
            </a>
        </div>
        
        <div class="data-section">
            <div class="data-card">
                <div class="card-title">Data Pribadi</div>
                
                <div class="data-item">
                    <span class="data-label">NIK:</span>
                    <span class="data-value"><?php echo $data['nik']; ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Nama Lengkap:</span>
                    <span class="data-value"><?php echo htmlspecialchars($data['nama_lengkap']); ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Tanggal Lahir:</span>
                    <span class="data-value"><?php echo date('d/m/Y', strtotime($data['tanggal_lahir'])); ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Jenis Kelamin:</span>
                    <span class="data-value"><?php echo $data['jenis_kelamin']; ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Pekerjaan  :</span>
                    <span class="data-value"><?php echo $data['pekerjaan']; ?></span>
                </div>
            </div>
            
            <div class="data-card">
                <div class="card-title">Data Kontak</div>
                
                <div class="data-item">
                    <span class="data-label">Nomor KK:</span>
                    <span class="data-value"><?php echo $data['no_kk']; ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Alamat:</span>
                    <span class="data-value"><?php echo htmlspecialchars($data['alamat']); ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">No. Telepon:</span>
                    <span class="data-value"><?php echo htmlspecialchars($data['no_telp']); ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Username:</span>
                    <span class="data-value"><?php echo htmlspecialchars($data['username']); ?></span>
                </div>
                
                <div class="data-item">
                    <span class="data-label">Terdaftar:</span>
                    <span class="data-value"><?php echo date('d/m/Y', strtotime($data['created_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <?php if (!empty($anggota_keluarga)): ?>
        <div class="data-card">
            <div class="card-title">Anggota Keluarga</div> <br>
            <div class="family-grid"> 
                <?php foreach ($anggota_keluarga as $anggota): ?>
                <div class="family-card">
                    <div class="family-name">
                        <?php echo htmlspecialchars($anggota['nama_lengkap']); ?>
                    </div>
                    
                    <div class="family-info">
                        <span class="label">NIK:</span>
                        <span class="value"><?php echo htmlspecialchars($anggota['nik']); ?></span>
                    </div>
                    
                    <div class="family-info">
                        <span class="label">Tanggal Lahir:</span>
                        <span class="value"><?php echo formatTanggalIndonesia($anggota['tanggal_lahir']); ?></span>
                    </div>
                    
                    <div class="family-info">
                        <span class="label">Jenis Kelamin:</span>
                        <span class="value <?php echo strtolower($anggota['jenis_kelamin']) == 'laki-laki' ? 'gender-male' : 'gender-female'; ?>">
                            <?php echo htmlspecialchars($anggota['jenis_kelamin']); ?>
                        </span>
                    </div>
                    
                    <div class="family-info">
                        <span class="label">Pekerjaan:</span>
                        <span class="value"><?php echo htmlspecialchars($anggota['pekerjaan']); ?></span>
                    </div>
                    
                    <span class="family-status">
                        <?php echo ucfirst(str_replace('_', ' ', $anggota['status_hubungan'])); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
         <?php if ($data['foto_ktp'] || $data['foto_kk']): ?>
        <div class="data-card photo-section">
            <div class="card-title">Dokumen</div> <br>
            
            <div class="photo-grid">
                            <?php if ($data['foto_ktp']): ?>
                    <img src="/uploads/<?php echo rawurlencode(basename($data['foto_ktp'])); ?>" alt="Foto KTP">
                <?php endif; ?>

                <?php if ($data['foto_kk']): ?>
                    <img src="/uploads/<?php echo rawurlencode(basename($data['foto_kk'])); ?>" alt="Foto KK">
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>




