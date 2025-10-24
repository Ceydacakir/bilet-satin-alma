<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$coupon = null;
$is_edit = false;

// Düzenleme modunda mı?
if (isset($_GET['id'])) {
    $coupon_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ?");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch();
    
    if (!$coupon) {
        setErrorMessage('Kupon bulunamadı.');
        header('Location: coupons.php');
        exit();
    }
    $is_edit = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = (float)($_POST['discount'] ?? 0);
    $usage_limit = (int)($_POST['usage_limit'] ?? 0);
    $expire_date = $_POST['expire_date'] ?: null;
    $company_id = ($_POST['company_id'] ?? '') !== '' ? $_POST['company_id'] : null; // boş ise genel kupon

    if ($code === '' || strlen($code) < 3 || strlen($code) > 20) {
        $error_message = 'Kupon kodu 3-20 karakter olmalı.';
    } elseif ($discount <= 0 || $discount > 100) {
        $error_message = 'İndirim 1-100 arasında olmalı.';
    } elseif ($usage_limit <= 0) {
        $error_message = 'Kullanım limiti 1 ve üzeri olmalı.';
    } elseif ($expire_date && strtotime($expire_date) <= time()) {
        $error_message = 'Son tarih gelecekte olmalı.';
    } else {
        if ($is_edit) {
            // Düzenleme işlemi
            $coupon_id = $_POST['coupon_id'];
            
            // Benzersiz kod kontrolü (kendi ID'si hariç)
            $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
            $stmt->execute([$code, $coupon_id]);
            if ($stmt->fetch()) {
                $error_message = 'Bu kupon kodu zaten var.';
            } else {
                $stmt = $pdo->prepare("UPDATE coupons SET code = ?, discount = ?, company_id = ?, usage_limit = ?, expire_date = ? WHERE id = ?");
                if ($stmt->execute([$code, $discount, $company_id, $usage_limit, $expire_date, $coupon_id])) {
                    setSuccessMessage('Kupon güncellendi.');
                    header('Location: coupons.php');
                    exit();
                } else {
                    $error_message = 'Kupon güncellenemedi.';
                }
            }
        } else {
            // Yeni kupon oluşturma
            $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ?");
            $stmt->execute([$code]);
            if ($stmt->fetch()) {
                $error_message = 'Bu kupon kodu zaten var.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO coupons (id, code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([uniqid('coup_'), $code, $discount, $company_id, $usage_limit, $expire_date])) {
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
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="text-white"><i class="fas fa-<?php echo $is_edit ? 'edit' : 'plus'; ?> me-2"></i><?php echo $is_edit ? 'Kupon Düzenle' : 'Yeni Kupon'; ?></h2>
            <p class="text-muted"><?php echo $is_edit ? 'Kupon bilgilerini düzenleyin' : 'Genel veya firma bazlı kupon oluşturun'; ?></p>
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
                <?php if ($is_edit): ?>
                    <input type="hidden" name="coupon_id" value="<?php echo $coupon['id']; ?>">
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">Kupon Kodu</label>
                    <input type="text" name="code" class="form-control" placeholder="WELCOME10" maxlength="20" 
                           value="<?php echo h($coupon['code'] ?? ''); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">İndirim (%)</label>
                    <input type="number" name="discount" class="form-control" min="1" max="100" step="0.01" 
                           value="<?php echo $coupon['discount'] ?? ''; ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kullanım Limiti</label>
                    <input type="number" name="usage_limit" class="form-control" min="1" 
                           value="<?php echo $coupon['usage_limit'] ?? '100'; ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Son Kullanma</label>
                    <input type="date" name="expire_date" class="form-control" 
                           value="<?php echo $coupon['expire_date'] ?? ''; ?>" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Firma (boş bırakılırsa tüm firmalara geçerli)</label>
                    <select name="company_id" class="form-select">
                        <option value="">Genel Kupon</option>
                        <?php foreach ($companies as $comp): ?>
                            <option value="<?php echo $comp['id']; ?>" 
                                    <?php echo ($coupon['company_id'] ?? '') == $comp['id'] ? 'selected' : ''; ?>>
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



