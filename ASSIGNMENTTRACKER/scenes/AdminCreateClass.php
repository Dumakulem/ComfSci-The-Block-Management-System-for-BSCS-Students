<?php

if(isset($_POST['backToCodes'])){
    header('Location: AdminClassCodes.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Class | Admin</title>
  <link rel="stylesheet" href="../css/classcode.css">
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
 
      <a href="#" class="btn-logout">
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
    <div class="form-wrap">
 
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="#">Class codes</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">New class</span>
      </nav>
 
      <section class="form-card">
        <h1>Create new class</h1>
        <p class="subtitle">Enter the class code and block name for the new class.</p>
 
    <form method="post" autocomplete="off">
      <div class="field">
        <label for="class_code">Class code</label>
        <input type="text" id="class_code" name="class_code" placeholder="e.g. 10007 - CS107A" maxlength="30" required>
      </div>

      <div class="field">
        <label for="block_name">Block name</label>
        <input type="text" id="block_name" name="block_name" placeholder="e.g. BSCS 2B" maxlength="30" required>
      </div>

      <div class="actions">
        <!-- Create comes first in the HTML on purpose (see note below) -->
        <button type="submit" class="btn btn-primary">Create Class</button>
        <button type="submit" name="backToCodes" class="btn btn-secondary" formnovalidate>Cancel</button>
      </div>
    </form>
        </div>
    </section>
 
    </div>
  </main>
 
</body>
</html>