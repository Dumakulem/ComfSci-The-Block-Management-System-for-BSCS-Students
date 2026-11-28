/* =========================================================
   Student Portal — behaviour (vanilla JS, talks to api.php)
   One page: Admins · Role search · Subjects · Students (by block) · Archive
   ========================================================= */
(() => {
  'use strict';

  const YEARS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
  const PROGRAMS = ['BSCS', 'BSIT', 'BSEMC-GD', 'BSEMC-DAT'];

  const HOME = 'Admin Panel'; // the page that holds Admins, Subjects, Students and Archive

  /* ---------- State ---------- */
  const state = {
    db: { subjects: {}, students: [], blocks: [], archived: [], admins: [] },
    page: HOME,
    year: YEARS[0],
    subjectProgram: 'All',
    studentYear: YEARS[0],
    studentProgram: 'All',
    studentBlock: 'All', // 'All' or a block id
    subjectId: null,
    studentId: null,
    userSearch: '',
    roleTargetId: null,
    pendingRole: '',
    roleStatus: '',
  };

  /* ---------- DOM helpers ---------- */
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const pad = (n) => String(n).padStart(2, '0');
  const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

  const content = $('#content');
  const modalRoot = $('#modalRoot');
  const sidebar = $('#sidebar');
  const scrim = $('#scrim');
  const menuBtn = $('#menuBtn');
  const SECTION_COPY = {
    Dashboard: 'See an overview of your portal activity.',
    'Block Forum': 'Join conversations with your classmates and faculty.',
    Announcements: 'Keep up with the latest class announcements.',
    'Event Operations': 'Plan and manage school events and activities.',
    Attendance: 'Track attendance across all subjects.',
    'Gamified Quiz': 'Take quizzes, earn points, and check your progress.',
    Assignments: 'View and manage upcoming coursework.',
    'Module Sources': 'Access learning modules and class resources.',
    Resources: 'Browse shared files and learning resources.',
  };

  /* ---------- API ---------- */
  async function api(body) {
    const res = await fetch('api.php', body
      ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
      : { cache: 'no-store' });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || 'Something went wrong. Please try again.');
    state.db = data;
    return data;
  }

  function toast(message) {
    $('.toast')?.remove();
    const el = document.createElement('div');
    el.className = 'toast';
    el.setAttribute('role', 'status');
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3200);
  }

  /* ---------- Derived data ---------- */
  const subjects = () => state.db.subjects[state.year] || [];
  const subjectsFor = (year, program) => (state.db.subjects[year] || [])
    .filter((s) => !program || program === 'All' || s.program === program);

  const sortBlocks = (a, b) => (PROGRAMS.indexOf(a.program) - PROGRAMS.indexOf(b.program))
    || a.name.localeCompare(b.name, undefined, { numeric: true });
  const blocksFor = (year, program) => (state.db.blocks || [])
    .filter((b) => b.yearLevel === year && (!program || program === 'All' || b.program === program))
    .sort(sortBlocks);
  const studentsFor = (year, program, blockId = 'All') => state.db.students.filter((s) =>
    (!year || s.yearLevel === year) && (!program || program === 'All' || s.program === program)
    && (blockId === 'All' || s.blockId === blockId));

  const selectedSubject = () => subjects().find((s) => s.id === state.subjectId) || null;
  const selectedStudent = () => state.db.students.find((s) => s.id === state.studentId) || null;
  const roleTarget = () => state.db.students.find((s) => s.id === state.roleTargetId) || null;
  const matchingUsers = () => {
    const q = state.userSearch.trim().toLowerCase();
    if (!q) return [];
    return state.db.students.filter((s) =>
      [s.fullName, s.studentNumber, s.email].some((v) => v.toLowerCase().includes(q)));
  };

  /** A block filter that no longer exists in the current year / program falls back to "All". */
  function normalizeBlockFilter() {
    if (state.studentBlock !== 'All' && !blocksFor(state.studentYear, state.studentProgram).some((b) => b.id === state.studentBlock)) {
      state.studentBlock = 'All';
    }
  }

  /* ---------- Page renderers ---------- */
  function render() {
    if (state.page !== HOME) return renderPlaceholder();
    normalizeBlockFilter();
    if (selectedSubject()) return renderSubjectDetail();
    if (selectedStudent()) return renderStudentDetail();
    renderDashboard();
  }

  function renderPlaceholder() {
    content.innerHTML = `
      <section class="subject-detail">
        <div class="subject-object">
          <p class="eyebrow">Admin portal</p>
          <h1>${esc(state.page)}</h1>
          <p>${esc(SECTION_COPY[state.page] || '')}</p>
          <button class="primary-button" type="button" data-act="go-home">Back to ${HOME}</button>
        </div>
      </section>`;
  }

  function navigate(label) {
    state.page = label;
    state.subjectId = null;
    state.studentId = null;
    $$('.nav-item[data-nav]').forEach((b) => {
      const on = b.dataset.nav === label;
      b.classList.toggle('nav-item-active', on);
      if (on) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current');
    });
    $('#mobileTitle').textContent = label;
    setMenu(false);
    render();
    window.scrollTo({ top: 0 });
  }

  function setMenu(open) {
    sidebar.classList.toggle('sidebar-open', open);
    scrim.hidden = !open;
    menuBtn.setAttribute('aria-expanded', String(open));
  }

  function confirmLogout() {
    confirmModal({
      eyebrow: 'End session', title: 'Log out of your account?', confirm: 'Logout',
      text: 'You can return to this portal at any time.',
      action: async () => { navigate(HOME); toast('You have been logged out.'); },
    });
  }

  function detailMarkup(backLabel, backAct, eyebrow, title, intro, rows, extra = '') {
    return `
      <section class="subject-detail">
        <button class="back-button" type="button" data-act="${backAct}">← ${esc(backLabel)}</button>
        <div class="subject-object">
          <p class="eyebrow">${esc(eyebrow)}</p>
          <h1>${esc(title)}</h1>
          <p>${esc(intro)}</p>
          <dl class="subject-metadata">
            ${rows.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v || 'Not provided')}</dd></div>`).join('')}
          </dl>
          ${extra}
          <button class="primary-button" type="button" data-act="${backAct}">Done</button>
        </div>
      </section>`;
  }

  function renderSubjectDetail() {
    const s = selectedSubject();
    content.innerHTML = detailMarkup(
      'Back to subjects', 'back-subject', `${s.program ? `${s.program} · ` : ''}${state.year} subject workspace`, s.name,
      s.description || 'This subject is ready for modules, assignments, and class activities.',
      [['Subject code', s.code], ['Program', s.program || 'Not set — use Edit'], ['Year level', state.year], ['Instructor', s.instructor], ['Schedule', s.schedule], ['Room', s.room]]);
  }

  function renderStudentDetail() {
    const s = selectedStudent();
    content.innerHTML = detailMarkup(
      'Back to students', 'back-student', 'Student profile', s.fullName, s.program,
      [['Student number', s.studentNumber], ['Email address', s.email], ['Program', s.program], ['Year level', s.yearLevel],
        ['Block', `Block ${s.block}`], ['Role', s.role]]);
  }

  /* ---------- Cards ---------- */
  function cardMarkup({ kind, item, index, title, meta, extraMeta = '', open = true, actions, cols }) {
    const head = `
          <span class="subject-index">${pad(index + 1)}</span>
          <span class="subject-copy"><strong>${esc(title)}</strong><small title="${esc(meta)}">${esc(meta)}</small>${extraMeta}</span>
          ${open ? '<span class="subject-arrow">→</span>' : ''}`;
    return `
      <article class="subject-card ${kind}-card">
        ${open
          ? `<button class="subject-open" type="button" data-act="open-${kind}" data-id="${esc(item.id)}">${head}</button>`
          : `<div class="subject-open">${head}</div>`}
        <div class="subject-actions subject-actions-${cols}">${actions}</div>
      </article>`;
  }

  const actionBtn = (cls, label, act, item, aria) =>
    `<button class="${cls}" type="button" aria-label="${esc(aria)}" data-act="${act}" data-id="${esc(item.id)}">${label}</button>`;

  const subjectCard = (s, i) => cardMarkup({
    kind: 'subject', item: s, index: i, title: s.name,
    meta: `${s.code || state.year} · ${s.program || 'No program'}`,
    cols: 2,
    actions: actionBtn('edit-subject', 'Edit', 'edit-subject', s, `Edit ${s.name}`)
      + actionBtn('delete-subject', 'Delete', 'delete-subject', s, `Delete ${s.name}`),
  });

  const studentCard = (s, i) => cardMarkup({
    kind: 'student', item: s, index: i, title: s.fullName,
    meta: `${s.studentNumber} · ${s.program} · ${s.role}`,
    cols: 2,
    actions: actionBtn('edit-subject', 'Edit', 'edit-student', s, `Edit ${s.fullName}`)
      + actionBtn('delete-subject', 'Delete', 'delete-student', s, `Delete ${s.fullName}`),
  });

  const archivedCard = (s, i) => cardMarkup({
    kind: 'archived', item: s, index: i, title: s.fullName, open: false,
    meta: `${s.studentNumber} · ${s.program} · ${s.yearLevel} · Block ${s.block}`,
    extraMeta: s.archivedAt ? `<small>Deleted ${esc(s.archivedAt.slice(0, 10))}</small>` : '',
    cols: 'archive',
    actions: actionBtn('view-archived', 'View details', 'view-archived', s, `View details of ${s.fullName}`)
      + actionBtn('restore-student', 'Restore', 'restore-student', s, `Restore ${s.fullName}`)
      + actionBtn('delete-subject', 'Delete forever', 'purge-student', s, `Permanently delete ${s.fullName}`),
  });

  const emptyMarkup = (letter, title, text) =>
    `<div class="empty-year"><span>${esc(letter)}</span><strong>${esc(title)}</strong><p>${esc(text)}</p></div>`;

  function subjectGridMarkup() {
    const list = subjectsFor(state.year, state.subjectProgram);
    if (!list.length) {
      const scope = state.subjectProgram === 'All' ? state.year : `${state.year} · ${state.subjectProgram}`;
      return emptyMarkup(state.year.charAt(0), `No subjects in ${scope} yet`,
        'Use the Add button to create a subject, or pick another year level or program.');
    }
    return list.map(subjectCard).join('');
  }

  function adminGridMarkup() {
    const list = state.db.admins || [];
    if (!list.length) {
      return emptyMarkup('A', 'No admin accounts yet', 'Use the Add button to create an admin who can sign in through the login system.');
    }
    return list.map((a, i) => cardMarkup({
      kind: 'admin', item: a, index: i, title: a.fullName, meta: a.email, open: false, cols: 1,
      actions: actionBtn('delete-subject', 'Remove', 'delete-admin', a, `Remove ${a.fullName}`),
    })).join('');
  }

  /** Students grouped into blocks (one box per block of the chosen year / program). */
  function studentBlocksMarkup() {
    const blocks = blocksFor(state.studentYear, state.studentProgram)
      .filter((b) => state.studentBlock === 'All' || b.id === state.studentBlock);
    if (!blocks.length) {
      const scope = [state.studentYear, state.studentProgram === 'All' ? '' : state.studentProgram].filter(Boolean).join(' · ');
      return emptyMarkup(state.studentYear.charAt(0), `No blocks in ${scope} yet`,
        'Use “+ Block” to create a block, or add a student and Block A is created automatically.');
    }
    return `<div class="block-list">${blocks.map((b) => {
      const list = state.db.students.filter((s) => s.blockId === b.id);
      return `
        <section class="block-group" aria-label="${esc(`${b.program} ${b.yearLevel} Block ${b.name}`)}">
          <div class="block-head">
            <div><p class="eyebrow">${esc(b.program)} · ${esc(b.yearLevel)}</p><h2>Block ${esc(b.name)}</h2></div>
            <div class="block-head-meta">
              <span class="block-count">${plural(list.length, 'student')}</span>
              ${list.length === 0 ? `<button class="block-delete" type="button" data-act="delete-block" data-id="${esc(b.id)}" aria-label="Delete Block ${esc(b.name)}">Delete block</button>` : ''}
              <button class="program-chip program-chip-add" type="button" data-act="add-student" data-block="${esc(b.id)}">+ Student</button>
            </div>
          </div>
          ${list.length
            ? `<div class="subject-grid">${list.map(studentCard).join('')}</div>`
            : '<p class="block-empty">No students in this block yet.</p>'}
        </section>`;
    }).join('')}</div>`;
  }

  function archiveGridMarkup() {
    const list = state.db.archived || [];
    if (!list.length) {
      return emptyMarkup('✓', 'The archive is empty', 'Students you delete are kept here, so you can restore them later.');
    }
    return list.map(archivedCard).join('');
  }

  const sectionMarkup = (label, eyebrow, title, addAct, body) => `
    <section class="section" aria-label="${esc(label)}">
      <div class="section-bar">
        <div><p class="eyebrow">${eyebrow}</p><h1>${esc(title)}</h1></div>
        ${addAct ? `<button class="add-button" type="button" data-act="${addAct}">Add</button>` : ''}
      </div>
      <div class="panel">${body}</div>
    </section>`;

  const yearTabsMarkup = (label, act, current, countFor, noun) => `
    <div class="year-levels" role="tablist" aria-label="${esc(label)}">
      ${YEARS.map((y) => {
        const n = countFor(y);
        return `<button class="year-tab ${y === current ? 'year-tab-active' : ''}" type="button" role="tab"
                  aria-selected="${y === current}" data-act="${act}" data-year="${y}">
                  <span>${y}</span><small>${plural(n, noun)}</small></button>`;
      }).join('')}
    </div>`;

  const chipsMarkup = (label, act, current, items) => `
    <div class="program-filter" role="group" aria-label="${esc(label)}">
      <span class="program-filter-label">${esc(label.split(' ')[0])}</span>
      ${items.map(({ value, text, n }) => `
        <button class="program-chip ${value === current ? 'program-chip-active' : ''}" type="button"
                aria-pressed="${value === current}" data-act="${act}" data-value="${esc(value)}">
                ${esc(text)}<small>${n}</small></button>`).join('')}
    </div>`;

  function renderDashboard() {
    const admins = state.db.admins || [];
    const programItems = (year, countFor) => ['All', ...PROGRAMS].map((p) => ({ value: p, text: p === 'All' ? 'ALL' : p, n: countFor(year, p) }));

    const blockChips = (() => {
      const blocks = blocksFor(state.studentYear, state.studentProgram);
      const items = [{ value: 'All', text: 'ALL', n: studentsFor(state.studentYear, state.studentProgram).length },
        ...blocks.map((b) => ({
          value: b.id,
          text: state.studentProgram === 'All' ? `${b.program} · ${b.name}` : `Block ${b.name}`,
          n: state.db.students.filter((s) => s.blockId === b.id).length,
        }))];
      return `${chipsMarkup('Block filter', 'student-block', state.studentBlock, items).replace(
        '</div>', '<button class="program-chip program-chip-add" type="button" data-act="add-block">+ Block</button></div>')}`;
    })();

    content.innerHTML = [
      sectionMarkup('Admin accounts',
        `Administration · ${plural(admins.length, 'account')}`, 'Admins', 'add-admin',
        `<div class="subject-grid" id="adminGrid">${adminGridMarkup()}</div>`),

      `<section class="user-management" aria-label="User role management">
        <div class="user-search-wrap">
          <label for="user-search">Search users</label>
          <div class="search-input-wrap">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.15a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
            <input id="user-search" type="search" placeholder="Search by name, student number, or email" autocomplete="off">
          </div>
          <div class="user-search-results" id="searchResults" hidden></div>
        </div>
        <div class="role-select-wrap">
          <label for="role-select">Select role</label>
          <div class="role-actions">
            <select id="role-select">
              <option value="" disabled>Select Role</option>
              <option value="Block Rep">Block Rep</option>
              <option value="Student">Student</option>
            </select>
            <button type="button" id="saveRole" data-act="save-role">Save changes</button>
          </div>
          <small id="roleStatus"></small>
        </div>
      </section>`,

      sectionMarkup('Subjects',
        `My learning space · ${esc(state.year)}${state.subjectProgram === 'All' ? '' : ` · ${esc(state.subjectProgram)}`}`,
        'Subjects', 'add-subject', `
          ${yearTabsMarkup('Year levels', 'year', state.year, (y) => subjectsFor(y, state.subjectProgram).length, 'subject')}
          ${chipsMarkup('Program filter for subjects', 'subject-program', state.subjectProgram,
            programItems(state.year, (y, p) => subjectsFor(y, p).length))}
          <div class="subject-grid" role="tabpanel" id="subjectGrid">${subjectGridMarkup()}</div>`),

      sectionMarkup('Students',
        `Student directory · ${esc(state.studentYear)} · ${studentsFor(state.studentYear, 'All').length} enrolled`,
        'Students', 'add-student', `
          ${yearTabsMarkup('Student year levels', 'student-year', state.studentYear,
            (y) => studentsFor(y, state.studentProgram).length, 'student')}
          ${chipsMarkup('Program filter for students', 'student-program', state.studentProgram,
            programItems(state.studentYear, (y, p) => studentsFor(y, p).length))}
          ${blockChips}
          <div id="studentGrid">${studentBlocksMarkup()}</div>`),

      sectionMarkup('Archive',
        `Deleted students · ${(state.db.archived || []).length} archived`, 'Archive', '', `
          <p class="archive-note">Deleted students are kept here. Restore puts a student back in their block; Delete forever removes the record for good.</p>
          <div class="subject-grid" id="archiveGrid">${archiveGridMarkup()}</div>`),
    ].join('');

    syncRoleControls();
    $('#user-search').value = state.userSearch;
    updateSearchResults();
  }

  /* ---------- Role management (partial updates keep input focus) ---------- */
  function syncRoleControls() {
    const target = roleTarget();
    const select = $('#role-select');
    const save = $('#saveRole');
    const status = $('#roleStatus');
    if (!select) return;
    select.disabled = !target;
    select.value = state.pendingRole || '';
    save.disabled = !target || !state.pendingRole || state.pendingRole === target.role;
    status.textContent = state.roleStatus || (target ? `Editing ${target.fullName}` : 'Select a user first');
    status.className = state.roleStatus ? 'role-status-success' : '';
  }

  function updateSearchResults() {
    const box = $('#searchResults');
    if (!box) return;
    const q = state.userSearch.trim();
    const target = roleTarget();
    if (!q || state.userSearch === target?.fullName) { box.hidden = true; return; }
    const matches = matchingUsers();
    box.hidden = false;
    box.innerHTML = matches.length
      ? matches.map((s) => `<button type="button" data-act="pick-user" data-id="${esc(s.id)}">
            <span>${esc(s.fullName)}</span><small>${esc(s.studentNumber)} · ${esc(s.role)}</small></button>`).join('')
      : '<p>No users found</p>';
  }

  /* ---------- Modals ---------- */
  function openModal(html, { onSubmit, focus } = {}) {
    modalRoot.innerHTML = `<div class="modal-backdrop" role="presentation">${html}</div>`;
    const backdrop = $('.modal-backdrop', modalRoot);
    const modal = $('.modal', backdrop);
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');

    // Click outside the dialog closes it (mousedown so text-selection drags don't).
    backdrop.addEventListener('mousedown', (e) => { if (e.target === backdrop) closeModal(); });
    $$('[data-close]', modal).forEach((b) => b.addEventListener('click', closeModal));

    if (onSubmit) {
      modal.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submit = $('[type=submit]', modal);
        const err = $('.form-error', modal);
        submit.disabled = true;
        try { await onSubmit(modal); closeModal(); }
        catch (error) {
          if (err) { err.textContent = error.message; err.hidden = false; } else toast(error.message);
          submit.disabled = false;
        }
      });
    }
    (focus ? $(focus, modal) : $('input, button', modal))?.focus();
    return modal;
  }
  function closeModal() { modalRoot.innerHTML = ''; }

  /** Disable a submit button until all [required] fields in the form are filled. */
  function bindRequired(modal, isValid) {
    const submit = $('[type=submit]', modal);
    const check = () => { submit.disabled = !isValid(modal); };
    modal.addEventListener('input', check);
    modal.addEventListener('change', check);
    check();
  }

  const val = (modal, name) => $(`[name="${name}"]`, modal).value.trim();
  const rawVal = (modal, name) => $(`[name="${name}"]`, modal).value; // not trimmed: passwords keep every character

  /** Small confirmation dialog: title, text, confirm label, async action. */
  function confirmModal({ eyebrow, title, text, confirm, action }) {
    const modal = openModal(`
      <form class="modal" aria-labelledby="mt" novalidate>
        <p class="eyebrow">${esc(eyebrow)}</p>
        <h2 id="mt">${esc(title)}</h2>
        <p>${esc(text)}</p>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="danger-button" type="submit">${esc(confirm)}</button>
        </div>
      </form>`, { onSubmit: action });
    $('.danger-button', modal).focus();
  }

  /* ---------- Admin accounts ---------- */
  function adminModal() {
    const field = (n, label, ph, type = 'text', wide = false, auto = 'off') => `
      <label class="form-field ${wide ? 'form-field-wide' : ''}"><span>${label}</span>
        <input name="${n}" type="${type}" maxlength="${type === 'password' ? 72 : 100}" placeholder="${esc(ph)}" autocomplete="${auto}"></label>`;
    const modal = openModal(`
      <form class="modal subject-form-modal" aria-labelledby="mt" novalidate autocomplete="off">
        <p class="eyebrow">Administration</p>
        <h2 id="mt">Add an admin account</h2>
        <p>The new admin signs in on the login page with this email and password.</p>
        <div class="subject-form-grid">
          ${field('firstName', 'First name *', 'e.g. Maria')}
          ${field('lastName', 'Last name *', 'e.g. Santos')}
          ${field('middleName', 'Middle name', 'optional')}
          ${field('extensionName', 'Extension', 'e.g. Jr., III')}
          ${field('email', 'Email address *', 'e.g. admin@school.edu', 'email', true)}
          ${field('password', 'Password *', 'At least 8 characters', 'password', false, 'new-password')}
          ${field('confirm', 'Confirm password *', 'Type it again', 'password', false, 'new-password')}
        </div>
        <small class="form-hint" id="adminHint">The password must be at least 8 characters.</small>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="primary-button" type="submit">Add admin</button>
        </div>
      </form>`, {
      focus: '[name=firstName]',
      onSubmit: async (m) => {
        await api({
          action: 'admin_add', firstName: val(m, 'firstName'), lastName: val(m, 'lastName'),
          middleName: val(m, 'middleName'), extensionName: val(m, 'extensionName'), email: val(m, 'email'),
          password: rawVal(m, 'password'),
        });
        toast('Admin account created.');
        render();
      },
    });
    bindRequired(modal, (m) => val(m, 'firstName') && val(m, 'lastName') && val(m, 'email')
      && rawVal(m, 'password').length >= 8 && rawVal(m, 'password') === rawVal(m, 'confirm'));
    modal.addEventListener('input', () => {
      const pw = rawVal(modal, 'password');
      const cf = rawVal(modal, 'confirm');
      $('#adminHint', modal).textContent = pw && pw.length < 8 ? 'Use at least 8 characters.'
        : cf && pw !== cf ? 'The passwords don’t match.' : 'The password must be at least 8 characters.';
    });
  }

  function adminDeleteModal(id) {
    const a = (state.db.admins || []).find((x) => x.id === id);
    if (!a) return;
    confirmModal({
      eyebrow: 'Administration', title: 'Remove admin?', confirm: 'Remove',
      text: `“${a.fullName}” will no longer be able to sign in as an admin. This action cannot be undone.`,
      action: async () => { await api({ action: 'admin_delete', id }); toast('Admin account removed.'); render(); },
    });
  }

  /* ---------- Subject form (shared by Add and Edit) ---------- */
  function subjectFieldsMarkup(v) {
    const sel = (name, label, options, current, placeholder) => `
      <label class="form-field"><span>${label}</span>
        <select name="${name}">
          <option value="" disabled ${options.includes(current) ? '' : 'selected'}>${placeholder}</option>
          ${options.map((o) => `<option value="${esc(o)}" ${o === current ? 'selected' : ''}>${esc(o)}</option>`).join('')}
        </select></label>`;
    return `
      ${sel('year', 'Year level *', YEARS, v.year, 'Select year level')}
      ${sel('program', 'Program *', PROGRAMS, v.program, 'Select program')}
      <label class="form-field form-field-wide"><span>Subject name *</span>
        <input name="name" maxlength="100" placeholder="e.g. Data Structures" value="${esc(v.name)}"></label>
      <label class="form-field"><span>Subject code *</span>
        <input name="code" maxlength="30" placeholder="e.g. CS 201" value="${esc(v.code)}"></label>
      <label class="form-field"><span>Instructor *</span>
        <input name="instructor" maxlength="100" placeholder="e.g. Prof. A. Reyes" value="${esc(v.instructor)}"></label>
      <label class="form-field"><span>Schedule</span>
        <input name="schedule" maxlength="100" placeholder="e.g. Mon &amp; Wed · 9:00 AM" value="${esc(v.schedule)}"></label>
      <label class="form-field"><span>Room</span>
        <input name="room" maxlength="60" placeholder="e.g. Room 204" value="${esc(v.room)}"></label>
      <label class="form-field form-field-wide"><span>Description</span>
        <textarea name="description" rows="3" maxlength="500" placeholder="Briefly describe this subject and its learning goals.">${esc(v.description)}</textarea></label>`;
  }

  const subjectPayload = (m) => ({
    year: val(m, 'year'), program: val(m, 'program'), name: val(m, 'name'), code: val(m, 'code'),
    instructor: val(m, 'instructor'), schedule: val(m, 'schedule'), room: val(m, 'room'), description: val(m, 'description'),
  });
  const subjectValid = (m) => val(m, 'year') && val(m, 'program') && val(m, 'name') && val(m, 'code') && val(m, 'instructor');

  /** After saving, show the year / program the subject now lives in. */
  function followSubject(payload) {
    state.year = payload.year;
    if (state.subjectProgram !== 'All' && state.subjectProgram !== payload.program) state.subjectProgram = 'All';
  }

  function addSubjectModal() {
    const v = {
      year: state.year, program: state.subjectProgram === 'All' ? '' : state.subjectProgram,
      name: '', code: '', instructor: '', schedule: '', room: '', description: '',
    };
    const modal = openModal(`
      <form class="modal subject-form-modal" aria-labelledby="mt" novalidate>
        <p class="eyebrow">Subjects</p>
        <h2 id="mt">Add a new subject</h2>
        <p>Enter the class details so students can quickly identify the subject.</p>
        <div class="subject-form-grid">${subjectFieldsMarkup(v)}</div>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="primary-button" type="submit">Add subject</button>
        </div>
      </form>`, {
      focus: '[name=name]',
      onSubmit: async (m) => {
        const payload = subjectPayload(m);
        await api({ action: 'subject_add', ...payload });
        followSubject(payload);
        const added = subjects().at(-1);
        state.subjectId = added ? added.id : null; // opens the new subject
        render();
      },
    });
    bindRequired(modal, subjectValid);
  }

  function editSubjectModal(id) {
    const s = subjects().find((x) => x.id === id);
    if (!s) return;
    const modal = openModal(`
      <form class="modal subject-form-modal" aria-labelledby="mt" novalidate>
        <p class="eyebrow">${esc(s.code)}</p>
        <h2 id="mt">Edit subject</h2>
        <p>Update any detail of this subject.</p>
        <div class="subject-form-grid">${subjectFieldsMarkup({ ...s, year: state.year })}</div>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="primary-button" type="submit">Save changes</button>
        </div>
      </form>`, {
      focus: '[name=name]',
      onSubmit: async (m) => {
        const payload = subjectPayload(m);
        await api({ action: 'subject_update', id, ...payload });
        followSubject(payload);
        render();
      },
    });
    bindRequired(modal, subjectValid);
  }

  function deleteSubjectModal(id) {
    const item = subjects().find((x) => x.id === id);
    if (!item) return;
    confirmModal({
      eyebrow: state.year, title: 'Delete subject?', confirm: 'Delete',
      text: `“${item.name}” will be removed from ${state.year}. This action cannot be undone.`,
      action: async () => { await api({ action: 'subject_delete', id }); render(); },
    });
  }

  /* ---------- Students: delete = archive ---------- */
  function deleteStudentModal(id) {
    const s = state.db.students.find((x) => x.id === id);
    if (!s) return;
    confirmModal({
      eyebrow: 'Student directory', title: 'Delete student?', confirm: 'Delete',
      text: `“${s.fullName}” will be moved to the Archive. You can restore them from there at any time.`,
      action: async () => {
        await api({ action: 'student_delete', id });
        if (state.roleTargetId === id) Object.assign(state, { roleTargetId: null, pendingRole: '', userSearch: '', roleStatus: '' });
        toast(`${s.fullName} moved to the Archive.`);
        render();
      },
    });
  }

  function archivedDetailsModal(id) {
    const s = (state.db.archived || []).find((x) => x.id === id);
    if (!s) return;
    const rows = [
      ['Student number', s.studentNumber], ['Email address', s.email], ['Program', s.program], ['Year level', s.yearLevel],
      ['Block', `Block ${s.block}`], ['Role', s.role], ['Deleted on', s.archivedAt ? s.archivedAt.slice(0, 16).replace('T', ' ') : ''],
    ];
    openModal(`
      <div class="modal subject-form-modal" aria-labelledby="mt">
        <p class="eyebrow">Archive · deleted student</p>
        <h2 id="mt">${esc(s.fullName)}</h2>
        <dl class="subject-metadata" style="margin:22px 0 0;width:100%">
          ${rows.map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v || 'Not provided')}</dd></div>`).join('')}
        </dl>
        <div class="modal-actions">
          <button type="button" data-close>Close</button>
          <button class="primary-button" type="button" data-restore="${esc(s.id)}">Restore</button>
        </div>
      </div>`);
    $('[data-restore]', modalRoot).addEventListener('click', async () => {
      try { await api({ action: 'student_restore', id }); closeModal(); toast(`${s.fullName} restored.`); render(); }
      catch (err) { toast(err.message); }
    });
  }

  function purgeStudentModal(id) {
    const s = (state.db.archived || []).find((x) => x.id === id);
    if (!s) return;
    confirmModal({
      eyebrow: 'Archive', title: 'Delete forever?', confirm: 'Delete forever',
      text: `“${s.fullName}” will be permanently removed. This action cannot be undone.`,
      action: async () => { await api({ action: 'student_purge', id }); toast('Student permanently deleted.'); render(); },
    });
  }

  /* ---------- Students: add / edit form with block ---------- */
  function studentModal(id, presetBlockId) {
    const editing = id ? state.db.students.find((x) => x.id === id) : null;
    const v = editing ? { ...editing } : { firstName: '', middleName: '', lastName: '', extensionName: '', studentNumber: '', email: '', program: '', yearLevel: '', blockId: '' };
    // New students start on whatever year / program / block is currently being browsed.
    if (!editing) {
      const preset = presetBlockId ? (state.db.blocks || []).find((b) => b.id === presetBlockId) : null;
      v.yearLevel = preset ? preset.yearLevel : state.studentYear;
      v.program = preset ? preset.program : (state.studentProgram === 'All' ? '' : state.studentProgram);
      v.blockId = preset ? preset.id : (state.studentBlock !== 'All' ? state.studentBlock : '');
    }
    const fields = [
      ['firstName', 'First name *', 'e.g. Juan', 'text'],
      ['lastName', 'Last name *', 'e.g. Dela Cruz', 'text'],
      ['middleName', 'Middle name', 'e.g. Santos', 'text', false, null, true],
      ['extensionName', 'Extension', 'e.g. Jr., III', 'text', false, null, true],
      ['studentNumber', 'Student number *', 'e.g. 2024-00123', 'text'],
      ['email', 'Email address *', 'e.g. juan@school.edu', 'email'],
      ['program', 'Program *', '', 'select', false, PROGRAMS],
      ['yearLevel', 'Year level *', '', 'select', false, YEARS],
    ];
    const fieldMarkup = ([n, label, ph, type, wide, options]) => type === 'select'
      ? `<label class="form-field ${wide ? 'form-field-wide' : ''}"><span>${label}</span>
          <select name="${n}">
            <option value="" disabled ${options.includes(v[n]) ? '' : 'selected'}>Select ${n === 'program' ? 'program' : 'year level'}</option>
            ${options.map((o) => `<option value="${esc(o)}" ${o === v[n] ? 'selected' : ''}>${esc(o)}</option>`).join('')}
          </select></label>`
      : `<label class="form-field ${wide ? 'form-field-wide' : ''}"><span>${label}</span>
          <input name="${n}" type="${type}" maxlength="120" placeholder="${esc(ph)}" value="${esc(v[n])}"></label>`;
    const modal = openModal(`
      <form class="modal subject-form-modal" aria-labelledby="mt" novalidate>
        <p class="eyebrow">Student directory</p>
        <h2 id="mt">${editing ? 'Edit student' : 'Add a new student'}</h2>
        <p>Enter the student’s enrollment and contact information.</p>
        <div class="subject-form-grid">
          ${fields.map(fieldMarkup).join('')}
          <label class="form-field form-field-wide"><span>Block</span><select name="block"></select></label>
        </div>
        <small class="form-hint">Students are sorted into blocks inside their program and year level.</small>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="primary-button" type="submit">${editing ? 'Save changes' : 'Add student'}</button>
        </div>
      </form>`, {
      focus: '[name=firstName]',
      onSubmit: async (m) => {
        const payload = {};
        fields.forEach(([n]) => { payload[n] = val(m, n); });
        const blockId = val(m, 'block');
        await api({ action: editing ? 'student_update' : 'student_add', id, ...payload, blockId });
        // Jump to where the saved student lives so it is visible straight away.
        state.studentYear = payload.yearLevel;
        if (state.studentProgram !== 'All' && state.studentProgram !== payload.program) state.studentProgram = 'All';
        if (state.studentBlock !== 'All' && state.studentBlock !== blockId) state.studentBlock = 'All';
        render();
      },
    });

    // The block list always follows the chosen program + year level.
    const blockSelect = $('[name=block]', modal);
    const refreshBlocks = (preferred) => {
      const program = val(modal, 'program');
      const year = val(modal, 'yearLevel');
      if (!program || !year) {
        blockSelect.innerHTML = '<option value="">Choose program and year level first</option>';
        blockSelect.disabled = true;
        return;
      }
      const blocks = blocksFor(year, program);
      blockSelect.disabled = false;
      blockSelect.innerHTML = blocks.length
        ? blocks.map((b) => `<option value="${esc(b.id)}">Block ${esc(b.name)}</option>`).join('')
        : '<option value="">Block A (created automatically)</option>';
      if (blocks.some((b) => b.id === preferred)) blockSelect.value = preferred;
    };
    refreshBlocks(v.blockId);
    modal.addEventListener('change', (e) => {
      if (e.target.name === 'program' || e.target.name === 'yearLevel') refreshBlocks('');
    });
    bindRequired(modal, (m) => fields.every(([n, , , , , , optional]) => optional || val(m, n)));
  }

  /* ---------- Blocks ---------- */
  function blockModal() {
    const v = {
      program: state.studentProgram === 'All' ? '' : state.studentProgram,
      yearLevel: state.studentYear,
    };
    const opts = (list, cur, ph) => `<option value="" disabled ${list.includes(cur) ? '' : 'selected'}>${ph}</option>`
      + list.map((o) => `<option value="${esc(o)}" ${o === cur ? 'selected' : ''}>${esc(o)}</option>`).join('');
    const modal = openModal(`
      <form class="modal" aria-labelledby="mt" novalidate>
        <p class="eyebrow">Student directory</p>
        <h2 id="mt">Add a block</h2>
        <p>A block groups students of one program and year level, like Block A or Block B.</p>
        <div class="subject-form-grid" style="grid-template-columns:1fr">
          <label class="form-field"><span>Program *</span><select name="program">${opts(PROGRAMS, v.program, 'Select program')}</select></label>
          <label class="form-field"><span>Year level *</span><select name="yearLevel">${opts(YEARS, v.yearLevel, 'Select year level')}</select></label>
        </div>
        <small class="form-hint" id="blockHint"></small>
        <p class="form-error" role="alert" hidden></p>
        <div class="modal-actions">
          <button type="button" data-close>Cancel</button>
          <button class="primary-button" type="submit">Add block</button>
        </div>
      </form>`, {
      focus: '[name=program]',
      onSubmit: async (m) => {
        const program = val(m, 'program');
        const yearLevel = val(m, 'yearLevel');
        const before = new Set(blocksFor(yearLevel, program).map((b) => b.id));
        await api({ action: 'block_add', program, yearLevel });
        const added = blocksFor(yearLevel, program).find((b) => !before.has(b.id));
        state.studentYear = yearLevel;
        state.studentProgram = program;
        state.studentBlock = 'All';
        toast(`${added ? `Block ${added.name}` : 'Block'} added to ${program} · ${yearLevel}.`);
        render();
      },
    });
    const hint = () => {
      const program = val(modal, 'program');
      const year = val(modal, 'yearLevel');
      if (!program || !year) { $('#blockHint', modal).textContent = ''; return; }
      const used = blocksFor(year, program).map((b) => b.name.toUpperCase());
      const next = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').find((l) => !used.includes(l));
      $('#blockHint', modal).textContent = next
        ? `This will create Block ${next} for ${program} · ${year}.` : 'This program and year level already has 26 blocks.';
    };
    modal.addEventListener('change', hint);
    hint();
    bindRequired(modal, (m) => val(m, 'program') && val(m, 'yearLevel'));
  }

  function deleteBlockModal(id) {
    const b = (state.db.blocks || []).find((x) => x.id === id);
    if (!b) return;
    const archived = (state.db.archived || []).filter((s) => s.blockId === id);
    if (archived.length) {
      // Archived students still belong to their block, so it can't be removed until they are restored or purged.
      const modal = openModal(`
        <div class="modal" aria-labelledby="mt">
          <p class="eyebrow">${esc(b.program)} · ${esc(b.yearLevel)}</p>
          <h2 id="mt">Block ${esc(b.name)} can’t be deleted yet</h2>
          <p>It still holds ${plural(archived.length, 'archived student')} (${esc(archived.map((s) => s.fullName).join(', '))}).
             Open the Archive and use Restore to move them back, or Delete forever to remove them. Then delete this block.</p>
          <div class="modal-actions"><button type="button" data-close>Got it</button></div>
        </div>`);
      $('button', modal).focus();
      return;
    }
    confirmModal({
      eyebrow: `${b.program} · ${b.yearLevel}`, title: `Delete Block ${b.name}?`, confirm: 'Delete',
      text: 'This block has no students, so it can be removed safely.',
      action: async () => { await api({ action: 'block_delete', id }); render(); },
    });
  }

  /* ---------- Event wiring (delegated) ---------- */
  document.addEventListener('click', async (e) => {
    const nav = e.target.closest('[data-nav]');
    if (nav) return navigate(nav.dataset.nav);

    const el = e.target.closest('[data-act]');
    if (!el) {
      // Click away from the search results closes them once a user is picked.
      if (!e.target.closest('.user-search-wrap')) {
        const b = $('#searchResults');
        if (b && state.userSearch.trim() && state.userSearch === roleTarget()?.fullName) b.hidden = true;
      }
      return;
    }
    const { act, id, value } = el.dataset;
    const guarded = async (fn) => { try { await fn(); } catch (err) { toast(err.message); } };

    switch (act) {
      case 'go-home': return navigate(HOME);
      case 'back-subject': state.subjectId = null; return render();
      case 'back-student': state.studentId = null; return render();
      case 'open-subject': state.subjectId = id; return render();
      case 'open-student': state.studentId = id; return render();
      case 'year': state.year = el.dataset.year; state.subjectId = null; return render();
      case 'subject-program': state.subjectProgram = value; state.subjectId = null; return render();
      case 'student-year': state.studentYear = el.dataset.year; return render();
      case 'student-program': state.studentProgram = value; return render();
      case 'student-block': state.studentBlock = value; return render();
      case 'add-admin': return adminModal();
      case 'delete-admin': return adminDeleteModal(id);
      case 'add-subject': return addSubjectModal();
      case 'edit-subject': return editSubjectModal(id);
      case 'delete-subject': return deleteSubjectModal(id);
      case 'add-student': return studentModal(undefined, el.dataset.block);
      case 'edit-student': return studentModal(id);
      case 'delete-student': return deleteStudentModal(id);
      case 'add-block': return blockModal();
      case 'delete-block': return deleteBlockModal(id);
      case 'restore-student': {
        const s = (state.db.archived || []).find((x) => x.id === id);
        return guarded(async () => {
          await api({ action: 'student_restore', id });
          toast(`${s ? s.fullName : 'Student'} restored.`);
          render();
        });
      }
      case 'view-archived': return archivedDetailsModal(id);
      case 'purge-student': return purgeStudentModal(id);
      case 'pick-user': {
        const s = state.db.students.find((x) => x.id === id);
        if (!s) return;
        Object.assign(state, { roleTargetId: s.id, userSearch: s.fullName, pendingRole: s.role, roleStatus: '' });
        $('#user-search').value = s.fullName;
        updateSearchResults(); syncRoleControls();
        return;
      }
      case 'save-role': {
        const target = roleTarget();
        if (!target || !state.pendingRole) return;
        return guarded(async () => {
          await api({ action: 'role_update', id: target.id, role: state.pendingRole });
          state.roleStatus = `${target.fullName} is now assigned as ${state.pendingRole}.`;
          render();
        });
      }
    }
  });

  document.addEventListener('input', (e) => {
    if (e.target.id === 'user-search') {
      state.userSearch = e.target.value;
      // Typing a new query invalidates the previously chosen user.
      if (roleTarget() && state.userSearch !== roleTarget().fullName) {
        Object.assign(state, { roleTargetId: null, pendingRole: '', roleStatus: '' });
        syncRoleControls();
      }
      updateSearchResults();
    }
  });

  document.addEventListener('change', (e) => {
    if (e.target.id === 'role-select') {
      state.pendingRole = e.target.value;
      state.roleStatus = '';
      syncRoleControls();
    }
  });

  $('#logoutBtn').addEventListener('click', confirmLogout);
  menuBtn.addEventListener('click', () => setMenu(!sidebar.classList.contains('sidebar-open')));
  scrim.addEventListener('click', () => setMenu(false));

  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (modalRoot.firstChild) closeModal(); else setMenu(false);
  });

  /* ---------- Boot ---------- */
  api()
    .then(render)
    .catch((err) => {
      content.innerHTML = `<section class="subject-detail"><div class="subject-object">
        <p class="eyebrow">Connection problem</p><h1>Can’t load data</h1>
        <p>${esc(err.message)} Make sure the site is running through PHP (see README).</p></div></section>`;
    });
})();
