window.addEventListener('load', function() {
        document.body.classList.add('loaded');
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');

        if (window.innerWidth > 768) {
            mainContent.classList.toggle('sidebar-open');
        }
    }

    function showLoading() {
        document.getElementById('loading').style.display = 'flex';
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        const urunAdi = document.getElementById('urun_adi').value.trim();
        const fiyat = parseFloat(document.getElementById('fiyat').value);
        const kategoriId = document.getElementById('kategori_id').value;

        if (urunAdi.length < 2) {
            e.preventDefault();
            alert('Ürün adı en az 2 karakter olmalıdır.');
            return false;
        }

        if (fiyat <= 0) {
            e.preventDefault();
            alert('Fiyat 0\'dan büyük olmalıdır.');
            return false;
        }

        if (!kategoriId) {
            e.preventDefault();
            alert('Lütfen bir kategori seçin.');
            return false;
        }
    });

    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        if (window.innerWidth <= 768) {
            mainContent.classList.remove('sidebar-open');
        } else if (sidebar.classList.contains('active')) {
            mainContent.classList.add('sidebar-open');
        }
    });

    document.querySelectorAll('.table tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });

        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });

    setTimeout(() => {
        document.getElementById('loading').style.display = 'none';
    }, 2000);

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    document.getElementById('fiyat').addEventListener('input', function(e) {
        let value = e.target.value;
        if (value && !isNaN(value)) {
            if (value.includes('.') && value.split('.')[1].length > 2) {
                e.target.value = parseFloat(value).toFixed(2);
            }
        }
    });

    function toggleFiyatGuncelle(urunId) {
        const form = document.getElementById('fiyatGuncelleForm_' + urunId);
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'flex';
        } else {
            form.style.display = 'none';
        }
    }