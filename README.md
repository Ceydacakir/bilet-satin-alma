# HopBilet - Otobüs Bileti Satış Platformu

Modern web teknolojileri kullanılarak geliştirilmiş, dinamik ve çok kullanıcılı otobüs bileti satış platformu.

## 🚀 Özellikler

### 👥 Kullanıcı Rolleri
- **Ziyaretçi**: Sefer arama ve görüntüleme
- **Yolcu**: Bilet satın alma, iptal etme, PDF indirme
- **Firma Admin**: Sefer ve kupon yönetimi
- **Sistem Admin**: Tüm sistem yönetimi

### 🎫 Bilet Sistemi
- Koltuk seçimi ve rezervasyon
- Kupon sistemi ile indirim uygulaması
- Bilet iptal etme (kalkış saatinden 1 saat öncesine kadar)
- PDF bilet üretimi
- Sanal kredi sistemi

### 🏢 Firma Yönetimi
- Sefer ekleme, düzenleme, silme
- Kupon oluşturma ve yönetimi
- Satış istatistikleri
- Koltuk doluluk takibi

### 🔧 Admin Paneli
- Firma yönetimi
- Kullanıcı yönetimi
- Sistem istatistikleri
- Genel kupon yönetimi

## 🛠️ Teknolojiler

- **Backend**: PHP 8.1
- **Veritabanı**: SQLite
- **Frontend**: Bootstrap 5, JavaScript
- **Tema**: Modern koyu tema
- **Container**: Docker

## 📦 Kurulum

### Docker ile Kurulum

1. Projeyi klonlayın:
```bash
git clone https://github.com/username/bilet-satin-alma.git
cd bilet-satin-alma
```

2. Docker Compose ile başlatın:
```bash
docker-compose up -d
```

3. Tarayıcınızda `http://localhost:8080` adresine gidin.

### Manuel Kurulum

1. PHP 8.1+ ve SQLite kurulumu yapın
2. Proje dosyalarını web sunucunuzun root dizinine kopyalayın
3. `database` ve `uploads` klasörlerine yazma izni verin
4. Web sunucunuzu başlatın

## 🔑 Demo Hesaplar

### Admin Hesabı
- **E-posta**: admin@hopbilet.com
- **Şifre**: admin123

### Firma Admin Hesabı
- **E-posta**: roket@hopbilet.com
- **Şifre**: company123

## 📊 Veritabanı Şeması

### Ana Tablolar
- **users**: Kullanıcı bilgileri
- **bus_companies**: Otobüs firmaları
- **trips**: Seferler
- **tickets**: Biletler
- **booked_seats**: Rezerve koltuklar
- **coupons**: Kuponlar
- **user_coupons**: Kullanıcı-kupon ilişkisi

## 🎨 Tasarım

- Modern koyu tema
- Responsive tasarım
- Bootstrap 5 framework
- Font Awesome ikonları
- Gradient renkler ve animasyonlar

## 🔒 Güvenlik

- Güvenli oturum yönetimi
- SQL injection koruması
- XSS koruması
- Rol tabanlı yetkilendirme
- Dosya yükleme güvenliği

## 📱 Responsive Tasarım

- Mobil uyumlu arayüz
- Tablet ve desktop optimizasyonu
- Touch-friendly kontroller
- Adaptive layout

## 🚌 Örnek Firmalar

- RoketOtobüs
- AyTekerlek
- BulutTuru
- UzayKoltuk
- UçuşOtobüsü
- SüratTuru
- YıldızMacerası

## 🛣️ Örnek Güzergahlar

- İstanbul ↔ Ankara
- İzmir ↔ Ankara
- Manisa ↔ Adana
- Ve daha fazlası...

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır.

## 🤝 Katkıda Bulunma

1. Fork yapın
2. Feature branch oluşturun (`git checkout -b feature/AmazingFeature`)
3. Commit yapın (`git commit -m 'Add some AmazingFeature'`)
4. Push yapın (`git push origin feature/AmazingFeature`)
5. Pull Request oluşturun

## 📞 İletişim

Proje hakkında sorularınız için issue açabilirsiniz.

---

**HopBilet** ile yolculuğa başlayın! 🚌✨

