<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// Firma ID kontrolü
if (!isset($_GET['id'])) {
    setErrorMessage('Firma ID belirtilmedi.');
    header('Location: companies.php');
    exit();
}

$company_id = (int)$_GET['id'];

// Firmayı al
$stmt = $pdo->prepare("SELECT * FROM bus_companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if (!$company) {
    setErrorMessage('Firma bulunamadı.');
    header('Location: companies.php');
    exit();
}

// Kullanıcı silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Kullanıcının bu firmaya ait olduğunu kontrol et
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND company_id = ? AND role = 'company'");
    $stmt->execute([$user_id, $company_id]);
    
    if ($stmt->fetch()) {
        try {
            $pdo->beginTransaction();
            
            // Kullanıcının biletlerini iptal et
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'cancelled' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Kullanıcıyı sil
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            setSuccessMessage('Kullanıcı başarıyla silindi.');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setErrorMessage('Kullanıcı silinirken bir hata oluştu.');
        }
    } else {
        setErrorMessage('Kullanıcı bulunamadı veya bu firmaya ait değil.');
    }
}

// Yeni kullanıcı ekleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $error_message = '';
    
    if (empty($full_name) || strlen($full_name) < 2) {
        $error_message = 'Ad soyad en az 2 karakter olmalı.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalı.';
    } else {
        // E-posta benzersizliği kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            try {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, company_id, balance) VALUES (?, ?, ?, 'company', ?, 0.00)");
                
                if ($stmt->execute([$full_name, $email, $hashed_password, $company_id])) {
                    setSuccessMessage('Firma admin kullanıcısı başarıyla oluşturuldu.');
                } else {
                    $error_message = 'Kullanıcı oluşturulamadı.';
                }
                
            } catch (Exception $e) {
                $error_message = 'Kullanıcı oluşturulurken bir hata oluştu.';
            }
        }
    }
    
    if ($error_message) {
        setErrorMessage($error_message);
    }
}

// Firma kullanıcılarını al
$stmt = $pdo->prepare("
    SELECT u.*, 
           COUNT(DISTINCT t.id) as ticket_count,
           SUM(CASE WHEN t.status = 'active' THEN 1 ELSE 0 END) as active_tickets
    FROM users u
    LEFT JOIN tickets t ON u.id = t.user_id
    WHERE u.company_id = ? AND u.role = 'company'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute([$company_id]);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($company['name']); ?> - Kullanıcı Yönetimi - HopBilet</title>
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
                        <a class="nav-link" href="dashboard.php">Admin Paneli</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="companies.php">Firmalar</a>
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
                            <li><a class="dropdown-item" href="../tickets.php">Biletlerim</a></li>
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
            <div class="col-md-6">
                <h2 class="text-white">
                    <i class="fas fa-users me-2"></i><?php echo h($company['name']); ?> - Kullanıcı Yönetimi
                </h2>
                <p class="text-muted">Firma admin kullanıcılarını yönetin</p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="companies.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Geri Dön
                </a>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Yeni Admin Ekle
                </button>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Kullanıcılar Tablosu -->
        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Henüz Admin Kullanıcı Yok</h5>
                        <p class="text-muted">Bu firma için ilk admin kullanıcısını oluşturmak için yukarıdaki butona tıklayın.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus me-2"></i>İlk Admin'i Oluşturun
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>E-posta</th>
                                    <th>Bakiye</th>
                                    <th>Toplam Bilet</th>
                                    <th>Aktif Bilet</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-tie text-primary me-2"></i>
                                                <strong><?php echo h($user['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo h($user['email']); ?></td>
                                        <td><?php echo formatPrice($user['balance']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo (int)$user['ticket_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo (int)$user['active_tickets']; ?></span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-outline-primary" title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button class="btn btn-outline-danger" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo h($user['full_name']); ?>')"
                                                        title="Sil">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
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

    <!-- Yeni Kullanıcı Ekleme Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Yeni Admin Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şifre</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                            <div class="form-text text-muted">En az 6 karakter</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="add_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Kullanıcı Sil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Dikkat!</strong> Bu işlem geri alınamaz.
                    </div>
                    <p class="text-white">Aşağıdaki kullanıcıyı silmek istediğinizden emin misiniz?</p>
                    <p class="text-warning" id="userName"></p>
                    <p class="text-muted">Bu işlem ile birlikte kullanıcının tüm biletleri iptal edilecek.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <input type="hidden" name="delete_user" value="1">
                        <button type="submit" class="btn btn-danger">Evet, Sil</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/script.js"></script>
    <script>
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('userName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
