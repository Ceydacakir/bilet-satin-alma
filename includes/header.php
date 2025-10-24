<?php
$current_page = basename($_SERVER['PHP_SELF']);
$path = $_SERVER['PHP_SELF'];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biletlerim - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/style.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                       <a class="nav-link <?php echo ($current_page == 'index.php' ? 'active' : ''); ?>" href="/index.php">Ana Sayfa</a>
                    </li>
                    
                    <?php if (strpos($path, '/company/') !== false): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="dashboard.php">Firma Paneli</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'trips.php' ? 'active' : ''); ?>" href="trips.php">Seferler</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'coupons.php' ? 'active' : ''); ?>" href="coupons.php">Kuponlar</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'company_tickets.php' ? 'active' : ''); ?>" href="company_tickets.php">Satılan Biletler</a>
                        </li>

                    <?php elseif (strpos($path, '/admin/') !== false): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="dashboard.php">Admin Paneli</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'companies.php' ? 'active' : ''); ?>" href="companies.php">Firmalar</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'coupons.php' ? 'active' : ''); ?>" href="coupons.php">Kuponlar</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'users.php' ? 'active' : ''); ?>" href="users.php">Kullanıcılar</a>
                        </li>

                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'search.php' ? 'active' : ''); ?>" href="/search.php">Sefer Ara</a>
                        </li>
                        <?php if (isLoggedIn() && $_SESSION['role'] == 'company'): ?>
                            <li><a class="nav-link <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="/company/dashboard.php">Firma Paneli</a></li>
                        <?php endif; ?>
                        <?php if (isLoggedIn() && $_SESSION['role'] == 'admin'): ?>
                            <li><a class="nav-link <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="/admin/dashboard.php">Admin Paneli</a></li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            
                            <ul class="dropdown-menu">
                                <?php if ($_SESSION['role'] == 'user'): ?>
                                    <li class="nav-item">
                                        <a class="dropdown-item <?php echo ($current_page == '/profile.php' ? 'active' : ''); ?>" href="profile.php">Profilim</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="dropdown-item <?php echo ($current_page == '/tickets.php' ? 'active' : ''); ?>" href="tickets.php">Biletlerim</a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] == 'company'): ?>
                                    <li><a class="dropdown-item <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="/company/dashboard.php">Firma Paneli</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item <?php echo ($current_page == 'profile.php' ? 'active' : ''); ?>" href="/profile.php">Hesabım</a></li>
                                    <li><a class="dropdown-item <?php echo ($current_page == 'company_tickets.php' ? 'active' : ''); ?>" href="/company/company_tickets.php">Biletler</a></li>
                                <?php endif; ?>

                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <li><a class="dropdown-item <?php echo ($current_page == 'dashboard.php' ? 'active' : ''); ?>" href="/admin/dashboard.php">Admin Paneli</a></li>
                                <?php endif; ?>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php">Çıkış Yap</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="/login.php">Giriş Yap</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/register.php">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>