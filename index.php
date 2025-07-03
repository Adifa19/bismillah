<?php
require_once 'config.php';

// Get completed activities for homepage
$stmt = $pdo->query("SELECT * FROM kegiatan WHERE status_kegiatan = 'Selesai' ORDER BY tanggal_kegiatan DESC LIMIT 6");
$kegiatan_selesai = $stmt->fetchAll();

// Get upcoming activities
$stmt = $pdo->query("SELECT * FROM kegiatan WHERE status_kegiatan = 'Direncanakan' AND tanggal_kegiatan >= CURDATE() ORDER BY tanggal_kegiatan ASC LIMIT 3");
$kegiatan_mendatang = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website RT - Rukun Tetangga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #f8fafc;
            --accent: #10b981;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            scroll-behavior: smooth;
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            transition: all 0.3s ease;
            padding: 1rem 0;
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98) !important;
            box-shadow: var(--shadow);
            padding: 0.5rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary) !important;
        }

        .nav-link {
            color: var(--text-dark) !important;
            font-weight: 500;
            margin: 0 0.5rem;
            transition: color 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary) !important;
        }

        /* Hero Section */
        .hero-section {
            background: 
                linear-gradient(135deg, rgba(37, 99, 235, 0.4) 0%, rgba(29, 78, 216, 0.4) 100%),
                url('kerjabakti.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }


        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(1px);
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            font-weight: 400;
        }

        .btn-hero {
            background: white;
            color: var(--primary);
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            border: none;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .btn-hero:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--primary);
        }

        /* Cards */
        .card-modern {
            border: none;
            border-radius: 16px;
            transition: all 0.3s ease;
            background: white;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-modern:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .card-modern .card-img-top {
            height: 220px;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .card-modern:hover .card-img-top {
            transform: scale(1.05);
        }

        .card-modern .card-body {
            padding: 1.5rem;
        }

        .badge-modern {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.75rem;
        }

        .badge-upcoming {
            background: linear-gradient(45deg, #f59e0b, #f97316);
            color: white;
        }

        .badge-completed {
            background: linear-gradient(45deg, var(--accent), #059669);
            color: white;
        }

        /* Sections */
        .section-padding {
            padding: 5rem 0;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--text-dark);
        }

        .section-subtitle {
            font-size: 1.125rem;
            color: var(--text-light);
            margin-bottom: 3rem;
        }

        /* Contact Section */
        .contact-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow);
            height: 100%;
        }

        .contact-info-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--secondary);
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .contact-info-item:hover {
            background: #e0f2fe;
            transform: translateX(5px);
        }

        .contact-icon {
            width: 50px;
            height: 50px;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        .pengurus-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .pengurus-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .pengurus-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 1rem;
        }

        /* Floating Button */
        .chat-float {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .btn-float {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #25d366;
            border: none;
            box-shadow: var(--shadow-lg);
            color: white;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .btn-float:hover {
            transform: scale(1.1);
            background: #128c7e;
            color: white;
        }

        /* Modal */
        .modal-content {
            border: none;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            background: linear-gradient(135deg, #25d366, #128c7e);
            border-radius: 16px 16px 0 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .section-padding {
                padding: 3rem 0;
            }
            
            .chat-float {
                bottom: 1rem;
                right: 1rem;
            }
        }

        /* Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--secondary);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#beranda">
                <i class="fas fa-home me-2"></i>Tetangga.id
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#beranda">Beranda</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#tentang">Tentang</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#kegiatan">Kegiatan</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Kontak</a>
                    </li>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Login</a>
                        </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="beranda" class="hero-section text-white">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title animate-fade-in">Selamat Datang di Website RT</h1>
                    <p class="hero-subtitle animate-fade-in">Rukun Tetangga - Membangun Kebersamaan, Mewujudkan Kesejahteraan Bersama</p>
                    <a href="#kegiatan" class="btn btn-hero animate-fade-in">
                        <i class="fas fa-calendar-alt me-2"></i>Lihat Kegiatan
                    </a>
                </div>
            </div>
        </div>
    </section>
<!-- Tentang Section -->
    <section id="tentang" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Sejarah Singkat RT 007 Graha Prima Baru</h2>
                <p class="section-subtitle">
                    Dari kawasan sederhana hingga menjadi komunitas yang solid
                </p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card card-modern">
                        <div class="card-body p-5">
                            <p class="text-muted mb-4 lead">
                                RT 007 adalah salah satu wilayah di Perumahan Graha Prima Baru yang terletak di 
                                <strong>Kelurahan Mangunjaya, Kecamatan Tambun Selatan, Kabupaten Bekasi</strong>. 
                                Kawasan ini mulai dibangun sekitar tahun <strong>2002</strong> sebagai bagian dari 
                                pengembangan hunian di wilayah penyangga ibukota.
                            </p>
                            <p class="text-muted mb-4">
                                Seiring bertambahnya warga yang menetap, RT 007 resmi terbentuk untuk memudahkan 
                                koordinasi dan menciptakan lingkungan yang tertib dan harmonis. Dari awal yang 
                                sederhana, RT 007 tumbuh menjadi komunitas yang aktif melalui berbagai kegiatan 
                                warga seperti kerja bakti, pengajian, ronda, dan perayaan hari besar.
                            </p>
                            <p class="text-muted mb-4">
                                Kini, <strong>RT 007 dikenal sebagai lingkungan yang solid, dan terus 
                                berkembang dalam semangat gotong royong</strong>.
                            </p>
                            
                            <div class="row g-3 justify-content-center">
                                <div class="col-md-3 col-6">
                                    <div class="text-center p-3 rounded-3" style="background: var(--secondary);">
                                        <h4 class="text-primary fw-bold mb-1">2002</h4>
                                        <small class="text-muted">Tahun Dibangun</small>
                                    </div>
                                </div>
                                <div class="col-md-3 col-6">    
                                    <div class="text-center p-3 rounded-3" style="background: var(--secondary);">
                                        <h4 class="text-primary fw-bold mb-1">20+</h4>
                                        <small class="text-muted">Tahun Berdiri</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visi Misi -->
            <div class="row mt-5">
                <div class="col-lg-6 mb-4">
                    <div class="card card-modern h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="contact-icon me-3">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Visi Kami</h5>
                            </div>
                            <p class="text-muted mb-0">
                                Menjadi RT yang mandiri, sejahtera, dan harmonis dengan mengutamakan 
                                gotong royong dan kepedulian terhadap lingkungan.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="card card-modern h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <div class="contact-icon me-3">
                                    <i class="fas fa-bullseye"></i>
                                </div>
                                <h5 class="fw-bold mb-0">Misi Kami</h5>
                            </div>
                            <ul class="text-muted mb-0 ps-3">
                                <li>Membangun komunikasi yang baik antar warga</li>
                                <li>Menciptakan lingkungan yang bersih dan asri</li>
                                <li>Mengadakan kegiatan yang mempererat persaudaraan</li>
                                <li>Meningkatkan keamanan dan kenyamanan lingkungan</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- Kegiatan Mendatang -->
    <?php if (!empty($kegiatan_mendatang)): ?>
    <section class="section-padding" style="background: var(--secondary);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Kegiatan Mendatang</h2>
                <p class="section-subtitle">Jangan lewatkan kegiatan-kegiatan menarik di RT kita</p>
            </div>
            <div class="row">
                <?php foreach ($kegiatan_mendatang as $kegiatan): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card card-modern h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold text-primary"><?= sanitize($kegiatan['judul']) ?></h5>
                                    <span class="badge badge-modern badge-upcoming">Akan Datang</span>
                                </div>
                                <p class="text-muted mb-3"><?= sanitize($kegiatan['deskripsi']) ?></p>
                                <div class="d-flex align-items-center mb-2 text-muted">
                                    <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                    <small><?= sanitize($kegiatan['hari']) ?>, <?= date('d F Y', strtotime($kegiatan['tanggal_kegiatan'])) ?></small>
                                </div>
                                <div class="d-flex align-items-center text-muted">
                                    <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                    <small><?= sanitize($kegiatan['alamat']) ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Kegiatan Section -->
    <section id="kegiatan" class="section-padding">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Kegiatan Yang Telah Dilaksanakan</h2>
                <p class="section-subtitle">Dokumentasi berbagai kegiatan RT yang telah berhasil dilaksanakan</p>
            </div>
            
            <?php if (!empty($kegiatan_selesai)): ?>
                <div class="row">
                    <?php foreach ($kegiatan_selesai as $kegiatan): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card card-modern h-100">
                                <?php if ($kegiatan['foto_kegiatan']): ?>
                                    <img src="uploads/kegiatan/<?= sanitize($kegiatan['foto_kegiatan']) ?>" 
                                         class="card-img-top" 
                                         alt="<?= sanitize($kegiatan['judul']) ?>">
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center bg-primary text-white" style="height: 220px;">
                                        <i class="fas fa-calendar-check fa-3x"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="fw-bold"><?= sanitize($kegiatan['judul']) ?></h5>
                                        <span class="badge badge-modern badge-completed">Selesai</span>
                                    </div>
                                    <p class="text-muted mb-3"><?= sanitize($kegiatan['deskripsi']) ?></p>
                                    <div class="d-flex align-items-center mb-2 text-muted">
                                        <i class="fas fa-calendar-alt me-2 text-primary"></i>
                                        <small><?= sanitize($kegiatan['hari']) ?>, <?= date('d F Y', strtotime($kegiatan['tanggal_kegiatan'])) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center mb-3 text-muted">
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                        <small><?= sanitize($kegiatan['alamat']) ?></small>
                                    </div>
                                    
                                    <?php if ($kegiatan['dokumentasi_link']): ?>
                                        <a href="<?= sanitize($kegiatan['dokumentasi_link']) ?>" 
                                           target="_blank" 
                                           class="btn btn-outline-primary btn-sm rounded-pill">
                                            <i class="fas fa-external-link-alt me-1"></i>Lihat Dokumentasi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h5>Belum ada kegiatan yang selesai</h5>
                    <p>Kegiatan yang telah dilaksanakan akan ditampilkan di sini</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section-padding" style="background: var(--secondary);">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Hubungi Kami</h2>
                <p class="section-subtitle">Jangan ragu untuk menghubungi pengurus RT</p>
            </div>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <div class="contact-card">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-map-marked-alt text-primary me-2"></i>Lokasi RT
                        </h5>
                        
                        <div class="mb-4" style="border-radius: 12px; overflow: hidden;">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3966.2316375179466!2d107.06086845503033!3d-6.233165929697967!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e698f2419cf2083%3A0xcada4f2e6f48823!2sNew%20Prima%20Graha!5e0!3m2!1sen!2sid!4v1750133624234!5m2!1sen!2sid" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <div class="contact-card">
                        <h5 class="fw-bold mb-4">
                            <i class="fas fa-users text-primary me-2"></i>Kontak Kami
                        </h5>
                        
                        <div class="pengurus-item">
                            <div class="d-flex align-items-center">
                                <div class="pengurus-avatar" style="background: var(--primary);">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Alamat</div>
                                    <div class="text-primary">
                                        Graha Prima Baru Blok Lili RT 007/ RW 020
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pengurus-item">
                            <div class="d-flex align-items-center">
                                <div class="pengurus-avatar" style="background: var(--accent);">
                                    <i class="fas fa-phone"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Telepon</div>
                                    <div class="text-primary">
                                        0812-3456-7891
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="pengurus-item">
                            <div class="d-flex align-items-center">
                                <div class="pengurus-avatar" style="background: #f59e0b;">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">Email</div>
                                    <div class="text-primary">
                                     Tetangga.id@gmail.com
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating Chat Button -->
    <div class="chat-float">
        <button class="btn btn-float" data-bs-toggle="modal" data-bs-target="#chatModal">
            <i class="fab fa-whatsapp"></i>
        </button>
    </div>

    <!-- Chat Modal -->
    <div class="modal fade" id="chatModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fab fa-whatsapp me-2"></i>Chat dengan Admin
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="mb-4">Pilih cara untuk menghubungi admin:</p>
                    
                    <div class="d-grid gap-3">
                        <a href="https://wa.me/6281234567890?text=Halo%20admin%2C%20saya%20ingin%20bertanya%20tentang%20kegiatan%20RT" 
                           target="_blank" 
                           class="btn btn-success btn-lg rounded-pill">
                            <i class="fab fa-whatsapp me-2"></i>Chat via WhatsApp
                        </a>
                        
                        <a href="tel:+6281234567890" class="btn btn-outline-success btn-lg rounded-pill">
                            <i class="fas fa-phone me-2"></i>Telepon Admin
                        </a>
                        
                        <a href="mailto:admin@rt.com?subject=Pertanyaan%20dari%20Website" class="btn btn-outline-primary btn-lg rounded-pill">
                            <i class="fas fa-envelope me-2"></i>Kirim Email
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);

        // Observe cards for animation
        document.querySelectorAll('.card-modern').forEach(card => {
            observer.observe(card);
        });

        // Floating button pulse effect
        setInterval(() => {
            const btn = document.querySelector('.btn-float');
            btn.style.transform = 'scale(1.1)';
            setTimeout(() => {
                btn.style.transform = 'scale(1)';
            }, 200);
        }, 3000);
    </script>
</body>
</html>