<?php
// Veritabanı bağlantı ayarları
$db_path = __DIR__ . '/../database/bus_tickets.db';

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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name VARCHAR(100) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            role TEXT DEFAULT 'user' CHECK(role IN ('user', 'company', 'admin')),
            password VARCHAR(255) NOT NULL,
            company_id INTEGER,
            balance DECIMAL(10,2) DEFAULT 0.00,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id)
        )
    ");

    // Bus_Company tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bus_companies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100) NOT NULL,
            logo_path VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Coupons tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code VARCHAR(20) UNIQUE NOT NULL,
            discount DECIMAL(5,2) NOT NULL,
            company_id INTEGER,
            usage_limit INTEGER DEFAULT 100,
            expire_date DATE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (company_id) REFERENCES bus_companies(id)
        )
    ");

    // User_Coupons tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            coupon_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id),
            FOREIGN KEY (user_id) REFERENCES users(id),
            UNIQUE(coupon_id, user_id)
        )
    ");

    // Trips tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS trips (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            company_id INTEGER NOT NULL,
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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            trip_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_id INTEGER NOT NULL,
            seat_number INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ticket_id) REFERENCES tickets(id)
        )
    ");
}

// Tabloları oluştur
createTables($pdo);

// Örnek verileri ekle
function insertSampleData($pdo) {
    // Otobüs firmalarını ekle
    $companies = [
        ['RoketOtobüs', 'assets/images/roket-otobus.png'],
        ['AyTekerlek', 'assets/images/ay-tekerlek.png'],
        ['BulutTuru', 'assets/images/bulut-turu.png'],
        ['UzayKoltuk', 'assets/images/uzay-koltuk.png'],
        ['UçuşOtobüsü', 'assets/images/ucus-otobusu.png'],
        ['SüratTuru', 'assets/images/surat-turu.png'],
        ['YıldızMacerası', 'assets/images/yildiz-macerasi.png']
    ];

    foreach ($companies as $company) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO bus_companies (name, logo_path) VALUES (?, ?)");
        $stmt->execute($company);
    }

    // Admin kullanıcısı oluştur (ID 1 ile)
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, full_name, email, role, password, balance) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Admin User', 'admin@hopbilet.com', 'admin', $adminPassword, 10000.00]);

    // Firma admin kullanıcıları oluştur
    $companyAdmins = [
        ['RoketOtobüs Admin', 'roket@hopbilet.com', 1],
        ['AyTekerlek Admin', 'ay@hopbilet.com', 2],
        ['BulutTuru Admin', 'bulut@hopbilet.com', 3]
    ];

    foreach ($companyAdmins as $admin) {
        $password = password_hash('company123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO users (full_name, email, role, password, company_id, balance) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$admin[0], $admin[1], 'company', $password, $admin[2], 5000.00]);
    }

    // Örnek seferler ekle: 23.10.2025 tarihine güncellendi
    $trips = [
        [1, 'İstanbul', 'Ankara', '2025-10-23 08:00:00', '2025-10-23 12:00:00', 350.00, 45],
        [1, 'Ankara', 'İstanbul', '2025-10-23 14:30:00', '2025-10-23 18:30:00', 350.00, 45],
        [2, 'İzmir', 'Ankara', '2025-10-23 09:00:00', '2025-10-23 15:00:00', 420.00, 45],
        [2, 'Ankara', 'İzmir', '2025-10-23 16:00:00', '2025-10-23 22:00:00', 420.00, 45],
        [3, 'Manisa', 'Adana', '2025-10-23 10:00:00', '2025-10-23 18:00:00', 390.00, 45],
        [3, 'Adana', 'Manisa', '2025-10-23 19:00:00', '2025-10-24 03:00:00', 390.00, 45]
    ];

    foreach ($trips as $trip) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO trips (company_id, destination_city, departure_city, departure_time, arrival_time, price, capacity) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute($trip);
    }

    // Örnek kuponlar ekle
    $coupons = [
        ['WELCOME10', 10.00, 1, 50, '2024-12-31'],
        ['SAVE20', 20.00, 2, 30, '2024-12-31'],
        ['SUMMER15', 15.00, null, 100, '2024-12-31']
    ];

    foreach ($coupons as $coupon) {
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO coupons (code, discount, company_id, usage_limit, expire_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute($coupon);
    }
}

// Örnek verileri ekle (sadece ilk çalıştırmada)
$checkData = $pdo->query("SELECT COUNT(*) FROM bus_companies")->fetchColumn();
if ($checkData == 0) {
    insertSampleData($pdo);
} else {
    // Admin kullanıcısını her zaman güncelle
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, full_name, email, role, password, balance) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, 'Admin User', 'admin@hopbilet.com', 'admin', $adminPassword, 10000.00]);
}
?>
