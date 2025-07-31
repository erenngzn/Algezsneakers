// Page Load Animation
        window.addEventListener('load', function() {
            document.body.classList.add('loaded');
        });

        // Sidebar Toggle Function
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

        // Loading Animation
        function showLoading() {
            document.getElementById('loading').style.display = 'flex';
        }

        // Form Validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const kategoriAdi = document.getElementById('kategori_adi').value.trim();
            if (kategoriAdi.length < 2) {
                e.preventDefault();
                alert('Kategori adı en az 2 karakter olmalıdır.');
                return false;
            }
        });

        // Responsive Sidebar
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

        // Auto-hide loading after form submit
        setTimeout(() => {
            document.getElementById('loading').style.display = 'none';
        }, 2000);

        // Smooth Scroll for Internal Links
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

        // Alert auto hide
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);