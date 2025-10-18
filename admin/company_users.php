<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$error_message = '';
$success_message = '';
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

// Kullanıcı silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
        if ($stmt->execute([$user_id, $company_id])) {
            setSuccessMessage('Kullanıcı başarıyla silindi.');
        } else {
            setErrorMessage('Kullanıcı silinemedi.');
        }
    } catch (Exception $e) {
        setErrorMessage('Kullanıcı silinirken bir hata oluştu.');
    }
}

// Yeni kullanıcı ekleme veya güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $balance = (float)($_POST['balance'] ?? 0);
    
    // Validasyon
    if (empty($full_name)) {
        $error_message = 'Ad Soyad boş olamaz.';
    } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $error_message = 'Ad Soyad 2-100 karakter arasında olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } elseif ($user_id == 0 && empty($password)) {
        $error_message = 'Yeni kullanıcı için şifre gereklidir.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            if ($user_id > 0) {
                // Güncelleme
                // E-posta benzersizliği kontrolü (kendi e-postası hariç)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                
                if ($stmt->fetch()) {
                    $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    if (!empty($password)) {
                        // Şifre güncellenecek
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ?, balance = ? WHERE id = ? AND company_id = ?");
                        $stmt->execute([$full_name, $email, $hashed_password, $balance, $user_id, $company_id]);
                    } else {
                        // Şifre güncellenmeyecek
                        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, balance = ? WHERE id = ? AND company_id = ?");
                        $stmt->execute([$full_name, $email, $balance, $user_id, $company_id]);
                    }
                    setSuccessMessage('Kullanıcı başarıyla güncellendi.');
                }
            } else {
                // Yeni kullanıcı
                // E-posta benzersizliği kontrolü
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                
                if ($stmt->fetch()) {
                    $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, company_id, balance) VALUES (?, ?, ?, 'company', ?, ?)");
                    $stmt->execute([$full_name, $email, $hashed_password, $company_id, $balance]);
                    setSuccessMessage('Firma admini başarıyla eklendi.');
                }
            }
        } catch (Exception $e) {
            $error_message = 'İşlem sırasında bir hata oluştu.';
        }
    }
}

// Firma kullanıcılarını al
$stmt = $pdo->prepare("
    SELECT u.*,
           (SELECT COUNT(*) FROM tickets WHERE user_id = u.id) as ticket_count
    FROM users u
    WHERE u.company_id = ? AND u.role = 'company'
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
    <title>Firma Yöneticileri - HopBilet</title>
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
                    <i class="fas fa-users me-2"></i>Firma Yöneticileri
                </h2>
                <p class="text-muted">
                    <strong><?php echo h($company['name']); ?></strong> firması için yönetici kullanıcıları yönetin
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <a href="companies.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-arrow-left me-2"></i>Firmalara Dön
                </a>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
                    <i class="fas fa-plus me-2"></i>Yeni Yönetici Ekle
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
                        <h5 class="text-white">Henüz Firma Yöneticisi Yok</h5>
                        <p class="text-muted">Bu firma için ilk yöneticiyi oluşturun.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="openAddModal()">
                            <i class="fas fa-plus me-2"></i>İlk Yöneticiyi Oluştur
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
                                    <th>Bilet Sayısı</th>
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
                                        <td>
                                            <span class="badge bg-success">₺<?php echo number_format($user['balance'], 2); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['ticket_count']; ?></span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at'], 'd.m.Y H:i'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                                        title="Düzenle">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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

    <!-- Kullanıcı Ekleme/Düzenleme Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white" id="userModalTitle">Yeni Yönetici Ekle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="user_id" value="0">
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Ad Soyad</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre <span id="passwordNote" class="text-muted">(en az 6 karakter)</span></label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text text-muted" id="passwordHelp">
                                Güncelleme yaparken şifreyi değiştirmek istemiyorsanız boş bırakın.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="balance" class="form-label">Bakiye (₺)</label>
                            <input type="number" class="form-control" id="balance" name="balance" step="0.01" min="0" value="0">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_user" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Kaydet
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
                    <h5 class="modal-title text-white">Yöneticiyi Sil</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Dikkat!</strong> Bu işlem geri alınamaz.
                    </div>
                    <p class="text-white">Aşağıdaki yöneticiyi silmek istediğinizden emin misiniz?</p>
                    <p class="text-warning" id="userName"></p>
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
        function openAddModal() {
            document.getElementById('userModalTitle').textContent = 'Yeni Yönetici Ekle';
            document.getElementById('user_id').value = '0';
            document.getElementById('full_name').value = '';
            document.getElementById('email').value = '';
            document.getElementById('password').value = '';
            document.getElementById('password').required = true;
            document.getElementById('balance').value = '0';
            document.getElementById('passwordNote').textContent = '(en az 6 karakter)';
            document.getElementById('passwordHelp').style.display = 'none';
        }

        function openEditModal(user) {
            document.getElementById('userModalTitle').textContent = 'Yöneticiyi Düzenle';
            document.getElementById('user_id').value = user.id;
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('email').value = user.email;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('balance').value = user.balance;
            document.getElementById('passwordNote').textContent = '(opsiyonel)';
            document.getElementById('passwordHelp').style.display = 'block';
            
            new bootstrap.Modal(document.getElementById('userModal')).show();
        }

        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('userName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Hata mesajı varsa modalı aç
        <?php if ($error_message): ?>
            new bootstrap.Modal(document.getElementById('userModal')).show();
        <?php endif; ?>
    </script>
</body>
</html>
