<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Mobile Friendly</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /*========== VARIABLES CSS ==========*/
        :root {
            --header-height: 4rem;
            --nav-width: 280px;
            --nav-width-collapsed: 68px;
            
            /*========== Colors ==========*/
            --first-color: #6923d0;
            --first-color-light: #f4f0fa;
            --first-color-alt: #8b5cf6;
            --title-color: #19181b;
            --text-color: #58555e;
            --text-color-light: #a5a1aa;
            --body-color: #f9f6fd;
            --container-color: #ffffff;
            --border-color: rgba(0, 0, 0, 0.08);
            --shadow-light: 0 2px 8px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 4px 16px rgba(0, 0, 0, 0.1);
            --chat-primary: #4CAF50;
            --chat-primary-dark: #45a049;
            --chat-bg: #ffffff;
            --chat-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            
            /*========== Font and typography ==========*/
            --body-font: "Poppins", sans-serif;
            --normal-font-size: 0.95rem;
            --small-font-size: 0.813rem;
            --smaller-font-size: 0.75rem;
            --h3-font-size: 1.125rem;
            
            /*========== Font weight ==========*/
            --font-light: 300;
            --font-medium: 500;
            --font-semi-bold: 600;
            
            /*========== z index ==========*/
            --z-tooltip: 10;
            --z-fixed: 100;
            --z-modal: 1000;
            --z-chat: 1050;
        }

        @media screen and (max-width: 768px) {
            :root {
                --header-height: 3.5rem;
                --normal-font-size: 0.875rem;
                --small-font-size: 0.75rem;
                --smaller-font-size: 0.688rem;
            }
        }

        /*========== BASE ==========*/
        *, ::before, ::after {
            box-sizing: border-box;
        }

        body {
            margin: var(--header-height) 0 0 0;
            padding: 0.75rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            background-color: var(--body-color);
            color: var(--text-color);
            transition: all 0.3s ease;
            overflow-x: hidden;
        }

        h3 {
            margin: 0;
            font-size: var(--h3-font-size);
            font-weight: var(--font-semi-bold);
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        /*========== MOBILE IMPROVEMENTS ==========*/
        @media screen and (max-width: 768px) {
            body {
                padding: 0.5rem;
                padding-bottom: 1rem;
            }
            
            /* Improve touch targets */
            .nav__link {
                min-height: 48px;
                padding: 0.75rem 1rem;
            }
            
            /* Better spacing */
            .nav__items {
                row-gap: 0.5rem;
            }
            
            /* Larger touch areas */
            .header__toggle {
                min-width: 44px;
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        /*========== HEADER ==========*/
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: var(--container-color);
            box-shadow: var(--shadow-light);
            padding: 0 1rem;
            z-index: var(--z-fixed);
            transition: all 0.3s ease;
        }

        .header__container {
            display: flex;
            align-items: center;
            height: var(--header-height);
            justify-content: space-between;
            max-width: 100%;
        }

        .header__logo {
            color: var(--first-color);
            font-weight: var(--font-semi-bold);
            font-size: 1.1rem;
            display: none;
        }

        .header__toggle {
            color: var(--title-color);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .header__toggle:hover {
            background-color: var(--first-color-light);
            color: var(--first-color);
        }

        .header__toggle i {
            font-size: 1.5rem;
        }

        /* Profile Dropdown - Mobile Optimized */
        .header__profile {
            position: relative;
        }

        .profile__btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: var(--normal-font-size);
            color: var(--title-color);
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            min-height: 44px;
        }

        .profile__btn:hover {
            background-color: var(--first-color-light);
            color: var(--first-color);
        }

        .profile__name {
            margin: 0 0.5rem;
            font-weight: var(--font-medium);
        }

        .profile__dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 0.5rem);
            background: var(--container-color);
            box-shadow: var(--shadow-medium);
            list-style: none;
            padding: 0.5rem 0;
            margin: 0;
            border-radius: 0.75rem;
            display: none;
            min-width: 200px;
            z-index: var(--z-modal);
            border: 1px solid var(--border-color);
        }

        .profile__dropdown li {
            padding: 0;
        }

        .profile__dropdown li a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--text-color);
            transition: all 0.3s ease;
            font-size: var(--small-font-size);
            min-height: 44px;
        }

        .profile__dropdown li a:hover {
            background-color: var(--first-color-light);
            color: var(--first-color);
        }

        .profile__dropdown li a i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /*========== NAV ==========*/
        .nav {
            position: fixed;
            top: 0;
            left: -100%;
            height: 100vh;
            width: var(--nav-width);
            background-color: var(--container-color);
            box-shadow: var(--shadow-medium);
            z-index: var(--z-modal);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border-right: 1px solid var(--border-color);
        }

        .nav__container {
            height: 100%;
            display: flex;
            flex-direction: column;
            padding: 1rem;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--first-color-light) transparent;
        }

        .nav__container::-webkit-scrollbar {
            width: 4px;
        }

        .nav__container::-webkit-scrollbar-track {
            background: transparent;
        }

        .nav__container::-webkit-scrollbar-thumb {
            background: var(--first-color-light);
            border-radius: 2px;
        }

        .nav__container::-webkit-scrollbar-thumb:hover {
            background: var(--first-color);
        }

        .nav__logo {
            display: flex;
            align-items: center;
            font-weight: var(--font-semi-bold);
            margin-bottom: 2rem;
            color: var(--first-color);
            font-size: 1.25rem;
            padding: 0.5rem;
        }

        .nav__logo .nav__icon {
            margin-right: 0.75rem;
            font-size: 1.5rem;
        }

        .nav__list {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .nav__items {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 2rem;
        }

        .nav__subtitle {
            font-size: var(--smaller-font-size);
            text-transform: uppercase;
            letter-spacing: 0.1rem;
            color: var(--text-color-light);
            font-weight: var(--font-semi-bold);
            margin-bottom: 1rem;
            padding: 0 0.5rem;
        }

        .nav__link {
            display: flex;
            align-items: center;
            color: var(--text-color);
            padding: 0.875rem 1rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            font-weight: var(--font-medium);
            position: relative;
            min-height: 48px;
        }

        .nav__link:hover {
            background-color: var(--first-color-light);
            color: var(--first-color);
            transform: translateX(4px);
        }

        .nav__link.active {
            background: linear-gradient(135deg, var(--first-color), var(--first-color-alt));
            color: white;
            box-shadow: var(--shadow-light);
        }

        .nav__link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 20px;
            background: white;
            border-radius: 0 2px 2px 0;
        }

        .nav__icon {
            font-size: 1.25rem;
            margin-right: 0.875rem;
            min-width: 24px;
        }

        .nav__name {
            font-size: var(--small-font-size);
            font-weight: var(--font-medium);
        }

        /*===== Show menu =====*/
        .show-menu {
            left: 0;
        }

        /*========== OVERLAY FOR MOBILE ==========*/
        .nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: calc(var(--z-modal) - 1);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nav-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /*========== MAIN CONTENT ==========*/
        .main__content {
            padding: 1.5rem 0;
            background-color: var(--body-color);
            min-height: calc(100vh - var(--header-height));
            transition: all 0.3s ease;
        }

        .content__card {
            background: var(--container-color);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            margin-bottom: 1rem;
        }

        /*========== LIVE CHAT BUBBLE - MOBILE OPTIMIZED ==========*/
        .chat-bubble {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, var(--chat-primary), var(--chat-primary-dark));
            border-radius: 50%;
            cursor: pointer;
            z-index: var(--z-chat);
            box-shadow: var(--chat-shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: pulse 2s infinite;
        }

        .chat-bubble:hover {
            transform: scale(1.1);
            box-shadow: 0 12px 40px rgba(76, 175, 80, 0.3);
        }

        .chat-bubble .material-icons {
            color: white;
            font-size: 24px;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(76, 175, 80, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(76, 175, 80, 0);
            }
        }

        /* ========== RESPONSIVE DESIGN ==========*/
        @media screen and (max-width: 480px) {
            :root {
                --nav-width: 100vw;
            }
            
            .nav {
                width: 100vw;
            }
            
            .header__container {
                padding: 0 0.5rem;
            }
            
            .profile__name {
                display: none;
            }
            
            .chat-bubble {
                bottom: 15px;
                right: 15px;
                width: 52px;
                height: 52px;
            }
            
            .content__card {
                padding: 1rem;
                margin-bottom: 0.75rem;
            }
        }

        @media screen and (min-width: 769px) {
            body {
                padding: 1rem 3rem 0 calc(var(--nav-width-collapsed) + 3rem);
                transition: padding-left 0.3s ease;
            }

            body.nav-expanded {
                padding-left: calc(var(--nav-width) + 3rem);
            }

            .header {
                padding: 0 3rem 0 calc(var(--nav-width-collapsed) + 3rem);
                transition: padding-left 0.3s ease;
            }

            .header.nav-expanded {
                padding-left: calc(var(--nav-width) + 3rem);
            }

            .header__toggle {
                display: block;
                position: relative;
                z-index: 10;
            }

            .header__logo {
                display: block;
            }

            .nav {
                left: 0;
                width: var(--nav-width-collapsed);
                transition: width 0.3s ease;
            }

            .nav.nav-expanded {
                width: var(--nav-width);
            }

            .nav__logo-name,
            .nav__name,
            .nav__subtitle {
                opacity: 0;
                transition: opacity 0.3s ease;
            }

            .nav.nav-expanded .nav__logo-name,
            .nav.nav-expanded .nav__name,
            .nav.nav-expanded .nav__subtitle {
                opacity: 1;
            }

            .nav-overlay {
                display: none;
            }
        }

        /*========== ADDITIONAL MOBILE TOUCHES ==========*/
        @media screen and (max-width: 768px) {
            /* Improve readability */
            .nav__name {
                font-size: var(--normal-font-size);
            }
            
            /* Add safe area for devices with notch */
            .header {
                padding-top: env(safe-area-inset-top);
                height: calc(var(--header-height) + env(safe-area-inset-top));
            }
            
            body {
                margin-top: calc(var(--header-height) + env(safe-area-inset-top));
            }
            
            /* Prevent horizontal scroll */
            .nav__container {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
            }
            
            /* Improve touch feedback */
            .nav__link:active {
                background-color: var(--first-color-light);
                transform: scale(0.98);
            }
            
            .profile__btn:active,
            .header__toggle:active {
                transform: scale(0.95);
            }
        }
    </style>
