<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Log In</title>
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

    <h1>Admin log in</h1>
    <p class="subtitle">Enter your administrator credentials to continue.</p>

    <form method="post" novalidate>
      <div class="field">
        <label for="username">Admin username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" autocomplete="username" required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
      </div>

      <button type="submit" class="btn btn-primary">Log in</button>
      <a href="AdminCreateAccount.php" class="btn btn-secondary">Create Account</a>
    </form>

    <p class="footer">Authorized administrators only.</p>
  </main>
</body>
</html>
