<?php
// Veritabanı bağlantısı
$host = 'localhost';
$dbname = 'algezsneakers';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Numara ekleme işlemi
if ($_POST && isset($_POST['urun_id']) && isset($_POST['numara'])) {
    try {
        $urun_id = (int)$_POST['urun_id'];
        $numara = trim($_POST['numara']);
        
        // Aynı ürün için aynı numara var mı kontrol et
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM numaralar WHERE urun_id = ? AND numara = ?");
        $check_stmt->execute([$urun_id, $numara]);
        
        if ($check_stmt->fetchColumn() > 0) {
            $error_message = "Bu ürün için bu numara zaten mevcut!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO numaralar (urun_id, numara) VALUES (?, ?)");
            $stmt->execute([$urun_id, $numara]);
            $success_message = "Numara başarıyla eklendi!";
        }
    } catch(PDOException $e) {
        $error_message = "Numara eklenirken hata oluştu: " . $e->getMessage();
    }
}

// Numara silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    try {
        $numara_id = (int)$_GET['sil'];
        
        // Önce bu numaraya ait stok var mı kontrol et
        $check_stock = $pdo->prepare("SELECT COUNT(*) FROM stoklar WHERE numara_id = ?");
        $check_stock->execute([$numara_id]);
        
        if ($check_stock->fetchColumn() > 0) {
            $error_message = "Bu numaraya ait stok kayıtları var, önce stok kayıtlarını silin!";
        } else {
            $delete_stmt = $pdo->prepare("DELETE FROM numaralar WHERE numara_id = ?");
            $delete_stmt->execute([$numara_id]);
            $success_message = "Numara başarıyla silindi!";
        }
    } catch(PDOException $e) {
        $error_message = "Numara silinirken hata oluştu: " . $e->getMessage();
    }
}

// Kategorileri çek
try {
    $kategoriler_stmt = $pdo->prepare("SELECT * FROM kategoriler WHERE aktif_mi = 1 ORDER BY kategori_adi");
    $kategoriler_stmt->execute();
    $kategoriler = $kategoriler_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Kategoriler yüklenirken hata oluştu: " . $e->getMessage();
    $kategoriler = [];
}

// Seçilen kategori varsa ürünleri çek
$urunler = [];
$selected_kategori = null;
if (isset($_GET['kategori_id']) && is_numeric($_GET['kategori_id'])) {
    $selected_kategori = (int)$_GET['kategori_id'];
    try {
        $urunler_stmt = $pdo->prepare("SELECT * FROM urunler WHERE kategori_id = ? ORDER BY urun_adi");
        $urunler_stmt->execute([$selected_kategori]);
        $urunler = $urunler_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_message = "Ürünler yüklenirken hata oluştu: " . $e->getMessage();
    }
}

