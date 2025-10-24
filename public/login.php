<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Lütfen tüm alanları doldurun.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['company_id'] = $user['company_id'];
                $_SESSION['balance'] = $user['balance'];
                
                setSuccessMessage('Başarıyla giriş yaptınız!');
                header('Location: index.php');
                exit();
            } else {
                $error_message = 'E-posta veya şifre hatalı.';
            }
        } else {
            $error_message = 'E-posta veya şifre hatalı.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - HopBilet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <link rel="shortcut icon" href="assets/images/favicon.ico" type="image/x-icon">
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">
    
</head>
<body class="dark-theme bus-background">
    <div class="bus bus-1"></div>
    <div class="bus bus-2"></div>
    <div class="bus bus-3"></div>
    <div class="bus bus-4"></div>
    <div class="bus bus-5"></div>
    
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-bus me-2"></i>HopBilet
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">Ana Sayfa</a>
                <a class="nav-link" href="register.php">Kayıt Ol</a>
            </div>
        </div>
    </nav>

    <div class="container mt-5" style="margin-bottom:64px;">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card bg-dark border-secondary">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-user-circle fa-3x text-primary mb-3"></i>
                            <h3 class="text-white">Giriş Yap</h3>
                            <p class="text-muted">Hesabınıza giriş yapın</p>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo h($error_message); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="needs-validation" novalidate>
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

                            <div class="mb-4">
                                <label for="password" class="form-label">Şifre</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary">
                                        <i class="fas fa-lock text-muted"></i>
                                    </span>
                                    <input type="password" class="form-control bg-dark border-secondary text-white" 
                                           id="password" name="password" placeholder="Şifrenizi girin" required>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted">Hesabınız yok mu? 
                                <a href="register.php" class="text-primary text-decoration-none fw-bold">Kayıt Ol</a>
                            </p>
                        </div>

                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Demo hesaplar:<br>
                                Admin: admin@hopbilet.com / admin123<br>
                                Firma: hophop@hopbilet.com / company123<br>
                                Yolcu: yolcu@hopbilet.com / user123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/script.js"></script>
</body>
</html>
