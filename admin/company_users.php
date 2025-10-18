<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Admin kontrolü
requireRole('admin');

$company_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Firma bilgisi
$stmt = $pdo->prepare("SELECT * FROM bus_companies WHERE id = ?");
$stmt->execute([$company_id]);
$company = $stmt->fetch();

if (!$company) {
    setErrorMessage('Firma bulunamadı.');
    header('Location: companies.php');
    exit();
}

$error_message = '';

// Yeni admin ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($full_name === '' || strlen($full_name) < 2) {
        $error_message = 'Ad soyad en az 2 karakter olmalı.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta girin.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalı.';
    } else {
        // E-posta benzersizliği
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta zaten kullanımda.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, role, password, company_id, balance) VALUES (?, ?, 'company', ?, ?, 0.0)");
            if ($stmt->execute([$full_name, $email, $hashed, $company_id])) {
                setSuccessMessage('Firma admini oluşturuldu ve atandı.');
                header('Location: company_users.php?id=' . $company_id);
                exit();
            } else {
                $error_message = 'Firma admini oluşturulamadı.';
            }
        }
    }
}

// Admin güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_admin'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $full_name = trim($_POST['edit_full_name'] ?? '');
    $email = trim($_POST['edit_email'] ?? '');
    $password = $_POST['edit_password'] ?? '';

    // İlgili kullanıcının bu firmaya ait olduğundan emin ol
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'company' AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    $target = $stmt->fetch();

    if (!$target) {
        setErrorMessage('Kullanıcı bulunamadı veya bu firmaya ait değil.');
        header('Location: company_users.php?id=' . $company_id);
        exit();
    }

    if ($full_name === '' || strlen($full_name) < 2) {
        $error_message = 'Ad soyad en az 2 karakter olmalı.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta girin.';
    } else {
        // E-posta benzersizliği (kendisi hariç)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta başka bir kullanıcı tarafından kullanılıyor.';
        } else {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?");
                $ok = $stmt->execute([$full_name, $email, $hashed, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
                $ok = $stmt->execute([$full_name, $email, $user_id]);
            }
            if ($ok) {
                setSuccessMessage('Firma admini güncellendi.');
                header('Location: company_users.php?id=' . $company_id);
                exit();
            } else {
                $error_message = 'Güncelleme sırasında bir hata oluştu.';
            }
        }
    }
}

// Admin kaldır (rol düşür)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_admin'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'company' AND company_id = ?");
    $stmt->execute([$user_id, $company_id]);
    if (!$stmt->fetch()) {
        setErrorMessage('Kullanıcı bulunamadı veya bu firmaya ait değil.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET role = 'user', company_id = NULL WHERE id = ?");
        if ($stmt->execute([$user_id])) {
            setSuccessMessage('Firma admini kaldırıldı.');
        } else {
            setErrorMessage('Firma admini kaldırılamadı.');
        }
    }
    header('Location: company_users.php?id=' . $company_id);
    exit();
}

// Mevcut firma adminleri
$stmt = $pdo->prepare("SELECT id, full_name, email, created_at FROM users WHERE role = 'company' AND company_id = ? ORDER BY created_at DESC");
$stmt->execute([$company_id]);
$admins = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firma Adminleri - HopBilet</title>
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
                    <li class="nav-item"><a class="nav-link" href="dashboard.php">Admin Paneli</a></li>
                    <li class="nav-item"><a class="nav-link active" href="companies.php">Firmalar</a></li>
                    <li class="nav-item"><a class="nav-link" href="coupons.php">Kuponlar</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item"><a class="nav-link" href="../logout.php">Çıkış Yap</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h2 class="text-white"><i class="fas fa-user-shield me-2"></i>Firma Adminleri</h2>
                <p class="text-muted">Firma: <span class="text-info"><?php echo h($company['name']); ?></span></p>
            </div>
            <a href="companies.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Firmalara Dön</a>
        </div>

        <?php displayMessages(); ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo h($error_message); ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-7">
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-body">
                        <h5 class="text-white mb-3"><i class="fas fa-list me-2"></i>Mevcut Adminler</h5>
                        <?php if (empty($admins)): ?>
                            <div class="text-muted">Bu firmaya atanmış admin yok.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-dark table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>E-posta</th>
                                            <th>Oluşturulma</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $u): ?>
                                            <tr>
                                                <td><?php echo h($u['full_name']); ?></td>
                                                <td><?php echo h($u['email']); ?></td>
                                                <td><?php echo formatDate($u['created_at'], 'd.m.Y'); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary" title="Düzenle"
                                                            onclick="openEditModal(<?php echo (int)$u['id']; ?>, '<?php echo h($u['full_name']); ?>', '<?php echo h($u['email']); ?>')">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" style="display:inline">
                                                            <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                                            <button type="submit" name="remove_admin" class="btn btn-outline-danger" title="Kaldır"
                                                                onclick="return confirm('Bu kullanıcıyı firma adminliğinden kaldırmak istediğinize emin misiniz?');">
                                                                <i class="fas fa-user-minus"></i>
                                                            </button>
                                                        </form>
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

            <div class="col-lg-5">
                <div class="card bg-dark border-secondary mb-4">
                    <div class="card-body">
                        <h5 class="text-white mb-3"><i class="fas fa-user-plus me-2"></i>Yeni Admin Ata</h5>
                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label">Ad Soyad</label>
                                <input type="text" name="full_name" class="form-control" required maxlength="100" value="<?php echo h($_POST['full_name'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">E-posta</label>
                                <input type="email" name="email" class="form-control" required value="<?php echo h($_POST['email'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Şifre</label>
                                <input type="password" name="password" class="form-control" required minlength="6">
                                <div class="form-text text-muted">En az 6 karakter</div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="add_admin" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Oluştur ve Ata
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Düzenleme Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header">
                    <h5 class="modal-title text-white">Admin Düzenle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Ad Soyad</label>
                            <input type="text" name="edit_full_name" id="edit_full_name" class="form-control" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">E-posta</label>
                            <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yeni Şifre (opsiyonel)</label>
                            <input type="password" name="edit_password" id="edit_password" class="form-control" minlength="6" placeholder="Boş bırakırsanız değişmez">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="update_admin" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openEditModal(id, name, email) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_full_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = '';
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
    </script>
</body>
</html>
