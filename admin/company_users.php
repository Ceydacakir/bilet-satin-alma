<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$success_message = '';

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

// Firma admin kullanıcılarını al
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT t.id) as ticket_count,
           COUNT(DISTINCT uc.id) as coupon_usage_count
    FROM users u
    LEFT JOIN tickets t ON u.id = t.user_id
    LEFT JOIN user_coupons uc ON u.id = uc.user_id
    WHERE u.company_id = ? AND u.role = 'company'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute([$company_id]);
$company_users = $stmt->fetchAll();

// Yeni firma admin ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($full_name) || empty($email) || empty($password)) {
        $error_message = 'Tüm alanlar doldurulmalıdır.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } else {
        // E-posta benzersizlik kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, role, password, company_id, balance) VALUES (?, ?, 'company', ?, ?, 5000.00)");
                $stmt->execute([$full_name, $email, $hashed_password, $company_id]);
                
                setSuccessMessage('Firma admin başarıyla eklendi.');
                header('Location: company_users.php?id=' . $company_id);
                exit();
                
            } catch (Exception $e) {
                $error_message = 'Firma admin eklenirken bir hata oluştu.';
            }
        }
    }
}

// Firma admin silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id) {
        try {
            $pdo->beginTransaction();
            
            // Kullanıcının biletlerini iptal et
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Rezerve koltukları sil
            $stmt = $pdo->prepare("
                DELETE FROM booked_seats WHERE ticket_id IN (
                    SELECT id FROM tickets WHERE user_id = ?
                )
            ");
            $stmt->execute([$user_id]);
            
            // Kullanıcı kuponlarını sil
            $stmt = $pdo->prepare("DELETE FROM user_coupons WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Kullanıcıyı sil
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ? AND role = 'company'");
            $stmt->execute([$user_id, $company_id]);
            
            $pdo->commit();
            setSuccessMessage('Firma admin başarıyla silindi.');
            header('Location: company_users.php?id=' . $company_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setErrorMessage('Firma admin silinirken bir hata oluştu.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Admin Yönetimi - HopBilet</title>
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

    <div class="container-fluid mt-4">
        <!-- Başlık -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h2 class="text-white">
                    <i class="fas fa-users me-2"></i><?php echo h($company['name']); ?> - Admin Yönetimi
                </h2>
                <p class="text-muted">Bu firmaya ait admin kullanıcıları yönetin</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="companies.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Firmalara Dön
                </a>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Yeni Admin Ekleme Formu -->
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-header">
                <h5 class="text-white mb-0">
                    <i class="fas fa-plus me-2"></i>Yeni Firma Admin Ekle
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="add_admin" value="1">
                    <div class="col-md-4">
                        <label for="full_name" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               placeholder="Örn: Ahmet Yılmaz" value="<?php echo h($_POST['full_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="admin@firma.com" value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="En az 6 karakter" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Firma Adminleri Listesi -->
        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if (empty($company_users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Henüz Admin Yok</h5>
                        <p class="text-muted">Bu firmaya ait admin kullanıcı bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Admin</th>
                                    <th>E-posta</th>
                                    <th>Bakiye</th>
                                    <th>Bilet Sayısı</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($company_users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-shield text-warning me-2"></i>
                                                <strong><?php echo h($user['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo h($user['email']); ?></td>
                                        <td>
                                            <span class="text-success"><?php echo formatPrice($user['balance']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['ticket_count']; ?></span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></td>
                                        <td>
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo h($user['full_name']); ?>')"
                                                    title="Sil">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Firma Admin Sil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Dikkat!</strong> Bu işlem geri alınamaz.
                    </div>
                    <p class="text-white">Aşağıdaki firma adminini silmek istediğinizden emin misiniz?</p>
                    <p class="text-warning" id="userName"></p>
                    <p class="text-muted">Bu işlem ile birlikte:</p>
                    <ul class="text-muted">
                        <li>Admin kullanıcısının tüm biletleri iptal edilecek</li>
                        <li>Admin kullanıcısının kupon kullanımları silinecek</li>
                        <li>Admin kullanıcı hesabı tamamen silinecek</li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <input type="hidden" name="delete_admin" value="1">
                        <button type="submit" class="btn btn-danger">Evet, Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('userName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>