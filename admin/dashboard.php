<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// İstatistikleri al
$stats = [];

// Toplam kullanıcı sayısı
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
$stats['total_users'] = $stmt->fetchColumn();

// Toplam firma sayısı
$stmt = $pdo->query("SELECT COUNT(*) FROM bus_companies");
$stats['total_companies'] = $stmt->fetchColumn();

// Toplam sefer sayısı
$stmt = $pdo->query("SELECT COUNT(*) FROM trips");
$stats['total_trips'] = $stmt->fetchColumn();

// Toplam bilet sayısı
$stmt = $pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'active'");
$stats['total_tickets'] = $stmt->fetchColumn();

// Toplam gelir
$stmt = $pdo->query("SELECT SUM(total_price) FROM tickets WHERE status = 'active'");
$stats['total_revenue'] = $stmt->fetchColumn() ?: 0;

// Son kullanıcılar
$stmt = $pdo->query("
    SELECT full_name, email, role, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_users = $stmt->fetchAll();

// Son firmalar
$stmt = $pdo->query("
    SELECT name, created_at 
    FROM bus_companies 
    ORDER BY created_at DESC 
    LIMIT 5
");
$recent_companies = $stmt->fetchAll();

// En çok satan firmalar
$stmt = $pdo->query("
    SELECT bc.name, COUNT(tk.id) as ticket_count, SUM(tk.total_price) as revenue
    FROM bus_companies bc
    LEFT JOIN trips t ON bc.id = t.company_id
    LEFT JOIN tickets tk ON t.id = tk.trip_id AND tk.status = 'active'
    GROUP BY bc.id, bc.name
    ORDER BY ticket_count DESC
    LIMIT 5
");
$top_companies = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Background Elements -->
    <div class="rockets"></div>
    <div class="space-particles"></div>

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-rocket me-2 rocket-icon"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Admin Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="companies.php">Firmalar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="coupons.php">Kuponlar</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../tickets.php">Bilet Yönetimi</a></li>
                            <li><a class="dropdown-item" href="users.php">Kullanıcı Yönetimi</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="text-white">
                    <i class="fas fa-rocket me-2 rocket-icon"></i>Admin Paneli
                </h2>
                <p class="text-muted">Sistem yönetimi ve istatistikler 🚀</p>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- İstatistik Kartları -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-users fa-2x text-primary mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['total_users']; ?></h4>
                        <p class="text-muted mb-0">Toplam Kullanıcı</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-building fa-2x text-success mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['total_companies']; ?></h4>
                        <p class="text-muted mb-0">Toplam Firma</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-route fa-2x text-warning mb-3"></i>
                        <h4 class="text-white"><?php echo $stats['total_trips']; ?></h4>
                        <p class="text-muted mb-0">Toplam Sefer</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card bg-dark border-secondary">
                    <div class="card-body text-center">
                        <i class="fas fa-lira-sign fa-2x text-info mb-3"></i>
                        <h4 class="text-white"><?php echo formatPrice($stats['total_revenue']); ?></h4>
                        <p class="text-muted mb-0">Toplam Gelir</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Son Kullanıcılar -->
            <div class="col-lg-6 mb-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-user-plus me-2"></i>Son Kayıt Olan Kullanıcılar
                        </h5>
                        <span class="text-muted small">
                            <i class="fas fa-info-circle me-1"></i>Sadece görüntüleme
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_users)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Henüz kullanıcı yok</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>E-posta</th>
                                            <th>Rol</th>
                                            <th>Tarih</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_users as $user): ?>
                                            <tr>
                                                <td><?php echo h($user['full_name']); ?></td>
                                                <td><?php echo h($user['email']); ?></td>
                                                <td>
                                                    <?php
                                                    $role_badges = [
                                                        'user' => 'bg-primary',
                                                        'company' => 'bg-warning',
                                                        'admin' => 'bg-danger'
                                                    ];
                                                    $role_names = [
                                                        'user' => 'Yolcu',
                                                        'company' => 'Firma',
                                                        'admin' => 'Admin'
                                                    ];
                                                    $badge_class = $role_badges[$user['role']] ?? 'bg-secondary';
                                                    $role_name = $role_names[$user['role']] ?? $user['role'];
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $role_name; ?></span>
                                                </td>
                                                <td><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Son Firmalar -->
            <div class="col-lg-6 mb-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-building me-2"></i>Son Eklenen Firmalar
                        </h5>
                        <a href="companies.php" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye me-1"></i>Tümünü Gör
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_companies)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-building fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">Henüz firma yok</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-sm">
                                    <thead>
                                        <tr>
                                            <th>Firma Adı</th>
                                            <th>Oluşturulma Tarihi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_companies as $company): ?>
                                            <tr>
                                                <td><?php echo h($company['name']); ?></td>
                                                <td><?php echo formatDate($company['created_at'], 'd.m.Y'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- En Çok Satan Firmalar -->
        <div class="row">
            <div class="col-12">
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-trophy me-2"></i>En Çok Satan Firmalar
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($top_companies)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-bar fa-3x text-muted mb-3"></i>
                                <h6 class="text-white">Veri Yok</h6>
                                <p class="text-muted">Henüz satış verisi bulunmamaktadır.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sıra</th>
                                            <th>Firma Adı</th>
                                            <th>Satılan Bilet</th>
                                            <th>Toplam Gelir</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_companies as $index => $company): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($index === 0): ?>
                                                        <i class="fas fa-trophy text-warning"></i>
                                                    <?php elseif ($index === 1): ?>
                                                        <i class="fas fa-medal text-secondary"></i>
                                                    <?php elseif ($index === 2): ?>
                                                        <i class="fas fa-award text-warning"></i>
                                                    <?php else: ?>
                                                        <span class="text-muted"><?php echo $index + 1; ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo h($company['name']); ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $company['ticket_count']; ?></span>
                                                </td>
                                                <td><?php echo formatPrice($company['revenue']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Hızlı Erişim -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card bg-dark border-secondary">
                    <div class="card-header">
                        <h5 class="text-white mb-0">
                            <i class="fas fa-bolt me-2"></i>Hızlı Erişim
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="company_add.php" class="btn btn-primary w-100">
                                    <i class="fas fa-rocket me-2"></i>Yeni Firma Ekle
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="../tickets.php" class="btn btn-success w-100">
                                    <i class="fas fa-ticket-alt me-2"></i>Bilet Yönetimi
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="coupon_add.php" class="btn btn-warning w-100">
                                    <i class="fas fa-tag me-2"></i>Kupon Oluştur
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="users.php" class="btn btn-info w-100">
                                    <i class="fas fa-users me-2"></i>Kullanıcılar
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
</body>
</html>

