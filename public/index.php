<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Mesajlar -->
<div class="container" style="margin-top:24px;">
    <?php displayMessages(); ?>
</div>

<!-- Hero Section -->
<div class="hero-section">
        <div class="bus bus-1"></div>
        <div class="bus bus-2"></div>
        <div class="bus bus-3"></div>
        <div class="bus bus-4"></div>
        <div class="bus bus-5"></div>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="hero-content text-center text-white fade-in-up">
                        <h1 class="display-4 fw-bold mb-4">HopBilet ile Yolculuğa Başla</h1>
                        <p class="lead mb-5">Türkiye'nin en güvenilir otobüs bileti satış platformu</p>
                        
                        <!-- Search Form -->
                        <div class="search-form bg-dark p-4 rounded-3 shadow">
                            <form action="search.php" method="GET">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="departure_city" class="form-label">Nereden</label>
                                        <select class="form-select" id="departure_city" name="departure_city" required>
                                            <option value="">Şehir Seçin</option>
                                            <option value="İstanbul">İstanbul</option>
                                            <option value="Ankara">Ankara</option>
                                            <option value="İzmir">İzmir</option>
                                            <option value="Manisa">Manisa</option>
                                            <option value="Adana">Adana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="destination_city" class="form-label">Nereye</label>
                                        <select class="form-select" id="destination_city" name="destination_city" required>
                                            <option value="">Şehir Seçin</option>
                                            <option value="İstanbul">İstanbul</option>
                                            <option value="Ankara">Ankara</option>
                                            <option value="İzmir">İzmir</option>
                                            <option value="Manisa">Manisa</option>
                                            <option value="Adana">Adana</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="departure_date" class="form-label">Tarih</label>
                                        <input type="date" class="form-control" id="departure_date" name="departure_date" required>
                                    </div>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        <i class="fas fa-search me-2"></i>Sefer Ara
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</div>

<div class="container my-5">
    <div class="row">
        <div class="col-lg-4 mb-4">
            <div class="feature-card text-center p-4">
                <div class="feature-icon mb-3">
                    <i class="fas fa-shield-alt fa-3x text-primary"></i>
                </div>
                <h4>Güvenli Ödeme</h4>
                <p class="text-muted">Tüm ödemeleriniz güvenli şekilde işlenir ve korunur.</p>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="feature-card text-center p-4">
                <div class="feature-icon mb-3">
                    <i class="fas fa-clock fa-3x text-primary"></i>
                </div>
                <h4>7/24 Hizmet</h4>
                <p class="text-muted">İstediğiniz zaman bilet alabilir ve işlemlerinizi yapabilirsiniz.</p>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="feature-card text-center p-4">
                <div class="feature-icon mb-3">
                    <i class="fas fa-ticket-alt fa-3x text-primary"></i>
                </div>
                <h4>Kolay İptal</h4>
                <p class="text-muted">Kalkış saatinden 1 saat öncesine kadar iptal yapabilirsiniz.</p>
            </div>
        </div>
    </div>
</div>

<div class="container my-5">
    <h2 class="text-center mb-5">Popüler Güzergahlar</h2>
    <div class="row">
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="route-card p-3">
                <h5>İstanbul - Ankara</h5>
                <p class="text-muted">Başkent'e konforlu yolculuk</p>
                <span class="badge bg-primary">En Popüler</span>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="route-card p-3">
                <h5>İzmir - Ankara</h5>
                <p class="text-muted">Ege'den başkente</p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="route-card p-3">
                <h5>Manisa - Adana</h5>
                <p class="text-muted">Akdeniz'e uzanan yol</p>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>