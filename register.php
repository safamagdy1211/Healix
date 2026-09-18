<?php

session_start();
require_once __DIR__ . '/database.php';

$uploadDir    = __DIR__ . '/images/doctors/';
$uploadDirWeb = 'images/doctors/'; 

function validateInputs(array $validates): array
{
    $errors = [];

    foreach ($validates as $validate_name => $validate_value) {

        if (empty($_POST[$validate_name])) {
            $errors[$validate_name] = "You must fill " . $validate_name;
            continue;
        }

        $options = $validate_value['my_options'] ?? 0;
        $value   = filter_input(INPUT_POST, $validate_name, $validate_value['filters'], $options);

        if ($value === false || $value === null) {
            $errors[$validate_name] = $validate_value['error'];
        }
    }

    return $errors;
}

$validates = [
    'name' => [
        'filters'    => FILTER_VALIDATE_REGEXP,
        'my_options' => ['options' => ['regexp' => '/^[A-Za-z ]{3,60}$/']],
        'error'      => 'Full name must be letters only between 3 and 60 characters.',
    ],

    'email' => [
        'filters' => FILTER_VALIDATE_EMAIL,
        'error'   => 'Invalid Email',
    ],

    'phone' => [
        'filters'    => FILTER_VALIDATE_REGEXP,
        'my_options' => ['options' => ['regexp' => '/^01[0125][0-9]{8}$/']],
        'error'      => 'Phone number must be a valid 11-digit .',
    ],

    'password' => [
        'filters'    => FILTER_VALIDATE_REGEXP,
        'my_options' => ['options' => ['regexp' => '/^[A-Za-z0-9]{8,20}$/']],
        'error'      => 'Password must be 8 to 20 characters (letters and numbers only).',
    ],

    'confirm_password' => [
        'filters'    => FILTER_VALIDATE_REGEXP,
        'my_options' => ['options' => ['regexp' => '/^[A-Za-z0-9]{8,20}$/']],
        'error'      => 'Confirm password must be 8 to 20 characters (letters and numbers only).',
    ],

    'role' => [
        'filters'    => FILTER_VALIDATE_REGEXP,
        'my_options' => ['options' => ['regexp' => '/^(patient|doctor)$/']],
        'error'      => 'Please choose Patient or Doctor.',
    ],
];


$specialtiesResult = mysqli_query($conn, "SELECT id, name FROM specialties ORDER BY name");
$specialties       = $specialtiesResult ? mysqli_fetch_all($specialtiesResult, MYSQLI_ASSOC) : [];

$old = [
    'name'          => '',
    'email'         => '',
    'phone'         => '',
    'role'          => 'patient',
    'specialty_id'  => '',
    'experience'    => '',
    'address'       => '',
    'bio'           => '',
];

