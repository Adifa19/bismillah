<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.0.7/css/boxicons.min.css' rel='stylesheet'>
    <style>
        /*========== VARIABLES CSS ==========*/
        :root {
            --header-height: 3.5rem;
            --nav-width: 219px;
            --nav-width-collapsed: 68px;
            
            /*========== Colors ==========*/
            --first-color: #6923d0;
            --first-color-light: #f4f0fa;
            --title-color: #19181b;
            --text-color: #58555e;
            --text-color-light: #a5a1aa;
            --body-color: #f9f6fd;
            --container-color: #ffffff;
            
            /*========== Font and typography ==========*/
            --body-font: "Poppins", sans-serif;
            --normal-font-size: 0.938rem;
            --small-font-size: 0.75rem;
            --smaller-font-size: 0.75rem;
            
            /*========== Font weight ==========*/
            --font-medium: 500;
            --font-semi-bold: 600;
            
            /*========== z index ==========*/
            --z-fixed: 100;
        }

        @media screen and (min-width: 1024px) {
            :root {
                --normal-font-size: 1rem;
                --small-font-size: 0.875rem;
                --smaller-font-size: 0.813rem;
            }
        }

        /*========== BASE ==========*/
        *, ::before, ::after {
            box-sizing: border-box;
        }

        body {
            margin: var(--header-height) 0 0 0;
            padding: 1rem 1rem 0;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            background-color: var(--body-color);
            color: var(--text-color);
            transition: padding 0.4s ease;
        }

        h3 {
            margin: 0;
        }

        a {
            text-decoration: none;
        }

        img {
            max-width: 100%;
            height: auto;
        }

        /*========== HEADER ==========*/
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: var(--container-color);
            box-shadow: 0 1px 0 rgba(22, 8, 43, 0.1);
            padding: 0 1rem;
            z-index: var(--z-fixed);
            transition: padding 0.4s ease;
        }

        .header__container {
            display: flex;
            align-items: center;
            height: var(--header-height);
            justify-content: space-between;
        }

        .header__img {
            width: 35px;
            height: 35px;
            border-radius: 50%;
        }

        .header__logo {
            color: var(--title-color);
            font-weight: var(--font-medium);
            display: none;
        }

        .header__search {
            display: flex;
            padding: 0.4rem 0.75rem;
            background-color: var(--first-color-light);
            border-radius: 0.25rem;
        }

        .header__input {
            width: 100%;
            border: none;
            outline: none;
            background-color: var(--first-color-light);
        }

        .header__input::placeholder {
            font-family: var(--body-font);
            color: var(--text-color);
        }

        .header__icon, .header__toggle {
            font-size: 1.2rem;
        }

        .header__toggle {
            color: var(--title-color);
            cursor: pointer;
        }

        /* Profile Dropdown Styles */
        .header__profile {
            position: relative;
        }

        .profile__btn {
            background: none;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            font-size: 16px;
            color: #333;
        }

        .material-icons {
            font-size: 22px;
            vertical-align: middle;
        }

        .profile__name {
            margin-right: 5px;
        }

        .profile__dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            list-style: none;
            padding: 10px 0;
            margin: 8px 0 0;
            border-radius: 5px;
            display: none;
            min-width: 150px;
            z-index: 99;
        }

        .profile__dropdown li {
            padding: 8px 16px;
        }

        .profile__dropdown li a {
            text-decoration: none;
            color: #333;
            display: block;
        }

        .profile__dropdown li:hover {
            background-color: #f0f0f0;
        }

        /*========== BREADCRUMB ==========*/
        .breadcrumb__container {
            position: fixed;
            top: var(--header-height);
            left: 0;
            width: 100%;
            background-color: var(--container-color);
            box-shadow: 0 1px 0 rgba(22, 8, 43, 0.1);
            padding: 0.75rem 1rem;
            z-index: calc(var(--z-fixed) - 1);
            display: none;
            transition: all 0.4s ease;
        }

        .breadcrumb__container.show {
            display: block;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: var(--small-font-size);
            color: var(--text-color-light);
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .breadcrumb__item {
            display: flex;
            align-items: center;
        }

        .breadcrumb__item:not(:last-child)::after {
            content: '/';
            margin: 0 0.5rem;
            color: var(--text-color-light);
        }

        .breadcrumb__link {
            color: var(--text-color);
            display: flex;
            align-items: center;
        }

        .breadcrumb__link:hover {
            color: var(--first-color);
        }

        .breadcrumb__link.active {
            color: var(--first-color);
            font-weight: var(--font-medium);
        }

        .breadcrumb__icon {
            font-size: 1rem;
            margin-right: 0.25rem;
        }

        /*========== NAV ==========*/
        .nav {
            position: fixed;
            top: 0;
            left: -100%;
            height: 100vh;
            padding: 1rem 1rem 0;
            background-color: var(--container-color);
            box-shadow: 1px 0 0 rgba(22, 8, 43, 0.1);
            z-index: var(--z-fixed);
            transition: 0.4s;
        }

        .nav__container {
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding-bottom: 3rem;
            overflow: auto;
            scrollbar-width: none;
        }

        .nav__container::-webkit-scrollbar {
            display: none;
        }

        .nav__logo {
            font-weight: var(--font-semi-bold);
            margin-bottom: 2.5rem;
        }

        .nav__list, .nav__items {
            display: grid;
        }

        .nav__list {
            row-gap: 2.5rem;
        }

        .nav__items {
            row-gap: 1.5rem;
        }

        .nav__subtitle {
            font-size: var(--normal-font-size);
            text-transform: uppercase;
            letter-spacing: 0.1rem;
            color: var(--text-color-light);
        }

        .nav__link {
            display: flex;
            align-items: center;
            color: var(--text-color);
        }

        .nav__link:hover {
            color: var(--first-color);
        }

        .nav__icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }

        .nav__name {
            font-size: var(--small-font-size);
            font-weight: var(--font-medium);
            white-space: nowrap;
        }

        .nav__logout {
            margin-top: 5rem;
        }

        /* Dropdown */
        .nav__dropdown {
            overflow: hidden;
            max-height: 21px;
            transition: 0.4s ease-in-out;
        }

        .nav__dropdown-collapse {
            background-color: var(--first-color-light);
            border-radius: 0.25rem;
            margin-top: 1rem;
        }

        .nav__dropdown-content {
            display: grid;
            row-gap: 0.5rem;
            padding: 0.75rem 2.5rem 0.75rem 1.8rem;
        }

        .nav__dropdown-item {
            font-size: var(--smaller-font-size);
            font-weight: var(--font-medium);
            color: var(--text-color);
        }

        .nav__dropdown-item:hover {
            color: var(--first-color);
        }

        .nav__dropdown-icon {
            margin-left: auto;
            transition: 0.4s;
        }

        .nav__dropdown:hover {
            max-height: 100rem;
        }

        .nav__dropdown:hover .nav__dropdown-icon {
            transform: rotate(180deg);
        }

        /*===== Show menu =====*/
        .show-menu {
            left: 0;
        }

        /*===== Active link =====*/
        .active {
            color: var(--first-color);
        }

        /*========== MAIN CONTENT ==========*/
        .main__content {
            padding: 2rem;
            background-color: var(--body-color);
            min-height: calc(100vh - var(--header-height));
            transition: all 0.4s ease;
        }

        .main__content.with-breadcrumb {
            padding-top: calc(2rem + 3rem); /* Add space for breadcrumb */
        }

        /* ========== MEDIA QUERIES ==========*/
        @media screen and (max-width: 320px) {
            .header__search {
                width: 70%;
            }
        }

        @media screen and (min-width: 768px) {
            body {
                padding: 1rem 3rem 0 calc(var(--nav-width-collapsed) + 3rem);
            }
            
            body.nav-expanded {
                padding-left: calc(var(--nav-width) + 3rem);
            }

            body.with-breadcrumb {
                margin-top: calc(var(--header-height) + 3rem);
            }

            .header {
                padding: 0 3rem 0 calc(var(--nav-width-collapsed) + 3rem);
            }

            .header.nav-expanded {
                padding-left: calc(var(--nav-width) + 3rem);
            }

            .breadcrumb__container {
                left: calc(var(--nav-width-collapsed) + 3rem);
                width: calc(100% - var(--nav-width-collapsed) - 3rem);
            }

            .breadcrumb__container.nav-expanded {
                left: calc(var(--nav-width) + 3rem);
                width: calc(100% - var(--nav-width) - 3rem);
            }

            .header__container {
                height: calc(var(--header-height) + 0.5rem);
            }

            .header__search {
                width: 300px;
                padding: 0.55rem 0.75rem;
            }

            .header__toggle {
                display: none;
            }

            .header__logo {
                display: block;
            }

            .header__img {
                width: 40px;
                height: 40px;
                order: 1;
            }

            .nav {
                left: 0;
                padding: 1.2rem 1.5rem 0;
                width: var(--nav-width-collapsed);
            }

            .nav__items {
                row-gap: 1.7rem;
            }

            .nav__icon {
                font-size: 1.3rem;
            }

            .nav__logo-name, .nav__name, .nav__subtitle, .nav__dropdown-icon {
                opacity: 0;
                transition: 0.3s;
            }

            .nav:hover {
                width: var(--nav-width);
            }

            .nav:hover .nav__logo-name {
                opacity: 1;
            }

            .nav:hover .nav__subtitle {
                opacity: 1;
            }

            .nav:hover .nav__name {
                opacity: 1;
            }

            .nav:hover .nav__dropdown-icon {
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!--========== HEADER ==========-->
    <header class="header">
        <div class="header__container">
            <a href="#" class="header__logo">Tetangga.id</a>

            <div class="header__toggle">
                <i class="bx bx-menu" id="header-toggle"></i>
            </div>

        <!-- Profile Display (No Dropdown) -->
        <div class="header__profile" style="display: flex; align-items: center; gap: 10px;">
            <i class="material-icons">account_circle</i>
            <span class="profile__name">
                <?php echo isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin'; ?>
            </span>
        </div>

        </div>
    </header>

    <!--========== NAV ==========-->
    <div class="nav" id="navbar">
        <nav class="nav__container">
            <div>
                <a href="#" class="nav__link nav__logo">
                    <span class="material-icons nav__icon">home</span>
                    <span class="nav__logo-name">Tetangga.id</span>
                </a>

                <div class="nav__list">
                    <div class="nav__items">
                        <h3 class="nav__subtitle">Profile</h3>

                        <a href="dashboard.php" class="nav__link active">
			<span class="material-icons nav__icon">dashboard</span>
			<span class="nav__name">Dashboard</span>
		  </a>

		  <a href="pendataan.php" class="nav__link">
		  <span class="material-icons nav__icon">post_add</span>
			<span class="nav__name">Data Penduduk</span>
		</a>

		<a href="tagihan.php" class="nav__link">
		  <span class="material-icons nav__icon">payments</span>
			<span class="nav__name">Tagihan</span>
		</a>

		<a href="income.php" class="nav__link">
		  <span class="material-icons nav__icon">paid</span>
			<span class="nav__name">Keuangan</span>
		</a>

		  <a href="kegiatan.php" class="nav__link">
			<span class="material-icons nav__icon">campaign</span>
			<span class="nav__name">Pengumuman</span>
		  </a>

		  <a href="akun_warga.php" class="nav__link">
			<span class="material-icons nav__icon">people</span>
			<span class="nav__name">Akun Warga</span>
		  </a>



		  <a href="livechat.php" class="nav__link">
			<span class="material-icons nav__icon">forum</span>
			<span class="nav__name">Live Chat</span>
		  </a>
		</div>
	  </div>
	</div>

	<a href="../login.php" class="nav__link nav__logout">
	  <span class="material-icons nav__icon">logout</span>
	  <span class="nav__name">Log Out</span>
	</a>
        </nav>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js"></script>
    
    <script>
          document.addEventListener("DOMContentLoaded", function () {
            const body = document.body;
            const header = document.querySelector('.header');
            const breadcrumbContainer = document.getElementById('breadcrumbContainer');
            const mainContent = document.getElementById('mainContent');
            const nav = document.getElementById('navbar');

            // Check if we're on desktop
            function isDesktop() {
                return window.innerWidth >= 768;
            }

            // Update breadcrumb
            function updateBreadcrumb(pageName) {
                const breadcrumbNav = document.getElementById('breadcrumbNav');
                const pageInfo = pageData[pageName];
                
                if (pageInfo) {
                    breadcrumbNav.innerHTML = `
                        <li class="breadcrumb__item">
                            <a href="#" class="breadcrumb__link" onclick="showPage('dashboard')">
                                <i class="material-icons breadcrumb__icon">home</i>
                                Dashboard
                            </a>
                        </li>
                        ${pageName !== 'dashboard' ? `
                            <li class="breadcrumb__item">
                                <span class="breadcrumb__link active">
                                    <i class="material-icons breadcrumb__icon">${pageInfo.icon}</i>
                                    ${pageInfo.title}
                                </span>
                            </li>
                        ` : ''}
                    `;
                }
            }

            // Show/hide breadcrumb based on nav state
            function toggleBreadcrumb() {
                if (isDesktop()) {
                    const isNavHovered = nav.matches(':hover');
                    if (!isNavHovered) {
                        breadcrumbContainer.classList.add('show');
                        body.classList.add('with-breadcrumb');
                        mainContent.classList.add('with-breadcrumb');
                    } else {
                        breadcrumbContainer.classList.remove('show');
                        body.classList.remove('with-breadcrumb');
                        mainContent.classList.remove('with-breadcrumb');
                    }
                } else {
                    breadcrumbContainer.classList.remove('show');
                    body.classList.remove('with-breadcrumb');
                    mainContent.classList.remove('with-breadcrumb');
                }
            }

            // Update layout based on nav state
            function updateLayout() {
                if (isDesktop()) {
                    const isNavHovered = nav.matches(':hover');
                    if (isNavHovered) {
                        body.classList.add('nav-expanded');
                        header.classList.add('nav-expanded');
                        breadcrumbContainer.classList.add('nav-expanded');
                    } else {
                        body.classList.remove('nav-expanded');
                        header.classList.remove('nav-expanded');
                        breadcrumbContainer.classList.remove('nav-expanded');
                    }
                }
            }

            // Show page content
            window.showPage = function(pageName) {
                const pageContent = document.getElementById('pageContent');
                const pageInfo = pageData[pageName];
                
                if (pageInfo) {
                    pageContent.innerHTML = pageInfo.content;
                    updateBreadcrumb(pageName);
                    
                    // Update active nav link
                    const navLinks = document.querySelectorAll('.nav__link[data-page]');
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.getAttribute('data-page') === pageName) {
                            link.classList.add('active');
                        }
                    });
                }
                
                // Close mobile menu if open
                if (!isDesktop()) {
                    nav.classList.remove("show-menu");
                    const toggleBtn = document.getElementById("header-toggle");
                    toggleBtn.classList.remove("bx-x");
                }
            };

            /*==================== SHOW NAVBAR ====================*/
            const showMenu = (headerToggle, navbarId) => {
                const toggleBtn = document.getElementById(headerToggle),
                    navElement = document.getElementById(navbarId);

                if (toggleBtn && navElement) {
                    toggleBtn.addEventListener("click", () => {
                        navElement.classList.toggle("show-menu");
                        toggleBtn.classList.toggle("bx-x");
                    });
                }
            };
            showMenu("header-toggle", "navbar");

            /*==================== NAV HOVER EVENTS FOR DESKTOP ====================*/
            if (isDesktop()) {
                nav.addEventListener('mouseenter', () => {
                    updateLayout();
                    toggleBreadcrumb();
                });

                nav.addEventListener('mouseleave', () => {
                    updateLayout();
                    toggleBreadcrumb();
                });
            }

            /*==================== PROFILE DROPDOWN ====================*/
            const profileToggle = document.getElementById('profileToggle');
            const profileDropdown = document.getElementById('profileDropdown');

            profileToggle.addEventListener('click', (e) => {
                e.preventDefault();
                profileDropdown.style.display = profileDropdown.style.display === 'block' ? 'none' : 'block';
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function (e) {
                if (!profileToggle.contains(e.target) && !profileDropdown.contains(e.target)) {
                    profileDropdown.style.display = 'none';
                }
            });

            /*==================== WINDOW RESIZE ====================*/
            window.addEventListener('resize', () => {
                if (isDesktop()) {
                    // Reset mobile menu state
                    nav.classList.remove("show-menu");
                    const toggleBtn = document.getElementById("header-toggle");
                    toggleBtn.classList.remove("bx-x");
                    
                    // Initialize desktop layout
                    updateLayout();
                    toggleBreadcrumb();
                } else {
                    // Reset desktop classes
                    body.classList.remove('nav-expanded', 'with-breadcrumb');
                    header.classList.remove('nav-expanded');
                    breadcrumbContainer.classList.remove('show', 'nav-expanded');
                    mainContent.classList.remove('with-breadcrumb');
                }
            });

            /*==================== INITIALIZATION ====================*/
            // Initialize breadcrumb for dashboard
            updateBreadcrumb('dashboard');
            
            // Initialize layout on page load
            if (isDesktop()) {
                updateLayout();
                toggleBreadcrumb();
            }
        });
    </script>
</body>
</html>