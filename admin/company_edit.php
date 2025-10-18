<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$company = null;

// Firma ID'sini al
$company_id = (int)($_GET['id'] ?? 0);

if (!$company_id) {
    header('Location: companies.php');
    exit();
}

// Firmayı al
$stmt = $pdo->prepare("SELECT * FROM bus_companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if (!$company) {
    setErrorMessage('Firma bulunamadı.');
    header('Location: companies.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    
    if (empty($name)) {
        $error_message = 'Firma adı boş olamaz.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error_message = 'Firma adı 2-100 karakter arasında olmalıdır.';
    } else {
        // Firma adının benzersizliğini kontrol et (kendi adı hariç)
        $stmt = $pdo->prepare("SELECT id FROM bus_companies WHERE name = ? AND id != ?");
        $stmt->execute([$name, $company_id]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu firma adı zaten kullanılıyor.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE bus_companies SET name = ? WHERE id = ?");
                $stmt->execute([$name, $company_id]);
                
                setSuccessMessage('Firma başarıyla güncellendi.');
                header('Location: companies.php');
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Firma güncellenirken bir hata oluştu.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Düzenle - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="../index.php"><i class="fas fa-bus me-2"></i>HopBilet</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Admin Paneli</a></li>
                    <li class="nav-item"><a class="nav-link active" href="companies.php">Firmalar</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php">Kullanıcılar</a></li>
                    <li class="nav-item"><a class="nav-link" href="coupons.php">Kuponlar</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Çıkış Yap</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="text-white"><i class="fas fa-edit me-2"></i>Firma Düzenle</h2>
                <p class="text-muted">Firma bilgilerini güncelleyin</p>
            </div>
            <a href="companies.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Geri</a>
        </div>

        <?php displayMessages(); ?>

        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Firma Adı</label>
                        <input type="text" name="name" class="form-control" placeholder="Örn: RoketOtobüs" 
                               value="<?php echo h($_POST['name'] ?? $company['name']); ?>" maxlength="100" required>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save me-2"></i>Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>