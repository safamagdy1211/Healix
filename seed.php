<?php

require_once __DIR__ . '/database.php';


// Specialties / doctors
$data = [
    'Cardiology' => [
        'doctors' => [
            ['Dr. Ahmed Hassan', 'images/Cardiology/Dr._Ahmed_Hassan.png', 12, 'Cairo', 'Specialises in heart rhythm disorders and preventive cardiology.'],
            ['Dr. Salma Hassan', 'images/Cardiology/Dr. Salma Hassan.png', 8,  'Giza',  'Focused on non-invasive cardiac imaging and patient education.'],
            ['Dr. Omar Khaled',  'images/Cardiology/Dr. Omar Khaled.png',  15, 'Cairo', 'Senior interventional cardiologist with two decades in the field.'],
            ['Dr. Mariam Ali',   'images/Cardiology/Dr._Mariam_Ali.png',   6,  'Alexandria', 'Works closely with patients managing hypertension and cholesterol.'],
        ],
    ],
    'Dentistry' => [
        'doctors' => [
            ['Dr. Sara Khalil',   'images/Dentistry/Dr. Sara Khalil.png',   9,  'Cairo', 'General dentistry with a focus on gentle, anxiety-free care.'],
            ['Dr. Youssef Adel',  'images/Dentistry/Dr. Youssef Adel.png',  11, 'Giza',  'Experienced in cosmetic dentistry and dental implants.'],
            ['Dr. Mohamed Samir', 'images/Dentistry/Dr. Mohamed Samir.webp', 7, 'Cairo', 'Specialises in orthodontics for children and adults.'],
            ['Dr. Nour Ahmed',    'images/Dentistry/Dr. Nour Ahmed.webp',    5, '6th of October', 'Passionate about preventive dental care and hygiene.'],
        ],
    ],
    'Dermatology' => [
        'doctors' => [
            ['Dr. Karim Mostafa', 'images/Dermatology/Dr. Karim Mostafa.png', 10, 'Cairo', 'Treats acne, eczema, and general skin health concerns.'],
            ['Dr. Jana Mahmoud',  'images/Dermatology/Dr. Jana Mahmoud.png',  6,  'Alexandria', 'Focused on cosmetic dermatology and skin care routines.'],
            ['Dr. Adam Nabil',    'images/Dermatology/Dr. Adam Nabil.png',    13, 'Giza', 'Specialist in chronic skin conditions and dermatologic surgery.'],
            ['Dr. Laila Ahmed',   'images/Dermatology/Dr. Laila Ahmed.png',   8,  'Cairo', 'Focuses on pediatric and adult dermatology cases.'],
        ],
    ],
    'Pediatrics' => [
        'doctors' => [
            ['Dr. Amr Mohamed',   'images/Pediatrics/Dr. Amr Mohamed.png',   14, 'Cairo', 'Two decades caring for newborns through adolescents.'],
            ['Dr. Aya Mostafa',   'images/Pediatrics/Dr. Aya Mostafa.png',   7,  'Giza',  'Focused on childhood nutrition and developmental checkups.'],
            ['Dr. Menna Khaled',  'images/Pediatrics/Dr. Menna Khaled.png',  9,  'Alexandria', 'Experienced in pediatric vaccination programs.'],
            ['Dr. Tamer Hassan',  'images/Pediatrics/Dr. Tamer Hassan.png',  11, 'Cairo', 'Specialises in respiratory conditions in children.'],
        ],
    ],
    'Ophthalmology' => [
        'doctors' => [
            ['Dr. Ali Tarek',    'images/Ophthalmology/Dr. Ali Tarek.png',    10, 'Cairo', 'Specialist in vision correction and cataract surgery.'],
            ['Dr. Hala Samir',   'images/Ophthalmology/Dr. Hala Samir.png',   6,  'Giza',  'Focused on pediatric eye care and screening.'],
            ['Dr. Reem Khaled',  'images/Ophthalmology/Dr. Reem Khaled.png',  12, 'Alexandria', 'Experienced in treating glaucoma and retinal conditions.'],
            ['Dr. Ziad Ahmed',   'images/Ophthalmology/Dr. Ziad Ahmed.png',   8,  'Cairo', 'General ophthalmology with a focus on eye strain and screen health.'],
        ],
    ],
    'Orthopedics' => [
        'doctors' => [
            ['Dr. Mahmoud Ali',  'images/Orthopedics/Dr. Mahmoud Ali.png',   13, 'Cairo', 'Specialises in sports injuries and joint reconstruction.'],
            ['Dr. Dina Adel',    'images/Orthopedics/Dr. Dina Adel.png',     7,  'Giza',  'Focused on spine care and physical rehabilitation.'],
            ['Dr. Hossam Samir', 'images/Orthopedics/Dr. Hossam Samir.png',  16, 'Cairo', 'Senior orthopedic surgeon specialising in hip and knee replacement.'],
            ['Dr. Farah Omar',   'images/Orthopedics/Dr. Farah Omar.png',    5,  'Alexandria', 'Works with patients recovering from fractures and injuries.'],
        ],
    ],
];

