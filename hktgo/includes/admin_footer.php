<?php
?>
            </div>
        </main>
    </div>
</div>
<?php if (!empty($adminModalsHtml)): ?>
<?= $adminModalsHtml ?>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var toggleBtn = document.getElementById('adminSidebarToggle');
    var sidebar   = document.getElementById('adminSidebar');
    var backdrop  = document.getElementById('adminSidebarBackdrop');
    if (toggleBtn && sidebar && backdrop) {
        function closeSidebar() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        }
        toggleBtn.addEventListener('click', function () {
            var isOpen = sidebar.classList.toggle('show');
            backdrop.classList.toggle('show', isOpen);
        });
        backdrop.addEventListener('click', closeSidebar);
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) {
                closeSidebar();
            }
        });
    }

    document.querySelectorAll('.admin-app .modal').forEach(function (modalEl) {
        document.body.appendChild(modalEl);
    });
})();
</script>
</body>
</html>
