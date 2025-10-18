<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Zaten giriş yapmış kullanıcıları ana sayfaya yönlendir
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasyon
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = 'Lütfen tüm alanları doldurun.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error_message = 'Şifre en az 6 karakter olmalıdır.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir e-posta adresi girin.';
    } else {
        // E-posta kontrolü
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error_message = 'Bu e-posta adresi zaten kullanılıyor.';
        } else {
            // Kullanıcıyı kaydet
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, balance) VALUES (?, ?, ?, 'user', 100.00)");
            
            if ($stmt->execute([$full_name, $email, $hashed_password])) {
                setSuccessMessage('Kayıt başarılı! Giriş yapabilirsiniz.');
                header('Location: login.php');
                exit();
            } else {
                $error_message = 'Kayıt sırasında bir hata oluştu.';
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
    <title>Kayıt Ol - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="dark-theme">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Ana Sayfa</a>
                <a class="nav-link" href="login.php">Giriş Yap</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card bg-dark border-secondary">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                            <h3 class="text-white">Kayıt Ol</h3>
                            <p class="text-muted">HopBilet'e katılın ve yolculuğa başlayın</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Ad Soyad</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary">
                                        <i class="fas fa-user text-muted"></i>
                                    </span>
                                    <input type="text" class="form-control bg-dark border-secondary text-white" 
                                           id="full_name" name="full_name" placeholder="Adınız ve soyadınız" 
                                           value="<?php echo h($_POST['full_name'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary">
                                        <i class="fas fa-envelope text-muted"></i>
                                    </span>
                                    <input type="email" class="form-control bg-dark border-secondary text-white" 
                                           id="email" name="email" placeholder="ornek@email.com" 
                                           value="<?php echo h($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control bg-dark border-secondary text-white" 
                                           id="password" name="password" placeholder="En az 6 karakter" 
                                           minlength="6" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Şifre Tekrar</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control bg-dark border-secondary text-white" 
                                           id="confirm_password" name="confirm_password" placeholder="Şifrenizi tekrar girin" 
                                           minlength="6" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms" required>
                                    <label class="form-check-label text-muted" for="terms">
                                        <a href="#" class="text-primary">Kullanım şartlarını</a> ve 
                                        <a href="#" class="text-primary">gizlilik politikasını</a> kabul ediyorum.
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-user-plus me-2"></i>Kayıt Ol
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted">Zaten hesabınız var mı? 
                                <a href="login.php" class="text-primary text-decoration-none fw-bold">Giriş Yap</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script>
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

