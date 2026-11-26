(function () {
    const root = document.documentElement;
    const sidebar = document.getElementById('dashboardSidebar');
    const trigger = document.querySelector('.comfsci-sidebar-trigger');
    const backdrop = document.querySelector('[data-sidebar-close]');
    const mobile = window.matchMedia('(max-width: 900px)');
    let closeTimer;

    function setSidebarOpen(open) {
        root.dataset.sidebar = open ? 'open' : 'closed';
        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
            trigger.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        }
    }

    if (sidebar) {
        sidebar.addEventListener('mouseenter', function () {
            if (!mobile.matches) {
                window.clearTimeout(closeTimer);
                setSidebarOpen(true);
            }
        });
        sidebar.addEventListener('mouseleave', function () {
            if (!mobile.matches) {
                closeTimer = window.setTimeout(function () { setSidebarOpen(false); }, 140);
            }
        });
        sidebar.addEventListener('focusin', function () {
            if (!mobile.matches) {
                window.clearTimeout(closeTimer);
                setSidebarOpen(true);
            }
        });
        sidebar.addEventListener('focusout', function (event) {
            if (!mobile.matches && !sidebar.contains(event.relatedTarget)) {
                setSidebarOpen(false);
            }
        });
    }

    if (trigger) {
        trigger.addEventListener('click', function () {
            setSidebarOpen(root.dataset.sidebar !== 'open');
        });
    }
    if (backdrop) {
        backdrop.addEventListener('click', function (event) {
            event.preventDefault();
            setSidebarOpen(false);
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });
    mobile.addEventListener('change', function () {
        setSidebarOpen(false);
    });
})();
