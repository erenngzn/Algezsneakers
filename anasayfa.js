let sidebarOpen = false;
        let profileDropdownOpen = false;

        // Sayfa yüklendiğinde
        document.addEventListener('DOMContentLoaded', () => {
            document.body.classList.add('loaded');
            updateCartCount();
            checkFavoriteStatus();
        });

        // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebarOpen = !sidebarOpen;

            if (sidebarOpen) {
                sidebar.classList.add('active');
                overlay.classList.add('active');
            } else {
                sidebar.classList.remove('active');
                overlay.classList.remove('active');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');

            sidebarOpen = false;
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Profil dropdown toggle
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profileDropdown');
            const toggle = document.getElementById('dropdownToggle');
            
            profileDropdownOpen = !profileDropdownOpen;
            
            if (profileDropdownOpen) {
                dropdown.classList.add('active');
                toggle.classList.add('active');
            } else {
                dropdown.classList.remove('active');
                toggle.classList.remove('active');
            }
        }

        // Click outside to close sidebar and dropdown
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            const profileSection = document.querySelector('.profile-section');
            const dropdown = document.getElementById('profileDropdown');
            
            if (sidebarOpen && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                closeSidebar();
            }
            
            if (profileDropdownOpen && !profileSection.contains(event.target)) {
                toggleProfileDropdown();
            }
        });

        // Kategoriye yönlendirme
        function goToCategory(category) {
            window.location.href = `kategoriler.php?kategori=${category}`;
        }

        // Sayfa geçiş fonksiyonları
        function showHome() {
            document.getElementById('homeContent').classList.remove('hidden');
            document.getElementById('productsContent').classList.remove('active');
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('profileContent').classList.remove('active');
            document.getElementById('cartContent').classList.remove('active');
            document.getElementById('favoritesContent').classList.remove('active');
        }

        function showProfile() {
            document.getElementById('homeContent').classList.add('hidden');
            document.getElementById('productsContent').classList.remove('active');
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('profileContent').classList.add('active');
            document.getElementById('cartContent').classList.remove('active');
            document.getElementById('favoritesContent').classList.remove('active');
            toggleProfileDropdown();
        }

        function showCart() {
            document.getElementById('homeContent').classList.add('hidden');
            document.getElementById('productsContent').classList.remove('active');
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('profileContent').classList.remove('active');
            document.getElementById('cartContent').classList.add('active');
            document.getElementById('favoritesContent').classList.remove('active');
            toggleProfileDropdown();
            loadCart();
        }

        function showFavorites() {
            document.getElementById('homeContent').classList.add('hidden');
            document.getElementById('productsContent').classList.remove('active');
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('profileContent').classList.remove('active');
            document.getElementById('cartContent').classList.remove('active');
            document.getElementById('favoritesContent').classList.add('active');
            toggleProfileDropdown();
            loadFavorites();
        }

        

        function showAddresses() {
            alert('Adreslerim sayfası yakında eklenecek');
            toggleProfileDropdown();
        }

        // Ürünlere geri dön
        function backToProducts() {
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('productsContent').classList.add('active');
        }

        // Ürünleri yükle
        function loadProducts(kategori_id, kategori_adi) {
            currentCategoryId = kategori_id;
            currentCategoryName = kategori_adi;
            
            // UI güncelleme
            document.getElementById('homeContent').classList.add('hidden');
            document.getElementById('productsContent').classList.add('active');
            document.getElementById('productDetail').classList.remove('active');
            document.getElementById('profileContent').classList.remove('active');
            document.getElementById('cartContent').classList.remove('active');
            document.getElementById('favoritesContent').classList.remove('active');
            
            document.getElementById('currentCategory').textContent = kategori_adi;
            document.getElementById('productsTitle').textContent = kategori_adi;
            
            // Loading state
            document.getElementById('loadingProducts').style.display = 'block';
            document.getElementById('productsGrid').innerHTML = '';
            
            // AJAX isteği
            fetch(`?action=get_products&kategori_id=${kategori_id}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderProducts(data.urunler);
                        document.getElementById('productsCount').textContent = `${data.urunler.length} ürün`;
                    } else {
                        alert('Ürünler yüklenirken bir hata oluştu: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ürünler yüklenirken bir hata oluştu');
                })
                .finally(() => {
                    document.getElementById('loadingProducts').style.display = 'none';
                });
            
            closeSidebar();
        }

        // Ürünleri render et
        

        // Ürün detayını göster
        function showProductDetail(productId) {
            document.getElementById('productsContent').classList.remove('active');
            document.getElementById('productDetail').classList.add('active');
            
            // Loading state
            const detailCard = document.getElementById('productDetailCard');
            detailCard.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Ürün bilgileri yükleniyor...</p></div>';
            
            // AJAX isteği
            fetch(`?action=get_product_detail&urun_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderProductDetail(data.urun, data.stoklar);
                    } else {
                        alert('Ürün detayı yüklenirken bir hata oluştu: ' + data.error);
                        backToProducts();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ürün detayı yüklenirken bir hata oluştu');
                    backToProducts();
                });
        }

        // Ürün detayını render et
        function renderProductDetail(product, stocks) {
    const detailCard = document.getElementById('productDetailCard');
    
    // Stok durumu
    let sizeOptions = '';
    if (stocks && stocks.length > 0) {
        stocks.forEach(stock => {
            sizeOptions += `
                <div class="size-option" data-size="${stock.numara}">
                    ${stock.numara} (${stock.stok_adedi} adet)
                </div>
            `;
        });
    } else {
        sizeOptions = '<p>Bu ürün için stok bulunmamaktadır.</p>';
    }
    
    // Ürün resmi
    let imageContent;
    if (product.resim) {
        imageContent = `<img src="../images/${product.resim}" alt="${product.urun_adi}" onerror="this.src='../images/default.jpg'">`;
    } else {
        imageContent = `<div class="product-placeholder">👟</div>`;
    }
    
    detailCard.innerHTML = `
        <div class="detail-grid">
            <div class="detail-image">
                ${imageContent}
            </div>
            <div class="detail-info">
                <h1>${product.urun_adi}</h1>
                <div class="detail-brand">${product.marka} - ${product.kategori_adi}</div>
                <div class="detail-price">${product.fiyat} ₺</div>
                <p class="detail-description">${product.aciklama || 'Bu ürün için açıklama bulunmamaktadır.'}</p>
                
                <div class="size-selector">
                    <h4>Numara Seçin</h4>
                    <div class="size-options">
                        ${sizeOptions}
                    </div>
                </div>
                
                <div class="detail-actions">
                    <button class="btn btn-primary btn-large" onclick="addToCart(${product.urun_id})">Sepete Ekle</button>
                    <button class="btn btn-secondary btn-large favorite-btn" id="detailFavoriteBtn" onclick="toggleFavorite(${product.urun_id}, this)">
                        <i class="fas fa-heart"></i> Favorilere Ekle
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Numara seçimi etkinleştirme
    document.querySelectorAll('.size-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.size-option').forEach(el => el.classList.remove('selected'));
            this.classList.add('selected');
        });
    });
    

    function checkFavoriteStatusForProduct(productId) {
    fetch(`?action=check_favorite&urun_id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if(data.isFavorite){
                document.getElementById('detailFavoriteBtn').classList.add('favorited');
            } else {
                document.getElementById('detailFavoriteBtn').classList.remove('favorited');
            }
        })
        .catch(err => console.error('Favori durumunu alırken hata:', err));
}

    // Favori durumunu kontrol et
    checkFavoriteStatusForProduct(product.urun_id);
}


        // Sepete ekle
       
            
            const size = selectedSize.dataset.size;
            
            fetch(`?action=add_to_cart&urun_id=${productId}&numara=${size}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Ürün sepete eklendi');
                        updateCartCount();
                        
                        // Eğer sepetteysek sepeti yenile
                        if (document.getElementById('cartContent').classList.contains('active')) {
                            loadCart();
                        }
                    } else {
                        alert('Ürün sepete eklenirken bir hata oluştu: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ürün sepete eklenirken bir hata oluştu');
                });
        

        // Sepeti yükle
        

        // Sepeti render et
       function renderCart(cartItems, total) {
    const cartItemsContainer = document.getElementById('cartItems');
    let html = '';

    cartItems.forEach(item => {
        const imageUrl = item.resim ? `../images/${item.resim}` : '../images/default.jpg';
        
        html += `
            <div class="cart-item" data-cart-id="${item.sepet_id}">
                <div class="cart-item-image">
                    <img src="${imageUrl}" alt="${item.urun_adi}" onerror="this.src='../images/default.jpg'">
                </div>
                <div class="cart-item-details">
                    <h4>${item.urun_adi}</h4>
                    <p>${item.marka}</p>
                    <p>Numara: ${item.numara || 'Belirtilmemiş'}</p>
                    <p>Adet: ${item.adet}</p>
                </div>
                <div class="cart-item-price">
                    <span>${(item.fiyat * item.adet).toFixed(2)} ₺</span>
                    <button class="remove-btn" onclick="removeFromCart(${item.sepet_id})">
                        <i class="fas fa-trash"></i> Sepetten Çıkar
                    </button>
                </div>
            </div>
        `;
    });

    cartItemsContainer.innerHTML = html || '<p class="empty-cart">Sepetiniz boş</p>';
    document.getElementById('subtotalPrice').textContent = `${total.toFixed(2)} ₺`;
    document.getElementById('totalPrice').textContent = `${total.toFixed(2)} ₺`;
}

        // Sepet öğesi miktarını güncelle
        function updateCartItemQuantity(sepet_id, newQuantity) {
  const item = cartItems.find(c => c.sepet_id === sepet_id);
  if (item) {
    if (newQuantity <= 0) {
      cartItems = cartItems.filter(c => c.sepet_id !== sepet_id);
    } else {
      item.adet = newQuantity;
    }
    renderCart();
  }
}

// Sepetten kaldır
function removeFromCart(cartId) {
    if (!confirm('Bu ürünü sepetinizden çıkarmak istediğinize emin misiniz?')) {
        return;
    }

    fetch(`?action=remove_from_cart&sepet_id=${cartId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Ürün sepetinizden çıkarıldı!', 'success');
                loadCart(); // Sepeti yeniden yükle
            } else {
                showNotification('Ürün çıkarılırken bir hata oluştu: ' + data.error, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('Bir hata oluştu, lütfen tekrar deneyin', 'error');
        });
}
function checkout() {
    if (!confirm("Siparişi tamamlamak istediğinizden emin misiniz?")) {
        return;
    }

    // AJAX isteği
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "checkout.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            alert(xhr.responseText); // PHP'den dönen mesaj
            // İsteğe bağlı: kullanıcıyı siparişlerim sayfasına yönlendir
            // window.location.href = 'siparislerim.php';
        } else {
            alert("Bir hata oluştu. Lütfen tekrar deneyin.");
        }
    };

    xhr.send(); // veri gerekmediği için boş gönderiyoruz
}
