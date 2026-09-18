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

$patientId = (int) $_SESSION['user_id'];

expirePendingAppointments($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax        = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    $action        = $_POST['action'] ?? '';
    $appointmentId = isset($_POST['appointment_id']) ? (int) $_POST['appointment_id'] : 0;
    $ok            = false;
    $errorMsg      = '';

    if ($action === 'cancel' && $appointmentId > 0) {
        // Single atomic query: only cancels if it belongs to this patient,
        // is still pending/confirmed, and hasn't started yet.
        $upd = mysqli_prepare($conn, "
            UPDATE appointments
            SET status = 'cancelled'
            WHERE id = ? AND patient_id = ?
              AND status IN ('pending','confirmed')
              AND TIMESTAMP(appointment_date, appointment_time) > NOW()
        ");
        mysqli_stmt_bind_param($upd, 'ii', $appointmentId, $patientId);
        mysqli_stmt_execute($upd);
        $ok = mysqli_stmt_affected_rows($upd) > 0;
        mysqli_stmt_close($upd);

        if (!$ok) {
            $errorMsg = 'This appointment can no longer be cancelled.';
        }
    }

    // AJAX request: answer immediately with JSON (no redirect / page reload)
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => $ok, 'error' => $errorMsg]);
        exit();
    }

    // Normal form fallback (JS disabled)
    if ($errorMsg !== '') {
        $_SESSION['pa_error'] = $errorMsg;
    }
    header('Location: appointments.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
    exit();
}

$error   = $_SESSION['pa_error'] ?? null;
unset($_SESSION['pa_error']);

$validTabs = ['upcoming', 'past', 'pending', 'confirmed', 'completed', 'cancelled', 'rejected'];
$activeTab = $_GET['status'] ?? 'upcoming';
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'upcoming';
}

