<?php
session_start();

if (!isset($_SESSION['kullanici_id'])) {
    echo "Oturum açmanız gerekiyor.";
    exit();
}

$host = 'localhost';
$db   = 'Algezsneakers';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "Veritabanı bağlantı hatası: " . $e->getMessage();
    exit();
}

$kullanici_id = $_SESSION['kullanici_id'];

try {
    // 1. Sepeti al
    $stmt = $pdo->prepare("SELECT * FROM sepet WHERE kullanici_id = ?");
    $stmt->execute([$kullanici_id]);
    $sepet_urunleri = $stmt->fetchAll();

    if (count($sepet_urunleri) === 0) {
        echo "Sepetiniz boş.";
        exit();
    }

    // 2. Toplam tutarı hesapla
    $toplam_tutar = 0;
    foreach ($sepet_urunleri as $urun) {
        $toplam_tutar += $urun['fiyat'] * $urun['adet'];
    }

    // 3. Siparişi oluştur (siparisler tablosu!)
    $stmt = $pdo->prepare("INSERT INTO siparisler (kullanici_id, toplam_tutar, siparis_tarihi, durum) VALUES (?, ?, NOW(), 'Hazırlanıyor')");
    $stmt->execute([$kullanici_id, $toplam_tutar]);
    $siparis_id = $pdo->lastInsertId();

    // 4. Ürünleri siparis_urunleri tablosuna aktar
    $stmt = $pdo->prepare("INSERT INTO siparis_urunleri (siparis_id, urun_id, numara_id, adet, birim_fiyat) VALUES (?, ?, ?, ?, ?)");
    foreach ($sepet_urunleri as $urun) {
        // numara_id'yi bul
        $numara = $urun['numara'];
        $sorgu = $pdo->prepare("SELECT numara_id FROM numaralar WHERE numara = ?");
        $sorgu->execute([$numara]);
        $numara_kayit = $sorgu->fetch();

        if (!$numara_kayit) {
            echo "Numara ID bulunamadı: " . htmlspecialchars($numara);
            exit();
        }

        $numara_id = $numara_kayit['numara_id'];

        $stmt->execute([
            $siparis_id,
            $urun['urun_id'],
            $numara_id,
            $urun['adet'],
            $urun['fiyat']
        ]);
    }

    // 5. Sepeti temizle
    $stmt = $pdo->prepare("DELETE FROM sepet WHERE kullanici_id = ?");
    $stmt->execute([$kullanici_id]);

    echo "Sipariş başarıyla tamamlandı. Sipariş Numaranız: #$siparis_id";

} catch (PDOException $e) {
    echo "Sipariş işlemi sırasında hata oluştu: " . $e->getMessage();
}
