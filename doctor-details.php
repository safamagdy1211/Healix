<?php

session_start();
require_once __DIR__ . '/database.php';

$doctorId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($doctorId <= 0) {
    header('Location: Doctors.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'book') {
        $bookErrors = [];
        $bookOld = [
            'appointment_date' => trim($_POST['appointment_date'] ?? ''),
            'appointment_time' => trim($_POST['appointment_time'] ?? ''),
            'reason'           => trim($_POST['reason'] ?? ''),
        ];

        if (!isset($_SESSION['user_id'])) {
            $_SESSION['dd_notice'] = 'Please log in as a patient to book an appointment.';
            header('Location: doctor-details.php?id=' . $doctorId);
            exit();
        }

        if ($_SESSION['user_role'] !== 'patient') {
            $bookErrors['general'] = 'Only patients can book appointments.';
        }

        $doctorCheckStmt = mysqli_prepare($conn, "SELECT id FROM doctors WHERE id = ?");
        mysqli_stmt_bind_param($doctorCheckStmt, 'i', $doctorId);
        mysqli_stmt_execute($doctorCheckStmt);
        mysqli_stmt_store_result($doctorCheckStmt);
        if (mysqli_stmt_num_rows($doctorCheckStmt) === 0) {
            mysqli_stmt_close($doctorCheckStmt);
            header('Location: Doctors.php');
            exit();
        }
        mysqli_stmt_close($doctorCheckStmt);

        $date = $bookOld['appointment_date'];
        $time = $bookOld['appointment_time'];
        $reason = $bookOld['reason'];

        $dateObj = $date !== '' ? DateTime::createFromFormat('Y-m-d', $date) : false;
        $timeObj = $time !== '' ? DateTime::createFromFormat('H:i', $time) : false;

        if ($date === '') {
            $bookErrors['appointment_date'] = 'Please choose a date.';
        } elseif (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $bookErrors['appointment_date'] = 'Please choose a valid date.';
        } elseif ($date < date('Y-m-d')) {
            $bookErrors['appointment_date'] = 'The date cannot be in the past.';
        }

        if ($time === '') {
            $bookErrors['appointment_time'] = 'Please choose a time.';
        } elseif (!$timeObj) {
            $bookErrors['appointment_time'] = 'Please choose a valid time.';
        }

        if (strlen($reason) > 255) {
            $bookErrors['reason'] = 'Reason is too long (255 characters max).';
        }

       
        if (empty($bookErrors) && $dateObj && $timeObj) {
            $dayName = $dateObj->format('l'); 

            $scheduleStmt = mysqli_prepare($conn, "
                SELECT start_time, end_time
                FROM doctor_schedules
                WHERE doctor_id = ? AND day = ?
            ");
            mysqli_stmt_bind_param($scheduleStmt, 'is', $doctorId, $dayName);
            mysqli_stmt_execute($scheduleStmt);
            $scheduleResult = mysqli_stmt_get_result($scheduleStmt);

            $withinWorkingHours = false;
            $worksThisDay = false;
            while ($row = mysqli_fetch_assoc($scheduleResult)) {
                $worksThisDay = true;
                if ($time >= substr($row['start_time'], 0, 5) && $time < substr($row['end_time'], 0, 5)) {
                    $withinWorkingHours = true;
                    break;
                }
            }
            mysqli_stmt_close($scheduleStmt);

            if (!$worksThisDay) {
                $bookErrors['appointment_date'] = 'This doctor does not work on ' . $dayName . '.';
            } elseif (!$withinWorkingHours) {
                $bookErrors['appointment_time'] = 'This time is outside the doctor\'s working hours on ' . $dayName . '.';
            }
        }

       
        if (empty($bookErrors)) {
            $slotStmt = mysqli_prepare($conn, "
                SELECT id FROM appointments
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ?
            ");
            mysqli_stmt_bind_param($slotStmt, 'iss', $doctorId, $date, $time);
            mysqli_stmt_execute($slotStmt);
            mysqli_stmt_store_result($slotStmt);
            if (mysqli_stmt_num_rows($slotStmt) > 0) {
                $bookErrors['appointment_time'] = 'This slot is already booked. Please pick another time.';
            }
            mysqli_stmt_close($slotStmt);
        }

       
        if (empty($bookErrors)) {
            $patientId = (int) $_SESSION['user_id'];
            $reasonToStore = $reason !== '' ? $reason : null;

            $insertStmt = mysqli_prepare($conn, "
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            mysqli_stmt_bind_param($insertStmt, 'iisss', $patientId, $doctorId, $date, $time, $reasonToStore);

            if (mysqli_stmt_execute($insertStmt)) {
                $_SESSION['dd_book_success'] = 'Your appointment request has been sent! You will see its status in your dashboard once the doctor responds.';
            } else {
                $bookErrors['general'] = 'This slot was just booked by someone else. Please pick another time.';
            }
            mysqli_stmt_close($insertStmt);
        }

        if (!empty($bookErrors)) {
            $_SESSION['dd_book_errors'] = $bookErrors;
            $_SESSION['dd_book_old'] = $bookOld;
        }

        header('Location: doctor-details.php?id=' . $doctorId . '#book');
        exit();
    }

    if ($action === 'review') {
        $reviewErrors = [];
        $reviewOld = [
            'rating'  => trim($_POST['rating'] ?? ''),
            'comment' => trim($_POST['comment'] ?? ''),
        ];

        if (!isset($_SESSION['user_id'])) {
            $_SESSION['dd_notice'] = 'Please log in as a patient to leave a review.';
            header('Location: doctor-details.php?id=' . $doctorId);
            exit();
        }

        if ($_SESSION['user_role'] !== 'patient') {
            $reviewErrors['general'] = 'Only patients can leave reviews.';
        }

        $doctorCheckStmt = mysqli_prepare($conn, "SELECT id FROM doctors WHERE id = ?");
        mysqli_stmt_bind_param($doctorCheckStmt, 'i', $doctorId);
        mysqli_stmt_execute($doctorCheckStmt);
        mysqli_stmt_store_result($doctorCheckStmt);
        if (mysqli_stmt_num_rows($doctorCheckStmt) === 0) {
            mysqli_stmt_close($doctorCheckStmt);
            header('Location: Doctors.php');
            exit();
        }
        mysqli_stmt_close($doctorCheckStmt);

        $rating = $reviewOld['rating'];
        $comment = $reviewOld['comment'];

        if ($rating === '' || !ctype_digit((string) $rating) || (int) $rating < 1 || (int) $rating > 5) {
            $reviewErrors['rating'] = 'Please choose a rating from 1 to 5.';
        }

        if ($comment === '') {
            $reviewErrors['comment'] = 'Please write a short comment.';
        } elseif (mb_strlen($comment) > 1000) {
            $reviewErrors['comment'] = 'Comment is too long (1000 characters max).';
        }

        if (empty($reviewErrors)) {
            $patientId = (int) $_SESSION['user_id'];
            $ratingInt = (int) $rating;

            $insertStmt = mysqli_prepare($conn, "
                INSERT INTO reviews (patient_id, doctor_id, rating, comment)
                VALUES (?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($insertStmt, 'iiis', $patientId, $doctorId, $ratingInt, $comment);
            mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);

            $_SESSION['dd_review_success'] = 'Thanks! Your review has been posted.';
        }

        if (!empty($reviewErrors)) {
            $_SESSION['dd_review_errors'] = $reviewErrors;
            $_SESSION['dd_review_old'] = $reviewOld;
        }

        header('Location: doctor-details.php?id=' . $doctorId . '#reviews');
        exit();
    }
}


$notice = $_SESSION['dd_notice'] ?? null;

$bookErrors = $_SESSION['dd_book_errors'] ?? [];
$bookOld = $_SESSION['dd_book_old'] ?? ['appointment_date' => '', 'appointment_time' => '', 'reason' => ''];
$bookSuccess = $_SESSION['dd_book_success'] ?? null;

$reviewErrors = $_SESSION['dd_review_errors'] ?? [];
$reviewOld = $_SESSION['dd_review_old'] ?? ['rating' => '', 'comment' => ''];
$reviewSuccess = $_SESSION['dd_review_success'] ?? null;

unset(
    $_SESSION['dd_notice'],
    $_SESSION['dd_book_errors'], $_SESSION['dd_book_old'], $_SESSION['dd_book_success'],
    $_SESSION['dd_review_errors'], $_SESSION['dd_review_old'], $_SESSION['dd_review_success']
);

$doctorStmt = mysqli_prepare($conn, "
    SELECT
        d.id, d.experience, d.bio, d.address, d.image,
        u.id AS user_id, u.name,
        s.name AS specialty_name,
        COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    LEFT JOIN reviews r ON r.doctor_id = d.id
    WHERE d.id = ?
    GROUP BY d.id, d.experience, d.bio, d.address, d.image, u.id, u.name, s.name
");
mysqli_stmt_bind_param($doctorStmt, 'i', $doctorId);
mysqli_stmt_execute($doctorStmt);
$doctorResult = mysqli_stmt_get_result($doctorStmt);
$doctor = $doctorResult ? mysqli_fetch_assoc($doctorResult) : null;
mysqli_stmt_close($doctorStmt);

if (!$doctor) {
    header('Location: Doctors.php');
    exit();
}

$schedule = [];
$scheduleStmt = mysqli_prepare($conn, "
    SELECT day, start_time, end_time
    FROM doctor_schedules
    WHERE doctor_id = ?
    ORDER BY FIELD(day, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), start_time
");
mysqli_stmt_bind_param($scheduleStmt, 'i', $doctorId);
mysqli_stmt_execute($scheduleStmt);
$scheduleResult = mysqli_stmt_get_result($scheduleStmt);
while ($row = mysqli_fetch_assoc($scheduleResult)) {
    $schedule[] = $row;
}
mysqli_stmt_close($scheduleStmt);

$reviews = [];
$reviewsStmt = mysqli_prepare($conn, "
    SELECT r.rating, r.comment, r.created_at, u.name AS patient_name
    FROM reviews r
    JOIN users u ON u.id = r.patient_id
    WHERE r.doctor_id = ?
    ORDER BY r.created_at DESC
");
mysqli_stmt_bind_param($reviewsStmt, 'i', $doctorId);
mysqli_stmt_execute($reviewsStmt);
$reviewsResult = mysqli_stmt_get_result($reviewsStmt);
while ($row = mysqli_fetch_assoc($reviewsResult)) {
    $reviews[] = $row;
}
mysqli_stmt_close($reviewsStmt);

function specialtyTitle(string $specialtyName): string
{
    $titles = [
        'Cardiology'    => 'Consultant Cardiologist',
        'Dentistry'     => 'Consultant Dentist',
        'Dermatology'   => 'Consultant Dermatologist',
        'Pediatrics'    => 'Consultant Pediatrician',
        'Ophthalmology' => 'Consultant Ophthalmologist',
        'Orthopedics'   => 'Consultant Orthopedic Surgeon',
    ];
    return $titles[$specialtyName] ?? ('Consultant ' . $specialtyName);
}

function specialtyColor(string $specialtyName): string
{
    $colors = [
        'Cardiology'    => '#14b8a6',
        'Dentistry'     => '#14b8a6',
        'Dermatology'   => '#14b8a6',
        'Pediatrics'    => '#14b8a6',
        'Ophthalmology' => '#14b8a6',
        'Orthopedics'   => '#14b8a6',
    ];
    return $colors[$specialtyName] ?? '#0d9488';
}

function renderStars(float $rating): string
{
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $out .= '<i class="fa-solid fa-star"></i>';
        } elseif ($rating >= $i - 0.5) {
            $out .= '<i class="fa-solid fa-star-half-stroke"></i>';
        } else {
            $out .= '<i class="fa-regular fa-star"></i>';
        }
    }
    return $out;
}

function formatTime12(string $time): string
{
    return date('g:i A', strtotime($time));
}

function timeAgo(string $datetime): string
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 2592000) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

$isLoggedIn = isset($_SESSION['user_id']);
$isPatient  = $isLoggedIn && $_SESSION['user_role'] === 'patient';
$specColor  = specialtyColor($doctor['specialty_name']);
$today      = date('Y-m-d');
$todayName  = date('l');


$autoOpenModal = '';
if (!empty($bookErrors)) {
    $autoOpenModal = 'book';
} elseif (!empty($reviewErrors)) {
    $autoOpenModal = 'review';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($doctor['name']); ?> — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/all.min.css">
  <link rel="stylesheet" href="css/doctor-details.css">
</head>
<body class="doctor-details-page" data-auto-open-modal="<?php echo htmlspecialchars($autoOpenModal); ?>">

  <!--NAVBAR  -->
  <header class="navbar">
    <div class="container">
      <a href="index.php" class="nav-logo">
        <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M20 33s-11-6.8-15-14C2.2 13.6 4.5 8 10 8c3.3 0 5.6 1.8 7 3.9C18.4 9.8 20.7 8 24 8c5.5 0 7.8 5.6 5 11-4 7.2-9 14-9 14Z"
                stroke="#2dd4bf" stroke-width="2.4" stroke-linejoin="round"/>
          <path d="M6 20h6l2.5-6L18 26l3-11 2 5h9" stroke="#2dd4bf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        Healix
      </a>

      <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="Doctors.php" class="active">Doctors</a>
        <a href="index.php#about">About</a>
        <a href="index.php#specialties">Specialties</a>
        <?php if ($isLoggedIn): ?>
          <?php
            if ($_SESSION['user_role'] === 'doctor') {
                $dashboardLinkMobile = 'doctor/dashboard.php';
            } else {
                $dashboardLinkMobile = 'patient/dashboard.php';
            }
          ?>
        <a href="<?php echo $dashboardLinkMobile; ?>" class="nav-links-dashboard">Dashboard</a>
        <?php endif; ?>
      </nav>

      <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
          <?php
            if ($_SESSION['user_role'] === 'doctor') {
                $dashboardLink = 'doctor/dashboard.php';
            } else {
                $dashboardLink = 'patient/dashboard.php';
            }
          ?>
          <a href="<?php echo $dashboardLink; ?>" class="btn btn-outline">Dashboard</a>
          <a href="logout.php" class="btn btn-solid">Logout</a>
        <?php else: ?>
          <a href="login.php" class="btn btn-outline">Login</a>
          <a href="register.php" class="btn btn-solid">Sign Up</a>
        <?php endif; ?>
      </div>
      <button class="nav-toggle" aria-label="Toggle navigation">
        <i class="fa-solid fa-bars"></i>
      </button>
    </div>
  </header>

  <main class="dd-page">
    <div class="container">

      <a href="Doctors.php" class="dd-back">
        <i class="fa-solid fa-arrow-left"></i> Back to Doctors
      </a>

      <?php if ($notice): ?>
        <div class="dd-alert">
          <?php echo htmlspecialchars($notice); ?>
          <a href="login.php">Log in</a>
        </div>
      <?php endif; ?>

      <!-- PROFILE CARD / ACTION CARD  -->
      <div class="dd-top-grid">

        <section class="dd-profile-card">
          <div class="dd-avatar-wrap">
            <img src="<?php echo htmlspecialchars($doctor['image']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" class="dd-avatar">
          </div>

          <div class="dd-profile-info">
            <span class="dd-specialty-badge" style="background:<?php echo $specColor; ?>33; color:<?php echo $specColor; ?>;">
              <?php echo htmlspecialchars($doctor['specialty_name']); ?>
            </span>

            <h1 class="dd-name"><?php echo htmlspecialchars($doctor['name']); ?></h1>
            <p class="dd-title"><?php echo htmlspecialchars(specialtyTitle($doctor['specialty_name'])); ?></p>

            <div class="dd-meta">
              <span class="dd-stars">
                <?php echo renderStars((float) $doctor['avg_rating']); ?>
                <strong><?php echo number_format((float) $doctor['avg_rating'], 1); ?></strong>
                <span class="dd-review-count">(<?php echo (int) $doctor['review_count']; ?> reviews)</span>
              </span>
              <span class="dd-meta-item"><i class="fa-solid fa-briefcase-medical"></i> <?php echo (int) $doctor['experience']; ?> Years Experience</span>
              <?php if (!empty($doctor['address'])): ?>
                <span class="dd-meta-item"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($doctor['address']); ?></span>
              <?php endif; ?>
            </div>

            <?php if (!empty($doctor['bio'])): ?>
              <p class="dd-bio"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></p>
            <?php endif; ?>
          </div>
        </section>

        <aside class="dd-action-card">
          <button type="button" class="btn-book" data-open-modal="book">
            <i class="fa-solid fa-calendar-check"></i> Book Appointment
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
          <button type="button" class="btn-outline-teal" data-open-modal="review">
            <i class="fa-regular fa-star"></i> Leave a Review
          </button>

          <div class="dd-quick-actions">
            <span class="dd-quick-action">
              <span class="dd-quick-icon"><i class="fa-solid fa-phone"></i></span> Call
            </span>
            <span class="dd-quick-action">
              <span class="dd-quick-icon"><i class="fa-solid fa-envelope"></i></span> Email
            </span>
            <span class="dd-quick-action">
              <span class="dd-quick-icon"><i class="fa-solid fa-location-dot"></i></span> Location
            </span>
          </div>
        </aside>
      </div>

      <!-- TABS  -->
      <div class="dd-tabs">
        <button type="button" class="dd-tab is-active" data-tab="schedule">
          <i class="fa-solid fa-calendar-days"></i> Schedule
        </button>
        <button type="button" class="dd-tab" data-tab="reviews">
          <i class="fa-regular fa-star"></i> Reviews <span class="dd-tab-count"><?php echo count($reviews); ?></span>
        </button>
      </div>

      <div class="dd-layout">

        <!--  LEFT: TAB PANELS -->
        <div class="dd-col">

          <!-- SCHEDULE -->
          <section class="dd-tab-panel is-active" data-tab-panel="schedule">
            <div class="dd-panel">
              <h2 class="dd-panel-title"><span><i class="fa-solid fa-calendar-days"></i> Weekly Schedule</span></h2>

              <?php if (empty($schedule)): ?>
                <p class="dd-empty">This doctor hasn't published a schedule yet.</p>
              <?php else: ?>
                <div class="dd-schedule-pro-grid">
                  <?php foreach ($schedule as $slot): ?>
                    <?php $isToday = $slot['day'] === $todayName; ?>
                    <div class="dd-schedule-pro-card<?php echo $isToday ? ' is-today' : ''; ?>" style="border-left-color:<?php echo $specColor; ?>;">
                      <?php if ($isToday): ?><span class="dd-schedule-pro-badge">Today</span><?php endif; ?>
                      <div class="dd-schedule-pro-day">
                        <span class="dd-schedule-pro-dayicon"><i class="fa-solid fa-calendar-day"></i></span>
                        <span class="dd-schedule-pro-dayname"><?php echo htmlspecialchars($slot['day']); ?></span>
                      </div>
                      <div class="dd-schedule-pro-time">
                        <i class="fa-regular fa-clock"></i>
                        <span><?php echo formatTime12($slot['start_time']); ?> – <?php echo formatTime12($slot['end_time']); ?></span>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <!-- REVIEWS -->
          <section class="dd-tab-panel" data-tab-panel="reviews">
            <div class="dd-panel">
              <h2 class="dd-panel-title">
                <span><i class="fa-solid fa-comments"></i> Patient Reviews (<?php echo count($reviews); ?>)</span>
                <button type="button" class="dd-panel-link" data-open-modal="review" style="background:none;border:none;cursor:pointer;">Leave a Review</button>
              </h2>

              <?php if (empty($reviews)): ?>
                <p class="dd-empty">No reviews yet be the first to review this doctor.</p>
              <?php else: ?>
                <div class="dd-reviews-grid">
                  <?php foreach ($reviews as $review): ?>
                    <div class="dd-review">
                      <div class="dd-review-head">
                        <span class="dd-review-avatar"><?php echo strtoupper(substr($review['patient_name'], 0, 1)); ?></span>
                        <div class="dd-review-headtext">
                          <span class="dd-review-author"><?php echo htmlspecialchars($review['patient_name']); ?></span>
                          <span class="dd-review-date"><?php echo timeAgo($review['created_at']); ?></span>
                        </div>
                      </div>
                      <div class="dd-review-stars"><?php echo renderStars((float) $review['rating']); ?></div>
                      <p class="dd-review-comment"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </section>

          <!-- STATS STRIP -->
          <div class="dd-stats-strip">
            <div class="dd-stat">
              <i class="fa-solid fa-calendar-check"></i>
              <div>
                <strong><?php echo (int) $doctor['experience']; ?>+</strong>
                <span>Years of Experience</span>
              </div>
            </div>
            <div class="dd-stat">
              <i class="fa-solid fa-comments"></i>
              <div>
                <strong><?php echo (int) $doctor['review_count']; ?></strong>
                <span>Patient Reviews</span>
              </div>
            </div>
            <div class="dd-stat">
              <i class="fa-solid fa-star"></i>
              <div>
                <strong><?php echo number_format((float) $doctor['avg_rating'], 1); ?></strong>
                <span>Average Rating</span>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT -->
        <div class="dd-col">

          <section class="dd-priority-card">
            <span class="dd-priority-icon"><i class="fa-solid fa-heart-pulse"></i></span>

            <h3>Your Health Is Our Priority</h3>
            <p>
              Book your appointment with <?php echo htmlspecialchars($doctor['name']); ?> in just a few clicks.
            </p>

            <ul class="dd-priority-list">
              <li><i class="fa-solid fa-circle-check"></i> Certified &amp; experienced specialists</li>
              <li><i class="fa-solid fa-circle-check"></i> Flexible scheduling that fits your day</li>
              <li><i class="fa-solid fa-circle-check"></i> Trusted by thousands of patients</li>
            </ul>

            <button type="button" class="btn-book dd-priority-cta" data-open-modal="book">
              <i class="fa-solid fa-calendar-check"></i> Book Your Visit
            </button>
          </section>
        </div>
      </div>
    </div>
  </main>

  <!-- ===================== MODAL: BOOK APPOINTMENT ===================== -->
  <div class="dd-modal-overlay" id="dd-modal-book">
    <div class="dd-modal">
      <button type="button" class="dd-modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      <h3 class="dd-modal-title"><i class="fa-solid fa-notes-medical"></i> Book Appointment</h3>
      <p class="dd-modal-subtitle">With <?php echo htmlspecialchars($doctor['name']); ?>  pick a date &amp; time below.</p>

      <?php if (!empty($bookErrors['general'])): ?>
        <div class="dd-alert"><?php echo htmlspecialchars($bookErrors['general']); ?></div>
      <?php endif; ?>

      <?php if (!$isLoggedIn): ?>
        <p class="dd-empty">Please <a href="login.php">log in</a> as a patient to book an appointment with this doctor.</p>
      <?php elseif (!$isPatient): ?>
        <p class="dd-empty">Only patient accounts can book appointments.</p>
      <?php elseif (empty($schedule)): ?>
        <p class="dd-empty">This doctor hasn't published a schedule yet, so booking isn't available.</p>
      <?php else: ?>
        <form action="doctor-details.php?id=<?php echo $doctorId; ?>#book" method="POST" class="dd-form">
          <input type="hidden" name="action" value="book">

          <div class="dd-form-row">
            <div class="dd-form-group">
              <label for="appointment_date">Date</label>
              <input
                type="date"
                id="appointment_date"
                name="appointment_date"
                min="<?php echo $today; ?>"
                value="<?php echo htmlspecialchars($bookOld['appointment_date']); ?>">
              <?php if (!empty($bookErrors['appointment_date'])): ?>
                <span class="field-error"><?php echo htmlspecialchars($bookErrors['appointment_date']); ?></span>
              <?php endif; ?>
            </div>

            <div class="dd-form-group">
              <label for="appointment_time">Time</label>
              <input
                type="time"
                id="appointment_time"
                name="appointment_time"
                value="<?php echo htmlspecialchars($bookOld['appointment_time']); ?>">
              <?php if (!empty($bookErrors['appointment_time'])): ?>
                <span class="field-error"><?php echo htmlspecialchars($bookErrors['appointment_time']); ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="dd-form-group">
            <label for="reason">Reason for visit <span class="dd-optional">(optional)</span></label>
            <textarea id="reason" name="reason" rows="3" maxlength="255" placeholder="Briefly describe why you're booking..."><?php echo htmlspecialchars($bookOld['reason']); ?></textarea>
            <?php if (!empty($bookErrors['reason'])): ?>
              <span class="field-error"><?php echo htmlspecialchars($bookErrors['reason']); ?></span>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn-book dd-submit">Request Appointment</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!--LEAVE A REVIEW -->
  <div class="dd-modal-overlay" id="dd-modal-review">
    <div class="dd-modal">
      <button type="button" class="dd-modal-close" data-close-modal aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
      <h3 class="dd-modal-title"><i class="fa-solid fa-star"></i> Leave a Review</h3>
      <p class="dd-modal-subtitle">Share your experience with <?php echo htmlspecialchars($doctor['name']); ?>.</p>

      <?php if (!empty($reviewErrors['general'])): ?>
        <div class="dd-alert"><?php echo htmlspecialchars($reviewErrors['general']); ?></div>
      <?php endif; ?>

      <?php if (!$isLoggedIn): ?>
        <p class="dd-empty">Please <a href="login.php">log in</a> as a patient to leave a review.</p>
      <?php elseif (!$isPatient): ?>
        <p class="dd-empty">Only patient accounts can leave reviews.</p>
      <?php else: ?>
        <form action="doctor-details.php?id=<?php echo $doctorId; ?>#reviews" method="POST" class="dd-form">
          <input type="hidden" name="action" value="review">

          <div class="dd-form-group">
            <label>Rating</label>
            <div class="dd-star-input">
              <?php for ($i = 5; $i >= 1; $i--): ?>
                <input type="radio" id="rating<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" <?php echo ((string) $reviewOld['rating'] === (string) $i) ? 'checked' : ''; ?>>
                <label for="rating<?php echo $i; ?>" title="<?php echo $i; ?> stars"><i class="fa-solid fa-star"></i></label>
              <?php endfor; ?>
            </div>
            <?php if (!empty($reviewErrors['rating'])): ?>
              <span class="field-error"><?php echo htmlspecialchars($reviewErrors['rating']); ?></span>
            <?php endif; ?>
          </div>

          <div class="dd-form-group">
            <label for="comment">Comment</label>
            <textarea id="comment" name="comment" rows="3" maxlength="1000" placeholder="Share your experience with this doctor..."><?php echo htmlspecialchars($reviewOld['comment']); ?></textarea>
            <?php if (!empty($reviewErrors['comment'])): ?>
              <span class="field-error"><?php echo htmlspecialchars($reviewErrors['comment']); ?></span>
            <?php endif; ?>
          </div>

          <button type="submit" class="btn-book dd-submit">Submit Review</button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="container footer-top">
      <div class="footer-brand">
        <a href="index.php" class="nav-logo footer-logo">
          <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 33s-11-6.8-15-14C2.2 13.6 4.5 8 10 8c3.3 0 5.6 1.8 7 3.9C18.4 9.8 20.7 8 24 8c5.5 0 7.8 5.6 5 11-4 7.2-9 14-9 14Z"
                  stroke="#2dd4bf" stroke-width="2.4" stroke-linejoin="round"/>
            <path d="M6 20h6l2.5-6L18 26l3-11 2 5h9" stroke="#2dd4bf" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Healix
        </a>
        <p class="footer-tagline">
          Making healthcare simple find the right doctor, book in
          minutes, and get the care you need, when you need it.
        </p>
      </div>

      <div class="footer-col">
        <h4>Quick Links</h4>
        <a href="index.php">Home</a>
        <a href="Doctors.php">Doctors</a>
        <a href="index.php#about">About</a>
        <a href="index.php#specialties">Specialties</a>
      </div>

      <div class="footer-col">
        <h4>For Patients</h4>
        <a href="register.php">Create Account</a>
        <a href="login.php">Login</a>
        <a href="Doctors.php">Find a Doctor</a>
      </div>

      <div class="footer-col">
        <h4>Contact</h4>
        <span class="footer-static">+20 100 000 0000</span>
        <span class="footer-static">support@healix.app</span>
        <span class="footer-static">Cairo, Egypt</span>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="container footer-bottom-inner">
        <p>&copy; <?php echo date('Y'); ?> Healix. All rights reserved.</p>
      </div>
    </div>
  </footer>

  <script src="js/script.js"></script>
  <script src="js/doctor-details.js"></script>
</body>
</html>