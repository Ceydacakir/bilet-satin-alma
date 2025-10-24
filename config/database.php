<?php
// Veritabanı bağlantısı
$db_path = __DIR__ . '/../database/hop_bilet.db';

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Veritabanı tablolarını oluştur
function createTables($pdo) {
    // User tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id TEXT PRIMARY KEY,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role TEXT DEFAULT 'user' CHECK(role IN ('user', 'company', 'admin')),
            password VARCHAR(255) NOT NULL,
            company_id TEXT NULL,
            balance DECIMAL(10,2) DEFAULT 800.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id)
        )
    ");

    // Bus_Company tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bus_companies (
            id TEXT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            logo_path VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Coupons tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id TEXT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            discount DECIMAL(5,2) NOT NULL,
            company_id TEXT,
            usage_limit INTEGER DEFAULT 100,
            expire_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id)
        )
    ");

    // User_Coupons tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_coupons (
            id TEXT PRIMARY KEY,
            coupon_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Trips tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trips (
            id TEXT PRIMARY KEY,
            company_id TEXT NOT NULL,
            destination_city VARCHAR(50) NOT NULL,
            departure_city VARCHAR(50) NOT NULL,
            departure_time DATETIME NOT NULL,
            arrival_time DATETIME NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            capacity INTEGER DEFAULT 45,
            created_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id)
        )
    ");

    // Tickets tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tickets (
            id TEXT PRIMARY KEY,
            trip_id TEXT NOT NULL,
            user_id TEXT NOT NULL,
            status TEXT DEFAULT 'active' CHECK(status IN ('active', 'cancelled', 'expired')),
            total_price DECIMAL(10,2) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (trip_id) REFERENCES trips(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");

    // Booked_Seats tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS booked_seats (
            id TEXT PRIMARY KEY,
            ticket_id TEXT NOT NULL,
            seat_number INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        )
    ");
}

// Tabloları oluştur
createTables($pdo);

function getRandomCities() {
    $cities = ['İstanbul', 'Ankara', 'İzmir', 'Adana', 'Manisa'];
    $departure = $cities[array_rand($cities)];
    do {
        $destination = $cities[array_rand($cities)];
    } while ($departure === $destination);
    return [$departure, $destination];
}

function getRandomTripTimes() {
    $min_timestamp = strtotime('2025-10-24 00:00:00');
    $max_timestamp = strtotime('2025-11-14 23:59:59');
    $departure_timestamp = mt_rand($min_timestamp, $max_timestamp);
    
    $travel_duration_seconds = mt_rand(7 * 3600, 10 * 3600);
    $arrival_timestamp = $departure_timestamp + $travel_duration_seconds;

    $departure_time = date('Y-m-d H:i:s', $departure_timestamp);
    $arrival_time = date('Y-m-d H:i:s', $arrival_timestamp);

    return [$departure_time, $arrival_time];
}


function insertSampleData($pdo) {
    $companies_data = [
        ['RoketOtobüs', 'roket-otobus.png'],
        ['HopHop', 'hop-hop.png'],
        ['BulutTuru', 'bulut-turu.png'],
        ['UzayKoltuk', 'uzay-koltuk.png']
    ];

    $company_ids = [];
    $stmt_company = $pdo->prepare("INSERT OR IGNORE INTO bus_companies (id, name, logo_path) VALUES (?, ?, ?)");
    
    foreach ($companies_data as $company) {
        $company_id = uniqid('cmp_');
        $stmt_company->execute([$company_id, $company[0], $company[1]]);
        $company_ids[] = [
            'id' => $company_id,
            'name' => $company[0]
        ];
    }

    $companyAdminPassword = password_hash('company123', PASSWORD_DEFAULT);
    $userPassword = password_hash('user123', PASSWORD_DEFAULT);

    $stmt_user = $pdo->prepare("INSERT OR REPLACE INTO users (id, full_name, email, role, password, company_id, balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt_trip = $pdo->prepare("INSERT INTO trips (id, company_id, destination_city, departure_city, departure_time, arrival_time, price, capacity) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_coupon = $pdo->prepare("INSERT INTO coupons (id, code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?, ?)");
    
    $expire_date_6_months = date('Y-m-d', strtotime('+6 months'));
    $base_price = 45.00;
    $min_price = $base_price;
    $max_price = $base_price * 2;

    foreach ($company_ids as $company) {
        $admin_name = $company['name'] . ' Yöneticisi';
        $admin_email = strtolower(str_replace(' ', '', $company['name'])) . '@hopbilet.com';
        $admin_id = uniqid('usr_');

        $stmt_user->execute([
            $admin_id, 
            $admin_name, 
            $admin_email, 
            'company', 
            $companyAdminPassword, 
            $company['id'], 
            800.00
        ]);

        // 7 Rastgele Seyahat Ekle
        for ($i = 0; $i < 7; $i++) {
            list($departure_city, $destination_city) = getRandomCities();
            list($departure_time, $arrival_time) = getRandomTripTimes();
            $min_multiplier = ceil($min_price / 5);
            $max_multiplier = floor($max_price / 5);
            $rand_multiplier = mt_rand($min_multiplier, $max_multiplier);
            $price = $rand_multiplier * 5;
            $price = number_format($price, 2, '.', '');

            $stmt_trip->execute([
                uniqid('trip_'),
                $company['id'],
                $destination_city,
                $departure_city,
                $departure_time,
                $arrival_time,
                $price,
                45
            ]);
        }

        $coupon_code = strtoupper(substr($company['name'], 0, 3)) . 'IND' . mt_rand(10, 25);
        $discount = mt_rand(10, 30); // %10 ile %30 arası indirim

        $stmt_coupon->execute([
            uniqid('coup_'),
            $coupon_code,
            $discount,
            $company['id'],
            100,
            $expire_date_6_months
        ]);
    }

    // Genel Kupon Ekle (company_id NULL)
    $stmt_coupon->execute([
        uniqid('coup_'),
        'GENEL20',
        20.00, // %20 genel indirim
        NULL,
        500,
        $expire_date_6_months
    ]);

    // Örnek Yolcu Ekle
    $stmt_user->execute([
        uniqid('usr_'), 
        'Hop Yolcu', 
        'yolcu@hopbilet.com', 
        'user', 
        $userPassword, 
        NULL, 
        800.00
    ]);
}


$checkData = $pdo->query("SELECT COUNT(*) FROM bus_companies")->fetchColumn();
if ($checkData == 0) {
    insertSampleData($pdo);
} 
    
$adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, full_name,email, role, password, balance) VALUES (?, ?, ?, ?, ?, ?)");

$admin_exists = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
$admin_exists->execute(['admin@hopbilet.com']);

if ($admin_exists->fetchColumn() === false) {
    $stmt->execute([uniqid('usr_'), 'Admin User', 'admin@hopbilet.com','admin', $adminPassword, 10000.00]);
}
?>
