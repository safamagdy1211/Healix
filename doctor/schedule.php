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

$doctorStmt = mysqli_prepare($conn, "SELECT id FROM doctors WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($doctorStmt, 'i', $userId);
mysqli_stmt_execute($doctorStmt);
$doctorRow = mysqli_stmt_get_result($doctorStmt)->fetch_assoc();
mysqli_stmt_close($doctorStmt);

if (!$doctorRow) {
    die('Your doctor profile is not set up yet. Please contact the admin.');
}

$doctorId = (int) $doctorRow['id'];

$validDays = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        $scheduleId = isset($_POST['schedule_id']) ? (int) $_POST['schedule_id'] : 0;

        $ownStmt = mysqli_prepare($conn, "SELECT id FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
        mysqli_stmt_bind_param($ownStmt, 'ii', $scheduleId, $doctorId);
        mysqli_stmt_execute($ownStmt);
        mysqli_stmt_store_result($ownStmt);
        $owns = mysqli_stmt_num_rows($ownStmt) > 0;
        mysqli_stmt_close($ownStmt);

        if ($owns) {
            $del = mysqli_prepare($conn, "DELETE FROM doctor_schedules WHERE id = ?");
            mysqli_stmt_bind_param($del, 'i', $scheduleId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            $_SESSION['ds_success'] = 'Time slot removed.';
        } else {
            $_SESSION['ds_error'] = 'Time slot not found.';
        }

        header('Location: schedule.php');
        exit();
    }

    if ($action === 'add' || $action === 'edit') {
        $editId = $action === 'edit' ? (int) ($_POST['schedule_id'] ?? 0) : 0;

        $errors = [];
        $old = [
            'day'        => trim($_POST['day'] ?? ''),
            'start_time' => trim($_POST['start_time'] ?? ''),
            'end_time'   => trim($_POST['end_time'] ?? ''),
        ];

        if ($action === 'edit') {
            $ownStmt = mysqli_prepare($conn, "SELECT id FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
            mysqli_stmt_bind_param($ownStmt, 'ii', $editId, $doctorId);
            mysqli_stmt_execute($ownStmt);
            mysqli_stmt_store_result($ownStmt);
            if (mysqli_stmt_num_rows($ownStmt) === 0) {
                mysqli_stmt_close($ownStmt);
                header('Location: schedule.php');
                exit();
            }
            mysqli_stmt_close($ownStmt);
        }

        $day   = $old['day'];
        $start = $old['start_time'];
        $end   = $old['end_time'];

        if ($day === '' || !in_array($day, $validDays, true)) {
            $errors['day'] = 'Please choose a valid day.';
        }

        $startObj = $start !== '' ? DateTime::createFromFormat('H:i', $start) : false;
        $endObj   = $end !== '' ? DateTime::createFromFormat('H:i', $end) : false;

        if ($start === '') {
            $errors['start_time'] = 'Please enter a start time.';
        } elseif (!$startObj) {
            $errors['start_time'] = 'Please enter a valid start time.';
        }

        if ($end === '') {
            $errors['end_time'] = 'Please enter an end time.';
        } elseif (!$endObj) {
            $errors['end_time'] = 'Please enter a valid end time.';
        }

        if (empty($errors) && $end <= $start) {
            $errors['end_time'] = 'End time must be later than start time.';
        }

        if (empty($errors)) {
            $overlapStmt = mysqli_prepare($conn, "
                SELECT id FROM doctor_schedules
                WHERE doctor_id = ? AND day = ? AND id != ?
                AND start_time < ? AND end_time > ?
            ");
            mysqli_stmt_bind_param($overlapStmt, 'isiss', $doctorId, $day, $editId, $end, $start);
            mysqli_stmt_execute($overlapStmt);
            mysqli_stmt_store_result($overlapStmt);
            if (mysqli_stmt_num_rows($overlapStmt) > 0) {
                $errors['start_time'] = 'This overlaps with another time slot you already have on ' . $day . '.';
            }
            mysqli_stmt_close($overlapStmt);
        }

        if (empty($errors)) {
            if ($action === 'add') {
                $insert = mysqli_prepare($conn, "
                    INSERT INTO doctor_schedules (doctor_id, day, start_time, end_time)
                    VALUES (?, ?, ?, ?)
                ");
                mysqli_stmt_bind_param($insert, 'isss', $doctorId, $day, $start, $end);
                if (mysqli_stmt_execute($insert)) {
                    $_SESSION['ds_success'] = 'New time slot added.';
                } else {
                    $errors['general'] = 'This exact time slot already exists.';
                }
                mysqli_stmt_close($insert);
            } else {
                $update = mysqli_prepare($conn, "
                    UPDATE doctor_schedules SET day = ?, start_time = ?, end_time = ?
                    WHERE id = ? AND doctor_id = ?
                ");
                mysqli_stmt_bind_param($update, 'sssii', $day, $start, $end, $editId, $doctorId);
                if (mysqli_stmt_execute($update)) {
                    $_SESSION['ds_success'] = 'Time slot updated.';
                } else {
                    $errors['general'] = 'This exact time slot already exists.';
                }
                mysqli_stmt_close($update);
            }
        }

        if (!empty($errors)) {
            $_SESSION['ds_errors'] = $errors;
            $_SESSION['ds_old'] = $old;
            header('Location: schedule.php' . ($editId ? '?edit=' . $editId : '') . '#form');
            exit();
        }

        header('Location: schedule.php');
        exit();
    }
}

$success = $_SESSION['ds_success'] ?? null;
$errors  = $_SESSION['ds_errors'] ?? [];
$old     = $_SESSION['ds_old'] ?? ['day' => '', 'start_time' => '', 'end_time' => ''];
unset($_SESSION['ds_success'], $_SESSION['ds_errors'], $_SESSION['ds_old']);

$editingId  = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingRow = null;

if ($editingId > 0) {
    $editStmt = mysqli_prepare($conn, "SELECT id, day, start_time, end_time FROM doctor_schedules WHERE id = ? AND doctor_id = ?");
    mysqli_stmt_bind_param($editStmt, 'ii', $editingId, $doctorId);
    mysqli_stmt_execute($editStmt);
    $editingRow = mysqli_stmt_get_result($editStmt)->fetch_assoc();
    mysqli_stmt_close($editStmt);

    if ($editingRow && empty($old['day'])) {
        $old = [
            'day'        => $editingRow['day'],
            'start_time' => substr($editingRow['start_time'], 0, 5),
            'end_time'   => substr($editingRow['end_time'], 0, 5),
        ];
    }
    if (!$editingRow) {
        $editingId = 0; 
    }
}

$dayOrderCase = "FIELD(day, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday')";
$scheduleStmt = mysqli_prepare($conn, "
    SELECT id, day, start_time, end_time
    FROM doctor_schedules
    WHERE doctor_id = ?
    ORDER BY $dayOrderCase, start_time ASC
");
mysqli_stmt_bind_param($scheduleStmt, 'i', $doctorId);
mysqli_stmt_execute($scheduleStmt);
$allSlots = mysqli_stmt_get_result($scheduleStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($scheduleStmt);

$slotsByDay = array_fill_keys($validDays, []);
foreach ($allSlots as $slot) {
    $slotsByDay[$slot['day']][] = $slot;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Schedule — Healix Doctor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/doctor.css">
  <link rel="stylesheet" href="../css/all.min.css">
</head>
<body class="doc-page">

  <!-- Mobile menu icon -->
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
        <a href="schedule.php" class="active"><i class="fa-solid fa-clock"></i> Schedule</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
      </nav>

      <a href="../logout.php" class="doc-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!-- MAIN -->
    <main class="doc-main">
      <header class="doc-topbar">
        <div>
          <h1><i class="fa-solid fa-calendar-week" style="color:var(--violet-500); margin-right:8px;"></i>My Schedule</h1>
          <p>Set the days and hours patients can book you for.</p>
        </div>
        <div class="doc-topbar-user">
          <span class="doc-welcome"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
      </header>

      <?php if (!empty($errors['general'])): ?>
        <div class="doc-alert doc-alert-error"><?php echo htmlspecialchars($errors['general']); ?></div>
      <?php endif; ?>

      <div class="doc-schedule-grid" id="form">

        <!-- ADD / EDIT FORM  -->
        <div class="doc-card doc-form-card">
          <h2><i class="fa-solid <?php echo $editingRow ? 'fa-pen' : 'fa-circle-plus'; ?>"></i> <?php echo $editingRow ? 'Edit Time Slot' : 'Add a Time Slot'; ?></h2>

          <form method="POST" action="schedule.php" class="doc-form">
            <input type="hidden" name="action" value="<?php echo $editingRow ? 'edit' : 'add'; ?>">
            <?php if ($editingRow): ?>
              <input type="hidden" name="schedule_id" value="<?php echo (int) $editingRow['id']; ?>">
            <?php endif; ?>

            <div class="doc-field">
              <label for="day">Day</label>
              <select id="day" name="day" class="<?php echo !empty($errors['day']) ? 'has-error' : ''; ?>">
                <option value="">Select a day</option>
                <?php foreach ($validDays as $d): ?>
                  <option value="<?php echo $d; ?>" <?php echo $old['day'] === $d ? 'selected' : ''; ?>><?php echo $d; ?></option>
                <?php endforeach; ?>
              </select>
              <?php if (!empty($errors['day'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($errors['day']); ?></span><?php endif; ?>
            </div>

            <div class="doc-field">
              <label for="start_time">Start time</label>
              <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($old['start_time']); ?>"
                     class="<?php echo !empty($errors['start_time']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['start_time'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($errors['start_time']); ?></span><?php endif; ?>
            </div>

            <div class="doc-field">
              <label for="end_time">End time</label>
              <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($old['end_time']); ?>"
                     class="<?php echo !empty($errors['end_time']) ? 'has-error' : ''; ?>">
              <?php if (!empty($errors['end_time'])): ?><span class="doc-field-error"><?php echo htmlspecialchars($errors['end_time']); ?></span><?php endif; ?>
            </div>

            <div class="doc-form-actions">
              <button type="submit" class="doc-btn doc-btn-accept"><i class="fa-solid <?php echo $editingRow ? 'fa-floppy-disk' : 'fa-plus'; ?>"></i> <?php echo $editingRow ? 'Save Changes' : 'Add Slot'; ?></button>
              <?php if ($editingRow): ?>
                <a href="schedule.php" class="doc-btn doc-btn-cancel-link"><i class="fa-solid fa-xmark"></i> Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <!--WEEKLY SCHEDULE LIST -->
        <div class="doc-card doc-week-card">
          <?php foreach ($validDays as $day): ?>
            <div class="doc-day-row">
              <div class="doc-day-name"><?php echo $day; ?></div>
              <div class="doc-day-slots">
                <?php if (empty($slotsByDay[$day])): ?>
                  <span class="doc-muted">Not available</span>
                <?php else: ?>
                  <?php foreach ($slotsByDay[$day] as $slot):
                      $s = DateTime::createFromFormat('H:i:s', $slot['start_time']);
                      $e = DateTime::createFromFormat('H:i:s', $slot['end_time']);
                  ?>
                    <div class="doc-slot-pill">
                      <span><i class="fa-regular fa-clock"></i> <?php echo $s ? $s->format('h:i A') : $slot['start_time']; ?> – <?php echo $e ? $e->format('h:i A') : $slot['end_time']; ?></span>
                      <a href="schedule.php?edit=<?php echo (int) $slot['id']; ?>#form" class="doc-slot-edit" title="Edit"><i class="fa-solid fa-pen"></i></a>
                      <form method="POST" action="schedule.php" data-confirm="Remove this time slot? This can't be undone." class="doc-slot-delete-form">
                        <input type="hidden" name="schedule_id" value="<?php echo (int) $slot['id']; ?>">
                        <button type="submit" name="action" value="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
</body>
</html>