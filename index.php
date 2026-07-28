<?php
session_start();
// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'faculty') {
        header("Location: faculty_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'hod') {
        header("Location: hod_dashboard.php");
        exit;
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Choose Your Role</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="theme-student">
    <div class="role-selection-wrapper">
        <div class="role-header">
            <span class="secure-badge"><i class="fa-solid fa-shield-halved"></i> Secure Sign-In</span>
            <h1>Choose your role</h1>
            <p>Pick the portal that fits you. Every role has a tailored experience with the tools you need.</p>
        </div>

        <div class="role-grid">
            <!-- Student Card -->
            <div class="role-card student glass-container" onclick="window.location.href='login.php?role=student'">
                <div class="role-icon-box">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <h3>Student</h3>
                <p>Access academics, attendance, fees, assignments, and raise leave requests.</p>
                <div class="role-link">
                    Continue <span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
                </div>
            </div>

            <!-- Faculty Card -->
            <div class="role-card faculty glass-container" onclick="window.location.href='login.php?role=faculty'">
                <div class="role-icon-box">
                    <i class="fa-solid fa-book-open"></i>
                </div>
                <h3>Faculty</h3>
                <p>Teach, publish assignments, review submissions, and approve leave requests.</p>
                <div class="role-link">
                    Continue <span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
                </div>
            </div>

            <!-- HOD Card -->
            <div class="role-card hod glass-container" onclick="window.location.href='login.php?role=hod'">
                <div class="role-icon-box">
                    <i class="fa-solid fa-users-gear"></i>
                </div>
                <h3>HOD</h3>
                <p>Manage department operations, view academic reports, and oversee faculty.</p>
                <div class="role-link">
                    Continue <span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
                </div>
            </div>

            <!-- Admin Card -->
            <div class="role-card admin glass-container" onclick="window.location.href='login.php?role=admin'">
                <div class="role-icon-box">
                    <i class="fa-solid fa-shield-gear"></i>
                </div>
                <h3>Admin</h3>
                <p>System-wide administration, user management, and global configurations.</p>
                <div class="role-link">
                    Continue <span class="arrow"><i class="fa-solid fa-arrow-right-long"></i></span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
