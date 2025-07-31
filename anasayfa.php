<?php
session_start();

// Veritabanı bağlantısını dahil et
$host = 'localhost';
$db   = 'Algezsneakers';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    echo "Veritabanı bağlantı hatası: " . $e->getMessage();
    exit();
}

// Kullanıcı giriş çıkış işlemleri
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit();
}

// AJAX istekleri için
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_products' && isset($_GET['kategori_id'])) {
        try {
            $kategori_id = intval($_GET['kategori_id']);
            
            // Kategori bilgisini al
            $kategori_stmt = $pdo->prepare("SELECT kategori_adi FROM kategoriler WHERE kategori_id = ?");
            $kategori_stmt->execute([$kategori_id]);
            $kategori = $kategori_stmt->fetch();
            
            // Ürünleri al
            $stmt = $pdo->prepare("
                SELECT u.urun_id, u.urun_adi, u.aciklama, u.marka, u.fiyat, u.resim,
                       GROUP_CONCAT(DISTINCT n.numara ORDER BY n.numara ASC) as mevcut_numaralar,
                       SUM(CASE WHEN s.stok_adedi > 0 THEN 1 ELSE 0 END) as stok_durumu
                FROM urunler u
                LEFT JOIN stoklar s ON u.urun_id = s.urun_id
                LEFT JOIN numaralar n ON s.numara_id = n.numara_id AND s.stok_adedi > 0
                WHERE u.kategori_id = ? AND u.aktif_mi = 1
                GROUP BY u.urun_id
                ORDER BY u.eklenme_tarihi DESC
            ");
            $stmt->execute([$kategori_id]);
            $urunler = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'kategori' => $kategori,
                'urunler' => $urunler
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_product_detail' && isset($_GET['urun_id'])) {
        try {
            $urun_id = intval($_GET['urun_id']);
            
            // Ürün detayı al
            $stmt = $pdo->prepare("
                SELECT u.*, k.kategori_adi
                FROM urunler u
                JOIN kategoriler k ON u.kategori_id = k.kategori_id
                WHERE u.urun_id = ? AND u.aktif_mi = 1
            ");
            $stmt->execute([$urun_id]);
            $urun = $stmt->fetch();
            
            // Numara ve stok bilgilerini al
            $stok_stmt = $pdo->prepare("
                SELECT n.numara, s.stok_adedi
                FROM stoklar s
                JOIN numaralar n ON s.numara_id = n.numara_id
                WHERE s.urun_id = ? AND s.stok_adedi > 0
                ORDER BY n.numara ASC
            ");
            $stok_stmt->execute([$urun_id]);
            $stoklar = $stok_stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'urun' => $urun,
                'stoklar' => $stoklar
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    // Sepet işlemleri
    if ($_GET['action'] == 'add_to_cart' && isset($_GET['urun_id']) && isset($_SESSION['kullanici_id'])) {
        try {
            $urun_id = intval($_GET['urun_id']);
            $user_id = $_SESSION['kullanici_id'];
            $numara = isset($_GET['numara']) ? $_GET['numara'] : null;
            $adet = isset($_GET['adet']) ? intval($_GET['adet']) : 1;
            
            // Ürün fiyatını al
            $stmt = $pdo->prepare("SELECT fiyat FROM urunler WHERE urun_id = ?");
            $stmt->execute([$urun_id]);
            $urun = $stmt->fetch();
            
            if (!$urun) {
                echo json_encode(['success' => false, 'error' => 'Ürün bulunamadı']);
                exit();
            }
            
            // Sepette var mı kontrol et
            $stmt = $pdo->prepare("SELECT * FROM sepet WHERE kullanici_id = ? AND urun_id = ? AND numara = ?");
            $stmt->execute([$user_id, $urun_id, $numara]);
            $sepet_urun = $stmt->fetch();
            
            if ($sepet_urun) {
                // Güncelle
                $stmt = $pdo->prepare("UPDATE sepet SET adet = adet + ? WHERE sepet_id = ?");
                $stmt->execute([$adet, $sepet_urun['sepet_id']]);
            } else {
                // Yeni ekle
                $stmt = $pdo->prepare("INSERT INTO sepet (kullanici_id, urun_id, numara, adet, fiyat) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $urun_id, $numara, $adet, $urun['fiyat']]);
            }
            
            echo json_encode(['success' => true]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'remove_from_cart' && isset($_GET['sepet_id']) && isset($_SESSION['kullanici_id'])) {
        try {
            $sepet_id = intval($_GET['sepet_id']);
            $user_id = $_SESSION['kullanici_id'];
            
            $stmt = $pdo->prepare("DELETE FROM sepet WHERE sepet_id = ? AND kullanici_id = ?");
            $stmt->execute([$sepet_id, $user_id]);
            
            echo json_encode(['success' => true]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    if ($_GET['action'] == 'get_cart' && isset($_SESSION['kullanici_id'])) {
        try {
            $user_id = $_SESSION['kullanici_id'];
            
            $stmt = $pdo->prepare("
                SELECT s.*, u.urun_adi, u.resim, u.marka
                FROM sepet s
                JOIN urunler u ON s.urun_id = u.urun_id
                WHERE s.kullanici_id = ?
            ");
            $stmt->execute([$user_id]);
            $sepet = $stmt->fetchAll();
            
            // Toplam tutar hesapla
            $toplam = 0;
            foreach ($sepet as $item) {
                $toplam += $item['fiyat'] * $item['adet'];
            }
            
            echo json_encode([
                'success' => true,
                'sepet' => $sepet,
                'toplam' => $toplam
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    // Favori işlemleri
    if ($_GET['action'] == 'toggle_favorite' && isset($_GET['urun_id']) && isset($_SESSION['kullanici_id'])) {
        try {
            $urun_id = intval($_GET['urun_id']);
            $user_id = $_SESSION['kullanici_id'];
            
            // Favoride var mı kontrol et
            $stmt = $pdo->prepare("SELECT * FROM favoriler WHERE kullanici_id = ? AND urun_id = ?");
            $stmt->execute([$user_id, $urun_id]);
            $favori = $stmt->fetch();
            
            if ($favori) {
                // Kaldır
                $stmt = $pdo->prepare("DELETE FROM favoriler WHERE favori_id = ?");
                $stmt->execute([$favori['favori_id']]);
                $is_favorite = false;
            } else {
                // Ekle
                $stmt = $pdo->prepare("INSERT INTO favoriler (kullanici_id, urun_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $urun_id]);
                $is_favorite = true;
            }
            
            echo json_encode(['success' => true, 'is_favorite' => $is_favorite]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    if ($_GET['action'] == 'get_orders' && isset($_SESSION['kullanici_id'])) {
    try {
        $user_id = $_SESSION['kullanici_id'];
        
        // Siparişleri ve sipariş ürünlerini tek sorguda al
        $stmt = $pdo->prepare("
            SELECT 
                s.siparis_id,
                s.toplam_tutar,
                s.siparis_tarihi,
                s.durum,
                su.urun_id,
                su.numara_id,
                su.adet,
                su.birim_fiyat,
                u.urun_adi,
                u.marka,
                u.resim,
                n.numara
            FROM siparisler s
            LEFT JOIN siparis_urunleri su ON s.siparis_id = su.siparis_id
            LEFT JOIN urunler u ON su.urun_id = u.urun_id
            LEFT JOIN numaralar n ON su.numara_id = n.numara_id
            WHERE s.kullanici_id = ?
            ORDER BY s.siparis_tarihi DESC
        ");
        $stmt->execute([$user_id]);
        $results = $stmt->fetchAll();
        
        // Siparişleri grupla
        $siparisler = [];
        foreach ($results as $row) {
            $siparis_id = $row['siparis_id'];
            
            if (!isset($siparisler[$siparis_id])) {
                $siparisler[$siparis_id] = [
                    'siparis_id' => $row['siparis_id'],
                    'toplam_tutar' => $row['toplam_tutar'],
                    'siparis_tarihi' => $row['siparis_tarihi'],
                    'durum' => $row['durum'],
                    'urunler' => []
                ];
            }
            
            if ($row['urun_id']) {
                $siparisler[$siparis_id]['urunler'][] = [
                    'urun_id' => $row['urun_id'],
                    'urun_adi' => $row['urun_adi'],
                    'marka' => $row['marka'],
                    'resim' => $row['resim'],
                    'numara' => $row['numara'],
                    'adet' => $row['adet'],
                    'birim_fiyat' => $row['birim_fiyat']
                ];
            }
        }
        
        // Array'i tekrar index'le
        $siparisler = array_values($siparisler);
        
        echo json_encode([
            'success' => true,
            'siparisler' => $siparisler
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
    
    if ($_GET['action'] == 'get_favorites' && isset($_SESSION['kullanici_id'])) {
        try {
            $user_id = $_SESSION['kullanici_id'];
            
            $stmt = $pdo->prepare("
                SELECT u.*, 
                       GROUP_CONCAT(DISTINCT n.numara ORDER BY n.numara ASC) as mevcut_numaralar
                FROM favoriler f
                JOIN urunler u ON f.urun_id = u.urun_id
                LEFT JOIN stoklar s ON u.urun_id = s.urun_id
                LEFT JOIN numaralar n ON s.numara_id = n.numara_id AND s.stok_adedi > 0
                WHERE f.kullanici_id = ?
                GROUP BY u.urun_id
                ORDER BY u.eklenme_tarihi DESC
            ");
            $stmt->execute([$user_id]);
            $favoriler = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'favoriler' => $favoriler
            ]);
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    
    // Kullanıcı profili
    if ($_GET['action'] == 'get_user_profile' && isset($_SESSION['kullanici_id'])) {
        try {
            $user_id = $_SESSION['kullanici_id'];
            
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE kullanici_id = ?");
            $stmt->execute([$user_id]);
            $kullanici = $stmt->fetch();
            
            if ($kullanici) {
                // Hassas bilgileri temizle
                unset($kullanici['sifre']);
                echo json_encode(['success' => true, 'kullanici' => $kullanici]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı']);
            }
            
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}

// Aktif kategorileri veritabanından çek
try {
    $stmt = $pdo->prepare("SELECT kategori_id, kategori_adi, aciklama FROM kategoriler WHERE aktif_mi = 1 ORDER BY kategori_adi ASC");
    $stmt->execute();
    $kategoriler = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Kategori verisi çekme hatası: " . $e->getMessage();
    $kategoriler = [];
}

// Kullanıcı giriş durumunu kontrol et
$isLoggedIn = isset($_SESSION['kullanici_id']);

// Kategori ikonları için dizi
function getKategoriIcon($kategori_adi) {
    // Küçük harfe çevirip, boşlukları ve özel karakterleri kaldırabiliriz
    $temiz_adi = strtolower(trim($kategori_adi));
    $temiz_adi = preg_replace('/[^a-z0-9]/', '', $temiz_adi);

    $dosya_yolu = "../images/{$temiz_adi}.png";

    // Dosya var mı kontrol et
    if (file_exists(__DIR__ . "/$dosya_yolu")) {
        return '<img src="' . htmlspecialchars($dosya_yolu) . '" alt="' . htmlspecialchars($kategori_adi) . '" style="height:24px; width:auto;">';
    } else {
        // Varsayılan logo
        return '<img src="images/default.png" alt="default" style="height:24px; width:auto;">';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AlgezSneakers - Premium Ayakkabı Mağazası</title>
    <link rel="icon" type="image/jpeg" href="../images/logo.jpg">
    <link href="https://fonts.googleapis.com/css2?family=Raleway:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="anasayfa.css">   
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <a href="#" class="logo" onclick="showHome()">
                
                Algez Sneakers
            </a>
            
            <div class="user-section">
                <?php if ($isLoggedIn): ?>
                    <div class="profile-section active">
                        
                        <button class="profile-btn" onclick="toggleProfileDropdown()">
                            <?php echo isset($_SESSION['kullanici_adi']) ? $_SESSION['kullanici_adi'] : 'Profil'; ?>
                            <i class="fas fa-chevron-down dropdown-toggle" id="dropdownToggle"></i>
                        </button>
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="#" onclick="showProfile()"><i class="fas fa-user"></i> Profilim</a>
                            <a href="#" onclick="showCart()"><i class="fas fa-shopping-cart"></i> Sepetim</a>
                            <a href="#" onclick="showFavorites()"><i class="fas fa-heart"></i> Favorilerim</a>
                            <a href="#" onclick="showOrders()"><i class="fas fa-box"></i> Siparişlerim</a>
                            <a href="giris.php"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
                        </div>
                    </div>
                <?php else: ?>
                    
                    <div class="login-section">
                        <a href="giris.php" class="login-btn">
                            <i class="fas fa-sign-in-alt"></i> Giriş Yap
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        ☰
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <h3>Kategoriler</h3>
        
        <?php if (!empty($kategoriler)): ?>
            <?php foreach ($kategoriler as $kategori): ?>
                <a href="#" 
                   class="category-item"
                   data-kategori-id="<?php echo $kategori['kategori_id']; ?>"
                   onclick="loadProducts(<?php echo $kategori['kategori_id']; ?>, '<?php echo htmlspecialchars($kategori['kategori_adi']); ?>')"
                   title="<?php echo htmlspecialchars($kategori['aciklama']); ?>">
                    <?php echo getKategoriIcon($kategori['kategori_adi']); ?> 
                    <?php echo htmlspecialchars($kategori['kategori_adi']); ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #666; padding: 15px;">Henüz kategori bulunmamaktadır.</p>
        <?php endif; ?>
        
        <!-- Hızlı Erişim Linkleri -->
        
    </nav>

    <!-- Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Ana Sayfa İçeriği -->
        <div class="home-content" id="homeContent">
            <section class="hero-section">
                <h1 class="hero-title">AlgezSneakers</h1>
                <p class="hero-subtitle">Premium ayakkabı koleksiyonuyla stilinizi yansıtın</p>
            </section>

            <section class="brand-panels">
                <div class="brand-panel nike-panel" >
                    <div class="panel-content">
                        <h2 class="panel-title">Nike</h2>
                        <p class="panel-subtitle">Just Do It - Premium Nike Koleksiyonu</p>
                    </div>
                </div>

                <div class="brand-panel adidas-panel" >
                    <div class="panel-content">
                        <h2 class="panel-title">Adidas</h2>
                        <p class="panel-subtitle">Impossible is Nothing - Adidas Dünyası</p>
                    </div>
                </div>

                <div class="brand-panel puma-panel" >
                    <div class="panel-content">
                        <h2 class="panel-title">Puma</h2>
                        <p class="panel-subtitle">Forever Faster - Puma Koleksiyonu</p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Ürün Listesi İçeriği -->
        <div class="products-content" id="productsContent">
            <div class="breadcrumb" id="breadcrumb">
                <a href="#" class="breadcrumb-item" onclick="showHome()">Ana Sayfa</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current" id="currentCategory">Kategori</span>
            </div>

            <div class="products-header">
                <h2 class="products-title" id="productsTitle">Ürünler</h2>
                <span class="products-count" id="productsCount">0 ürün</span>
            </div>

            <div class="loading" id="loadingProducts">
                <div class="loading-spinner"></div>
                <p>Ürünler yükleniyor...</p>
            </div>

            <div class="products-grid" id="productsGrid">
                <!-- Ürünler buraya dinamik olarak eklenecek -->
            </div>
        </div>

        <!-- Ürün Detay Sayfası -->
        <div class="product-detail" id="productDetail">
            <div class="detail-header">
                <button class="back-btn" onclick="backToProducts()">← Geri</button>
                <h2 class="detail-title">Ürün Detayı</h2>
            </div>

            <div class="product-detail-card" id="productDetailCard">
                <!-- Ürün detayları buraya dinamik olarak eklenecek -->
            </div>
        </div>

        <!-- Profil Sayfası -->
        <div class="profile-content" id="profileContent">
            <div class="breadcrumb">
                <a href="#" class="breadcrumb-item" onclick="showHome()">Ana Sayfa</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Profilim</span>
            </div>

            <div class="profile-card">
                <div class="profile-header">
                    
                    <div class="profile-info">
                        <h2 id="ad"><?php echo isset($_SESSION['kullanici_adi']) ? $_SESSION['kullanici_adi'] : 'Kullanıcı'; ?></h2>
                        <p id="email"><?php echo isset($_SESSION['eposta']) ? $_SESSION['eposta'] : ''; ?></p>
                        <p id="cep"><?php echo isset($_SESSION['cep_telefonu']) ? $_SESSION['cep_telefonu'] : ''; ?></p>
                        <p id="dogum"><?php echo isset($_SESSION['dogum_tarihi']) ? $_SESSION['dogum_tarihi'] : ''; ?></p>
                        <p id="cinsiyet"><?php echo isset($_SESSION['cinsiyet']) ? $_SESSION['cinsiyet'] : ''; ?></p>
                        <p id="adres"><?php echo isset($_SESSION['adres']) ? $_SESSION['adres'] : ''; ?></p>
                        
                    </div>
                </div>

                <div class="profile-details">
                    <div class="detail-group">
                        <h3>Kişisel Bilgiler</h3>
                        <div class="detail-item">
                            <span class="detail-label">Ad Soyad:</span>
                            <span class="detail-value" id="ad"><?php echo isset($_SESSION['kullanici_adi']) ? $_SESSION['kullanici_adi'] : ''; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Doğum Tarihi:</span>
                            <span class="detail-value" id="dogum"><?php echo isset($_SESSION['dogum_tarihi']) ? $_SESSION['dogum_tarihi'] : ''; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Cinsiyet:</span>
                            <span class="detail-value" id="cinsiyet"><?php echo isset($_SESSION['cinsiyet']) ? $_SESSION['cinsiyet'] : ''; ?></span>
                        </div>
                    </div>

                    <div class="detail-group">
                        <h3>İletişim Bilgileri</h3>
                        <div class="detail-item">
                            <span class="detail-label">E-posta:</span>
                            <span class="detail-value" id="email"><?php echo isset($_SESSION['eposta']) ? $_SESSION['eposta'] : ''; ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Telefon:</span>
                            <span class="detail-value" id="cep"><?php echo isset($_SESSION['cep_telefonu']) ? $_SESSION['cep_telefonu'] : ''; ?></span>
                        </div>
                    </div>

                    <div class="detail-group">
                        <h3>Adres Bilgileri</h3>
                        <div class="detail-item">
                            <span class="detail-label">Adres:</span>
                            <span class="detail-value" id="adres"><?php echo isset($_SESSION['adres']) ? $_SESSION['adres'] : ''; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sepet Sayfası -->
        <div class="cart-content" id="cartContent">
            <div class="breadcrumb">
                <a href="#" class="breadcrumb-item" onclick="showHome()">Ana Sayfa</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Sepetim</span>
            </div>

            <h2 class="products-title">Sepetim</h2>

            <div class="loading" id="loadingCart">
                <div class="loading-spinner"></div>
                <p>Sepet yükleniyor...</p>
            </div>

            <div class="cart-items" id="cartItems">
                <!-- Sepet öğeleri buraya dinamik olarak eklenecek -->
            </div>

            <div class="cart-summary" id="cartSummary">
                <h3 class="summary-title">Sipariş Özeti</h3>
                <div class="summary-row">
                    <span>Ürünler Toplamı:</span>
                    <span id="subtotalPrice">0 ₺</span>
                </div>
                <div class="summary-row">
                    <span>Kargo Ücreti:</span>
                    <span>Ücretsiz</span>
                </div>
                <div class="summary-row">
                    <span class="summary-total">Toplam:</span>
                    <span class="summary-total" id="totalPrice">0 ₺</span>
                </div>
                <button class="checkout-btn" onclick="checkout()">SİPARİŞİ TAMAMLA</button>
            </div>
        </div>

        <!-- Favoriler Sayfası -->
        <div class="favorites-content" id="favoritesContent">
            <div class="breadcrumb">
                <a href="#" class="breadcrumb-item" onclick="showHome()">Ana Sayfa</a>
                <span class="breadcrumb-separator">/</span>
                <span class="breadcrumb-current">Favorilerim</span>
            </div>

            <div class="products-header">
                <h2 class="products-title">Favorilerim</h2>
                <span class="products-count" id="favoritesCount">0 ürün</span>
            </div>

            <div class="loading" id="loadingFavorites">
                <div class="loading-spinner"></div>
                <p>Favoriler yükleniyor...</p>
            </div>

            <div class="products-grid" id="favoritesGrid">
                <!-- Favori ürünler buraya dinamik olarak eklenecek -->
            </div>
        </div>
 <div class="orders-content" id="ordersContent">
    <div class="breadcrumb">
        <a href="#" class="breadcrumb-item" onclick="showHome()">Ana Sayfa</a>
        <span class="breadcrumb-separator">/</span>
        <span class="breadcrumb-current">Siparişlerim</span>
    </div>

    <div class="products-header">
        <h2 class="products-title">Siparişlerim</h2>
        <span class="products-count" id="ordersCount">0 sipariş</span>
    </div>

    <div class="loading" id="loadingOrders">
        <div class="loading-spinner"></div>
        <p>Siparişler yükleniyor...</p>
    </div>

    <div class="orders-list" id="ordersList">
        <!-- Siparişler buraya dinamik olarak eklenecek -->
    </div>
</div>
    </main>

    
    <script>
    function renderProducts(products) {
    hideAllContent(); // Diğer içerikleri gizle
    

    const grid = document.getElementById('productsGrid');
    
    if (!products || products.length === 0) {
        grid.innerHTML = '<div class="no-products"><p>Bu kategoride henüz ürün bulunmamaktadır.</p></div>';
        return;
    }

    let html = '';
    products.forEach(product => {
        const imageUrl = product.resim ? `../images/${product.resim}` : '../images/default.jpg';
        const hasStock = product.stok_durumu > 0;
        const availableSizes = product.mevcut_numaralar ? product.mevcut_numaralar.split(',').join(', ') : 'Stokta yok';
        
        html += `
            <div class="product-card ${!hasStock ? 'out-of-stock' : ''}" data-product-id="${product.urun_id}">
                <div class="product-image">
                    <img src="${imageUrl}" alt="${product.urun_adi}" onerror="this.src='../images/default.jpg'">
                    ${!hasStock ? '<div class="stock-badge">Stokta Yok</div>' : ''}
                    <button class="favorite-btn" onclick="toggleFavorite(${product.urun_id}, this)" title="Favorilere ekle" style="position: absolute; top: 10px; right: 10px; background: rgba(255, 255, 255, 0.9); border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 2;">
                        <i class="fas fa-heart" style="font-size: 18px; color: #666;"></i>
                    </button>
                </div>
                <div class="product-info">
                    <h3 class="product-title">${product.urun_adi}</h3>
                    <p class="product-brand">${product.marka}</p>
                    <p class="product-description">${product.aciklama || ''}</p>
                    <div class="product-sizes">
                        <span class="sizes-label">Mevcut Numaralar:</span>
                        <span class="sizes-list">${availableSizes}</span>
                    </div>
                    <div class="product-footer">
                        <span class="product-price">${parseFloat(product.fiyat).toFixed(2)} ₺</span>
                        <button class="view-detail-btn" onclick="showProductDetail(${product.urun_id})" 
                                ${!hasStock ? 'disabled' : ''}>
                            ${hasStock ? 'Detay Gör' : 'Stokta Yok'}
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
    
    <?php if ($isLoggedIn): ?>
        setTimeout(() => {
            checkFavoriteStatus();
        }, 100);
    <?php endif; ?>
}

function toggleFavorite(productId, heartElement) {
    <?php if (!$isLoggedIn): ?>
        alert('Favorilere ürün eklemek için giriş yapmalısınız');
        window.location.href = 'giris.php';
        return;
    <?php endif; ?>
    
    console.log('toggleFavorite çağrıldı:', productId);
    
    // Kalp butonunu geçici olarak devre dışı bırak
    heartElement.style.pointerEvents = 'none';
    heartElement.style.opacity = '0.5';
    
    fetch(`?action=toggle_favorite&urun_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            console.log('Favori response:', data);
            if (data.success) {
                const heartIcon = heartElement.querySelector('i');
                // Favori durumuna göre kalp ikonunu güncelle
                if (data.is_favorite) {
                    heartElement.classList.add('favorite');
                    heartIcon.style.color = '#e74c3c';
                    heartElement.title = 'Favorilerden çıkar';
                    showNotification('Ürün favorilere eklendi!', 'success');
                } else {
                    heartElement.classList.remove('favorite');
                    heartIcon.style.color = '#666';
                    heartElement.title = 'Favorilere ekle1';
                    showNotification('Ürün favorilerden çıkarıldı!', 'info');
                    
                    // Eğer favorites sayfasındaysak, ürünü listeden kaldır
                    if (document.getElementById('favoritesContent') && document.getElementById('favoritesContent').style.display === 'block') {
                        loadFavorites();
                    }
                }
            } else {
                alert('Favori işlemi sırasında bir hata oluştu: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Favori işlemi sırasında bir hata oluştu');
        })
        .finally(() => {
            // Kalp butonunu tekrar aktif et
            heartElement.style.pointerEvents = 'auto';
            heartElement.style.opacity = '1';
        });
}

function checkFavoriteStatus() {
    const productCards = document.querySelectorAll('.product-card');
    
    productCards.forEach(card => {
        const productId = card.dataset.productId;
        const heartBtn = card.querySelector('.favorite-btn');
        
        if (heartBtn) {
            // Her ürün için favori durumunu kontrol et
            fetch(`?action=check_favorite&urun_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_favorite) {
                        const heartIcon = heartBtn.querySelector('i');
                        heartBtn.classList.add('favorite');
                        heartIcon.style.color = '#e74c3c';
                        heartBtn.title = 'Favorilerden çıkar';
                    }
                })
                .catch(error => {
                    console.error('Favori durumu kontrol edilirken hata:', error);
                });
        }
    });
}

// Favoriler sayfasını göster
function showFavorites() {
    console.log('showFavorites fonksiyonu çağrıldı');
    
    // PHP ile kullanıcı giriş kontrolü
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    
    if (!isLoggedIn) {
        alert('Favorileri görmek için giriş yapmalısınız');
        window.location.href = 'giris.php';
        return;
    }
    
    hideAllContent();
    const favoritesContent = document.getElementById('favoritesContent');
    if (favoritesContent) {
        favoritesContent.style.display = 'block';
        loadFavorites();
    } else {
        console.error('favoritesContent elementi bulunamadı');
    }
}

// Favorileri yükle
function loadFavorites() {
    console.log('loadFavorites fonksiyonu çağrıldı');
    
    const loadingElement = document.getElementById('loadingFavorites');
    const gridElement = document.getElementById('favoritesGrid');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (gridElement) gridElement.innerHTML = '';
    
    fetch('?action=get_favorites')
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Favoriler data:', data);
            if (data.success) {
                renderFavorites(data.favoriler);
                const countElement = document.getElementById('favoritesCount');
                if (countElement) {
                    countElement.textContent = `${data.favoriler.length} ürün`;
                }
            } else {
                console.error('Favoriler yükleme hatası:', data.error);
                alert('Favoriler yüklenirken bir hata oluştu: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Favoriler yüklenirken bir hata oluştu');
        })
        .finally(() => {
            if (loadingElement) loadingElement.style.display = 'none';
        });
}

// Favori ürünleri render et
function renderFavorites(favorites) {
    console.log('renderFavorites çağrıldı:', favorites);
    
    const grid = document.getElementById('favoritesGrid');
    if (!grid) {
        console.error('favoritesGrid elementi bulunamadı');
        return;
    }
    
    if (!favorites || favorites.length === 0) {
        grid.innerHTML = `
            <div class="no-products" style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-heart" style="font-size: 48px; color: #ddd; margin-bottom: 20px; display: block;"></i>
                <p style="margin-bottom: 20px;">Henüz favori ürününüz bulunmamaktadır.</p>
                <button onclick="showHome()" class="btn-primary" style="background: #e74c3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Alışverişe Başla</button>
            </div>
        `;
        return;
    }

    let html = '';
    favorites.forEach(product => {
        const imageUrl = product.resim ? `../images/${product.resim}` : '../images/default.jpg';
        const availableSizes = product.mevcut_numaralar ? product.mevcut_numaralar.split(',').join(', ') : 'Stokta yok';
        
        html += `
            <div class="product-card" data-product-id="${product.urun_id}">
                <div class="product-image">
                    <img src="${imageUrl}" alt="${product.urun_adi}" onerror="this.src='../images/default.jpg'">
                    <button class="favorite-btn favorite" onclick="toggleFavorite(${product.urun_id}, this)" title="Favorilerden çıkar" style="position: absolute; top: 10px; right: 10px; background: rgba(255, 255, 255, 0.9); border: none; border-radius: 50%; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 2;">
                        <i class="fas fa-heart" style="color: #e74c3c; font-size: 18px;"></i>
                    </button>
                </div>
                <div class="product-info">
                    <h3 class="product-title">${product.urun_adi}</h3>
                    <p class="product-brand">${product.marka}</p>
                    <p class="product-description">${product.aciklama || ''}</p>
                    <div class="product-sizes">
                        <span class="sizes-label">Mevcut Numaralar:</span>
                        <span class="sizes-list">${availableSizes}</span>
                    </div>
                    <div class="product-footer">
                        <span class="product-price">${parseFloat(product.fiyat).toFixed(2)} ₺</span>
                        <button class="view-detail-btn" onclick="showProductDetail(${product.urun_id})">
                            Detay Gör
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
}

// Bildirim gösterme fonksiyonu
function showNotification(message, type = 'info') {
    // Mevcut bildirimleri temizle
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(notification => notification.remove());
    
    // Yeni bildirim oluştur
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        display: flex;
        align-items: center;
        gap: 10px;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        z-index: 1000;
        min-width: 250px;
        border-left: 4px solid ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
    `;
    
    notification.innerHTML = `
        <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}" style="font-size: 18px; color: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};"></i>
        <span style="font-weight: 500; color: #333;">${message}</span>
    `;
    
    // Bildirimi sayfaya ekle
    document.body.appendChild(notification);
    
    // Animasyon için kısa bir gecikme
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // 3 saniye sonra bildirimi kaldır
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

function loadCart() {
    <?php if (!$isLoggedIn): ?>
        window.location.href = 'giris.php';
        return;
    <?php endif; ?>
    
    document.getElementById('loadingCart').style.display = 'block';
    document.getElementById('cartItems').innerHTML = '';
    
    fetch(`?action=get_cart`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderCart(data.sepet, data.toplam);
            } else {
                alert('Sepet yüklenirken bir hata oluştu: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Sepet yüklenirken bir hata oluştu');
        })
        .finally(() => {
            document.getElementById('loadingCart').style.display = 'none';
        });
}

function addToCart(productId) {
    <?php if (!$isLoggedIn): ?>
        alert('Sepete ürün eklemek için giriş yapmalısınız');
        window.location.href = 'giris.php';
        return;
    <?php endif; ?>
    
    const selectedSize = document.querySelector('.size-option.selected');
    if (!selectedSize) {
        alert('Lütfen bir numara seçiniz');
        return;
    }

    const size = selectedSize.dataset.size;
    const quantity = 1; // Veya miktar seçimi yapılıyorsa o değer alınır

    fetch(`?action=add_to_cart&urun_id=${productId}&numara=${size}&adet=${quantity}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Ürün sepete eklendi!', 'success');
                loadCart(); // Sepeti güncelle
            } else {
                alert('Sepete ekleme başarısız: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Sepete ekleme sırasında bir hata oluştu');
        });
}
function showOrders() {
    
    console.log('showOrders fonksiyonu çağrıldı');
    
    // PHP ile kullanıcı giriş kontrolü
    const isLoggedIn = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    
    if (!isLoggedIn) {
        alert('Siparişleri görmek için giriş yapmalısınız');
        window.location.href = 'giris.php';
        return;
    }
    
    hideAllContent();
    const ordersContent = document.getElementById('ordersContent');
    if (ordersContent) {
        ordersContent.style.display = 'block';
        loadOrders();
    } else {
        console.error('ordersContent elementi bulunamadı');
    }
}

// Siparişleri yükle
function loadOrders() {
    console.log('loadOrders fonksiyonu çağrıldı');
    
    const loadingElement = document.getElementById('loadingOrders');
    const listElement = document.getElementById('ordersList');
    
    if (loadingElement) loadingElement.style.display = 'block';
    if (listElement) listElement.innerHTML = '';
    
    fetch('?action=get_orders')
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Siparişler data:', data);
            if (data.success) {
                renderOrders(data.siparisler);
                const countElement = document.getElementById('ordersCount');
                if (countElement) {
                    countElement.textContent = `${data.siparisler.length} sipariş`;
                }
            } else {
                console.error('Siparişler yükleme hatası:', data.error);
                alert('Siparişler yüklenirken bir hata oluştu: ' + data.error);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Siparişler yüklenirken bir hata oluştu');
        })
        .finally(() => {
            if (loadingElement) loadingElement.style.display = 'none';
        });
}

// Siparişleri render et
function renderOrders(orders) {
    console.log('renderOrders çağrıldı:', orders);
    
    const list = document.getElementById('ordersList');
    if (!list) {
        console.error('ordersList elementi bulunamadı');
        return;
    }
    
    if (!orders || orders.length === 0) {
        list.innerHTML = `
            <div class="no-orders">
                <i class="fas fa-box"></i>
                <p>Henüz siparişiniz bulunmamaktadır.</p>
                <button onclick="showHome()" class="btn-primary">Alışverişe Başla</button>
            </div>
        `;
        return;
    }

    let html = '';
    orders.forEach(order => {
        const orderDate = new Date(order.siparis_tarihi).toLocaleDateString('tr-TR');
        const statusClass = getStatusClass(order.durum);
        const statusText = getStatusText(order.durum);
        
        html += `
            <div class="order-card">
                <div class="order-header">
                    <div class="order-info">
                        <h3>Sipariş #${order.siparis_id}</h3>
                        <div class="order-date">${orderDate}</div>
                    </div>
                    <div class="order-status ${statusClass}">
                        ${statusText}
                    </div>
                </div>
                
                <div class="order-products">
        `;
        
        order.urunler.forEach(product => {
            const imageUrl = product.resim ? `../images/${product.resim}` : '../images/default.jpg';
            const totalPrice = (parseFloat(product.birim_fiyat) * parseInt(product.adet)).toFixed(2);
            
            html += `
                <div class="order-product">
                    <div class="order-product-image">
                        <img src="${imageUrl}" alt="${product.urun_adi}" onerror="this.src='../images/default.jpg'">
                    </div>
                    <div class="order-product-info">
                        <div class="order-product-name">${product.urun_adi}</div>
                        <div class="order-product-details">
                            ${product.marka} - Numara: ${product.numara} - Adet: ${product.adet}
                        </div>
                    </div>
                    <div class="order-product-price">
                        ${totalPrice} ₺
                    </div>
                </div>
            `;
        });
        
        html += `
                </div>
                
                <div class="order-total">
                    <span class="order-total-label">Toplam Tutar:</span>
                    <span class="order-total-amount">${parseFloat(order.toplam_tutar).toFixed(2)} ₺</span>
                </div>
            </div>
        `;
    });
    
    list.innerHTML = html;
}

// Sipariş durumu CSS sınıfı
function getStatusClass(status) {
    switch(status.toLowerCase()) {
        case 'beklemede':
            return 'status-beklemede';
        case 'hazırlanıyor':
        case 'hazirlaniyor':
            return 'status-hazirlaniyor';
        case 'kargoda':
            return 'status-kargoda';
        case 'teslim edildi':
        case 'teslim-edildi':
            return 'status-teslim-edildi';
        case 'iptal edildi':
        case 'iptal-edildi':
            return 'status-iptal-edildi';
        default:
            return 'status-beklemede';
    }
}

// Sipariş durumu metni
function getStatusText(status) {
    switch(status.toLowerCase()) {
        case 'beklemede':
            return 'Beklemede';
        case 'hazırlanıyor':
        case 'hazirlaniyor':
            return 'Hazırlanıyor';
        case 'kargoda':
            return 'Kargoda';
        case 'teslim edildi':
        case 'teslim-edildi':
            return 'Teslim Edildi';
        case 'iptal edildi':
        case 'iptal-edildi':
            return 'İptal Edildi';
        default:
            return status;
    }
}
function hideAllContent() {
    
    const contents = document.querySelectorAll('#main-content > .content-section');
    contents.forEach(content => {
        content.style.display = 'none';
    });
     const sections = [
        'homeContent',
        'categoriesContent',
        'cartContent',
        'favoritesContent',
        'ordersContent',
        'profileContent',
        'loginForm',
        'registerForm'
    ];

    sections.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    
}

function showHome() {
    hideAllContent();
    const home = document.getElementById('homeContent');
    if (home) {
        home.style.display = 'block';
    }
}

</script>
<script src="anasayfa.js"></script>

</body>
</html>