<?php

session_start();
require_once __DIR__ . '/database.php';

$old = ['email' => ''];
$errors = $_SESSION['login_errors'] ?? [];
if (!empty($_SESSION['login_old'])) {
    $old = array_merge($old, $_SESSION['login_old']);
}
unset($_SESSION['login_errors'], $_SESSION['login_old']);

$justRegistered = isset($_GET['registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $old = ['email' => $email];

    if ($email === '') {
        $errors['email'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Please enter your password.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "SELECT id, name, password, role FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id']   = (int) $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];

            header('Location: index.php');
            exit();
        }

        $errors['general'] = 'Invalid email or password.';
    }

    if ($errors) {
        $_SESSION['login_errors'] = $errors;
        $_SESSION['login_old']    = $old;
        header('Location: login.php');
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">

  <main class="auth-wrap">
    <div class="auth-card">

      <!--  LEFT -->
      <div class="auth-panel">
        <div class="auth-panel-glow"></div>

        <a href="index.php" class="auth-logo">
          <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 33s-11-6.8-15-14C2.2 13.6 4.5 8 10 8c3.3 0 5.6 1.8 7 3.9C18.4 9.8 20.7 8 24 8c5.5 0 7.8 5.6 5 11-4 7.2-9 14-9 14Z"
                  stroke="#2dd4bf" stroke-width="2.4" stroke-linejoin="round"/>
            <path d="M6 20h6l2.5-6L18 26l3-11 2 5h9" stroke="#2dd4bf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Healix
        </a>

        <p class="auth-panel-script">Welcome Back</p>

        <h1 class="auth-panel-title">
          Your Health<br>
          <span class="teal">Matters to Us</span>
        </h1>

        <p class="auth-panel-subtitle">
          Log in to book appointments, manage your health, and more.
        </p>

        <ul class="auth-panel-features">
          <li>
            <span class="auth-panel-feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 9.5 17 19 7"/></svg>
            </span>
            Verified, licensed doctors
          </li>
          <li>
            <span class="auth-panel-feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 9.5 17 19 7"/></svg>
            </span>
            Book appointments in minutes
          </li>
          <li>
            <span class="auth-panel-feature-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5 9.5 17 19 7"/></svg>
            </span>
            Your data stays private &amp; secure
          </li>
        </ul>
      </div>

      <!--  RIGHT: FORM -->
      <div class="auth-form-panel">
        <p class="auth-eyebrow">
          <span class="dash-line"></span>
          <span class="auth-eyebrow-text">Welcome Back</span>
          <span class="dash-line"></span>
        </p>

        <h2 class="auth-title">Login to Your Account</h2>
        <p class="auth-subtitle">Access your appointments, profile and health records.</p>

        <?php if (!empty($errors['general'])): ?>
          <div class="auth-alert"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>

        <form class="auth-form" action="login.php" method="POST" novalidate>

          <div class="form-group">
            <label for="email">Email Address <span class="required">*</span></label>
            <input
              type="email" id="email" name="email" placeholder="Enter your email address"
              value="<?php echo htmlspecialchars($old['email']); ?>"
              class="<?php echo !empty($errors['email']) ? 'has-error' : ''; ?>">
            <?php if (!empty($errors['email'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
          </div>

          <div class="form-group">
            <label for="password">Password <span class="required">*</span></label>
            <div class="password-field">
              <input
                type="password" id="password" name="password" placeholder="Enter your password"
                class="<?php echo !empty($errors['password']) ? 'has-error' : ''; ?>">
              <button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z" stroke-linejoin="round"/><circle cx="12" cy="12" r="3"/></svg>
              </button>
            </div>
            <?php if (!empty($errors['password'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
          </div>

          

          <button type="submit" class="btn-login">
            Login
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>

          <p class="auth-switch-text" style="margin-top: 22px;">Don't have an account? <a href="register.php">Register</a></p>
        </form>
      </div>
    </div>

    <a href="index.php" class="auth-back-home">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 11.5 12 4l8 7.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M6 10v9h12v-9" stroke-linecap="round" stroke-linejoin="round"/><path d="M10 19v-5h4v5" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back To Home
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </a>
  </main>

  <script>
    const pwInput = document.getElementById('password');
    const pwToggle = document.getElementById('passwordToggle');
    pwToggle?.addEventListener('click', () => {
      const showing = pwInput.type === 'text';
      pwInput.type = showing ? 'password' : 'text';
      pwToggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    });
  </script>

</body>
</html>