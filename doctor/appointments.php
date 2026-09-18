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
$doctorStmt = mysqli_prepare($conn, "SELECT id, specialty_id FROM doctors WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($doctorStmt, 'i', $userId);
mysqli_stmt_execute($doctorStmt);
$doctorRow = mysqli_stmt_get_result($doctorStmt)->fetch_assoc();
mysqli_stmt_close($doctorStmt);

if (!$doctorRow) {
    die('Your doctor profile is not set up yet. Please contact the admin.');
}

$doctorId = (int) $doctorRow['id'];

expirePendingAppointments($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action        = $_POST['action'] ?? '';
    $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;

    $allowedActions = ['accept', 'reject', 'complete'];

    if ($appointmentId > 0 && in_array($action, $allowedActions, true)) {
        
        $apptStmt = mysqli_prepare($conn, "
            SELECT id, status, appointment_date, appointment_time
            FROM appointments
            WHERE id = ? AND doctor_id = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($apptStmt, 'ii', $appointmentId, $doctorId);
        mysqli_stmt_execute($apptStmt);
        $appt = mysqli_stmt_get_result($apptStmt)->fetch_assoc();
        mysqli_stmt_close($apptStmt);

        if (!$appt) {
            $_SESSION['da_error'] = 'Appointment not found.';
        } else {
            $currentStatus = $appt['status'];

            if ($action === 'accept') {
                if ($currentStatus !== 'pending') {
                    $_SESSION['da_error'] = 'Only pending appointments can be accepted.';
                } else {
                    $upd = mysqli_prepare($conn, "UPDATE appointments SET status = 'confirmed' WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'i', $appointmentId);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                    $_SESSION['da_success'] = 'Appointment confirmed.';
                }
            } elseif ($action === 'reject') {
                if ($currentStatus !== 'pending') {
                    $_SESSION['da_error'] = 'Only pending appointments can be rejected.';
                } else {
                    $upd = mysqli_prepare($conn, "UPDATE appointments SET status = 'rejected' WHERE id = ?");
                    mysqli_stmt_bind_param($upd, 'i', $appointmentId);
                    mysqli_stmt_execute($upd);
                    mysqli_stmt_close($upd);
                    $_SESSION['da_success'] = 'Appointment rejected.';
                }
            } elseif ($action === 'complete') {
                if ($currentStatus !== 'confirmed') {
                    $_SESSION['da_error'] = 'Only confirmed appointments can be marked as completed.';
                } else {
                   
                    $apptDateTime = $appt['appointment_date'] . ' ' . $appt['appointment_time'];
                    if (strtotime($apptDateTime) > time()) {
                        $_SESSION['da_error'] = 'This appointment is still upcoming — it can only be completed after its scheduled time.';
                    } else {
                        $upd = mysqli_prepare($conn, "UPDATE appointments SET status = 'completed' WHERE id = ?");
                        mysqli_stmt_bind_param($upd, 'i', $appointmentId);
                        mysqli_stmt_execute($upd);
                        mysqli_stmt_close($upd);
                        $_SESSION['da_success'] = 'Appointment marked as completed.';
                    }
                }
            }
        }
    }

    header('Location: appointments.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$success = $_SESSION['da_success'] ?? null;
$error   = $_SESSION['da_error'] ?? null;
unset($_SESSION['da_success'], $_SESSION['da_error']);

$validStatuses = ['pending', 'confirmed', 'completed', 'rejected', 'cancelled'];
$activeStatus  = $_GET['status'] ?? 'all';
if ($activeStatus !== 'all' && !in_array($activeStatus, $validStatuses, true)) {
    $activeStatus = 'all';
}

$counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
$countStmt = mysqli_prepare($conn, "SELECT status, COUNT(*) AS c FROM appointments WHERE doctor_id = ? GROUP BY status");
mysqli_stmt_bind_param($countStmt, 'i', $doctorId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
while ($row = mysqli_fetch_assoc($countResult)) {
    $counts[$row['status']] = (int) $row['c'];
    $counts['all'] += (int) $row['c'];
}
mysqli_stmt_close($countStmt);

$sql = "
    SELECT
        a.id, a.appointment_date, a.appointment_time, a.reason, a.status, a.created_at,
        u.name AS patient_name, u.email AS patient_email, u.phone AS patient_phone
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ?
";
$params = [$doctorId];
$types  = 'i';

if ($activeStatus !== 'all') {
    $sql .= " AND a.status = ?";
    $params[] = $activeStatus;
    $types .= 's';
}

$sql .= in_array($activeStatus, ['completed', 'rejected', 'cancelled'], true)
    ? " ORDER BY a.appointment_date DESC, a.appointment_time DESC"
    : " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$listStmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($listStmt, $types, ...$params);
mysqli_stmt_execute($listStmt);
$appointments = mysqli_stmt_get_result($listStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($listStmt);

$tabs = [
    'all'       => 'All',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'rejected'  => 'Rejected',
    'cancelled' => 'Cancelled',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Appointments — Healix Doctor</title>
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
        <a href="appointments.php" class="active"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
        <a href="schedule.php"><i class="fa-solid fa-clock"></i> Schedule</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
      </nav>

      <a href="../logout.php" class="doc-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!--  MAIN  -->
    <main class="doc-main">
      <header class="doc-topbar">
        <div>
          <h1><i class="fa-solid fa-calendar-check" style="color:var(--teal-500); margin-right:8px;"></i>Appointments</h1>
          <p>Review requests, respond to your patients, and track past visits.</p>
        </div>
        <div class="doc-topbar-user">
          <span class="doc-welcome"><i class="fa-solid fa-user-doctor"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
      </header>

      <?php if ($error): ?>
        <div class="doc-alert doc-alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!--  STATUS TABS  -->
      <div class="doc-tabs">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="appointments.php?status=<?php echo $key; ?>"
             class="doc-tab <?php echo $activeStatus === $key ? 'active' : ''; ?>">
            <?php echo $label; ?>
            <span class="doc-tab-count"><?php echo $counts[$key]; ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- APPOINTMENTS TABLE -->
      <div class="doc-card">
        <?php if (empty($appointments)): ?>
          <div class="doc-empty">
            <i class="fa-regular fa-calendar-xmark"></i>
            <p>No <?php echo $activeStatus === 'all' ? '' : htmlspecialchars($activeStatus) . ' '; ?>appointments here yet.</p>
          </div>
        <?php else: ?>
          <div class="doc-table-wrap">
          <table class="doc-table">
            <thead>
              <tr>
                <th><i class="fa-solid fa-user"></i> Patient</th>
                <th><i class="fa-regular fa-calendar"></i> Date &amp; Time</th>
                <th><i class="fa-regular fa-note-sticky"></i> Reason</th>
                <th><i class="fa-solid fa-circle-info"></i> Status</th>
                <th><i class="fa-solid fa-bolt"></i> Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($appointments as $row):
                  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['appointment_date'] . ' ' . $row['appointment_time']);
                  $isPast = $dt && $dt->getTimestamp() <= time();
              ?>
                <tr>
                  <td>
                    <div class="doc-patient">
                      <div class="doc-patient-info">
                        <span class="doc-patient-name"><?php echo htmlspecialchars($row['patient_name']); ?></span>
                        <span class="doc-patient-contact"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($row['patient_phone']); ?> &middot; <?php echo htmlspecialchars($row['patient_email']); ?></span>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php echo $dt ? $dt->format('D, d M Y') : htmlspecialchars($row['appointment_date']); ?><br>
                    <span class="doc-time"><i class="fa-regular fa-clock"></i> <?php echo $dt ? $dt->format('h:i A') : htmlspecialchars($row['appointment_time']); ?></span>
                  </td>
                  <td class="doc-reason"><?php echo $row['reason'] ? htmlspecialchars($row['reason']) : '<span class="doc-muted">—</span>'; ?></td>
                  <td><span class="doc-badge doc-badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                  <td>
                    <?php if ($row['status'] === 'pending'): ?>
                      <form method="POST" action="appointments.php<?php echo $activeStatus !== 'all' ? '?status=' . $activeStatus : ''; ?>" class="doc-inline-form">
                        <input type="hidden" name="appointment_id" value="<?php echo (int) $row['id']; ?>">
                        <button type="submit" name="action" value="accept" class="doc-btn doc-btn-accept"><i class="fa-solid fa-check"></i> Accept</button>
                        <button type="submit" name="action" value="reject" class="doc-btn doc-btn-reject"><i class="fa-solid fa-xmark"></i> Reject</button>
                      </form>
                    <?php elseif ($row['status'] === 'confirmed'): ?>
                      <form method="POST" action="appointments.php<?php echo $activeStatus !== 'all' ? '?status=' . $activeStatus : ''; ?>" class="doc-inline-form">
                        <input type="hidden" name="appointment_id" value="<?php echo (int) $row['id']; ?>">
                        <button type="submit" name="action" value="complete" class="doc-btn doc-btn-complete" <?php echo !$isPast ? 'disabled title="Available after the appointment time"' : ''; ?>><i class="fa-solid fa-flag-checkered"></i> Complete</button>
                      </form>
                    <?php else: ?>
                      <span class="doc-muted">—</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
</body>
</html>