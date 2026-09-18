<?php
session_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['user_role'] !== 'patient') {
    header('Location: ../index.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $errors = [];
        $old = [
            'name'  => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
        ];

        // ---- Name ----
        if (!preg_match('/^[A-Za-z ]{3,60}$/', $old['name'])) {
            $errors['name'] = 'Full name must be letters only, between 3 and 60 characters.';
        }

        if ($old['email'] === '' || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        } else {
            $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($checkStmt, 'si', $old['email'], $userId);
            mysqli_stmt_execute($checkStmt);
            mysqli_stmt_store_result($checkStmt);
            if (mysqli_stmt_num_rows($checkStmt) > 0) {
                $errors['email'] = 'An account with this email already exists.';
            }
            mysqli_stmt_close($checkStmt);
        }

        if (!preg_match('/^01[0125][0-9]{8}$/', $old['phone'])) {
            $errors['phone'] = 'Phone number must be a valid 11-digit Egyptian mobile number.';
        }

        if (empty($errors)) {
            $updateUser = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateUser, 'sssi', $old['name'], $old['email'], $old['phone'], $userId);

            if (mysqli_stmt_execute($updateUser)) {
                $_SESSION['user_name'] = $old['name'];
                $_SESSION['profile_success'] = 'Your profile was updated successfully.';
            } else {
                $errors['general'] = 'Something went wrong while saving your profile. Please try again.';
            }
            mysqli_stmt_close($updateUser);
        }

        if (!empty($errors)) {
            $_SESSION['profile_errors'] = $errors;
            $_SESSION['profile_old']    = $old;
        }

        header('Location: profile.php');
        exit();
    }

    if ($action === 'change_password') {
        $pwErrors = [];

        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_new_password'] ?? '';

        $userStmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($userStmt, 'i', $userId);
        mysqli_stmt_execute($userStmt);
        $userRow = mysqli_stmt_get_result($userStmt)->fetch_assoc();
        mysqli_stmt_close($userStmt);

        if ($currentPassword === '' || !$userRow || !password_verify($currentPassword, $userRow['password'])) {
            $pwErrors['current_password'] = 'Your current password is incorrect.';
        }

        if (!preg_match('/^[A-Za-z0-9]{8,20}$/', $newPassword)) {
            $pwErrors['new_password'] = 'New password must be 8 to 20 characters (letters and numbers only).';
        }

        if (empty($pwErrors['new_password']) && $confirmPassword !== $newPassword) {
            $pwErrors['confirm_new_password'] = 'Passwords do not match.';
        }

        if (empty($pwErrors)) {
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
            $updatePw = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($updatePw, 'si', $hashed, $userId);
            if (mysqli_stmt_execute($updatePw)) {
                $_SESSION['password_success'] = 'Your password was changed successfully.';
            } else {
                $pwErrors['general'] = 'Something went wrong while changing your password. Please try again.';
            }
            mysqli_stmt_close($updatePw);
        }

        if (!empty($pwErrors)) {
            $_SESSION['password_errors'] = $pwErrors;
        }

        header('Location: profile.php#password');
        exit();
    }
}

$profileSuccess = $_SESSION['profile_success'] ?? null;
$profileErrors  = $_SESSION['profile_errors'] ?? [];
$profileOld     = $_SESSION['profile_old'] ?? null;
unset($_SESSION['profile_success'], $_SESSION['profile_errors'], $_SESSION['profile_old']);

$passwordSuccess = $_SESSION['password_success'] ?? null;
$passwordErrors  = $_SESSION['password_errors'] ?? [];
unset($_SESSION['password_success'], $_SESSION['password_errors']);

