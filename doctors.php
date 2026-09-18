<?php


session_start();
require_once __DIR__ . '/database.php';

$search = trim($_GET['q'] ?? '');

$selectedSpecialties = [];
if (!empty($_GET['specialty']) && is_array($_GET['specialty'])) {
    $selectedSpecialties = array_values(array_unique(array_map('intval', $_GET['specialty'])));
}

$perPage = 8;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$specialties = [];
$specResult = mysqli_query($conn, "
    SELECT s.id, s.name, COUNT(d.id) AS doctor_count
    FROM specialties s
    LEFT JOIN doctors d ON d.specialty_id = s.id
    GROUP BY s.id, s.name
    ORDER BY s.name ASC
");
while ($row = mysqli_fetch_assoc($specResult)) {
    $specialties[] = [
        'id'    => (int) $row['id'],
        'name'  => $row['name'],
        'count' => (int) $row['doctor_count'],
    ];
}


$visibleSpecialtyLimit = 6;
$hasHiddenSpecialties  = count($specialties) > $visibleSpecialtyLimit;

$where  = [];
$params = [];
$types  = '';

if ($search !== '') {
    $where[]  = '(u.name LIKE ? OR s.name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
    $types   .= 'ss';
}

if (!empty($selectedSpecialties)) {
    $placeholders = implode(',', array_fill(0, count($selectedSpecialties), '?'));
    $where[] = "d.specialty_id IN ($placeholders)";
    foreach ($selectedSpecialties as $specialtyId) {
        $params[] = $specialtyId;
        $types   .= 'i';
    }
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';


$orderSql = 'ORDER BY avg_rating DESC, d.experience DESC, u.name ASC';

$countSql = "
    SELECT COUNT(*)
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    $whereSql
";
$countStmt = mysqli_prepare($conn, $countSql);
if ($types !== '') {
    mysqli_stmt_bind_param($countStmt, $types, ...$params);
}
mysqli_stmt_execute($countStmt);
$totalDoctors = (int) (mysqli_fetch_row(mysqli_stmt_get_result($countStmt))[0] ?? 0);
mysqli_stmt_close($countStmt);

$totalPages = max(1, (int) ceil($totalDoctors / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;


$sql = "
    SELECT
        d.id, d.experience, d.address, d.image,
        u.name,
        s.name AS specialty_name,
        COALESCE(ROUND(AVG(r.rating), 1), 0) AS avg_rating,
        COUNT(DISTINCT r.id) AS review_count
    FROM doctors d
    JOIN users u ON u.id = d.user_id
    JOIN specialties s ON s.id = d.specialty_id
    LEFT JOIN reviews r ON r.doctor_id = d.id
    $whereSql
    GROUP BY d.id, d.experience, d.address, d.image, u.name, s.name
    $orderSql
    LIMIT ? OFFSET ?
";
$stmt = mysqli_prepare($conn, $sql);
$listTypes  = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
mysqli_stmt_bind_param($stmt, $listTypes, ...$listParams);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$doctors = [];
while ($row = mysqli_fetch_assoc($result)) {
    $doctors[] = $row;
}
mysqli_stmt_close($stmt);


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

function doctorsPageUrl(int $page): string
{
    $params = $_GET;
    unset($params['ajax']); // pagination links are plain <a href>, not AJAX
    $params['page'] = $page;
    return 'Doctors.php?' . http_build_query($params);
}

// DOCTOR-GRID / PAGINATION FRAGMENT
function renderDoctorsGrid(array $doctors, int $totalDoctors, int $totalPages, int $page): void
{
    if (empty($doctors)): ?>
      <div class="doctors-empty">
        <p>No doctors match your search or filters right now.</p>
        <button type="button" class="btn-outline-teal" id="emptyClearFilters">Clear Filters</button>
      </div>
    <?php else: ?>
      <div class="doctor-grid">
        <?php foreach ($doctors as $doctor): ?>
          <?php
            $color = specialtyColor($doctor['specialty_name']);
            $hasReviews = (int) $doctor['review_count'] > 0;
          ?>
          <article class="doctor-card">
            <button type="button" class="doctor-fav" aria-label="Save doctor">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-6.7-4.2-9.3-8.6C.8 9 2.3 5 6.2 5c2.2 0 3.7 1.2 4.7 2.6C11.9 6.2 13.4 5 15.6 5c3.9 0 5.4 4 3.5 7.4C16.5 16.8 12 21 12 21Z"/></svg>
            </button>

            <div class="doctor-card-top">
              <img src="<?php echo htmlspecialchars($doctor['image']); ?>" alt="<?php echo htmlspecialchars($doctor['name']); ?>" class="doctor-avatar">
              <span class="doctor-specialty-badge" style="background:<?php echo $color; ?>1a; color:<?php echo $color; ?>;">
                <?php echo htmlspecialchars($doctor['specialty_name']); ?>
              </span>
            </div>

            <h3 class="doctor-name"><?php echo htmlspecialchars($doctor['name']); ?></h3>
            <p class="doctor-title"><?php echo htmlspecialchars(specialtyTitle($doctor['specialty_name'])); ?></p>

            <p class="doctor-rating">
              <?php if ($hasReviews): ?>
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 17.3 6.2 20l1.1-6.5L2.5 8.9l6.5-.9L12 2l3 6 6.5.9-4.8 4.6L17.8 20 12 17.3Z"/></svg>
                <?php echo number_format((float) $doctor['avg_rating'], 1); ?>
                <span class="doctor-review-count">(<?php echo (int) $doctor['review_count']; ?> reviews)</span>
              <?php else: ?>
                <span class="doctor-review-count">No reviews yet</span>
              <?php endif; ?>
            </p>

            <p class="doctor-location">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.3-7-11.5A7 7 0 0 1 19 9.5C19 15.7 12 21 12 21Z" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4"/></svg>
              <?php echo htmlspecialchars($doctor['address'] ?? ''); ?> · <?php echo (int) $doctor['experience']; ?> yrs exp.
            </p>

            <a href="doctor-details.php?id=<?php echo $doctor['id']; ?>" class="btn-book">
              View Doctor
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="doctors-pagination" aria-label="Doctors pagination">
          <a href="<?php echo $page > 1 ? doctorsPageUrl($page - 1) : '#'; ?>"
             class="page-arrow <?php echo $page <= 1 ? 'is-disabled' : ''; ?>" aria-label="Previous page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m15 6-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>

          <?php
            $windowStart = max(1, $page - 2);
            $windowEnd   = min($totalPages, $windowStart + 4);
            $windowStart = max(1, $windowEnd - 4);
          ?>
          <?php for ($p = $windowStart; $p <= $windowEnd; $p++): ?>
            <a href="<?php echo doctorsPageUrl($p); ?>" class="page-number <?php echo $p === $page ? 'is-active' : ''; ?>"><?php echo $p; ?></a>
          <?php endfor; ?>

          <a href="<?php echo $page < $totalPages ? doctorsPageUrl($page + 1) : '#'; ?>"
             class="page-arrow <?php echo $page >= $totalPages ? 'is-disabled' : ''; ?>" aria-label="Next page">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </a>
        </nav>
      <?php endif; ?>
    <?php endif;
}

if (isset($_GET['ajax'])) {
    header('Content-Type: text/html; charset=utf-8');
    renderDoctorsGrid($doctors, $totalDoctors, $totalPages, $page);
    return;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Find a Doctor — Healix</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Caveat:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/all.min.css">
  <link rel="stylesheet" href="css/doctors.css">
</head>
<body class="doctors-page">
   <div class="pic_idea">  
  <!-- NAVBAR  -->
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

  <form action="Doctors.php" method="GET" id="doctorsForm">

    <!-- HERO  -->
    <section class="doctors-hero">
      <div class="container doctors-hero-content">
        <span class="doctors-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14" stroke-linecap="round"/></svg>
          Our Doctors
        </span>

        <h1 class="doctors-title">
          Find the Right Doctor<br>
          for <span class="teal">Your Health</span>
        </h1>
      </div>

      <div class="container doctors-search-wrap">
        <div class="doctors-search">
          <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>
          </svg>
          <input type="text" name="q" id="doctorSearchInput" placeholder="Search by doctor name..." value="<?php echo htmlspecialchars($search); ?>">
          <button type="submit" class="doctors-search-btn">
            Search
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </button>
        </div>
      </div>
    </section>

    <!--CONTENT  -->
    <section class="doctors-content">
      <div class="container doctors-layout">

        <!-- SIDEBAR: SPECIALTY  -->
        <aside class="doctors-filters">
          <div class="filters-card">
            <p class="filters-group-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.3-7-11.5A7 7 0 0 1 19 9.5C19 15.7 12 21 12 21Z" stroke-linejoin="round"/><circle cx="12" cy="9.5" r="2.4"/></svg>
              Specialty
            </p>

            <div class="specialty-checkboxes">
              <?php foreach ($specialties as $i => $specialty): ?>
                <label class="specialty-check <?php echo $i >= $visibleSpecialtyLimit ? 'specialty-check--extra' : ''; ?>">
                  <input
                    type="checkbox"
                    name="specialty[]"
                    value="<?php echo $specialty['id']; ?>"
                    <?php echo in_array($specialty['id'], $selectedSpecialties, true) ? 'checked' : ''; ?>>
                  <span><?php echo htmlspecialchars($specialty['name']); ?></span>
                  <span class="specialty-count"><?php echo $specialty['count']; ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <?php if ($hasHiddenSpecialties): ?>
              <button type="button" class="filters-view-more" id="viewMoreSpecialties">
                View More
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            <?php endif; ?>

            <button type="button" class="filters-clear" id="clearFiltersBtn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7M3 4v5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              Clear Filters
            </button>
          </div>
        </aside>

        <div class="doctors-results" id="doctorsResults">
          <?php renderDoctorsGrid($doctors, $totalDoctors, $totalPages, $page); ?>
        </div>
      </div>
    </section>
  </form>

  </div>

  <!--  FOOTER -->
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
        <a href="Doctors.php" class="active">Doctors</a>
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
  <script>
    (function () {
      const form        = document.getElementById('doctorsForm');
      const resultsWrap = document.getElementById('doctorsResults');
      const searchInput = document.getElementById('doctorSearchInput');

     
      function loadResults(queryString, updateUrl = true) {
        resultsWrap.classList.add('is-loading');

        fetch('Doctors.php?ajax=1&' + queryString)
          .then((r) => r.text())
          .then((html) => {
            resultsWrap.innerHTML = html;
            resultsWrap.classList.remove('is-loading');
            bindResultEvents();
          })
          .catch(() => {
            resultsWrap.classList.remove('is-loading');
          });

        if (updateUrl) {
          history.pushState(null, '', 'Doctors.php?' + queryString);
        }
      }

  
      function currentFilterQuery() {
        const params = new URLSearchParams(new FormData(form));
        params.set('page', '1');
        return params.toString();
      }

     
      form.querySelectorAll('input[name="specialty[]"]').forEach((checkbox) => {
        checkbox.addEventListener('change', () => loadResults(currentFilterQuery()));
      });

     
      form.addEventListener('submit', (e) => {
        e.preventDefault();
        loadResults(currentFilterQuery());
      });

   
      function bindPagination() {
        resultsWrap.querySelectorAll('.doctors-pagination a:not(.is-disabled)').forEach((link) => {
          link.addEventListener('click', (e) => {
            e.preventDefault();
            const url = new URL(link.href, window.location.href);
            loadResults(url.searchParams.toString());
            resultsWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        });
      }

      function bindFavButtons() {
        resultsWrap.querySelectorAll('.doctor-fav').forEach((btn) => {
          btn.addEventListener('click', () => btn.classList.toggle('is-active'));
        });
      }
      function bindClearInsideResults() {
        const emptyClear = resultsWrap.querySelector('#emptyClearFilters');
        emptyClear?.addEventListener('click', clearFilters);
      }

      function bindResultEvents() {
        bindPagination();
        bindFavButtons();
        bindClearInsideResults();
      }

      function clearFilters() {
        form.querySelectorAll('input[name="specialty[]"]').forEach((cb) => { cb.checked = false; });
        searchInput.value = '';
        loadResults('page=1');
      }

      document.getElementById('clearFiltersBtn')?.addEventListener('click', clearFilters);
      window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        loadResults(params.toString(), false);
      });
      const viewMoreBtn = document.getElementById('viewMoreSpecialties');
      viewMoreBtn?.addEventListener('click', () => {
        document.querySelectorAll('.specialty-check--extra').forEach((el) => {
          el.style.display = 'flex';
        });
        viewMoreBtn.style.display = 'none';
      });

      bindResultEvents();
    })();
  </script>
</body>
</html>