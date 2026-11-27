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
    const fileInput = document.getElementById('module-source-file');
    const currentFileBox = document.getElementById('module-source-current-file');
    const currentFileName = document.getElementById('module-source-current-file-name');
    const removeFileCheckbox = document.getElementById('module-source-remove-file');
    let modules = [];
    let searchTimer;
    let editingHasFile = false;

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
        if (!value) {
            return '';
        }
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

    function formatBytes(bytes) {
        if (bytes == null || bytes < 0) {
            return '';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        if (bytes < 1024 * 1024) {
            return (bytes / 1024).toFixed(1) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function isPdf(module) {
        const mime = (module.mimeType || '').toLowerCase();
        const name = (module.originalName || '').toLowerCase();
        return mime.indexOf('pdf') !== -1 || name.endsWith('.pdf');
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

            const actionsRow = document.createElement('div');
            actionsRow.className = 'module-source-links';

            if (module.hasFile) {
                const viewLink = document.createElement('a');
                viewLink.className = 'module-source-link module-source-link-file';
                // Open in-app viewer so ComfiBot can use the resource context
                viewLink.href = './module_viewer.php?id=' + encodeURIComponent(String(module.moduleId));
                const sizeLabel = formatBytes(module.fileSize);
                const fileLabel = module.originalName
                    ? (isPdf(module) ? 'View PDF' : 'Open file') + ' · ' + module.originalName
                    : (isPdf(module) ? 'View PDF' : 'Open file');
                viewLink.textContent = fileLabel + (sizeLabel ? ' (' + sizeLabel + ')' : '');
                actionsRow.appendChild(viewLink);

                if (module.downloadUrl) {
                    const dl = document.createElement('a');
                    dl.className = 'module-source-link module-source-link-download';
                    dl.href = module.downloadUrl;
                    dl.textContent = 'Download';
                    actionsRow.appendChild(dl);
                }
            }

            const url = safeHttpUrl(module.resourceUrl);
            if (url) {
                const link = document.createElement('a');
                link.className = 'module-source-link';
                link.href = url;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = 'Open external link';
                actionsRow.appendChild(link);
            }

            if (actionsRow.childNodes.length) {
                card.appendChild(actionsRow);
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
        editingHasFile = false;
        if (currentFileBox) {
            currentFileBox.hidden = true;
        }
        if (removeFileCheckbox) {
            removeFileCheckbox.checked = false;
        }
        document.getElementById('module-form-heading').textContent = 'Add a module source';
        submitButton.textContent = 'Add source';
        cancelButton.hidden = true;
        setStatus(formStatus, '', false);
    }

    function editModule(module) {
        moduleIdInput.value = String(module.moduleId);
        if (isAdmin && blockInput) {
            blockInput.value = String(module.blockId || '');
        }
        courseInput.value = module.course || '';
        subjectInput.value = module.subject || '';
        titleInput.value = module.title || '';
        urlInput.value = module.resourceUrl || '';
        descriptionInput.value = module.description || '';
        if (fileInput) {
            fileInput.value = '';
        }
        editingHasFile = Boolean(module.hasFile);
        if (currentFileBox && currentFileName) {
            if (module.hasFile) {
                currentFileBox.hidden = false;
                currentFileName.textContent = 'Current file: ' + (module.originalName || 'attached file')
                    + (module.fileSize != null ? ' (' + formatBytes(module.fileSize) + ')' : '');
                if (removeFileCheckbox) {
                    removeFileCheckbox.checked = false;
                }
            } else {
                currentFileBox.hidden = true;
            }
        }
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

            const hasFileSelected = fileInput && fileInput.files && fileInput.files.length > 0;
            const formData = new FormData();
            if (moduleIdInput.value) {
                formData.append('moduleId', moduleIdInput.value);
            }
            formData.append('course', courseInput.value);
            formData.append('subject', subjectInput.value);
            formData.append('title', titleInput.value);
            formData.append('description', descriptionInput.value);
            formData.append('resourceUrl', urlInput.value);
            formData.append('csrf', csrfToken);
            if (isAdmin && blockInput) {
                formData.append('blockId', blockInput.value);
            }
            if (removeFileCheckbox && removeFileCheckbox.checked) {
                formData.append('removeFile', '1');
            }
            if (hasFileSelected) {
                formData.append('resourceFile', fileInput.files[0]);
            }

            try {
                const response = await fetch('./api/module_sources/save.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-Token': csrfToken
                    },
                    body: formData
                });
                const result = await response.json().catch(function () {
                    return {};
                });
                if (!response.ok || !result.ok) {
                    throw new Error(result.message || 'The request could not be completed.');
                }
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
