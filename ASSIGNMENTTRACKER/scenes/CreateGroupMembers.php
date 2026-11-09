<?php
session_start();
$name = $_SESSION['name'];
$classCode = $_SESSION['code'];

require_once __DIR__ . '/../database/admin.php';

$assignment = getAssignment($pdo, (int)$_SESSION['groupAsID'], $classCode);
$allStudents = getAllStudents($pdo, $classCode);

if(isset($_POST['cancel'])){
    header('Location: AdminCreateGroupMembers.php');
    exit;
}

if(isset($_POST['createGroup']) && 
   isset($_POST['leader']) && 
   isset($_POST['members']) &&
   isset($_POST['groupName'])){

   //make an assignment and group members creator
   createMembersAssignment($pdo, $classCode, $_POST['groupName'],
                           $_POST['leader'], $_POST['members'], $assignment);

    header('Location: AdminCreateGroupMembers.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Group | Admin</title>
  <link rel="stylesheet" href="../css/createassignments.css">
  <!-- Extra styles for this page. Keep it AFTER createassignments.css -->
  <link rel="stylesheet" href="../css/creategroup.css">
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
      <!-- Admin name -->
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
        <!-- Initials and admin name -->
        <span class="user-avatar" aria-hidden="true">AN</span>
        <span class="user-name"><?= $name ?></span>
      </div>
    </header>

    <div class="form-wrap">

      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="AdminAssignmentGroups.php">Groups</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">New group</span>
      </nav>

      <section class="form-card">

        <div class="form-head">
          <span class="form-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </span>
          <div>
            <h1>Create new group</h1>
            <p>Choose a leader and add members for this group.</p>
          </div>
        </div>

        <!-- Summary of where the group will be created (read only) -->
        <dl class="info-panel">
          <div class="info-item wide">
            <dt>Title</dt>
            <!-- Assignment title -->
            <dd><?= $assignment['as_title'] ?></dd>
          </div>
          <div class="info-item">
            <dt>Subject</dt>
            <!-- Subject -->
            <dd><?= $assignment['as_subject'] ?></dd>
          </div>
          <div class="info-item">
            <dt>Class</dt>
            <!-- Class code -->
            <dd><span class="info-pill"><?= $classCode ?></span></dd>
          </div>
        </dl>

        <form method="POST" autocomplete="off">

        <!-- Group Name -->
          <div class="field">
            <label for="groupName">Group Name <span class="req" aria-hidden="true">*</span></label>
            <input type="text" id="groupName" name="groupName" placeholder="e.g. Group 1" maxlength="50" required>
          </div>

          <!-- ======================================================
               ASSIGN A LEADER (search + dropdown, one choice)
               ====================================================== -->
          <div class="field">
            <label for="leaderSearch">Assign a leader <span class="req" aria-hidden="true">*</span></label>

            <div class="combo" id="leaderCombo">
              <svg class="combo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>

              <!-- What the user types (not submitted) -->
              <input type="text" id="leaderSearch" class="combo-input" placeholder="Search or select a leader"
                     role="combobox" aria-expanded="false" aria-controls="leaderList" aria-autocomplete="list"
                     autocomplete="off" required>

              <!-- The real value that gets submitted: $_POST['leader'] -->
              <input type="hidden" name="leader" id="leaderValue">

              <button type="button" class="combo-toggle" tabindex="-1" aria-label="Show all students">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </button>

              <ul class="combo-list" id="leaderList" role="listbox" hidden>
                <!-- FOREACH START: put your foreach line here, one <li> per student.
                     data-value = the student ID that is submitted (do not use quotes in it)
                     the text   = the student name
                     FOREACH END: close it after the </li> below.
                     The rows below are samples. Delete them when your foreach works. -->
                <?php foreach($allStudents as $student): ?>

                    <li class="combo-option" role="option" aria-selected="false" data-value="<?= (int) $student['studentID'] ?>"><?= $student['studentLastName'] . ", " . $student['studentFirstName'] . " (" . $student['studentID'] . ")" ?></li>

                <?php endforeach; ?>

                <li class="combo-empty" role="presentation" hidden>No matches found</li>
              </ul>
            </div>
          </div>

          <!-- ======================================================
               ADD MEMBERS (search + dropdown, as many as needed)
               Chosen students appear as chips inside the box.
               ====================================================== -->
          <div class="field">
            <label for="memberSearch">Add members <span class="req" aria-hidden="true">*</span></label>

            <div class="combo" id="memberCombo">
              <svg class="combo-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
              </svg>

              <div class="multi-box" id="memberBox">
                <!-- Chips are added here by the script, one per chosen student.
                     Each chip submits a hidden input named members[]  ->  $_POST['members'] is an array. -->

                <!-- What the user types (not submitted) -->
                <input type="text" id="memberSearch" class="multi-input" placeholder="Search students to add"
                       role="combobox" aria-expanded="false" aria-controls="memberList" aria-autocomplete="list"
                       autocomplete="off" required>
              </div>

              <button type="button" class="combo-toggle" tabindex="-1" aria-label="Show all students">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="6 9 12 15 18 9"/>
                </svg>
              </button>

              <ul class="combo-list" id="memberList" role="listbox" aria-multiselectable="true" hidden>
                <!-- FOREACH START: the same student list as the leader list above.
                     FOREACH END: close it after the </li> below.
                     Delete the sample rows when your foreach works. -->
                <?php foreach($allStudents as $student): ?>

                    <li class="combo-option" role="option" aria-selected="false" data-value="<?= (int) $student['studentID'] ?>"><?= $student['studentLastName'] . ", " . $student['studentFirstName'] . " (" . $student['studentID'] . ")" ?></li>

                <?php endforeach; ?>

                <li class="combo-empty" role="presentation" hidden>No matches found</li>
              </ul>
            </div>

            <p class="hint">Pick as many students as you need. The leader can&rsquo;t be added again here, and Backspace removes the last member.</p>
          </div>

          <div class="form-actions">
            <button name="cancel" form="cancelForm" type="submit" class="btn btn-secondary">Cancel</button>
            <button type="submit" name="createGroup" class="btn btn-primary">Create Group</button>
          </div>

        </form>

        <!-- Separate form just for Cancel, so the required fields never block it -->
        <form method="POST" id="cancelForm"></form>
      </section>

    </div>
  </main>

</div>

<script>
  /* ============ LEADER: search + dropdown (one choice) ============ */
  (function () {
    var combo   = document.getElementById('leaderCombo');
    var input   = document.getElementById('leaderSearch');
    var hidden  = document.getElementById('leaderValue');
    var list    = document.getElementById('leaderList');
    var toggle  = combo.querySelector('.combo-toggle');
    var empty   = list.querySelector('.combo-empty');
    var options = Array.prototype.slice.call(list.querySelectorAll('.combo-option'));
    var activeIndex = -1;

    options.forEach(function (o, i) {
      o.id = 'leaderOption' + i;
      o.dataset.label = o.textContent.trim();
    });

    // A student who is already a member can't also be the leader
    function isMember(value) {
      return !!document.querySelector('#memberBox .chip[data-value="' + value + '"]');
    }

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
      activeIndex = i;
    }

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
        if (pos > -1 && !isMember(o.dataset.value)) {
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

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (list.hidden) { filter(currentQuery()); openList(); }
        else setActive(Math.min(activeIndex + 1, vis.length - 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!list.hidden) setActive(Math.max(activeIndex - 1, 0));
      } else if (e.key === 'Enter') {
        if (!list.hidden && activeIndex > -1 && vis[activeIndex]) {
          e.preventDefault();          // pick the option instead of submitting the form
          choose(vis[activeIndex]);
        }
      } else if (e.key === 'Escape') {
        closeList();
      }
    });

    // Leaving the field: accept an exact match typed in full (e.g. "matt")
    input.addEventListener('blur', function () {
      var q = input.value.trim().toLowerCase();
      if (!hidden.value && q) {
        var exact = options.filter(function (o) {
          return o.dataset.label.toLowerCase() === q && !isMember(o.dataset.value);
        })[0];
        if (exact) choose(exact);
      }
      closeList();
    });
  })();


  /* ============ MEMBERS: search + dropdown (many choices, shown as chips) ============ */
  (function () {
    var combo   = document.getElementById('memberCombo');
    var box     = document.getElementById('memberBox');
    var input   = document.getElementById('memberSearch');
    var list    = document.getElementById('memberList');
    var toggle  = combo.querySelector('.combo-toggle');
    var empty   = list.querySelector('.combo-empty');
    var leader  = document.getElementById('leaderValue');
    var options = Array.prototype.slice.call(list.querySelectorAll('.combo-option'));
    var activeIndex = -1;

    var CLOSE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    options.forEach(function (o, i) {
      o.id = 'memberOption' + i;
      o.dataset.label = o.textContent.trim();
    });

    function isChosen(value) {
      return !!box.querySelector('.chip[data-value="' + value + '"]');
    }

    // The text field is only "required" while no member has been added yet
    function updateRequired() {
      input.required = box.querySelectorAll('.chip').length === 0;
    }

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
      activeIndex = i;
    }

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

    // Keep matches that are not already added and are not the leader, closest first
    function filter(q) {
      var matches = [];
      options.forEach(function (o) {
        var value = o.dataset.value;
        var pos = q ? o.dataset.label.toLowerCase().indexOf(q) : 0;
        var available = !isChosen(value) && value !== leader.value;
        if (pos > -1 && available) {
          matches.push({ el: o, pos: pos });
          o.hidden = false;
        } else {
          o.hidden = true;
        }
        paint(o, q);
      });

      matches.sort(function (a, b) {
        return (a.pos - b.pos) || a.el.dataset.label.localeCompare(b.el.dataset.label);
      });
      matches.forEach(function (m) { list.insertBefore(m.el, empty); });

      empty.textContent = q ? 'No matches found' : 'No more students to add';
      empty.hidden = matches.length > 0;
      setActive(q && matches.length ? 0 : -1);
    }

    function currentQuery() {
      return input.value.trim().toLowerCase();
    }

    // Add a chip for the chosen student
    function addMember(option) {
      var value = option.dataset.value;
      var label = option.dataset.label;
      if (isChosen(value)) return;

      var chip = document.createElement('span');
      chip.className = 'chip';
      chip.dataset.value = value;

      var text = document.createElement('span');
      text.className = 'chip-label';
      text.textContent = label;

      var remove = document.createElement('button');
      remove.type = 'button';
      remove.className = 'chip-remove';
      remove.setAttribute('aria-label', 'Remove ' + label);
      remove.innerHTML = CLOSE_ICON;

      // This is what gets submitted: members[]
      var hiddenInput = document.createElement('input');
      hiddenInput.type = 'hidden';
      hiddenInput.name = 'members[]';
      hiddenInput.value = value;

      chip.appendChild(text);
      chip.appendChild(remove);
      chip.appendChild(hiddenInput);
      box.insertBefore(chip, input);

      input.value = '';
      input.setCustomValidity('');
      updateRequired();
    }

    function choose(option) {
      addMember(option);
      filter('');       // list stays open so more students can be added
      openList();
      input.focus();
    }

    // Typing
    input.addEventListener('input', function () {
      input.setCustomValidity(input.value.trim() ? 'Pick a student from the list, or clear the text.' : '');
      filter(currentQuery());
      openList();
    });

    // Clicking anywhere in the box focuses the text field
    box.addEventListener('click', function (e) {
      var remove = e.target.closest('.chip-remove');
      if (remove) {
        remove.closest('.chip').remove();
        updateRequired();
        input.focus();
        filter(currentQuery());
        openList();
        return;
      }
      input.focus();
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

    // Clicking an option (mousedown is blocked so the text field keeps focus)
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });
    list.addEventListener('click', function (e) {
      var option = e.target.closest('.combo-option');
      if (option) choose(option);
    });

    // Keyboard
    input.addEventListener('keydown', function (e) {
      var vis = visibleOptions();

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (list.hidden) { filter(currentQuery()); openList(); }
        else setActive(Math.min(activeIndex + 1, vis.length - 1));
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!list.hidden) setActive(Math.max(activeIndex - 1, 0));
      } else if (e.key === 'Enter') {
        if (!list.hidden && activeIndex > -1 && vis[activeIndex]) {
          e.preventDefault();          // add the student instead of submitting the form
          choose(vis[activeIndex]);
        }
      } else if (e.key === 'Escape') {
        closeList();
      } else if (e.key === 'Backspace' && input.value === '') {
        var chips = box.querySelectorAll('.chip');
        if (chips.length) {
          chips[chips.length - 1].remove();
          updateRequired();
          filter('');
        }
      }
    });

    // Leaving the field: accept an exact match typed in full
    input.addEventListener('blur', function () {
      var q = currentQuery();
      if (q) {
        var exact = options.filter(function (o) {
          return o.dataset.label.toLowerCase() === q && !isChosen(o.dataset.value) && o.dataset.value !== leader.value;
        })[0];
        if (exact) addMember(exact);
      }
      closeList();
    });

    updateRequired();
  })();
</script>

</body>
</html>