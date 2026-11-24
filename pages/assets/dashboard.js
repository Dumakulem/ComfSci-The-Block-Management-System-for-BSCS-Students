(function () {
    const root = document.documentElement;
    const sidebar = document.getElementById('dashboardSidebar');
    const menuButton = document.querySelector('.sidebarToggle');
    const backdrop = document.querySelector('[data-sidebar-close]');
    const mobile = window.matchMedia('(max-width: 900px)');
    let closeTimer;

    function setSidebarOpen(open) {
        root.dataset.sidebar = open ? 'open' : 'closed';
        if (menuButton) {
            menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuButton.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
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

    if (menuButton) {
        menuButton.addEventListener('click', function () {
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
            closeNotifications();
        }
    });
    mobile.addEventListener('change', function () {
        setSidebarOpen(false);
    });

    const themeButton = document.querySelector('.themeToggle');
    const themeIcon = themeButton && themeButton.querySelector('.ic');
    function setTheme(theme) {
        root.dataset.theme = theme;
        const dark = theme === 'dark';
        if (themeIcon) {
            themeIcon.src = './assets/rai-icons/' + (dark ? 'sun' : 'moon') + '.png';
        }
        if (themeButton) {
            themeButton.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
        }
        window.localStorage.setItem('comfysci-dashboard-theme', theme);
    }
    const savedTheme = window.localStorage.getItem('comfysci-dashboard-theme');
    setTheme(savedTheme === 'dark' ? 'dark' : 'light');
    if (themeButton) {
        themeButton.addEventListener('click', function () {
            setTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
        });
    }

    const notificationButton = document.querySelector('.notificationToggle');
    const notificationPanel = document.querySelector('.notif-pop');
    function closeNotifications() {
        if (notificationPanel && notificationButton) {
            notificationPanel.hidden = true;
            notificationButton.setAttribute('aria-expanded', 'false');
        }
    }
    if (notificationButton && notificationPanel) {
        notificationButton.addEventListener('click', function () {
            notificationPanel.hidden = !notificationPanel.hidden;
            notificationButton.setAttribute('aria-expanded', notificationPanel.hidden ? 'false' : 'true');
        });
        document.addEventListener('click', function (event) {
            if (!event.target.closest('.bell-wrap')) {
                closeNotifications();
            }
        });
    }

    const addTaskForm = document.querySelector('.add-task-form');
    document.querySelectorAll('.addTaskToggle').forEach(function (button) {
        button.addEventListener('click', function () {
            if (!addTaskForm) {
                return;
            }
            addTaskForm.hidden = !addTaskForm.hidden;
            document.querySelectorAll('.addTaskToggle').forEach(function (toggle) {
                toggle.setAttribute('aria-expanded', addTaskForm.hidden ? 'false' : 'true');
            });
            if (!addTaskForm.hidden) {
                const title = addTaskForm.querySelector('input[name="title"]');
                if (title) {
                    title.focus();
                }
            }
        });
    });
}());