$doctorPassword = 'Doctor@123'; 
$phoneCounter   = 1;


// Weekly schedule patterns
$schedulePatterns = [
    [ ['Saturday', '09:00', '14:00'], ['Monday',   '09:00', '14:00'], ['Wednesday', '09:00', '14:00'] ],
    [ ['Sunday',   '12:00', '18:00'], ['Tuesday',  '12:00', '18:00'], ['Thursday',  '12:00', '18:00'] ],
    [ ['Saturday', '10:00', '16:00'], ['Sunday',   '10:00', '16:00'], ['Tuesday',   '10:00', '16:00'] ],
    [ ['Monday',   '14:00', '20:00'], ['Wednesday','14:00', '20:00'], ['Friday',    '10:00', '14:00'] ],
];

function seedDoctorSchedule(mysqli $conn, int $doctorId, array $pattern, string $doctorLabel): void
{
    $countStmt = mysqli_prepare($conn, "SELECT COUNT(*) FROM doctor_schedules WHERE doctor_id = ?");
    mysqli_stmt_bind_param($countStmt, 'i', $doctorId);
    mysqli_stmt_execute($countStmt);
    mysqli_stmt_bind_result($countStmt, $existingCount);
    mysqli_stmt_fetch($countStmt);
    mysqli_stmt_close($countStmt);

    if ($existingCount > 0) {
        echo "Schedule already exists for $doctorLabel.<br>";
        return;
    }

    $insertSlot = mysqli_prepare($conn, "
        INSERT INTO doctor_schedules (doctor_id, day, start_time, end_time)
        VALUES (?, ?, ?, ?)
    ");
    foreach ($pattern as [$day, $start, $end]) {
        mysqli_stmt_bind_param($insertSlot, 'isss', $doctorId, $day, $start, $end);
        mysqli_stmt_execute($insertSlot);
    }
    mysqli_stmt_close($insertSlot);

    echo "Schedule added for $doctorLabel.<br>";
}

$scheduleIndex = 0;

foreach ($data as $specialtyName => $group) {

    // --- specialty ---
    $stmt = mysqli_prepare($conn, "SELECT id FROM specialties WHERE name = ?");
    mysqli_stmt_bind_param($stmt, 's', $specialtyName);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $specialtyId);
    $found = mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    if (!$found) {
        $insert = mysqli_prepare($conn, "INSERT INTO specialties (name) VALUES (?)");
        mysqli_stmt_bind_param($insert, 's', $specialtyName);
        mysqli_stmt_execute($insert);
        $specialtyId = mysqli_insert_id($conn);
        mysqli_stmt_close($insert);
        echo "Specialty added: $specialtyName<br>";
    }

    // doctors for this specialty 
    foreach ($group['doctors'] as [$fullName, $image, $experience, $address, $bio]) {

        $email = strtolower(str_replace(' ', '.', str_replace('Dr. ', '', $fullName))) . '@healix.app';
        $phone = '010' . str_pad($phoneCounter, 8, '0', STR_PAD_LEFT);
        $phoneCounter++;

        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $existingUserId);
        $userExists = mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if ($userExists) {
            $doctorLookup = mysqli_prepare($conn, "SELECT id FROM doctors WHERE user_id = ?");
            mysqli_stmt_bind_param($doctorLookup, 'i', $existingUserId);
            mysqli_stmt_execute($doctorLookup);
            mysqli_stmt_bind_result($doctorLookup, $doctorId);
            mysqli_stmt_fetch($doctorLookup);
            mysqli_stmt_close($doctorLookup);
        } else {
            $hashed = password_hash($doctorPassword, PASSWORD_DEFAULT);
            $role   = 'doctor';

            $insertUser = mysqli_prepare($conn, "INSERT INTO users (name, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertUser, 'sssss', $fullName, $email, $hashed, $phone, $role);
            mysqli_stmt_execute($insertUser);
            $userId = mysqli_insert_id($conn);
            mysqli_stmt_close($insertUser);

            $insertDoctor = mysqli_prepare($conn, "INSERT INTO doctors (user_id, specialty_id, experience, bio, address, image) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertDoctor, 'iiisss', $userId, $specialtyId, $experience, $bio, $address, $image);
            mysqli_stmt_execute($insertDoctor);
            $doctorId = mysqli_insert_id($conn);
            mysqli_stmt_close($insertDoctor);

            echo "Doctor added: $fullName ($specialtyName)<br>";
        }

       
        $pattern = $schedulePatterns[$scheduleIndex % count($schedulePatterns)];
        $scheduleIndex++;
        seedDoctorSchedule($conn, $doctorId, $pattern, "$fullName ($specialtyName)");
    }
}

echo "<br><strong>Seeding complete.</strong>";