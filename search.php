<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Arama parametrelerini al
$departure_city = $_GET['departure_city'] ?? '';
$destination_city = $_GET['destination_city'] ?? '';
$departure_date = $_GET['departure_date'] ?? date('Y-m-d');

$trips = [];
$search_performed = false;

// Arama yapıldıysa
if (!empty($departure_city) && !empty($destination_city) && !empty($departure_date)) {
    $search_performed = true;
    
    $stmt = $pdo->prepare("
        SELECT t.*, bc.name as company_name, bc.logo_path,
               (SELECT COUNT(*) FROM tickets tk WHERE tk.trip_id = t.id AND tk.status = 'active') as sold_tickets
        FROM trips t
        JOIN bus_companies bc ON t.company_id = bc.id
        WHERE t.departure_city = ? AND t.destination_city = ? 
        AND DATE(t.departure_time) = ?
        AND t.departure_time > datetime('now')
        ORDER BY t.departure_time
    ");
    
    $stmt->execute([$departure_city, $destination_city, $departure_date]);
    $trips = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Ara - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Ana Sayfa</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="search.php">Sefer Ara</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php">Hesabım</a></li>
                                <li><a class="dropdown-item" href="tickets.php">Biletlerim</a></li>
                                <?php if ($_SESSION['role'] == 'company'): ?>
                                    <li><a class="dropdown-item" href="company/dashboard.php">Firma Paneli</a></li>
                                <?php endif; ?>
                                <?php if ($_SESSION['role'] == 'admin'): ?>
                                    <li><a class="dropdown-item" href="admin/dashboard.php">Admin Paneli</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php">Çıkış Yap</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">Giriş Yap</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">Kayıt Ol</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Arama Formu -->
        <div class="row justify-content-center mb-5">
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-body p-4">
                        <h4 class="text-white mb-4">
                            <i class="fas fa-search me-2"></i>Sefer Ara
                        </h4>
                        
                        <form method="GET" action="search.php">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="departure_city" class="form-label">Nereden</label>
                                    <select class="form-select" id="departure_city" name="departure_city" required>
                                        <option value="">Şehir Seçin</option>
                                        <option value="İstanbul" <?php echo $departure_city === 'İstanbul' ? 'selected' : ''; ?>>İstanbul</option>
                                        <option value="Ankara" <?php echo $departure_city === 'Ankara' ? 'selected' : ''; ?>>Ankara</option>
                                        <option value="İzmir" <?php echo $departure_city === 'İzmir' ? 'selected' : ''; ?>>İzmir</option>
                                        <option value="Manisa" <?php echo $departure_city === 'Manisa' ? 'selected' : ''; ?>>Manisa</option>
                                        <option value="Adana" <?php echo $departure_city === 'Adana' ? 'selected' : ''; ?>>Adana</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="destination_city" class="form-label">Nereye</label>
                                    <select class="form-select" id="destination_city" name="destination_city" required>
                                        <option value="">Şehir Seçin</option>
                                        <option value="İstanbul" <?php echo $destination_city === 'İstanbul' ? 'selected' : ''; ?>>İstanbul</option>
                                        <option value="Ankara" <?php echo $destination_city === 'Ankara' ? 'selected' : ''; ?>>Ankara</option>
                                        <option value="İzmir" <?php echo $destination_city === 'İzmir' ? 'selected' : ''; ?>>İzmir</option>
                                        <option value="Manisa" <?php echo $destination_city === 'Manisa' ? 'selected' : ''; ?>>Manisa</option>
                                        <option value="Adana" <?php echo $destination_city === 'Adana' ? 'selected' : ''; ?>>Adana</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="departure_date" class="form-label">Tarih</label>
                                    <input type="date" class="form-control" id="departure_date" name="departure_date" 
                                           value="<?php echo h($departure_date); ?>" required>
                                </div>
                            </div>
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg px-5">
                                    <i class="fas fa-search me-2"></i>Sefer Ara
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arama Sonuçları -->
        <?php if ($search_performed): ?>
            <div class="row">
                <div class="col-12">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-route me-2"></i>
                        <?php echo h($departure_city); ?> - <?php echo h($destination_city); ?> 
                        (<?php echo formatDate($departure_date, 'd.m.Y'); ?>)
                    </h4>
                    
                    <?php if (empty($trips)): ?>
                        <div class="card bg-dark border-secondary">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-white">Sefer Bulunamadı</h5>
                                <p class="text-muted">Aradığınız kriterlere uygun sefer bulunmamaktadır.</p>
                                <a href="search.php" class="btn btn-primary">Yeni Arama Yap</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($trips as $trip): ?>
                            <div class="card bg-dark border-secondary mb-3">
                                <div class="card-body">
                                    <div class="row align-items-center">
                                    <div class="col-md-2 text-center">
                                        <div class="company-logo mb-2">
                                            <i class="fas fa-bus fa-2x text-primary"></i>
                                        </div>
                                        <h6 class="text-white"><?php echo h($trip['company_name']); ?></h6>
                                        <small class="text-muted">Firma</small>
                                    </div>
                                        
                                        <div class="col-md-3">
                                            <div class="time-info">
                                                <div class="departure-time">
                                                    <small class="text-muted">Kalkış</small>
                                                    <h5 class="text-white mb-0"><?php echo formatDate($trip['departure_time'], 'H:i'); ?></h5>
                                                    <small class="text-muted"><?php echo h($trip['departure_city']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-3">
                                            <div class="time-info">
                                                <div class="arrival-time">
                                                    <small class="text-muted">Varış</small>
                                                    <h5 class="text-white mb-0"><?php echo formatDate($trip['arrival_time'], 'H:i'); ?></h5>
                                                    <small class="text-muted"><?php echo h($trip['destination_city']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <div class="price-info text-center">
                                                <h4 class="text-primary mb-0"><?php echo formatPrice($trip['price']); ?></h4>
                                                <small class="text-muted">Kişi başı</small>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-2">
                                            <div class="seat-info text-center mb-2">
                                                <small class="text-muted">Kalan Koltuk</small>
                                                <div class="text-white">
                                                    <?php echo $trip['capacity'] - $trip['sold_tickets']; ?> / <?php echo $trip['capacity']; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <?php if (isLoggedIn()): ?>
                                                    <?php if ($_SESSION['role'] === 'user'): ?>
                                                        <a href="trip_details.php?id=<?php echo $trip['id']; ?>" 
                                                           class="btn btn-primary btn-sm">
                                                            <i class="fas fa-ticket-alt me-1"></i>Bilet Al
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="trip_details.php?id=<?php echo $trip['id']; ?>" 
                                                           class="btn btn-outline-info btn-sm">
                                                            <i class="fas fa-eye me-1"></i>Sefer Detayları
                                                        </a>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="login.php" class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-sign-in-alt me-1"></i>Giriş Yap
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
