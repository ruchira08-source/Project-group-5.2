<?php
/* Logic for POST add_user */
?>
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
    }
