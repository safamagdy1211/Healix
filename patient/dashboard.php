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

$counts = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0];
$countStmt = mysqli_prepare($conn, "SELECT status, COUNT(*) AS c FROM appointments WHERE patient_id = ? GROUP BY status");
mysqli_stmt_bind_param($countStmt, 'i', $patientId);
mysqli_stmt_execute($countStmt);
$countResult = mysqli_stmt_get_result($countStmt);
$total = 0;
while ($row = mysqli_fetch_assoc($countResult)) {
    $counts[$row['status']] = (int) $row['c'];
    $total += (int) $row['c'];
}
mysqli_stmt_close($countStmt);

$upcomingCount = 0;
$upcomingCountStmt = mysqli_prepare($conn, "
    SELECT COUNT(*) AS c FROM appointments
    WHERE patient_id = ? AND status IN ('pending','confirmed')
    AND TIMESTAMP(appointment_date, appointment_time) > NOW()
");
mysqli_stmt_bind_param($upcomingCountStmt, 'i', $patientId);
mysqli_stmt_execute($upcomingCountStmt);
$upcomingCount = (int) (mysqli_stmt_get_result($upcomingCountStmt)->fetch_assoc()['c'] ?? 0);
mysqli_stmt_close($upcomingCountStmt);

$nextStmt = mysqli_prepare($conn, "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status, a.reason,
           u.name AS doctor_name, s.name AS specialty_name, d.id AS doctor_id
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    WHERE a.patient_id = ? AND a.status IN ('pending','confirmed')
    AND TIMESTAMP(a.appointment_date, a.appointment_time) > NOW()
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
    LIMIT 1
");
mysqli_stmt_bind_param($nextStmt, 'i', $patientId);
mysqli_stmt_execute($nextStmt);
$next = mysqli_stmt_get_result($nextStmt)->fetch_assoc();
mysqli_stmt_close($nextStmt);

$recentStmt = mysqli_prepare($conn, "
    SELECT a.id, a.appointment_date, a.appointment_time, a.status,
           u.name AS doctor_name, s.name AS specialty_name
    FROM appointments a
    JOIN doctors d ON d.id = a.doctor_id
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    WHERE a.patient_id = ?
    ORDER BY a.created_at DESC
    LIMIT 5
");
mysqli_stmt_bind_param($recentStmt, 'i', $patientId);
mysqli_stmt_execute($recentStmt);
$recent = mysqli_stmt_get_result($recentStmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($recentStmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Healix</title>
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

    <!--  SIDEBAR -->
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
        <a href="dashboard.php" class="active"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        <a href="appointments.php"><i class="fa-solid fa-calendar-check"></i> My Appointments</a>
        <a href="profile.php"><i class="fa-solid fa-user"></i> Profile</a>
        <a href="../doctors.php"><i class="fa-solid fa-magnifying-glass"></i> Find a Doctor</a>
      </nav>

      <a href="../logout.php" class="pat-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </aside>

    <!-- MAIN -->
    <main class="pat-main">
      <header class="pat-topbar">
        <div>
          <h1><i class="fa-solid fa-hand-sparkles" style="color:var(--teal-500); margin-right:8px;"></i>Welcome back <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
          <p>Here's what's happening with your appointments.</p>
        </div>
        <a href="../doctors.php" class="pat-btn pat-btn-primary" style="margin-top:0;"><i class="fa-solid fa-calendar-plus"></i> Book a Doctor</a>
      </header>

      <!--  STAT CARDS  -->
      <div class="pat-stats-grid">
        <a href="appointments.php?status=upcoming" class="pat-stat-card">
          <div class="pat-stat-icon pat-stat-icon-blue"><i class="fa-solid fa-calendar-days"></i></div>
          <div><span class="pat-stat-value"><?php echo $upcomingCount; ?></span><span class="pat-stat-label">Upcoming</span></div>
        </a>
        <a href="appointments.php?status=completed" class="pat-stat-card">
          <div class="pat-stat-icon pat-stat-icon-green"><i class="fa-solid fa-circle-check"></i></div>
          <div><span class="pat-stat-value"><?php echo $counts['completed']; ?></span><span class="pat-stat-label">Completed</span></div>
        </a>
        <a href="appointments.php?status=cancelled" class="pat-stat-card">
          <div class="pat-stat-icon pat-stat-icon-muted"><i class="fa-solid fa-ban"></i></div>
          <div><span class="pat-stat-value"><?php echo $counts['cancelled']; ?></span><span class="pat-stat-label">Cancelled</span></div>
        </a>
        <div class="pat-stat-card pat-stat-card-static">
          <div class="pat-stat-icon pat-stat-icon-navy"><i class="fa-solid fa-clipboard-list"></i></div>
          <div><span class="pat-stat-value"><?php echo $total; ?></span><span class="pat-stat-label">Total Bookings</span></div>
        </div>
      </div>

      <!-- NEXT APPOINTMENT  -->
      <?php if ($next):
          $dt = DateTime::createFromFormat('Y-m-d H:i:s', $next['appointment_date'] . ' ' . $next['appointment_time']);
      ?>
        <div class="pat-next-card">
          <div class="pat-next-label"><i class="fa-solid fa-star"></i> Your next appointment</div>
          <div class="pat-next-body">
            <div>
              <h3><?php echo htmlspecialchars($next['doctor_name']); ?></h3>
              <span class="pat-specialty"><?php echo htmlspecialchars($next['specialty_name']); ?></span>
            </div>
            <div class="pat-next-when">
              <i class="fa-regular fa-calendar"></i> <?php echo $dt ? $dt->format('D, d M Y') : ''; ?>
              &nbsp;&middot;&nbsp;
              <i class="fa-regular fa-clock"></i> <?php echo $dt ? $dt->format('h:i A') : ''; ?>
            </div>
            <span class="pat-badge pat-badge-<?php echo htmlspecialchars($next['status']); ?>"><?php echo ucfirst($next['status']); ?></span>
          </div>
        </div>
      <?php else: ?>
        <div class="pat-card">
          <div class="pat-empty">
            <i class="fa-regular fa-calendar-plus"></i>
            <p>No upcoming appointments booked yet.</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- RECENT ACTIVITY  -->
      <div class="pat-section-head">
        <h2><i class="fa-solid fa-clock-rotate-left"></i> Recent Activity</h2>
        <a href="appointments.php">View all</a>
      </div>

      <?php if (empty($recent)): ?>
        <div class="pat-card">
          <div class="pat-empty"><i class="fa-regular fa-clock"></i><p>No activity yet.</p></div>
        </div>
      <?php else: ?>
        <div class="pat-card">
          <ul class="pat-mini-list">
            <?php foreach ($recent as $r):
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $r['appointment_date'] . ' ' . $r['appointment_time']);
            ?>
              <li>
                <span class="pat-mini-icon"><i class="fa-solid fa-user-doctor"></i></span>
                <div class="pat-mini-info">
                  <span class="pat-mini-name"><?php echo htmlspecialchars($r['doctor_name']); ?></span>
                  <span class="pat-mini-specialty"><?php echo htmlspecialchars($r['specialty_name']); ?></span>
                </div>
                <div class="pat-mini-meta">
                  <span class="pat-mini-time"><?php echo $dt ? $dt->format('d M Y, h:i A') : ''; ?></span>
                  <span class="pat-badge pat-badge-<?php echo htmlspecialchars($r['status']); ?>"><?php echo ucfirst($r['status']); ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <script src="../js/dashboard-ui.js"></script>
</body>
</html>