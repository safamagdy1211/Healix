<?php

session_start();
require_once __DIR__ . '/database.php';


$doctorsCount     = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM doctors"))[0] ?? 0);
$appointmentsCount = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM appointments"))[0] ?? 0);
$patientsCount    = (int) (mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE role = 'patient'"))[0] ?? 0);
$avgRatingRow     = mysqli_fetch_row(mysqli_query($conn, "SELECT ROUND(AVG(rating), 1) FROM reviews"));
$avgRating        = $avgRatingRow && $avgRatingRow[0] !== null ? (float) $avgRatingRow[0] : 0.0;

$specialtyTaglines = [
    'Cardiology'    => 'Heart care, for a healthier tomorrow',
    'Dentistry'     => 'Healthy smiles, confident you',
    'Dermatology'   => 'Radiant skin, healthy glow',
    'Pediatrics'    => 'Healthy childhood, brighter future',
    'Ophthalmology' => 'Clear vision, better living',
    'Orthopedics'   => 'Strong bones, active you',
];

$specialties = [];
$specialtiesResult = mysqli_query($conn, "
    SELECT s.id, s.name, COUNT(d.id) AS doctor_count
    FROM specialties s
    LEFT JOIN doctors d ON d.specialty_id = s.id
    GROUP BY s.id, s.name
    ORDER BY s.id
");
while ($row = mysqli_fetch_assoc($specialtiesResult)) {
    $name = $row['name'];
    $specialties[] = [
        'id'      => (int) $row['id'],
        'name'    => $name,
        'icon'    => strtolower($name),
        'tagline' => $specialtyTaglines[$name] ?? 'Expert care, tailored for you',
        'count'   => (int) $row['doctor_count'],
    ];
}
$specialtiesJson = json_encode(
    $specialties,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Healix </title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/all.min.css">
</head>
<body>

  <!--  NAVBAR  -->
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
        <a href="#home" class="active">Home</a>
        <a href="doctors.php">Doctors</a>
        <a href="#about">About</a>
        <a href="#specialties">Specialties</a>
  <?php if (isset($_SESSION['user_id'])): ?>
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
  <?php if (isset($_SESSION['user_id'])): ?>
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

  <!-- HERO -->
  <section class="hero" id="home">
    <div class="hero-content">

      <div class="hero-badge-wrap">
        <span class="hero-badge">
          <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-6.7-4.2-9.3-8.6C.8 9 2.3 5 6.2 5c2.2 0 3.7 1.2 4.7 2.6C11.9 6.2 13.4 5 15.6 5c3.9 0 5.4 4 3.5 7.4C16.5 16.8 12 21 12 21Z"/></svg>
          Care That Comes First
          <span class="chevron">›</span>
        </span>
        <span class="hero-badge-dashes"><span></span><span></span></span>
      </div>

      <p class="hero-eyebrow">Your Health, Our Priority</p>

      <h1 class="hero-title">
        Book Your Doctor Appointment with
        <span class="script">
          Ease
          <svg class="sparkle" viewBox="0 0 24 24" fill="none"><path d="M12 2l1.4 5.6L19 9l-5.6 1.4L12 16l-1.4-5.6L5 9l5.6-1.4L12 2Z" fill="currentColor"/></svg>
          <svg class="underline-doodle" viewBox="0 0 200 20" preserveAspectRatio="none" fill="none">
            <path d="M2 12c30-10 60-10 90-4s70 4 106-6" stroke="currentColor" stroke-width="4" stroke-linecap="round"/>
          </svg>
        </span>
      </h1>

      <p class="hero-subtitle">
        Find the right doctor, choose your preferred time, and take the
        first step towards a healthier you.
      </p>

      <form class="hero-search" action="doctors.php" method="GET">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>
        </svg>
        <input type="text" name="q" placeholder="Search by doctor name, specialty...">
        <button type="submit" class="hero-search-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>
          </svg>
          Find Doctors
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
      </form>

      <div class="hero-stats">
        <div class="hero-stat">
          <span class="hero-stat-number counter" data-target="<?php echo $doctorsCount; ?>" data-suffix="+">0</span>
          <span class="hero-stat-label">Trusted Doctors</span>
        </div>
        <span class="hero-stat-divider"></span>

        <div class="hero-stat">
          <span class="hero-stat-number counter" data-target="<?php echo $appointmentsCount; ?>" data-suffix="+">0</span>
          <span class="hero-stat-label">Appointments Booked</span>
        </div>
        <span class="hero-stat-divider"></span>

        <div class="hero-stat">
          <span class="hero-stat-number counter" data-target="<?php echo $avgRating; ?>" data-decimals="1">0</span>
          <span class="hero-stat-label">Average Rating</span>
        </div>
        <span class="hero-stat-divider"></span>

        <div class="hero-stat">
          <span class="hero-stat-number counter" data-target="<?php echo $patientsCount; ?>" data-suffix="+">0</span>
          <span class="hero-stat-label">Happy Patients</span>
        </div>
      </div>
    </div>

  </section>

  <!-- ABOUT  -->
  <section class="about-section" id="about">
    <div class="container">
      <div class="about-card">

        <p class="about-eyebrow">
          <span class="dash-line"></span>
          <span class="about-eyebrow-text">
            About
            <span class="underline-word">
              Healix
              <svg class="underline-doodle" viewBox="0 0 120 18" preserveAspectRatio="none" fill="none">
                <path d="M2 10c18-7 36-7 56-2s44 2 60-6" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/>
              </svg>
            </span>
          </span>
          <span class="dash-line"></span>
        </p>

        <h2 class="about-title">
          Your Health<br>
          <span class="teal">Our Mission</span>
        </h2>

        <p class="about-text">
          Healix is a modern doctor appointment system designed to make your
          healthcare journey easier, faster, and more convenient. We connect
          you with trusted doctors, simplify the booking process, and ensure
          you get the care you need when you need it.
        </p>

        <div class="about-divider"></div>

        <div class="about-tabs" role="tablist">
          <button type="button" class="about-tab active" role="tab" aria-selected="true" data-tab="who-we-are">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" stroke-linecap="round"/><path d="M16 8.3c1.4.3 2.5 1.4 2.5 3s-1.1 2.7-2.5 3M21.5 20c0-3-2.1-5.2-5-5.8" stroke-linecap="round"/></svg>
            Who We Are
          </button>
          <button type="button" class="about-tab" role="tab" aria-selected="false" data-tab="what-we-offer">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v6a4 4 0 0 0 8 0V4" stroke-linecap="round"/><path d="M4 4h4M12 4h4" stroke-linecap="round"/><path d="M18 10v2a6 6 0 0 1-12 0" stroke-linecap="round"/><circle cx="19" cy="8.5" r="2"/></svg>
            What We Offer
          </button>
          <button type="button" class="about-tab" role="tab" aria-selected="false" data-tab="our-promise">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v5c0 4.6-3 8.4-7 10-4-1.6-7-5.4-7-10V6l7-3Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Our Promise
          </button>
        </div>

        <div class="about-tab-panels">
          <p class="about-tab-panel active" data-panel="who-we-are">
            We are a team of passionate healthcare professionals and technology
            experts, united by a simple goal: to make quality healthcare accessible
            to everyone. We believe that better care leads to healthier lives,
            and we're here to make that happen.
          </p>
          <p class="about-tab-panel" data-panel="what-we-offer">
            From finding the right specialist to picking a time that works for
            you, Healix brings search, scheduling, and reminders into one place 
            so doctors can focus on patients, and patients can focus on getting
            better.
          </p>
          <p class="about-tab-panel" data-panel="our-promise">
            Every doctor on Healix is verified, every booking is confirmed in
            real time, and your health information stays private and secure.
            That's the standard of care we promise, every single time.
          </p>
        </div>

        <a href="doctors.php" class="btn btn-solid about-cta">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" stroke-linecap="round"/><path d="M16 8.3c1.4.3 2.5 1.4 2.5 3s-1.1 2.7-2.5 3M21.5 20c0-3-2.1-5.2-5-5.8" stroke-linecap="round"/></svg>
          Discover Our Doctors
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </a>
      </div>
    </div>
  </section>

  <!-- TRUST MARQUEE -->
  <div class="marquee-section" aria-hidden="true">
    <div class="marquee-track">
      <div class="marquee-group">
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9"/></svg>
          Available Around the Clock<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 17.3 6.2 20l1.1-6.5L2.5 8.9l6.5-.9L12 2l3 6 6.5.9-4.8 4.6L17.8 20 12 17.3Z" stroke-linejoin="round"/></svg>
          Top Rated Doctors<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v6a4 4 0 0 0 8 0V4" stroke-linecap="round"/><path d="M18 10v2a6 6 0 0 1-12 0" stroke-linecap="round"/><circle cx="19" cy="8.5" r="2"/></svg>
          Wide Range of Specialties<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v5c0 4.6-3 8.4-7 10-4-1.6-7-5.4-7-10V6l7-3Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Verified &amp; Trusted Care<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
          Easy Online Booking<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" stroke-linecap="round"/><path d="M16 8.3c1.4.3 2.5 1.4 2.5 3s-1.1 2.7-2.5 3M21.5 20c0-3-2.1-5.2-5-5.8" stroke-linecap="round"/></svg>
          Trusted by 50,000+ Patients<span class="marquee-dot"></span>
        </span>
      </div>
      <div class="marquee-group">
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="9"/></svg>
          Available Around the Clock<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 17.3 6.2 20l1.1-6.5L2.5 8.9l6.5-.9L12 2l3 6 6.5.9-4.8 4.6L17.8 20 12 17.3Z" stroke-linejoin="round"/></svg>
          Top Rated Doctors<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 4v6a4 4 0 0 0 8 0V4" stroke-linecap="round"/><path d="M18 10v2a6 6 0 0 1-12 0" stroke-linecap="round"/><circle cx="19" cy="8.5" r="2"/></svg>
          Wide Range of Specialties<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3l7 3v5c0 4.6-3 8.4-7 10-4-1.6-7-5.4-7-10V6l7-3Z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
          Verified &amp; Trusted Care<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
          Easy Online Booking<span class="marquee-dot"></span>
        </span>
        <span class="marquee-item">
          <svg class="marquee-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3.2"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6" stroke-linecap="round"/><path d="M16 8.3c1.4.3 2.5 1.4 2.5 3s-1.1 2.7-2.5 3M21.5 20c0-3-2.1-5.2-5-5.8" stroke-linecap="round"/></svg>
          Trusted by 50,000+ Patients<span class="marquee-dot"></span>
        </span>
      </div>
    </div>
  </div>

  <!-- SPECIALTIES -->
  <section class="specialties-section" id="specialties">
    <svg class="specialties-arc" viewBox="0 0 1300 110" preserveAspectRatio="none" fill="none">
      <path d="M10 90 C 350 10, 950 10, 1290 90" stroke="rgba(15,42,69,0.15)" stroke-width="1.5" stroke-linecap="round"/>
      <circle cx="10" cy="90" r="6" fill="#0d9488"/>
      <circle cx="1290" cy="90" r="6" fill="#0d9488"/>
    </svg>

    <div class="container">
      <div class="specialties-eyebrow">
        <span class="line"></span>
        <span class="pill">Specialties</span>
        <span class="line"></span>
      </div>

      <h2 class="specialties-title">
        Find the Right Doctor<br>
        for <span class="teal">Your Needs</span>
      </h2>

      <p class="specialties-subtitle">
        Choose a specialty and explore our team of experienced doctors, ready to help you live a healthier life.
      </p>

      <div class="specialties-carousel">
        <button type="button" class="carousel-arrow prev" id="specialties-prev" aria-label="Previous specialty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="m15 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>

        <div class="specialties-track" id="specialties-track"></div>

        <button type="button" class="carousel-arrow next" id="specialties-next" aria-label="Next specialty">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
      </div>

      <div class="specialties-cta">
        <a href="doctors.php" class="btn-outline-teal">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>
            <rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
          </svg>
          View All Specialties
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </a>
      </div>
    </div>

    <script type="application/json" id="specialties-data"><?php echo $specialtiesJson; ?></script>
  </section>

  
  <!--  FOOTER  -->
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
        <div class="footer-socials">
          <a href="#" class="footer-social" aria-label="Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 21v-7.5h2.5l.4-3H13.5V8.4c0-.87.24-1.46 1.5-1.46h1.6V3.9c-.28-.04-1.22-.12-2.32-.12-2.3 0-3.88 1.4-3.88 3.97V10.5H8v3h2.4V21h3.1Z"/></svg>
          </a>
          <a href="#" class="footer-social" aria-label="Instagram">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
          <a href="#" class="footer-social" aria-label="X / Twitter">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="m4 4 7 8.4L4.4 20H7l5.4-5.9L17 20h3l-7.3-8.8L19.4 4H17l-5 5.5L8 4H4Z"/></svg>
          </a>
          <a href="#" class="footer-social" aria-label="LinkedIn">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM3.3 9.6h3.4V21H3.3V9.6Zm6.4 0h3.25v1.56h.05c.45-.85 1.56-1.75 3.2-1.75 3.42 0 4.05 2.25 4.05 5.18V21H16.9v-5.7c0-1.36-.02-3.1-1.9-3.1-1.9 0-2.19 1.48-2.19 3v5.8H9.7V9.6Z"/></svg>
          </a>
        </div>
      </div>

      <div class="footer-col">
        <h4>Quick Links</h4>
        <a href="#home" class="active">Home</a>
        <a href="doctors.php">Doctors</a>
        <a href="#about">About</a>
        <a href="#specialties">Specialties</a>
      </div>

      <div class="footer-col">
        <h4>For Patients</h4>
        <a href="register.php">Create Account</a>
        <a href="login.php">Login</a>
        <a href="doctors.php">Find a Doctor</a>
      </div>

      <div class="footer-col">
        <h4>Contact</h4>
        <span class="footer-static">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5c0 8.3 6.7 15 15 15l2-3.6c.2-.4.1-.9-.3-1.2l-3.6-2.7c-.4-.3-.9-.2-1.2.1l-1.4 1.6a11.4 11.4 0 0 1-5.7-5.7l1.6-1.4c.3-.3.4-.8.1-1.2L7.8 2.3C7.5 1.9 7 1.8 6.6 2L4 5Z" stroke-linejoin="round"/></svg>
          +20 100 000 0000
        </span>
        <span class="footer-static">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2.5"/><path d="m4 7 8 6 8-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          support@healix.app
        </span>
        <span class="footer-static">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-5.3-7-11.5A7 7 0 0 1 19 9.5C19 15.7 12 21 12 21Z" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4"/></svg>
          Cairo, Egypt
        </span>
      </div>
    </div>

    <div class="footer-bottom">
      <div class="container footer-bottom-inner">
        <p>&copy; <?php echo date('Y'); ?> Healix. All rights reserved.</p>
        <div class="footer-legal">
          <a href="#">Privacy Policy</a>
          <a href="#">Terms of Service</a>
        </div>
      </div>
    </div>
  </footer>

 <script src="js/script.js"></script>
</body>
</html>