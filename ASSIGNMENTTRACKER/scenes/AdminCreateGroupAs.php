<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];

if(isset($_POST['cancel'])){
    header("Location: AdminGroupAssignments.php");
    exit;
}

if(isset($_POST['createAssignment'])){
require_once __DIR__ . '/../controllers/AdminCon.php';

$title = $_POST['title'];
$subject = $_POST['subject'];
$files = $_FILES['files'] ?? null;
$des = $_POST['description'];
$dt = mergeDateAndTime($_POST['date'], $_POST['time']);
$forDatabase = $dt->format('Y-m-d H:i:s');   // "2026-11-20 23:59:00"

require_once __DIR__ . '/../database/admin.php';
createAssignment($pdo, $classCode, $name, $title, $subject, getAllStudents($pdo, $classCode), $files, $des, $forDatabase);

//$forDatabase = $dt->format('Y-m-d H:i:s');   // "2026-11-20 23:59:00"
//$forDisplay  = $dt->format('M j, Y, g:i A'); // "Nov 20, 2026, 11:59 PM"

    header("Location: AdminAssignments.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Assignment | Admin</title>
  <link rel="stylesheet" href="../css/createassignments.css">
</head>
<body>
<div class="shell">

  <!-- ================= Sidebar ================= -->
  <aside class="sidebar">
    <div class="profile">
      <div class="profile-avatar" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
          <circle cx="12" cy="7" r="4"/>
        </svg>
      </div>
      <!-- Admin name: replace "Admin Name" with  echo $adminName  -->
      <p class="profile-name"><?= $name ?></p>
      <p class="profile-role">Administrator</p>
    </div>

    <div class="nav-panel">
      <nav class="nav" aria-label="Main">
        <a class="nav-link" href="AdminClassCodes.php">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="3" width="7" height="7" rx="1"/>
            <rect x="14" y="14" width="7" height="7" rx="1"/>
            <rect x="3" y="14" width="7" height="7" rx="1"/>
          </svg>
          <span>Class Codes</span>
        </a>
        <a class="nav-link is-active" href="AdminAssignments.php" aria-current="page">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="8" y="2" width="8" height="4" rx="1"/>
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
            <path d="M9 12h6"/>
            <path d="M9 16h4"/>
          </svg>
          <span>Assignments</span>
        </a>
      </nav>

      <a class="nav-link logout" href="AdminLoginScene.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Logout</span>
      </a>
    </div>
  </aside>

  <!-- ================= Main ================= -->
  <main class="main">

    <header class="topbar">
      <div class="page-label">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="8" y="2" width="8" height="4" rx="1"/>
          <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
          <path d="M9 12h6"/>
          <path d="M9 16h4"/>
        </svg>
        <span>Assignments</span>
      </div>

      <div class="user-chip">
        <!-- Initials and name: replace with  echo $adminName  -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <div class="form-wrap">

      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="AdminAssignments.php">Assignments</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">New assignment</span>
      </nav>

      <section class="form-card">

        <div class="form-head">
          <span class="form-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
              <polyline points="14 2 14 8 20 8"/>
              <line x1="12" y1="18" x2="12" y2="12"/>
              <line x1="9" y1="15" x2="15" y2="15"/>
            </svg>
          </span>
          <div>
            <h1>Create new group assignment</h1>
            <p>Fill in the details below to post a group assignment.</p>
          </div>
        </div>

        <!-- enctype="multipart/form-data" is required for file uploads -->
        <form method="POST" autocomplete="off" enctype="multipart/form-data">

          <!-- Title -->
          <div class="field">
            <label for="title">Title <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="title" name="title" placeholder="e.g. Linked List Implementation" maxlength="100" required>
          </div>

          <!-- Subject -->
          <!-- Subject -->
          <div class="field">
               <label for="subject">Subject <span class="req" aria-hidden="true">*</span></label>
               <input type="text" id="subject" name="subject" placeholder="e.g. Data Structures and Algorithms" maxlength="100" required>
          </div>

          <!-- File (multiple files allowed, drag and drop works) -->
          <div class="field">
            <label for="files">File <span class="optional">(optional)</span></label>
            <input type="file" id="files" name="files">
            <p class="hint">Click to browse or drop files here. You can add more than one file.</p>
          </div>

          <!-- Description -->
          <div class="field">
            <label for="description">Description <span class="req" aria-hidden="true">*</span></label>
            <textarea id="description" name="description" placeholder="Write one short sentence about this assignment." maxlength="300" required></textarea>
            <p class="hint">Keep it to one sentence. Longer text is shortened with an ellipsis on the assignment card.</p>
          </div>

 <!-- Assign a leader (search + dropdown) -->
<div class="field">
  <label for="leaderSearch">Assign a leader <span class="req" aria-hidden="true">*</span></label>

  <div class="combo" id="leaderCombo">
    <svg class="combo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <circle cx="11" cy="11" r="8"/>
      <line x1="21" y1="21" x2="16.65" y2="16.65"/>
    </svg>

    <!-- What the user types (not submitted) -->
    <input type="text" id="leaderSearch" class="combo-input" placeholder="Search or select a leader"
           role="combobox" aria-expanded="false" aria-controls="leaderList" aria-autocomplete="list"
           autocomplete="off" required>

    <!-- The real value that gets submitted -->
    <input type="hidden" name="leader" id="leaderValue">

    <button type="button" class="combo-toggle" tabindex="-1" aria-label="Show all leaders">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"/>
      </svg>
    </button>

    <ul class="combo-list" id="leaderList" role="listbox" hidden>
      <!-- One <li> per leader. data-value is what $_POST['leader'] receives.
           If you print these with a foreach, run the name through htmlspecialchars(). -->
      <li class="combo-option" role="option" aria-selected="false" data-value="janine">Janine</li>
      <li class="combo-option" role="option" aria-selected="false" data-value="matt">Matt</li>
      <li class="combo-option" role="option" aria-selected="false" data-value="khent">Khent</li>

      <li class="combo-empty" role="presentation" hidden>No matches found</li>
    </ul>
  </div>
</div>

          <!-- Deadline (date picker + time) -->
          <div class="row-2">
            <div class="field">
              <label for="deadline_date">Deadline <span class="req" aria-hidden="true">*</span></label>
              <input type="date" id="deadline_date" name="date" required>
            </div>

            <div class="field">
              <label for="deadline_time">Time</label>
              <input type="time" id="deadline_time" name="time" value="23:59">
            </div>
          </div>

          <div class="form-actions">
            <button name="cancel" form="cancelForm" type="submit" class="btn btn-secondary">Cancel</button>
            <button type="submit" name="createAssignment" class="btn btn-primary">Create Assignment</button>
          </div>

        </form>

        <form method="POST" id="cancelForm"></form>
      </section>

    </div>
  </main>

</div>

<script>
  (function () {
    var combo   = document.getElementById('leaderCombo');
    var input   = document.getElementById('leaderSearch');
    var hidden  = document.getElementById('leaderValue');
    var list    = document.getElementById('leaderList');
    var toggle  = combo.querySelector('.combo-toggle');
    var empty   = list.querySelector('.combo-empty');
    var options = Array.prototype.slice.call(list.querySelectorAll('.combo-option'));

    options.forEach(function (o, i) {
      o.id = 'leaderOption' + i;
      o.dataset.label = o.textContent.trim();
    });

    // Visible options, in the order they appear on screen
    function visibleOptions() {
      return Array.prototype.filter.call(list.querySelectorAll('.combo-option'), function (o) {
        return !o.hidden;
      });
    }

    function setActive(i) {
      var vis = visibleOptions();
      options.forEach(function (o) { o.classList.remove('is-active'); });
      if (i > -1 && vis[i]) {
        vis[i].classList.add('is-active');
        input.setAttribute('aria-activedescendant', vis[i].id);
        vis[i].scrollIntoView({ block: 'nearest' });
      } else {
        input.removeAttribute('aria-activedescendant');
      }
      setActive.index = i;
    }
    setActive.index = -1;

    function openList() {
      list.hidden = false;
      combo.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
    }

    function closeList() {
      list.hidden = true;
      combo.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      setActive(-1);
    }

    // Shows the matching part of the name in orange
    function paint(option, q) {
      var label = option.dataset.label;
      var pos = q ? label.toLowerCase().indexOf(q) : -1;
      option.textContent = '';
      if (pos < 0) { option.textContent = label; return; }
      option.appendChild(document.createTextNode(label.slice(0, pos)));
      var mark = document.createElement('mark');
      mark.textContent = label.slice(pos, pos + q.length);
      option.appendChild(mark);
      option.appendChild(document.createTextNode(label.slice(pos + q.length)));
    }

    // Keep matches, put the closest ones first
    function filter(q) {
      var matches = [];
      options.forEach(function (o) {
        var pos = q ? o.dataset.label.toLowerCase().indexOf(q) : 0;
        if (pos > -1) {
          matches.push({ el: o, pos: pos });
          o.hidden = false;
        } else {
          o.hidden = true;
        }
        paint(o, q);
      });

      // Earlier match position first (names that start with the text), then A-Z
      matches.sort(function (a, b) {
        return (a.pos - b.pos) || a.el.dataset.label.localeCompare(b.el.dataset.label);
      });
      matches.forEach(function (m) { list.insertBefore(m.el, empty); });

      empty.hidden = matches.length > 0;
      setActive(q && matches.length ? 0 : -1);
    }

    function choose(option) {
      hidden.value = option.dataset.value;
      input.value = option.dataset.label;
      options.forEach(function (o) {
        o.setAttribute('aria-selected', o === option ? 'true' : 'false');
      });
      input.setCustomValidity('');
      closeList();
    }

    function currentQuery() {
      return hidden.value ? '' : input.value.trim().toLowerCase();
    }

    // Typing
    input.addEventListener('input', function () {
      hidden.value = '';
      options.forEach(function (o) { o.setAttribute('aria-selected', 'false'); });
      input.setCustomValidity(input.value.trim() ? 'Please choose a leader from the list.' : '');
      filter(input.value.trim().toLowerCase());
      openList();
    });

    input.addEventListener('click', function () {
      filter(currentQuery());
      openList();
    });

    // Arrow button
    toggle.addEventListener('mousedown', function (e) { e.preventDefault(); });
    toggle.addEventListener('click', function () {
      if (list.hidden) {
        input.focus();
        filter(currentQuery());
        openList();
      } else {
        closeList();
      }
    });

    // Clicking an option (mousedown is blocked so the input keeps focus)
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });
    list.addEventListener('click', function (e) {
      var option = e.target.closest('.combo-option');
      if (option) choose(option);
    });

    // Keyboard
    input.addEventListener('keydown', function (e) {
      var vis = visibleOptions();
      var i = setActive.index;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (list.hidden) { filter(currentQuery()); openList(); }
        else setActive(Math.min(i + 1, vis.length - 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!list.hidden) setActive(Math.max(i - 1, 0));
      } else if (e.key === 'Enter') {
        if (!list.hidden && i > -1 && vis[i]) {
          e.preventDefault();          // pick the option instead of submitting the form
          choose(vis[i]);
        }
      } else if (e.key === 'Escape') {
        closeList();
      }
    });

    // Leaving the field: accept an exact match typed in full (e.g. "matt")
    input.addEventListener('blur', function () {
      var q = input.value.trim().toLowerCase();
      if (!hidden.value && q) {
        var exact = options.filter(function (o) { return o.dataset.label.toLowerCase() === q; })[0];
        if (exact) choose(exact);
      }
      closeList();
    });
  })();
</script>

</body>
</html>