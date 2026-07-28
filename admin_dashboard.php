<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?role=admin");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_notifications') {
    $db['recent_activity'] = [];
    save_db($db);
    echo json_encode(['success' => true]);
    exit;
}

$success_message = '';
$error_message = '';

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

$active_tab = 'dashboard';
if (isset($_SESSION['active_tab'])) {
    $active_tab = $_SESSION['active_tab'];
    unset($_SESSION['active_tab']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_system_config') {
        $db['settings']['maintenance_mode'] = isset($_POST['maintenance_mode']);
        $db['settings']['captcha_enabled'] = isset($_POST['captcha_enabled']);
        $db['settings']['notifications_enabled'] = isset($_POST['notifications_enabled']);
        save_db($db);
        $_SESSION['success_message'] = "System configurations updated successfully.";
        $_SESSION['active_tab'] = 'system-configuration';
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_POST['action'] === 'update_setting') {
        $key = $_POST['setting_key'] ?? '';
        $val = $_POST['setting_value'] ?? '';
        if ($key && isset($db['settings'][$key])) {
            $db['settings'][$key] = $val;
            save_db($db);
            $_SESSION['success_message'] = "Setting updated successfully!";
        }
        $_SESSION['active_tab'] = 'system-configuration';
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_POST['action'] === 'add_user') {
        $role = $_POST['role'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $department = $_POST['department'] ?? 'Information Technology';
        
        if ($role === 'student') {
            $new_id = '125UIT' . rand(1000, 9999);
            $prn = trim($_POST['prn'] ?? '');
            if (empty($prn)) {
                $prn = generate_next_prn($db, $department);
            }
            
            $year = $_POST['year'] ?? 'First Year';
            $semester = $_POST['semester'] ?? '1st Semester';
            $division = $_POST['division'] ?? 'A';
            $roll_no = rand(1, 60);

            $db['students'][] = [
                'id' => $new_id,
                'prn' => $prn,
                'username' => $prn, // PRN as username
                'password' => $prn, // PRN as password initially
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'dept' => $department . ' - Div ' . $division,
                'department' => $department,
                'year' => $year,
                'semester' => $semester,
                'division' => $division,
                'roll_no' => $roll_no,
                'attendance' => '100%',
                'status' => 'Active',
                'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=random'
            ];
            save_db($db);
            $_SESSION['success_message'] = "Student added successfully with PRN: {$prn}!";
            $_SESSION['active_tab'] = 'user-management';
            header("Location: admin_dashboard.php");
            exit;
        } elseif ($role === 'faculty') {
            $designation = $_POST['designation'] ?? 'Assistant Professor';
            $subjects = $_POST['subjects'] ?? 'To be assigned';
            $username = strtolower(str_replace(' ', '', $name)) . rand(10,99);

            $db['faculty'][] = [
                'id' => 'fac' . rand(100, 999),
                'username' => $username,
                'password' => $username, // Username as password initially
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'designation' => $designation,
                'department' => $department,
                'workload' => '0 Hours / Week',
                'attendance' => '100%',
                'subjects' => $subjects,
                'avatar' => 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=random'
            ];
            save_db($db);
            $_SESSION['success_message'] = "Faculty added successfully with Username: {$username}!";
            $_SESSION['active_tab'] = 'user-management';
            header("Location: admin_dashboard.php");
            exit;
        }
    } elseif ($_POST['action'] === 'import_students') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['csv_file']['tmp_name'];
            $handle = fopen($file, "r");
            if ($handle !== FALSE) {
                // Read header row
                $header = fgetcsv($handle, 1000, ",");
                
                $count = 0;
                $duplicates = 0;
                require_once 'config.php';
                
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 5) {
                        $student_name = trim($data[0]);
                        $zprn = trim($data[1]);
                        $division = trim($data[2]);
                        $semester = trim($data[3]);
                        $department = trim($data[4]);
                        
                        if (empty($zprn) || empty($student_name)) {
                            continue;
                        }
                        
                        // Check if duplicate ZPRN exists in the database
                        $check = $pdo->prepare("SELECT id FROM students WHERE zprn = ?");
                        $check->execute([$zprn]);
                        if ($check->fetch()) {
                            $duplicates++;
                            continue;
                        }
                        
                        // Insert student with default hashed password
                        $hashed_pass = password_hash($zprn, PASSWORD_BCRYPT);
                        $email = strtolower(explode(' ', $student_name)[0]) . '.' . strtolower(end(explode(' ', $student_name))) . '@erp.edu';
                        $mobile = '+91 9' . rand(10000000, 99999999);
                        
                        $insert = $pdo->prepare("INSERT INTO students (student_name, zprn, password, department, division, semester, email, mobile, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
                        $insert->execute([$student_name, $zprn, $hashed_pass, $department, $division, $semester, $email, $mobile]);
                        $count++;
                    }
                }
                fclose($handle);
                $_SESSION['success_message'] = "Import complete! Imported {$count} students successfully." . ($duplicates > 0 ? " ({$duplicates} duplicate ZPRNs skipped)" : "");
            } else {
                $_SESSION['error_message'] = "Failed to open uploaded file.";
            }
        } else {
            $_SESSION['error_message'] = "File upload failed or no file selected.";
        }
        $_SESSION['active_tab'] = 'user-management';
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_POST['action'] === 'add_department') {
        $name = $_POST['dept_name'] ?? '';
        $code = $_POST['dept_code'] ?? '';
        $intake = $_POST['intake'] ?? 0;
        $hod = $_POST['hod_name'] ?? '';
        
        if (!isset($db['departments'])) {
            $db['departments'] = [];
        }
        
        $db['departments'][] = [
            'id' => 'dept_' . time(),
            'name' => $name,
            'code' => $code,
            'intake' => (int)$intake,
            'hod_name' => $hod
        ];
        save_db($db);
        $_SESSION['success_message'] = "Department added successfully!";
        $_SESSION['active_tab'] = 'department-management';
        header("Location: admin_dashboard.php");
        exit;
    } elseif ($_POST['action'] === 'publish_notice') {
        $title = trim($_POST['title']);
        $desc = trim($_POST['desc']);
        $expiry = trim($_POST['expiry']);
        $target_audience = trim($_POST['target_audience'] ?? 'All Departments');
        $file_name = '';

        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['attachment']['name']);
            if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
            move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
            $file_name = 'uploads/' . $file_name;
        }
        
        if (!empty($title) && !empty($desc)) {
            $db['notices'][] = [
                'id' => count($db['notices']) + 1,
                'title' => $title,
                'desc' => $desc,
                'author' => 'System Admin',
                'role' => 'Admin',
                'target_audience' => $target_audience,
                'date' => date('d M Y'),
                'expiry' => $expiry,
                'attachment' => $file_name,
                'size' => $file_name ? '1.5MB' : ''
            ];
            save_db($db);
            $_SESSION['success_message'] = "Notice published successfully.";
            $_SESSION['active_tab'] = 'notice-management';
            header("Location: admin_dashboard.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Title and Description are required.";
            $_SESSION['active_tab'] = 'notice-management';
            header("Location: admin_dashboard.php");
            exit;
        }
    } elseif ($_POST['action'] === 'generate_report') {
        $report_type = $_POST['report_type'] ?? 'Report';
        $format = $_POST['format'] ?? 'pdf';
        // Mock generation behavior
        $_SESSION['success_message'] = "Your " . htmlspecialchars($report_type) . " report is being generated and will download shortly.";
        $_SESSION['active_tab'] = 'report-generation';
        header("Location: admin_dashboard.php");
        exit;
    }
}

// Calculate User Counts
$student_count = count($db['students'] ?? []);

$faculty_count = 0;
$hod_count = 0;
foreach ($db['faculty'] as $f) {
    if (strpos(strtolower($f['designation']), 'hod') !== false) {
        $hod_count++;
    } else {
        $faculty_count++;
    }
}
$admin_count = 1; // Assuming 1 Super Admin

