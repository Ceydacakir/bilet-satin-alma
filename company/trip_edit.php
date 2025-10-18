<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];
$trip_id = $_GET['id'] ?? 0;

// Seferin bu firmaya ait olduğunu kontrol et
$stmt = $pdo->prepare("SELECT * FROM trips WHERE id = ? AND company_id = ?");
$stmt->execute([$trip_id, $company_id]);
$trip = $stmt->fetch();

if (!$trip) {
    setErrorMessage('Sefer bulunamadı.');
    header('Location: trips.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $departure_city = trim($_POST['departure_city']);
    $destination_city = trim($_POST['destination_city']);
    $departure_time = $_POST['departure_time'];
    $arrival_time = $_POST['arrival_time'];
    $price = floatval($_POST['price']);
    $capacity = intval($_POST['capacity']);
    
    // Validasyon
    if (empty($departure_city) || empty($destination_city) || empty($departure_time) || empty($arrival_time)) {
        $error_message = 'Lütfen tüm alanları doldurun.';
    } elseif ($departure_city === $destination_city) {
        $error_message = 'Kalkış ve varış şehirleri aynı olamaz.';
    } elseif ($price <= 0) {
        $error_message = 'Fiyat 0\'dan büyük olmalıdır.';
    } elseif ($capacity <= 0 || $capacity > 100) {
        $error_message = 'Kapasite 1-100 arasında olmalıdır.';
    } elseif (strtotime($departure_time) <= time()) {
        $error_message = 'Kalkış saati gelecekte olmalıdır.';
    } elseif (strtotime($arrival_time) <= strtotime($departure_time)) {
        $error_message = 'Varış saati kalkış saatinden sonra olmalıdır.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE trips 
                SET departure_city = ?, destination_city = ?, departure_time = ?, 
                    arrival_time = ?, price = ?, capacity = ?
                WHERE id = ? AND company_id = ?
            ");
            $stmt->execute([$departure_city, $destination_city, $departure_time, $arrival_time, $price, $capacity, $trip_id, $company_id]);
            
            setSuccessMessage('Sefer başarıyla güncellendi.');
            header('Location: trips.php');
            exit();
            
        } catch (Exception $e) {
            $error_message = 'Sefer güncellenirken bir hata oluştu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sefer Düzenle - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
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
                        <a class="nav-link" href="dashboard.php">Firma Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="trips.php">Seferler</a>
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
                            <li><a class="dropdown-item" href="../profile.php">Hesabım</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="text-white">
                            <i class="fas fa-edit me-2"></i>Sefer Düzenle
                        </h2>
                        <p class="text-muted">Sefer bilgilerini güncelleyin</p>
                    </div>
                    <a href="trips.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                </div>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card bg-dark border-secondary">
                    <div class="card-body">
                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_city" class="form-label">Kalkış Şehri</label>
                                    <select class="form-select" id="departure_city" name="departure_city" required>
                                        <option value="">Şehir Seçin</option>
                                        <option value="İstanbul" <?php echo $trip['departure_city'] === 'İstanbul' ? 'selected' : ''; ?>>İstanbul</option>
                                        <option value="Ankara" <?php echo $trip['departure_city'] === 'Ankara' ? 'selected' : ''; ?>>Ankara</option>
                                        <option value="İzmir" <?php echo $trip['departure_city'] === 'İzmir' ? 'selected' : ''; ?>>İzmir</option>
                                        <option value="Manisa" <?php echo $trip['departure_city'] === 'Manisa' ? 'selected' : ''; ?>>Manisa</option>
                                        <option value="Adana" <?php echo $trip['departure_city'] === 'Adana' ? 'selected' : ''; ?>>Adana</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="destination_city" class="form-label">Varış Şehri</label>
                                    <select class="form-select" id="destination_city" name="destination_city" required>
                                        <option value="">Şehir Seçin</option>
                                        <option value="İstanbul" <?php echo $trip['destination_city'] === 'İstanbul' ? 'selected' : ''; ?>>İstanbul</option>
                                        <option value="Ankara" <?php echo $trip['destination_city'] === 'Ankara' ? 'selected' : ''; ?>>Ankara</option>
                                        <option value="İzmir" <?php echo $trip['destination_city'] === 'İzmir' ? 'selected' : ''; ?>>İzmir</option>
                                        <option value="Manisa" <?php echo $trip['destination_city'] === 'Manisa' ? 'selected' : ''; ?>>Manisa</option>
                                        <option value="Adana" <?php echo $trip['destination_city'] === 'Adana' ? 'selected' : ''; ?>>Adana</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="departure_time" class="form-label">Kalkış Tarihi ve Saati</label>
                                    <input type="datetime-local" class="form-control" id="departure_time" name="departure_time" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($trip['departure_time'])); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="arrival_time" class="form-label">Varış Tarihi ve Saati</label>
                                    <input type="datetime-local" class="form-control" id="arrival_time" name="arrival_time" 
                                           value="<?php echo date('Y-m-d\TH:i', strtotime($trip['arrival_time'])); ?>" required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Bilet Fiyatı (TL)</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           min="1" step="0.01" value="<?php echo $trip['price']; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="capacity" class="form-label">Koltuk Kapasitesi</label>
                                    <input type="number" class="form-control" id="capacity" name="capacity" 
                                           min="1" max="100" value="<?php echo $trip['capacity']; ?>" required>
                                </div>
                            </div>

                            <div class="text-end">
                                <a href="trips.php" class="btn btn-secondary me-2">İptal</a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Güncelle
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        // Şehir seçimi validasyonu
        document.getElementById('departure_city').addEventListener('change', function() {
            const destinationSelect = document.getElementById('destination_city');
            const selectedDeparture = this.value;
            
            Array.from(destinationSelect.options).forEach(option => {
                if (option.value === selectedDeparture) {
                    option.disabled = true;
                } else {
                    option.disabled = false;
                }
            });
        });

        // Tarih validasyonu
        document.getElementById('departure_time').addEventListener('change', function() {
            const arrivalInput = document.getElementById('arrival_time');
            const departureTime = new Date(this.value);
            const minArrivalTime = new Date(departureTime.getTime() + 30 * 60000); // 30 dakika sonra
            
            arrivalInput.min = minArrivalTime.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>

