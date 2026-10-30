<?php
session_start();
$classCode = $_SESSION['classCode'];
$blockName = $_SESSION['blockName'];

if(isset($_POST['cancel'])){
    header('Location: AdminClassCodes.php');
    unset($_SESSION['classCode']);
    unset($_SESSION['blockName']);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Student | Admin</title>
  <link rel="stylesheet" href="../css/classcode.css">

  <style>
    .sf-card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 16px;
      padding: 28px;
      max-width: 760px;
      margin: 0 auto 40px;
      box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .sf-class {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fff7ed;
      border: 1px solid #fed7aa;
      border-radius: 12px;
      padding: 12px 16px;
      margin-bottom: 22px;
      font-size: .95rem;
    }
    .sf-class strong { color: #ea580c; font-size: 1.05rem; }
    .sf-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 18px 20px;
    }
    .sf-field { display: flex; flex-direction: column; gap: 6px; }
    .sf-field.full { grid-column: 1 / -1; }
    .sf-field label { font-size: .85rem; font-weight: 600; color: #374151; }
    .sf-field .optional { font-weight: 400; color: #6b7280; }
    .sf-field input,
    .sf-field select {
      padding: 10px 12px;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      font: inherit;
      background: #fff;
    }
    .sf-field input:focus,
    .sf-field select:focus {
      outline: 2px solid #ff6a00;
      outline-offset: 1px;
      border-color: #ff6a00;
    }
    .sf-actions {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
      margin-top: 26px;
    }
    .sf-btn {
      display: inline-block;
      padding: 11px 20px;
      border-radius: 10px;
      font: inherit;
      font-weight: 600;
      text-decoration: none;
      text-align: center;
      cursor: pointer;
      border: 1px solid transparent;
    }
    .sf-btn-primary { background: #ff6a00; color: #fff; }
    .sf-btn-primary:hover { background: #e65f00; }
    .sf-btn-cancel { background: #fff; color: #374151; border-color: #d1d5db; }
    .sf-btn-cancel:hover { background: #f3f4f6; }
    @media (max-width: 600px) {
      .sf-grid { grid-template-columns: 1fr; }
      .sf-actions { flex-direction: column-reverse; }
    }
  </style>
</head>
<body>

  <header class="topbar">
    <div class="container topbar-inner">
      <div class="brand">
        <div class="mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/>
            <path d="M9 12l2 2 4-4"/>
          </svg>
        </div>
        <span>Admin Portal</span>
      </div>

      <a href="AdminLoginScene.php" class="btn-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Log out</span>
      </a>
    </div>
  </header>

  <main class="container">
    <div class="page-head">
      <h1>Create Student Account</h1>
      <p>Fill in the student's details. The account will be added to the class below.</p>
    </div>

    <section class="sf-card" aria-label="Create student account">

      <!-- Current class code -->
      <div class="sf-class">
        <span>Adding student to class code:</span>
        <strong><?= htmlspecialchars($blockName) ?> : <?= htmlspecialchars($classCode) ?></strong>
      </div>

      <form if="studentForm" method="POST" autocomplete="off">
        <div class="sf-grid">

          <div class="sf-field full">
            <label for="studentID">Student ID</label>
            <input type="text" id="studentID" name="studentID" maxlength="20" required>
          </div>

          <div class="sf-field">
            <label for="lastName">Last Name</label>
            <input type="text" id="lastName" name="lastName" maxlength="50" required>
          </div>

          <div class="sf-field">
            <label for="firstName">First Name</label>
            <input type="text" id="firstName" name="firstName" maxlength="50" required>
          </div>

          <div class="sf-field">
            <label for="middleName">Middle Name</label>
            <input type="text" id="middleName" name="middleName" maxlength="50" required>
          </div>

          <div class="sf-field">
            <label for="extName">Ext Name <span class="optional">(optional)</span></label>
            <input type="text" id="extName" name="extName" maxlength="10" placeholder="Jr., III, etc.">
          </div>

          <div class="sf-field full">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="100" required>
          </div>

          <div class="sf-field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required autocomplete="new-password">
          </div>

          <div class="sf-field">
            <label for="confirmPassword">Confirm Password</label>
            <input type="password" id="confirmPassword" name="confirmPassword" minlength="8" required autocomplete="new-password">
          </div>

          <div class="sf-field full">
            <label for="isRep">Add as Class Representative</label>
            <select id="isRep" name="isRep">
              <option value="No" selected>No</option>
              <option value="Yes">Yes</option>
            </select>
          </div>

        </div>

      </form>

      <div class="sf-actions">
          <form method="POST">
              <button type="submit" name="cancel" class="sf-btn sf-btn-cancel">Cancel</button>
          </form>

      <button type="submit" form="studentForm" name="createStudent" class="sf-btn sf-btn-primary">
           Create Student Account
       </button>
</div>

    </section>
  </main>

</body>
</html>