$counts = ['all' => 0, 'upcoming' => 0, 'past' => 0, 'pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0, 'rejected' => 0];
$countStmt = mysqli_prepare($conn, "
    SELECT status, CONCAT(appointment_date, ' ', appointment_time) AS dt
    FROM appointments
    WHERE patient_id = ?
");
mysqli_stmt_bind_param($countStmt, 'i', $patientId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$now = time();
while ($row = mysqli_fetch_assoc($countResult)) {
    $counts['all']++;
    $counts[$row['status']] = ($counts[$row['status']] ?? 0) + 1;
    $isFuture = strtotime($row['dt']) > $now;
    if (in_array($row['status'], ['pending', 'confirmed'], true) && $isFuture) {
        $counts['upcoming']++;
    } else {
        $counts['past']++;
    }
}
mysqli_stmt_close($countStmt);

$sql = "
    SELECT
        a.id, a.appointment_date, a.appointment_time, a.reason, a.status,
        u.name AS doctor_name, s.name AS specialty_name, d.id AS doctor_id
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    WHERE a.patient_id = ?
";
$params = [$patientId];
$types  = 'i';

if ($activeTab === 'upcoming') {
    $sql .= " AND a.status IN ('pending','confirmed') AND TIMESTAMP(a.appointment_date, a.appointment_time) > NOW()";
} elseif ($activeTab === 'past') {
    $sql .= " AND (a.status IN ('completed','cancelled','rejected') OR TIMESTAMP(a.appointment_date, a.appointment_time) <= NOW())";
} else {
    $sql .= " AND a.status = ?";
    $params[] = $activeTab;
    $types .= 's';
}

$sql .= $activeTab === 'upcoming'
    ? " ORDER BY a.appointment_date ASC, a.appointment_time ASC"
    : " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$listStmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($listStmt, $types, ...$params);
mysqli_stmt_execute($listStmt);
$appointments = mysqli_stmt_get_result($listStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($listStmt);

$tabs = [
    'upcoming'  => 'Upcoming',
    'past'      => 'Past',
    'pending'   => 'Pending',
    'confirmed' => 'Confirmed',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled',
    'rejected'  => 'Rejected',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Appointments — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/patient.css?v=<?php echo filemtime(__DIR__ . '/../css/patient.css'); ?>">
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
        <a href="appointments.php" class="active"><i class="fa-solid fa-calendar-check"></i> My Appointments</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="../doctors.php"><i class="fa-solid fa-magnifying-glass"></i> Find a Doctor</a>
      </nav>

      <a href="../logout.php" class="pat-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!--  MAIN -->
    <main class="pat-main">
      <header class="pat-topbar">
        <div>
          <h1><i class="fa-solid fa-calendar-check" style="color:var(--teal-500); margin-right:8px;"></i>My Appointments</h1>
          <p>Track your booking requests and manage upcoming visits.</p>
        </div>
        <div class="pat-topbar-user">
          <span class="pat-welcome"><i class="fa-solid fa-hand"></i> Hi, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
        </div>
      </header>

      <?php if ($error): ?>
        <div class="pat-alert pat-alert-error"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <!--  FILTER TABS -->
      <div class="pat-tabs">
        <?php foreach ($tabs as $key => $label): ?>
          <a href="appointments.php?status=<?php echo $key; ?>"
             class="pat-tab <?php echo $activeTab === $key ? 'active' : ''; ?>">
            <?php echo $label; ?>
            <span class="pat-tab-count"><?php echo $counts[$key]; ?></span>
          </a>
        <?php endforeach; ?>
      </div>

      <!--  APPOINTMENTS LIST -->
      <?php if (empty($appointments)): ?>
        <div class="pat-card">
          <div class="pat-empty">
            <i class="fa-regular fa-calendar-xmark"></i>
            <p>No <?php echo htmlspecialchars($tabs[$activeTab]); ?> appointments yet.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="pat-appt-grid">
          <?php foreach ($appointments as $row):
              $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row['appointment_date'] . ' ' . $row['appointment_time']);
              $isFuture = $dt && $dt->getTimestamp() > time();
              $canCancel = in_array($row['status'], ['pending', 'confirmed'], true) && $isFuture;
          ?>
            <div class="pat-appt-card" data-appt-card data-status="<?php echo htmlspecialchars($row['status']); ?>">
              <div class="pat-appt-top">
                <div>
                  <h3><?php echo htmlspecialchars($row['doctor_name']); ?></h3>
                  <span class="pat-specialty"><?php echo htmlspecialchars($row['specialty_name']); ?></span>
                </div>
                <span class="pat-badge pat-badge-<?php echo htmlspecialchars($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span>
              </div>

              <div class="pat-appt-meta">
                <span><i class="fa-regular fa-calendar"></i> <?php echo $dt ? $dt->format('D, d M Y') : htmlspecialchars($row['appointment_date']); ?></span>
                <span><i class="fa-regular fa-clock"></i> <?php echo $dt ? $dt->format('h:i A') : htmlspecialchars($row['appointment_time']); ?></span>
              </div>

              <?php if ($row['reason']): ?>
                <p class="pat-appt-reason"><?php echo htmlspecialchars($row['reason']); ?></p>
              <?php endif; ?>

              <div class="pat-appt-actions">
                <a href="../doctor-details.php?id=<?php echo (int) $row['doctor_id']; ?>" class="pat-btn pat-btn-outline"><i class="fa-solid fa-user-doctor"></i> View Doctor</a>
                <?php if ($canCancel): ?>
                  <form method="POST" action="appointments.php<?php echo isset($_GET['status']) ? '?status=' . htmlspecialchars($activeTab) : ''; ?>"
                        data-ajax data-confirm="Cancel this appointment? This can't be undone.">
                    <input type="hidden" name="appointment_id" value="<?php echo (int) $row['id']; ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button type="submit" class="pat-btn pat-btn-cancel"><i class="fa-solid fa-ban"></i> Cancel</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <script src="../js/dashboard-ui.js?v=<?php echo filemtime(__DIR__ . '/../js/dashboard-ui.js'); ?>"></script>
</body>
</html>