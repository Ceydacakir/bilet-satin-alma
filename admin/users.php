<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

// Kullanıcı silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    
    // Admin kendini silemez ve admin kullanıcıları silinemez
    if ($user_id == $_SESSION['user_id']) {
        setErrorMessage('Kendi hesabınızı silemezsiniz.');
    } else {
        // Silinecek kullanıcının rolünü kontrol et
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $target_user = $stmt->fetch();
        
        if ($target_user && $target_user['role'] === 'admin') {
            setErrorMessage('Admin kullanıcıları silinemez.');
        } else {
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
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();
            setSuccessMessage('Kullanıcı başarıyla silindi.');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            setErrorMessage('Kullanıcı silinirken bir hata oluştu.');
        }
        }
    }
}

// Filtreleme parametreleri
$role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

// Kullanıcıları al
$where_conditions = ["1=1"];
$params = [];

if ($role !== 'all') {
    $where_conditions[] = "u.role = ?";
    $params[] = $role;
}

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

$stmt = $pdo->prepare("
    SELECT u.*, bc.name as company_name,
           COUNT(DISTINCT t.id) as ticket_count,
           COUNT(DISTINCT uc.id) as coupon_usage_count
    FROM users u
    LEFT JOIN bus_companies bc ON u.company_id = bc.id
    LEFT JOIN tickets t ON u.id = t.user_id
    LEFT JOIN user_coupons uc ON u.id = uc.user_id
    WHERE $where_clause
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - HopBilet</title>
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
                        <a class="nav-link" href="companies.php">Firmalar</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="users.php">Kullanıcılar</a>
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
                    <i class="fas fa-users me-2"></i>Kullanıcı Yönetimi
                </h2>
                <p class="text-muted">Sistemdeki tüm kullanıcıları yönetin</p>
            </div>
            <div class="col-md-6 text-md-end">
                <button class="btn btn-secondary" disabled title="Admin kullanıcıları ekleyemez">
                    <i class="fas fa-plus me-2"></i>Yeni Kullanıcı Ekle
                </button>
            </div>
        </div>

        <!-- Mesajlar -->
        <?php displayMessages(); ?>

        <!-- Filtreler -->
        <div class="card bg-dark border-secondary mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role">
                            <option value="all" <?php echo $role === 'all' ? 'selected' : ''; ?>>Tümü</option>
                            <option value="user" <?php echo $role === 'user' ? 'selected' : ''; ?>>Yolcu</option>
                            <option value="company" <?php echo $role === 'company' ? 'selected' : ''; ?>>Firma Admin</option>
                            <option value="admin" <?php echo $role === 'admin' ? 'selected' : ''; ?>>Sistem Admin</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Arama</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Ad, soyad veya e-posta ile arayın..." value="<?php echo h($search); ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search me-1"></i>Filtrele
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Kullanıcılar Tablosu -->
        <div class="card bg-dark border-secondary">
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-white">Kullanıcı Bulunamadı</h5>
                        <p class="text-muted">Arama kriterlerinize uygun kullanıcı bulunmamaktadır.</p>
                        <a href="user_add.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>İlk Kullanıcıyı Oluşturun
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-dark table-hover">
                            <thead>
                                <tr>
                                    <th>Kullanıcı</th>
                                    <th>E-posta</th>
                                    <th>Rol</th>
                                    <th>Firma</th>
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
                                                <i class="fas fa-user-circle text-primary me-2"></i>
                                                <strong><?php echo h($user['full_name']); ?></strong>
                                            </div>
                                        </td>
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
                                                'company' => 'Firma Admin',
                                                'admin' => 'Sistem Admin'
                                            ];
                                            $badge_class = $role_badges[$user['role']] ?? 'bg-secondary';
                                            $role_name = $role_names[$user['role']] ?? $user['role'];
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $role_name; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($user['company_name']): ?>
                                                <span class="text-info"><?php echo h($user['company_name']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="text-success"><?php echo formatPrice($user['balance']); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['ticket_count']; ?></span>
                                        </td>
                                        <td><?php echo formatDate($user['created_at'], 'd.m.Y'); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($user['role'] !== 'admin'): ?>
                                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-outline-primary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-secondary" disabled title="Admin kullanıcıları düzenlenemez">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] !== 'admin'): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo h($user['full_name']); ?>')"
                                                            title="Sil">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php elseif ($user['role'] === 'admin'): ?>
                                                    <button class="btn btn-outline-secondary" disabled title="Admin kullanıcıları silinemez">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
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
                    <p class="text-muted">Bu işlem ile birlikte:</p>
                    <ul class="text-muted">
                        <li>Kullanıcının tüm biletleri iptal edilecek</li>
                        <li>Kullanıcının kupon kullanımları silinecek</li>
                        <li>Kullanıcı hesabı tamamen silinecek</li>
                    </ul>
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

