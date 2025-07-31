 // Sidebar Toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mainContent = document.getElementById('mainContent');
            
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
            mainContent.classList.toggle('sidebar-open');
        }

        // Sayfa yüklendiğinde animasyonu tetikle
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loaded');
        });

        // Form gönderimlerinde yükleme efekti
        const forms = document.querySelectorAll('form');
        forms.forEach(form => {
            form.addEventListener('submit', function() {
                const submitButtons = this.querySelectorAll('button[type="submit"]');
                submitButtons.forEach(button => {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
                    button.disabled = true;
                });
            });
        });