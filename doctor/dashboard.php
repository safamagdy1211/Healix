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
    SELECT
        d.id, d.image, s.name AS specialty_name,
        COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM doctors d
    JOIN specialties s ON s.id = d.specialty_id
    LEFT JOIN reviews r ON r.doctor_id = d.id
    WHERE d.user_id = ?
    GROUP BY d.id, d.image, s.name
    LIMIT 1
");
mysqli_stmt_bind_param($doctorStmt, 'i', $userId);
mysqli_stmt_execute($doctorStmt);
$doctor = mysqli_stmt_get_result($doctorStmt)->fetch_assoc();
mysqli_stmt_close($doctorStmt);

if (!$doctor) {
    die('Your doctor profile is not set up yet. Please contact the admin.');
}

$doctorId = (int) $doctor['id'];

$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
$countStmt = mysqli_prepare($conn, "SELECT status, COUNT(*) AS c FROM appointments WHERE doctor_id = ? GROUP BY status");
mysqli_stmt_bind_param($countStmt, 'i', $doctorId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
while ($row = mysqli_fetch_assoc($countResult)) {
    $counts[$row['status']] = (int) $row['c'];
}
mysqli_stmt_close($countStmt);

$patientsStmt = mysqli_prepare($conn, "SELECT COUNT(DISTINCT patient_id) AS c FROM appointments WHERE doctor_id = ?");
mysqli_stmt_bind_param($patientsStmt, 'i', $doctorId);
mysqli_stmt_execute($patientsStmt);
$uniquePatients = (int) (mysqli_stmt_get_result($patientsStmt)->fetch_assoc()['c'] ?? 0);
mysqli_stmt_close($patientsStmt);

$todayStmt = mysqli_prepare($conn, "
    SELECT a.id, a.appointment_time, a.status, u.name AS patient_name
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND a.appointment_date = CURDATE() AND a.status IN ('pending','confirmed')
    ORDER BY a.appointment_time ASC
");
mysqli_stmt_bind_param($todayStmt, 'i', $doctorId);
mysqli_stmt_execute($todayStmt);
$todayAppointments = mysqli_stmt_get_result($todayStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($todayStmt);

$upcomingStmt = mysqli_prepare($conn, "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, u.name AS patient_name
    FROM appointments a
    JOIN users u ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND a.status IN ('pending','confirmed')
    AND TIMESTAMP(a.appointment_date, a.appointment_time) > NOW()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 5
");
mysqli_stmt_bind_param($upcomingStmt, 'i', $doctorId);
mysqli_stmt_execute($upcomingStmt);
$upcoming = mysqli_stmt_get_result($upcomingStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($upcomingStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Healix Doctor</title>
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

    <!-- SIDEBAR -->
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
        <a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="appointments.php"><i class="fa-solid fa-calendar-check"></i> Appointments</a>
        <a href="schedule.php"><i class="fa-solid fa-clock"></i> Schedule</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
      </nav>

      <a href="../logout.php" class="doc-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!--MAIN -->
    <main class="doc-main">
      <header class="doc-topbar">
        <div>
          <h1><i class="fa-solid fa-hand-sparkles" style="color:var(--teal-500); margin-right:8px;"></i>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
          <p><i class="fa-solid fa-stethoscope" style="color:var(--violet-500); margin-right:4px;"></i> <?php echo htmlspecialchars($doctor['specialty_name']); ?> &middot;
             <?php echo $doctor['review_count'] > 0
                  ? '⭐ ' . htmlspecialchars($doctor['avg_rating']) . ' (' . (int) $doctor['review_count'] . ' reviews)'
                  : 'No reviews yet'; ?>
          </p>
        </div>
        <a href="appointments.php?status=pending" class="doc-welcome">
          <i class="fa-solid fa-bell"></i>
          <?php echo $counts['pending']; ?> pending request<?php echo $counts['pending'] === 1 ? '' : 's'; ?>
        </a>
      </header>

      <!--  STAT CARDS -->
      <div class="doc-stats-grid">
        <a href="appointments.php?status=pending" class="doc-stat-card">
          <div class="doc-stat-icon doc-stat-icon-amber"><i class="fa-solid fa-hourglass-half"></i></div>
          <div><span class="doc-stat-value"><?php echo $counts['pending']; ?></span><span class="doc-stat-label">Pending</span></div>
        </a>
        <a href="appointments.php?status=confirmed" class="doc-stat-card">
          <div class="doc-stat-icon doc-stat-icon-blue"><i class="fa-solid fa-calendar-check"></i></div>
          <div><span class="doc-stat-value"><?php echo $counts['confirmed']; ?></span><span class="doc-stat-label">Confirmed</span></div>
        </a>
        <a href="appointments.php?status=completed" class="doc-stat-card">
          <div class="doc-stat-icon doc-stat-icon-green"><i class="fa-solid fa-circle-check"></i></div>
          <div><span class="doc-stat-value"><?php echo $counts['completed']; ?></span><span class="doc-stat-label">Completed</span></div>
        </a>
        <div class="doc-stat-card doc-stat-card-static">
          <div class="doc-stat-icon doc-stat-icon-navy"><i class="fa-solid fa-user-group"></i></div>
          <div><span class="doc-stat-value"><?php echo $uniquePatients; ?></span><span class="doc-stat-label">Patients</span></div>
        </div>
      </div>

      <div class="doc-dashboard-grid">

        <!-- TODAY -->
        <div class="doc-card doc-section-card">
          <div class="doc-section-head">
            <h2><i class="fa-solid fa-sun"></i> Today's Appointments</h2>
            <a href="appointments.php">View all</a>
          </div>
          <?php if (empty($todayAppointments)): ?>
            <div class="doc-empty doc-empty-small">
              <i class="fa-regular fa-calendar"></i>
              <p>Nothing scheduled for today.</p>
            </div>
          <?php else: ?>
            <ul class="doc-mini-list">
              <?php foreach ($todayAppointments as $a):
                  $t = DateTime::createFromFormat('H:i:s', $a['appointment_time']);
              ?>
                <li>
                  <span class="doc-mini-time"><?php echo $t ? $t->format('h:i A') : $a['appointment_time']; ?></span>
                  <span class="doc-mini-name"><?php echo htmlspecialchars($a['patient_name']); ?></span>
                  <span class="doc-badge doc-badge-<?php echo htmlspecialchars($a['status']); ?>"><?php echo ucfirst($a['status']); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>

        <!-- UPCOMING  -->
        <div class="doc-card doc-section-card">
          <div class="doc-section-head">
            <h2><i class="fa-solid fa-hourglass-start"></i> Upcoming</h2>
            <a href="appointments.php">View all</a>
          </div>
          <?php if (empty($upcoming)): ?>
            <div class="doc-empty doc-empty-small">
              <i class="fa-regular fa-calendar-plus"></i>
              <p>No upcoming appointments.</p>
            </div>
          <?php else: ?>
            <ul class="doc-mini-list">
              <?php foreach ($upcoming as $a):
                  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $a['appointment_date'] . ' ' . $a['appointment_time']);
              ?>
                <li>
                  <span class="doc-mini-time"><?php echo $dt ? $dt->format('d M, h:i A') : ''; ?></span>
                  <span class="doc-mini-name"><?php echo htmlspecialchars($a['patient_name']); ?></span>
                  <span class="doc-badge doc-badge-<?php echo htmlspecialchars($a['status']); ?>"><?php echo ucfirst($a['status']); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
</body>
</html>