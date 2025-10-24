<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Firma admin kontrolü
requireRole('company');

$company_id = $_SESSION['company_id'];
$error_message = '';
$coupon = null;

// Kupon ID'sini al
$coupon_id = $_GET['id'];

if (!$coupon_id) {
    header('Location: coupons.php');
    exit();
}

// Kuponu al ve firma kontrolü yap
$stmt = $pdo->prepare("SELECT * FROM coupons WHERE id = ? AND company_id = ?");
$stmt->execute([$coupon_id, $company_id]);
$coupon = $stmt->fetch();

if (!$coupon) {
    setErrorMessage('Kupon bulunamadı veya bu kuponu düzenleme yetkiniz yok.');
    header('Location: coupons.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code']));
    $discount = floatval($_POST['discount']);
    $usage_limit = intval($_POST['usage_limit']);
    $expire_date = $_POST['expire_date'] ?: null;
    
    // Validasyon
    if (empty($code)) {
        $error_message = 'Kupon kodu boş olamaz.';
    } elseif (strlen($code) < 3 || strlen($code) > 20) {
        $error_message = 'Kupon kodu 3-20 karakter arasında olmalıdır.';
    } elseif ($discount <= 0 || $discount > 100) {
        $error_message = 'İndirim oranı 0-100 arasında olmalıdır.';
    } elseif ($usage_limit <= 0) {
        $error_message = 'Kullanım limiti 0\'dan büyük olmalıdır.';
    } elseif ($expire_date && strtotime($expire_date) <= time()) {
        $error_message = 'Son kullanma tarihi gelecekte olmalıdır.';
    } else {
        // Kupon kodunun benzersizliğini kontrol et (kendi kodu hariç)
        $stmt = $pdo->prepare("SELECT id FROM coupons WHERE code = ? AND id != ?");
        $stmt->execute([$code, $coupon_id]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu kupon kodu zaten kullanılıyor.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE coupons 
                    SET code = ?, discount = ?, usage_limit = ?, expire_date = ?
                    WHERE id = ? AND company_id = ?
                ");
                $stmt->execute([$code, $discount, $usage_limit, $expire_date, $coupon_id, $company_id]);
                
                setSuccessMessage('Kupon başarıyla güncellendi.');
                header('Location: coupons.php');
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Kupon güncellenirken bir hata oluştu.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mt-4">
    <!-- Başlık -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="text-white">
                        <i class="fas fa-edit me-2"></i>Kupon Düzenle
                    </h2>
                    <p class="text-muted">Kupon bilgilerini güncelleyin</p>
                </div>
                <a href="coupons.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                </a>
            </div>
        </div>
    </div>
    <!-- Mesajlar -->
    <?php displayMessages(); ?>
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card bg-dark border-secondary">
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="code" class="form-label">Kupon Kodu</label>
                            <input type="text" class="form-control" id="code" name="code" 
                                   placeholder="Örn: WELCOME10" value="<?php echo h($_POST['code'] ?? $coupon['code']); ?>" 
                                   maxlength="20" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                3-20 karakter arasında, büyük harflerle yazılacak
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="discount" class="form-label">İndirim Oranı (%)</label>
                            <input type="number" class="form-control" id="discount" name="discount" 
                                   min="1" max="100" step="0.01" value="<?php echo h($_POST['discount'] ?? $coupon['discount']); ?>" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                1-100 arasında bir değer girin
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="usage_limit" class="form-label">Kullanım Limiti</label>
                            <input type="number" class="form-control" id="usage_limit" name="usage_limit" 
                                   min="1" value="<?php echo h($_POST['usage_limit'] ?? $coupon['usage_limit']); ?>" required>
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Bu kupon kaç kez kullanılabilir?
                            </div>
                        </div>
                        <div class="mb-4">
                            <label for="expire_date" class="form-label">Son Kullanma Tarihi (Opsiyonel)</label>
                            <input type="date" class="form-control" id="expire_date" name="expire_date" 
                                   value="<?php echo h($_POST['expire_date'] ?? $coupon['expire_date']); ?>">
                            <div class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Boş bırakırsanız kupon süresiz olur
                            </div>
                        </div>
                        <!-- Önizleme -->
                        <div class="card bg-secondary mb-4">
                            <div class="card-body">
                                <h6 class="text-white mb-2">
                                    <i class="fas fa-eye me-2"></i>Kupon Önizlemesi
                                </h6>
                                <div class="d-flex align-items-center">
                                    <code class="text-primary me-3" id="previewCode"><?php echo h($coupon['code']); ?></code>
                                    <span class="badge bg-success me-3" id="previewDiscount">%<?php echo $coupon['discount']; ?></span>
                                    <small class="text-muted" id="previewLimit"><?php echo $coupon['usage_limit']; ?> kullanım</small>
                                </div>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="coupons.php" class="btn btn-secondary me-2">İptal</a>
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
    // Kupon kodu büyük harfe çevir
    document.getElementById('code').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
        updatePreview();
    });
    // Önizleme güncelle
    function updatePreview() {
        const code = document.getElementById('code').value || 'KUPON_KODU';
        const discount = document.getElementById('discount').value || '0';
        const limit = document.getElementById('usage_limit').value || '0';
        
        document.getElementById('previewCode').textContent = code;
        document.getElementById('previewDiscount').textContent = '%' + discount;
        document.getElementById('previewLimit').textContent = limit + ' kullanım';
    }
    // Tüm input değişikliklerini dinle
    ['code', 'discount', 'usage_limit'].forEach(id => {
        document.getElementById(id).addEventListener('input', updatePreview);
    });
    // Sayfa yüklendiğinde önizlemeyi güncelle
    document.addEventListener('DOMContentLoaded', updatePreview);
</script>
</body>
</html>
