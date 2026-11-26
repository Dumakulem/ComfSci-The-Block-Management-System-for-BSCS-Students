document.addEventListener('DOMContentLoaded', function () {
    const app = document.getElementById('module-sources-app');
    const list = document.getElementById('module-source-list');
    const search = document.getElementById('module-source-search');
    const status = document.getElementById('module-source-list-status');
    if (!app || !list || !search || !status) {
        return;
    }

    const canManage = app.dataset.canManage === '1';
    const isAdmin = app.dataset.isAdmin === '1';
    const csrfToken = app.dataset.csrfToken;
    const form = document.getElementById('module-source-form');
    const formStatus = document.getElementById('module-source-form-status');
    const submitButton = document.getElementById('module-source-submit');
    const cancelButton = document.getElementById('module-source-cancel');
    const moduleIdInput = document.getElementById('module-source-id');
    const blockInput = document.getElementById('module-source-block');
    const courseInput = document.getElementById('module-source-course');
    const subjectInput = document.getElementById('module-source-subject');
    const titleInput = document.getElementById('module-source-title');
    const urlInput = document.getElementById('module-source-url');
    const descriptionInput = document.getElementById('module-source-description');
    let modules = [];
    let searchTimer;

    function setStatus(element, message, isError) {
        if (!element) {
            return;
        }
        element.textContent = message;
        element.classList.toggle('is-error', Boolean(isError));
    }

    async function request(url, options) {
        const response = await fetch(url, options || { credentials: 'same-origin' });
        const result = await response.json().catch(function () {
            return {};
        });
        if (!response.ok || !result.ok) {
            throw new Error(result.message || 'The request could not be completed.');
        }
        return result;
    }

    function addText(parent, tagName, className, value) {
        const element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        element.textContent = value || '';
        parent.appendChild(element);
        return element;
    }

    function safeHttpUrl(value) {
        try {
            const url = new URL(value);
            return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
        } catch (_error) {
            return '';
        }
    }

    function formatDate(value) {
        const date = new Date(String(value).replace(' ', 'T'));
        return Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
    }

    function renderModules() {
        list.replaceChildren();
        if (!modules.length) {
            addText(list, 'p', 'module-sources-empty', 'No module sources found for this block.');
            return;
        }

        modules.forEach(function (module) {
            const card = document.createElement('article');
            card.className = 'module-source-card';
            const metadata = [module.course, module.subject, module.blockName].filter(Boolean).join(' · ');
            addText(card, 'p', 'module-source-meta', metadata);
            addText(card, 'h2', 'module-source-title', module.title);
            if (module.description) {
                addText(card, 'p', 'module-source-description', module.description);
            }

            const url = safeHttpUrl(module.resourceUrl);
            if (url) {
                const link = document.createElement('a');
                link.className = 'module-source-link';
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = 'Open resource';
                card.appendChild(link);
            }

            const date = formatDate(module.dateAndTimeAdded);
            if (date) {
                addText(card, 'p', 'module-source-date', 'Added ' + date);
            }

            if (canManage) {
                const actions = document.createElement('div');
                actions.className = 'module-source-actions';
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'module-sources-button module-sources-button-muted';
                edit.textContent = 'Edit';
                edit.addEventListener('click', function () {
                    editModule(module);
                });
                actions.appendChild(edit);

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'module-sources-button module-sources-button-danger';
                remove.textContent = 'Delete';
                remove.addEventListener('click', function () {
                    deleteModule(module);
                });
                actions.appendChild(remove);
                card.appendChild(actions);
            }

            list.appendChild(card);
        });
    }

    async function loadModules() {
        setStatus(status, 'Loading module sources…', false);
        try {
            const params = new URLSearchParams();
            if (search.value.trim()) {
                params.set('search', search.value.trim());
            }
            const result = await request('./api/module_sources/get.php?' + params.toString());
            modules = result.modules;
            setStatus(status, result.count + (result.count === 1 ? ' module source' : ' module sources'), false);
            renderModules();
        } catch (error) {
            setStatus(status, error.message, true);
            list.replaceChildren();
        }
    }

    function resetForm() {
        if (!form) {
            return;
        }
        form.reset();
        moduleIdInput.value = '';
        document.getElementById('module-form-heading').textContent = 'Add a module source';
        submitButton.textContent = 'Add source';
        cancelButton.hidden = true;
        setStatus(formStatus, '', false);
    }

    function editModule(module) {
        moduleIdInput.value = String(module.moduleId);
        if (isAdmin) {
            blockInput.value = String(module.blockId || '');
        }
        courseInput.value = module.course || '';
        subjectInput.value = module.subject || '';
        titleInput.value = module.title || '';
        urlInput.value = module.resourceUrl || '';
        descriptionInput.value = module.description || '';
        document.getElementById('module-form-heading').textContent = 'Edit module source';
        submitButton.textContent = 'Save changes';
        cancelButton.hidden = false;
        setStatus(formStatus, '', false);
        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        titleInput.focus();
    }

    async function deleteModule(module) {
        if (!window.confirm('Delete "' + module.title + '"?')) {
            return;
        }
        try {
            const result = await request('./api/module_sources/delete.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ moduleId: module.moduleId })
            });
            setStatus(status, result.message, false);
            await loadModules();
        } catch (error) {
            setStatus(status, error.message, true);
        }
    }

    if (form) {
        cancelButton.addEventListener('click', resetForm);
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            submitButton.disabled = true;
            setStatus(formStatus, 'Saving…', false);
            const payload = {
                moduleId: moduleIdInput.value || null,
                course: courseInput.value,
                subject: subjectInput.value,
                title: titleInput.value,
                description: descriptionInput.value,
                resourceUrl: urlInput.value
            };
            if (isAdmin) {
                payload.blockId = blockInput.value;
            }

            try {
                const result = await request('./api/module_sources/save.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                resetForm();
                setStatus(formStatus, result.message, false);
                await loadModules();
            } catch (error) {
                setStatus(formStatus, error.message, true);
            } finally {
                submitButton.disabled = false;
            }
        });
    }

    search.addEventListener('input', function () {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(loadModules, 250);
    });

    loadModules();
});
