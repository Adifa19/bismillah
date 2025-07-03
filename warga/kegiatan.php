<?php
require_once '../config.php';
requireLogin();

// Get upcoming activities for logged-in users
$stmt = $pdo->query("SELECT * FROM kegiatan WHERE status_kegiatan = 'Direncanakan' AND tanggal_kegiatan >= CURDATE() ORDER BY tanggal_kegiatan ASC");
$kegiatan_mendatang = $stmt->fetchAll();

// Get completed activities
$stmt = $pdo->query("SELECT * FROM kegiatan WHERE status_kegiatan = 'Selesai' ORDER BY tanggal_kegiatan DESC LIMIT 10");
$kegiatan_selesai = $stmt->fetchAll();

// Get user info (assuming you have a users table)
$user_name = $_SESSION['username'] ?? 'Warga';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Warga - RT Website</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .welcome-section {
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
            color: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.15);
        }

        .welcome-section h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .stat-card.upcoming .icon {
            background: #dbeafe;
            color: #3b82f6;
        }

        .stat-card.completed .icon {
            background: #dcfce7;
            color: #22c55e;
        }

        .stat-card.time .icon {
            background: #fef3c7;
            color: #f59e0b;
        }

        .stat-card h3 {
            font-size: 1.875rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: #1e293b;
        }

        .stat-card p {
            color: #64748b;
            margin: 0;
        }

        .activities-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .activity-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid #e2e8f0;
        }

        .activity-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .activity-header.upcoming {
            background: linear-gradient(135deg, #3b82f6 0%, #6366f1 100%);
            color: white;
        }

        .activity-header.completed {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .activity-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
        }

        .activity-body {
            padding: 1.5rem;
            max-height: 500px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 3px solid #e2e8f0;
            background: #f8fafc;
            transition: all 0.2s ease;
        }

        .activity-item:hover {
            background: #f1f5f9;
            border-left-color: #6366f1;
        }

        .activity-item:last-child {
            margin-bottom: 0;
        }

        .activity-item h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1e293b;
        }

        .activity-item p {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.8rem;
            color: #64748b;
        }

        .activity-meta span {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .badge {
            background: #e2e8f0;
            color: #475569;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge.upcoming {
            background: #dbeafe;
            color: #3b82f6;
        }

        .badge.completed {
            background: #dcfce7;
            color: #22c55e;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .doc-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #6366f1;
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 0.5rem;
            transition: color 0.2s ease;
        }

        .doc-link:hover {
            color: #4f46e5;
        }

        .activity-body::-webkit-scrollbar {
            width: 4px;
        }

        .activity-body::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .activity-body::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }

        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }

            .activities-grid {
                grid-template-columns: 1fr;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }

            .activity-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0; 
                transform: translateY(20px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            font-size: 2.5em;
            margin: 0 0 10px 0;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .page-header p {
            font-size: 1.2em;
            margin: 0;
            opacity: 0.9;
            font-weight: 300;
        }

        /* Responsive untuk mobile */
        @media (max-width: 767px) {
            .page-header {
                margin-bottom: 20px;
                padding: 15px 10px;
            }
            
            .page-header h1 {
                font-size: 2em;
            }
            
            .page-header p {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    
    <div class="main-container">
        <div class="page-header">
            <h1>Kegiatan Warga</h1>
            <p>Dashboard informasi kegiatan RT terbaru dan terkini</p>
            </div>
        <!-- Statistics -->
        <div class="stats-grid fade-in">
            <div class="stat-card upcoming">
                <div class="icon">
                    <i class="fas fa-calendar-plus"></i>
                </div>
                <h3><?= count($kegiatan_mendatang) ?></h3>
                <p>Kegiatan Mendatang</p>
            </div>
            
            <div class="stat-card completed">
                <div class="icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?= count($kegiatan_selesai) ?></h3>
                <p>Kegiatan Selesai</p>
            </div>
            
            <div class="stat-card time">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 id="current-time"><?= date('H:i') ?></h3>
                <p>Waktu Sekarang</p>
            </div>
        </div>

        <!-- Activities -->
        <div class="activities-grid fade-in">
            <!-- Upcoming Activities -->
            <div class="activity-section">
                <div class="activity-header upcoming">
                    <h2><i class="fas fa-calendar-alt me-2"></i>Kegiatan Mendatang</h2>
                </div>
                <div class="activity-body">
                    <?php if (!empty($kegiatan_mendatang)): ?>
                        <?php foreach ($kegiatan_mendatang as $kegiatan): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3><?= sanitize($kegiatan['judul']) ?></h3>
                                    <span class="badge upcoming">
                                        <?php 
                                        $days = floor((strtotime($kegiatan['tanggal_kegiatan']) - time()) / (60*60*24));
                                        echo $days == 0 ? 'Hari ini' : $days . ' hari lagi';
                                        ?>
                                    </span>
                                </div>
                                <p><?= sanitize($kegiatan['deskripsi']) ?></p>
                                <div class="activity-meta">
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= sanitize($kegiatan['hari']) ?>, <?= date('d/m/Y', strtotime($kegiatan['tanggal_kegiatan'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= sanitize($kegiatan['alamat']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <h4>Tidak ada kegiatan mendatang</h4>
                            <p>Kegiatan yang dijadwalkan akan muncul di sini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Activities -->
            <div class="activity-section">
                <div class="activity-header completed">
                    <h2><i class="fas fa-check-circle me-2"></i>Kegiatan Selesai</h2>
                </div>
                <div class="activity-body">
                    <?php if (!empty($kegiatan_selesai)): ?>
                        <?php foreach ($kegiatan_selesai as $kegiatan): ?>
                            <div class="activity-item">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h3><?= sanitize($kegiatan['judul']) ?></h3>
                                    <span class="badge completed">Selesai</span>
                                </div>
                                <p><?= sanitize($kegiatan['deskripsi']) ?></p>
                                <div class="activity-meta">
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= sanitize($kegiatan['hari']) ?>, <?= date('d/m/Y', strtotime($kegiatan['tanggal_kegiatan'])) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <?= sanitize($kegiatan['alamat']) ?>
                                    </span>
                                </div>
                                <?php if ($kegiatan['dokumentasi_link']): ?>
                                    <a href="<?= sanitize($kegiatan['dokumentasi_link']) ?>" 
                                       target="_blank" 
                                       class="doc-link">
                                        <i class="fas fa-external-link-alt"></i>
                                        Lihat Dokumentasi
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-check"></i>
                            <h4>Belum ada kegiatan selesai</h4>
                            <p>Kegiatan yang telah dilaksanakan akan muncul di sini</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update time every second
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('current-time').textContent = timeString;
        }
        
        setInterval(updateTime, 1000);

        // Simple fade in animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.fade-in');
            elements.forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>