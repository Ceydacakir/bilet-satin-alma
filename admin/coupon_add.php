<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$is_edit = isset($_GET['id']);
$coupon = null;

// Edit modunda kupon bilgilerini al
if ($is_edit) {
    $coupon_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        setErrorMessage('Kupon bulunamadı.');
        header('Location: coupons.php');
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = (float)($_POST['discount'] ?? 0);
    $usage_limit = (int)($_POST['usage_limit'] ?? 0);
    $expire_date = $_POST['expire_date'] ?: null;
    $company_id = ($_POST['company_id'] ?? '') !== '' ? (int)$_POST['company_id'] : null; // boş ise genel kupon

    if ($code === '' || strlen($code) < 3 || strlen($code) > 20) {
        $error_message = 'Kupon kodu 3-20 karakter olmalı.';
    } elseif ($discount <= 0 || $discount > 100) {
        $error_message = 'İndirim 1-100 arasında olmalı.';
    } elseif ($usage_limit <= 0) {
        $error_message = 'Kullanım limiti 1 ve üzeri olmalı.';
    } elseif ($expire_date && strtotime($expire_date) <= time()) {
        $error_message = 'Son tarih gelecekte olmalı.';
    } else {
        // Benzersiz kod kontrolü (edit modunda kendi kodunu hariç tut)
        $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?" . ($is_edit ? " AND id != ?" : ""));
        $params = [$code];
        if ($is_edit) {
            $params[] = $coupon_id;
        }
        $stmt->execute($params);
        if ($stmt->fetch()) {
            $error_message = 'Bu kupon kodu zaten var.';
        } else {
            if ($is_edit) {
                // Kupon güncelleme
                $stmt = $pdo->prepare("UPDATE coupons SET code = ?, discount = ?, company_id = ?, usage_limit = ?, expire_date = ? WHERE id = ?");
                if ($stmt->execute([$code, $discount, $company_id, $usage_limit, $expire_date, $coupon_id])) {
                    setSuccessMessage('Kupon güncellendi.');
                    header('Location: coupons.php');
                    exit();
                } else {
                    $error_message = 'Kupon güncellenemedi.';
                }
            } else {
                // Yeni kupon oluşturma
                $stmt = $pdo->prepare("INSERT INTO coupons (code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$code, $discount, $company_id, $usage_limit, $expire_date])) {
                    setSuccessMessage('Kupon oluşturuldu.');
                    header('Location: coupons.php');
                    exit();
                } else {
                    $error_message = 'Kupon oluşturulamadı.';
                }
            }
        }
    }
}

// Firma listesi
$companies = $pdo->query("SELECT id, name FROM bus_companies ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kupon Oluştur - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php"><i class="fas fa-bus me-2"></i>HopBilet</a>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Admin Paneli</a></li>
                    <li class="nav-item"><a class="nav-link" href="companies.php">Firmalar</a></li>
                    <li class="nav-item"><a class="nav-link" href="users.php">Kullanıcılar</a></li>
                    <li class="nav-item"><a class="nav-link active" href="coupons.php">Kuponlar</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="text-white"><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus'; ?> me-2"></i><?php echo $is_edit ? 'Kupon Düzenle' : 'Yeni Kupon'; ?></h2>
                <p class="text-muted"><?php echo $is_edit ? 'Kupon bilgilerini güncelleyin' : 'Genel veya firma bazlı kupon oluşturun'; ?></p>
            </div>
            <a href="coupons.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Geri</a>
        </div>

        <?php displayMessages(); ?>

        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?php echo h($error_message); ?></div>
                <?php endif; ?>

                <form method="post" class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Kupon Kodu</label>
                        <input type="text" name="code" class="form-control" placeholder="WELCOME10" maxlength="20" 
                               value="<?php echo h($coupon['code'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">İndirim (%)</label>
                        <input type="number" name="discount" class="form-control" min="1" max="100" step="0.01" 
                               value="<?php echo h($coupon['discount'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Kullanım Limiti</label>
                        <input type="number" name="usage_limit" class="form-control" min="1" 
                               value="<?php echo h($coupon['usage_limit'] ?? '100'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Son Kullanma (opsiyonel)</label>
                        <input type="date" name="expire_date" class="form-control" 
                               value="<?php echo h($coupon['expire_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Firma (boş bırakılırsa tüm firmalara geçerli)</label>
                        <select name="company_id" class="form-select">
                            <option value="">Genel Kupon</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo $comp['id']; ?>" 
                                        <?php echo (isset($coupon['company_id']) && $coupon['company_id'] == $comp['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($comp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?php echo $is_edit ? 'Güncelle' : 'Oluştur'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



