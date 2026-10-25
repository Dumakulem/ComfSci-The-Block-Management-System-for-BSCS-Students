<?php
session_start();

if (!isset($_SESSION['name'])) {
    header('Location: AdminLoginScene.php');
    exit;
}

$name = $_SESSION['name'];

if(isset($_POST['back'])){
    session_start();
    session_destroy();
    header('Location: AdminLoginScene.php');
    exit;
}

if(isset($_POST['createClass'])){
    //session_start();
    //session_destroy();
    header('Location: AdminCreateClass.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Class Codes | Admin</title>
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
 
      <form method="POST">
        <button name="back" type="submit" class="btn-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        <span>Log out</span>
      </button>
      </form>

      
    </div>
  </header>
 
  <main class="container">
    <div class="page-head">
      <h1>Welcome, <?= $name ?></h1>
      <p>Select a block to manage it, or add a new class code.</p>
    </div>
 
    <section class="grid" aria-label="Class codes">
 
        <article class="card">
          <div class="card-body">
            <span class="code-badge">24567</span>
            <p class="block-label">Block</p>
            <h2 class="block-name">BSCS-2B</h2>
          </div>
 
          <div class="card-footer">
            <div class="person">
              <span class="avatar" aria-hidden="true">A</span>
              <span class="person-name"><?= $name ?></span>
            </div>
            <a href="#" class="btn-enter" aria-label="Enter">Enter</a>
          </div>
        </article>
 
      <!-- "+" card: always rendered after the last card -->
    <form method="POST" class="add-form">
      <button type="submit" name="createClass" class="card card-add" aria-label="Add a new class code">
        <span class="plus" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
           <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
        </span>
        <span>Create new</span>
      </button>
    </form>
 
    </section>
  </main>
 
</body>
</html>