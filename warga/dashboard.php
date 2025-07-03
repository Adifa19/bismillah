<?php
require_once '../config.php';
requireLogin();

// Ambil data kegiatan yang sudah selesai
try {
    $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE status_kegiatan = 'Selesai' ORDER BY tanggal_kegiatan DESC");
    $stmt->execute();
    $kegiatan_selesai = $stmt->fetchAll();
} catch(PDOException $e) {
    $kegiatan_selesai = [];
}

// Ambil data kegiatan yang akan datang
try {
    $stmt = $pdo->prepare("SELECT * FROM kegiatan WHERE status_kegiatan = 'Direncanakan' ORDER BY tanggal_kegiatan ASC LIMIT 3");
    $stmt->execute();
    $kegiatan_mendatang = $stmt->fetchAll();
} catch(PDOException $e) {
    $kegiatan_mendatang = [];
}

// Fungsi untuk format tanggal Indonesia
function formatTanggalIndonesia($tanggal) {
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $split = explode('-', $tanggal);
    return $split[2] . ' ' . $bulan[(int)$split[1]] . ' ' . $split[0];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Warga</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --warning-gradient: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            --card-bg: rgba(255, 255, 255, 0.1);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --text-primary: #2d3748;
            --text-secondary: #4a5568;
            --shadow-soft: rgba(0, 0, 0, 0.1);
            --border-radius: 20px;
            --animation-speed: 0.3s;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Header dengan efek glassmorphism */
        .page-header {
            background: var(--primary-gradient);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px var(--shadow-soft);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.1), transparent);
            transform: rotate(45deg);
            animation: shimmer 3s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }

        .page-header h1 {
            color: white;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 800;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            text-shadow: 0 4px 20px rgba(0,0,0,0.3);
            position: relative;
            z-index: 2;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.2rem;
            font-weight: 500;
            position: relative;
            z-index: 2;
        }

        /* Menu Grid dengan hover effects */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 3rem;
        }

        .menu-item {
            background: white;
            border: 2px solid #f1f5f9;
            padding: 2rem 1.5rem;
            border-radius: var(--border-radius);
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            box-shadow: 0 10px 30px var(--shadow-soft);
            transition: all var(--animation-speed) cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .menu-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s ease;
        }

        .menu-item:hover::before {
            left: 100%;
        }

        .menu-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .menu-icon {
            font-size: 2.5em;
            margin-bottom: 1rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .menu-item h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .menu-item p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Activity sections dengan improved cards */
        .kegiatan-section {
            background: white;
            border: 2px solid #f1f5f9;
            padding: 2.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px var(--shadow-soft);
        }

        .section-title {
            font-size: 2.2rem;
            color: var(--text-primary);
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 700;
        }

        .section-title i {
            margin-right: 1rem;
            background: var(--warning-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Carousel improvements */
        .carousel-container {
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .carousel-wrapper {
            display: flex;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide {
            min-width: 100%;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            padding: 2rem;
            border-radius: var(--border-radius);
            margin-right: 10px;
        }

        .kegiatan-card {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .kegiatan-image {
            width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            transition: transform var(--animation-speed) ease;
        }

        .kegiatan-image:hover {
            transform: scale(1.05);
        }

        .kegiatan-content {
            flex: 1;
            color: var(--text-primary);
        }

        .kegiatan-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .kegiatan-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .kegiatan-meta i {
            margin-right: 0.5rem;
            color: #667eea;
        }

        .kegiatan-description {
            color: var(--text-secondary);
            line-height: 1.6;
            margin-bottom: 1.5rem;
            font-size: 1rem;
        }

        .dokumentasi-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--secondary-gradient);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all var(--animation-speed) ease;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }

        .dokumentasi-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.3);
        }

        /* Carousel controls */
        .carousel-controls {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .control-btn {
            background: white;
            border: 2px solid #e2e8f0;
            color: var(--text-primary);
            padding: 1rem;
            border-radius: 50%;
            cursor: pointer;
            transition: all var(--animation-speed) ease;
            font-size: 1.2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .control-btn:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: scale(1.1);
        }

        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #cbd5e1;
            cursor: pointer;
            transition: all var(--animation-speed) ease;
        }

        .dot.active {
            background: #667eea;
            transform: scale(1.3);
            box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);
        }

        /* Upcoming events cards */
        .upcoming-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .upcoming-card {
            background: white;
            border: 2px solid #f1f5f9;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px var(--shadow-soft);
            transition: all var(--animation-speed) ease;
            position: relative;
            overflow: hidden;
        }

        .upcoming-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--success-gradient);
        }

        .upcoming-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: #667eea;
        }

        .upcoming-card h4 {
            color: var(--text-primary);
            margin-bottom: 1rem;
            font-size: 1.3rem;
            font-weight: 600;
        }

        .upcoming-card .meta {
            color: var(--text-secondary);
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
        }

        .upcoming-card .meta i {
            margin-right: 0.8rem;
            color: #667eea;
            width: 16px;
        }

        .upcoming-card p {
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .no-data {
            text-align: center;
            color: var(--text-secondary);
            font-style: italic;
            padding: 3rem;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .menu-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .menu-item {
                padding: 1.5rem 1rem;
            }
            
            .menu-icon {
                font-size: 2rem;
            }
            
            .kegiatan-card {
                flex-direction: column;
                text-align: center;
            }
            
            .kegiatan-image {
                width: 100%;
                max-width: 300px;
            }
            
            .kegiatan-meta {
                justify-content: center;
            }
            
            .upcoming-grid {
                grid-template-columns: 1fr;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .menu-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .kegiatan-section {
                padding: 1.5rem;
            }
        }

        /* Loading animation */
        .loading {
            opacity: 0;
            animation: fadeIn 0.6s ease-in-out forwards;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Scroll animations */
        .scroll-reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s ease;
        }

        .scroll-reveal.revealed {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>  
    <div class="container">
        <div class="page-header loading">
            <h1><i class="fas fa-home"></i> Dashboard Warga</h1>
            <p>Selamat datang, <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengguna'; ?>! ✨</p>
        </div>

        <!-- Menu Grid -->
        <div class="menu-grid loading">
            <a href="profile.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-user-circle"></i></div>
                <h3>Profile</h3>
                <p>Kelola informasi pribadi Anda</p>
            </a>

            <a href="tagihan.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-file-invoice-dollar"></i></div>
                <h3>Tagihan</h3>
                <p>Lihat tagihan yang harus dibayar</p>
            </a>

            <a href="riwayat.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-history"></i></div>
                <h3>Riwayat Pembayaran</h3>
                <p>Lihat riwayat pembayaran Anda</p>
            </a>

            <a href="income.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-arrow-trend-up"></i></div>
                <h3>Pemasukan</h3>
                <p>Lihat data pemasukan RT</p>
            </a>

            <a href="pengeluaran.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-arrow-trend-down"></i></div>
                <h3>Pengeluaran</h3>
                <p>Lihat data pengeluaran RT</p>
            </a>

            <a href="pusat_bantuan.php" class="menu-item">
                <div class="menu-icon"><i class="fas fa-comments"></i></div>
                <h3>Pusat Bantuan</h3>
                <p>Perbaiki masalah yang terjadi</p>
            </a>
        </div>

        <!-- Section Kegiatan Selesai -->
        <div class="kegiatan-section scroll-reveal">
            <h2 class="section-title"><i class="fas fa-calendar-check"></i> Kegiatan yang Telah Selesai</h2>
            
            <?php if (count($kegiatan_selesai) > 0): ?>
                <div class="carousel-container">
                    <div class="carousel-wrapper" id="carouselWrapper">
                        <?php foreach ($kegiatan_selesai as $index => $kegiatan): ?>
                            <div class="carousel-slide">
                                <div class="kegiatan-card">
                                    <?php if ($kegiatan['foto_kegiatan']): ?>
                                        <img src="../uploads/kegiatan/<?php echo sanitize($kegiatan['foto_kegiatan']); ?>" 
                                             alt="<?php echo sanitize($kegiatan['judul']); ?>" 
                                             class="kegiatan-image">
                                    <?php else: ?>
                                        <div class="kegiatan-image" style="background: var(--primary-gradient); display: flex; align-items: center; justify-content: center; color: white; font-size: 3em;">
                                            <i class="fas fa-calendar-alt"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="kegiatan-content">
                                        <h3 class="kegiatan-title"><?php echo sanitize($kegiatan['judul']); ?></h3>
                                        <div class="kegiatan-meta">
                                            <span><i class="fas fa-calendar"></i> <?php echo sanitize($kegiatan['hari']); ?>, <?php echo formatTanggalIndonesia($kegiatan['tanggal_kegiatan']); ?></span>
                                            <span><i class="fas fa-map-marker-alt"></i> <?php echo sanitize($kegiatan['alamat']); ?></span>
                                        </div>
                                        <p class="kegiatan-description"><?php echo nl2br(sanitize($kegiatan['deskripsi'])); ?></p>
                                        
                                        <?php if ($kegiatan['dokumentasi_link']): ?>
                                            <a href="<?php echo sanitize($kegiatan['dokumentasi_link']); ?>" 
                                               target="_blank" class="dokumentasi-link">
                                                <i class="fas fa-camera"></i> Lihat Dokumentasi
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if (count($kegiatan_selesai) > 1): ?>
                <div class="carousel-controls">
                    <button class="control-btn" id="prevBtn">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="control-btn" id="nextBtn">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <div class="carousel-dots" id="carouselDots">
                    <?php for ($i = 0; $i < count($kegiatan_selesai); $i++): ?>
                        <span class="dot <?php echo $i === 0 ? 'active' : ''; ?>" onclick="currentSlide(<?php echo $i + 1; ?>)"></span>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-times"></i>
                    <p>Belum ada kegiatan yang selesai</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Section Kegiatan Mendatang -->
        <div class="kegiatan-section scroll-reveal">
            <h2 class="section-title"><i class="fas fa-calendar-plus"></i> Kegiatan Mendatang</h2>
            
            <?php if (count($kegiatan_mendatang) > 0): ?>
                <div class="upcoming-grid">
                    <?php foreach ($kegiatan_mendatang as $kegiatan): ?>
                        <div class="upcoming-card">
                            <h4><?php echo sanitize($kegiatan['judul']); ?></h4>
                            <p class="meta">
                                <i class="fas fa-calendar"></i> <?php echo sanitize($kegiatan['hari']); ?>, <?php echo formatTanggalIndonesia($kegiatan['tanggal_kegiatan']); ?>
                            </p>
                            <p class="meta">
                                <i class="fas fa-map-marker-alt"></i> <?php echo sanitize($kegiatan['alamat']); ?>
                            </p>
                            <p><?php echo nl2br(sanitize(substr($kegiatan['deskripsi'], 0, 100))); ?><?php echo strlen($kegiatan['deskripsi']) > 100 ? '...' : ''; ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-calendar-plus"></i>
                    <p>Belum ada kegiatan yang direncanakan</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Loading animations
        document.addEventListener('DOMContentLoaded', function() {
            const loadingElements = document.querySelectorAll('.loading');
            loadingElements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 200);
            });

            // Scroll reveal animation
            const revealElements = document.querySelectorAll('.scroll-reveal');
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                    }
                });
            }, { threshold: 0.1 });

            revealElements.forEach(el => revealObserver.observe(el));
        });

        // Carousel functionality
        let currentSlideIndex = 0;
        const totalSlides = <?php echo count($kegiatan_selesai); ?>;
        
        function showSlide(index) {
            if (totalSlides <= 1) return;
            
            const wrapper = document.getElementById('carouselWrapper');
            const dots = document.querySelectorAll('.dot');
            
            if (index >= totalSlides) {
                currentSlideIndex = 0;
            } else if (index < 0) {
                currentSlideIndex = totalSlides - 1;
            } else {
                currentSlideIndex = index;
            }
            
            wrapper.style.transform = `translateX(-${currentSlideIndex * 100}%)`;
            
            // Update dots
            dots.forEach((dot, i) => {
                dot.classList.toggle('active', i === currentSlideIndex);
            });
        }
        
        function nextSlide() {
            showSlide(currentSlideIndex + 1);
        }
        
        function prevSlide() {
            showSlide(currentSlideIndex - 1);
        }
        
        function currentSlide(index) {
            showSlide(index - 1);
        }
        
        // Event listeners
        const nextBtn = document.getElementById('nextBtn');
        const prevBtn = document.getElementById('prevBtn');
        
        if (nextBtn) nextBtn.addEventListener('click', nextSlide);
        if (prevBtn) prevBtn.addEventListener('click', prevSlide);
        
        // Auto slide every 5 seconds
        <?php if (count($kegiatan_selesai) > 1): ?>
        setInterval(nextSlide, 5000);
        <?php endif; ?>
        
        // Touch/swipe support for mobile
        let startX = 0;
        let endX = 0;
        
        const carousel = document.getElementById('carouselWrapper');
        
        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                startX = e.touches[0].clientX;
            });
            
            carousel.addEventListener('touchend', (e) => {
                endX = e.changedTouches[0].clientX;
                handleSwipe();
            });
            
            function handleSwipe() {
                const swipeThreshold = 50;
                const swipeDistance = startX - endX;
                
                if (Math.abs(swipeDistance) > swipeThreshold) {
                    if (swipeDistance > 0) {
                        nextSlide();
                    } else {
                        prevSlide();
                    }
                }
            }
        }

        // Add smooth scrolling
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
    </script>
</body>
</html>