</head>
<body>
    <!--========== NAV OVERLAY ==========-->
    <div class="nav-overlay" id="navOverlay"></div>

    <!--========== HEADER ==========-->
    <header class="header" id="header">
        <div class="header__container">
            <div class="header__toggle" id="header-toggle">
                <i class="bx bx-menu"></i>
            </div>

            <a href="#" class="header__logo">Tetangga.id</a>

            <!-- Profile Dropdown -->
            <div class="header__profile">
                <button class="profile__btn" id="profileToggle">
                    <i class="material-icons">account_circle</i>
                    <span class="profile__name">
                        <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Pengguna'; ?>
                    </span>
                    <i class="material-icons">expand_more</i>
                </button>

                <ul class="profile__dropdown" id="profileDropdown">
                    <li><a href="profile.php"><i class="material-icons">person</i> Profil</a></li>
                    <li><a href="pass.php"><i class="material-icons">lock</i> Ganti Password</a></li>
                    <li><a href="../logout.php"><i class="material-icons">logout</i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!--========== NAV ==========-->
    <div class="nav" id="navbar">
        <nav class="nav__container">
            <a href="#" class="nav__logo">
                <i class="material-icons nav__icon">home</i>
                <span class="nav__logo-name">Tetangga.id</span>
            </a>

            <div class="nav__list">
                <div class="nav__items">
                    <h3 class="nav__subtitle">Menu Utama</h3>

                    <a href="dashboard.php" class="nav__link active" data-page="dashboard">
                        <i class="material-icons nav__icon">dashboard</i>
                        <span class="nav__name">Dashboard</span>
                    </a>

                    <a href="income.php" class="nav__link" data-page="keuangan">
                        <i class="material-icons nav__icon">paid</i>
                        <span class="nav__name">Keuangan</span>
                    </a>

                    <a href="tagihan.php" class="nav__link" data-page="tagihan">
                        <i class="material-icons nav__icon">payments</i>
                        <span class="nav__name">Tagihan</span>
                    </a>

                    <a href="kegiatan.php" class="nav__link" data-page="pengumuman">
                        <i class="material-icons nav__icon">campaign</i>
                        <span class="nav__name">Pengumuman</span>
                    </a>

                    <a href="pusat_bantuan.php" class="nav__link" data-page="bantuan">
                        <i class="material-icons nav__icon">help</i>
                        <span class="nav__name">Pusat Bantuan</span>
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!--========== LIVE CHAT BUBBLE ==========-->
    <a href="livechat.php" class="chat-bubble">
        <i class="material-icons">chat</i>
    </a>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const nav = document.getElementById('navbar');
            const navOverlay = document.getElementById('navOverlay');
            const headerToggle = document.getElementById('header-toggle');
            const profileToggle = document.getElementById('profileToggle');
            const profileDropdown = document.getElementById('profileDropdown');
            const header = document.getElementById('header');
            const body = document.body;
            
            // Check if mobile
            function isMobile() {
                return window.innerWidth <= 768;
            }

            /*==================== SHOW/HIDE NAVBAR ====================*/
            function toggleNav() {
                if (isMobile()) {
                    // Mobile behavior - overlay
                    nav.classList.toggle("show-menu");
                    navOverlay.classList.toggle("show");
                    
                    // Prevent body scroll when nav is open
                    if (nav.classList.contains("show-menu")) {
                        document.body.style.overflow = 'hidden';
                    } else {
                        document.body.style.overflow = '';
                    }
                } else {
                    // Desktop behavior - push content
                    nav.classList.toggle("nav-expanded");
                    header.classList.toggle("nav-expanded");
                    body.classList.toggle("nav-expanded");
                }
                
                // Update hamburger icon
                const icon = headerToggle.querySelector('i');
                const isOpen = nav.classList.contains("show-menu") || nav.classList.contains("nav-expanded");
                
                if (isOpen) {
                    icon.classList.remove('bx-menu');
                    icon.classList.add('bx-x');
                } else {
                    icon.classList.remove('bx-x');
                    icon.classList.add('bx-menu');
                }
            }

            function closeNav() {
                if (isMobile()) {
                    nav.classList.remove("show-menu");
                    navOverlay.classList.remove("show");
                    document.body.style.overflow = '';
                } else {
                    nav.classList.remove("nav-expanded");
                    header.classList.remove("nav-expanded");
                    body.classList.remove("nav-expanded");
                }
                
                const icon = headerToggle.querySelector('i');
                icon.classList.remove('bx-x');
                icon.classList.add('bx-menu');
            }

            // Toggle nav on button click
            headerToggle.addEventListener("click", toggleNav);

            // Close nav when clicking overlay (mobile only)
            navOverlay.addEventListener("click", closeNav);

            // Close nav when clicking nav links (mobile only)
            const navLinks = document.querySelectorAll('.nav__link[href]');
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (isMobile()) {
                        closeNav();
                    }
                });
            });

            /*==================== PROFILE DROPDOWN ====================*/
            profileToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const isVisible = profileDropdown.style.display === 'block';
                profileDropdown.style.display = isVisible ? 'none' : 'block';
                
                // Rotate arrow icon
                const arrow = profileToggle.querySelector('i:last-child');
                arrow.style.transform = isVisible ? 'rotate(0deg)' : 'rotate(180deg)';
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.style.display = 'none';
                    const arrow = profileToggle.querySelector('i:last-child');
                    arrow.style.transform = 'rotate(0deg)';
                }
            });

            /*==================== ACTIVE LINK MANAGEMENT ====================*/
            const currentPage = window.location.pathname.split('/').pop();
            const links = document.querySelectorAll('.nav__link[data-page]');
            
            links.forEach(link => {
                link.classList.remove('active');
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                }
            });

            /*==================== SMOOTH SCROLL FOR HASH LINKS ====================*/
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

            /*==================== SWIPE GESTURES FOR MOBILE ====================*/
            if (isMobile()) {
                let startX = 0;
                let currentX = 0;
                let isDragging = false;

                document.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].clientX;
                    isDragging = true;
                });

                document.addEventListener('touchmove', (e) => {
                    if (!isDragging) return;
                    currentX = e.touches[0].clientX;
                });

                document.addEventListener('touchend', (e) => {
                    if (!isDragging) return;
                    isDragging = false;
                    
                    const diffX = currentX - startX;
                    const threshold = 100;
                    
                    // Swipe right to open nav
                    if (diffX > threshold && startX < 50 && !nav.classList.contains('show-menu')) {
                        toggleNav();
                    }
                    // Swipe left to close nav
                    else if (diffX < -threshold && nav.classList.contains('show-menu')) {
                        closeNav();
                    }
                });
            }

            /*==================== KEYBOARD NAVIGATION ====================*/
            document.addEventListener('keydown', (e) => {
                // ESC key closes nav and dropdown
                if (e.key === 'Escape') {
                    closeNav();
                    profileDropdown.style.display = 'none';
                    const arrow = profileToggle.querySelector('i:last-child');
                    arrow.style.transform = 'rotate(0deg)';
                }
            });

            /*==================== WINDOW RESIZE ====================*/
            window.addEventListener('resize', () => {
                if (!isMobile() && nav.classList.contains('show-menu')) {
                    // Convert mobile overlay to desktop expanded
                    nav.classList.remove('show-menu');
                    navOverlay.classList.remove('show');
                    document.body.style.overflow = '';
                    
                    nav.classList.add('nav-expanded');
                    header.classList.add('nav-expanded');
                    body.classList.add('nav-expanded');
                } else if (isMobile() && nav.classList.contains('nav-expanded')) {
                    // Convert desktop expanded to mobile overlay
                    nav.classList.remove('nav-expanded');
                    header.classList.remove('nav-expanded');
                    body.classList.remove('nav-expanded');
                    
                    nav.classList.add('show-menu');
                    navOverlay.classList.add('show');
                    document.body.style.overflow = 'hidden';
                }
            });

            /*==================== IMPROVE ACCESSIBILITY ====================*/
            // Add proper ARIA attributes
            headerToggle.setAttribute('aria-label', 'Toggle navigation menu');
            headerToggle.setAttribute('aria-expanded', 'false');
            
            nav.setAttribute('aria-hidden', 'true');
            
            // Update ARIA attributes when nav toggles
            const originalToggleNav = toggleNav;
            toggleNav = function() {
                originalToggleNav();
                const isOpen = nav.classList.contains('show-menu') || nav.classList.contains('nav-expanded');
                headerToggle.setAttribute('aria-expanded', isOpen);
                nav.setAttribute('aria-hidden', !isOpen);
            };
        });
    </script>
</body>
</html>