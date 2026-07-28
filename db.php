<?php
date_default_timezone_set('Asia/Kolkata');
// Prevent direct access to db.php
if (basename($_SERVER['PHP_SELF']) == 'db.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

require_once 'config.php';

function get_initials_avatar($name, $size = 40, $font_size = 16, $border = 2) {
    $name = str_replace(['Prof. ', 'Dr. ', 'Mr. ', 'Ms. '], '', trim($name));
    $parts = explode(" ", $name);
    $first_initial = substr($parts[0], 0, 1);
    $last_initial = count($parts) > 1 ? substr(end($parts), 0, 1) : '';
    $initials = strtoupper($first_initial . $last_initial);
    return "<div style=\"width: {$size}px; height: {$size}px; border-radius: 50%; background: #6366f1; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; border: {$border}px solid #e0e7ff; font-size: {$font_size}px; flex-shrink: 0;\">{$initials}</div>";
}

function get_db() {
    global $pdo;
    if (!$pdo) {
        require_once 'config.php';
    }

    $db = [
        'notices' => [],
        'assignments' => [],
        'assignment_submissions' => [],
        'leaves' => [],
        'students' => [],
        'faculty' => [],
        'grievances' => [],
        'recent_activity' => [],
        'settings' => [],
        'departments' => []
    ];

    try {
        // Ensure connection uses database
        $pdo->exec("USE erp_system");

        // Dynamically add profile_details if not exists
        try {
            $pdo->query("SELECT profile_details FROM students LIMIT 1");
        } catch (PDOException $e) {
            try {
                $pdo->exec("ALTER TABLE students ADD COLUMN profile_details LONGTEXT NULL");
            } catch (Exception $ex) {}
        }

        // notices
        $stmt = $pdo->query("SELECT * FROM notices");
        while ($row = $stmt->fetch()) {
            $db['notices'][] = [
                'id' => intval($row['id']),
                'title' => $row['title'],
                'desc' => $row['description'],
                'author' => $row['author'],
                'role' => $row['role'],
                'date' => $row['date_posted'],
                'expiry' => $row['expiry'] ?? '',
                'attachment' => $row['attachment'] ?? '',
                'size' => $row['size'] ?? ''
            ];
        }

        // assignments
        $db['assignments'] = [];
        $stmt = $pdo->query("SELECT * FROM assignments");
        while ($row = $stmt->fetch()) {
            $db['assignments'][] = [
                'id' => intval($row['id']),
                'unit' => intval($row['unit']),
                'title' => $row['title'],
                'desc' => $row['description']
            ];
        }

        // subject_assignments
        $db['subject_assignments'] = [];
        $stmt = $pdo->query("SELECT * FROM subject_assignments");
        while ($row = $stmt->fetch()) {
            $db['subject_assignments'][] = [
                'id' => intval($row['id']),
                'assignment_id' => intval($row['assignment_id']),
                'subject_name' => $row['subject_name'],
                'assignment_title' => $row['assignment_title'],
                'question_pdf' => $row['question_pdf'],
                'due' => $row['due_date'],
                'created_by' => $row['created_by'],
                'department' => $row['department'] ?? '',
                'division' => $row['division'] ?? '',
                'semester' => $row['semester'] ?? '',
                'description' => $row['description'] ?? '',
                'published_date' => $row['published_date'] ?? ''
            ];
        }

        // assignment_submissions
        $db['assignment_submissions'] = [];
        $stmt = $pdo->query("SELECT * FROM assignment_submissions");
        while ($row = $stmt->fetch()) {
            $db['assignment_submissions'][] = [
                'id' => intval($row['id']),
                'submission_id' => $row['submission_id'] ?? '',
                'subject_assignment_id' => intval($row['subject_assignment_id']),
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'subject_id' => $row['subject_id'] ?? '',
                'unit' => intval($row['unit'] ?? 0),
                'file_name' => $row['file_name'] ?? '',
                'file_path' => $row['file_path'] ?? '',
                'file_type' => $row['file_type'] ?? '',
                'file_size' => $row['file_size'] ?? '',
                'submitted_at' => $row['submitted_at'] ?? '',
                'status' => $row['status'],
                'marks' => $row['marks'] ?? 'Pending',
                'remarks' => $row['remarks'] ?? '',
                'evaluated_at' => $row['evaluated_at'] ?? '',
                'history' => is_string($row['history']) ? json_decode($row['history'], true) : ($row['history'] ?: []),
                'file' => $row['file_path'] ?? '' // fallback for any existing code using 'file'
            ];
        }

        // assignment_grievances — ensure faculty_id and department_id columns exist
        try {
            $pdo->exec("ALTER TABLE assignment_grievances ADD COLUMN IF NOT EXISTS faculty_id VARCHAR(100) DEFAULT ''");
            $pdo->exec("ALTER TABLE assignment_grievances ADD COLUMN IF NOT EXISTS department_id VARCHAR(50) DEFAULT ''");
        } catch (PDOException $alterEx) { /* columns may already exist */ }

        $db['assignment_grievances'] = [];
        try {
            $stmt = $pdo->query("SELECT * FROM assignment_grievances");
            while ($row = $stmt->fetch()) {
                $db['assignment_grievances'][] = [
                    'id'                    => intval($row['id']),
                    'subject_assignment_id' => intval($row['subject_assignment_id']),
                    'student_id'            => $row['student_id'],
                    'student_name'          => $row['student_name'],
                    'faculty_id'            => $row['faculty_id'] ?? '',
                    'department_id'         => $row['department_id'] ?? '',
                    'issue_type'            => $row['issue_type'],
                    'description'           => $row['description'],
                    'screenshot'            => $row['screenshot'] ?? '',
                    'status'                => $row['status'] ?? 'Pending',
                    'reply'                 => $row['reply'] ?? '',
                    'created_at'            => $row['created_at']
                ];
            }
        } catch (PDOException $ex) {}

        // leaves
        $stmt = $pdo->query("SELECT * FROM leave_requests");
        while ($row = $stmt->fetch()) {
            $db['leaves'][] = [
                'id' => intval($row['id']),
                'student_id' => $row['student_id'] ?? null,
                'applicant_name' => $row['applicant_name'],
                'applicant_role' => $row['applicant_role'],
                'file' => $row['file'],
                'reason' => $row['reason'],
                'from' => $row['from_date'],
                'to' => $row['to_date'],
                'status' => $row['status'],
                'remarks' => $row['remarks'] ?? ''
            ];
        }

        // students
        $stmt = $pdo->query("SELECT * FROM students");
        while ($row = $stmt->fetch()) {
            $db['students'][] = [
                'id' => $row['zprn'],
                'prn' => $row['zprn'], // User wants prn = zprn (or PRN = username). Wait, earlier it was 'IT'. Let's use zprn.
                'username' => $row['zprn'],
                'name' => $row['student_name'],
                'email' => $row['email'] ?? '',
                'phone' => $row['mobile'] ?? '',
                'dept' => $row['department'] . ' - Div ' . $row['division'],
                'department' => $row['department'],
                'year' => $row['year'] ?? 'First Year',
                'division' => $row['division'],
                'semester' => $row['semester'],
                'roll_no' => $row['roll_no'] ?? '',
                'attendance' => $row['attendance'] ?? '85%',
                'status' => $row['status'] ?? 'Active',
                'avatar' => $row['avatar'] ?: 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=150&auto=format&fit=crop',
                'profile_details' => is_string($row['profile_details']) ? json_decode($row['profile_details'], true) : ($row['profile_details'] ?: [])
            ];
        }

        // faculty
        $stmt = $pdo->query("SELECT * FROM faculty");
        while ($row = $stmt->fetch()) {
            $db['faculty'][] = [
                'id'          => $row['username'],
                'username'    => $row['username'],
                'name'        => $row['name'],
                'email'       => $row['email'] ?? '',
                'phone'       => $row['phone'] ?? '',
                'designation' => $row['designation'] ?? 'Assistant Professor',
                'workload'    => $row['workload'] ?? '16 Hours / Week',
                'attendance'  => $row['attendance'] ?? '95%',
                'subjects'    => $row['subjects'] ?? '',
                'department'  => $row['department'] ?? '',
                'role'        => 'faculty',
                'avatar'      => $row['avatar'] ?: 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=150&auto=format&fit=crop'
            ];
        }
        // HOD also counted as faculty in frontend views
        $stmt = $pdo->query("SELECT * FROM hod");
        while ($row = $stmt->fetch()) {
            $db['faculty'][] = [
                'id'          => $row['username'],
                'username'    => $row['username'],
                'name'        => $row['name'],
                'email'       => $row['email'] ?? '',
                'phone'       => $row['phone'] ?? '',
                'designation' => $row['designation'] ?? 'Professor & HOD',
                'workload'    => $row['workload'] ?? '8 Hours / Week',
                'attendance'  => $row['attendance'] ?? '98%',
                'subjects'    => $row['subjects'] ?? '',
                'department'  => $row['department'] ?? '',
                'role'        => 'HOD',
                'avatar'      => $row['avatar'] ?: 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=150&auto=format&fit=crop'
            ];
        }

        // grievances
        $stmt = $pdo->query("SELECT * FROM grievances");
        while ($row = $stmt->fetch()) {
            $db['grievances'][] = [
                'id' => intval($row['id']),
                'student_id' => $row['student_id'],
                'student_name' => $row['student_name'],
                'title' => $row['title'],
                'category' => $row['category'],
                'desc' => $row['description'],
                'date' => $row['date_posted'],
                'status' => $row['status'],
                'replies' => is_string($row['replies']) ? json_decode($row['replies'], true) : ($row['replies'] ?: [])
            ];
        }

        // recent_activity
        $stmt = $pdo->query("SELECT * FROM notifications ORDER BY id DESC LIMIT 5");
        while ($row = $stmt->fetch()) {
            $db['recent_activity'][] = [
                'title' => $row['title'],
                'desc' => $row['description'],
                'time' => $row['time']
            ];
        }

        // settings
        $stmt = $pdo->query("SELECT * FROM settings");
        while ($row = $stmt->fetch()) {
            $val = $row['setting_value'];
            if ($val === '1') $val = true;
            elseif ($val === '0') $val = false;
            $db['settings'][$row['setting_key']] = $val;
        }

        // departments
        $stmt = $pdo->query("SELECT * FROM departments");
        while ($row = $stmt->fetch()) {
            $db['departments'][] = [
                'id' => 'dept_' . $row['id'],
                'name' => $row['name'],
                'code' => $row['code'],
                'intake' => intval($row['intake']),
                'hod_name' => $row['hod_name']
            ];
        }

        // subjects
        $db['subjects'] = [];
        try {
            $stmt = $pdo->query("SELECT * FROM subjects");
            while ($row = $stmt->fetch()) {
                $db['subjects'][] = [
                    'id' => intval($row['id']),
                    'name' => $row['name'],
                    'code' => $row['code'],
                    'department_id' => intval($row['department_id'])
                ];
            }
        } catch (PDOException $ex) {}
    } catch (PDOException $e) {
        error_log("Database read failed: " . $e->getMessage());
    }

    return $db;
}

function save_db($data) {
    global $pdo;
    if (!$pdo) {
        require_once 'config.php';
    }

    try {
        $pdo->exec("USE erp_system");
        $pdo->beginTransaction();

        // Notices
        if (isset($data['notices'])) {
            $pdo->exec("DELETE FROM notices");
            $stmt = $pdo->prepare("INSERT INTO notices (id, title, description, author, role, date_posted, expiry, attachment, size) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['notices'] as $n) {
                $stmt->execute([
                    $n['id'] ?? null,
                    $n['title'],
                    $n['desc'],
                    $n['author'],
                    $n['role'],
                    $n['date'],
                    $n['expiry'] ?? '',
                    $n['attachment'] ?? '',
                    $n['size'] ?? ''
                ]);
            }
        }

        // Assignments
        if (isset($data['assignments'])) {
            $pdo->exec("DELETE FROM assignments");
            $stmt = $pdo->prepare("INSERT INTO assignments (id, unit, title, description) VALUES (?, ?, ?, ?)");
            foreach ($data['assignments'] as $a) {
                $stmt->execute([
                    $a['id'] ?? null,
                    $a['unit'],
                    $a['title'],
                    $a['desc']
                ]);
            }
        }

        // Subject Assignments
        if (isset($data['subject_assignments'])) {
            $pdo->exec("DELETE FROM subject_assignments");
            $stmt = $pdo->prepare("INSERT INTO subject_assignments (id, assignment_id, subject_name, assignment_title, question_pdf, due_date, created_by, department, division, semester, description, published_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['subject_assignments'] as $sa) {
                $stmt->execute([
                    $sa['id'] ?? null,
                    $sa['assignment_id'],
                    $sa['subject_name'],
                    $sa['assignment_title'],
                    $sa['question_pdf'],
                    $sa['due'] ?? $sa['due_date'] ?? '',
                    $sa['created_by'],
                    $sa['department'] ?? '',
                    $sa['division'] ?? '',
                    $sa['semester'] ?? '',
                    $sa['description'] ?? '',
                    $sa['published_date'] ?? ''
                ]);
            }
        }

        // Submissions
        if (isset($data['assignment_submissions'])) {
            $pdo->exec("DELETE FROM assignment_submissions");
            $stmt = $pdo->prepare("INSERT INTO assignment_submissions (id, submission_id, subject_assignment_id, student_id, student_name, subject_id, unit, file_name, file_path, file_type, file_size, submitted_at, status, marks, remarks, evaluated_at, history) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['assignment_submissions'] as $sub) {
                $stmt->execute([
                    $sub['id'] ?? null,
                    $sub['submission_id'] ?? ('SUB_' . uniqid()),
                    $sub['subject_assignment_id'],
                    $sub['student_id'],
                    $sub['student_name'],
                    $sub['subject_id'] ?? '',
                    $sub['unit'] ?? 0,
                    $sub['file_name'] ?? '',
                    $sub['file_path'] ?? $sub['file'] ?? '',
                    $sub['file_type'] ?? '',
                    $sub['file_size'] ?? '',
                    $sub['submitted_at'] ?? '',
                    $sub['status'] ?? 'Submitted',
                    $sub['marks'] ?? 'Pending',
                    $sub['remarks'] ?? '',
                    $sub['evaluated_at'] ?? null,
                    is_array($sub['history']) ? json_encode($sub['history']) : ($sub['history'] ?? '[]')
                ]);
            }
        }

        // Assignment Grievances
        if (isset($data['assignment_grievances'])) {
            $pdo->exec("DELETE FROM assignment_grievances");
            $stmt = $pdo->prepare("INSERT INTO assignment_grievances (id, subject_assignment_id, student_id, student_name, faculty_id, department_id, issue_type, description, screenshot, status, reply, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['assignment_grievances'] as $g) {
                $stmt->execute([
                    $g['id'] ?? null,
                    $g['subject_assignment_id'],
                    $g['student_id'],
                    $g['student_name'],
                    $g['faculty_id'] ?? '',
                    $g['department_id'] ?? '',
                    $g['issue_type'],
                    $g['description'],
                    $g['screenshot'] ?? '',
                    $g['status'] ?? 'Pending',
                    $g['reply'] ?? '',
                    $g['created_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        }



        // Leaves
        if (isset($data['leaves'])) {
            $pdo->exec("DELETE FROM leave_requests");
            $stmt = $pdo->prepare("INSERT INTO leave_requests (id, student_id, applicant_name, applicant_role, file, reason, from_date, to_date, status, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['leaves'] as $l) {
                $stmt->execute([
                    $l['id'] ?? null,
                    $l['student_id'] ?? null,
                    $l['applicant_name'],
                    $l['applicant_role'],
                    $l['file'],
                    $l['reason'],
                    $l['from'],
                    $l['to'],
                    $l['status'],
                    $l['remarks'] ?? ''
                ]);
            }
        }

        // Grievances
        if (isset($data['grievances'])) {
            $pdo->exec("DELETE FROM grievances");
            $stmt = $pdo->prepare("INSERT INTO grievances (id, student_id, student_name, title, category, description, date_posted, status, replies) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            foreach ($data['grievances'] as $g) {
                $stmt->execute([
                    $g['id'] ?? null,
                    $g['student_id'],
                    $g['student_name'],
                    $g['title'],
                    $g['category'],
                    $g['desc'],
                    $g['date'],
                    $g['status'],
                    json_encode($g['replies'] ?? [])
                ]);
            }
        }

        // Notifications
        if (isset($data['recent_activity'])) {
            $pdo->exec("DELETE FROM notifications");
            $stmt = $pdo->prepare("INSERT INTO notifications (title, description, time) VALUES (?, ?, ?)");
            foreach ($data['recent_activity'] as $act) {
                $stmt->execute([
                    $act['title'],
                    $act['desc'],
                    $act['time']
                ]);
            }
        }

        // Settings
        if (isset($data['settings'])) {
            foreach ($data['settings'] as $key => $val) {
                $db_val = $val;
                if (is_bool($val)) $db_val = $val ? '1' : '0';
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $db_val, $db_val]);
            }
        }



        // Students Profiles
        if (isset($data['students'])) {
            foreach ($data['students'] as $s) {
                $div = 'A';
                if (preg_match('/Div\s+([A-C])/i', $s['dept'], $matches)) {
                    $div = strtoupper($matches[1]);
                }
                
                $stmt = $pdo->prepare("UPDATE students SET 
                    student_name = ?, 
                    email = ?, 
                    mobile = ?, 
                    year = ?,
                    division = ?, 
                    semester = ?, 
                    roll_no = ?,
                    attendance = ?, 
                    status = ?, 
                    avatar = ?,
                    profile_details = ? 
                    WHERE zprn = ?");
                $stmt->execute([
                    $s['name'],
                    $s['email'],
                    $s['phone'],
                    $s['year'] ?? 'First Year',
                    $div,
                    $s['semester'],
                    $s['roll_no'] ?? null,
                    $s['attendance'] ?? '85%',
                    $s['status'] ?? 'Active',
                    $s['avatar'],
                    json_encode($s['profile_details'] ?? []),
                    $s['id']
                ]);
            }
        }

        // Faculty Profiles
        if (isset($data['faculty'])) {
            foreach ($data['faculty'] as $f) {
                $checkHOD = $pdo->prepare("SELECT id FROM hod WHERE username = ?");
                $checkHOD->execute([$f['username']]);
                if ($checkHOD->fetch()) {
                    $stmt = $pdo->prepare("UPDATE hod SET name = ?, email = ?, phone = ?, designation = ?, workload = ?, attendance = ?, subjects = ?, avatar = ? WHERE username = ?");
                    $stmt->execute([$f['name'], $f['email'], $f['phone'], $f['designation'], $f['workload'], $f['attendance'], $f['subjects'], $f['avatar'], $f['username']]);
                } else {
                    $stmt = $pdo->prepare("UPDATE faculty SET name = ?, email = ?, phone = ?, designation = ?, workload = ?, attendance = ?, subjects = ?, avatar = ? WHERE username = ?");
                    $stmt->execute([$f['name'], $f['email'], $f['phone'], $f['designation'], $f['workload'], $f['attendance'], $f['subjects'], $f['avatar'], $f['username']]);
                }
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Database save sync failed: " . $e->getMessage());
    }
}

function generate_next_prn(&$db, $department) {
    // Left for compatibility, return string format
    return 'IT' . sprintf('%04d', count($db['students']) + 1);
}
?>
