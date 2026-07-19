(function () {
    'use strict';

    function isMobileShell() {
        return window.matchMedia('(max-width: 768px)').matches;
    }

    function getSidebar() {
        return document.getElementById('sidebar');
    }

    function closeMobileSidebar() {
        var sidebar = getSidebar();
        if (!sidebar) return;
        sidebar.classList.remove('mobile-open');
        document.body.classList.remove('sidebar-mobile-open');
    }

    function openMobileSidebar() {
        var sidebar = getSidebar();
        if (!sidebar) return;
        sidebar.classList.add('mobile-open');
        document.body.classList.add('sidebar-mobile-open');
    }

    function toggleMobileSidebar() {
        var sidebar = getSidebar();
        if (!sidebar) return;
        if (sidebar.classList.contains('mobile-open')) {
            closeMobileSidebar();
        } else {
            openMobileSidebar();
        }
    }

    function ensureOverlay() {
        if (document.getElementById('sidebarOverlay')) return;
        var overlay = document.createElement('div');
        overlay.id = 'sidebarOverlay';
        overlay.className = 'sidebar-overlay';
        overlay.addEventListener('click', closeMobileSidebar);
        document.body.appendChild(overlay);
    }

    function syncOverlay() {
        var overlay = document.getElementById('sidebarOverlay');
        var sidebar = getSidebar();
        if (!overlay || !sidebar) return;
        overlay.classList.toggle('show', isMobileShell() && sidebar.classList.contains('mobile-open'));
    }

    document.addEventListener('DOMContentLoaded', function () {
        var sidebar = getSidebar();
        if (!sidebar) return;

        ensureOverlay();

        var originalToggle = typeof window.toggleSidebar === 'function' ? window.toggleSidebar : null;

        window.toggleSidebar = function () {
            if (isMobileShell()) {
                toggleMobileSidebar();
                syncOverlay();
                return;
            }
            closeMobileSidebar();
            syncOverlay();
            if (originalToggle) {
                return originalToggle.apply(this, arguments);
            }
        };

        window.addEventListener('resize', function () {
            if (!isMobileShell()) {
                closeMobileSidebar();
            }
            syncOverlay();
        });

        var observer = new MutationObserver(syncOverlay);
        observer.observe(sidebar, { attributes: true, attributeFilter: ['class'] });
        syncOverlay();

        // Close drawer after navigating within sidebar on phones
        sidebar.addEventListener('click', function (event) {
            if (!isMobileShell()) return;
            var link = event.target.closest('a[href]');
            if (!link) return;
            var href = link.getAttribute('href') || '';
            if (href && href.charAt(0) !== '#') {
                closeMobileSidebar();
                syncOverlay();
            }
        });
    });

    window.closeMobileSidebar = closeMobileSidebar;
})();
