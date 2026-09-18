<?php

session_start();
require_once __DIR__ . '/../database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['user_role'] !== 'doctor') {
    header('Location: ../index.php');
    exit();
}

$userId = (int) $_SESSION['user_id'];

$doctorStmt = mysqli_prepare($conn, "
    SELECT d.id, d.experience, d.bio, d.address, d.image, s.name AS specialty_name
    FROM doctors d
    JOIN specialties s ON s.id = d.specialty_id
    WHERE d.user_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($doctorStmt, 'i', $userId);
mysqli_stmt_execute($doctorStmt);
$doctorRow = mysqli_stmt_get_result($doctorStmt)->fetch_assoc();
mysqli_stmt_close($doctorStmt);

if (!$doctorRow) {
    die('Your doctor profile is not set up yet. Please contact the admin.');
}

$doctorId = (int) $doctorRow['id'];

$allowedImageExts = ['jpg', 'jpeg', 'png', 'webp'];
$maxImageBytes     = 2 * 1024 * 1024; 
$uploadDir         = __DIR__ . '/../images/doctors/';
$uploadDirWeb      = 'images/doctors/'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $errors = [];
        $old = [
            'name'       => trim($_POST['name'] ?? ''),
            'email'      => trim($_POST['email'] ?? ''),
            'phone'      => trim($_POST['phone'] ?? ''),
            'experience' => trim($_POST['experience'] ?? ''),
            'bio'        => trim($_POST['bio'] ?? ''),
            'address'    => trim($_POST['address'] ?? ''),
        ];

        // ---- Name ----
        if (!preg_match('/^[A-Za-z ]{3,60}$/', $old['name'])) {
            $errors['name'] = 'Full name must be letters only, between 3 and 60 characters.';
        }

        // ---- Email ----
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

        // ---- Phone ----
        if (!preg_match('/^01[0125][0-9]{8}$/', $old['phone'])) {
            $errors['phone'] = 'Phone number must be a valid 11-digit Egyptian mobile number.';
        }

        // ---- Experience ----
        if ($old['experience'] === '' || !ctype_digit($old['experience']) || (int) $old['experience'] > 60) {
            $errors['experience'] = 'Experience must be a whole number of years (0-60).';
        }

        // ---- Bio (optional) ----
        if (mb_strlen($old['bio']) > 1000) {
            $errors['bio'] = 'Bio must be under 1000 characters.';
        }

        // ---- Address ----
        if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
            $errors['address'] = 'Please enter your address.';
        }

        // ---- Photo  ----
        $newImagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $file = $_FILES['image'];

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $safeName = 'doctor_' . $doctorId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $safeName)) {
                $newImagePath = $uploadDirWeb . $safeName;
            } else {
                $errors['image'] = 'Could not save the uploaded photo. Please try again.';
            }
        }

        if (empty($errors)) {
            $imageToSave = $newImagePath ?? $doctorRow['image'];

            mysqli_begin_transaction($conn);
            $okUser = false;
            $okDoctor = false;

            $updateUser = mysqli_prepare($conn, "UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateUser, 'sssi', $old['name'], $old['email'], $old['phone'], $userId);
            $okUser = mysqli_stmt_execute($updateUser);
            mysqli_stmt_close($updateUser);

            $experience = (int) $old['experience'];
            $updateDoctor = mysqli_prepare($conn, "UPDATE doctors SET experience = ?, bio = ?, address = ?, image = ? WHERE id = ?");
            mysqli_stmt_bind_param($updateDoctor, 'isssi', $experience, $old['bio'], $old['address'], $imageToSave, $doctorId);
            $okDoctor = mysqli_stmt_execute($updateDoctor);
            mysqli_stmt_close($updateDoctor);

            if ($okUser && $okDoctor) {
                mysqli_commit($conn);
                if ($newImagePath && !empty($doctorRow['image']) && strpos($doctorRow['image'], $uploadDirWeb) === 0) {
                    $oldFile = __DIR__ . '/../' . $doctorRow['image'];
                    if (is_file($oldFile)) {
                        @unlink($oldFile);
                    }
                }

                $_SESSION['user_name'] = $old['name'];
                $_SESSION['profile_success'] = 'Your profile was updated successfully.';
            } else {
                mysqli_rollback($conn);
                if ($newImagePath && is_file($uploadDir . basename($newImagePath))) {
                    @unlink($uploadDir . basename($newImagePath));
                }
                $errors['general'] = 'Something went wrong while saving your profile. Please try again.';
            }
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

$userStmt = mysqli_prepare($conn, "SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($userStmt, 'i', $userId);
mysqli_stmt_execute($userStmt);
$userRow = mysqli_stmt_get_result($userStmt)->fetch_assoc();
mysqli_stmt_close($userStmt);

if (!empty($profileErrors) && $profileOld) {
    $display = $profileOld;
} else {
    
    $doctorStmt = mysqli_prepare($conn, "SELECT experience, bio, address, image FROM doctors WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($doctorStmt, 'i', $doctorId);
    mysqli_stmt_execute($doctorStmt);
    $freshDoctor = mysqli_stmt_get_result($doctorStmt)->fetch_assoc();
    mysqli_stmt_close($doctorStmt);

    $display = [
        'name'       => $userRow['name'],
        'email'      => $userRow['email'],
        'phone'      => $userRow['phone'],
        'experience' => $freshDoctor['experience'],
        'bio'        => $freshDoctor['bio'],
        'address'    => $freshDoctor['address'],
    ];
    $doctorRow['image'] = $freshDoctor['image'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — Healix Doctor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/doctor.css">
  <link rel="stylesheet" href="../css/all.min.css">
</head>
<body class="doc-page">

  
  <button type="button" class="doc-hamburger" data-sidebar-toggle aria-label="Toggle menu" aria-expanded="false">
    <i class="fa-solid fa-bars"></i>
    <i class="fa-solid fa-xmark"></i>
  </button>
  <div class="doc-sidebar-overlay" data-sidebar-overlay></div>

  <div class="doc-layout">

    <!--  SIDEBAR  -->
    <aside class="doc-sidebar" data-sidebar>
      <a href="../index.php" class="doc-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 33s-11-6.8-15-14C2.2 13.6 4.5 8 10 8c3.3 0 5.6 1.8 7 3.9C18.4 9.8 20.7 8 24 8c5.5 0 7.8 5.6 5 11-4 7.2-9 14-9 14Z"
                stroke="#2dd4bf" stroke-width="2.4" stroke-linejoin="round"/>
          <path d="M6 20h6l2.5-6L18 26l3-11 2 5h9" stroke="#2dd4bf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Healix
      </a>

      <nav class="doc-nav">
        <a href="dashboard.php"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="appointments.php"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
        <a href="schedule.php"><i class="fa-solid fa-clock"></i> Schedule</a>
        <a href="profile.php" class="active"><i class="fa-solid fa-user"></i> Profile</a>
      </nav>

      <a href="../logout.php" class="doc-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!--  MAIN -->
    <main class="doc-main">
      <header class="doc-topbar">
        <div>
          <h1><i class="fa-solid fa-id-badge" style="color:var(--teal-500); margin-right:8px;"></i>My Profile</h1>
          <p>Update your personal and professional information.</p>
        </div>
        <div class="doc-topbar-user">
          <span class="doc-welcome"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
          <button type="button" class="doc-btn-change-password" data-open-modal="passwordModal">
            <i class="fa-solid fa-lock"></i> Change Password
          </button>
        </div>
      </header>

      <?php if (!empty($profileErrors['general'])): ?>
        <div class="doc-alert doc-alert-error"><?php echo htmlspecialchars($profileErrors['general']); ?></div>
      <?php endif; ?>

      <div class="doc-profile-grid">

        <!--  PROFILE INFO  -->
        <div class="doc-card doc-form-card">
          <h2><i class="fa-solid fa-address-card"></i> Profile Information</h2>

          <form method="POST" action="profile.php" class="doc-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">

            <?php
                $placeholderAvatar = 'data:image/svg+xml;utf8,' . rawurlencode(
                    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#e7ecf1"/><circle cx="50" cy="38" r="18" fill="#9fb4c2"/><path d="M18 88c0-19 14-32 32-32s32 13 32 32" fill="#9fb4c2"/></svg>'
                );
                $avatarSrc = !empty($doctorRow['image']) ? '../' . htmlspecialchars($doctorRow['image']) : $placeholderAvatar;
            ?>
            <div class="doc-avatar-row">
              <img src="<?php echo $avatarSrc; ?>" alt="Profile photo" class="doc-avatar-preview" id="avatarPreview">
              <div class="doc-avatar-upload">
                <label for="image" class="doc-btn doc-btn-cancel-link doc-avatar-btn"><i class="fa-solid fa-camera"></i> Change Photo</label>
                <input type="file" id="image" name="image" accept=".jpg,.jpeg,.png,.webp" hidden>

                <?php if (!empty($profileErrors['image'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['image']); ?></span><?php endif; ?>
              </div>
            </div>

            <div class="doc-field-grid">
              <div class="doc-field">
                <label for="name"><i class="fa-solid fa-user"></i> Full Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($display['name']); ?>"
                       class="<?php echo !empty($profileErrors['name']) ? 'has-error' : ''; ?>">
                <?php if (!empty($profileErrors['name'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['name']); ?></span><?php endif; ?>
              </div>

              <div class="doc-field">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($display['email']); ?>"
                       class="<?php echo !empty($profileErrors['email']) ? 'has-error' : ''; ?>">
                <?php if (!empty($profileErrors['email'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['email']); ?></span><?php endif; ?>
              </div>

              <div class="doc-field">
                <label for="phone"><i class="fa-solid fa-phone"></i> Phone Number</label>
                <input type="tel" id="phone" name="phone" maxlength="11" value="<?php echo htmlspecialchars($display['phone']); ?>"
                       class="<?php echo !empty($profileErrors['phone']) ? 'has-error' : ''; ?>">
                <?php if (!empty($profileErrors['phone'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['phone']); ?></span><?php endif; ?>
              </div>

              <div class="doc-field">
                <label><i class="fa-solid fa-stethoscope"></i> Specialty</label>
                <input type="text" value="<?php echo htmlspecialchars($doctorRow['specialty_name']); ?>" disabled>
               
              </div>

              <div class="doc-field">
                <label for="experience"><i class="fa-solid fa-briefcase-medical"></i> Experience (years)</label>
                <input type="number" id="experience" name="experience" min="0" max="60"
                       value="<?php echo htmlspecialchars((string) $display['experience']); ?>"
                       class="<?php echo !empty($profileErrors['experience']) ? 'has-error' : ''; ?>">
                <?php if (!empty($profileErrors['experience'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['experience']); ?></span><?php endif; ?>
              </div>

              <div class="doc-field">
                <label for="address"><i class="fa-solid fa-location-dot"></i> Address</label>
                <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($display['address']); ?>"
                       class="<?php echo !empty($profileErrors['address']) ? 'has-error' : ''; ?>">
                <?php if (!empty($profileErrors['address'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['address']); ?></span><?php endif; ?>
              </div>

              <div class="doc-field doc-field-full">
                <label for="bio"><i class="fa-solid fa-feather-pointed"></i> Bio</label>
                <textarea id="bio" name="bio" rows="4" placeholder="A short professional bio patients will see on your profile."
                          class="<?php echo !empty($profileErrors['bio']) ? 'has-error' : ''; ?>"><?php echo htmlspecialchars($display['bio']); ?></textarea>
                <?php if (!empty($profileErrors['bio'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($profileErrors['bio']); ?></span><?php endif; ?>
              </div>
            </div>

            <div class="doc-form-actions">
              <button type="submit" class="doc-btn doc-btn-accept"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            </div>
          </form>
        </div>

        <!--  CHANGE PASSWORD  -->
        <div class="doc-modal-backdrop <?php echo !empty($passwordErrors) ? 'is-visible' : ''; ?>" id="passwordModal">
          <div class="doc-modal-box">
            <button type="button" class="doc-modal-close" data-close-modal aria-label="Close">
              <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="doc-card doc-form-card" id="password">
              <h2><i class="fa-solid fa-lock"></i> Change Password</h2>

              <?php if (!empty($passwordErrors['general'])): ?>
                <div class="doc-alert doc-alert-error"><?php echo htmlspecialchars($passwordErrors['general']); ?></div>
              <?php endif; ?>

              <form method="POST" action="profile.php#password" class="doc-form">
                <input type="hidden" name="action" value="change_password">

                <div class="doc-field">
                  <label for="current_password"><i class="fa-solid fa-key"></i> Current Password</label>
                  <input type="password" id="current_password" name="current_password"
                         class="<?php echo !empty($passwordErrors['current_password']) ? 'has-error' : ''; ?>">
                  <?php if (!empty($passwordErrors['current_password'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($passwordErrors['current_password']); ?></span><?php endif; ?>
                </div>

                <div class="doc-field">
                  <label for="new_password"><i class="fa-solid fa-shield-halved"></i> New Password</label>
                  <input type="password" id="new_password" name="new_password"
                         class="<?php echo !empty($passwordErrors['new_password']) ? 'has-error' : ''; ?>">
                  <?php if (!empty($passwordErrors['new_password'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($passwordErrors['new_password']); ?></span><?php endif; ?>
                </div>

                <div class="doc-field">
                  <label for="confirm_new_password"><i class="fa-solid fa-shield-halved"></i> Confirm New Password</label>
                  <input type="password" id="confirm_new_password" name="confirm_new_password"
                         class="<?php echo !empty($passwordErrors['confirm_new_password']) ? 'has-error' : ''; ?>">
                  <?php if (!empty($passwordErrors['confirm_new_password'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($passwordErrors['confirm_new_password']); ?></span><?php endif; ?>
                </div>

                <div class="doc-form-actions">
                  <button type="submit" class="doc-btn doc-btn-complete"><i class="fa-solid fa-key"></i> Update Password</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
  <script>
  
    const imageInput = document.getElementById('image');
    const avatarPreview = document.getElementById('avatarPreview');
    imageInput?.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      if (file) {
        avatarPreview.src = URL.createObjectURL(file);
      }
    });

    const pwModal = document.getElementById('passwordModal');
    const openPwBtn = document.querySelector('[data-open-modal="passwordModal"]');

    function openPwModal() {
      pwModal.classList.add('is-visible');
      document.body.classList.add('hx-no-scroll');
    }
    function closePwModal() {
      pwModal.classList.remove('is-visible');
      document.body.classList.remove('hx-no-scroll');
    }

    openPwBtn?.addEventListener('click', openPwModal);
    pwModal?.querySelector('[data-close-modal]')?.addEventListener('click', closePwModal);
    pwModal?.addEventListener('click', (e) => {
      if (e.target === pwModal) closePwModal();
    });
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && pwModal?.classList.contains('is-visible')) closePwModal();
    });
  </script>

</body>
</html>