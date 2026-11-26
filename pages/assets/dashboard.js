(function () {
    const root = document.documentElement;
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeNotifications();
        }
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
