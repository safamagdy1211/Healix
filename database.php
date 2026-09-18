<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'healix_db');


$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS);
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}


$createDbSql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if (!mysqli_query($conn, $createDbSql)) {
    die('Could not create database: ' . mysqli_error($conn));
}


mysqli_select_db($conn, DB_NAME);
mysqli_set_charset($conn, 'utf8mb4');


$tables = [];
// users 
$tables['users'] = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        role ENUM('patient', 'doctor') NOT NULL DEFAULT 'patient',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
// specialties 
$tables['specialties'] = "
    CREATE TABLE IF NOT EXISTS specialties (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL UNIQUE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
// doctors
$tables['doctors'] = "
    CREATE TABLE IF NOT EXISTS doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        specialty_id INT NOT NULL,
        experience INT NOT NULL DEFAULT 0,
        bio TEXT NULL,
        address VARCHAR(255) NULL,
        image VARCHAR(255) NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (specialty_id) REFERENCES specialties(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
// doctor_schedules 
$tables['doctor_schedules'] = "
    CREATE TABLE IF NOT EXISTS doctor_schedules (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        day ENUM('Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE KEY unique_schedule_slot (doctor_id, day, start_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
// appointments 
$tables['appointments'] = "
    CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        reason VARCHAR(255) NULL,
        status ENUM('pending','confirmed','rejected','cancelled','completed') NOT NULL DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        UNIQUE KEY unique_booking_slot (doctor_id, appointment_date, appointment_time)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";
// reviews 
$tables['reviews'] = "
    CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
        CONSTRAINT chk_rating_range CHECK (rating BETWEEN 1 AND 5)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

foreach ($tables as $tableName => $sql) {
    if (!mysqli_query($conn, $sql)) {
        die("Could not create table `$tableName`: " . mysqli_error($conn));
    }
}

/**
 * Auto-reject any appointment that is still 'pending' but whose scheduled
 * date/time has already passed (the doctor never accepted or rejected it
 * in time). Call this once near the top of any page that reads/lists
 * appointments, before the data is fetched for display.
 */
function expirePendingAppointments(mysqli $conn): void
{
    mysqli_query($conn, "
        UPDATE appointments
        SET status = 'rejected'
        WHERE status = 'pending'
        AND TIMESTAMP(appointment_date, appointment_time) <= NOW()
    ");
}