if (!empty($profileErrors) && $profileOld) {
    $display = $profileOld;
} else {
    $userStmt = mysqli_prepare($conn, "SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($userStmt, 'i', $userId);
    mysqli_stmt_execute($userStmt);
    $userRow = mysqli_stmt_get_result($userStmt)->fetch_assoc();
    mysqli_stmt_close($userStmt);

    $display = [
        'name'  => $userRow['name'],
        'email' => $userRow['email'],
        'phone' => $userRow['phone'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/patient.css">
  <link rel="stylesheet" href="../css/all.min.css">
</head>
<body class="pat-page">

  <!-- Mobile menu icon -->
  <button type="button" class="pat-hamburger" data-sidebar-toggle aria-label="Toggle menu" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
    <i class="fa-solid fa-xmark"></i>
  </button>
  <div class="pat-sidebar-overlay" data-sidebar-overlay></div>

  <div class="pat-layout">

    <!-- SIDEBAR  -->
    <aside class="pat-sidebar" data-sidebar>
      <a href="../index.php" class="pat-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 33s-11-6.8-15-14C2.2 13.6 4.5 8 10 8c3.3 0 5.6 1.8 7 3.9C18.4 9.8 20.7 8 24 8c5.5 0 7.8 5.6 5 11-4 7.2-9 14-9 14Z"
                stroke="#2dd4bf" stroke-width="2.4" stroke-linejoin="round"/>
          <path d="M6 20h6l2.5-6L18 26l3-11 2 5h9" stroke="#2dd4bf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Healix
      </a>

      <nav class="pat-nav">
        <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="appointments.php"><i class="fa-solid fa-calendar-check"></i> My Appointments</a>
        <a href="profile.php" class="active"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="../doctors.php"><i class="fa-solid fa-magnifying-glass"></i> Find a Doctor</a>
      </nav>

      <a href="../logout.php" class="pat-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!-- MAIN -->
    <main class="pat-main">
      <header class="pat-topbar">
        <div>
          <h1><i class="fa-solid fa-id-badge" style="color:var(--teal-500); margin-right:8px;"></i>My Profile</h1>
          <p>Update your personal information and password.</p>
        </div>
      </header>

      <?php if (!empty($profileErrors['general'])): ?>
        <div class="pat-alert pat-alert-error"><?php echo htmlspecialchars($profileErrors['general']); ?></div>
      <?php endif; ?>

      <div class="pat-profile-grid">

        <!--  PROFILE INFO  -->
        <div class="pat-card pat-form-card">
          <h2><i class="fa-solid fa-address-card"></i> Profile Information</h2>

          <form method="POST" action="profile.php" class="pat-form">
            <input type="hidden" name="action" value="update_profile">

            <div class="pat-field">
              <label for="name"><i class="fa-solid fa-user"></i> Full Name</label>
              <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($display['name']); ?>"
                     class="<?php echo !empty($profileErrors['name']) ? 'has-error' : ''; ?>">
              <?php if (!empty($profileErrors['name'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($profileErrors['name']); ?></span><?php endif; ?>
            </div>

            <div class="pat-field">
              <label for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
              <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($display['email']); ?>"
                     class="<?php echo !empty($profileErrors['email']) ? 'has-error' : ''; ?>">
              <?php if (!empty($profileErrors['email'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($profileErrors['email']); ?></span><?php endif; ?>
            </div>

            <div class="pat-field">
              <label for="phone"><i class="fa-solid fa-phone"></i> Phone Number</label>
              <input type="tel" id="phone" name="phone" maxlength="11" value="<?php echo htmlspecialchars($display['phone']); ?>"
                     class="<?php echo !empty($profileErrors['phone']) ? 'has-error' : ''; ?>">
              <?php if (!empty($profileErrors['phone'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($profileErrors['phone']); ?></span><?php endif; ?>
            </div>

            <div class="pat-form-actions">
              <button type="submit" class="pat-btn pat-btn-primary" style="margin-top:0;"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
          </form>
        </div>

        <!--  CHANGE PASSWORD  -->
        <div class="pat-card pat-form-card" id="password">
          <h2><i class="fa-solid fa-lock"></i> Change Password</h2>

          <?php if (!empty($passwordErrors['general'])): ?>
            <div class="pat-alert pat-alert-error"><?php echo htmlspecialchars($passwordErrors['general']); ?></div>
          <?php endif; ?>

          <form method="POST" action="profile.php#password" class="pat-form">
            <input type="hidden" name="action" value="change_password">

            <div class="pat-field">
              <label for="current_password"><i class="fa-solid fa-key"></i> Current Password</label>
              <input type="password" id="current_password" name="current_password"
                     class="<?php echo !empty($passwordErrors['current_password']) ? 'has-error' : ''; ?>">
              <?php if (!empty($passwordErrors['current_password'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($passwordErrors['current_password']); ?></span><?php endif; ?>
            </div>

            <div class="pat-field">
              <label for="new_password"><i class="fa-solid fa-shield-halved"></i> New Password</label>
              <input type="password" id="new_password" name="new_password"
                     class="<?php echo !empty($passwordErrors['new_password']) ? 'has-error' : ''; ?>">
              <?php if (!empty($passwordErrors['new_password'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($passwordErrors['new_password']); ?></span><?php endif; ?>
            </div>

            <div class="pat-field">
              <label for="confirm_new_password"><i class="fa-solid fa-shield-halved"></i> Confirm New Password</label>
              <input type="password" id="confirm_new_password" name="confirm_new_password"
                     class="<?php echo !empty($passwordErrors['confirm_new_password']) ? 'has-error' : ''; ?>">
              <?php if (!empty($passwordErrors['confirm_new_password'])): ?><span class="pat-field-error"><?php echo htmlspecialchars($passwordErrors['confirm_new_password']); ?></span><?php endif; ?>
            </div>

            <div class="pat-form-actions">
              <button type="submit" class="pat-btn pat-btn-primary" style="margin-top:0;"><i class="fa-solid fa-key"></i> Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
</body>
</html>
