<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php?role=student");
    exit;
}

$user = $_SESSION['user'];
$student_id = $user['id'];
$db = get_db();

// Load current student full info
$current_student = null;
foreach ($db['students'] as $s) {
    if ($s['id'] === $student_id) {
        $current_student = $s;
        break;
    }
}
$profile_details = $current_student['profile_details'] ?? [];

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

// Handle POST submissions (Leave applications or Assignment uploads or Profile updates)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'clear_notifications') {
        $db['recent_activity'] = [];
        save_db($db);
        echo json_encode(['success' => true]);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'upload_subject_assignment') {
        header('Content-Type: application/json');
        
        $sa_id = intval($_POST['subject_assignment_id'] ?? 0);
        if ($sa_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid subject assignment ID.']);
            exit;
        }

        $has_file = isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK;
        $comments = trim($_POST['comments'] ?? '');

        if (!$has_file && empty($comments)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please provide a file or a comment.']);
            exit;
        }

        $file_name = '';
        $file_size = 0;
        $dest_filename = '';
        $ext = '';
        $formatted_size = '0 MB';

        if ($has_file) {
            $file_name = $_FILES['assignment_file']['name'];
            $file_size = $_FILES['assignment_file']['size'];
            $tmp_name = $_FILES['assignment_file']['tmp_name'];

            // File format check
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Only PDF, DOC, DOCX, JPG, JPEG, and PNG files are allowed.']);
                exit;
            }

            // File size check (20MB)
            if ($file_size > 20 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Maximum file size allowed is 20 MB.']);
                exit;
            }
        }

        // Fetch subject assignment
        $db = get_db();
        $sa_item = null;
        foreach ($db['subject_assignments'] as $sa) {
            if ($sa['id'] === $sa_id) {
                $sa_item = $sa;
                break;
            }
        }

        if (!$sa_item) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Subject assignment not found.']);
            exit;
        }

        // Enforce due date check
        $due_time = strtotime($sa_item['due']);
        if (time() > $due_time) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'The due date for this assignment has passed. Submissions are closed.']);
            exit;
        }

        if ($has_file) {
            // Move file
            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0777, true);
            }
            $dest_filename = 'sub_' . $student_id . '_' . $sa_id . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($tmp_name, __DIR__ . '/uploads/' . $dest_filename)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save file.']);
                exit;
            }
            $formatted_size = number_format($file_size / (1024 * 1024), 2) . ' MB';
        }

        // Check if previous submission exists
        $submission_idx = -1;
        if (isset($db['assignment_submissions'])) {
            foreach ($db['assignment_submissions'] as $idx => $sub) {
                if ($sub['subject_assignment_id'] === $sa_id && $sub['student_id'] === $student_id) {
                    $submission_idx = $idx;
                    break;
                }
            }
        }

        $now = date('d M Y h:i A');
        $formatted_size = number_format($file_size / (1024 * 1024), 2) . ' MB';

        // Find Unit number
        $unit_no = 1;
        foreach ($db['assignments'] as $a) {
            if ($a['id'] == $sa_item['assignment_id']) {
                $unit_no = intval($a['unit']);
                break;
            }
        }

        if ($submission_idx !== -1) {
            // Update submission
            $prev_sub = $db['assignment_submissions'][$submission_idx];
            $history = $prev_sub['history'] ?? [];
            $history[] = [
                'file' => $prev_sub['file_path'] ?? $prev_sub['file'] ?? '',
                'submitted_at' => $prev_sub['submitted_at'] ?? $now,
                'status' => $prev_sub['status'] ?? 'Submitted',
                'comments' => $prev_sub['comments'] ?? ''
            ];

            if ($has_file) {
                $db['assignment_submissions'][$submission_idx]['file_name'] = $file_name;
                $db['assignment_submissions'][$submission_idx]['file_path'] = $dest_filename;
                $db['assignment_submissions'][$submission_idx]['file_type'] = $ext;
                $db['assignment_submissions'][$submission_idx]['file_size'] = $formatted_size;
            }
            if (!empty($comments)) {
                $db['assignment_submissions'][$submission_idx]['comments'] = $comments;
            }
            $db['assignment_submissions'][$submission_idx]['submitted_at'] = $now;
            $db['assignment_submissions'][$submission_idx]['status'] = 'Submitted';
            $db['assignment_submissions'][$submission_idx]['history'] = $history;
        } else {
            // Create new submission
            $unique_sub_id = 'SUB_' . strtoupper(bin2hex(random_bytes(4)));
            $max_sub_id = 0;
            foreach (($db['assignment_submissions'] ?? []) as $sub) {
                if (isset($sub['id']) && intval($sub['id']) > $max_sub_id) {
                    $max_sub_id = intval($sub['id']);
                }
            }

            $db['assignment_submissions'][] = [
                'id' => $max_sub_id + 1,
                'submission_id' => $unique_sub_id,
                'subject_assignment_id' => $sa_id,
                'student_id' => $student_id,
                'student_name' => $user['name'],
                'subject_id' => $sa_item['subject_name'],
                'unit' => $unit_no,
                'file_name' => $file_name,
                'file_path' => $dest_filename,
                'file_type' => $ext,
                'file_size' => $formatted_size,
                'comments' => $comments,
                'submitted_at' => $now,
                'status' => 'Submitted',
                'marks' => 'Pending',
                'remarks' => '',
                'evaluated_at' => null,
                'history' => []
            ];
        }

        // Log upload action in notifications (recent activity)
        $db['recent_activity'] = array_merge([
            [
                'title' => 'Assignment Uploaded',
                'desc' => $user['name'] . ' submitted ' . $sa_item['assignment_title'],
                'time' => 'Just now'
            ]
        ], array_slice($db['recent_activity'] ?? [], 0, 4));

        save_db($db);
        echo json_encode(['success' => true, 'submitted_at' => $now]);
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'submit_assignment_grievance') {
        $sa_id = intval($_POST['subject_assignment_id'] ?? 0);
        $issue_type = trim($_POST['issue_type'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if ($sa_id <= 0 || empty($issue_type) || empty($description)) {
            $_SESSION['error_message'] = "All required grievance fields must be filled.";
            header("Location: student_dashboard.php");
            exit;
        }

        $db = get_db();

        // Look up the subject_assignment to find which faculty created it
        $target_sa = null;
        foreach (($db['subject_assignments'] ?? []) as $sa) {
            if (intval($sa['id']) == $sa_id) {
                $target_sa = $sa;
                break;
            }
        }

        // Resolve faculty_id: match created_by (faculty name) against faculty list
        $grievance_faculty_id = '';
        if ($target_sa) {
            $faculty_name_search = trim($target_sa['created_by'] ?? '');
            foreach (($db['faculty'] ?? []) as $f) {
                if (strcasecmp(trim($f['name']), $faculty_name_search) === 0) {
                    $grievance_faculty_id = $f['username'] ?? $f['id'] ?? '';
                    break;
                }
            }
        }

        // Resolve department_id from student's department name
        $student_dept_name = trim($current_student['department'] ?? '');
        $grievance_department_id = '';
        foreach (($db['departments'] ?? []) as $dept) {
            if (strcasecmp(trim($dept['name']), $student_dept_name) === 0) {
                $grievance_department_id = $dept['id'] ?? '';
                break;
            }
        }

        // Handle screenshot upload
        $screenshot_name = '';
        if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
                if (!is_dir(__DIR__ . '/uploads')) {
                    mkdir(__DIR__ . '/uploads', 0777, true);
                }
                $screenshot_name = 'gr_' . $student_id . '_' . $sa_id . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['screenshot']['tmp_name'], __DIR__ . '/uploads/' . $screenshot_name);
            }
        }

        // Prevent duplicate grievances for the same assignment and student
        $existing = false;
        foreach (($db['assignment_grievances'] ?? []) as $g) {
            if (intval($g['subject_assignment_id']) === $sa_id && $g['student_id'] === $student_id) {
                if (in_array($g['status'], ['Pending', 'In Progress', 'In Review'])) {
                    $existing = true;
                    break;
                }
            }
        }
        if ($existing) {
            $_SESSION['error_message'] = "You already have an active grievance for this assignment.";
            header("Location: student_dashboard.php");
            exit;
        }

        $max_gr_id = 0;
        foreach (($db['assignment_grievances'] ?? []) as $g) {
            if (isset($g['id']) && intval($g['id']) > $max_gr_id) {
                $max_gr_id = intval($g['id']);
            }
        }
        $db['assignment_grievances'][] = [
            'id'                    => $max_gr_id + 1,
            'subject_assignment_id' => $sa_id,
            'student_id'            => $student_id,
            'student_name'          => $user['name'],
            'faculty_id'            => $grievance_faculty_id,
            'department_id'         => $grievance_department_id,
            'issue_type'            => $issue_type,
            'description'           => $description,
            'screenshot'            => $screenshot_name,
            'status'                => 'Pending',
            'reply'                 => '',
            'created_at'            => date('Y-m-d H:i:s')
        ];

        // Log grievance action
        $db['recent_activity'] = array_merge([
            [
                'title' => 'Grievance Raised',
                'desc'  => $user['name'] . ' raised issue: ' . $issue_type,
                'time'  => 'Just now'
            ]
        ], array_slice($db['recent_activity'], 0, 4));

        save_db($db);
        $_SESSION['success_message'] = "Grievance submitted successfully.";
        header("Location: student_dashboard.php");
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_passport_api') {
        header('Content-Type: application/json');
        
        $passport_no = strtoupper(trim($_POST['passport_no'] ?? ''));
        $place_of_issue = trim($_POST['place_of_issue'] ?? '');
        $issue_date = trim($_POST['issue_date'] ?? '');
        $expiry_date = trim($_POST['expiry_date'] ?? '');

        // Validation
        if (empty($passport_no) || empty($place_of_issue) || empty($issue_date) || empty($expiry_date)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'All passport fields are required.']);
            exit;
        }

        if (!preg_match('/^[A-Z0-9]{9}$/', $passport_no)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Passport Number must be exactly 9 alphanumeric characters.']);
            exit;
        }

        $issue_time = strtotime($issue_date);
        $expiry_time = strtotime($expiry_date);
        if ($expiry_time <= $issue_time) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Expiry Date must be later than Issue Date.']);
            exit;
        }

        // Fetch db and update
        $db = get_db();
        $updated = false;
        foreach ($db['students'] as &$s) {
            if ($s['id'] === $student_id) {
                // Ensure profile_details array exists
                if (!isset($s['profile_details']) || !is_array($s['profile_details'])) {
                    $s['profile_details'] = [];
                }
                if (!isset($s['profile_details']['passport_details']) || !is_array($s['profile_details']['passport_details'])) {
                    $s['profile_details']['passport_details'] = [];
                }
                
                $s['profile_details']['passport_details']['passport_no'] = $passport_no;
                $s['profile_details']['passport_details']['place_of_issue'] = $place_of_issue;
                $s['profile_details']['passport_details']['issue_date'] = $issue_date;
                $s['profile_details']['passport_details']['expiry_date'] = $expiry_date;
                
                $updated = true;
                break;
            }
        }

        if ($updated) {
            save_db($db);
            echo json_encode(['success' => true, 'message' => 'Passport details saved successfully.']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Student record not found.']);
        }
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'save_profile') {
        $primary_email = trim($_POST['primary_email'] ?? '');
        $alternate_email = trim($_POST['alternate_email'] ?? '');
        if (strcasecmp($primary_email, $alternate_email) === 0) {
            $_SESSION['error_message'] = 'Alternate Email cannot be the same as Primary Email.';
            $active_tab = trim($_POST['current_active_profile_tab'] ?? 'personal');
            header("Location: student_dashboard.php?tab=profile&profile_tab=" . urlencode($active_tab));
            exit;
        }

        // Mobile number validation backend
        $mobile_val = trim($_POST['mobile_number'] ?? '');
        if (!empty($mobile_val) && !preg_match('/^[0-9]{10}$/', $mobile_val)) {
            $_SESSION['error_message'] = 'Mobile Number must be exactly 10 digits.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=personal");
            exit;
        }

        // Identity verification backend
        $aadhaar_val = trim($_POST['identity_details']['aadhaar_no'] ?? '');
        $pan_val = strtoupper(trim($_POST['identity_details']['pan_no'] ?? ''));
        $voter_val = strtoupper(trim($_POST['identity_details']['voter_id'] ?? ''));
        $dl_val = strtoupper(trim($_POST['identity_details']['driving_license'] ?? ''));

        if (!empty($aadhaar_val) && !preg_match('/^[0-9]{12}$/', $aadhaar_val)) {
            $_SESSION['error_message'] = 'Aadhaar Number must be exactly 12 digits.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=identity");
            exit;
        }
        if (!empty($pan_val) && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_val)) {
            $_SESSION['error_message'] = 'Enter a valid PAN Number.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=identity");
            exit;
        }
        if (!empty($voter_val) && !preg_match('/^[A-Z]{3}[0-9]{7}$/', $voter_val)) {
            $_SESSION['error_message'] = 'Enter a valid Voter ID Number.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=identity");
            exit;
        }
        if (!empty($dl_val) && !preg_match('/^[A-Z]{2}[0-9]{13}$/', $dl_val)) {
            $_SESSION['error_message'] = 'Enter a valid Driving License Number.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=identity");
            exit;
        }

        // Disability percentage validation backend
        $handicap_pct = trim($_POST['handicap_details']['disability_percentage'] ?? '');
        if (!empty($handicap_pct) && (!is_numeric($handicap_pct) || strlen($handicap_pct) > 2)) {
            $_SESSION['error_message'] = 'Disability Percentage must be a maximum of 2 digits.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=handicap");
            exit;
        }

        // Passport number validation backend
        $passport_val = strtoupper(trim($_POST['passport_details']['passport_no'] ?? ''));
        if (!empty($passport_val) && !preg_match('/^[A-Z0-9]{9}$/', $passport_val)) {
            $_SESSION['error_message'] = 'Passport Number must be exactly 9 alphanumeric characters.';
            header("Location: student_dashboard.php?tab=profile&profile_tab=passport");
            exit;
        }

        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $full_name = trim("$last_name $first_name $middle_name");
        
        $profile_data = [
            'first_name' => $first_name,
            'middle_name' => $middle_name,
            'last_name' => $last_name,
            'category' => trim($_POST['category'] ?? ''),
            'cast' => trim($_POST['cast'] ?? ''),
            'sub_caste' => trim($_POST['sub_caste'] ?? ''),
            'nationality' => trim($_POST['nationality'] ?? ''),
            'domicile' => trim($_POST['domicile'] ?? ''),
            'birth_place' => trim($_POST['birth_place'] ?? ''),
            'birth_country' => trim($_POST['birth_country'] ?? ''),
            'birth_state' => trim($_POST['birth_state'] ?? ''),
            'birth_district' => trim($_POST['birth_district'] ?? ''),
            'native_place' => trim($_POST['native_place'] ?? ''),
            'native_country' => trim($_POST['native_country'] ?? ''),
            'native_state' => trim($_POST['native_state'] ?? ''),
            'native_district' => trim($_POST['native_district'] ?? ''),
            'primary_email' => trim($_POST['primary_email'] ?? ''),
            'alternate_email' => trim($_POST['alternate_email'] ?? ''),
            'blood_group' => trim($_POST['blood_group'] ?? ''),
            'anti_ragging_no' => trim($_POST['anti_ragging_no'] ?? ''),
            'earning_parent_name' => trim($_POST['earning_parent_name'] ?? ''),
            'earning_parent_relation' => trim($_POST['earning_parent_relation'] ?? ''),
            'career_choice' => trim($_POST['career_choice'] ?? ''),
            'alumni_institute' => trim($_POST['alumni_institute'] ?? ''),
            
            // Other tabs
            'identity_details' => $_POST['identity_details'] ?? [],
            'religion_details' => $_POST['religion_details'] ?? [],
            'handicap_details' => $_POST['handicap_details'] ?? [],
            'minority_details' => $_POST['minority_details'] ?? [],
            'passport_details' => $_POST['passport_details'] ?? [],
            'exam_details' => $_POST['exam_details'] ?? []
        ];

        // Update database record
        $db = get_db();
        foreach ($db['students'] as &$s) {
            if ($s['id'] === $student_id) {
                if (!empty($full_name)) {
                    $s['name'] = $full_name;
                }
                $s['phone'] = trim($_POST['mobile_number'] ?? $s['phone']);
                $s['profile_details'] = $profile_data;
                
                // Update session variables
                $_SESSION['user']['name'] = $s['name'];
                break;
            }
        }
        save_db($db);
        $_SESSION['success_message'] = 'Profile details saved successfully!';
        
        $active_tab = trim($_POST['current_active_profile_tab'] ?? 'personal');
        header("Location: student_dashboard.php?tab=profile&profile_tab=" . urlencode($active_tab));
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
        $reason = trim($_POST['reason']);
        $from_date = trim($_POST['from_date']);
        $to_date = trim($_POST['to_date']);
        $file_name = 'Leave_Form_' . date('d_M_Y') . '.pdf'; // Default fallback

        // Handle uploaded file if present
        if (isset($_FILES['leave_file']) && $_FILES['leave_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['leave_file']['name']);
            if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
            move_uploaded_file($_FILES['leave_file']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
            $file_name = 'uploads/' . $file_name;
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        if (!empty($reason) && !empty($from_date) && !empty($to_date)) {
            // Read, append, and save
            $db['leaves'][] = [
                'id' => count($db['leaves']) + 1,
                'applicant_name' => $user['name'],
                'applicant_role' => 'Student',
                'file' => $file_name,
                'reason' => $reason,
                'from' => $from_date,
                'to' => $to_date,
                'status' => 'Pending',
                'remarks' => ''
            ];
            $db['recent_activity'] = array_merge([
                [
                    'title' => 'New Leave Application',
                    'desc' => $user['name'] . ' applied for ' . $reason . ' Leave',
                    'time' => 'Just now'
                ]
            ], array_slice($db['recent_activity'], 0, 3));
            save_db($db);
            $_SESSION['success_message'] = 'Leave application submitted successfully! It has been routed to the Faculty Dashboard for approval.';
        } else {
            $_SESSION['error_message'] = 'Please fill out all leave application fields.';
        }
        header("Location: student_dashboard.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'upload_assignment') {
        $unit = intval($_POST['unit']);
        $file_name = 'Assignment_Unit_' . $unit . '.pdf';

        if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = basename($_FILES['assignment_file']['name']);
            if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
            move_uploaded_file($_FILES['assignment_file']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
            $file_name = 'uploads/' . $file_name;
        } elseif (isset($_POST['file_name']) && !empty($_POST['file_name'])) {
            $file_name = trim($_POST['file_name']);
        }

        // Check if submission already exists
        $found = false;
        if (!isset($db['assignment_submissions'])) { $db['assignment_submissions'] = []; }
        foreach ($db['assignment_submissions'] as &$sub) {
            if ($sub['assignment_unit'] == $unit && $sub['student_id'] === $student_id) {
                $sub['file'] = $file_name;
                $sub['status'] = 'submitted';
                $sub['marks'] = 'Pending';
                $found = true;
                break;
            }
        }

        if (!$found) {
            $db['assignment_submissions'][] = [
                'id' => count($db['assignment_submissions']) + 1,
                'assignment_unit' => $unit,
                'student_id' => $student_id,
                'student_name' => $user['name'],
                'file' => $file_name,
                'status' => 'submitted',
                'marks' => 'Pending'
            ];
        }
        
        save_db($db);
        $_SESSION['success_message'] = 'Assignment for Unit ' . $unit . ' submitted successfully!';
        header("Location: student_dashboard.php");
        exit;
    } elseif (isset($_POST['action']) && $_POST['action'] === 'submit_grievance') {
        $title = trim($_POST['title']);
        $category = trim($_POST['category']);
        $desc = trim($_POST['desc']);
        if (!empty($title) && !empty($category) && !empty($desc)) {
            $new_g = [
                'id' => count($db['grievances']) + 1,
                'student_id' => $user['username'],
                'student_name' => $user['name'],
                'title' => $title,
                'category' => $category,
                'desc' => $desc,
                'date' => date('d M Y h:i A'),
                'status' => 'Pending',
                'replies' => []
            ];
            $db['grievances'][] = $new_g;
            
            // Add a recent activity
            $db['recent_activity'] = array_merge([
                [
                    'title' => 'Grievance Raised',
                    'desc' => $user['name'] . ' reported "' . $title . '"',
                    'time' => 'Just now'
                ]
            ], array_slice($db['recent_activity'], 0, 3));
            
            save_db($db);
            $_SESSION['success_message'] = 'Grievance submitted successfully!';
        } else {
            $_SESSION['error_message'] = 'Please fill out all grievance fields.';
        }
        header("Location: student_dashboard.php");
        exit;
    }
}

// Fetch fresh updates
$notices = $db['notices'] ?? [];
$assignments = $db['assignments'] ?? [];
$leaves = [];
foreach ($db['leaves'] ?? [] as $leave) {
    if (isset($leave['applicant_name']) && $leave['applicant_name'] === $user['name']) {
        $leaves[] = $leave;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="theme-student">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-brand">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <span>College ERP</span>
                        <span class="sub">Student Portal</span>
                    </div>
                </div>
                <ul class="sidebar-nav">
                    <li><a class="sidebar-nav-item" onclick="switchTab('profile', this)"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                    <li><a class="sidebar-nav-item active" onclick="switchTab('dashboard', this)"><i class="fa-solid fa-border-all"></i><span>Dashboard</span></a></li>
                    <!-- <li><a class="sidebar-nav-item" onclick="switchTab('attendance', this)"><i class="fa-solid fa-calendar-check"></i><span>Attendance</span></a></li> -->
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Requests</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('grievance', this)"><i class="fa-solid fa-circle-question"></i><span>Grievance</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Notices</span></a></li>
                </ul>
            </div>
            <div class="sidebar-footer">
                <a href="logout.php" class="sidebar-nav-item" style="background: rgba(239, 68, 68, 0.1); color: #f87171;"><i class="fa-solid fa-right-from-bracket"></i><span>Logout</span></a>
            </div>
        </aside>

        <!-- Main Dashboard View Area -->
        <main class="main-content">
            <!-- Header Widget -->
            <header class="dashboard-header">
                <div class="page-title-box">
                    <h2 id="currentTabTitle">Dashboard</h2>
                    <p id="currentTabSubtitle">Quick access to all essential student services.</p>
                </div>
                <div class="user-profile-widget">
                    <button class="theme-toggle-btn" title="Toggle Dark/Light Theme" onclick="toggleDarkMode()">
                        <i class="fa-solid fa-moon"></i>
                    </button>
                    <div class="notification-wrapper" style="position: relative;">
                        <div class="notification-bell" id="notificationToggle" style="cursor:pointer;">
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
                    <div class="user-avatar-box" onclick="switchTab('profile', document.querySelector('.sidebar-nav-item[onclick*=\'profile\']'))" style="cursor: pointer;">
                        <?= get_initials_avatar($user['name'], 40, 16, 2) ?>
                        <div class="user-details">
                            <span class="name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="role"><?= htmlspecialchars($user['username']) ?> | <?php echo htmlspecialchars($user['dept']); ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Success/Error alert banner -->
            <?php if (!empty($success_message)): ?>
                <div class="toast-notification toast-success">
                    <i class="fa-solid fa-circle-check"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="toast-notification toast-error">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- 0. DASHBOARD PAGE                            -->
            <!-- ============================================ -->
            <div id="tab-dashboard" class="app-view active">
                <h3 style="margin-bottom: 1.5rem; color: #1e293b;">Portal Summary</h3>
                <?php
                // Calculate summaries
                $student_name = $user['name'] ?? 'Prasad Kulkarni';
                
                // Assignments
                $total_assignments = count($db['assignments'] ?? []);
                $submitted_assignments = 0;
                foreach ($db['assignment_submissions'] ?? [] as $sub) {
                    if (($sub['student_name'] ?? '') === $student_name) {
                        $submitted_assignments++;
                    }
                }
                $pending_assignments = max(0, $total_assignments - $submitted_assignments);
                
                // Leaves
                $my_leaves = 0;
                $pending_leaves = 0;
                foreach ($db['leaves'] ?? [] as $l) {
                    if (($l['applicant_name'] ?? '') === $student_name) {
                        $my_leaves++;
                        if (($l['status'] ?? '') === 'Pending') $pending_leaves++;
                    }
                }
                
                // Grievances
                $active_grievances = 0;
                foreach ($db['grievances'] ?? [] as $g) {
                    if (($g['student_name'] ?? '') === $student_name) {
                        if (($g['status'] ?? '') !== 'Resolved') {
                            $active_grievances++;
                        }
                    }
                }
                
                // Notices
                $total_notices = count($db['notices'] ?? []);
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem;">

                    <!-- Assignments Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('assignments', document.querySelectorAll('.sidebar-nav-item')[3])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Pending Assignments</h4>
                        <div style="color: #6366f1; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $pending_assignments ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Out of <?= $total_assignments ?> total</p>
                    </div>

                    <!-- Leaves Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('leaves', document.querySelectorAll('.sidebar-nav-item')[4])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #dcfce7; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-calendar-check"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Leaves Pending</h4>
                        <div style="color: #10b981; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $pending_leaves ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Total Applied: <?= $my_leaves ?></p>
                    </div>

                    <!-- Grievance Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('grievance', document.querySelectorAll('.sidebar-nav-item')[5])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #ffedd5; color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-comments"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Grievances</h4>
                        <div style="color: #f97316; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $active_grievances ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Requires resolution</p>
                    </div>

                    <!-- Notice Summary Card -->
                    <div style="background: white; border-radius: 12px; padding: 1.75rem 1.25rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('notices', document.querySelectorAll('.sidebar-nav-item')[6])">
                        <div style="width: 58px; height: 58px; border-radius: 50%; background: #dbeafe; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1.15rem;">
                            <i class="fa-regular fa-bell"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Notices</h4>
                        <div style="color: #3b82f6; font-size: 2.2rem; font-weight: 800; margin-bottom: 0.35rem;"><?= $total_notices ?></div>
                        <p style="color: #94a3b8; font-size: 0.8rem; margin-bottom: 0;">Recent updates</p>
                    </div>

                </div>


            </div>

            <!-- ============================================ -->
            <!-- 1. NOTICES PAGE                              -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view">
                <div class="notice-hero">
                    <div class="notice-hero-icon">
                        <i class="fa-solid fa-bullhorn"></i>
                    </div>
                    <div class="notice-hero-text">
                        <h4>Important Notices</h4>
                        <p>Notices published by faculty and administration will appear here.</p>
                    </div>
                </div>

                <div class="data-table-container">
                    <div class="table-header-filters">
                        <select class="select-filter" id="noticeRoleFilter" onchange="filterNotices()">
                            <option value="all">All Notices</option>
                            <option value="faculty">Faculty Only</option>
                            <option value="admin">Administration Only</option>
                        </select>
                        <select class="select-filter" id="noticeSortFilter" onchange="filterNotices()">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Title</th>
                                <th>Published By</th>
                                <th>Date & Time</th>
                                <th>Attachment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $notice): ?>
                                <tr>
                                    <td><?php echo $notice['id']; ?></td>
                                    <td>
                                        <div class="notice-title"><?php echo htmlspecialchars($notice['title']); ?></div>
                                        <div class="notice-desc"><?php echo htmlspecialchars($notice['desc']); ?></div>
                                    </td>
                                    <td>
                                        <div class="publisher-cell">
                                            <span class="pub-name"><?php echo htmlspecialchars($notice['author']); ?></span>
                                            <span class="pub-role"><?php echo htmlspecialchars($notice['role']); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell"><?php echo htmlspecialchars($notice['date']); ?></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($notice['attachment'])): ?>
                                            <?php 
                                                $ext = pathinfo($notice['attachment'], PATHINFO_EXTENSION); 
                                                $badge_class = ($ext === 'pdf') ? 'pdf' : 'docx';
                                            ?>
                                            <a href="<?php echo htmlspecialchars($notice['attachment']); ?>" target="_blank" class="attachment-badge <?php echo $badge_class; ?>">
                                                <i class="fa-regular <?php echo ($badge_class==='pdf')?'fa-file-pdf':'fa-file-word'; ?>"></i>
                                                <span><?php echo htmlspecialchars($notice['attachment']); ?> (<?php echo $notice['size']; ?>)</span>
                                            </a>
                                            <a href="<?php echo htmlspecialchars($notice['attachment']); ?>" target="_blank" class="btn-icon-download" style="margin-left: 0.5rem; text-decoration: none;">
                                                <i class="fa-solid fa-download"></i>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem;">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 2. ASSIGNMENTS PAGE                          -->
            <!-- ============================================ -->
            <div id="tab-assignments" class="app-view">
                <?php
                // Fetch student class metadata
                $student_dept = $current_student['department'] ?? '';
                $student_div = $current_student['division'] ?? '';
                $student_sem = $current_student['semester'] ?? '';
                ?>
                <div class="data-table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Assignment Units</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $units_list = [1, 2, 3, 4, 5, 6];
                            foreach ($units_list as $unit_num): 
                                // Find assignment_id for this unit
                                $target_assign_id = 0;
                                foreach ($db['assignments'] as $a) {
                                    if (intval($a['unit']) === $unit_num) {
                                        $target_assign_id = intval($a['id']);
                                        break;
                                    }
                                }

                                // Load subject-wise assignments dynamically from database
                                $unit_sas = [];
                                if ($target_assign_id > 0 && isset($db['subject_assignments'])) {
                                    foreach ($db['subject_assignments'] as $sa) {
                                        if (intval($sa['assignment_id']) === $target_assign_id) {
                                            $sa_dept = $sa['department'] ?? '';
                                            $sa_div = $sa['division'] ?? '';
                                            $sa_sem = $sa['semester'] ?? '';

                                            $match_dept = (strcasecmp($sa_dept, $student_dept) === 0);
                                            $match_div = (empty($sa_div) || strcasecmp($sa_div, $student_div) === 0);

                                            if ($match_dept && $match_div) {
                                                $unit_sas[] = $sa;
                                            }
                                        }
                                    }
                                }
                            ?>
                                <tr class="assignment-unit-row" data-unit="<?php echo $unit_num; ?>" style="cursor: pointer;">
                                    <td style="font-weight: 500; padding: 1.5rem; text-align: center; color: #4b5563;"><?php echo $unit_num; ?></td>
                                    <td style="padding: 1.5rem;">
                                        <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; width: 100%;">
                                            <div style="display: flex; gap: 1rem; align-items: center;">
                                                <div style="width: 44px; height: 44px; background: #f3e8ff; color: #8b5cf6; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex-shrink: 0;">
                                                    <i class="fa-solid fa-file-lines"></i>
                                                </div>
                                                <div>
                                                    <div style="font-weight: 700; color: #1e293b; font-size: 1rem;">Unit <?php echo $unit_num; ?></div>
                                                </div>
                                            </div>
                                            <!-- Accordion Rotating arrow -->
                                            <div class="accordion-arrow" style="font-size: 1rem; color: #64748b; transition: transform 0.3s ease; padding: 0.5rem; cursor: pointer;">
                                                <i class="fa-solid fa-chevron-down"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="assignment-details-row" id="unit-details-<?php echo $unit_num; ?>" style="display: none; background: #f8fafc;">
                                    <td colspan="2" style="padding: 1.5rem 2rem;">
                                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                                            <?php 
                                            // Get all subjects dynamically from the database
                                            $all_subjects = $db['subjects'] ?? [];
                                            if (empty($all_subjects)): 
                                            ?>
                                                <div style="padding: 1.5rem; text-align: center; color: #64748b; font-size: 0.95rem; font-weight: 500;">
                                                    No subjects are assigned to your department/semester.
                                                </div>
                                            <?php 
                                            else:
                                                foreach ($all_subjects as $subject):
                                                    $subject_name = $subject['name'];
                                                    
                                                    // Find the faculty assignment for this unit and subject
                                                    $sa_item = null;
                                                    if (isset($db['subject_assignments'])) {
                                                        foreach ($db['subject_assignments'] as $sa) {
                                                            if (intval($sa['assignment_id']) === $target_assign_id && $sa['subject_name'] === $subject_name) {
                                                                $sa_dept = $sa['department'] ?? '';
                                                                $sa_div = $sa['division'] ?? '';
                                                                $sa_sem = $sa['semester'] ?? '';

                                                                $match_dept = (strcasecmp($sa_dept, $student_dept) === 0);
                                                                $match_div = (empty($sa_div) || strcasecmp($sa_div, $student_div) === 0);

                                                                if ($match_dept && $match_div) {
                                                                    $sa_item = $sa;
                                                                    break;
                                                                }
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Check submission status - use loose == for type-safe comparison
                                                    $my_sub = null;
                                                    if ($sa_item) {
                                                        foreach (($db['assignment_submissions'] ?? []) as $sub) {
                                                            if (intval($sub['subject_assignment_id']) == intval($sa_item['id']) && (string)$sub['student_id'] == (string)$student_id) {
                                                                $my_sub = $sub;
                                                                break;
                                                            }
                                                        }
                                                    }
                                                    $sub_status = $my_sub ? $my_sub['status'] : 'Pending';
                                                    $sub_marks = $my_sub ? $my_sub['marks'] : 'Pending';
                                                    $sub_remarks = $my_sub ? $my_sub['remarks'] : '';
                                                    $sub_file = $my_sub ? ($my_sub['file_path'] ?? $my_sub['file'] ?? '') : '';
                                                    $sub_file_name = $my_sub ? ($my_sub['file_name'] ?? '') : '';
                                                    $submitted_at = $my_sub ? ($my_sub['submitted_at'] ?? '') : '';
                                            ?>
                                                <!-- Subject Level Header/Accordion -->
                                                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); margin-bottom: 0.5rem; width: 100%;">
                                                    <div onclick="toggleSubjectDetails(<?php echo $unit_num; ?>, <?php echo $subject['id']; ?>)" style="padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; cursor: pointer; background: #f8fafc; border-bottom: 1px solid #f1f5f9; width: 100%;">
                                                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                            <span style="font-weight: 700; color: #334155; font-size: 0.95rem;"><?php echo htmlspecialchars($subject_name); ?></span>
                                                            <?php if ($sa_item): ?>
                                                                <?php if ($sub_status === 'Pending'): ?>
                                                                    <span style="background: #fee2e2; color: #ef4444; font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600;">Pending</span>
                                                                <?php else: ?>
                                                                    <span style="background: #dcfce7; color: #16a34a; font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 600;">Submitted</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span style="background: #f1f5f9; color: #64748b; font-size: 0.7rem; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 500;">No Assignment</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <i class="fa-solid fa-chevron-right subject-arrow-icon-<?php echo $unit_num; ?>-<?php echo $subject['id']; ?>" style="color: #94a3b8; transition: transform 0.2s;"></i>
                                                    </div>
                                                    
                                                    <!-- Subject Level Body Containing the Three Sections -->
                                                    <div id="subject-body-<?php echo $unit_num; ?>-<?php echo $subject['id']; ?>" style="display: none; padding: 1.5rem; border-top: 1px solid #e2e8f0; background: white;">
                                                        <div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 1.5rem; align-items: start;">
                                                            
                                                            <!-- COLUMN 1: SECTION 1 (FACULTY ASSIGNMENT) & SECTION 3 (GRIEVANCE) -->
                                                            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                                                                <!-- SECTION 1: FACULTY ASSIGNMENT -->
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                                                                    <h6 style="margin: 0 0 0.75rem 0; font-size: 0.85rem; text-transform: uppercase; color: #64748b; font-weight: 700; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;">
                                                                        <i class="fa-solid fa-file-signature" style="color: #4f46e5;"></i> SECTION 1 : FACULTY ASSIGNMENT
                                                                    </h6>
                                                                    <?php if ($sa_item): ?>
                                                                        <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.85rem;">
                                                                            <div><strong>Subject Name:</strong> <span style="color: #334155;"><?= htmlspecialchars($sa_item['subject_name']) ?></span></div>
                                                                            <div><strong>Assignment Title:</strong> <span style="color: #4f46e5; font-weight: 600;"><?= htmlspecialchars($sa_item['assignment_title']) ?></span></div>
                                                                            <div><strong>Assignment Description:</strong> <p style="margin: 0.25rem 0 0 0; color: #64748b; font-size: 0.85rem; line-height: 1.4;"><?= htmlspecialchars($sa_item['description'] ?? 'Solve all tasks.') ?></p></div>
                                                                            <div><strong>Unit Number:</strong> <span style="color: #334155;">Unit <?= htmlspecialchars($unit_num) ?></span></div>
                                                                            <div><strong>Faculty Name:</strong> <span style="color: #334155; font-weight: 600;"><?= htmlspecialchars($sa_item['created_by']) ?></span></div>
                                                                            <div><strong>Published Date:</strong> <span style="color: #334155;"><?= htmlspecialchars($sa_item['published_date'] ?? 'N/A') ?></span></div>
                                                                            <div><strong>Due Date:</strong> <span style="color: #b91c1c; font-weight: 600;"><i class="fa-regular fa-calendar-times"></i> <?= htmlspecialchars($sa_item['due'] ?? '') ?></span></div>
                                                                            <div style="margin-top: 0.75rem; display: flex; gap: 0.75rem;">
                                                                                <a href="uploads/<?= htmlspecialchars($sa_item['question_pdf']) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; padding: 0.4rem 0.8rem; background: #e0f2fe; color: #0369a1; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-decoration: none;">
                                                                                    <i class="fa-solid fa-eye"></i> View PDF
                                                                                </a>
                                                                                <a href="uploads/<?= htmlspecialchars($sa_item['question_pdf']) ?>" download style="display: inline-flex; align-items: center; gap: 4px; padding: 0.4rem 0.8rem; background: #f0fdf4; color: #166534; border-radius: 6px; font-weight: 700; font-size: 0.8rem; text-decoration: none;">
                                                                                    <i class="fa-solid fa-download"></i> Download PDF
                                                                                </a>
                                                                            </div>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <div style="color: #94a3b8; font-size: 0.9rem; text-align: center; padding: 1.5rem 0;">
                                                                            <i class="fa-regular fa-folder-open" style="font-size: 2rem; display: block; margin-bottom: 0.5rem; color: #cbd5e1;"></i>
                                                                            No assignment has been published for this subject.
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                
                                                                <!-- SECTION 3: GRIEVANCE -->
                                                                <?php if ($sa_item): ?>
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                                                                    <h6 style="margin: 0 0 0.75rem 0; font-size: 0.85rem; text-transform: uppercase; color: #64748b; font-weight: 700; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;">
                                                                        <i class="fa-solid fa-circle-exclamation" style="color: #ef4444;"></i> SECTION 3 : GRIEVANCE
                                                                    </h6>
                                                                    <p style="font-size: 0.8rem; color: #64748b; line-height: 1.4; margin-bottom: 1rem;">
                                                                        If you notice issues in the faculty's document (such as blurry pages, corrupted file, wrong subject, or broken download link), you can report it to the faculty.
                                                                    </p>
                                                                    <button type="button" onclick="openSubjectGrievanceModal(<?= $sa_item['id'] ?>, '<?= htmlspecialchars(addslashes($subject_name)) ?>', '<?= htmlspecialchars(addslashes($sa_item['assignment_title'])) ?>')" style="background: #ef4444; color: white; border: none; padding: 0.5rem 1rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(239,68,68,0.2);">
                                                                        <i class="fa-solid fa-triangle-exclamation"></i> Raise Grievance
                                                                    </button>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- COLUMN 2: SECTION 2 (STUDENT SUBMISSION) -->
                                                            <div>
                                                                <?php if ($sa_item): ?>
                                                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                                                                    <h6 style="margin: 0 0 0.75rem 0; font-size: 0.85rem; text-transform: uppercase; color: #64748b; font-weight: 700; border-bottom: 1px dashed #cbd5e1; padding-bottom: 0.5rem; display: flex; align-items: center; gap: 6px;">
                                                                        <i class="fa-solid fa-cloud-arrow-up" style="color: #10b981;"></i> SECTION 2 : STUDENT SUBMISSION
                                                                    </h6>
                                                                    
                                                                    <?php 
                                                                    $due_passed = time() > strtotime($sa_item['due'] ?? $sa_item['due_date'] ?? '');
                                                                    ?>
                                                                    
                                                                    <!-- Upload Portal State -->
                                                                    <div id="submission-portal-container-<?php echo $sa_item['id']; ?>">
                                                                        <?php if ($sub_status !== 'Pending'): ?>
                                                                            <!-- Already Submitted View -->
                                                                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                                                                <div style="display: flex; align-items: center; gap: 6px; color: #15803d; font-weight: 700; font-size: 0.9rem;">
                                                                                    <i class="fa-solid fa-circle-check"></i> Submitted Successfully
                                                                                </div>
                                                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                                                    <strong>Submission Date & Time:</strong> <?= htmlspecialchars($submitted_at) ?>
                                                                                </div>
                                                                                <div style="font-size: 0.8rem; color: #64748b;">
                                                                                    <strong>File Name:</strong> <?= htmlspecialchars($sub_file_name) ?>
                                                                                </div>
                                                                                
                                                                                <div style="display: flex; gap: 0.5rem; margin-top: 0.25rem;">
                                                                                    <a href="uploads/<?= htmlspecialchars($sub_file) ?>" target="_blank" style="padding: 0.35rem 0.75rem; background: #e0f2fe; color: #0369a1; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                                                        <i class="fa-solid fa-eye"></i> View File
                                                                                    </a>
                                                                                    <a href="uploads/<?= htmlspecialchars($sub_file) ?>" download style="padding: 0.35rem 0.75rem; background: #f0fdf4; color: #166534; border-radius: 4px; font-size: 0.75rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                                                        <i class="fa-solid fa-download"></i> Download File
                                                                                    </a>
                                                                                </div>

                                                                                <!-- Evaluation Marks / Remarks -->
                                                                                <?php if ($sub_marks !== 'Pending'): ?>
                                                                                    <div style="background: white; border: 1px solid #dcfce7; padding: 0.75rem; border-radius: 6px; margin-top: 0.5rem;">
                                                                                        <div style="font-weight: 700; color: #15803d; font-size: 0.85rem; margin-bottom: 2px;">Evaluation Result</div>
                                                                                        <div style="font-size: 0.8rem; color: #334155;"><strong>Marks Given:</strong> <span style="font-weight: 700; color: #16a34a;"><?= htmlspecialchars($sub_marks) ?></span></div>
                                                                                        <?php if (!empty($sub_remarks)): ?>
                                                                                            <div style="font-size: 0.8rem; color: #475569; margin-top: 2px;"><strong>Faculty Remarks:</strong> <em>"<?= htmlspecialchars($sub_remarks) ?>"</em></div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endif; ?>

                                                                                <!-- Replace submission button before due date only -->
                                                                                <?php if (!$due_passed): ?>
                                                                                    <button type="button" onclick="showPortalUploadForm(<?= $sa_item['id'] ?>)" style="background: #e2e8f0; color: #475569; border: none; padding: 0.45rem 1rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; cursor: pointer; margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;">
                                                                                        <i class="fa-solid fa-arrows-rotate"></i> Replace Submission
                                                                                    </button>
                                                                                <?php else: ?>
                                                                                    <button type="button" disabled style="background: #f1f5f9; color: #94a3b8; border: none; padding: 0.45rem 1rem; border-radius: 6px; font-weight: 700; font-size: 0.8rem; cursor: not-allowed; margin-top: 0.5rem; display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;" title="Due date has passed. Replacement disabled.">
                                                                                        <i class="fa-solid fa-lock"></i> Replace Submission (Disabled - Due Date Passed)
                                                                                    </button>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php endif; ?>

                                                                        <!-- Upload Form -->
                                                                        <div id="upload-form-wrapper-<?php echo $sa_item['id']; ?>" style="<?= ($sub_status !== 'Pending') ? 'display: none;' : '' ?>">
                                                                            <?php if ($due_passed): ?>
                                                                                <div style="color: #94a3b8; font-size: 0.85rem; text-align: center; padding: 1rem 0;">
                                                                                    <i class="fa-solid fa-lock" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem; color: #cbd5e1;"></i>
                                                                                    Due date has passed. Submissions are closed.
                                                                                </div>
                                                                            <?php else: ?>
                                                                                <form onsubmit="handlePortalAssignmentUpload(event, <?= $sa_item['id'] ?>)" style="display: flex; flex-direction: column; gap: 0.75rem;">
                                                                                    <div class="drag-drop-zone" id="drag-drop-zone-<?= $sa_item['id'] ?>" ondragover="event.preventDefault(); this.style.borderColor='#4f46e5';" ondragleave="this.style.borderColor='#cbd5e1';" ondrop="handlePortalFileDrop(event, <?= $sa_item['id'] ?>)" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 1.5rem; text-align: center; background: white; cursor: pointer; transition: border-color 0.2s;" onclick="document.getElementById('portal-file-input-<?= $sa_item['id'] ?>').click()">
                                                                                        <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2rem; color: #94a3b8; margin-bottom: 0.5rem;"></i>
                                                                                        <span style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569;">Drag & Drop upload</span>
                                                                                        <span style="display: block; font-size: 0.75rem; color: #94a3b8; margin: 4px 0;">or</span>
                                                                                        <button type="button" style="background: #4f46e5; color: white; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem; cursor: pointer;">Browse File</button>
                                                                                        <input type="file" id="portal-file-input-<?= $sa_item['id'] ?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;" onchange="handlePortalFileSelect(this, <?= $sa_item['id'] ?>)">
                                                                                    </div>
                                                                                    
                                                                                    <!-- Selected File Preview Container -->
                                                                                    <div id="file-preview-container-<?= $sa_item['id'] ?>" style="display: none; background: white; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                                                                                        <div style="display: flex; align-items: center; gap: 8px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                                                            <i class="fa-regular fa-file" style="color: #4f46e5; font-size: 1.25rem;"></i>
                                                                                            <div>
                                                                                                <div class="selected-file-name" style="font-size: 0.8rem; font-weight: 700; color: #1e293b;">file.pdf</div>
                                                                                                <div class="selected-file-size" style="font-size: 0.7rem; color: #94a3b8;">1.2 MB</div>
                                                                                            </div>
                                                                                        </div>
                                                                                        <button type="button" onclick="clearPortalSelectedFile(<?= $sa_item['id'] ?>)" style="background: none; border: none; color: #ef4444; font-size: 1rem; cursor: pointer; padding: 0.25rem;"><i class="fa-solid fa-trash-can"></i></button>
                                                                                    </div>
                                                                                    
                                                                                    <!-- Upload Progress Bar -->
                                                                                    <div id="progress-container-<?= $sa_item['id'] ?>" style="display: none; width: 100%;">
                                                                                        <div style="display: flex; justify-content: space-between; font-size: 0.7rem; font-weight: 700; color: #475569; margin-bottom: 2px;">
                                                                                            <span>Uploading...</span>
                                                                                            <span class="progress-percentage-<?= $sa_item['id'] ?>">0%</span>
                                                                                        </div>
                                                                                        <div style="width: 100%; height: 6px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                                                                                            <div class="progress-bar-fill-<?= $sa_item['id'] ?>" style="width: 0%; height: 100%; background: #10b981; transition: width 0.1s;"></div>
                                                                                        </div>
                                                                                    </div>

                                                                                    <div style="font-size: 0.75rem; color: #94a3b8; display: flex; flex-direction: column; gap: 2px; padding: 2px;">
                                                                                        <span>• Accepted files: PDF, DOC, DOCX, JPG, JPEG, PNG</span>
                                                                                        <span>• Maximum size: 20 MB</span>
                                                                                    </div>
                                                                                    <textarea id="portal-comments-<?= $sa_item['id'] ?>" placeholder="Add a comment (optional)..." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0.5rem; font-size: 0.8rem; font-family: inherit; resize: vertical; min-height: 60px; box-sizing: border-box;"></textarea>

                                                                                    <button type="submit" id="btn-submit-portal-<?= $sa_item['id'] ?>" style="background: #10b981; color: white; border: none; padding: 0.6rem; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: flex; align-items: center; gap: 6px; justify-content: center; box-shadow: 0 2px 4px rgba(16,185,129,0.2);">
                                                                                        <i class="fa-solid fa-cloud-arrow-up"></i> Submit Assignment
                                                                                    </button>
                                                                                </form>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php 
                                                endforeach;
                                            endif; 
                                            ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Assignment Grievances Section -->
                <div class="data-table-container" style="margin-top: 3rem;">
                    <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">My Assignment Grievances</h3>
                    </div>
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php 
                        $my_assign_grievances = array_filter($db['assignment_grievances'] ?? [], function($g) use ($student_id) {
                            return $g['student_id'] === $student_id;
                        });
                        
                        if (empty($my_assign_grievances)): ?>
                            <p style="color:var(--text-muted); text-align:center;">No assignment grievances raised yet.</p>
                        <?php else: foreach ($my_assign_grievances as $g): 
                            // Find subject assignment
                            $sa_item = null;
                            foreach ($db['subject_assignments'] as $sa) {
                                if ($sa['id'] == $g['subject_assignment_id']) {
                                    $sa_item = $sa;
                                    break;
                                }
                            }
                            $subject_name = $sa_item ? $sa_item['subject_name'] : 'Unknown Subject';
                            $assign_title = $sa_item ? $sa_item['assignment_title'] : 'Unknown Assignment';
                        ?>
                            <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem; background: #fafafa;">
                                <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                                    <div>
                                        <h4 style="font-size:1rem; font-weight:700; color:#111827; margin-bottom:0.25rem;"><?= htmlspecialchars($subject_name) ?> - <?= htmlspecialchars($assign_title) ?></h4>
                                        <span style="font-size:0.75rem; color:#64748b;">Issue: <strong><?= htmlspecialchars($g['issue_type']) ?></strong> • Raised on: <?= htmlspecialchars($g['created_at']) ?></span>
                                    </div>
                                    <div>
                                        <?php 
                                        $st = strtolower($g['status']);
                                        $pill_bg = '#fee2e2'; $pill_color = '#b91c1c';
                                        if ($st === 'resolved') { $pill_bg = '#dcfce7'; $pill_color = '#15803d'; }
                                        elseif (in_array($st, ['in review', 'in progress'])) { $pill_bg = '#dbeafe'; $pill_color = '#1d4ed8'; }
                                        elseif ($st === 'rejected') { $pill_bg = '#f3f4f6'; $pill_color = '#4b5563'; }
                                        ?>
                                        <span style="background: <?= $pill_bg ?>; color: <?= $pill_color ?>; padding: 0.3rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($g['status']) ?></span>
                                    </div>
                                </div>
                                <div style="font-size:0.85rem; color:#334155; margin-bottom:1rem; line-height:1.5;">
                                    <?= nl2br(htmlspecialchars($g['description'])) ?>
                                </div>
                                <?php if (!empty($g['screenshot'])): ?>
                                    <div style="margin-bottom: 1rem;">
                                        <a href="uploads/<?= htmlspecialchars($g['screenshot']) ?>" target="_blank" style="color: #4f46e5; text-decoration: none; font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa-regular fa-image"></i> View Screenshot
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($g['reply'])): ?>
                                    <div style="background: #f1f5f9; padding: 1rem; border-radius: 8px; border-left: 4px solid #64748b;">
                                        <div style="font-weight: 700; font-size: 0.8rem; color: #475569; margin-bottom: 0.25rem;">Faculty Response:</div>
                                        <div style="font-size: 0.8rem; color: #334155; line-height: 1.4;"><?= nl2br(htmlspecialchars($g['reply'])) ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 3. LEAVE REQUESTS PAGE                       -->
            <!-- ============================================ -->
            <div id="tab-leaves" class="app-view">
                <div class="leave-grid">
                    <!-- Submit Request card -->
                    <div class="leave-form-container">
                        <div class="leave-form-header">
                            <h3>Apply for Leave</h3>
                            <p>Upload your filled leave form and submit your dates to request leaves.</p>
                        </div>
                        <form id="leaveApplicationForm" method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="apply_leave">
                            
                            <!-- File Drop Zone -->
                            <div class="drag-drop-zone" id="leaveDropZone" onclick="document.getElementById('leaveFileInput').click()">
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p>Click to choose file or drag & drop here</p>
                                <span>Supported formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)</span>
                                <input type="file" id="leaveFileInput" name="leave_file" style="display:none;" onchange="handleFileSelect(event)">
                            </div>
                            
                            <!-- Display selected file info -->
                            <div class="selected-file-display" id="fileDisplayArea">
                                <div class="file-info">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    <span id="displayFileName">FileName.pdf</span>
                                </div>
                                <button type="button" class="btn-remove-file" onclick="removeSelectedFile()"><i class="fa-solid fa-trash-can"></i></button>
                            </div>
                            <!-- Fallback hidden input to pass file name if uploaded directly -->
                            <input type="hidden" id="fallbackFileName" name="file_name" value="">

                            <!-- Reason and Dates -->
                            <div class="leave-form-row">
                                <div class="form-group">
                                    <label for="leaveReason"><i class="fa-solid fa-circle-info"></i> Reason</label>
                                    <div class="input-wrapper">
                                        <select class="select-filter" id="leaveReason" name="reason" style="width: 100%; height: 45px;" required>
                                            <option value="">Select leave reason</option>
                                            <option value="Medical">Medical / Sick Leave</option>
                                            <option value="Personal">Personal Reasons</option>
                                            <option value="Family Function">Family Function</option>
                                            <option value="Exam Preparation">Exam Preparation</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="leaveFromDate"><i class="fa-regular fa-calendar-days"></i> From Date</label>
                                    <div class="input-wrapper">
                                        <input type="date" id="leaveFromDate" name="from_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="leaveToDate"><i class="fa-regular fa-calendar-days"></i> To Date</label>
                                    <div class="input-wrapper">
                                        <input type="date" id="leaveToDate" name="to_date" min="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn-submit-leave">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Submit Leave Application</span>
                            </button>
                        </form>
                    </div>

                    <!-- Leave list table -->
                    <div class="data-table-container">
                        <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">Your Leave Requests</h3>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">#</th>
                                    <th>Leave Form</th>
                                    <th>Reason</th>
                                    <th>From Date</th>
                                    <th>To Date</th>
                                    <th style="text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaves as $leave): ?>
                                    <tr>
                                        <td><?php echo $leave['id']; ?></td>
                                        <td>
                                            <div class="publisher-cell" style="flex-direction:row; align-items:center; gap:0.5rem;">
                                                <?php 
                                                    $ext = pathinfo($leave['file'], PATHINFO_EXTENSION);
                                                    $is_pdf = (strtolower($ext) === 'pdf');
                                                ?>
                                                <i class="fa-solid <?php echo $is_pdf?'fa-file-pdf':'fa-file-word'; ?>" style="font-size:1.15rem; color:<?php echo $is_pdf?'#ef4444':'#0284c7'; ?>"></i>
                                                <a href="<?php echo htmlspecialchars($leave['file']); ?>" target="_blank" class="pub-name" style="font-size:0.9rem; font-weight:500; text-decoration:none; color: var(--primary-color);"><?php echo htmlspecialchars($leave['file']); ?></a>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500;"><?php echo htmlspecialchars($leave['reason']); ?></span>
                                        </td>
                                        <td>
                                            <span class="date-cell"><?php echo htmlspecialchars($leave['from']); ?></span>
                                        </td>
                                        <td>
                                            <span class="date-cell"><?php echo htmlspecialchars($leave['to']); ?></span>
                                        </td>
                                        <td style="text-align: center;">
                                            <?php 
                                                $status = strtolower($leave['status']);
                                                $pill_class = ($status === 'approved') ? 'graded' : (($status === 'pending') ? 'pending' : 'rejected');
                                            ?>
                                            <span class="status-pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars($leave['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 4. GRIEVANCES PAGE                           -->
            <!-- ============================================ -->
            <div id="tab-grievance" class="app-view">
                <div class="leave-grid">
                    <!-- Submit Request card -->
                    <div class="leave-form-container">
                        <div class="leave-form-header">
                            <h3>Submit a Grievance</h3>
                            <p>Report issues to administration or department heads.</p>
                        </div>
                        <form method="POST" action="student_dashboard.php">
                            <input type="hidden" name="action" value="submit_grievance">
                            <div class="leave-form-row">
                                <div class="form-group">
                                    <label><i class="fa-solid fa-heading"></i> Subject Title</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="title" required placeholder="Brief description of the issue">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fa-solid fa-tag"></i> Category</label>
                                    <div class="input-wrapper">
                                        <select class="select-filter" name="category" style="width: 100%; height: 45px;" required>
                                            <option value="">Select Category</option>
                                            <option value="Infrastructure">Infrastructure & Facilities</option>
                                            <option value="Academics">Academics & Grading</option>
                                            <option value="Administration">Administrative Issues</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group" style="margin-bottom: 1.5rem;">
                                <label><i class="fa-solid fa-align-left"></i> Description</label>
                                <textarea name="desc" rows="4" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: var(--border-radius-sm); font-family: var(--font-primary);" required placeholder="Explain your grievance in detail..."></textarea>
                            </div>
                            <button type="submit" class="btn-submit-leave">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>Submit Grievance</span>
                            </button>
                        </form>
                    </div>

                    <!-- Grievances list -->
                    <div class="data-table-container">
                        <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">My Grievances</h3>
                        </div>
                        <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.5rem;">
                            <?php 
                            $my_grievances = array_filter($db['grievances'], function($g) use ($user) {
                                return $g['student_id'] === $user['username'];
                            });
                            
                            if (empty($my_grievances)): ?>
                                <p style="color:var(--text-muted); text-align:center;">You have not submitted any grievances yet.</p>
                            <?php else: foreach ($my_grievances as $g): ?>
                                <div style="border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 1.25rem; background: #fafafa;">
                                    <div style="display:flex; justify-content:space-between; margin-bottom: 1rem;">
                                        <div>
                                            <h4 style="font-size:1.1rem; font-weight:700; color:#111827; margin-bottom:0.25rem;"><?= htmlspecialchars($g['title']) ?></h4>
                                            <span class="notice-desc"><?= htmlspecialchars($g['category']) ?> • <?= htmlspecialchars($g['date']) ?></span>
                                        </div>
                                        <div>
                                            <span class="status-pill <?= strtolower(str_replace(' ', '-', $g['status'])) ?>"><?= htmlspecialchars($g['status']) ?></span>
                                        </div>
                                    </div>
                                    <p style="color:#374151; font-size:0.95rem; margin-bottom:1rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color);"><?= nl2br(htmlspecialchars($g['desc'])) ?></p>
                                    
                                    <?php if (!empty($g['replies'])): ?>
                                        <div style="display:flex; flex-direction:column; gap:0.75rem;">
                                            <h5 style="font-size:0.9rem; font-weight:600; color:#4b5563;">Responses:</h5>
                                            <?php foreach ($g['replies'] as $reply): ?>
                                                <div style="background: white; border-radius:var(--border-radius-sm); padding:1rem; border:1px solid var(--border-color);">
                                                    <div style="display:flex; justify-content:space-between; margin-bottom:0.5rem;">
                                                        <span style="font-weight:600; font-size:0.85rem; color:var(--primary-color);"><?= htmlspecialchars($reply['author']) ?> (<?= htmlspecialchars($reply['role']) ?>)</span>
                                                        <span style="font-size:0.8rem; color:var(--text-muted);"><?= htmlspecialchars($reply['date']) ?></span>
                                                    </div>
                                                    <div style="font-size:0.9rem; color:#374151;"><?= nl2br(htmlspecialchars($reply['message'])) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <p style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">No responses yet.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ATTENDANCE TRACKER PAGE                     -->
            <!-- ============================================ -->
            <div id="tab-attendance" class="app-view">
                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem;">
                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #dcfce7; color: #166534; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-chart-pie"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Overall Attendance</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.15rem 0;">87.5%</div>
                            <span style="display: inline-block; background: #dcfce7; color: #15803d; padding: 0.15rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">Safe (>75%)</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #dbeafe; color: #1e40af; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-chalkboard-user"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Total Conducted</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 0.15rem 0;">120</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Lectures held</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-user-check"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Lectures Attended</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #16a34a; margin: 0.15rem 0;">105</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Present in class</span>
                        </div>
                    </div>

                    <div style="background: white; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; align-items: center; gap: 1.25rem;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                            <i class="fa-solid fa-user-xmark"></i>
                        </div>
                        <div>
                            <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;">Missed Lectures</div>
                            <div style="font-size: 1.85rem; font-weight: 800; color: #dc2626; margin: 0.15rem 0;">15</div>
                            <span style="color: #64748b; font-size: 0.75rem;">Includes 4 approved leaves</span>
                        </div>
                    </div>
                </div>

                <!-- Subject-wise Breakdown Section -->
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);">
                    <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fa-solid fa-book-bookmark" style="color: #4f46e5;"></i> Subject-wise Attendance Breakdown
                    </h3>
                    
                    <?php
                    $student_subjects = [
                        [
                            'name' => 'Engineering Mathematics II (EM-II)',
                            'faculty' => 'Ms. Priyanka Patil',
                            'attendance' => '90.0%',
                            'attended' => 36,
                            'total' => 40,
                            'missed' => 4,
                            'color' => '#10b981'
                        ],
                        [
                            'name' => 'Engineering Physics (EP)',
                            'faculty' => 'Dr. Yogesh Sonawane',
                            'attendance' => '92.5%',
                            'attended' => 37,
                            'total' => 40,
                            'missed' => 3,
                            'color' => '#8b5cf6'
                        ],
                        [
                            'name' => 'Digital Systems Design & Architecture (DSDA)',
                            'faculty' => 'Dr. Ashwini Kumar Mishra',
                            'attendance' => '80.0%',
                            'attended' => 32,
                            'total' => 40,
                            'missed' => 8,
                            'color' => '#3b82f6'
                        ],
                        [
                            'name' => 'Foundations of C++ Programming (C++)',
                            'faculty' => 'Mr. Karan Jadhav',
                            'attendance' => '85.0%',
                            'attended' => 34,
                            'total' => 40,
                            'missed' => 6,
                            'color' => '#f59e0b'
                        ],
                        [
                            'name' => 'Fundamentals of Computer Systems & Networking (FCSN)',
                            'faculty' => 'Mr. Sumesh Shinde',
                            'attendance' => '87.5%',
                            'attended' => 35,
                            'total' => 40,
                            'missed' => 5,
                            'color' => '#ec4899'
                        ]
                    ];
                    ?>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
                        <?php foreach ($student_subjects as $ss): ?>
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;"><?php echo htmlspecialchars($ss['name']); ?></h4>
                                    <span style="font-size: 0.75rem; color: #64748b;"><i class="fa-solid fa-user-tie" style="margin-right: 4px;"></i><?php echo htmlspecialchars($ss['faculty']); ?></span>
                                </div>
                                <span style="font-size: 1.1rem; font-weight: 800; color: <?php echo $ss['color']; ?>;"><?php echo $ss['attendance']; ?></span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden; margin: 0.75rem 0;">
                                <div style="width: <?php echo $ss['attendance']; ?>; height: 100%; background: <?php echo $ss['color']; ?>; border-radius: 4px;"></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: #64748b;">
                                <span>Attended: <?php echo $ss['attended']; ?> / <?php echo $ss['total']; ?></span>
                                <span>Missed: <?php echo $ss['missed']; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Lecture Logs (When He Attended Lectures) -->
                <div style="background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 1rem;">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #1e293b; margin: 0; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-clock-rotate-left" style="color: #4f46e5;"></i> Lecture Attendance History Logs
                        </h3>
                        <div style="display: flex; gap: 0.75rem;">
                            <select class="select-filter" id="attendanceSubjectFilter" onchange="filterAttendanceLogs()" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #334155;">
                                <option value="all">All Subjects</option>
                                <option value="Engineering Mathematics II (EM-II)">Engineering Mathematics II (EM-II)</option>
                                <option value="Engineering Physics (EP)">Engineering Physics (EP)</option>
                                <option value="Digital Systems Design & Architecture (DSDA)">Digital Systems Design & Architecture (DSDA)</option>
                                <option value="Foundations of C++ Programming (C++)">Foundations of C++ Programming (C++)</option>
                                <option value="Fundamentals of Computer Systems & Networking (FCSN)">Fundamentals of Computer Systems & Networking (FCSN)</option>
                            </select>
                            <select class="select-filter" id="attendanceStatusFilter" onchange="filterAttendanceLogs()" style="padding: 0.45rem 0.85rem; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.85rem; color: #334155;">
                                <option value="all">All Statuses</option>
                                <option value="Present">Present Only</option>
                                <option value="Absent">Absent Only</option>
                                <option value="On Leave">On Leave Only</option>
                            </select>
                        </div>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="data-table" id="attendanceLogsTable">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Subject</th>
                                    <th>Lecture Topic / Unit</th>
                                    <th>Faculty Instructor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-subject="Engineering Mathematics II (EM-II)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">23 Jul 2026, 10:00 AM</td>
                                    <td>Engineering Mathematics II (EM-II)</td>
                                    <td>Matrices & Linear Algebra (Unit 4)</td>
                                    <td>Ms. Priyanka Patil</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Digital Systems Design & Architecture (DSDA)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">23 Jul 2026, 09:00 AM</td>
                                    <td>Digital Systems Design & Architecture (DSDA)</td>
                                    <td>Combinational Logic Circuits</td>
                                    <td>Dr. Ashwini Kumar Mishra</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Engineering Physics (EP)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">22 Jul 2026, 11:30 AM</td>
                                    <td>Engineering Physics (EP)</td>
                                    <td>Quantum Mechanics & Wave Theory</td>
                                    <td>Dr. Yogesh Sonawane</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Digital Systems Design & Architecture (DSDA)" data-status="Absent">
                                    <td style="font-weight: 600; color: #1e293b;">21 Jul 2026, 01:00 PM</td>
                                    <td>Digital Systems Design & Architecture (DSDA)</td>
                                    <td>Instruction Set Architecture</td>
                                    <td>Dr. Ashwini Kumar Mishra</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #fee2e2; color: #b91c1c; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-xmark" style="margin-right: 4px;"></i>Absent</span></td>
                                </tr>
                                <tr data-subject="Engineering Mathematics II (EM-II)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">20 Jul 2026, 03:00 PM</td>
                                    <td>Engineering Mathematics II (EM-II)</td>
                                    <td>Differential Equations Applications</td>
                                    <td>Ms. Priyanka Patil</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Engineering Physics (EP)" data-status="On Leave">
                                    <td style="font-weight: 600; color: #1e293b;">20 Jul 2026, 11:30 AM</td>
                                    <td>Engineering Physics (EP)</td>
                                    <td>Wave Optics & Laser Principles</td>
                                    <td>Dr. Yogesh Sonawane</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #fef3c7; color: #b45309; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-file-signature" style="margin-right: 4px;"></i>On Leave</span></td>
                                </tr>
                                <tr data-subject="Foundations of C++ Programming (C++)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">18 Jul 2026, 02:00 PM</td>
                                    <td>Foundations of C++ Programming (C++)</td>
                                    <td>Classes, Objects & Constructors</td>
                                    <td>Mr. Karan Jadhav</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Fundamentals of Computer Systems & Networking (FCSN)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">17 Jul 2026, 09:00 AM</td>
                                    <td>Fundamentals of Computer Systems & Networking (FCSN)</td>
                                    <td>OSI Reference Model & Networking Basics</td>
                                    <td>Mr. Sumesh Shinde</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                                <tr data-subject="Foundations of C++ Programming (C++)" data-status="Present">
                                    <td style="font-weight: 600; color: #1e293b;">16 Jul 2026, 11:30 AM</td>
                                    <td>Foundations of C++ Programming (C++)</td>
                                    <td>Pointers, References & Array Operations</td>
                                    <td>Mr. Karan Jadhav</td>
                                    <td><span style="display: inline-block; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;"><i class="fa-solid fa-check" style="margin-right: 4px;"></i>Present</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 5. STUDENT PROFILE PAGE                      -->
            <!-- ============================================ -->
            <!-- ============================================ -->
            <!-- 5. STUDENT PROFILE PAGE                      -->
            <!-- ============================================ -->
            <div id="tab-profile" class="app-view">
                <?php
                // Parse initials/first name/middle/last name if not already saved in profile_details
                $first_name_val = $profile_details['first_name'] ?? '';
                $middle_name_val = $profile_details['middle_name'] ?? '';
                $last_name_val = $profile_details['last_name'] ?? '';

                if (empty($first_name_val) && empty($last_name_val)) {
                    // Parse from full name
                    $name_parts = explode(' ', $current_student['name'] ?? '');
                    if (count($name_parts) >= 3) {
                        $last_name_val = $name_parts[0];
                        $first_name_val = $name_parts[1];
                        $middle_name_val = $name_parts[2];
                    } elseif (count($name_parts) == 2) {
                        $first_name_val = $name_parts[0];
                        $last_name_val = $name_parts[1];
                    } else {
                        $first_name_val = $current_student['name'] ?? '';
                    }
                }
                ?>
                <form action="student_dashboard.php" method="POST" id="studentProfileForm" class="settings-form-container" style="max-width: 1100px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 2rem; box-shadow: var(--box-shadow-subtle);">
                    <input type="hidden" name="action" value="save_profile">
                    <input type="hidden" name="current_active_profile_tab" id="current_active_profile_tab" value="personal">

                    <!-- Profile Header -->
                    <div class="profile-main-header" style="display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; margin-bottom: 1.5rem;">
                        <?= get_initials_avatar($current_student['name'], 100, 36, 4) ?>
                        <div>
                            <h2 style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.5rem 0;"><?= htmlspecialchars($current_student['name']) ?></h2>
                            <span class="status-pill graded" style="font-size: 0.85rem; padding: 0.25rem 0.75rem; background: #e0e7ff; color: #4f46e5; border-radius: 9999px; font-weight: 600;">Active Student</span>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.95rem;">PRN: <span style="font-weight: 700; color: #4f46e5;"><?= htmlspecialchars($current_student['prn']) ?></span> | ID: <?= htmlspecialchars($current_student['username']) ?> | <?= htmlspecialchars($current_student['dept']) ?></p>
                        </div>
                        <div style="margin-left: auto;">
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 1.5rem; font-size: 0.9rem; margin-top: 0; display: inline-flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-floppy-disk"></i><span>Save All Changes</span>
                            </button>
                        </div>
                    </div>

                    <!-- Custom Tab Bar -->
                    <div class="profile-details-tabs-bar" style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 2rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.75rem;">
                        <button type="button" class="profile-details-tab-btn active" data-tab-target="personal" onclick="switchProfileTab('personal')">PERSONAL DETAILS</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="identity" onclick="switchProfileTab('identity')">IDENTITY</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="religion" onclick="switchProfileTab('religion')">RELIGION</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="handicap" onclick="switchProfileTab('handicap')">PHYSICALLY HANDICAPPED</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="minority" onclick="switchProfileTab('minority')">MINORITY DETAILS</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="passport" onclick="switchProfileTab('passport')">PASSPORT DETAILS</button>
                        <button type="button" class="profile-details-tab-btn" data-tab-target="exams" onclick="switchProfileTab('exams')">EXAMINATION DETAILS</button>
                    </div>

                    <!-- 1. PERSONAL DETAILS -->
                    <div class="profile-details-section active" id="profile-details-sec-personal" style="display: block;">
                        <div class="form-grid-3">
                            <div class="form-group-col">
                                <label>First Name <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-pen"></i>
                                    <input type="text" name="first_name" required value="<?= htmlspecialchars($first_name_val) ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Middle Name <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-pen"></i>
                                    <input type="text" name="middle_name" required value="<?= htmlspecialchars($middle_name_val) ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Last Name <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-pen"></i>
                                    <input type="text" name="last_name" required value="<?= htmlspecialchars($last_name_val) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Email(Official) <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" readonly value="<?= htmlspecialchars($current_student['email']) ?>" style="background: #f1f5f9; cursor: not-allowed;">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Category <span style="color:red;">*</span></label>
                                <select name="category" required>
                                    <option value="">Select Category</option>
                                    <?php
                                    $categories = ['General', 'OBC', 'SC', 'ST', 'EWS', 'SBC', 'VJNT'];
                                    $current_cat = $profile_details['category'] ?? '';
                                    foreach ($categories as $cat) {
                                        $selected = (strcasecmp($current_cat, $cat) === 0) ? 'selected' : '';
                                        echo "<option value=\"$cat\" $selected>$cat</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Cast <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-users"></i>
                                    <input type="text" name="cast" required value="<?= htmlspecialchars($profile_details['cast'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Sub Caste <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-users-line"></i>
                                    <input type="text" name="sub_caste" required value="<?= htmlspecialchars($profile_details['sub_caste'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Nationality <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-flag"></i>
                                    <input type="text" name="nationality" required value="<?= htmlspecialchars($profile_details['nationality'] ?? 'Indian') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Domicile <span style="color:red;">*</span></label>
                                <select name="domicile" required>
                                    <option value="">Select Domicile State</option>
                                    <?php
                                    $states = ['Maharashtra', 'Gujarat', 'Delhi', 'Karnataka', 'Tamil Nadu', 'Uttar Pradesh', 'Other'];
                                    $current_dom = $profile_details['domicile'] ?? '';
                                    foreach ($states as $st) {
                                        $selected = (strcasecmp($current_dom, $st) === 0) ? 'selected' : '';
                                        echo "<option value=\"$st\" $selected>$st</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Mobile Number <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-phone"></i>
                                    <input type="text" id="personal_mobile" name="mobile_number" required value="<?= htmlspecialchars($current_student['phone'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_personal_mobile" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                            <div class="form-group-col">
                                <label>Birth Place (Strictly as per LC) <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <input type="text" name="birth_place" required value="<?= htmlspecialchars($profile_details['birth_place'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Birth Country <span style="color:red;">*</span></label>
                                <select name="birth_country" required>
                                    <option value="India" selected>India</option>
                                    <option value="USA">USA</option>
                                    <option value="UK">UK</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Birth State <span style="color:red;">*</span></label>
                                <select name="birth_state" required>
                                    <option value="">Select State</option>
                                    <?php
                                    $current_bstate = $profile_details['birth_state'] ?? '';
                                    foreach ($states as $st) {
                                        $selected = (strcasecmp($current_bstate, $st) === 0) ? 'selected' : '';
                                        echo "<option value=\"$st\" $selected>$st</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Birth District <span style="color:red;">*</span></label>
                                <select name="birth_district" required>
                                    <option value="">Select District</option>
                                    <?php
                                    $districts = ['Jalgaon', 'Pune', 'Mumbai', 'Nashik', 'Nagpur', 'Aurangabad', 'Other'];
                                    $current_bdist = $profile_details['birth_district'] ?? '';
                                    foreach ($districts as $d) {
                                        $selected = (strcasecmp($current_bdist, $d) === 0) ? 'selected' : '';
                                        echo "<option value=\"$d\" $selected>$d</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Native Place <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-house-chimney"></i>
                                    <input type="text" name="native_place" required value="<?= htmlspecialchars($profile_details['native_place'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Native Country <span style="color:red;">*</span></label>
                                <select name="native_country" required>
                                    <option value="India" selected>India</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Native State <span style="color:red;">*</span></label>
                                <select name="native_state" required>
                                    <option value="">Select Native State</option>
                                    <?php
                                    $current_nstate = $profile_details['native_state'] ?? '';
                                    foreach ($states as $st) {
                                        $selected = (strcasecmp($current_nstate, $st) === 0) ? 'selected' : '';
                                        echo "<option value=\"$st\" $selected>$st</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Native District <span style="color:red;">*</span></label>
                                <select name="native_district" required>
                                    <option value="">Select Native District</option>
                                    <?php
                                    $current_ndist = $profile_details['native_district'] ?? '';
                                    foreach ($districts as $d) {
                                        $selected = (strcasecmp($current_ndist, $d) === 0) ? 'selected' : '';
                                        echo "<option value=\"$d\" $selected>$d</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Primary Email (Personal) <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="primary_email" name="primary_email" required value="<?= htmlspecialchars($profile_details['primary_email'] ?? '') ?>" oninput="validateEmails()">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Alternate Email <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="alternate_email" name="alternate_email" required value="<?= htmlspecialchars($profile_details['alternate_email'] ?? '') ?>" oninput="validateEmails()">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Blood Group <span style="color:red;">*</span></label>
                                <select name="blood_group" required>
                                    <option value="">Select Blood Group</option>
                                    <?php
                                    $b_groups = ['A+ve', 'A-ve', 'B+ve', 'B-ve', 'O+ve', 'O-ve', 'AB+ve', 'AB-ve'];
                                    $current_bg = $profile_details['blood_group'] ?? '';
                                    foreach ($b_groups as $bg) {
                                        $selected = (strcasecmp($current_bg, $bg) === 0) ? 'selected' : '';
                                        echo "<option value=\"$bg\" $selected>$bg</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Anti Ragging Undertaking No <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-shield-halved"></i>
                                    <input type="text" name="anti_ragging_no" required value="<?= htmlspecialchars($profile_details['anti_ragging_no'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Earning Parent Name (last first middle) <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-user-tie"></i>
                                    <input type="text" name="earning_parent_name" required value="<?= htmlspecialchars($profile_details['earning_parent_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Earning Parent Relation <span style="color:red;">*</span></label>
                                <select name="earning_parent_relation" required>
                                    <option value="">Select Relation</option>
                                    <?php
                                    $relations = ['Father', 'Mother', 'Guardian'];
                                    $current_rel = $profile_details['earning_parent_relation'] ?? '';
                                    foreach ($relations as $rel) {
                                        $selected = (strcasecmp($current_rel, $rel) === 0) ? 'selected' : '';
                                        echo "<option value=\"$rel\" $selected>$rel</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid-3" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Career Choice <span style="color:red;">*</span></label>
                                <select name="career_choice" required>
                                    <option value="">Select Career Choice</option>
                                    <?php
                                    $careers = ['Job / Placement', 'Higher Studies', 'Entrepreneurship', 'Civil Services', 'Other'];
                                    $current_car = $profile_details['career_choice'] ?? '';
                                    foreach ($careers as $car) {
                                        $selected = (strcasecmp($current_car, $car) === 0) ? 'selected' : '';
                                        echo "<option value=\"$car\" $selected>$car</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Alumni Institute <span style="color:red;">*</span></label>
                                <select name="alumni_institute" required>
                                    <option value="No" <?= ($profile_details['alumni_institute'] ?? 'No') === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($profile_details['alumni_institute'] ?? 'No') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('identity')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 2. IDENTITY -->
                    <div class="profile-details-section" id="profile-details-sec-identity" style="display: none;">
                        <div class="form-grid-2">
                            <div class="form-group-col">
                                <label>Aadhaar Card Number</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-address-card"></i>
                                    <input type="text" id="identity_aadhaar" name="identity_details[aadhaar_no]" value="<?= htmlspecialchars($profile_details['identity_details']['aadhaar_no'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_identity_aadhaar" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                            <div class="form-group-col">
                                <label>PAN Card Number</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-id-card-clip"></i>
                                    <input type="text" id="identity_pan" name="identity_details[pan_no]" value="<?= htmlspecialchars($profile_details['identity_details']['pan_no'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_identity_pan" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                        </div>
                        <div class="form-grid-2" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Voter ID Card Number</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-check-to-slot"></i>
                                    <input type="text" id="identity_voter_id" name="identity_details[voter_id]" value="<?= htmlspecialchars($profile_details['identity_details']['voter_id'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_identity_voter_id" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                            <div class="form-group-col">
                                <label>Driving License Number</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-car"></i>
                                    <input type="text" id="identity_driving_license" name="identity_details[driving_license]" value="<?= htmlspecialchars($profile_details['identity_details']['driving_license'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_identity_driving_license" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('personal')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('religion')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 3. RELIGION -->
                    <div class="profile-details-section" id="profile-details-sec-religion" style="display: none;">
                        <div class="form-grid-3">
                            <div class="form-group-col">
                                <label>Religion</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-place-of-worship"></i>
                                    <input type="text" name="religion_details[religion]" value="<?= htmlspecialchars($profile_details['religion_details']['religion'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Mother Tongue</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-language"></i>
                                    <input type="text" name="religion_details[mother_tongue]" value="<?= htmlspecialchars($profile_details['religion_details']['mother_tongue'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Caste Category</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-layer-group"></i>
                                    <input type="text" name="religion_details[caste_category]" value="<?= htmlspecialchars($profile_details['religion_details']['caste_category'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('identity')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('handicap')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 4. PHYSICALLY HANDICAPPED -->
                    <div class="profile-details-section" id="profile-details-sec-handicap" style="display: none;">
                        <div class="form-grid-3">
                            <div class="form-group-col">
                                <label>Is Physically Handicapped?</label>
                                <select name="handicap_details[is_handicapped]">
                                    <option value="No" <?= ($profile_details['handicap_details']['is_handicapped'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($profile_details['handicap_details']['is_handicapped'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Disability Type</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-wheelchair"></i>
                                    <input type="text" name="handicap_details[disability_type]" value="<?= htmlspecialchars($profile_details['handicap_details']['disability_type'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-group-col">
                                <label>Disability Percentage (%)</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-percent"></i>
                                    <input type="text" id="handicap_percentage" name="handicap_details[disability_percentage]" value="<?= htmlspecialchars($profile_details['handicap_details']['disability_percentage'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_handicap_percentage" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('religion')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('minority')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 5. MINORITY DETAILS -->
                    <div class="profile-details-section" id="profile-details-sec-minority" style="display: none;">
                        <div class="form-grid-2">
                            <div class="form-group-col">
                                <label>Is Minority?</label>
                                <select name="minority_details[is_minority]">
                                    <option value="No" <?= ($profile_details['minority_details']['is_minority'] ?? '') === 'No' ? 'selected' : '' ?>>No</option>
                                    <option value="Yes" <?= ($profile_details['minority_details']['is_minority'] ?? '') === 'Yes' ? 'selected' : '' ?>>Yes</option>
                                </select>
                            </div>
                            <div class="form-group-col">
                                <label>Minority Type</label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-users-viewfinder"></i>
                                    <input type="text" name="minority_details[minority_type]" value="<?= htmlspecialchars($profile_details['minority_details']['minority_type'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('handicap')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('passport')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 6. PASSPORT DETAILS -->
                    <div class="profile-details-section" id="profile-details-sec-passport" style="display: none;">
                        <div class="form-grid-2">
                            <div class="form-group-col">
                                <label>Passport Number <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-passport"></i>
                                    <input type="text" id="passport_number" name="passport_details[passport_no]" required maxlength="9" value="<?= htmlspecialchars($profile_details['passport_details']['passport_no'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_passport_number" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                            <div class="form-group-col">
                                <label>Place of Issue <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-location-arrow"></i>
                                    <input type="text" id="passport_place_of_issue" name="passport_details[place_of_issue]" required value="<?= htmlspecialchars($profile_details['passport_details']['place_of_issue'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_passport_place_of_issue" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                        </div>
                        <div class="form-grid-2" style="margin-top: 1rem;">
                            <div class="form-group-col">
                                <label>Issue Date <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <input type="date" id="passport_issue_date" name="passport_details[issue_date]" required value="<?= htmlspecialchars($profile_details['passport_details']['issue_date'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_passport_issue_date" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                            <div class="form-group-col">
                                <label>Expiry Date <span style="color:red;">*</span></label>
                                <div class="input-with-icon">
                                    <i class="fa-solid fa-calendar-xmark"></i>
                                    <input type="date" id="passport_expiry_date" name="passport_details[expiry_date]" required value="<?= htmlspecialchars($profile_details['passport_details']['expiry_date'] ?? '') ?>">
                                </div>
                                <span class="error-msg-span" id="err_passport_expiry_date" style="color: #ef4444; font-size: 0.8rem; display: none; margin-top: 0.25rem; font-weight: 500;"></span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('minority')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="button" id="btn_save_passport" class="btn-login" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: var(--primary-color);">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save Details
                            </button>
                            <button type="button" class="btn-login" onclick="switchProfileTab('exams')" style="width: auto; padding: 0.75rem 2rem; font-size: 0.95rem; margin-top: 0; background: #10b981; border-color: #10b981;">
                                Next Tab <i class="fa-solid fa-arrow-right" style="margin-left: 6px;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- 7. EXAMINATION DETAILS -->
                    <div class="profile-details-section" id="profile-details-sec-exams" style="display: none;">
                        <h4 style="margin: 0 0 1rem 0; color: #4f46e5; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; font-size: 1.1rem; font-weight: 700;">SSC (10th) Records</h4>
                        <div class="form-grid-3">
                            <div class="form-group-col">
                                <label>Board/University Name</label>
                                <input type="text" name="exam_details[ssc_board]" value="<?= htmlspecialchars($profile_details['exam_details']['ssc_board'] ?? '') ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Passing Year</label>
                                <input type="number" name="exam_details[ssc_year]" value="<?= htmlspecialchars($profile_details['exam_details']['ssc_year'] ?? '') ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Percentage / CGPA Obtained</label>
                                <input type="text" name="exam_details[ssc_marks]" value="<?= htmlspecialchars($profile_details['exam_details']['ssc_marks'] ?? '') ?>">
                            </div>
                        </div>

                        <h4 style="margin: 2rem 0 1rem 0; color: #4f46e5; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; font-size: 1.1rem; font-weight: 700;">HSC / Diploma (12th) Records</h4>
                        <div class="form-grid-3">
                            <div class="form-group-col">
                                <label>Board/University Name</label>
                                <input type="text" name="exam_details[hsc_board]" value="<?= htmlspecialchars($profile_details['exam_details']['hsc_board'] ?? '') ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Passing Year</label>
                                <input type="number" name="exam_details[hsc_year]" value="<?= htmlspecialchars($profile_details['exam_details']['hsc_year'] ?? '') ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Percentage / CGPA Obtained</label>
                                <input type="text" name="exam_details[hsc_marks]" value="<?= htmlspecialchars($profile_details['exam_details']['hsc_marks'] ?? '') ?>">
                            </div>
                        </div>

                        <h4 style="margin: 2rem 0 1rem 0; color: #4f46e5; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; font-size: 1.1rem; font-weight: 700;">Other Graduation / Last Examination</h4>
                        <div class="form-grid-2">
                            <div class="form-group-col">
                                <label>Examination Name</label>
                                <input type="text" name="exam_details[last_exam_name]" value="<?= htmlspecialchars($profile_details['exam_details']['last_exam_name'] ?? '') ?>">
                            </div>
                            <div class="form-group-col">
                                <label>Percentage / CGPA Obtained</label>
                                <input type="text" name="exam_details[last_exam_marks]" value="<?= htmlspecialchars($profile_details['exam_details']['last_exam_marks'] ?? '') ?>">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; margin-top: 2rem; gap: 1rem;">
                            <button type="button" class="btn-secondary" onclick="switchProfileTab('passport')" style="padding: 0.75rem 2rem;">Back</button>
                            <button type="submit" class="btn-login" style="width: auto; padding: 0.75rem 2.5rem; font-size: 0.95rem; margin-top: 0; background: #4f46e5; border-color: #4f46e5;">
                                <i class="fa-solid fa-floppy-disk" style="margin-right: 6px;"></i>Save All Profile Details
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ============================================ -->
            <!-- MOCK TABS PANEL                              -->
            <!-- ============================================ -->
            <div id="tab-mock" class="app-view">
                <div class="mock-page-container">
                    <div class="mock-page-icon" id="mockPageIcon">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <h3 id="mockPageTitle">Dashboard Summary</h3>
                    <p id="mockPageDesc">This panel displays real-time statistics and summaries related to student profile metrics. Feel free to navigate back to the Notices, Assignments, or Leave Requests panels for live mock interactive elements.</p>
                </div>
            </div>

        </main>
    </div>

    <!-- ============================================ -->
    <!-- ASSIGNMENT UPLOAD MODAL                      -->
    <!-- ============================================ -->
    <div id="assignmentUploadModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="modal-card" style="background: white; border-radius: 12px; max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: var(--box-shadow-lg); position: relative;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem;">
                <h3 id="uploadModalTitle" style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;">Upload Subject Assignment</h3>
                <button type="button" class="btn-close-modal" onclick="closeUploadModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #94a3b8;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <!-- Read-only Assignment Details Section -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: #334155;">
                <h4 style="margin: 0 0 0.5rem 0; color: #4f46e5; font-size: 0.9rem; font-weight: 700;">Assignment Details</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <div><strong>Subject:</strong> <span id="dt_subject">C++</span></div>
                    <div><strong>Unit:</strong> <span id="dt_unit">Unit 1</span></div>
                    <div><strong>Title:</strong> <span id="dt_title">Loops</span></div>
                    <div><strong>Due Date:</strong> <span id="dt_due">date</span></div>
                    <div><strong>Faculty:</strong> <span id="dt_faculty">Faculty Name</span></div>
                </div>
                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #e2e8f0;">
                    <strong>Instructions:</strong>
                    <p id="dt_instructions" style="margin: 0.25rem 0 0 0; color: #64748b; line-height: 1.4;"></p>
                </div>
            </div>
            
            <form id="assignmentUploadForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_subject_assignment">
                <input type="hidden" name="subject_assignment_id" id="upload_sa_id">
                
                <div class="drag-drop-zone" id="assignmentDropZone" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; background: #f8fafc; transition: all 0.2s; margin-bottom: 1.25rem;">
                    <i class="fa-solid fa-cloud-arrow-up" style="font-size: 2.5rem; color: #8b5cf6; margin-bottom: 1rem; display: block;"></i>
                    <p style="font-weight: 600; color: #334155; margin-bottom: 0.25rem;">Choose file or drag & drop here</p>
                    <span style="font-size: 0.75rem; color: #64748b; display: block;">PDF, DOC, DOCX, JPG, JPEG, PNG (Max 20 MB)</span>
                    <input type="file" id="assignmentFileInput" name="assignment_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required style="display: none;">
                </div>
                
                <!-- File info block with Remove Option -->
                <div id="uploadFileInfo" style="display: none; margin-top: 1rem; padding: 0.75rem; background: #e0f2fe; color: #0369a1; border-radius: 6px; font-size: 0.85rem; font-weight: 500; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <i class="fa-solid fa-file" style="margin-right: 4px;"></i> <span id="uploadFileName">filename.pdf</span>
                    </div>
                    <button type="button" onclick="removeSelectedFile()" style="background: none; border: none; color: #ef4444; font-weight: 700; cursor: pointer; font-size: 0.85rem;">Remove File</button>
                </div>

                <!-- Preview Box (For PDF / Images) -->
                <div id="submissionPreviewContainer" style="display: none; margin-bottom: 1.25rem; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0.5rem; background: #fafafa;">
                    <span style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; margin-bottom: 0.5rem;">File Preview</span>
                    <div id="previewPane" style="display: flex; justify-content: center; align-items: center; max-height: 250px; overflow: hidden; border-radius: 6px;">
                        <!-- dynamic img or pdf iframe -->
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div id="uploadProgressBarContainer" style="display: none; margin-top: 1.5rem; margin-bottom: 1.25rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #64748b; margin-bottom: 4px; font-weight: 600;">
                        <span>Uploading...</span>
                        <span id="uploadProgressPercent">0%</span>
                    </div>
                    <div style="background: #e2e8f0; border-radius: 4px; height: 8px; overflow: hidden; width: 100%;">
                        <div id="uploadProgressBar" style="background: #8b5cf6; height: 100%; width: 0%; transition: width 0.1s ease;"></div>
                    </div>
                </div>
                
                <!-- Success Status -->
                <div id="uploadSuccessMessage" style="display: none; margin-top: 1.5rem; padding: 1rem; background: #dcfce7; border: 1px solid #bbf7d0; border-radius: 8px; color: #15803d; text-align: center; margin-bottom: 1.25rem;">
                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-circle-check"></i></div>
                    <div style="font-weight: 700; margin-bottom: 0.25rem;">✓ Assignment submitted successfully.</div>
                    <div id="uploadSuccessTime" style="font-size: 0.8rem; color: #16a34a;">Date & Time: --</div>
                </div>

                <!-- Student Declaration -->
                <div style="margin-bottom: 1.25rem; display: flex; gap: 0.5rem; align-items: flex-start; text-align: left;">
                    <input type="checkbox" id="declarationCheck" onchange="validateFormSubmission()" style="margin-top: 3px; cursor: pointer;">
                    <label for="declarationCheck" style="font-size: 0.85rem; color: #334155; cursor: pointer; font-weight: 500; line-height: 1.4;">I confirm this assignment is my own work.</label>
                </div>
                
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" id="submitAssignmentBtn" disabled class="btn-login" style="width: auto; padding: 0.65rem 1.5rem; font-size: 0.9rem; margin-top: 0; background: #8b5cf6; border-color: #8b5cf6; opacity: 0.5; cursor: not-allowed;">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span>Submit Assignment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- RAISE GRIEVANCE MODAL                        -->
    <!-- ============================================ -->
    <div id="assignmentGrievanceModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
        <div class="modal-card" style="background: white; border-radius: 12px; max-width: 500px; width: 90%; padding: 2rem; box-shadow: var(--box-shadow-lg); position: relative;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 700; color: #1e293b;">Raise Assignment Grievance</h3>
                <button type="button" class="btn-close-modal" onclick="closeGrievanceModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #94a3b8;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <p style="color: #ef4444; font-size: 0.8rem; font-weight: 600; margin-bottom: 1.5rem; line-height: 1.4;">
                * Important: Grievances must be related ONLY to the faculty's uploaded question/assignment file (e.g. blurred, corrupted, wrong file). No student upload issues allowed.
            </p>
            
            <form id="assignmentGrievanceForm" method="POST" action="student_dashboard.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="submit_assignment_grievance">
                <input type="hidden" name="subject_assignment_id" id="grievance_sa_id">
                
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Subject</label>
                    <input type="text" id="grievance_subject" readonly style="background: #f1f5f9; cursor: not-allowed; border: 1px solid #cbd5e1; padding: 0.6rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; color: #475569; outline: none;">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Assignment</label>
                    <input type="text" id="grievance_assignment" readonly style="background: #f1f5f9; cursor: not-allowed; border: 1px solid #cbd5e1; padding: 0.6rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; color: #475569; outline: none;">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Issue Type <span style="color: red;">*</span></label>
                    <select name="issue_type" required style="border: 1px solid #cbd5e1; padding: 0.6rem; border-radius: 6px; outline: none; background: white; font-size: 0.9rem;">
                        <option value="">Select Issue Category</option>
                        <option value="Question PDF is blurred.">Question PDF is blurred.</option>
                        <option value="PDF is corrupted.">PDF is corrupted.</option>
                        <option value="Image is not visible.">Image is not visible.</option>
                        <option value="Wrong assignment uploaded.">Wrong assignment uploaded.</option>
                        <option value="Incorrect subject.">Incorrect subject.</option>
                        <option value="Missing pages.">Missing pages.</option>
                        <option value="Download not working.">Download not working.</option>
                        <option value="Wrong due date.">Wrong due date.</option>
                        <option value="Other issue.">Other issue.</option>
                    </select>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Description <span style="color: red;">*</span></label>
                    <textarea name="description" required rows="4" placeholder="Detail the issue with the question document..." style="border: 1px solid #cbd5e1; padding: 0.6rem; border-radius: 6px; outline: none; resize: vertical; font-size: 0.9rem; font-family: inherit;"></textarea>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 0.25rem; margin-bottom: 1.5rem;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #475569;">Upload Screenshot (Optional)</label>
                    <input type="file" name="screenshot" accept="image/*" style="border: 1px solid #cbd5e1; padding: 0.4rem; border-radius: 6px; font-size: 0.85rem; outline: none;">
                </div>
                
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                    <button type="button" class="btn-secondary" onclick="closeGrievanceModal()">Cancel</button>
                    <button type="submit" class="btn-login" style="width: auto; padding: 0.65rem 1.5rem; font-size: 0.9rem; margin-top: 0; background: #dc2626; border-color: #dc2626;">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Submit Grievance</span>
                    </button>
                </div>
            </form>
        </div>
    </div></div>

    <!-- JavaScript code for navigation, modal interaction and drag-drop selection -->
    <script>
        const portalSelectedFiles = {};

        function toggleSubjectDetails(unitNum, subjectId) {
            const body = document.getElementById(`subject-body-${unitNum}-${subjectId}`);
            const arrow = document.querySelector(`.subject-arrow-icon-${unitNum}-${subjectId}`);
            if (body.style.display === 'none' || !body.style.display) {
                body.style.display = 'block';
                arrow.style.transform = 'rotate(90deg)';
            } else {
                body.style.display = 'none';
                arrow.style.transform = 'rotate(0deg)';
            }
        }

        function showPortalUploadForm(saId) {
            document.getElementById(`upload-form-wrapper-${saId}`).style.display = 'block';
            const portalContainer = document.getElementById(`submission-portal-container-${saId}`);
            // Hide the submitted view if toggling to replace
            const firstChild = portalContainer.children[0];
            if (firstChild && firstChild.id !== `upload-form-wrapper-${saId}`) {
                firstChild.style.display = 'none';
            }
        }

        function handlePortalFileSelect(input, saId) {
            const file = input.files[0];
            if (file) {
                setPortalFile(file, saId);
            }
        }

        function handlePortalFileDrop(event, saId) {
            event.preventDefault();
            document.getElementById(`drag-drop-zone-${saId}`).style.borderColor = '#cbd5e1';
            const file = event.dataTransfer.files[0];
            if (file) {
                setPortalFile(file, saId);
            }
        }

        function setPortalFile(file, saId) {
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            if (!allowed.includes(ext)) {
                showToastNotification('Only PDF, DOC, DOCX, JPG, JPEG, and PNG files are allowed.', 'error');
                return;
            }
            if (file.size > 20 * 1024 * 1024) {
                showToastNotification('Maximum file size allowed is 20 MB.', 'error');
                return;
            }
            portalSelectedFiles[saId] = file;
            const preview = document.getElementById(`file-preview-container-${saId}`);
            preview.querySelector('.selected-file-name').textContent = file.name;
            preview.querySelector('.selected-file-size').textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            preview.style.display = 'flex';
        }

        function clearPortalSelectedFile(saId) {
            delete portalSelectedFiles[saId];
            document.getElementById(`file-preview-container-${saId}`).style.display = 'none';
            document.getElementById(`portal-file-input-${saId}`).value = '';
        }

        function handlePortalAssignmentUpload(event, saId) {
            event.preventDefault();
            const file = portalSelectedFiles[saId];
            const commentsElement = document.getElementById(`portal-comments-${saId}`);
            const comments = commentsElement ? commentsElement.value.trim() : '';
            
            if (!file && !comments) {
                showToastNotification('Please select a file or add a comment to submit.', 'error');
                return;
            }
            
            const progressContainer = document.getElementById(`progress-container-${saId}`);
            if (progressContainer) {
                progressContainer.style.display = 'block';
                const progressBar = progressContainer.querySelector(`.progress-bar-fill-${saId}`);
                const progressPercent = progressContainer.querySelector(`.progress-percentage-${saId}`);
                if (progressBar) progressBar.style.width = '0%';
                if (progressPercent) progressPercent.textContent = '0%';
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_subject_assignment');
            formData.append('subject_assignment_id', saId);
            if (file) {
                formData.append('assignment_file', file);
            }
            if (comments) {
                formData.append('comments', comments);
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'student_dashboard.php', true);
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressPercent.textContent = percent + '%';
                }
            });
            xhr.onreadystatechange = function() {
                if (xhr.readyState === XMLHttpRequest.DONE) {
                    if (xhr.status === 200) {
                        try {
                            const res = JSON.parse(xhr.responseText);
                            if (res.success) {
                                showToastNotification('Assignment uploaded successfully.', 'success');
                                setTimeout(() => { window.location.reload(); }, 1500);
                            } else {
                                showToastNotification(res.message || 'Upload failed.', 'error');
                            }
                        } catch(err) {
                            showToastNotification('Server response parse failed.', 'error');
                        }
                    } else {
                        showToastNotification('An error occurred during submission.', 'error');
                    }
                }
            };
            xhr.send(formData);
        }

        function openSubjectGrievanceModal(saId, subjectName, assignmentTitle) {
            openGrievanceModal(saId, subjectName, assignmentTitle);
        }

        // Switch between dashboard tabs
        function switchTab(tabName, element) {
            // Update active states in navigation
            const items = document.querySelectorAll('.sidebar-nav-item');
            items.forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            // Hide all panels
            const panels = document.querySelectorAll('.app-view');
            panels.forEach(p => p.classList.remove('active'));

            const headerTitle = document.getElementById('currentTabTitle');
            const headerSubtitle = document.getElementById('currentTabSubtitle');

            // Show selected panel or show mock panel with custom descriptors
            if (tabName === 'notices') {
                document.getElementById('tab-notices').classList.add('active');
                headerTitle.textContent = "Notices";
                headerSubtitle.textContent = "Stay updated with the latest announcements and important information.";
            } else if (tabName === 'assignments') {
                document.getElementById('tab-assignments').classList.add('active');
                headerTitle.textContent = "Assignments";
                headerSubtitle.textContent = "View your unit assignments and upload your finished answers.";
            } else if (tabName === 'leaves') {
                document.getElementById('tab-leaves').classList.add('active');
                headerTitle.textContent = "Leave Requests";
                headerSubtitle.textContent = "Apply for college leave by submitting your verified leave form.";
            } else if (tabName === 'grievance') {
                document.getElementById('tab-grievance').classList.add('active');
                headerTitle.textContent = "Grievance";
                headerSubtitle.textContent = "Submit issues or report institutional suggestions.";
            } else if (tabName === 'dashboard') {
                document.getElementById('tab-dashboard').classList.add('active');
                headerTitle.textContent = "Dashboard";
                headerSubtitle.textContent = "Quick access to all essential student portals and services.";
            } else if (tabName === 'attendance') {
                document.getElementById('tab-attendance').classList.add('active');
                headerTitle.textContent = "Attendance Tracker";
                headerSubtitle.textContent = "Monitor overall attendance, subject-wise statistics, and lecture history.";
            } else if (tabName === 'profile') {
                document.getElementById('tab-profile').classList.add('active');
                headerTitle.textContent = "My Profile";
                headerSubtitle.textContent = "View and manage your academic profile credentials.";
            } else {
                // Show mock templates
                const mockPanel = document.getElementById('tab-mock');
                mockPanel.classList.add('active');

                const titleText = document.getElementById('mockPageTitle');
                const descText = document.getElementById('mockPageDesc');
                const iconBox = document.getElementById('mockPageIcon');

                headerTitle.textContent = tabName.charAt(0).toUpperCase() + tabName.slice(1);
                headerSubtitle.textContent = `Access student ${tabName} records and configuration setups.`;

                // Update mock details
                titleText.textContent = tabName.toUpperCase();
                
                if (tabName === 'profile') {
                    iconBox.innerHTML = '<i class="fa-solid fa-id-card"></i>';
                    descText.textContent = "Prasad Kulkarni | Student ID: 125UIT1080 | Department of Information Technology (IT-A2). Academic profile status, emergency contact info, and registration logs are managed inside this panel.";
                }
            }
        }

        // Leave Requests file input rendering
        function handleFileSelect(event) {
            const input = event.target;
            if (input.files && input.files[0]) {
                const file = input.files[0];
                document.getElementById('displayFileName').textContent = file.name;
                document.getElementById('fallbackFileName').value = file.name; // Keep name string
                document.getElementById('leaveDropZone').style.display = 'none';
                document.getElementById('fileDisplayArea').style.display = 'flex';
            }
        }

        function removeSelectedFile() {
            document.getElementById('leaveFileInput').value = '';
            document.getElementById('fallbackFileName').value = '';
            document.getElementById('leaveDropZone').style.display = 'block';
            document.getElementById('fileDisplayArea').style.display = 'none';
        }

        // Drag & Drop event bindings for Leave Requests
        const dropZone = document.getElementById('leaveDropZone');
        if (dropZone) {
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.add('dragover');
                }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, (e) => {
                    e.preventDefault();
                    dropZone.classList.remove('dragover');
                }, false);
            });
            dropZone.addEventListener('drop', (e) => {
                const dt = e.dataTransfer;
                const files = dt.files;
                if (files.length > 0) {
                    document.getElementById('leaveFileInput').files = files;
                    document.getElementById('displayFileName').textContent = files[0].name;
                    document.getElementById('fallbackFileName').value = files[0].name;
                    dropZone.style.display = 'none';
                    document.getElementById('fileDisplayArea').style.display = 'flex';
                }
            });
        }

        // Unit accordion toggle
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.assignment-unit-row').forEach(row => {
                row.addEventListener('click', function() {
                    const unit = this.getAttribute('data-unit');
                    const detailsRow = document.getElementById(`unit-details-${unit}`);
                    const arrow = this.querySelector('.accordion-arrow');
                    if (detailsRow) {
                        if (detailsRow.style.display === 'none') {
                            detailsRow.style.display = 'table-row';
                            if (arrow) arrow.style.transform = 'rotate(180deg)';
                        } else {
                            detailsRow.style.display = 'none';
                            if (arrow) arrow.style.transform = 'rotate(0deg)';
                        }
                    }
                });
            });

            // File upload drag & drop and change bindings
            const assignmentDropZone = document.getElementById('assignmentDropZone');
            const assignmentFileInput = document.getElementById('assignmentFileInput');
            if (assignmentDropZone && assignmentFileInput) {
                assignmentDropZone.addEventListener('click', () => assignmentFileInput.click());
                
                assignmentFileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        displaySelectedAssignmentFile(this.files[0]);
                    }
                });
                
                assignmentDropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    assignmentDropZone.style.borderColor = '#8b5cf6';
                    assignmentDropZone.style.background = '#f5f3ff';
                });
                
                assignmentDropZone.addEventListener('dragleave', () => {
                    assignmentDropZone.style.borderColor = '#cbd5e1';
                    assignmentDropZone.style.background = '#f8fafc';
                });
                
                assignmentDropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    assignmentDropZone.style.borderColor = '#cbd5e1';
                    assignmentDropZone.style.background = '#f8fafc';
                    if (e.dataTransfer.files.length > 0) {
                        assignmentFileInput.files = e.dataTransfer.files;
                        displaySelectedAssignmentFile(e.dataTransfer.files[0]);
                    }
                });
            }

            function displaySelectedAssignmentFile(file) {
                const ext = file.name.split('.').pop().toLowerCase();
                if (!['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'].includes(ext)) {
                    showToastNotification('Only PDF, DOC, DOCX, JPG, JPEG, and PNG files are allowed.', 'error');
                    assignmentFileInput.value = '';
                    removeSelectedFile();
                    return;
                }
                if (file.size > 20 * 1024 * 1024) {
                    showToastNotification('Maximum file size allowed is 20 MB.', 'error');
                    assignmentFileInput.value = '';
                    removeSelectedFile();
                    return;
                }
                
                const info = document.getElementById('uploadFileInfo');
                const nameText = document.getElementById('uploadFileName');
                if (info && nameText) {
                    nameText.textContent = `${file.name} (${(file.size / (1024*1024)).toFixed(2)} MB)`;
                    info.style.display = 'flex';
                }

                // Show preview
                const previewContainer = document.getElementById('submissionPreviewContainer');
                const previewPane = document.getElementById('previewPane');
                if (previewContainer && previewPane) {
                    previewPane.innerHTML = '';
                    if (['jpg', 'jpeg', 'png'].includes(ext)) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            previewPane.innerHTML = `<img src="${e.target.result}" style="max-width:100%; max-height:240px; object-fit:contain; border-radius:4px;">`;
                            previewContainer.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else if (ext === 'pdf') {
                        const fileURL = URL.createObjectURL(file);
                        previewPane.innerHTML = `<iframe src="${fileURL}" style="width:100%; height:240px; border:none; border-radius:4px;"></iframe>`;
                        previewContainer.style.display = 'block';
                    } else {
                        // Word documents
                        previewPane.innerHTML = `<div style="text-align:center; padding:1.5rem; color:#475569;"><i class="fa-solid fa-file-word" style="font-size:3rem; color:#2b579a; margin-bottom:0.5rem; display:block;"></i> <strong>${ext.toUpperCase()} File Preview Not Available</strong></div>`;
                        previewContainer.style.display = 'block';
                    }
                }

                validateFormSubmission();
            }

            // AJAX assignment upload form listener
            const uploadForm = document.getElementById('assignmentUploadForm');
            if (uploadForm) {
                uploadForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const saId = document.getElementById('upload_sa_id').value;
                    const file = assignmentFileInput.files[0];
                    if (!file) return;
                    
                    const progressPercent = document.getElementById('uploadProgressPercent');
                    const progressBar = document.getElementById('uploadProgressBar');
                    const progressContainer = document.getElementById('uploadProgressBarContainer');
                    const successMsg = document.getElementById('uploadSuccessMessage');
                    const successTime = document.getElementById('uploadSuccessTime');
                    
                    if (progressContainer) progressContainer.style.display = 'block';
                    if (successMsg) successMsg.style.display = 'none';
                    if (progressBar) progressBar.style.width = '0%';
                    if (progressPercent) progressPercent.textContent = '0%';
                    
                    const formData = new FormData();
                    formData.append('action', 'upload_subject_assignment');
                    formData.append('subject_assignment_id', saId);
                    formData.append('assignment_file', file);
                    
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'student_dashboard.php', true);
                    
                    xhr.upload.addEventListener('progress', function(event) {
                        if (event.lengthComputable) {
                            const percent = Math.round((event.loaded / event.total) * 100);
                            if (progressBar) progressBar.style.width = percent + '%';
                            if (progressPercent) progressPercent.textContent = percent + '%';
                        }
                    });
                    
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            if (xhr.status === 200) {
                                try {
                                    const res = JSON.parse(xhr.responseText);
                                    if (res.success) {
                                        if (successMsg) successMsg.style.display = 'block';
                                        if (successTime) successTime.textContent = 'Submitted on: ' + res.submitted_at;
                                        showToastNotification('Assignment uploaded successfully.', 'success');
                                        setTimeout(() => {
                                            window.location.reload();
                                        }, 1500);
                                    } else {
                                        showToastNotification(res.message || 'Upload failed.', 'error');
                                        if (progressContainer) progressContainer.style.display = 'none';
                                    }
                                } catch (e) {
                                    showToastNotification('An error occurred during upload processing.', 'error');
                                    if (progressContainer) progressContainer.style.display = 'none';
                                }
                            } else {
                                showToastNotification('Server error: HTTP ' + xhr.status, 'error');
                                if (progressContainer) progressContainer.style.display = 'none';
                            }
                        }
                    };
                    xhr.send(formData);
                });
            }

        });

        // Subject modal open/close functions
        function openSubjectUploadModal(btn) {
            const id = btn.getAttribute('data-id');
            const subject = btn.getAttribute('data-subject');
            const unit = btn.getAttribute('data-unit');
            const title = btn.getAttribute('data-title');
            const due = btn.getAttribute('data-due');
            const faculty = btn.getAttribute('data-faculty');
            const instructions = btn.getAttribute('data-instructions');

            document.getElementById('upload_sa_id').value = id;
            document.getElementById('dt_subject').textContent = subject;
            document.getElementById('dt_unit').textContent = unit;
            document.getElementById('dt_title').textContent = title;
            document.getElementById('dt_due').textContent = due;
            document.getElementById('dt_faculty').textContent = faculty;
            document.getElementById('dt_instructions').textContent = instructions;

            removeSelectedFile();
            
            const progress = document.getElementById('uploadProgressBarContainer');
            const success = document.getElementById('uploadSuccessMessage');
            if (progress) progress.style.display = 'none';
            if (success) success.style.display = 'none';
            
            const modal = document.getElementById('assignmentUploadModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeUploadModal() {
            const modal = document.getElementById('assignmentUploadModal');
            if (modal) modal.style.display = 'none';
        }

        function removeSelectedFile() {
            const fileInput = document.getElementById('assignmentFileInput');
            if (fileInput) fileInput.value = '';
            
            const info = document.getElementById('uploadFileInfo');
            if (info) info.style.display = 'none';
            
            const previewContainer = document.getElementById('submissionPreviewContainer');
            const previewPane = document.getElementById('previewPane');
            if (previewContainer) previewContainer.style.display = 'none';
            if (previewPane) previewPane.innerHTML = '';
            
            const checkbox = document.getElementById('declarationCheck');
            if (checkbox) checkbox.checked = false;

            validateFormSubmission();
        }

        function validateFormSubmission() {
            const fileInput = document.getElementById('assignmentFileInput');
            const checkbox = document.getElementById('declarationCheck');
            const submitBtn = document.getElementById('submitAssignmentBtn');
            if (submitBtn) {
                const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
                const checked = checkbox && checkbox.checked;
                if (hasFile && checked) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                } else {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                }
            }
        }

        function openGrievanceModal(saId, subject, title) {
            // Reset only the user-facing fields (NOT hidden fields)
            const form = document.getElementById('assignmentGrievanceForm');
            if (form) {
                const sel = form.querySelector('select[name="issue_type"]');
                const desc = form.querySelector('textarea[name="description"]');
                const scr = form.querySelector('input[name="screenshot"]');
                if (sel) sel.value = '';
                if (desc) desc.value = '';
                if (scr) scr.value = '';
            }
            // Set hidden + display fields AFTER reset
            const idInput = document.getElementById('grievance_sa_id');
            const subjectInput = document.getElementById('grievance_subject');
            const assignmentInput = document.getElementById('grievance_assignment');
            if (idInput) idInput.value = saId;
            if (subjectInput) subjectInput.value = subject;
            if (assignmentInput) assignmentInput.value = title;
            
            const modal = document.getElementById('assignmentGrievanceModal');
            if (modal) modal.style.display = 'flex';
        }

        function closeGrievanceModal() {
            const modal = document.getElementById('assignmentGrievanceModal');
            if (modal) modal.style.display = 'none';
        }

        // Filtering logic for notices
        function filterNotices() {
            const roleFilter = document.getElementById('noticeRoleFilter').value;
            const sortFilter = document.getElementById('noticeSortFilter').value;
            const tbody = document.querySelector('#tab-notices tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.forEach(row => {
                const roleCell = row.querySelector('.pub-role').textContent.toLowerCase();
                if (roleFilter === 'all') {
                    row.style.display = '';
                } else if (roleFilter === 'faculty' && roleCell.includes('faculty')) {
                    row.style.display = '';
                } else if (roleFilter === 'admin' && roleCell.includes('admin')) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Sorting by ID (simulating date since data is mock/static in structure)
            const sortedRows = rows.sort((a, b) => {
                const idA = parseInt(a.cells[0].textContent);
                const idB = parseInt(b.cells[0].textContent);
                return sortFilter === 'newest' ? idB - idA : idA - idB;
            });

            sortedRows.forEach(row => tbody.appendChild(row));
        }

        // Attendance logs filter
        function filterAttendanceLogs() {
            const subjectFilter = document.getElementById('attendanceSubjectFilter').value;
            const statusFilter = document.getElementById('attendanceStatusFilter').value;
            const rows = document.querySelectorAll('#attendanceLogsTable tbody tr');

            rows.forEach(row => {
                const subjectMatch = (subjectFilter === 'all' || row.getAttribute('data-subject') === subjectFilter);
                const statusMatch = (statusFilter === 'all' || row.getAttribute('data-status') === statusFilter);
                if (subjectMatch && statusMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
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

        function switchProfileTab(tabId) {
            // Remove active class from all buttons and sections
            document.querySelectorAll('.profile-details-tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            document.querySelectorAll('.profile-details-section').forEach(sec => {
                sec.classList.remove('active');
                sec.style.display = 'none';
            });
            
            // Add active class to targets
            const targetBtn = document.querySelector(`.profile-details-tab-btn[data-tab-target="${tabId}"]`);
            if (targetBtn) {
                targetBtn.classList.add('active');
            }
            
            const targetSec = document.getElementById(`profile-details-sec-${tabId}`);
            if (targetSec) {
                targetSec.classList.add('active');
                targetSec.style.display = 'block';
            }
            
            // Update hidden input
            const activeInput = document.getElementById('current_active_profile_tab');
            if (activeInput) {
                activeInput.value = tabId;
            }
        }

        function validatePassportNumber(el) {
            const errSpan = document.getElementById('err_passport_number');
            const regex = /^[A-Z0-9]{9}$/;
            if (el.value.length > 0 && !regex.test(el.value)) {
                el.setCustomValidity('Passport Number must be exactly 9 alphanumeric characters.');
                errSpan.textContent = 'Passport Number must be exactly 9 alphanumeric characters.';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validatePassportPlace(el) {
            const errSpan = document.getElementById('err_passport_place_of_issue');
            if (el.required && (!el.value || el.value.trim() === '')) {
                el.setCustomValidity('Place of Issue is required.');
                if (errSpan) {
                    errSpan.textContent = 'Place of Issue is required.';
                    errSpan.style.display = 'block';
                }
                return false;
            } else {
                el.setCustomValidity('');
                if (errSpan) {
                    errSpan.style.display = 'none';
                }
                return true;
            }
        }

        function validatePassportDates() {
            const issueDate = document.getElementById('passport_issue_date');
            const expiryDate = document.getElementById('passport_expiry_date');
            const errIssue = document.getElementById('err_passport_issue_date');
            const errExpiry = document.getElementById('err_passport_expiry_date');
            
            let valid = true;
            
            if (issueDate) {
                if (issueDate.required && (!issueDate.value || issueDate.value.trim() === '')) {
                    issueDate.setCustomValidity('Issue Date is required.');
                    if (errIssue) {
                        errIssue.textContent = 'Issue Date is required.';
                        errIssue.style.display = 'block';
                    }
                    valid = false;
                } else {
                    issueDate.setCustomValidity('');
                    if (errIssue) {
                        errIssue.style.display = 'none';
                    }
                }
            }
            
            if (expiryDate) {
                if (expiryDate.required && (!expiryDate.value || expiryDate.value.trim() === '')) {
                    expiryDate.setCustomValidity('Expiry Date is required.');
                    if (errExpiry) {
                        errExpiry.textContent = 'Expiry Date is required.';
                        errExpiry.style.display = 'block';
                    }
                    valid = false;
                } else {
                    expiryDate.setCustomValidity('');
                    if (errExpiry) {
                        errExpiry.style.display = 'none';
                    }
                }
            }
            
            if (valid && issueDate && expiryDate && issueDate.value && expiryDate.value) {
                const issue = new Date(issueDate.value);
                const expiry = new Date(expiryDate.value);
                if (expiry <= issue) {
                    expiryDate.setCustomValidity('Expiry Date must be later than Issue Date.');
                    if (errExpiry) {
                        errExpiry.textContent = 'Expiry Date must be later than Issue Date.';
                        errExpiry.style.display = 'block';
                    }
                    valid = false;
                } else {
                    expiryDate.setCustomValidity('');
                    if (errExpiry) {
                        errExpiry.style.display = 'none';
                    }
                }
            }
            
            return valid;
        }

        function validateHandicapPercentage(el) {
            const errSpan = document.getElementById('err_handicap_percentage');
            if (el.value.length > 0 && (isNaN(el.value) || el.value.length > 2)) {
                el.setCustomValidity('Disability Percentage must be a maximum of 2 digits.');
                errSpan.textContent = 'Disability Percentage must be a maximum of 2 digits.';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validateMobileField(el) {
            const errSpan = document.getElementById('err_personal_mobile');
            if (el.value.length > 0 && el.value.length !== 10) {
                el.setCustomValidity('Mobile Number must be exactly 10 digits.');
                errSpan.textContent = 'Mobile Number must be exactly 10 digits.';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validateEmails() {
            const primary = document.getElementById('primary_email');
            const alternate = document.getElementById('alternate_email');
            
            if (primary && alternate) {
                if (primary.value && alternate.value && primary.value.trim().toLowerCase() === alternate.value.trim().toLowerCase()) {
                    alternate.setCustomValidity('Alternate Email cannot be the same as Primary Email.');
                } else {
                    alternate.setCustomValidity('');
                }
            }
        }

        function validateAadhaarField(el) {
            const errSpan = document.getElementById('err_identity_aadhaar');
            if (el.value.length > 0 && el.value.length !== 12) {
                el.setCustomValidity('Aadhaar Number must be exactly 12 digits.');
                errSpan.textContent = 'Aadhaar Number must be exactly 12 digits.';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validatePanField(el) {
            const errSpan = document.getElementById('err_identity_pan');
            const regex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            if (el.value.length > 0 && !regex.test(el.value)) {
                el.setCustomValidity('Enter a valid PAN Number.');
                errSpan.textContent = 'Enter a valid PAN Number.';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validateVoterField(el) {
            const errSpan = document.getElementById('err_identity_voter_id');
            const regex = /^[A-Z]{3}[0-9]{7}$/;
            if (el.value.length > 0 && !regex.test(el.value)) {
                el.setCustomValidity('Enter a valid Voter ID Number.');
                errSpan.textContent = 'Enter a valid Voter ID Number (e.g., ABC1234567).';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        function validateDlField(el) {
            const errSpan = document.getElementById('err_identity_driving_license');
            const regex = /^[A-Z]{2}[0-9]{13}$/;
            if (el.value.length > 0 && !regex.test(el.value)) {
                el.setCustomValidity('Enter a valid Driving License Number.');
                errSpan.textContent = 'Enter a valid Driving License Number (e.g., MH1220211234567).';
                errSpan.style.display = 'block';
                return false;
            } else {
                el.setCustomValidity('');
                errSpan.style.display = 'none';
                return true;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme_preference') === 'dark') {
                document.body.classList.add('dark-mode');
                updateThemeIcon(true);
            }

            // Check URL parameters for tab navigation
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                const targetSidebarBtn = document.querySelector(`.sidebar-nav-item[onclick*="'${tabParam}'"]`);
                if (targetSidebarBtn) {
                    switchTab(tabParam, targetSidebarBtn);
                }
            }

            const profileTabParam = urlParams.get('profile_tab');
            if (profileTabParam) {
                switchProfileTab(profileTabParam);
            }

            // Bind Mobile & Identity Tab Real-Time Validations & Input Constraints
            const mobile = document.getElementById('personal_mobile');
            if (mobile) {
                mobile.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                    validateMobileField(this);
                });
                mobile.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    let digitsOnly = pasteData.replace(/[^0-9]/g, '');
                    if (digitsOnly.length > 10) {
                        digitsOnly = digitsOnly.slice(0, 10);
                    }
                    e.preventDefault();
                    this.value = digitsOnly;
                    validateMobileField(this);
                });
            }

            const aadhaar = document.getElementById('identity_aadhaar');
            const pan = document.getElementById('identity_pan');
            const voter = document.getElementById('identity_voter_id');
            const dl = document.getElementById('identity_driving_license');

            if (aadhaar) {
                aadhaar.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 12) {
                        this.value = this.value.slice(0, 12);
                    }
                    validateAadhaarField(this);
                });
                aadhaar.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    let digitsOnly = pasteData.replace(/[^0-9]/g, '');
                    if (digitsOnly.length > 12) {
                        digitsOnly = digitsOnly.slice(0, 12);
                    }
                    e.preventDefault();
                    this.value = digitsOnly;
                    validateAadhaarField(this);
                });
            }

            if (pan) {
                pan.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                    validatePanField(this);
                });
                pan.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (pasteData.length > 10) {
                        pasteData = pasteData.slice(0, 10);
                    }
                    e.preventDefault();
                    this.value = pasteData;
                    validatePanField(this);
                });
            }

            if (voter) {
                voter.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                    validateVoterField(this);
                });
                voter.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (pasteData.length > 10) {
                        pasteData = pasteData.slice(0, 10);
                    }
                    e.preventDefault();
                    this.value = pasteData;
                    validateVoterField(this);
                });
            }

            if (dl) {
                dl.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (this.value.length > 15) {
                        this.value = this.value.slice(0, 15);
                    }
                    validateDlField(this);
                });
                dl.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (pasteData.length > 15) {
                        pasteData = pasteData.slice(0, 15);
                    }
                    e.preventDefault();
                    this.value = pasteData;
                    validateDlField(this);
                });
            }

            const hp = document.getElementById('handicap_percentage');
            if (hp) {
                hp.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    if (this.value.length > 2) {
                        this.value = this.value.slice(0, 2);
                    }
                    validateHandicapPercentage(this);
                });
                hp.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text');
                    let digitsOnly = pasteData.replace(/[^0-9]/g, '');
                    if (digitsOnly.length > 2) {
                        digitsOnly = digitsOnly.slice(0, 2);
                    }
                    e.preventDefault();
                    this.value = digitsOnly;
                    validateHandicapPercentage(this);
                });
            }

            function showToastNotification(message, type) {
                const existing = document.querySelector('.toast-notification');
                if (existing) existing.remove();
                
                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;
                
                const icon = document.createElement('i');
                icon.className = type === 'success' ? 'fa-solid fa-circle-check' : 'fa-solid fa-triangle-exclamation';
                toast.appendChild(icon);
                
                const span = document.createElement('span');
                span.textContent = message;
                toast.appendChild(span);
                
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500);
                }, 4000);
            }

            const passportInput = document.getElementById('passport_number');
            if (passportInput) {
                passportInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (this.value.length > 9) {
                        this.value = this.value.slice(0, 9);
                    }
                    validatePassportNumber(this);
                });
                passportInput.addEventListener('paste', function(e) {
                    let pasteData = (e.clipboardData || window.clipboardData).getData('text').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    if (pasteData.length > 9) {
                        pasteData = pasteData.slice(0, 9);
                    }
                    e.preventDefault();
                    this.value = pasteData;
                    validatePassportNumber(this);
                });
            }

            const passportPlace = document.getElementById('passport_place_of_issue');
            if (passportPlace) {
                passportPlace.addEventListener('input', function() {
                    validatePassportPlace(this);
                });
                passportPlace.addEventListener('blur', function() {
                    validatePassportPlace(this);
                });
            }

            const passportIssue = document.getElementById('passport_issue_date');
            const passportExpiry = document.getElementById('passport_expiry_date');
            if (passportIssue) {
                passportIssue.addEventListener('change', function() {
                    validatePassportDates();
                });
            }
            if (passportExpiry) {
                passportExpiry.addEventListener('change', function() {
                    validatePassportDates();
                });
            }

            const btnSavePassport = document.getElementById('btn_save_passport');
            if (btnSavePassport) {
                btnSavePassport.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const passportNo = document.getElementById('passport_number');
                    const placeOfIssue = document.getElementById('passport_place_of_issue');
                    const issueDate = document.getElementById('passport_issue_date');
                    const expiryDate = document.getElementById('passport_expiry_date');
                    
                    // Run all validations
                    let isValid = true;
                    if (passportNo) {
                        if (!validatePassportNumber(passportNo)) isValid = false;
                        if (passportNo.required && (!passportNo.value || passportNo.value.trim() === '')) {
                            passportNo.setCustomValidity('Passport Number must be exactly 9 alphanumeric characters.');
                            const errSpan = document.getElementById('err_passport_number');
                            if (errSpan) {
                                errSpan.textContent = 'Passport Number must be exactly 9 alphanumeric characters.';
                                errSpan.style.display = 'block';
                            }
                            isValid = false;
                        }
                    }
                    if (placeOfIssue) {
                        if (!validatePassportPlace(placeOfIssue)) isValid = false;
                    }
                    if (!validatePassportDates()) {
                        isValid = false;
                    }
                    
                    if (!isValid) {
                        // Focus on first invalid
                        if (passportNo && !passportNo.checkValidity()) {
                            passportNo.reportValidity();
                            passportNo.focus();
                        } else if (placeOfIssue && !placeOfIssue.checkValidity()) {
                            placeOfIssue.reportValidity();
                            placeOfIssue.focus();
                        } else if (passportExpiry && !passportExpiry.checkValidity()) {
                            passportExpiry.reportValidity();
                            passportExpiry.focus();
                        } else if (passportIssue && !passportIssue.checkValidity()) {
                            passportIssue.reportValidity();
                            passportIssue.focus();
                        }
                        return;
                    }
                    
                    // Disable button to prevent duplicate submissions
                    btnSavePassport.disabled = true;
                    const originalHTML = btnSavePassport.innerHTML;
                    btnSavePassport.innerHTML = '<i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i>Saving...';
                    
                    // Send POST request with JSON / urlencoded format
                    const params = new URLSearchParams();
                    params.append('action', 'save_passport_api');
                    params.append('passport_no', passportNo.value.trim());
                    params.append('place_of_issue', placeOfIssue.value.trim());
                    params.append('issue_date', issueDate.value.trim());
                    params.append('expiry_date', expiryDate.value.trim());
                    
                    fetch('student_dashboard.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: params.toString()
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.json().then(err => { throw err; }).catch(() => {
                                throw new Error('Server returned HTTP ' + response.status);
                            });
                        }
                        return response.json();
                    })
                    .then(data => {
                        btnSavePassport.disabled = false;
                        btnSavePassport.innerHTML = originalHTML;
                        if (data.success) {
                            showToastNotification('Passport details saved successfully.', 'success');
                            // Keep value visible and refresh DOM attributes if necessary
                        } else {
                            showToastNotification(data.message || 'Failed to save passport details.', 'error');
                        }
                    })
                    .catch(err => {
                        console.error('API request error:', err);
                        btnSavePassport.disabled = false;
                        btnSavePassport.innerHTML = originalHTML;
                        showToastNotification(err.message || 'An error occurred while saving passport details.', 'error');
                    });
                });
            }

            // Intercept form submit to validate and scroll to alternate email if duplicate
            const profileForm = document.getElementById('studentProfileForm');
            if (profileForm) {
                profileForm.addEventListener('submit', function(e) {
                    try {
                        // Check all required fields first to prevent silent browser blocking in hidden tabs
                        const requiredFields = profileForm.querySelectorAll('[required]');
                        for (let input of requiredFields) {
                            if (!input.value || input.value.trim() === '') {
                                e.preventDefault();
                                const parentSection = input.closest('.profile-details-section');
                                if (parentSection) {
                                    const sectionId = parentSection.id.replace('profile-details-sec-', '');
                                    switchProfileTab(sectionId);
                                }
                                setTimeout(() => {
                                    input.reportValidity();
                                    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    input.focus();
                                }, 100);
                                return;
                            }
                        }

                        // Validate Mobile Number
                        if (mobile) validateMobileField(mobile);
                        const isMobileValid = mobile ? mobile.checkValidity() : true;
                        if (!isMobileValid) {
                            e.preventDefault();
                            switchProfileTab('personal');
                            setTimeout(() => {
                                mobile.reportValidity();
                                mobile.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                mobile.focus();
                            }, 100);
                            return;
                        }

                        const primary = document.getElementById('primary_email');
                        const alternate = document.getElementById('alternate_email');
                        if (primary && alternate && primary.value && alternate.value && primary.value.trim().toLowerCase() === alternate.value.trim().toLowerCase()) {
                            e.preventDefault();
                            switchProfileTab('personal');
                            alternate.setCustomValidity('Alternate Email cannot be the same as Primary Email.');
                            alternate.reportValidity();
                            alternate.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            alternate.focus();
                            return;
                        }

                        // Validate Identity fields
                        if (aadhaar) validateAadhaarField(aadhaar);
                        if (pan) validatePanField(pan);
                        if (voter) validateVoterField(voter);
                        if (dl) validateDlField(dl);
                        if (hp) validateHandicapPercentage(hp);
                        if (passportInput) validatePassportNumber(passportInput);

                        const isAadhaarValid = aadhaar ? aadhaar.checkValidity() : true;
                        const isPanValid = pan ? pan.checkValidity() : true;
                        const isVoterValid = voter ? voter.checkValidity() : true;
                        const isDlValid = dl ? dl.checkValidity() : true;
                        const isHpValid = hp ? hp.checkValidity() : true;
                        const isPassportValid = passportInput ? passportInput.checkValidity() : true;

                        if (!isAadhaarValid || !isPanValid || !isVoterValid || !isDlValid) {
                            e.preventDefault();
                            switchProfileTab('identity');
                            
                            let firstInvalid = null;
                            if (!isAadhaarValid) firstInvalid = aadhaar;
                            else if (!isPanValid) firstInvalid = pan;
                            else if (!isVoterValid) firstInvalid = voter;
                            else if (!isDlValid) firstInvalid = dl;

                            if (firstInvalid) {
                                setTimeout(() => {
                                    firstInvalid.reportValidity();
                                    firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    firstInvalid.focus();
                                }, 100);
                            }
                        } else if (!isHpValid) {
                            e.preventDefault();
                            switchProfileTab('handicap');
                            if (hp) {
                                setTimeout(() => {
                                    hp.reportValidity();
                                    hp.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    hp.focus();
                                }, 100);
                            }
                        } else if (!isPassportValid) {
                            e.preventDefault();
                            switchProfileTab('passport');
                            if (passportInput) {
                                setTimeout(() => {
                                    passportInput.reportValidity();
                                    passportInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                    passportInput.focus();
                                }, 100);
                            }
                        }
                    } catch (err) {
                        console.warn("Validation script warning, proceeding with form submit:", err);
                        // Do not prevent default, allow form submit
                    }
                });
            }

            // If backend returned email duplicate error, scroll to the email input immediately
            const errorToast = document.querySelector('.toast-error');
            if (errorToast) {
                if (errorToast.textContent.includes('Alternate Email')) {
                    const altEmailInput = document.getElementById('alternate_email');
                    if (altEmailInput) {
                        setTimeout(() => {
                            altEmailInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            altEmailInput.focus();
                        }, 300);
                    }
                } else if (errorToast.textContent.includes('Mobile Number')) {
                    switchProfileTab('personal');
                    const mob = document.getElementById('personal_mobile');
                    if (mob) {
                        setTimeout(() => {
                            mob.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            mob.focus();
                        }, 300);
                    }
                } else if (errorToast.textContent.includes('Disability Percentage')) {
                    switchProfileTab('handicap');
                    const hpInput = document.getElementById('handicap_percentage');
                    if (hpInput) {
                        setTimeout(() => {
                            hpInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            hpInput.focus();
                        }, 300);
                    }
                } else if (errorToast.textContent.includes('Passport Number')) {
                    switchProfileTab('passport');
                    const passInput = document.getElementById('passport_number');
                    if (passInput) {
                        setTimeout(() => {
                            passInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            passInput.focus();
                        }, 300);
                    }
                } else if (errorToast.textContent.includes('Aadhaar') || errorToast.textContent.includes('PAN') || errorToast.textContent.includes('Voter') || errorToast.textContent.includes('Driving')) {
                    // Switch to Identity tab and focus appropriate field
                    switchProfileTab('identity');
                    let targetInput = null;
                    if (errorToast.textContent.includes('Aadhaar')) targetInput = document.getElementById('identity_aadhaar');
                    else if (errorToast.textContent.includes('PAN')) targetInput = document.getElementById('identity_pan');
                    else if (errorToast.textContent.includes('Voter')) targetInput = document.getElementById('identity_voter_id');
                    else if (errorToast.textContent.includes('Driving')) targetInput = document.getElementById('identity_driving_license');
                    
                    if (targetInput) {
                        setTimeout(() => {
                            targetInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            targetInput.focus();
                        }, 300);
                    }
                }
            }
        });
    </script>
</body>
</html>
