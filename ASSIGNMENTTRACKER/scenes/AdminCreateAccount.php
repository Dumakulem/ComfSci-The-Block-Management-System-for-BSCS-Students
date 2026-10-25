<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Create Admin Account</title>
  <link rel="stylesheet" href="../css/main.css">
</head>
<body>
  <main class="card">
    <div class="mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3l8 3v6c0 4.5-3.2 8.2-8 9-4.8-.8-8-4.5-8-9V6l8-3z"/>
        <path d="M9 12l2 2 4-4"/>
      </svg>
    </div>

    <h1>Create admin account</h1>
    <p class="subtitle">Set up your administrator credentials.</p>

    <form action="../scenes/AdminClassCodes.php" method="POST">
      <div class="field">
        <label for="username">Admin username</label>
        <input type="text" id="username" name="adminUsername" placeholder="Choose a username" autocomplete="username" required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="adminPassword" placeholder="Create a password" autocomplete="new-password" required>
      </div>

      <div class="field">
        <label for="confirm_password">Re-enter password</label>
        <input type="password" id="confirm_password" name="adminConfirmPassword" placeholder="Re-enter your password" autocomplete="new-password" required>
      </div>

      <button type="submit" class="btn btn-primary">Create Account</button>
    </form>

    <p class="switch">Already have an account? <a href="AdminLoginScene.php">Log in</a></p>
  </main>
</body>
</html>