// Calculate Department Statistics
$dept_count = isset($db['departments']) ? count($db['departments']) : 0;
$total_intake = 0;
if (isset($db['departments'])) {
    foreach ($db['departments'] as $d) {
        $total_intake += (int)$d['intake'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: #f3f5f9;
            display: flex;
            min-height: 100vh;
            color: #333;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: #1a1b35;
            color: #fff;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 10;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 24px 20px;
            gap: 15px;
        }

        .sidebar-header .shield-icon {
            width: 35px;
            height: 35px;
            background-color: #3f4177;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .sidebar-header .shield-icon i {
            color: #fff;
            font-size: 16px;
        }

        .sidebar-header-text h2 {
            font-size: 16px;
            font-weight: 700;
            margin: 0;
        }

        .sidebar-header-text p {
            font-size: 11px;
            color: #a0a2c0;
            margin: 2px 0 0 0;
        }

        .nav-links {
            list-style: none;
            padding: 15px 15px;
            flex: 1;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #d1d2e8;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 8px;
            transition: background 0.2s, color 0.2s;
            cursor: pointer;
        }

        .nav-link i {
            width: 20px;
            font-size: 15px;
            margin-right: 15px;
            text-align: center;
        }

        .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-link.active {
            background-color: #326bf3;
            color: #fff;
        }

        .logout-container {
            padding: 15px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e57373;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            background-color: rgba(229, 115, 115, 0.1);
            border-radius: 8px;
            transition: background 0.2s;
        }

        .logout-btn i {
            margin-right: 15px;
        }

        .logout-btn:hover {
            background-color: rgba(229, 115, 115, 0.2);
        }

        /* Main Content Styling */
        .main-content {
            margin-left: 260px;
            flex: 1;
            padding: 25px 35px;
        }

        .top-banner {
            background-color: #fff;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .banner-left h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin-bottom: 5px;
        }

        .banner-left p {
            color: #6b7280;
            font-size: 13.5px;
        }

        .banner-right {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .notification-icon {
            width: 42px;
            height: 42px;
            background-color: #f1f5f9;
            color: #475569;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .notification-icon:hover {
            background-color: #e2e8f0;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .profile-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #8b5cf6;
            padding: 2px;
        }

        .profile-info {
            display: flex;
            flex-direction: column;
        }

        .profile-name {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .profile-role {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }

        .app-view {
            display: none;
        }

        .app-view.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .dashboard-card {
            background-color: #fff;
            border-radius: 16px;
            padding: 35px 25px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.06);
        }

        .card-icon-container {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
        }

        .card-icon-container i {
            font-size: 24px;
        }

        .dashboard-card h3 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .dashboard-card p {
            color: #6b7280;
            font-size: 12px;
            line-height: 1.5;
            margin-bottom: 25px;
            flex: 1;
        }

        .card-btn {
            width: 100%;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s;
            cursor: pointer;
        }

        /* Specific Card Colors */
        .card-purple .card-icon-container { background-color: #f3e8ff; color: #8b5cf6; }
        .card-purple h3 { color: #8b5cf6; }
        .card-purple .card-btn { border: 1px solid #d8b4fe; color: #8b5cf6; }
        .card-purple .card-btn:hover { background-color: #f3e8ff; }

        .card-green .card-icon-container { background-color: #dcfce7; color: #10b981; }
        .card-green h3 { color: #10b981; }
        .card-green .card-btn { border: 1px solid #6ee7b7; color: #10b981; }
        .card-green .card-btn:hover { background-color: #dcfce7; }

        .card-blue .card-icon-container { background-color: #e0f2fe; color: #3b82f6; }
        .card-blue h3 { color: #3b82f6; }
        .card-blue .card-btn { border: 1px solid #93c5fd; color: #3b82f6; }
        .card-blue .card-btn:hover { background-color: #e0f2fe; }

        .card-orange .card-icon-container { background-color: #ffedd5; color: #f97316; }
        .card-orange h3 { color: #f97316; }
        .card-orange .card-btn { border: 1px solid #fdba74; color: #f97316; }
        .card-orange .card-btn:hover { background-color: #ffedd5; }

        .card-red .card-icon-container { background-color: #ffe4e6; color: #ef4444; }
        .card-red h3 { color: #ef4444; }
        .card-red .card-btn { border: 1px solid #fda4af; color: #ef4444; }
        .card-red .card-btn:hover { background-color: #ffe4e6; }

        /* User Management specific styles */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 20px;
        }

        .stat-info h4 {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
        }

        .stat-students .stat-icon { background: #eff6ff; color: #3b82f6; }
        .stat-faculty .stat-icon { background: #f0fdf4; color: #22c55e; }
        .stat-hod .stat-icon { background: #fdf2f8; color: #ec4899; }
        .stat-admin .stat-icon { background: #f5f3ff; color: #8b5cf6; }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section-header h2 {
            font-size: 18px;
            color: #1e293b;
        }
        .add-btn {
            background-color: #3b82f6;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s;
        }
        .add-btn:hover { background-color: #2563eb; }

        .table-container {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }
        th {
            background-color: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        td {
            font-size: 14px;
            color: #334155;
        }
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .user-cell img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-student { background: #eff6ff; color: #3b82f6; }
        .badge-faculty { background: #f0fdf4; color: #22c55e; }
        .badge-hod { background: #fdf2f8; color: #ec4899; }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal.active {
            display: flex;
            opacity: 1;
        }
        .modal-content {
            background: #fff;
            width: 100%;
            max-width: 500px;
            border-radius: 16px;
            padding: 30px;
            transform: translateY(20px);
            transition: transform 0.3s ease;
        }
        .modal.active .modal-content {
            transform: translateY(0);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        .modal-header h3 {
            font-size: 18px;
            color: #1e293b;
        }
        .close-modal {
            background: none;
            border: none;
            font-size: 20px;
            color: #64748b;
            cursor: pointer;
        }
        .form-group { margin-bottom: 15px; }
        .form-group label {
            display: block;
            font-size: 13px;
            color: #475569;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 14px;
            color: #1e293b;
            outline: none;
            transition: border-color 0.2s;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #3b82f6;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }
        .submit-btn:hover { background: #2563eb; }
        
        /* Toast Notifications */
        .toast-notification {
            position: fixed;
            bottom: 30px;
            right: 30px;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 14.5px;
            font-weight: 600;
            z-index: 9999;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: toastSlideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards, toastFadeOut 0.4s ease 3s forwards;
        }

        .toast-success {
            background-color: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .toast-error {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @keyframes toastSlideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes toastFadeOut {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(150%); opacity: 0; }
        }
        
        /* Profile Modal CSS */
        .user-name-link {
            color: #3b82f6;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s;
        }
        .user-name-link:hover {
            color: #2563eb;
            text-decoration: underline;
        }
        
        .profile-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1050;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .profile-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            width: 100%;
            max-width: 400px;
            border-radius: 24px;
            overflow: hidden;
            transform: translateY(20px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .profile-modal-overlay.active .profile-card {
            transform: translateY(0) scale(1);
        }
        .profile-card-header {
            background: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            padding: 40px 20px 20px;
            text-align: center;
            position: relative;
            color: white;
        }
        .close-profile-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            width: 32px; height: 32px;
            border-radius: 50%;
            color: white;
            font-size: 16px;
            cursor: pointer;
            display: flex; justify-content: center; align-items: center;
            transition: background 0.2s;
        }
        .close-profile-btn:hover { background: rgba(255, 255, 255, 0.4); }
        .profile-avatar-wrapper {
            width: 100px; height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            margin: 0 auto 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        #pm-name {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 5px;
            letter-spacing: -0.5px;
        }
        .pm-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .profile-card-body {
            padding: 30px;
            background: #ffffff;
        }
        .pm-info-row {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px dashed #e2e8f0;
        }
        .pm-info-row:last-child {
            margin-bottom: 0; padding-bottom: 0; border-bottom: none;
        }
        .pm-info-icon {
            width: 40px; height: 40px;
            background: #f8fafc;
            border-radius: 12px;
            display: flex; justify-content: center; align-items: center;
            color: #6366f1;
            font-size: 18px;
            flex-shrink: 0;
            transition: transform 0.2s, background 0.2s;
        }
        .pm-info-row:hover .pm-info-icon {
            transform: scale(1.1);
            background: #eff6ff;
        }
        .pm-info-text label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 3px;
            letter-spacing: 0.5px;
        }
        .pm-info-text p {
            font-size: 14.5px;
            color: #1e293b;
            font-weight: 600;
            margin: 0;
            line-height: 1.4;
        }

    </style>
</head>
<body>

    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="shield-icon">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="sidebar-header-text">
                <h2>College ERP</h2>
                <p>Admin Panel</p>
            </div>
        </div>

        <ul class="nav-links">
            <li class="nav-item">
                <a onclick="switchTab('dashboard')" class="nav-link active" id="nav-dashboard">
                    <i class="fa-solid fa-border-all"></i>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a onclick="switchTab('user-management')" class="nav-link" id="nav-user-management">
                    <i class="fa-solid fa-user-group"></i>
                    User Management
                </a>
            </li>
            <li class="nav-item">
                <a onclick="switchTab('department-management')" class="nav-link" id="nav-department-management">
                    <i class="fa-solid fa-building"></i>
                    Department Management
                </a>
            </li>
            <li class="nav-item">
                <a onclick="switchTab('notice-management')" class="nav-link" id="nav-notice-management">
                    <i class="fa-solid fa-bullhorn"></i>
                    Notice Management
                </a>
            </li>
            <li class="nav-item">
                <a onclick="switchTab('report-generation')" class="nav-link" id="nav-report-generation">
                    <i class="fa-solid fa-file-contract"></i>
                    Report Generation
                </a>
            </li>
            <li class="nav-item">
                <a onclick="switchTab('system-configuration')" class="nav-link" id="nav-system-configuration">
                    <i class="fa-solid fa-gear"></i>
                    System Configuration
                </a>
            </li>
        </ul>

        <div class="logout-container">
            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="top-banner">
            <div class="banner-left">
                <h1 id="banner-title">Dashboard</h1>
                <p id="banner-desc">Welcome to the Admin Panel.</p>
            </div>
            <div class="banner-right" style="display: flex; align-items: center; gap: 1rem;">
                <button class="theme-toggle-btn" title="Toggle Dark/Light Theme" onclick="toggleDarkMode()">
                    <i class="fa-solid fa-moon"></i>
                </button>
                <div class="notification-wrapper" style="position: relative;">
                    <div class="notification-icon" id="notificationToggle" style="cursor:pointer;">
                        <i class="fa-regular fa-bell"></i>
                        <?php if (!empty($db['recent_activity'])): ?>
                        <span class="badge" style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; border-radius: 50%; width: 16px; height: 16px; font-size: 0.6rem; display: flex; align-items: center; justify-content: center; font-weight: bold;"><?php echo min(count($db['recent_activity']), 9); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-dropdown" id="notificationDropdown" style="display: none; position: absolute; top: 120%; right: 0; width: 320px; background: white; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); border: 1px solid var(--border-color); z-index: 100; overflow: hidden; cursor: default;">
                        <div style="padding: 1rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                            <h4 style="margin: 0; font-size: 1rem; color: #1e293b;">Notifications</h4>
                            <span style="font-size: 0.75rem; color: var(--primary-color); cursor: pointer; font-weight: 600;" onclick="fetch(window.location.href, {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'action=clear_notifications'}).then(() => { this.parentElement.nextElementSibling.innerHTML='<div style=\'padding: 2rem 1rem; text-align: center; color: #64748b; font-size: 0.9rem;\'><i class=\'fa-regular fa-bell-slash\' style=\'font-size: 1.5rem; margin-bottom: 0.5rem; color: #cbd5e1;\'></i><br>No new notifications</div>'; let b = document.querySelector('#notificationToggle .badge'); if(b) b.style.display='none'; });">Mark all as read</span>
                        </div>
                        <div style="max-height: 350px; overflow-y: auto; text-align: left;">
                            <?php if (empty($db['recent_activity'])): ?>
                                <div style="padding: 2rem 1rem; text-align: center; color: #64748b; font-size: 0.9rem;">
                                    <i class="fa-regular fa-bell-slash" style="font-size: 1.5rem; margin-bottom: 0.5rem; color: #cbd5e1;"></i><br>
                                    No new notifications
                                </div>
                            <?php else: ?>
                                <?php foreach(array_slice($db['recent_activity'], 0, 5) as $idx => $activity): ?>
                                <?php
                                $targetTab = 'dashboard';
                                $t = strtolower($activity['title'] ?? '');
                                if (strpos($t, 'leave') !== false) $targetTab = 'leaves';
                                elseif (strpos($t, 'grievance') !== false) $targetTab = 'grievance';
                                elseif (strpos($t, 'assignment') !== false) $targetTab = 'assignments';
                                elseif (strpos($t, 'notice') !== false) $targetTab = 'notices';
                                ?>
                                <div onclick="triggerTab('<?php echo $targetTab; ?>')" style="padding: 1rem; border-bottom: 1px solid #f1f5f9; cursor: pointer; transition: background 0.2s; <?php echo $idx === 0 ? 'background: #f0f9ff;' : ''; ?>" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='<?php echo $idx === 0 ? '#f0f9ff' : 'transparent'; ?>'">
                                    <div style="display: flex; gap: 0.75rem;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                            <i class="fa-solid fa-bolt"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; font-size: 0.9rem; color: #334155; margin-bottom: 0.15rem;"><?php echo htmlspecialchars($activity['title'] ?? 'Notification'); ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.25rem;"><?php echo htmlspecialchars($activity['desc'] ?? ''); ?></div>
                                            <div style="font-size: 0.7rem; color: #94a3b8;"><i class="fa-regular fa-clock" style="margin-right: 3px;"></i> <?php echo htmlspecialchars($activity['time'] ?? 'Just now'); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                    </div>
                    <script>
                        function triggerTab(tabName) {
                            if (!tabName) return;
                            if (tabName === 'grievance') {
                                let hasGrievances = false;
                                document.querySelectorAll('.sidebar-nav-item').forEach(el => {
                                    if ((el.getAttribute('onclick')||'').includes("'grievances'") || el.getAttribute('data-tab') === 'grievances') hasGrievances = true;
                                });
                                if (hasGrievances) tabName = 'grievances';
                            }
                            if (tabName === 'grievances') {
                                let hasGrievance = false;
                                document.querySelectorAll('.sidebar-nav-item').forEach(el => {
                                    if ((el.getAttribute('onclick')||'').includes("'grievance'") && !(el.getAttribute('onclick')||'').includes("'grievances'")) hasGrievance = true;
                                    if (el.getAttribute('data-tab') === 'grievance') hasGrievance = true;
                                });
                                if (hasGrievance) tabName = 'grievance';
                            }
                            
                            document.getElementById('notificationDropdown').style.display = 'none';
                            
                            let items = document.querySelectorAll('.sidebar-nav-item');
                            let targetEl = null;
                            for (let i=0; i<items.length; i++) {
                                let onclick = items[i].getAttribute('onclick') || '';
                                let dataTab = items[i].getAttribute('data-tab') || '';
                                if (onclick.includes("'" + tabName + "'") || dataTab === tabName) {
                                    targetEl = items[i];
                                    break;
                                }
                            }
                            
                            if (typeof switchTab === 'function') {
                                if (targetEl && switchTab.length === 2) {
                                    switchTab(tabName, targetEl);
                                } else {
                                    try { switchTab(tabName); } catch(e) {}
                                }
                            }
                        }

                        document.getElementById('notificationToggle').addEventListener('click', function(e) {
                            e.stopPropagation();
                            var dropdown = document.getElementById('notificationDropdown');
                            dropdown.style.display = dropdown.style.display === 'none' || dropdown.style.display === '' ? 'block' : 'none';
                        });
                        document.addEventListener('click', function(e) {
                            var dropdown = document.getElementById('notificationDropdown');
                            var toggle = document.getElementById('notificationToggle');
                            if (dropdown && !dropdown.contains(e.target) && !toggle.contains(e.target)) {
                                dropdown.style.display = 'none';
                            }
                        });
                    </script>
                </div>
                <div class="user-profile">
                    <!-- Removed admin profile avatar per user request -->
                    <div class="profile-info">
                        <span class="profile-name">Admin User</span>
                        <span class="profile-role">System Administrator</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if(!empty($success_message)): ?>
        <div class="toast-notification toast-success">
            <i class="fa-solid fa-circle-check"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <?php endif; ?>
        <?php if(!empty($error_message)): ?>
        <div class="toast-notification toast-error">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <!-- Dashboard View -->
        <div id="view-dashboard" class="app-view active">
            <!-- Admin Portal Summary -->
            <div class="stats-row" style="margin-bottom: 30px;">
                <div class="stat-card stat-students">
                    <div class="stat-icon" style="background: #eff6ff; color: #3b82f6;"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h4>Total Students</h4>
                        <p><?php echo isset($db['students']) ? count($db['students']) : 0; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-faculty">
                    <div class="stat-icon" style="background: #f0fdf4; color: #22c55e;"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="stat-info">
                        <h4>Total Faculties</h4>
                        <p><?php echo isset($db['faculty']) ? count($db['faculty']) : 0; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-department">
                    <div class="stat-icon" style="background: #fdf2f8; color: #ec4899;"><i class="fa-solid fa-building"></i></div>
                    <div class="stat-info">
                        <h4>Total Departments</h4>
                        <p><?php echo isset($db['departments']) ? count($db['departments']) : 0; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-notice">
                    <div class="stat-icon" style="background: #fffbeb; color: #f59e0b;"><i class="fa-solid fa-bullhorn"></i></div>
                    <div class="stat-info">
                        <h4>Total Notices</h4>
                        <p><?php echo isset($db['notices']) ? count($db['notices']) : 0; ?></p>
                    </div>
                </div>
            </div>
            
            <h3 style="font-size: 1.15rem; color: #1e293b; margin-bottom: 20px; font-weight: 700;">Quick Access</h3>
            <div class="cards-grid">
                <!-- User Management Card -->
                <div class="dashboard-card card-purple">
                    <div class="card-icon-container">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <h3>User Management</h3>
                    <p>Manage users, roles and permissions.</p>
                    <a onclick="switchTab('user-management')" class="card-btn">
                        <span>Go to User Management</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Department Management Card -->
                <div class="dashboard-card card-green">
                    <div class="card-icon-container">
                        <i class="fa-solid fa-table-cells"></i>
                    </div>
                    <h3>Department Management</h3>
                    <p>Manage departments and related details.</p>
                    <a onclick="switchTab('department-management')" class="card-btn">
                        <span>Go to Department Management</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Notice Management Card -->
                <div class="dashboard-card card-blue">
                    <div class="card-icon-container">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <h3>Notice Management</h3>
                    <p>Create, update and manage notices.</p>
                    <a onclick="switchTab('notice-management')" class="card-btn">
                        <span>Go to Notice Management</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Report Generation Card -->
                <div class="dashboard-card card-orange">
                    <div class="card-icon-container">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <h3>Report Generation</h3>
                    <p>Generate and view various reports.</p>
                    <a onclick="switchTab('report-generation')" class="card-btn">
                        <span>Go to Report Generation</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>

                <!-- System Configuration Card -->
                <div class="dashboard-card card-red">
                    <div class="card-icon-container">
                        <i class="fa-solid fa-gear"></i>
                    </div>
                    <h3>System Configuration</h3>
                    <p>Configure system settings and preferences.</p>
                    <a onclick="switchTab('system-configuration')" class="card-btn">
                        <span>Go to System Configuration</span>
                        <i class="fa-solid fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- User Management View -->
        <div id="view-user-management" class="app-view">
            <div class="stats-row">
                <div class="stat-card stat-students">
                    <div class="stat-icon"><i class="fa-solid fa-user-graduate"></i></div>
                    <div class="stat-info">
                        <h4>Total Students</h4>
                        <p><?php echo $student_count; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-faculty">
                    <div class="stat-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                    <div class="stat-info">
                        <h4>Total Faculties</h4>
                        <p><?php echo $faculty_count; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-hod">
                    <div class="stat-icon"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="stat-info">
                        <h4>Total HOD</h4>
                        <p><?php echo $hod_count; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-admin">
                    <div class="stat-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="stat-info">
                        <h4>Total Admin</h4>
                        <p><?php echo $admin_count; ?></p>
                    </div>
                </div>
            </div>


            <div class="section-header">
                <h2>User Management</h2>
                <div style="display: flex; gap: 10px;">
                    <button class="add-btn" onclick="openModal()"><i class="fa-solid fa-plus"></i> Add User</button>
                    <button class="add-btn" onclick="openImportModal()" style="background-color: #10b981;"><i class="fa-solid fa-file-import"></i> Import CSV</button>
                </div>
            </div>

            <style>
                .um-tabs { display: flex; gap: 15px; margin-bottom: 20px; border-bottom: 1px solid #e2e8f0; }
                .um-tab { padding: 10px 20px; cursor: pointer; font-weight: 600; color: #64748b; border-bottom: 3px solid transparent; transition: all 0.2s; }
                .um-tab:hover { color: #4f46e5; }
                .um-tab.active { color: #4f46e5; border-bottom-color: #4f46e5; }
                .um-panel { display: none; }
                .um-panel.active { display: block; overflow-x: auto; }
                .um-panel table { min-width: 900px; }
            </style>

            <div class="um-tabs">
                <div class="um-tab active" onclick="switchUmTab('students', this)">Students</div>
                <div class="um-tab" onclick="switchUmTab('faculty', this)">Faculty</div>
                <div class="um-tab" onclick="switchUmTab('hod', this)">HOD</div>
                <div class="um-tab" onclick="switchUmTab('admin', this)">Admin</div>
            </div>

            <script>
                function switchUmTab(tabId, el) {
                    document.querySelectorAll('.um-panel').forEach(p => p.classList.remove('active'));
                    document.querySelectorAll('.um-tab').forEach(t => t.classList.remove('active'));
                    document.getElementById('um-' + tabId).classList.add('active');
                    el.classList.add('active');
                }
            </script>

            <div class="table-container um-panel active" id="um-students">
                <table>
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>PRN (Username)</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Year</th>
                            <th>Semester</th>
                            <th>Division</th>
                            <th>Roll No</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['students'] as $s): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($s['prn'] ?? $s['id'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#e0e7ff; color:#3730a3; padding:2px 8px; border-radius:4px; font-weight:700;"><?php echo htmlspecialchars($s['prn'] ?? $s['id'] ?? '(same as username)'); ?></span></td>
                            <td><?php echo htmlspecialchars($s['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($s['year'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['semester'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['division'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['roll_no'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($s['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-faculty">
                <table>
                    <thead>
                        <tr>
                            <th>Faculty Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Assigned Subject</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['faculty'] as $f): if(strpos(strtolower($f['designation'] ?? ''), 'hod') !== false) continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($f['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($f['password'] ?? $f['username'] ?? ''); ?></span></td>
                            <td><?php echo htmlspecialchars($f['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($f['subjects'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-hod">
                <table>
                    <thead>
                        <tr>
                            <th>HOD Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Department</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['faculty'] as $f): if(strpos(strtolower($f['designation'] ?? ''), 'hod') === false) continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($f['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($f['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($f['password'] ?? $f['username'] ?? ''); ?></span></td>
                            <td><?php echo htmlspecialchars($f['department'] ?? 'Information Technology'); ?></td>
                            <td><?php echo htmlspecialchars($f['email'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($f['phone'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container um-panel" id="um-admin">
                <table>
                    <thead>
                        <tr>
                            <th>Admin Name</th>
                            <th>Username</th>
                            <th>Password</th>
                            <th>Role</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['users'] as $u): if(($u['role'] ?? '') !== 'admin') continue; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['name'] ?? ''); ?></td>
                            <td><span style="font-size:0.9rem; color:#4f46e5; font-weight:700;"><?php echo htmlspecialchars($u['username'] ?? ''); ?></span></td>
                            <td><span style="font-family:monospace; background:#f1f5f9; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($u['password'] ?? '********'); ?></span></td>
                            <td><span class='badge badge-admin'>Admin</span></td>
                            <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                            <td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <!-- Department Management View -->
        <div id="view-department-management" class="app-view">
            <div class="stats-row">
                <div class="stat-card stat-student">
                    <div class="stat-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="stat-info">
                        <h4>Total Departments</h4>
                        <p><?php echo $dept_count; ?></p>
                    </div>
                </div>
                <div class="stat-card stat-faculty">
                    <div class="stat-icon"><i class="fa-solid fa-users-line"></i></div>
                    <div class="stat-info">
                        <h4>Total Intake Capacity</h4>
                        <p><?php echo $total_intake; ?></p>
                    </div>
                </div>
            </div>

            <div class="section-header">
                <h2>All Departments</h2>
                <button class="add-btn" onclick="openDeptModal()"><i class="fa-solid fa-plus"></i> Add Department</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Code</th>
                            <th>Intake Capacity</th>
                            <th>HOD Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (isset($db['departments'])) {
                            foreach ($db['departments'] as $d) {
                                echo "<tr>";
                                echo "<td><strong>".htmlspecialchars($d['name'])."</strong></td>";
                                echo "<td><span class='badge badge-faculty'>".htmlspecialchars($d['code'])."</span></td>";
                                echo "<td>".htmlspecialchars($d['intake'])."</td>";
                                echo "<td>".htmlspecialchars($d['hod_name'])."</td>";
                                echo "<td><span style='color: #22c55e; font-weight: 500;'><i class='fa-solid fa-circle' style='font-size: 8px; margin-right: 4px;'></i> Active</span></td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notice Management View -->
        <div id="view-notice-management" class="app-view">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h2 style="font-size: 2.25rem; color: #3b82f6; font-weight: 800; margin-bottom: 0.5rem;">Publish Notice</h2>
                <p style="color: #64748b;">Post announcements and broadcast updates to everyone or specific departments.</p>
            </div>
            
            <div style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 2rem; margin-bottom: 3rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="publish_notice">
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Notice Title</label>
                        <input type="text" name="title" required placeholder="e.g. Extra Class Scheduled" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem;">
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Target Audience (Department)</label>
                        <select name="target_audience" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem; outline: none;">
                            <option value="All Departments">All Departments</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="Electronics & Telecommunication">Electronics & Telecommunication</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                            <option value="Faculty Only">Faculty Only</option>
                            <option value="Students Only">Students Only</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Description</label>
                        <textarea name="desc" rows="4" required placeholder="Enter notice details..." style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem; resize: vertical;"></textarea>
                    </div>
                    
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Expiry Date (Optional)</label>
                        <input type="date" name="expiry" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem;">
                    </div>
                    
                    <div style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; background: #f8fafc; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                            <div style="width: 56px; height: 56px; background: #dbeafe; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                <i class="fa-solid fa-paperclip"></i>
                            </div>
                            <div style="text-align: left;">
                                <h4 style="font-weight: 600; margin-bottom: 0.25rem; font-size: 1.05rem; color: #1e293b;">Attach File (Optional)</h4>
                                <p style="font-size: 0.9rem; color: #64748b;">Click here to <label for="admin-notice-upload" style="color: #3b82f6; font-weight: 600; cursor: pointer;">browse</label> and select a file</p>
                                <input id="admin-notice-upload" type="file" name="attachment" style="display: none;">
                                <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">Supported formats: PDF, DOCX, JPG, PNG (Max 5MB)</p>
                            </div>
                        </div>
                        <label for="admin-notice-upload" style="background: white; border: 1px solid #cbd5e1; padding: 0.65rem 1.25rem; border-radius: 6px; color: #3b82f6; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">
                            <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                        </label>
                    </div>
                    
                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); transition: transform 0.2s, box-shadow 0.2s;">Publish Notice</button>
                    </div>
                </form>
            </div>
            
            <h3 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b;">Published Notices</h3>
            
            <?php foreach (array_reverse($db['notices'] ?? []) as $n): ?>
            <div style="background: white; border: 1px solid #cbd5e1; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow: hidden;">
                <div style="padding: 1.5rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 48px; height: 48px; background: #fff1f2; color: #e11d48; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                            <h4 style="font-size: 1.15rem; font-weight: 700; color: #1e293b;"><?= htmlspecialchars($n['title']) ?></h4>
                            <?php if (isset($n['target_audience'])): ?>
                                <span style="background: #f1f5f9; color: #475569; padding: 0.25rem 0.65rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;"><?= htmlspecialchars($n['target_audience']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p style="color: #64748b; font-size: 0.95rem; margin-bottom: 0.65rem;"><?= htmlspecialchars($n['desc']) ?></p>
                        <div style="display: flex; align-items: center; gap: 1.5rem; font-size: 0.85rem; color: #475569; font-weight: 500; flex-wrap: wrap;">
                            <span><i class="fa-regular fa-calendar" style="color: #64748b;"></i> Published: <?= htmlspecialchars($n['date']) ?></span>
                            <span><i class="fa-regular fa-clock" style="color: #64748b;"></i> Expiry: <?= htmlspecialchars($n['expiry'] ?: 'N/A') ?></span>
                            <?php if (!empty($n['attachment'])): ?>
                                <a href="<?= htmlspecialchars($n['attachment']) ?>" target="_blank" style="color: #0284c7; text-decoration: none;"><i class="fa-solid fa-paperclip"></i> <?= htmlspecialchars($n['attachment']) ?></a>
                            <?php endif; ?>
                            <form method="POST" action="delete.php" style="margin:0; margin-left:auto;">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="type" value="notices">
                                <input type="hidden" name="id" value="<?= $n['id'] ?>">
                                <button type="submit" style="background:transparent;border:none;color:#ef4444;cursor:pointer;padding:0.2rem;" title="Delete Notice" onclick="return confirm('Delete this notice?');"><i class="fa-solid fa-trash"></i> Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
        </div>

        <!-- Report Generation View -->
        <div id="view-report-generation" class="app-view">
            
            <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                <button onclick="document.getElementById('reportFormContainer').style.display = document.getElementById('reportFormContainer').style.display === 'none' ? 'block' : 'none';" style="background: #5b21b6; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 500; cursor: pointer; font-family: inherit; font-size: 0.95rem; box-shadow: 0 2px 4px rgba(91, 33, 182, 0.2); transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-regular fa-file-lines"></i> Generate Report
                </button>
            </div>

            <!-- Form Container (Hidden by default) -->
            <div id="reportFormContainer" style="display: none; background: white; border: 1px solid #cbd5e1; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
                <form method="POST" action="admin_dashboard.php">
                    <input type="hidden" name="action" value="generate_report">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Report Type</label>
                            <select name="report_type" required style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem; outline: none;">
                                <option value="" disabled selected>Select Report Type</option>
                                <option value="Student Master List">Student Master List</option>
                                <option value="Faculty Directory">Faculty Directory</option>
                                <option value="Overall Attendance">Overall Attendance</option>
                                <option value="Leave Applications">Leave Applications</option>
                                <option value="Notice History">Notice History</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Department Filter</label>
                            <select name="department" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem; outline: none;">
                                <option value="All">All Departments</option>
                                <?php if(isset($db['departments'])): foreach($db['departments'] as $d): ?>
                                    <option value="<?= htmlspecialchars($d['name']) ?>"><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Start Date (Optional)</label>
                            <input type="date" name="start_date" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">End Date (Optional)</label>
                            <input type="date" name="end_date" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                    </div>
                    <div style="margin-bottom: 2rem;">
                        <label style="display: block; margin-bottom: 0.75rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Export Format</label>
                        <div style="display: flex; gap: 1rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 6px; transition: border 0.2s;">
                                <input type="radio" name="format" value="pdf" checked style="accent-color: #5b21b6;">
                                <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> PDF Document
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 6px; transition: border 0.2s;">
                                <input type="radio" name="format" value="csv" style="accent-color: #5b21b6;">
                                <i class="fa-solid fa-file-csv" style="color: #10b981;"></i> CSV/Excel
                            </label>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; padding-top: 1.5rem; border-top: 1px solid #f1f5f9;">
                        <button type="submit" style="background: #5b21b6; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem; box-shadow: 0 4px 6px rgba(91, 33, 182, 0.2); transition: transform 0.2s, box-shadow 0.2s; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-download"></i> Generate & Download
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                <!-- Card 1 -->
                <div style="background: white; border-radius: 8px; border: 1px solid #f1f5f9; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.01);">
                    <div style="background: #f3e8ff; color: #8b5cf6; width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
                        <i class="fa-regular fa-file-lines"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: #0f172a; font-weight: 600; margin-bottom: 0.25rem;">Total Reports</div>
                        <div style="font-size: 1.6rem; font-weight: 700; color: #8b5cf6; line-height: 1;">32</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">All time</div>
                    </div>
                </div>
                <!-- Card 2 -->
                <div style="background: white; border-radius: 8px; border: 1px solid #f1f5f9; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.01);">
                    <div style="background: #dcfce7; color: #22c55e; width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
                        <i class="fa-solid fa-user-group"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: #0f172a; font-weight: 600; margin-bottom: 0.25rem;">Student Reports</div>
                        <div style="font-size: 1.6rem; font-weight: 700; color: #22c55e; line-height: 1;">16</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">All time</div>
                    </div>
                </div>
                <!-- Card 3 -->
                <div style="background: white; border-radius: 8px; border: 1px solid #f1f5f9; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.01);">
                    <div style="background: #ffedd5; color: #f97316; width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
                        <i class="fa-regular fa-user"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: #0f172a; font-weight: 600; margin-bottom: 0.25rem;">Faculty Reports</div>
                        <div style="font-size: 1.6rem; font-weight: 700; color: #f97316; line-height: 1;">8</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">All time</div>
                    </div>
                </div>
                <!-- Card 4 -->
                <div style="background: white; border-radius: 8px; border: 1px solid #f1f5f9; padding: 1.25rem; display: flex; align-items: center; gap: 1rem; box-shadow: 0 2px 8px rgba(0,0,0,0.01);">
                    <div style="background: #e0f2fe; color: #3b82f6; width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; flex-shrink: 0;">
                        <i class="fa-regular fa-calendar"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.8rem; color: #0f172a; font-weight: 600; margin-bottom: 0.25rem;">Generated This Month</div>
                        <div style="font-size: 1.6rem; font-weight: 700; color: #3b82f6; line-height: 1;">7</div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">May 2024</div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div style="background: white; border-radius: 10px; border: 1px solid #f1f5f9; box-shadow: 0 2px 8px rgba(0,0,0,0.01); padding: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem;">Reports List</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; min-width: 800px;">
                        <thead>
                            <tr style="border-bottom: 1px solid #f1f5f9; background: #fafaf9;">
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem;">Report Name</th>
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem;">Category</th>
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem;">Generated By</th>
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem;">Date</th>
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem;">Status</th>
                                <th style="padding: 1rem; font-weight: 600; color: #0f172a; font-size: 0.85rem; text-align: center;">Download</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Student Attendance Report</td>
                                <td style="padding: 1rem;"><span style="background: #f3e8ff; color: #8b5cf6; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Student</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">20 May 2024 10:30 AM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Student_Attendance_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Student Marks Report</td>
                                <td style="padding: 1rem;"><span style="background: #f3e8ff; color: #8b5cf6; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Student</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">19 May 2024 04:15 PM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Student_Marks_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Faculty Attendance Report</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Faculty</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">18 May 2024 11:20 AM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Faculty_Attendance_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Assignment Submission Report</td>
                                <td style="padding: 1rem;"><span style="background: #ffedd5; color: #ea580c; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Academic</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">17 May 2024 02:45 PM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Assignment_Submission_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Leave Report</td>
                                <td style="padding: 1rem;"><span style="background: #dbeafe; color: #2563eb; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Leave</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">16 May 2024 09:10 AM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Leave_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Grievance Report</td>
                                <td style="padding: 1rem;"><span style="background: #fee2e2; color: #e11d48; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Grievance</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">15 May 2024 03:30 PM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Grievance_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr style="border-bottom: 1px solid #f8fafc;">
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Notice Report</td>
                                <td style="padding: 1rem;"><span style="background: #cffafe; color: #0891b2; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Notice</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">14 May 2024 10:05 AM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Notice_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                            <tr>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155; font-weight: 500;">Fee Collection Report</td>
                                <td style="padding: 1rem;"><span style="background: #fef3c7; color: #d97706; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Finance</span></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">Administrator</td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: #334155;">13 May 2024 05:00 PM</td>
                                <td style="padding: 1rem;"><span style="background: #dcfce7; color: #16a34a; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600;">Completed</span></td>
                                <td style="padding: 1rem; text-align: center;"><a href="download_report.php?report=Fee_Collection_Report" target="_blank" style="color: #8b5cf6; font-size: 1.1rem; text-decoration: none;"><i class="fa-solid fa-download"></i></a></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- System Configuration View -->
        <div id="view-system-configuration" class="app-view">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <div>
                    <h2 style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">System Configuration</h2>
                    <p style="color: #64748b; font-size: 0.95rem;">Configure system settings and preferences.</p>
                </div>
            </div>

            <?php
            $config_groups = [
                'College Information' => [
                    'icon' => '<i class="fa-solid fa-building-columns"></i>',
                    'color' => '#4f46e5', 'bg' => '#e0e7ff',
                    'desc' => 'View and update basic information about the college.',
                    'settings' => [
                        'site_name' => 'College Name',
                        'address' => 'Address',
                        'email_from' => 'Email',
                        'phone' => 'Phone',
                        'website' => 'Website',
                        'established_year' => 'Established Year'
                    ]
                ],
                'Academic Settings' => [
                    'icon' => '<i class="fa-solid fa-graduation-cap"></i>',
                    'color' => '#8b5cf6', 'bg' => '#f3e8ff',
                    'desc' => 'Manage academic year, semesters, departments and courses.',
                    'settings' => [
                        'academic_year' => 'Academic Year',
                        'current_semester' => 'Current Semester',
                        'total_departments' => 'Departments',
                        'total_courses' => 'Courses',
                        'sections_divisions' => 'Sections / Divisions',
                        'grading_system' => 'Grading System'
                    ]
                ],
                'User & Role Management' => [
                    'icon' => '<i class="fa-solid fa-user-group"></i>',
                    'color' => '#3b82f6', 'bg' => '#dbeafe',
                    'desc' => 'Manage users, roles and their access to the system.',
                    'settings' => [
                        'total_users' => 'Total Users',
                        'admin_users' => 'Admin Users',
                        'faculty_users' => 'Faculty Users',
                        'student_users' => 'Student Users',
                        'roles_defined' => 'Roles'
                    ]
                ],
                'System Maintenance' => [
                    'icon' => '<i class="fa-solid fa-wrench"></i>',
                    'color' => '#8b5cf6', 'bg' => '#f3e8ff',
                    'desc' => 'System health, cache and logs management.',
                    'settings' => [
                        'system_status' => 'System Status',
                        'cache' => 'Cache',
                        'database_size' => 'Database Size',
                        'system_logs' => 'System Logs'
                    ]
                ],
                'Notification Settings' => [
                    'icon' => '<i class="fa-regular fa-bell"></i>',
                    'color' => '#6366f1', 'bg' => '#e0e7ff',
                    'desc' => 'Configure email, SMS and in-app notifications.',
                    'settings' => [
                        'email_notifications' => 'Email Notifications',
                        'sms_notifications' => 'SMS Notifications',
                        'in_app_notifications' => 'In-App Notifications',
                        'notice_duration' => 'Notice Display Duration'
                    ]
                ],
                'Security Settings' => [
                    'icon' => '<i class="fa-solid fa-shield"></i>',
                    'color' => '#8b5cf6', 'bg' => '#f3e8ff',
                    'desc' => 'Manage security preferences and login settings.',
                    'settings' => [
                        'password_policy' => 'Password Policy',
                        'two_factor_auth' => 'Two-Factor Authentication',
                        'session_timeout' => 'Session Timeout',
                        'login_history' => 'Login History'
                    ]
                ],
                'Backup & Restore' => [
                    'icon' => '<i class="fa-solid fa-cloud-arrow-up"></i>',
                    'color' => '#3b82f6', 'bg' => '#dbeafe',
                    'desc' => 'Backup and restore system data and settings.',
                    'settings' => [
                        'last_backup' => 'Last Backup',
                        'backup_frequency' => 'Backup Frequency',
                        'auto_backup' => 'Auto Backup',
                        'restore_points' => 'Restore Points'
                    ]
                ],
                'System Preferences' => [
                    'icon' => '<i class="fa-solid fa-sliders"></i>',
                    'color' => '#8b5cf6', 'bg' => '#f3e8ff',
                    'desc' => 'Set system preferences and default options.',
                    'settings' => [
                        'language' => 'Language',
                        'date_format' => 'Date Format',
                        'default_timezone' => 'Time Zone',
                        'theme' => 'Theme'
                    ]
                ]
            ];
            ?>
            <style>
                .masonry-grid {
                    column-count: 2;
                    column-gap: 1.5rem;
                }
                .masonry-card {
                    break-inside: avoid;
                    background: white;
                    border-radius: 12px;
                    border: 1px solid #f1f5f9;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
                    padding: 1.5rem;
                    margin-bottom: 1.5rem;
                    display: inline-block;
                    width: 100%;
                }
                @media (max-width: 1200px) {
                    .masonry-grid {
                        column-count: 1;
                    }
                }
            </style>
            
            <div class="masonry-grid">
                <?php foreach ($config_groups as $group_name => $group_data): ?>
                <div class="masonry-card">
                    <!-- Card Header -->
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
                        <div style="background: <?= $group_data['bg'] ?>; color: <?= $group_data['color'] ?>; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0;">
                            <?= $group_data['icon'] ?>
                        </div>
                        <div>
                            <h3 style="font-size: 1.05rem; font-weight: 700; color: #0f172a; margin-bottom: 0.15rem;"><?= htmlspecialchars($group_name) ?></h3>
                            <p style="font-size: 0.8rem; color: #64748b; margin: 0; line-height: 1.4;"><?= htmlspecialchars($group_data['desc']) ?></p>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tbody>
                                <?php foreach ($group_data['settings'] as $key => $label): 
                                    $val = $db['settings'][$key] ?? '';
                                ?>
                                <tr style="border-bottom: 1px dashed #f1f5f9;">
                                    <td style="padding: 0.75rem 0; font-size: 0.85rem; font-weight: 600; color: #334155; width: 45%;"><?= htmlspecialchars($label) ?></td>
                                    <td style="padding: 0.75rem 0; font-size: 0.85rem; color: #475569;" id="val-<?= $key ?>">
                                        <?php if ($key === 'system_status'): ?>
                                            <span style="background: #dcfce7; color: #16a34a; padding: 0.15rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600;">Healthy</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($val) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 0.75rem 0; text-align: right; width: 60px;">
                                        <a href="#" onclick="openSettingModal('<?= $key ?>', '<?= addslashes(htmlspecialchars($label)) ?>', '<?= addslashes(htmlspecialchars($val)) ?>'); return false;" style="color: #4f46e5; font-size: 0.8rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem; transition: color 0.2s;" onmouseover="this.style.color='#3730a3'" onmouseout="this.style.color='#4f46e5'">
                                            <i class="fa-solid fa-pen" style="font-size: 0.75rem;"></i> Edit
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </main>

    <!-- Add User Modal -->
    <div class="modal" id="addUserModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New User</h3>
                <button class="close-modal" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="roleSelect" onchange="handleRoleChange()" required>
                        <option value="student">Student</option>
                        <option value="faculty">Faculty</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required placeholder="Enter full name">
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="Enter email address">
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" required placeholder="Enter phone number">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department" id="deptSelect" onchange="updatePRN()" required>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="Electronics & Telecommunication">Electronics & Telecommunication</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>

                <!-- Student specific fields -->
                <div id="studentFields">
                    <div class="form-group" id="prnGroup">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">
                            Automatic Student PRN
                        </label>
                        <div style="display: flex; align-items: center; gap: 0.6rem; background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 0.6rem 0.75rem; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="autoPrnToggle" checked onchange="toggleAutoPrnMode(this)" style="width: 18px; height: 18px; accent-color: #4f46e5; cursor: pointer;">
                            <label for="autoPrnToggle" style="font-size: 0.85rem; font-weight: 600; color: #475569; cursor: pointer; margin: 0; user-select: none;">Auto-generate PRN by Department</label>
                        </div>
                        <div>
                            <input type="text" name="prn" id="prnInput" readonly value="<?= htmlspecialchars(generate_next_prn($db, 'Information Technology')) ?>" style="background-color: #e0e7ff; font-weight: 700; color: #3730a3; border: 1.5px solid #6366f1; cursor: not-allowed; font-size: 1rem; letter-spacing: 0.5px; width: 100%; padding: 0.65rem 0.75rem; border-radius: 8px;" placeholder="Auto-generated PRN">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Year</label>
                        <select name="year" id="yearSelect">
                            <option value="First Year">First Year</option>
                            <option value="Second Year">Second Year</option>
                            <option value="Third Year">Third Year</option>
                            <option value="Final Year">Final Year</option>
                        </select>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="semester" id="semesterSelect">
                                <option value="1st Semester">1st Semester</option>
                                <option value="2nd Semester">2nd Semester</option>
                                <option value="3rd Semester">3rd Semester</option>
                                <option value="4th Semester">4th Semester</option>
                                <option value="5th Semester">5th Semester</option>
                                <option value="6th Semester">6th Semester</option>
                                <option value="7th Semester">7th Semester</option>
                                <option value="8th Semester">8th Semester</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Division</label>
                            <select name="division" id="divisionSelect">
                                <option value="A">Div A</option>
                                <option value="B">Div B</option>
                                <option value="C">Div C</option>
                                <option value="D">Div D</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Faculty specific fields -->
                <div id="facultyFields" style="display: none;">
                    <div class="form-group">
                        <label>Designation</label>
                        <select name="designation">
                            <option value="Assistant Professor">Assistant Professor</option>
                            <option value="Associate Professor">Associate Professor</option>
                            <option value="Professor">Professor</option>
                            <option value="Professor & HOD">Professor & HOD</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Subjects</label>
                        <input type="text" name="subjects" placeholder="e.g. Data Structures, OS">
                    </div>
                </div>

                <button type="submit" class="submit-btn">Save User</button>
            </form>
        </div>
    </div>
    <!-- Import Users Modal -->
    <div class="modal" id="importUsersModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bulk Import Students</h3>
                <button class="close-modal" onclick="closeImportModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_students">
                
                <div class="form-group">
                    <label>Select CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required style="padding: 0.5rem; border: 1.5px solid #cbd5e1; border-radius: 8px; width: 100%;">
                </div>
                
                <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 1rem; line-height: 1.4;">
                    <strong>CSV Columns expected:</strong><br>
                    Student Name, ZPRN, Division, Semester, Department
                </div>

                <button type="submit" class="submit-btn" style="background-color: #10b981;">Import Students</button>
            </form>
        </div>
    </div>

    <!-- Add Department Modal -->
    <div class="modal" id="addDeptModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Department</h3>
                <button class="close-modal" onclick="closeDeptModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_department">
                
                <div class="form-group">
                    <label>Department Name</label>
                    <input type="text" name="dept_name" required placeholder="e.g. Mechanical Engineering">
                </div>

                <div class="form-group">
                    <label>Department Code</label>
                    <input type="text" name="dept_code" required placeholder="e.g. ME-ENGG">
                </div>

                <div class="form-group">
                    <label>Intake Capacity</label>
                    <input type="number" name="intake" required placeholder="e.g. 120">
                </div>

                <div class="form-group">
                    <label>HOD Name</label>
                    <input type="text" name="hod_name" required placeholder="e.g. Dr. Rajesh Kumar">
                </div>

                <button type="submit" class="submit-btn">Save Department</button>
            </form>
        </div>
    </div>

    <!-- Edit Setting Modal -->
    <div class="modal" id="editSettingModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Setting</h3>
                <button class="close-modal" onclick="closeSettingModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_setting">
                <input type="hidden" name="setting_key" id="editSettingKey">
                
                <div class="form-group">
                    <label id="editSettingNameLabel">Setting Name</label>
                    <input type="text" name="setting_value" id="editSettingValue" required placeholder="Enter new value">
                </div>

                <button type="submit" class="submit-btn">Save Setting</button>
            </form>
        </div>
    </div>

    <!-- Profile Modal HTML -->
    <div id="profileModal" class="profile-modal-overlay">
        <div class="profile-card">
            <div class="profile-card-header">
                <button class="close-profile-btn" onclick="closeUserProfile()"><i class="fa-solid fa-xmark"></i></button>
                <div class="profile-avatar-wrapper" style="display: flex; justify-content: center; align-items: center; color: white; font-size: 42px; font-weight: 800; border: 3px solid white;">
                    <span id="pm-initials"></span>
                </div>
                <h2 id="pm-name">Name</h2>
                <span id="pm-role" class="pm-badge">Role</span>
            </div>
            <div class="profile-card-body">
                <div class="pm-info-row">
                    <div class="pm-info-icon"><i class="fa-regular fa-envelope"></i></div>
                    <div class="pm-info-text">
                        <label>Email</label>
                        <p id="pm-email">email@example.com</p>
                    </div>
                </div>
                <div class="pm-info-row">
                    <div class="pm-info-icon"><i class="fa-solid fa-phone"></i></div>
                    <div class="pm-info-text">
                        <label>Phone</label>
                        <p id="pm-phone">+91 000000000</p>
                    </div>
                </div>
                <div class="pm-info-row">
                    <div class="pm-info-icon"><i class="fa-solid fa-building"></i></div>
                    <div class="pm-info-text">
                        <label>Department</label>
                        <p id="pm-department">IT</p>
                    </div>
                </div>
                <div class="pm-info-row pm-prn-row">
                    <div class="pm-info-icon"><i class="fa-solid fa-id-card"></i></div>
                    <div class="pm-info-text">
                        <label>PRN</label>
                        <p id="pm-prn">N/A</p>
                    </div>
                </div>
                <div class="pm-info-row pm-subjects-row">
                    <div class="pm-info-icon"><i class="fa-solid fa-book"></i></div>
                    <div class="pm-info-text">
                        <label>Subjects</label>
                        <p id="pm-subjects">N/A</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabId) {
            // Hide all views
            document.querySelectorAll('.app-view').forEach(view => {
                view.classList.remove('active');
            });
            // Remove active class from navs
            document.querySelectorAll('.nav-link').forEach(nav => {
                nav.classList.remove('active');
            });

            // Show selected view
            document.getElementById('view-' + tabId).classList.add('active');
            document.getElementById('nav-' + tabId).classList.add('active');

            // Update top banner dynamically
            const title = document.getElementById('banner-title');
            const desc = document.getElementById('banner-desc');

            if(tabId === 'dashboard') {
                title.innerText = 'Dashboard';
                desc.innerText = 'Welcome to the Admin Panel.';
            } else if (tabId === 'user-management') {
                title.innerText = 'User Management';
                desc.innerText = 'Manage IT Department students and faculty.';
            } else if (tabId === 'department-management') {
                title.innerText = 'Department Management';
                desc.innerText = 'Manage college departments and intake capacities.';
            } else if (tabId === 'notice-management') {
                title.innerText = 'Notice Management';
                desc.innerText = 'Create, publish, and manage college-wide notices.';
            } else if (tabId === 'report-generation') {
                title.innerText = 'Report Generation';
                desc.innerText = 'Generate and view various reports.';
            } else if (tabId === 'system-configuration') {
                title.innerText = 'System Configuration';
                desc.innerText = 'Manage global settings, security preferences, and system behavior.';
            }
        }

        window.existingStudents = <?php echo json_encode($db['students'] ?? []); ?>;

        function getDeptPrefix(deptName) {
            if (!deptName) return 'ST';
            const clean = deptName.replace(/^Department of\s+/i, '').split(' - ')[0].trim();
            const map = {
                'Information Technology': 'IT',
                'Computer Engineering': 'CE',
                'Computer Science': 'CS',
                'Computer Science & Engineering': 'CSE',
                'Electronics & Telecommunication': 'ENTC',
                'Electronics Engineering': 'EXTC',
                'Mechanical Engineering': 'ME',
                'Civil Engineering': 'CV',
                'Electrical Engineering': 'EE',
                'Chemical Engineering': 'CHE',
                'AI & Data Science': 'AIDS',
                'Artificial Intelligence': 'AI'
            };
            if (map[clean]) return map[clean];
            const words = clean.replace(/[^a-zA-Z\s]/g, '').split(/\s+/);
            let initials = '';
            words.forEach(w => {
                if (w && !['of', 'and', '&'].includes(w.toLowerCase())) {
                    initials += w[0].toUpperCase();
                }
            });
            return initials || 'ST';
        }

        function toggleAutoPrnMode(checkbox) {
            const prnInput = document.getElementById('prnInput');
            if (!prnInput) return;
            if (checkbox.checked) {
                prnInput.readOnly = true;
                prnInput.style.backgroundColor = '#e0e7ff';
                prnInput.style.color = '#3730a3';
                prnInput.style.border = '1.5px solid #6366f1';
                prnInput.style.cursor = 'not-allowed';
                updatePRN();
            } else {
                prnInput.readOnly = false;
                prnInput.style.backgroundColor = '#ffffff';
                prnInput.style.color = '#0f172a';
                prnInput.style.border = '1.5px solid #cbd5e1';
                prnInput.style.cursor = 'text';
                prnInput.focus();
            }
        }

        function updatePRN() {
            const roleSelect = document.getElementById('roleSelect');
            if (!roleSelect) return;
            const role = roleSelect.value;
            const deptSelect = document.querySelector('#addUserModal select[name="department"]');
            const prnInput = document.getElementById('prnInput');
            const prnGroup = document.getElementById('prnGroup');
            const autoPrnToggle = document.getElementById('autoPrnToggle');

            if (role === 'student') {
                if (prnGroup) prnGroup.style.display = 'block';
                if (autoPrnToggle && !autoPrnToggle.checked) {
                    return;
                }
                const dept = deptSelect ? deptSelect.value : 'Information Technology';
                const prefix = getDeptPrefix(dept);
                
                let count = 0;
                if (window.existingStudents && Array.isArray(window.existingStudents)) {
                    window.existingStudents.forEach(s => {
                        const sPrefix = getDeptPrefix(s.dept || s.department || '');
                        if (sPrefix === prefix || (s.prn && s.prn.startsWith(prefix))) {
                            count++;
                        }
                    });
                }
                
                const nextNum = count + 1;
                const paddedNum = String(nextNum).padStart(4, '0');
                if (prnInput) prnInput.value = prefix + paddedNum;
            } else {
                if (prnGroup) prnGroup.style.display = 'none';
            }
        }

        // Modal functions
        function openModal() {
            document.getElementById('addUserModal').classList.add('active');
            handleRoleChange();
            updatePRN();
        }

        function closeModal() {
            document.getElementById('addUserModal').classList.remove('active');
        }

        function handleRoleChange() {
            const role = document.getElementById('roleSelect').value;
            const facultyFields = document.getElementById('facultyFields');
            const studentFields = document.getElementById('studentFields');
            
            if (role === 'faculty') {
                facultyFields.style.display = 'block';
                studentFields.style.display = 'none';
            } else {
                facultyFields.style.display = 'none';
                studentFields.style.display = 'block';
            }
            updatePRN();
        }

        // Close modal when clicking outside
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal();
            }
        });

        function openImportModal() {
            document.getElementById('importUsersModal').classList.add('active');
        }

        function closeImportModal() {
            document.getElementById('importUsersModal').classList.remove('active');
        }

        document.getElementById('importUsersModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeImportModal();
            }
        });

        function openDeptModal() {
            document.getElementById('addDeptModal').classList.add('active');
        }

        function closeDeptModal() {
            document.getElementById('addDeptModal').classList.remove('active');
        }

        document.getElementById('addDeptModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeDeptModal();
            }
        });
        
        // Edit Setting Modal
        function openSettingModal(key, name, value) {
            document.getElementById('editSettingKey').value = key;
            document.getElementById('editSettingNameLabel').innerText = name;
            document.getElementById('editSettingValue').value = value;
            document.getElementById('editSettingModal').classList.add('active');
        }

        function closeSettingModal() {
            document.getElementById('editSettingModal').classList.remove('active');
        }

        document.getElementById('editSettingModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeSettingModal();
            }
        });
        
        // Profile Modal JS
        function showUserProfile(element) {
            const userData = JSON.parse(element.getAttribute('data-user'));
            
            // Extract initials
            let cleanName = userData.name.replace(/^(Prof\.|Dr\.|Mr\.|Ms\.)\s+/i, '').trim();
            let parts = cleanName.split(/\s+/);
            let initials = '';
            if (parts.length === 1) {
                initials = parts[0].charAt(0).toUpperCase();
            } else {
                initials = parts[0].charAt(0).toUpperCase() + parts[parts.length - 1].charAt(0).toUpperCase();
            }
            document.getElementById('pm-initials').textContent = initials;
            document.getElementById('pm-name').textContent = userData.name;
            document.getElementById('pm-role').textContent = userData.role;
            document.getElementById('pm-email').textContent = userData.email;
            document.getElementById('pm-phone').textContent = userData.phone;
            document.getElementById('pm-department').textContent = userData.department;
            
            const prnRow = document.querySelector('.pm-prn-row');
            if(userData.role === 'Student') {
                document.querySelector('.pm-subjects-row').style.display = 'none';
                if(prnRow) {
                    prnRow.style.display = 'flex';
                    document.getElementById('pm-prn').textContent = userData.prn || 'N/A';
                }
            } else {
                document.querySelector('.pm-subjects-row').style.display = 'flex';
                document.getElementById('pm-subjects').textContent = userData.subjects;
                if(prnRow) {
                    prnRow.style.display = 'none';
                }
            }
            
            const modal = document.getElementById('profileModal');
            modal.classList.add('active');
        }

        function closeUserProfile() {
            document.getElementById('profileModal').classList.remove('active');
        }

        document.getElementById('profileModal').addEventListener('click', function(e) {
            if(e.target === this) {
                closeUserProfile();
            }
        });
        
        // Show active tab if a success message exists (meaning they just added a record)
        <?php if($success_message): ?>
            switchTab('<?php echo isset($active_tab) ? $active_tab : 'user-management'; ?>');
        <?php endif; ?>
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('form[action="delete.php"]').forEach(form => {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    try {
                        let formData = new FormData(this);
                        let response = await fetch('delete.php', {
                            method: 'POST',
                            body: formData
                        });
                        if (response.ok) {
                            window.location.reload();
                        } else {
                            alert('Failed to delete item.');
                        }
                    } catch (err) {
                        console.error(err);
                        alert('An error occurred while deleting.');
                    }
                });
            });
        });

        // Dark mode toggle handler
        function toggleDarkMode() {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme_preference', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);
        }

        function updateThemeIcon(isDark) {
            const btns = document.querySelectorAll('.theme-toggle-btn');
            btns.forEach(btn => {
                btn.innerHTML = isDark 
                    ? '<i class="fa-solid fa-sun" style="color: #f59e0b;"></i>' 
                    : '<i class="fa-solid fa-moon"></i>';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            updatePRN();
            if (localStorage.getItem('theme_preference') === 'dark') {
                document.body.classList.add('dark-mode');
                updateThemeIcon(true);
            }
        });
    </script>
</body>
</html>
