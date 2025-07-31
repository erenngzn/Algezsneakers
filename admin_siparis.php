<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$baglanti = new mysqli("localhost", "root", "", "Algezsneakers");
if ($baglanti->connect_error) die("Bağlantı hatası: " . $baglanti->connect_error);

// Sipariş iptal
if (isset($_GET['iptal'])) {
    $siparis_id = (int)$_GET['iptal'];
    $baglanti->query("UPDATE siparisler SET durum = 'İptal' WHERE siparis_id = $siparis_id");
    header("Location: admin_siparis.php");
    exit;
}

// Siparişler
$siparisler = $baglanti->query("
    SELECT s.*, k.kullanici_adi 
    FROM siparisler s
    LEFT JOIN kullanicilar k ON s.kullanici_id = k.kullanici_id
    ORDER BY s.siparis_tarihi DESC
");

if (!$siparisler) {
    die("Sorgu hatası: " . $baglanti->error);
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>AlgezSneakers - Sipariş Yönetimi</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminsiparis.css" />
    <style>
        body { font-family: Arial; margin: 20px; background: #f1f1f1; }
        h1 { text-align: center; }
        table { width: 100%; border-collapse: collapse; background: #fff; }
        th, td { padding: 10px; border: 1px solid #ccc; text-align: left; }
        th { background: #333; color: #fff; }
        .btn { padding: 5px 10px; text-decoration: none; border-radius: 5px; }
        .btn-danger { background: #e74c3c; color: #fff; }
        .btn-detail { background: #3498db; color: #fff; }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="spinner"></div>
    </div>
    
    <div class="header">
        <div class="header-content">
            <div class="logo">AlgezSneakers</div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                Admin Panel
            </div>
        </div>
    </div>

     <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

     <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="#" class="active"><i class="fas fa-tags"></i> Kategori Yönetimi</a></li>
            <li><a href="admin_urun.php"><i class="fas fa-box"></i> Ürün Yönetimi</a></li>
            <li><a href="admin_numara.php" class="active"><i class="fas fa-sort-numeric-up"></i> Numara/Stok Yönetimi</a></li>
            <li><a href="admin_siparis.php"><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</a></li>
            <li><a href="giris.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
            
        </ul>
    </div>

    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <div class="main-content" id="mainContent">
        <h1 class="page-title">Sipariş Yönetimi</h1>
        <p class="page-subtitle">Siparişleri Kontrol Edin, Düzenleyin.</p>

    <?php if ($siparisler->num_rows === 0): ?>
        <p style="text-align:center;">Hiç sipariş bulunamadı.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Kullanıcı</th>
                    <th>Toplam Tutar</th>
                    <th>Sipariş Tarihi</th>
                    <th>Durum</th>
                    <th>İşlem</th>
                </tr>
            </thead>
            <tbody>
                <?php while($siparis = $siparisler->fetch_assoc()): ?>
                <tr>
                    <td><?= $siparis['siparis_id'] ?></td>
                    <td><?= htmlspecialchars($siparis['kullanici_adi'] ?? 'Bilinmiyor') ?></td>
                    <td><?= number_format($siparis['toplam_tutar'], 2) ?> ₺</td>
                    <td><?= $siparis['siparis_tarihi'] ?></td>
                    <td><?= htmlspecialchars($siparis['durum']) ?></td>
                    <td>
                        <a href="admin_siparis_detay.php?siparis_id=<?= $siparis['siparis_id'] ?>" class="btn btn-detail">Detay</a>
                        <?php if ($siparis['durum'] !== 'İptal'): ?>
                        <a href="admin_siparis.php?iptal=<?= $siparis['siparis_id'] ?>" class="btn btn-danger" onclick="return confirm('Siparişi iptal etmek istediğinize emin misiniz?')">İptal Et</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
    <script src="adminsiparis.js"></script>
</body>
</html>