// Seçilen ürün varsa numaraları çek
$numaralar = [];
$selected_urun = null;
$urun_bilgisi = null;
if (isset($_GET['urun_id']) && is_numeric($_GET['urun_id'])) {
    $selected_urun = (int)$_GET['urun_id'];
    try {
        // Ürün bilgisini çek
        $urun_stmt = $pdo->prepare("
            SELECT u.*, k.kategori_adi 
            FROM urunler u 
            JOIN kategoriler k ON u.kategori_id = k.kategori_id 
            WHERE u.urun_id = ?
        ");
        $urun_stmt->execute([$selected_urun]);
        $urun_bilgisi = $urun_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Numaraları çek
        $numaralar_stmt = $pdo->prepare("SELECT * FROM numaralar WHERE urun_id = ? ORDER BY numara");
        $numaralar_stmt->execute([$selected_urun]);
        $numaralar = $numaralar_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_message = "Numaralar yüklenirken hata oluştu: " . $e->getMessage();
    }
}

// İstatistikleri hesapla
$toplam_numara = 0;
$toplam_urun_numara = 0;
try {
    $stats_stmt = $pdo->prepare("SELECT COUNT(*) as toplam FROM numaralar");
    $stats_stmt->execute();
    $toplam_numara = $stats_stmt->fetchColumn();
    
    $urun_stats_stmt = $pdo->prepare("SELECT COUNT(DISTINCT urun_id) as toplam FROM numaralar");
    $urun_stats_stmt->execute();
    $toplam_urun_numara = $urun_stats_stmt->fetchColumn();
} catch(PDOException $e) {
    // İstatistikler yüklenemedi
}

// Seçilen ürün varsa stok bilgilerini de çek
$stoklar = [];
if ($selected_urun) {
    try {
        $stok_stmt = $pdo->prepare("
            SELECT s.*, n.numara 
            FROM stoklar s 
            JOIN numaralar n ON s.numara_id = n.numara_id 
            WHERE s.urun_id = ?
            ORDER BY n.numara
        ");
        $stok_stmt->execute([$selected_urun]);
        $stoklar = $stok_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        $error_message = "Stok bilgileri yüklenirken hata oluştu: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgezSneakers - Numara Yönetimi</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="adminnumara.css"> 
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="logo">AlgezSneakers</div>
            <div class="admin-badge">
                <i class="fas fa-user-shield"></i>
                Admin Panel
            </div>
        </div>
    </div>

    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="admin_kategori.php"><i class="fas fa-tags"></i> Kategori Yönetimi</a></li>
            <li><a href="admin_urun.php"><i class="fas fa-box"></i> Ürün Yönetimi</a></li>
            <li><a href="admin_numara.php" class="active"><i class="fas fa-sort-numeric-up"></i> Numara/Stok Yönetimi</a></li>
            <li><a href="admin_siparis.php"><i class="fas fa-shopping-cart"></i> Sipariş Yönetimi</a></li>
            <li><a href="giris.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></li>
        </ul>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <h1 class="page-title">Numara Yönetimi</h1>
        <p class="page-subtitle">Ürünlere numara ekleyin ve yönetin</p>

        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <span class="stat-number"><?php echo $toplam_numara; ?></span>
                <div class="stat-label">Toplam Numara</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $toplam_urun_numara; ?></span>
                <div class="stat-label">Numarası Olan Ürün</div>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo count($kategoriler); ?></span>
                <div class="stat-label">Aktif Kategori</div>
            </div>
        </div>

        <!-- Navigation Steps -->
        <div class="nav-steps">
            <a href="admin_numara.php" class="nav-step <?php echo (!$selected_kategori && !$selected_urun) ? 'active' : ''; ?>">
                <i class="fas fa-tags"></i>
                1. Kategori Seç
            </a>
            <?php if ($selected_kategori): ?>
                <a href="admin_numara.php?kategori_id=<?php echo $selected_kategori; ?>" class="nav-step <?php echo ($selected_kategori && !$selected_urun) ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    2. Ürün Seç
                </a>
            <?php endif; ?>
            <?php if ($selected_urun): ?>
                <a href="admin_numara.php?kategori_id=<?php echo $selected_kategori; ?>&urun_id=<?php echo $selected_urun; ?>" class="nav-step active">
                    <i class="fas fa-sort-numeric-up"></i>
                    3. Numara Yönet
                </a>
            <?php endif; ?>
        </div>

        <?php if (!$selected_kategori): ?>
            <!-- Kategori Seçimi -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-tags"></i>
                    Kategori Seçin
                </h2>
                <div class="grid-3">
                    <?php foreach ($kategoriler as $kategori): ?>
                        <div class="product-card" onclick="location.href='admin_numara.php?kategori_id=<?php echo $kategori['kategori_id']; ?>'">
                            <h3><?php echo htmlspecialchars($kategori['kategori_adi']); ?></h3>
                            <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($kategori['aciklama']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

        <?php elseif (!$selected_urun): ?>
            <!-- Ürün Seçimi -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-box"></i>
                    Ürün Seçin
                </h2>
                <?php if (empty($urunler)): ?>
                    <p style="text-align: center; color: #666; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                        Bu kategoride henüz ürün bulunmuyor.
                    </p>
                <?php else: ?>
                    <div class="grid-3">
                        <?php foreach ($urunler as $urun): ?>
                            <div class="product-card" onclick="location.href='admin_numara.php?kategori_id=<?php echo $selected_kategori; ?>&urun_id=<?php echo $urun['urun_id']; ?>'">
                                <h3><?php echo htmlspecialchars($urun['urun_adi']); ?></h3>
                                <p><i class="fas fa-barcode"></i> SKU: <?php echo htmlspecialchars($urun['urun_kodu']); ?></p>
                                <p><i class="fas fa-money-bill-wave"></i> Fiyat: <?php echo number_format($urun['fiyat'], 2); ?> ₺</p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Numara Yönetimi -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-sort-numeric-up"></i>
                    <?php echo htmlspecialchars($urun_bilgisi['urun_adi']); ?> - Numara Yönetimi
                    <span style="font-size: 0.8rem; color: #666; margin-left: 10px;">
                        (Kategori: <?php echo htmlspecialchars($urun_bilgisi['kategori_adi']); ?>)
                    </span>
                </h2>

                <!-- Numara Ekleme Formu -->
                <form method="POST" action="" class="form-group">
                    <input type="hidden" name="urun_id" value="<?php echo $selected_urun; ?>">
                    <div style="display: flex; gap: 15px; align-items: flex-end;">
                        <div style="flex: 1;">
                            <label for="numara">Yeni Numara Ekle</label>
                            <input type="text" id="numara" name="numara" class="form-control" 
                                   placeholder="Örnek: 38, 39, 40 veya S, M, L" required>
                        </div>
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Numara Ekle
                        </button>
                    </div>
                </form>

                <!-- Numaralar Listesi -->
                <div class="number-tags">
                    <?php if (empty($numaralar)): ?>
                        <p style="text-align: center; color: #666; padding: 20px; width: 100%;">
                            <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                            Bu ürüne henüz numara eklenmemiş.
                        </p>
                    <?php else: ?>
                        <?php foreach ($numaralar as $numara): ?>
                            <div class="number-tag">
                                <span class="number"><?php echo htmlspecialchars($numara['numara']); ?></span>
                                <a href="admin_numara.php?kategori_id=<?php echo $selected_kategori; ?>&urun_id=<?php echo $selected_urun; ?>&sil=<?php echo $numara['numara_id']; ?>" 
                                   class="delete-btn" 
                                   onclick="return confirm('Bu numarayı silmek istediğinize emin misiniz?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stok Bilgileri -->
            <div class="card fade-in">
                <h2>
                    <i class="fas fa-warehouse"></i>
                    Stok Durumu
                </h2>
                
                <?php if (!empty($numaralar)): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Numara</th>
                                    <th>Stok Adedi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ($numaralar as $numara): 
                                    $stok_kaydi = null;
                                    if (!empty($stoklar)) {
                                        foreach ($stoklar as $stok) {
                                            if ($stok['numara_id'] == $numara['numara_id']) {
                                                $stok_kaydi = $stok;
                                                break;
                                            }
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($numara['numara']); ?></td>
                                        <td>
                                            <form method="POST" action="stok_guncelle.php" style="display: flex; gap: 10px;">
                                                <input type="hidden" name="urun_id" value="<?php echo $selected_urun; ?>">
                                                <input type="hidden" name="numara_id" value="<?php echo $numara['numara_id']; ?>">
                                                <input type="hidden" name="kategori_id" value="<?php echo $selected_kategori; ?>">
                                                <input type="number" name="stok_adedi" class="form-control" 
                                                       value="<?php echo $stok_kaydi ? $stok_kaydi['stok_adedi'] : 0; ?>" 
                                                       min="0" style="width: 100px;">
                                                <button type="submit" class="btn btn-sm">
                                                    <i class="fas fa-save"></i> Kaydet
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($stok_kaydi): ?>
                                                <a href="stok_sil.php?stok_id=<?php echo $stok_kaydi['stok_id']; ?>&urun_id=<?php echo $selected_urun; ?>&kategori_id=<?php echo $selected_kategori; ?>" 
                                                   class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('Bu stok kaydını silmek istediğinize emin misiniz?')">
                                                    <i class="fas fa-trash"></i> Sil
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 20px;">
                        <i class="fas fa-info-circle"></i>
                        Stok yönetimi için önce numara eklemelisiniz.
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

   
    <script src="adminnumara.js"></script>
</body>
</html>