$errors = $_SESSION['errors'] ?? [];
if (!empty($_SESSION['old'])) {
    $old = array_merge($old, $_SESSION['old']);
}
unset($_SESSION['errors'], $_SESSION['old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errors = validateInputs($validates);

    $old = [
        'name'         => trim($_POST['name'] ?? ''),
        'email'        => trim($_POST['email'] ?? ''),
        'phone'        => trim($_POST['phone'] ?? ''),
        'role'         => ($_POST['role'] ?? 'patient') === 'doctor' ? 'doctor' : 'patient',
        'specialty_id' => trim($_POST['specialty_id'] ?? ''),
        'experience'   => trim($_POST['experience'] ?? ''),
        'address'      => trim($_POST['address'] ?? ''),
        'bio'          => trim($_POST['bio'] ?? ''),
    ];

    $password        = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

 
    if (empty($errors['confirm_password']) && $confirmPassword !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors['email'])) {
        $checkStmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($checkStmt, 's', $old['email']);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errors['email'] = 'An account with this email already exists.';
        }
        mysqli_stmt_close($checkStmt);
    }

    $validSpecialtyIds = array_map('intval', array_column($specialties, 'id'));

    if ($old['role'] === 'doctor') {
        if ($old['specialty_id'] === '' || !in_array((int) $old['specialty_id'], $validSpecialtyIds, true)) {
            $errors['specialty_id'] = 'Please choose a valid specialty.';
        }

        if ($old['experience'] === '' || !ctype_digit($old['experience']) || (int) $old['experience'] > 60) {
            $errors['experience'] = 'Experience must be a whole number of years (0-60).';
        }

        if ($old['address'] === '' || mb_strlen($old['address']) > 255) {
            $errors['address'] = 'Please enter your clinic/work address.';
        }

        if (mb_strlen($old['bio']) > 1000) {
            $errors['bio'] = 'Bio must be under 1000 characters.';
        }
    }

    if ($errors) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old']    = $old;
        header('Location: register.php');
        exit();
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    mysqli_begin_transaction($conn);

    $insertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $insertStmt,
        'sssss',
        $old['name'],
        $old['email'],
        $hashedPassword,
        $old['phone'],
        $old['role']
    );
    $okUser = mysqli_stmt_execute($insertStmt);
    $newUserId = $okUser ? mysqli_insert_id($conn) : null;
    mysqli_stmt_close($insertStmt);

    $okDoctor      = true;
    $savedImagePath = null;

    if ($okUser && $old['role'] === 'doctor') {
      
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            $safeName = 'doctor_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $safeName)) {
                $savedImagePath = $uploadDirWeb . $safeName;
            }
        }

        $specialtyId = (int) $old['specialty_id'];
        $experience  = (int) $old['experience'];

        $insertDoctor = mysqli_prepare(
            $conn,
            "INSERT INTO doctors (user_id, specialty_id, experience, bio, address, image) VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $insertDoctor,
            'iiisss',
            $newUserId,
            $specialtyId,
            $experience,
            $old['bio'],
            $old['address'],
            $savedImagePath
        );
        $okDoctor = mysqli_stmt_execute($insertDoctor);
        mysqli_stmt_close($insertDoctor);
    }

    if ($okUser && $okDoctor) {
        mysqli_commit($conn);
        header('Location: login.php?registered=1');
        exit();
    }

    mysqli_rollback($conn);

    if ($savedImagePath) {
        $orphanFile = __DIR__ . '/' . $savedImagePath;
        if (is_file($orphanFile)) {
            @unlink($orphanFile);
        }
    }

    $_SESSION['errors'] = ['general' => 'Something went wrong while creating your account. Please try again.'];
    $_SESSION['old']    = $old;
    header('Location: register.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">

  <main class="auth-wrap">
    <div class="auth-card">

      <!-- LEFT-->
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

        <p class="auth-panel-script">Join Healix</p>

        <h1 class="auth-panel-title">
          Your Health<br>
          <span class="teal">Matters to Us</span>
        </h1>

        <p class="auth-panel-subtitle">Better care. Healthier you.</p>

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

      <!-- RIGHT -->
      <div class="auth-form-panel">
        <p class="auth-eyebrow">
          <span class="dash-line"></span>
          <span class="auth-eyebrow-text">Create Your Account</span>
          <span class="dash-line"></span>
        </p>

        <h2 class="auth-title">Register Now</h2>
        <p class="auth-subtitle">Fill in your details to get started.</p>

        <?php if (!empty($errors['general'])): ?>
          <div class="auth-alert"><?php echo htmlspecialchars($errors['general']); ?></div>
        <?php endif; ?>

        <form class="auth-form" action="register.php" method="POST" enctype="multipart/form-data" novalidate>

          <!-- ACCOUNT TYPE  -->
          <div class="form-group">
            <span class="form-section-label">Account Type</span>
            <label class="auth-main-label">I am a <span class="required">*</span></label>
            <div class="role-toggle">
              <input type="radio" id="role-patient" name="role" value="patient" <?php echo $old['role'] === 'patient' ? 'checked' : ''; ?>>
              <label for="role-patient">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg>
                Patient
              </label>

              <input type="radio" id="role-doctor" name="role" value="doctor" <?php echo $old['role'] === 'doctor' ? 'checked' : ''; ?>>
              <label for="role-doctor">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4v5a4 4 0 0 0 8 0V4"/><path d="M10 13v2.5a5 5 0 0 0 10 0V13"/><circle cx="20" cy="10.5" r="1.6"/></svg>
                Doctor
              </label>
            </div>
            <?php if (!empty($errors['role'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['role']); ?></span><?php endif; ?>
          </div>

          <!-- PERSONAL INFORMATION -->
          <span class="form-section-label form-section-label-spaced">Personal Information</span>

          <div class="form-group">
            <label for="name">Full Name <span class="required">*</span></label>
            <input
              type="text" id="name" name="name" placeholder="Enter your full name"
              value="<?php echo htmlspecialchars($old['name']); ?>"
              class="<?php echo !empty($errors['name']) ? 'has-error' : ''; ?>">
            <?php if (!empty($errors['name'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['name']); ?></span><?php endif; ?>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="email">Email Address <span class="required">*</span></label>
              <input
                type="email" id="email" name="email" placeholder="you@example.com"
                value="<?php echo htmlspecialchars($old['email']); ?>"
                class="<?php echo !empty($errors['email']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['email'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['email']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
              <label for="phone">Phone Number <span class="required">*</span></label>
              <input
                type="tel" id="phone" name="phone" placeholder="01xxxxxxxxx" maxlength="11"
                value="<?php echo htmlspecialchars($old['phone']); ?>"
                class="<?php echo !empty($errors['phone']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['phone'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['phone']); ?></span><?php endif; ?>
            </div>
          </div>

          <!--  SECURITY  -->
          <span class="form-section-label form-section-label-spaced">Security</span>

          <div class="form-row">
            <div class="form-group">
              <label for="password">Password <span class="required">*</span></label>
              <input
                type="password" id="password" name="password" placeholder="Create a password"
                class="<?php echo !empty($errors['password']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['password'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['password']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm Password <span class="required">*</span></label>
              <input
                type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password"
                class="<?php echo !empty($errors['confirm_password']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['confirm_password'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['confirm_password']); ?></span><?php endif; ?>
            </div>
          </div>

          <!-- DOCTOR-ONLY FIELDS  -->
          <div id="doctorOnlyFields" style="<?php echo $old['role'] === 'doctor' ? '' : 'display:none;'; ?>">
            <span class="form-section-label form-section-label-spaced">Professional Details</span>

            <div class="form-group">
              <label for="specialty_id">Specialty <span class="required">*</span></label>
              <select id="specialty_id" name="specialty_id"
                      class="<?php echo !empty($errors['specialty_id']) ? 'has-error' : ''; ?>">
                <option value="">Select your specialty</option>
                <?php foreach ($specialties as $specialty): ?>
                  <option value="<?php echo (int) $specialty['id']; ?>" <?php echo (string) $old['specialty_id'] === (string) $specialty['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($specialty['name']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (empty($specialties)): ?>
                <span class="field-error">No specialties are set up yet — please contact the site owner.</span>
              <?php endif; ?>
              <?php if (!empty($errors['specialty_id'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['specialty_id']); ?></span><?php endif; ?>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="experience">Experience (years) <span class="required">*</span></label>
                <input
                  type="number" id="experience" name="experience" min="0" max="60" placeholder="e.g. 5"
                  value="<?php echo htmlspecialchars($old['experience']); ?>"
                  class="<?php echo !empty($errors['experience']) ? 'has-error' : ''; ?>">
                <?php if (!empty($errors['experience'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['experience']); ?></span><?php endif; ?>
              </div>

              <div class="form-group">
                <label for="address">Clinic / Work Address <span class="required">*</span></label>
                <input
                  type="text" id="address" name="address" placeholder="e.g. Giza"
                  value="<?php echo htmlspecialchars($old['address']); ?>"
                  class="<?php echo !empty($errors['address']) ? 'has-error' : ''; ?>">
                <?php if (!empty($errors['address'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['address']); ?></span><?php endif; ?>
              </div>
            </div>

            <div class="form-group">
              <label for="bio">Bio <span class="optional-tag">Optional</span></label>
              <textarea id="bio" name="bio" rows="3" placeholder="A short professional bio patients will see on your profile."
                        class="<?php echo !empty($errors['bio']) ? 'has-error' : ''; ?>"><?php echo htmlspecialchars($old['bio']); ?></textarea>
              <?php if (!empty($errors['bio'])): ?><span class="field-error"><?php echo htmlspecialchars($errors['bio']); ?></span><?php endif; ?>
            </div>

            <div class="form-group">
              <label for="image">Profile Photo <span class="optional-tag">Optional</span></label>
              <label for="image" class="file-upload">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M12 4 7 9M12 4l5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/></svg>
                <span id="fileUploadLabel">Choose a photo</span>
              </label>
              <input type="file" id="image" name="image" class="visually-hidden-file">
            </div>
          </div>

          <button type="submit" class="btn-register">
            Create Account
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>

          <p class="auth-footer-text">Already have an account? <a href="login.php">Login</a></p>
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
    const roleRadios       = document.querySelectorAll('input[name="role"]');
    const doctorOnlyFields = document.getElementById('doctorOnlyFields');
    const imageInput       = document.getElementById('image');
    const fileUploadLabel  = document.getElementById('fileUploadLabel');

    function toggleDoctorFields() {
      const isDoctor = document.getElementById('role-doctor').checked;
      doctorOnlyFields.style.display = isDoctor ? '' : 'none';
    }

    roleRadios.forEach((radio) => radio.addEventListener('change', toggleDoctorFields));
    toggleDoctorFields();

    imageInput?.addEventListener('change', () => {
      const file = imageInput.files && imageInput.files[0];
      fileUploadLabel.textContent = file ? file.name : 'Choose a photo';
    });
  </script>

</body>
</html>