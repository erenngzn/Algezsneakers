<?php
// HATA AYIKLAMA İÇİN TÜM HATALARI GÖSTER
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';    // Veritabanı sunucusu
$db   = 'Algezsneakers';  // Veritabanı adı (kendine göre değiştir)
$user = 'root';         // Veritabanı kullanıcı adı
$pass = '';             // Veritabanı şifresi
$charset = 'utf8mb4';   // Karakter seti

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Hataları yakalamak için
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Varsayılan fetch modu
    PDO::ATTR_EMULATE_PREPARES   => false,                   // Gerçek prepared statement kullanımı
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Bağlantı başarısızsa burası çalışır
    echo "Veritabanı bağlantı hatası: " . $e->getMessage();
    exit();
}
