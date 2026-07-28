


<?php
session_start();
require_once 'db.php';

// Authentication check
if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'faculty') {
    header("Location: login.php?role=faculty");
    exit;
}

$user = $_SESSION['user'];
$db = get_db();

$current_faculty = null;
foreach ($db['faculty'] as $f) {
    if ($f['username'] === $user['username']) {
        $current_faculty = $f;
        break;
    }
}
if (!$current_faculty) {
    $current_faculty = [
        'username' => $user['username'],
        'name' => $user['name'],
        'email' => 'faculty@erp.edu',
        'phone' => '+91 99999 88888',
        'designation' => 'Assistant Professor',
        'workload' => '16 Hours / Week',
        'attendance' => '95%',
        'subjects' => '',
        'assigned_divisions' => 'A,B,C'
    ];
}
$faculty_subjects = array_map('trim', explode(',', $current_faculty['subjects'] ?? ''));
$faculty_divisions = array_map('trim', explode(',', $current_faculty['assigned_divisions'] ?? 'A,B,C'));

$is_hod = (($current_faculty['role'] ?? '') === 'HOD');
$my_department_id = '';
if ($is_hod) {
    foreach ($db['departments'] as $dept) {
        if ($dept['hod_name'] === $current_faculty['name'] || $dept['name'] === ($current_faculty['department'] ?? '')) {
            $my_department_id = $dept['id'];
            break;
        }
    }
}

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

// Handle Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['approve', 'reject'])) {
    $action = $_POST['action'];
    $leave_id = isset($_POST['leave_id']) ? intval($_POST['leave_id']) : 0;

    $updated = false;
    foreach ($db['leaves'] as &$leave) {
        if ($leave['id'] === $leave_id) {
            if ($action === 'approve') {
                $leave['status'] = 'Approved';
                $_SESSION['success_message'] = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Approved.';
                $updated = true;
            } elseif ($action === 'reject') {
                $leave['status'] = 'Rejected';
                $_SESSION['success_message'] = 'Leave request #' . $leave_id . ' (Reason: ' . $leave['reason'] . ') has been Rejected.';
                $updated = true;
            }
            break;
        }
    }

    if ($updated) {
        save_db($db);
    } else {
        $_SESSION['error_message'] = 'Failed to update leave request status. Request #' . $leave_id . ' not found.';
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_notice') {
    $title = trim($_POST['title']);
    $desc = trim($_POST['desc']);
    $expiry = trim($_POST['expiry']);
    $file_name = '';

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_name = basename($_FILES['attachment']['name']);
        if (!is_dir(__DIR__ . '/uploads')) { mkdir(__DIR__ . '/uploads', 0777, true); }
        move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__ . '/uploads/' . $file_name);
        $file_name = 'uploads/' . $file_name;
    }
    
    if (!empty($title) && !empty($desc)) {
        $max_notice_id = 0;
        foreach (($db['notices'] ?? []) as $n) {
            if (isset($n['id']) && intval($n['id']) > $max_notice_id) {
                $max_notice_id = intval($n['id']);
            }
        }
        $db['notices'][] = [
            'id' => $max_notice_id + 1,
            'title' => $title,
            'desc' => $desc,
            'author' => $user['name'],
            'role' => 'Faculty (' . $user['dept'] . ')',
            'date' => date('d M Y'),
            'expiry' => $expiry,
            'attachment' => $file_name,
            'size' => $file_name ? '1.5MB' : ''
        ];
        save_db($db);
        $_SESSION['success_message'] = "Notice published successfully.";
    } else {
        $_SESSION['error_message'] = "Title and Description are required.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'grade_assignment') {
    $sub_id = intval($_POST['assignment_id']);
    $marks = trim($_POST['marks'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $status = trim($_POST['status'] ?? 'Graded');
    
    $updated = false;
    if (isset($db['assignment_submissions'])) {
        foreach ($db['assignment_submissions'] as &$sub) {
            if ($sub['id'] === $sub_id) {
                $sub['status'] = $status;
                $sub['marks'] = $marks;
                $sub['remarks'] = $remarks;
                $sub['evaluated_at'] = date('d M Y h:i A');
                $updated = true;
                break;
            }
        }
    }
    if ($updated) {
        save_db($db);
        $_SESSION['success_message'] = "Assignment submission status updated to '{$status}' successfully.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publish_assignment') {
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    
    $title = trim($_POST['title'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $unit_number = intval($_POST['unit_number'] ?? 1);
    $due_date = trim($_POST['due_date'] ?? '');
    $target_dept = trim($_POST['target_dept'] ?? '');
    $target_sem = trim($_POST['target_sem'] ?? '');
    $target_div = trim($_POST['target_div'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $file_name = '';

    if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['assignment_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
            $raw_file_name = 'sa_' . uniqid() . '_' . time() . '.' . $ext;
            if (!is_dir(__DIR__ . '/uploads/assignments')) { mkdir(__DIR__ . '/uploads/assignments', 0777, true); }
            if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], __DIR__ . '/uploads/assignments/' . $raw_file_name)) {
                $file_name = 'assignments/' . $raw_file_name;
            }
        }
    }

    if (!empty($title) && !empty($file_name)) {
        $formatted_due = $due_date ? date('d M Y', strtotime($due_date)) . ' 11:59 PM' : 'No Due Date';
        $pub_date = date('d M Y h:i A');
        
        $db = get_db();
        
        // Ensure unit assignment (unit) exists in assignments table
        $unit_id = 0;
        foreach ($db['assignments'] as $a) {
            if ($a['unit'] === $unit_number) {
                $unit_id = $a['id'];
                break;
            }
        }
        
        if ($unit_id === 0) {
            $max_unit_id = 0;
            foreach (($db['assignments'] ?? []) as $a) {
                if (isset($a['id']) && intval($a['id']) > $max_unit_id) {
                    $max_unit_id = intval($a['id']);
                }
            }
            $unit_id = $max_unit_id + 1;
            $db['assignments'][] = [
                'id' => $unit_id,
                'unit' => $unit_number,
                'title' => 'Unit ' . $unit_number,
                'desc' => 'Unit ' . $unit_number . ' Subject Assignments'
            ];
        }

        $max_sa_id = 0;
        foreach (($db['subject_assignments'] ?? []) as $sa) {
            if (isset($sa['id']) && intval($sa['id']) > $max_sa_id) {
                $max_sa_id = intval($sa['id']);
            }
        }
        $new_sa_id = $max_sa_id + 1;
        $db['subject_assignments'][] = [
            'id' => $new_sa_id,
            'assignment_id' => $unit_id,
            'subject_name' => $subject_name,
            'assignment_title' => $title,
            'question_pdf' => $file_name,
            'due' => $formatted_due,
            'created_by' => $user['name'],
            'department' => $target_dept,
            'division' => $target_div,
            'semester' => $target_sem,
            'description' => $description,
            'published_date' => $pub_date
        ];
        
        save_db($db);
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Assignment published successfully targeting Department: {$target_dept}, Div: {$target_div}."]);
            exit;
        }
        $_SESSION['success_message'] = "Assignment published successfully targeting Department: {$target_dept}, Div: {$target_div}.";
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Assignment Title and a valid file (PDF, Word, or Image) are required."]);
            exit;
        }
        $_SESSION['error_message'] = "Assignment Title and a valid file (PDF, Word, or Image) are required.";
    }
    header("Location: faculty_dashboard.php?tab=assignments");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_grievance') {
    $g_id = intval($_POST['grievance_id']);
    $updated = false;
    foreach ($db['grievances'] as &$g) {
        if ($g['id'] === $g_id) {
            $g['status'] = 'Resolved';
            $updated = true;
            break;
        }
    }
    if ($updated) {
        save_db($db);
        $_SESSION['success_message'] = "Grievance marked as resolved.";
    }
    header("Location: faculty_dashboard.php");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'respond_assignment_grievance') {
    $g_id = intval($_POST['grievance_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'Resolved');
    $reply = trim($_POST['reply'] ?? '');
    
    $updated = false;
    if (isset($db['assignment_grievances'])) {
        foreach ($db['assignment_grievances'] as &$g) {
            if ($g['id'] === $g_id) {
                $g['status'] = $status;
                $g['reply'] = $reply;
                $updated = true;

                // Handle optional file replacement
                $sa_id = intval($_POST['subject_assignment_id'] ?? $g['subject_assignment_id']);
                if (isset($_FILES['new_question_pdf']) && $_FILES['new_question_pdf']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['new_question_pdf']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'])) {
                        $dest_filename = 'sa_' . $sa_id . '_' . time() . '.' . $ext;
                        if (!is_dir(__DIR__ . '/uploads')) {
                            mkdir(__DIR__ . '/uploads', 0777, true);
                        }
                        if (move_uploaded_file($_FILES['new_question_pdf']['tmp_name'], __DIR__ . '/uploads/' . $dest_filename)) {
                            if (isset($db['subject_assignments'])) {
                                foreach ($db['subject_assignments'] as &$sa) {
                                    if ($sa['id'] === $sa_id) {
                                        $sa['question_pdf'] = $dest_filename;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }

                // Notify affected student
                $db['recent_activity'] = array_merge([
                    [
                        'title' => 'Grievance Status Change',
                        'desc' => "Your grievance regarding assignment has been updated to {$status}.",
                        'time' => 'Just now'
                    ]
                ], array_slice($db['recent_activity'] ?? [], 0, 4));
                break;
            }
        }
    }
    if ($updated) {
        save_db($db);
        $_SESSION['success_message'] = "Grievance status updated to {$status}. Notification sent to student.";
    } else {
        $_SESSION['error_message'] = "Grievance ID not found.";
    }
    header("Location: faculty_dashboard.php?tab=grievances");
    exit;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'replace_question_pdf') {
    $sa_id = intval($_POST['subject_assignment_id'] ?? 0);
    $file_uploaded = false;
    
    if (isset($_FILES['new_question_pdf']) && $_FILES['new_question_pdf']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['new_question_pdf']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'])) {
            $dest_filename = 'sa_' . $sa_id . '_' . time() . '.' . $ext;
            if (!is_dir(__DIR__ . '/uploads')) {
                mkdir(__DIR__ . '/uploads', 0777, true);
            }
            if (move_uploaded_file($_FILES['new_question_pdf']['tmp_name'], __DIR__ . '/uploads/' . $dest_filename)) {
                if (isset($db['subject_assignments'])) {
                    foreach ($db['subject_assignments'] as &$sa) {
                        if ($sa['id'] === $sa_id) {
                            $sa['question_pdf'] = $dest_filename;
                            $file_uploaded = true;

                            // Notify students automatically
                            $db['recent_activity'] = array_merge([
                                [
                                    'title' => 'Assignment PDF Replaced',
                                    'desc' => "Question file replaced for {$sa['subject_name']}.",
                                    'time' => 'Just now'
                                ]
                            ], array_slice($db['recent_activity'] ?? [], 0, 4));
                            break;
                        }
                    }
                }
            }
        }
    }
    
    if ($file_uploaded) {
        save_db($db);
        $_SESSION['success_message'] = "Question file replaced successfully and students notified.";
    } else {
        $_SESSION['error_message'] = "Failed to replace file (only PDF, DOC, DOCX, JPG, JPEG, PNG, GIF formats accepted).";
    }
    header("Location: faculty_dashboard.php?tab=grievances");
    exit;
}

// Reload database to get fresh updates
$db = get_db();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>College ERP Portal - Faculty Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="theme-faculty">
    <div class="dashboard-wrapper">
        <!-- Sidebar Navigation -->
        <aside class="sidebar">
            <div class="sidebar-top">
                <div class="sidebar-brand">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <div>
                        <span>College ERP</span>
                        <span class="sub">Faculty Portal</span>
                    </div>
                </div>
                    <li><a class="sidebar-nav-item" onclick="switchTab('profile', this)"><i class="fa-solid fa-id-card"></i><span>My Profile</span></a></li>
                    <li><a class="sidebar-nav-item active" onclick="switchTab('dashboard', this)"><i class="fa-solid fa-border-all"></i><span>Dashboard</span></a></li>

                    <li><a class="sidebar-nav-item" onclick="switchTab('leaves', this)"><i class="fa-solid fa-envelope-open-text"></i><span>Leave Approvals</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('assignments', this)"><i class="fa-solid fa-file-invoice"></i><span>Manage Assignments</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('notices', this)"><i class="fa-solid fa-bullhorn"></i><span>Publish Notices</span></a></li>
                    <li><a class="sidebar-nav-item" onclick="switchTab('grievances', this)"><i class="fa-solid fa-circle-exclamation"></i><span>Grievance</span></a></li>
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
                    <p id="currentTabSubtitle">Quick access to all essential faculty services.</p>
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
                    <div class="user-avatar-box">
                        <?= get_initials_avatar($user['name'], 40, 16, 2) ?>
                        <div class="user-details">
                            <span class="name"><?php echo htmlspecialchars($user['name']); ?></span>
                            <span class="role"><?php echo htmlspecialchars($user['dept']); ?></span>
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
                // Leaves: filter by faculty's students
                $pending_leaves = 0;
                $total_leaves = 0;
                foreach ($db['leaves'] ?? [] as $l) {
                    $is_ours = false;
                    foreach ($db['students'] as $stu) {
                        if ($stu['name'] === $l['applicant_name']) {
                            $student_div = $stu['division'] ?? '';
                            if (in_array($student_div, $faculty_divisions)) {
                                $is_ours = true;
                            }
                            break;
                        }
                    }
                    if ($is_ours) {
                        $total_leaves++;
                        if (($l['status'] ?? '') === 'Pending') $pending_leaves++;
                    }
                }

                // Assignments: filter by subject
                $ungraded_submissions = 0;
                $total_submissions = 0;
                foreach ($db['assignment_submissions'] ?? [] as $sub) {
                    if (in_array($sub['subject_id'], $faculty_subjects)) {
                        $total_submissions++;
                        if (($sub['status'] ?? '') === 'submitted' || strtolower($sub['marks'] ?? '') === 'pending') {
                            $ungraded_submissions++;
                        }
                    }
                }
                
                // Grievances: filter by division (general) or subject (assignment)
                $active_grievances = 0;
                foreach ($db['grievances'] ?? [] as $g) {
                    $is_ours = false;
                    foreach ($db['students'] as $stu) {
                        if ($stu['id'] === $g['student_id']) {
                            $student_div = $stu['division'] ?? '';
                            if (in_array($student_div, $faculty_divisions)) {
                                $is_ours = true;
                            }
                            break;
                        }
                    }
                    if ($is_ours && ($g['status'] ?? '') !== 'Resolved') {
                        $active_grievances++;
                    }
                }
                foreach ($db['assignment_grievances'] ?? [] as $ag) {
                    $is_mine = ($ag['faculty_id'] ?? '') === $current_faculty['username'];
                    $is_my_dept = $is_hod && ($ag['department_id'] ?? '') === $my_department_id;
                    if ($is_mine || $is_my_dept) {
                        if (($ag['status'] ?? '') !== 'Resolved') {
                            $active_grievances++;
                        }
                    }
                }
                
                // Notices: only count notices created by the logged-in faculty
                $total_notices = 0;
                foreach ($db['notices'] ?? [] as $n) {
                    if ($n['author'] === $current_faculty['name']) {
                        $total_notices++;
                    }
                }
                ?>
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem;">
                    
                    <!-- Leave Approvals Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('leaves', document.querySelectorAll('.sidebar-nav-item')[2])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-envelope-open-text"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Leaves Pending</h4>
                        <div style="color: #10b981; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $pending_leaves ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Out of <?= $total_leaves ?> total leaves</p>
                    </div>

                    <!-- Manage Assignments Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('assignments', document.querySelectorAll('.sidebar-nav-item')[3])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #f3e8ff; color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-file-invoice"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Ungraded Work</h4>
                        <div style="color: #6366f1; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $ungraded_submissions ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Out of <?= $total_submissions ?> submissions</p>
                    </div>

                    <!-- Publish Notices Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('notices', document.querySelectorAll('.sidebar-nav-item')[4])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #dbeafe; color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Notices</h4>
                        <div style="color: #3b82f6; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $total_notices ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Recent updates</p>
                    </div>

                    <!-- Grievance Card -->
                    <div style="background: white; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); border: 1px solid #f1f5f9; display: flex; flex-direction: column; align-items: center; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-5px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 6px -1px rgba(0,0,0,0.05)';" onclick="switchTab('grievances', document.querySelectorAll('.sidebar-nav-item')[5])">
                        <div style="width: 64px; height: 64px; border-radius: 50%; background: #ffedd5; color: #f97316; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; margin-bottom: 1.25rem;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                        </div>
                        <h4 style="color: #64748b; font-size: 0.95rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.5px;">Active Grievances</h4>
                        <div style="color: #f97316; font-size: 2.5rem; font-weight: 800; margin-bottom: 0.5rem;"><?= $active_grievances ?></div>
                        <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 0;">Requires resolution</p>
                    </div>

                </div>
            </div>

            <!-- ============================================ -->
            <!-- -1. PROFILE PAGE                             -->
            <!-- ============================================ -->
            <div id="tab-profile" class="app-view">
                <div class="settings-form-container" style="max-width: 800px; margin: 0 auto; background: white; border: 1px solid var(--border-color); border-radius: var(--border-radius-md); padding: 2rem; box-shadow: var(--box-shadow-subtle);">
                    <div style="display: flex; gap: 2rem; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 2rem; margin-bottom: 2rem;">
                        <?= get_initials_avatar($user['name'], 120, 48, 4) ?>
                        <div>
                            <h2 style="font-size: 1.75rem; font-weight: 800; color: #111827; margin: 0 0 0.5rem 0;"><?= htmlspecialchars($user['name']) ?></h2>
                            <span class="status-pill graded" style="font-size: 0.85rem; padding: 0.25rem 0.75rem; background: #dcfce7; color: #15803d;">Active Faculty</span>
                            <p style="margin: 0.5rem 0 0 0; color: var(--text-muted); font-size: 0.95rem;">ID: <?= htmlspecialchars($user['username']) ?> | <?= htmlspecialchars($user['dept']) ?></p>
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Full Name</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['name']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Employee ID</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['username']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Email Address</label>
                            <input type="text" readonly value="<?= htmlspecialchars($current_faculty['email'] ?? '') ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Phone Number</label>
                            <input type="text" readonly value="<?= htmlspecialchars($current_faculty['phone'] ?? '') ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>

                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Department</label>
                            <input type="text" readonly value="<?= htmlspecialchars($user['dept']) ?>" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                        <div class="form-group-col">
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #4b5563; margin-bottom: 0.5rem;">Designation</label>
                            <input type="text" readonly value="Associate Professor" style="width: 100%; background: #f9fafb; cursor: not-allowed; border: 1px solid var(--border-color); padding: 0.75rem 1rem; border-radius: var(--border-radius-sm);">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- 1. LEAVE APPROVALS TAB                       -->
            <!-- ============================================ -->
            <div id="tab-leaves" class="app-view">
                <div class="data-table-container">
                    <div class="table-header-filters" style="justify-content: flex-start; background: #fafafa; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size: 1.15rem; font-weight: 700; color: #111827; padding: 0.5rem 0.25rem;">Active Leave Requests</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Student Details</th>
                                <th>Reason</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Leave Form</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center; width: 200px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($db['leaves'] as $leave): 
                                $is_ours = false;
                                foreach ($db['students'] as $stu) {
                                    if ($stu['name'] === $leave['applicant_name']) {
                                        if (in_array($stu['division'] ?? '', $faculty_divisions)) {
                                            $is_ours = true;
                                        }
                                        break;
                                    }
                                }
                                if (!$is_ours) continue;
                            ?>
                                <tr>
                                    <td><?php echo $leave['id']; ?></td>
                                    <td>
                                        <div class="publisher-cell">
                                            <span class="pub-name"><?php echo htmlspecialchars($leave['applicant_name'] ?? 'Prasad Kulkarni'); ?></span>
                                            <span class="pub-role"><?php echo htmlspecialchars($leave['applicant_role'] ?? 'Student'); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600;"><?php echo htmlspecialchars($leave['reason']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-cell"><?php echo htmlspecialchars($leave['from']); ?></span>
                                    </td>
                                    <td>
                                        <span class="date-cell"><?php echo htmlspecialchars($leave['to']); ?></span>
                                    </td>
                                    <td>
                                        <div class="publisher-cell" style="flex-direction:row; align-items:center; gap:0.5rem;">
                                            <?php 
                                                $ext = pathinfo($leave['file'], PATHINFO_EXTENSION);
                                                $is_pdf = (strtolower($ext) === 'pdf');
                                            ?>
                                            <i class="fa-solid <?php echo $is_pdf?'fa-file-pdf':'fa-file-word'; ?>" style="font-size:1.15rem; color:<?php echo $is_pdf?'#ef4444':'#0284c7'; ?>"></i>
                                            <a href="<?php echo htmlspecialchars($leave['file']); ?>" target="_blank" class="pub-name" style="font-size:0.9rem; font-weight:500; text-decoration:none; color: var(--primary-color);">
                                                <?php echo htmlspecialchars($leave['file']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php 
                                            $status = strtolower($leave['status']);
                                            $pill_class = ($status === 'approved') ? 'graded' : (($status === 'pending') ? 'pending' : 'rejected');
                                        ?>
                                        <span class="status-pill <?php echo $pill_class; ?>"><?php echo htmlspecialchars($leave['status']); ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if ($status === 'pending'): ?>
                                            <div class="faculty-actions-cell" style="display:flex; gap:0.5rem; justify-content:center;">
                                                <form method="POST" action="delete.php" style="margin:0;">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="type" value="leaves">
                                                    <input type="hidden" name="id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn-reject" style="padding: 0.4rem 0.6rem; border-radius:4px;" title="Delete" onclick="return confirm('Delete this leave request?');"><i class="fa-solid fa-trash"></i></button>
                                                </form>
                                                <form method="POST" action="faculty_dashboard.php" style="margin:0;">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn-approve">
                                                        <i class="fa-solid fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" action="faculty_dashboard.php" style="margin:0;">
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                                    <button type="submit" class="btn-reject">
                                                        <i class="fa-solid fa-xmark"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500;">No Action Needed</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- ASSIGNMENTS TAB (ENHANCED)                   -->
            <!-- ============================================ -->
            <div id="tab-assignments" class="app-view">

                <!-- Sub-tab Navigation Bar -->
                <div style="display: flex; gap: 0.4rem; margin-bottom: 2rem; background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 0.4rem; box-shadow: var(--box-shadow-subtle);">
                    <button class="assign-subtab-btn active" onclick="switchAssignSubTab('upload', this)" style="flex:1; padding:0.7rem 1rem; border:none; background:#4f46e5; color:white; border-radius:8px; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.88rem; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Assignment
                    </button>
                    <button class="assign-subtab-btn" onclick="switchAssignSubTab('subjects', this)" style="flex:1; padding:0.7rem 1rem; border:none; background:transparent; color:#64748b; border-radius:8px; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.88rem; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                        <i class="fa-solid fa-book"></i> Subject-wise Units
                    </button>
                    <button class="assign-subtab-btn" onclick="switchAssignSubTab('manage', this)" style="flex:1; padding:0.7rem 1rem; border:none; background:transparent; color:#64748b; border-radius:8px; font-weight:700; cursor:pointer; font-family:inherit; font-size:0.88rem; transition:all 0.2s; display:flex; align-items:center; justify-content:center; gap:0.4rem;">
                        <i class="fa-solid fa-chart-bar"></i> Manage Assignments
                    </button>
                </div>

                <!-- ===== PANEL 1: UPLOAD ASSIGNMENT ===== -->
                <div id="assign-sub-upload" class="assign-subpanel" style="display:block;">
                    <div style="text-align:center; margin-bottom:2rem;">
                        <h2 style="font-size:2rem; color:#4f46e5; font-weight:800; margin-bottom:0.5rem;"><i class="fa-solid fa-cloud-arrow-up" style="margin-right:0.5rem;"></i>Upload Assignment</h2>
                        <p style="color:var(--text-muted);">Select department, year, class, division, subject and unit — then upload your question file.</p>
                    </div>
                    <div style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:2rem; margin-bottom:2rem; box-shadow:var(--box-shadow-subtle);">
                        <form id="publishAssignmentForm" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="publish_assignment">

                            <!-- Row 1: Department + Year -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-building-columns" style="color:#4f46e5; margin-right:5px;"></i>Department</label>
                                    <select id="upload_dept" name="target_dept" required onchange="updateClassOptions()" style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Department --</option>
                                        <option value="Computer Engineering">Computer Engineering</option>
                                        <option value="Information Technology">Information Technology</option>
                                        <option value="Electronics Engineering">Electronics Engineering</option>
                                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                                        <option value="Civil Engineering">Civil Engineering</option>
                                        <option value="Other Engineering">Other Engineering Departments</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-layer-group" style="color:#4f46e5; margin-right:5px;"></i>Year</label>
                                    <select id="upload_year" name="target_year" required onchange="updateClassOptions()" style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Year --</option>
                                        <option value="First Year">First Year</option>
                                        <option value="Second Year">Second Year</option>
                                        <option value="Third Year">Third Year</option>
                                        <option value="Final Year">Final Year</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 2: Semester + Division -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-graduation-cap" style="color:#4f46e5; margin-right:5px;"></i>Semester</label>
                                    <select id="upload_sem" name="target_sem" required style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Semester --</option>
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
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-people-group" style="color:#4f46e5; margin-right:5px;"></i>Division</label>
                                    <select id="upload_div" name="target_div" required style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Division --</option>
                                        <option value="A">Div A</option>
                                        <option value="B">Div B</option>
                                        <option value="C">Div C</option>
                                        <option value="D">Div D</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 3: Subject + Unit -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-book-open" style="color:#4f46e5; margin-right:5px;"></i>Subject <span style="font-size:0.75rem; color:#6366f1; font-weight:500; background:#eef2ff; padding:0.1rem 0.4rem; border-radius:4px;">Your subjects only</span></label>
                                    <select id="upload_subject" name="subject_name" required style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Subject --</option>
                                        <?php foreach ($faculty_subjects as $fs): if (empty(trim($fs))) continue; ?>
                                            <option value="<?php echo htmlspecialchars($fs); ?>"><?php echo htmlspecialchars($fs); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-list-ol" style="color:#4f46e5; margin-right:5px;"></i>Unit</label>
                                    <select id="upload_unit" name="unit_number" required style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; background:white; font-size:0.95rem;">
                                        <option value="">-- Select Unit --</option>
                                        <option value="1">Unit 1</option>
                                        <option value="2">Unit 2</option>
                                        <option value="3">Unit 3</option>
                                        <option value="4">Unit 4</option>
                                        <option value="5">Unit 5</option>
                                        <option value="6">Unit 6</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Row 4: Title + Due Date -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; margin-bottom:1.5rem;">
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-tag" style="color:#4f46e5; margin-right:5px;"></i>Assignment Title</label>
                                    <input type="text" name="title" id="upload_title" required placeholder="e.g. Unit 2 - Linked List Assignment" style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.95rem;">
                                </div>
                                <div>
                                    <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-regular fa-calendar" style="color:#4f46e5; margin-right:5px;"></i>Due Date</label>
                                    <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>" style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.95rem;">
                                </div>
                            </div>

                            <!-- Row 5: Description -->
                            <div style="margin-bottom:1.5rem;">
                                <label style="display:block; margin-bottom:0.5rem; font-weight:600; font-size:0.9rem; color:#334155;"><i class="fa-solid fa-align-left" style="color:#4f46e5; margin-right:5px;"></i>Assignment Description</label>
                                <textarea name="description" required rows="3" placeholder="Provide detailed instructions for students..." style="width:100%; padding:0.75rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; resize:vertical; font-size:0.95rem;"></textarea>
                            </div>

                            <!-- Drop Zone -->
                            <div id="facultyDropZone" style="border:2px dashed #cbd5e1; border-radius:8px; padding:2rem; background:#f8fafc; margin-bottom:1.5rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; cursor:pointer; transition:all 0.2s;">
                                <div style="display:flex; align-items:center; gap:1.25rem; pointer-events:none;">
                                    <div id="upload-icon-container" style="width:56px; height:56px; background:#e0e7ff; color:#4f46e5; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0;">
                                        <i class="fa-solid fa-cloud-arrow-up"></i>
                                    </div>
                                    <div style="text-align:left;">
                                        <h4 id="upload-title-text" style="font-weight:600; margin-bottom:0.25rem; font-size:1.05rem; color:#1e293b;">Upload Question File *</h4>
                                        <p id="upload-status-text" style="font-size:0.9rem; color:var(--text-muted);">Click here to <span style="color:#4f46e5; font-weight:600;">browse</span> and select a file</p>
                                        <input id="file-upload" type="file" name="assignment_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none;">
                                        <p id="upload-allowed-text" style="font-size:0.8rem; color:#94a3b8; margin-top:0.35rem;">PDF, DOC, DOCX, JPG, JPEG, PNG allowed (Max 10MB)</p>
                                    </div>
                                </div>
                                <div id="dynamic-btn-area">
                                    <label for="file-upload" style="background:white; border:1px solid var(--border-color); padding:0.65rem 1.25rem; border-radius:6px; color:#4f46e5; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:0.5rem; box-shadow:0 1px 2px rgba(0,0,0,0.05); transition:background 0.2s;">
                                        <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                                    </label>
                                </div>
                            </div>

                            <!-- Progress Bar -->
                            <div id="uploadProgressContainer" style="display:none; background:white; border:1px solid var(--border-color); border-radius:8px; padding:1.25rem; margin-bottom:1.5rem;">
                                <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700; color:#475569; margin-bottom:0.25rem;">
                                    <span id="progressStatusLabel">Uploading...</span>
                                    <span id="progressPercentText">0%</span>
                                </div>
                                <div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden;">
                                    <div id="progressBarFill" style="width:0%; height:100%; background:#10b981; transition:width 0.1s;"></div>
                                </div>
                            </div>

                            <div style="display:flex; justify-content:flex-end;">
                                <button id="btnPublishAssignment" type="submit" style="background:linear-gradient(135deg,#4f46e5,#7c3aed); color:white; border:none; padding:0.85rem 2rem; border-radius:8px; font-weight:700; cursor:pointer; font-family:inherit; font-size:1rem; box-shadow:0 4px 12px rgba(79,70,229,0.3); transition:transform 0.2s,box-shadow 0.2s; display:flex; align-items:center; gap:0.5rem;">
                                    <i class="fa-solid fa-paper-plane"></i> Publish Assignment
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ===== PANEL 2: SUBJECT-WISE UNITS ===== -->
                <div id="assign-sub-subjects" class="assign-subpanel" style="display:none;">
                    <div style="text-align:center; margin-bottom:2rem;">
                        <h2 style="font-size:2rem; color:#4f46e5; font-weight:800; margin-bottom:0.5rem;"><i class="fa-solid fa-book" style="margin-right:0.5rem;"></i>Subject-wise Unit View</h2>
                        <p style="color:var(--text-muted);">Each subject shows Units 1–6. Click <strong>Upload</strong> to quickly publish an assignment for that unit.</p>
                    </div>
                    <?php
                    $valid_subjects = array_filter($faculty_subjects, function($s){ return !empty(trim($s)); });
                    if (empty($valid_subjects)):
                    ?>
                    <div style="padding:3rem; text-align:center; background:white; border-radius:12px; border:1px solid var(--border-color); color:var(--text-muted);">
                        <i class="fa-solid fa-book-open" style="font-size:2.5rem; color:#cbd5e1; margin-bottom:1rem; display:block;"></i>
                        No subjects assigned. Please contact admin.
                    </div>
                    <?php else: foreach ($valid_subjects as $fsubj): ?>
                    <div style="background:white; border:1px solid var(--border-color); border-radius:12px; margin-bottom:1.5rem; box-shadow:var(--box-shadow-subtle); overflow:hidden;">
                        <!-- Subject Header -->
                        <div style="background:linear-gradient(135deg,#4f46e5,#7c3aed); padding:1.25rem 1.75rem; display:flex; align-items:center; gap:1rem;">
                            <div style="width:44px; height:44px; background:rgba(255,255,255,0.2); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; color:white; flex-shrink:0;">
                                <i class="fa-solid fa-book"></i>
                            </div>
                            <div>
                                <h4 style="color:white; font-size:1.05rem; font-weight:700; margin:0;"><?= htmlspecialchars($fsubj) ?></h4>
                                <p style="color:rgba(255,255,255,0.75); font-size:0.82rem; margin:0.15rem 0 0 0;">Click any unit to upload an assignment</p>
                            </div>
                        </div>
                        <!-- Units Grid -->
                        <div style="display:grid; grid-template-columns:repeat(6,1fr);">
                            <?php for ($u = 1; $u <= 6; $u++):
                                $existing_count = 0;
                                foreach ($db['subject_assignments'] as $sa_chk) {
                                    if ($sa_chk['subject_name'] === $fsubj) {
                                        foreach ($db['assignments'] as $a_chk) {
                                            if ($a_chk['id'] == $sa_chk['assignment_id'] && intval($a_chk['unit']) === $u) {
                                                $existing_count++; break;
                                            }
                                        }
                                    }
                                }
                                $u_bg = $existing_count > 0 ? '#f0fdf4' : 'white';
                                $u_border = $u < 6 ? 'border-right:1px solid var(--border-color);' : '';
                            ?>
                            <div style="<?= $u_border ?> padding:1.25rem 0.75rem; text-align:center; background:<?= $u_bg ?>; transition:background 0.2s;">
                                <div style="font-size:0.72rem; font-weight:700; color:#64748b; text-transform:uppercase; margin-bottom:0.4rem; letter-spacing:0.04em;">Unit <?= $u ?></div>
                                <?php if ($existing_count > 0): ?>
                                    <div style="font-size:0.68rem; color:#10b981; font-weight:600; margin-bottom:0.5rem;"><i class="fa-solid fa-circle-check"></i> <?= $existing_count ?> uploaded</div>
                                <?php else: ?>
                                    <div style="font-size:0.68rem; color:#94a3b8; margin-bottom:0.5rem;">Not uploaded</div>
                                <?php endif; ?>
                                <button onclick="prefillUploadForm('<?= htmlspecialchars(addslashes($fsubj), ENT_QUOTES) ?>', <?= $u ?>)" style="background:<?= $existing_count > 0 ? '#10b981' : '#4f46e5' ?>; color:white; border:none; padding:0.4rem 0.55rem; border-radius:6px; font-size:0.72rem; font-weight:700; cursor:pointer; font-family:inherit; display:flex; align-items:center; gap:3px; margin:0 auto; white-space:nowrap;">
                                    <i class="fa-solid fa-upload" style="font-size:0.65rem;"></i> <?= $existing_count > 0 ? 'Re-upload' : 'Upload' ?>
                                </button>
                            </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- ===== PANEL 4: MANAGE ASSIGNMENTS + SUBMISSION TRACKING ===== -->
                <div id="assign-sub-manage" class="assign-subpanel" style="display:none;">
                    <?php
                    $my_published_sas = [];
                    if (isset($db['subject_assignments'])) {
                        foreach ($db['subject_assignments'] as $sa) {
                            if (in_array($sa['subject_name'], $faculty_subjects)) {
                                // Fetch the unit from assignments
                                $unit = 1; // Default
                                if (isset($db['assignments'])) {
                                    foreach ($db['assignments'] as $a) {
                                        if ($a['id'] == $sa['assignment_id']) {
                                            $unit = $a['unit'];
                                            break;
                                        }
                                    }
                                }
                                $sa['unit'] = $unit;
                                $my_published_sas[] = $sa;
                            }
                        }
                    }
                    $my_published_sas = array_reverse($my_published_sas);
                    
                    // Output assignments array to JS for cascading filters
                    echo "<script>var _myAssignments = " . json_encode($my_published_sas) . ";</script>";
                    ?>
                    
                    <!-- Filter UI -->
                    <div style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; box-shadow:var(--box-shadow-subtle);">
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:1rem; align-items:end;">
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Department</label>
                                <select id="filter_dept" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="Computer Engineering">Computer Engineering</option>
                                    <option value="Information Technology">Information Technology</option>
                                    <option value="Electronics Engineering">Electronics</option>
                                    <option value="Mechanical Engineering">Mechanical</option>
                                    <option value="Civil Engineering">Civil</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Year</label>
                                <select id="filter_year" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="First Year">First Year</option>
                                    <option value="Second Year">Second Year</option>
                                    <option value="Third Year">Third Year</option>
                                    <option value="Final Year">Final Year</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Semester</label>
                                <select id="filter_sem" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <?php 
                                    $sem_arr = ['1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th'];
                                    foreach($sem_arr as $s) echo "<option value='{$s} Semester'>{$s} Semester</option>"; 
                                    ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Division</label>
                                <select id="filter_div" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <option value="A">Div A</option>
                                    <option value="B">Div B</option>
                                    <option value="C">Div C</option>
                                    <option value="D">Div D</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.85rem; font-weight:600; color:#475569; margin-bottom:0.35rem;">Subject</label>
                                <select id="filter_subject" class="manage-filter" onchange="updateCascadingFilters()" style="width:100%; padding:0.6rem; border:1px solid var(--border-color); border-radius:6px; font-family:inherit; font-size:0.9rem; background:white;">
                                    <option value="">-- Select --</option>
                                    <?php foreach($faculty_subjects as $sub) {
                                        if (trim($sub) !== '') echo "<option value='" . htmlspecialchars($sub) . "'>" . htmlspecialchars($sub) . "</option>";
                                    } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div id="unit_cards_container" style="display:none; display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:1.5rem; margin-bottom:2rem;">
                        <!-- Dynamically populated by JS -->
                    </div>
                    </div>
                    </div>

                    <!-- Hidden Data Elements for JS to consume -->
                    <div id="manage_data_store" style="display:none;">
                        <?php 
                        // Output students array
                        echo "<script>var _allStudents = " . json_encode($db['students']) . ";</script>";
                        
                        // Output submissions grouped by subject_assignment_id
                        $all_subs = [];
                        if (isset($db['assignment_submissions'])) {
                            foreach ($db['assignment_submissions'] as $sub) {
                                $said = $sub['subject_assignment_id'];
                                if (!isset($all_subs[$said])) $all_subs[$said] = [];
                                $all_subs[$said][$sub['student_id']] = $sub;
                            }
                        }
                        echo "<script>var _allSubmissions = " . json_encode($all_subs) . ";</script>";
                        ?>
                    </div>

                    <!-- Grading Table Container -->
                    <div id="grading_container" style="display:none; background:white; border:1px solid var(--border-color); border-radius:12px; overflow:hidden; box-shadow:var(--box-shadow-subtle);">
                        <div style="padding:1rem 1.5rem; background:#f8fafc; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                            <h3 style="margin:0; font-size:1.05rem; color:#1e293b;"><i class="fa-solid fa-users" style="color:#4f46e5; margin-right:0.5rem;"></i>Student Submissions</h3>
                            <div id="grading_stats" style="font-size:0.85rem; font-weight:600; color:#64748b; background:white; padding:0.25rem 0.75rem; border:1px solid var(--border-color); border-radius:20px;">
                                Total: 0 | Submitted: 0 | Pending: 0
                            </div>
                        </div>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse; min-width:700px;">
                                <thead style="background:#f1f5f9; font-size:0.75rem; text-transform:uppercase; color:#475569; letter-spacing:0.05em;">
                                    <tr>
                                        <th style="padding:0.9rem 1.25rem; text-align:left; border-bottom:1px solid var(--border-color);">PRN</th>
                                        <th style="padding:0.9rem 1.25rem; text-align:left; border-bottom:1px solid var(--border-color);">Student Name</th>
                                        <th style="padding:0.9rem 1.25rem; text-align:center; border-bottom:1px solid var(--border-color);">Submission Status</th>
                                        <th style="padding:0.9rem 1.25rem; text-align:left; border-bottom:1px solid var(--border-color);">File</th>
                                        <th style="padding:0.9rem 1.25rem; text-align:left; border-bottom:1px solid var(--border-color);">Marks</th>
                                        <th style="padding:0.9rem 1.25rem; text-align:center; border-bottom:1px solid var(--border-color);">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="grading_tbody">
                                    <!-- Populated via JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- NOTICES TAB                                  -->
            <!-- ============================================ -->
            <div id="tab-notices" class="app-view">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h2 style="font-size: 2.25rem; color: #3b82f6; font-weight: 800; margin-bottom: 0.5rem;">Publish Notice</h2>
                    <p style="color: var(--text-muted);">Post announcements and broadcast updates to everyone.</p>
                </div>
                
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 2rem; margin-bottom: 3rem; box-shadow: var(--box-shadow-subtle);">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="publish_notice">
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Notice Title</label>
                            <input type="text" name="title" required placeholder="e.g. Extra Class Scheduled" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Description</label>
                            <textarea name="desc" rows="4" required placeholder="Enter notice details..." style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem; resize: vertical;"></textarea>
                        </div>
                        
                        <div style="margin-bottom: 1.5rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.9rem; color: #334155;">Expiry Date (Optional)</label>
                            <input type="date" name="expiry" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 1rem;">
                        </div>
                        
                        <div style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; background: #f8fafc; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div style="display: flex; align-items: center; gap: 1.25rem;">
                                <div style="width: 56px; height: 56px; background: #dbeafe; color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; flex-shrink: 0;">
                                    <i class="fa-solid fa-paperclip"></i>
                                </div>
                                <div style="text-align: left;">
                                    <h4 style="font-weight: 600; margin-bottom: 0.25rem; font-size: 1.05rem; color: #1e293b;">Attach File (Optional)</h4>
                                    <p style="font-size: 0.9rem; color: var(--text-muted);">Click here to <label for="notice-file-upload" style="color: #3b82f6; font-weight: 600; cursor: pointer;">browse</label> and select a file</p>
                                    <input id="notice-file-upload" type="file" name="attachment" style="display: none;">
                                    <p style="font-size: 0.8rem; color: #94a3b8; margin-top: 0.35rem;">Supported formats: PDF, DOCX, JPG, PNG (Max 5MB)</p>
                                </div>
                            </div>
                            <label for="notice-file-upload" style="background: white; border: 1px solid var(--border-color); padding: 0.65rem 1.25rem; border-radius: 6px; color: #3b82f6; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                            </label>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" style="background: #3b82f6; color: white; border: none; padding: 0.85rem 1.75rem; border-radius: 6px; font-weight: 600; cursor: pointer; font-family: inherit; font-size: 1rem; box-shadow: 0 4px 6px rgba(59, 130, 246, 0.2); transition: transform 0.2s, box-shadow 0.2s;">Publish Notice</button>
                        </div>
                    </form>
                </div>
                
                <h3 style="font-size: 1.35rem; font-weight: 700; margin-top: 3rem; margin-bottom: 1.5rem; color: #1e293b;">Published Notices</h3>
                
                <?php 
                foreach ($db['notices'] as $n): 
                    if ($n['author'] !== $current_faculty['name']) continue;
                ?>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 1.5rem; box-shadow: var(--box-shadow-subtle); overflow: hidden;">
                    <div style="padding: 1.5rem; display: flex; gap: 1.25rem; align-items: flex-start;">
                        <div style="width: 48px; height: 48px; background: #fff1f2; color: #e11d48; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; flex-shrink: 0;">
                            <i class="fa-solid fa-bullhorn"></i>
                        </div>
                        <div style="flex: 1;">
                            <h4 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem; color: #1e293b;"><?= htmlspecialchars($n['title']) ?></h4>
                            <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 0.65rem;"><?= htmlspecialchars($n['desc']) ?></p>
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

            <!-- ============================================ -->
            <!-- GRIEVANCES TAB                               -->
            <!-- ============================================ -->
            <div id="tab-grievances" class="app-view">
                <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: #1e293b;">Submitted Grievances</h3>
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto; box-shadow: var(--box-shadow-subtle);">
                    <table style="width: 100%; border-collapse: collapse; min-width: 800px;">
                        <thead style="background: #f8fafc; font-size: 0.85rem; color: #1e293b; font-weight: 600;">
                            <tr>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color); width: 60px;">#</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Student Details</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Subject</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Date Submitted</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Render general grievances from newest to oldest (exclude assignment-type)
                            $grievances = array_reverse($db['grievances']);
                            $my_grievances = [];
                            $general_exclude_categories = ['assignment', 'Assignment', 'assignment_document'];
                            foreach ($grievances as $g) {
                                // Exclude assignment-type grievances — they appear in Assignment Document Grievances
                                if (in_array(strtolower($g['category'] ?? ''), ['assignment', 'assignment_document'])) {
                                    continue;
                                }
                                $is_ours = false;
                                foreach ($db['students'] as $stu) {
                                    if ($stu['id'] === $g['student_id']) {
                                        if (in_array($stu['division'] ?? '', $faculty_divisions)) {
                                            $is_ours = true;
                                        }
                                        break;
                                    }
                                }
                                if ($is_ours) {
                                    $my_grievances[] = $g;
                                }
                            }
                            foreach ($my_grievances as $idx => $g): 
                            ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1.25rem 1.5rem; font-size: 0.95rem; color: #334155;"><?= $idx + 1 ?></td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="display: flex; align-items: center; gap: 1rem;">
                                        <?php 
                                            $parts = explode(" ", $g['student_name']);
                                            $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                        ?>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; background: #f3e8ff; color: #6b21a8; font-weight: 600; font-size: 1rem; display: flex; align-items: center; justify-content: center;">
                                            <?= $initials ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem;"><?= htmlspecialchars($g['student_name']) ?></div>
                                            <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.15rem;">PRN: <?= htmlspecialchars($g['student_id']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 600; color: #1e293b; font-size: 0.95rem; margin-bottom: 0.25rem;"><?= htmlspecialchars($g['category']) ?></div>
                                    <div style="font-size: 0.85rem; color: #475569; margin-bottom: 0.35rem;"><?= htmlspecialchars($g['title']) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-size: 0.9rem; color: #334155;">
                                    <?= htmlspecialchars($g['date']) ?>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: center;">
                                    <div style="display:flex; gap:0.5rem; align-items:center; justify-content:center;">
                                        <?php if (isset($g['status']) && $g['status'] === 'Resolved'): ?>
                                            <span style="display: inline-block; padding: 0.35rem 1rem; background: #dcfce7; color: #166534; font-size: 0.85rem; font-weight: 600; border-radius: 6px; height: 32px; display: flex; align-items: center;">Resolved</span>
                                        <?php else: ?>
                                            <form method="POST" style="margin: 0;">
                                                <input type="hidden" name="action" value="resolve_grievance">
                                                <input type="hidden" name="grievance_id" value="<?= $g['id'] ?>">
                                                <button type="submit" style="background: white; border: 1px solid #4f46e5; color: #4f46e5; padding: 0.4rem 0.85rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; height: 32px; display: flex; align-items: center;">
                                                    <i class="fa-solid fa-check" style="margin-right: 0.35rem;"></i> Mark as Resolved
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick='showGrievanceDetails(<?= htmlspecialchars(json_encode($g), ENT_QUOTES, "UTF-8") ?>); return false;' style="background: white; border: 1px solid #64748b; color: #64748b; padding: 0.4rem 0.85rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all 0.2s; height: 32px; display: flex; align-items: center;">View Chat</button>
                                        <form method="POST" action="delete.php" style="margin:0;">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="type" value="grievances">
                                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                                            <button type="submit" style="background: white; border: 1px solid #ef4444; color: #ef4444; padding: 0.4rem 0.6rem; border-radius: 6px; font-weight: 600; cursor: pointer; height: 32px; display: flex; align-items: center; justify-content: center;" title="Delete" onclick="return confirm('Delete this grievance?');"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h3 style="font-size: 1.25rem; font-weight: 700; margin-top: 3rem; margin-bottom: 1.5rem; color: #1e293b;">Assignment Document Grievances</h3>
                
                <!-- Filters for Assignment Grievances -->
                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; gap: 1rem; box-shadow: var(--box-shadow-subtle);">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem;">Filter by Subject</label>
                        <select id="facGrievanceSubjectFilter" onchange="filterFacultyGrievances()" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 0.9rem; background: white;">
                            <option value="all">All Subjects</option>
                            <?php foreach ($faculty_subjects as $fs): ?>
                                <option value="<?php echo htmlspecialchars($fs); ?>"><?php echo htmlspecialchars($fs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #475569; margin-bottom: 0.35rem;">Filter by Status</label>
                        <select id="facGrievanceStatusFilter" onchange="filterFacultyGrievances()" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; font-size: 0.9rem; background: white;">
                            <option value="all">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Resolved">Resolved</option>
                        </select>
                    </div>
                </div>

                <div style="background: white; border: 1px solid var(--border-color); border-radius: 12px; overflow-x: auto; box-shadow: var(--box-shadow-subtle); margin-bottom: 3rem;">
                    <table style="width: 100%; border-collapse: collapse; min-width: 900px;">
                        <thead style="background: #f8fafc; font-size: 0.85rem; color: #1e293b; font-weight: 600;">
                            <tr>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Student Name</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">PRN</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Subject</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Assignment</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Issue Type</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Date Submitted</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Status</th>
                                <th style="padding: 1.25rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-color);">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $assign_grievances = array_reverse($db['assignment_grievances'] ?? []);
                            $my_assign_grievances = [];
                            $seen_ag_ids = []; // for deduplication
                            foreach ($assign_grievances as $g) {
                                // Deduplicate by grievance ID
                                $gid = intval($g['id']);
                                if (in_array($gid, $seen_ag_ids)) continue;
                                $seen_ag_ids[] = $gid;

                                $is_mine = ($g['faculty_id'] ?? '') === $current_faculty['username'];
                                $is_my_dept = $is_hod && ($g['department_id'] ?? '') === $my_department_id;
                                if ($is_mine || $is_my_dept) {
                                    $my_assign_grievances[] = $g;
                                }
                            }
                            if (empty($my_assign_grievances)):
                            ?>
                                <tr>
                                    <td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-muted);">No assignment grievances submitted yet.</td>
                                </tr>
                            <?php 
                            else:
                                foreach ($my_assign_grievances as $idx => $g): 
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
                            <tr class="assignment-grievance-row" data-subject="<?= htmlspecialchars($subject_name) ?>" data-status="<?= htmlspecialchars($g['status'] ?? 'Pending') ?>" style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem 1.5rem; font-weight: 600; color: #1e293b;"><?= htmlspecialchars($g['student_name']) ?></td>
                                <td style="padding: 1rem 1.5rem; color: #475569; font-size: 0.9rem;"><?= htmlspecialchars($g['student_id']) ?></td>
                                <td style="padding: 1rem 1.5rem; color: #1e293b;"><?= htmlspecialchars($subject_name) ?></td>
                                <td style="padding: 1rem 1.5rem; color: #475569; font-size: 0.9rem;"><?= htmlspecialchars($assign_title) ?></td>
                                <td style="padding: 1rem 1.5rem; color: #b91c1c; font-weight: 500; font-size: 0.9rem;"><?= htmlspecialchars($g['issue_type']) ?></td>
                                <?php
                                    $raw_date = $g['created_at'] ?? '';
                                    // Replace invalid MySQL zero-date with N/A
                                    $display_date = (empty($raw_date) || str_starts_with($raw_date, '0000')) ? 'N/A' : htmlspecialchars($raw_date);
                                ?>
                                <td style="padding: 1rem 1.5rem; color: #475569; font-size: 0.9rem;"><?= $display_date ?></td>
                                <td style="padding: 1rem 1.5rem;">
                                    <?php 
                                        $status_color = '#eab308'; // Pending (Yellow)
                                        if (($g['status'] ?? '') === 'Resolved') $status_color = '#10b981'; // Green
                                        elseif (($g['status'] ?? '') === 'In Progress') $status_color = '#3b82f6'; // Blue
                                    ?>
                                    <span style="background: <?= $status_color ?>20; color: <?= $status_color ?>; padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700;">
                                        <?= htmlspecialchars($g['status'] ?? 'Pending') ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem 1.5rem;">
                                    <?php
                                        // Package data for modal
                                        $modal_data = htmlspecialchars(json_encode([
                                            'id' => $g['id'],
                                            'sa_id' => $g['subject_assignment_id'],
                                            'student_name' => $g['student_name'],
                                            'student_id' => $g['student_id'],
                                            'issue_type' => $g['issue_type'],
                                            'description' => $g['description'],
                                            'screenshot' => $g['screenshot'] ?? '',
                                            'reply' => $g['reply'] ?? '',
                                            'status' => $g['status'] ?? 'Pending'
                                        ]), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <button type="button" onclick="openAssignGrievanceModal(<?= $modal_data ?>)" style="background: #4f46e5; color: white; border: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: background 0.2s;">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                            <?php 
                                endforeach; 
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Grievance Details Modal -->
            <div id="grievanceModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1050; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;">
                <div class="modal-content" style="background: #fff; width: 100%; max-width: 500px; border-radius: 16px; padding: 30px; transform: translateY(20px); transition: transform 0.3s ease; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Grievance Details</h3>
                        <button onclick="closeGrievanceModal()" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer; transition: color 0.2s;"><i class="fa-solid fa-xmark"></i></button>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px;">Student Name & ID</div>
                        <div id="modal-g-student" style="font-size: 1rem; color: #1e293b; font-weight: 600;"></div>
                    </div>
                    <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px;">Category</div>
                            <div id="modal-g-category" style="font-size: 0.95rem; color: #334155; font-weight: 500;"></div>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px;">Date Submitted</div>
                            <div id="modal-g-date" style="font-size: 0.95rem; color: #334155; font-weight: 500;"></div>
                        </div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px;">Title</div>
                        <div id="modal-g-title" style="font-size: 0.95rem; color: #1e293b; font-weight: 600;"></div>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px; letter-spacing: 0.5px;">Description</div>
                        <div id="modal-g-desc" style="font-size: 0.95rem; color: #475569; line-height: 1.5; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; white-space: pre-wrap;"></div>
                    </div>
                    <div style="display: flex; justify-content: flex-end;">
                        <button onclick="closeGrievanceModal()" style="background: #e2e8f0; color: #475569; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">Close</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- JavaScript code for navigation -->
    <script>
        function showGrievanceDetails(g) {
            document.getElementById('modal-g-student').textContent = g.student_name + ' (' + g.student_id + ')';
            document.getElementById('modal-g-category').textContent = g.category;
            document.getElementById('modal-g-date').textContent = g.date;
            document.getElementById('modal-g-title').textContent = g.title;
            document.getElementById('modal-g-desc').textContent = g.desc || 'No description provided.';
            
            const modal = document.getElementById('grievanceModal');
            modal.style.display = 'flex';
            // Trigger reflow
            void modal.offsetWidth;
            modal.style.opacity = '1';
            modal.querySelector('.modal-content').style.transform = 'translateY(0)';
        }
        
        function closeGrievanceModal() {
            const modal = document.getElementById('grievanceModal');
            modal.style.opacity = '0';
            modal.querySelector('.modal-content').style.transform = 'translateY(20px)';
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            const modal = document.getElementById('grievanceModal');
            if (e.target === modal) {
                closeGrievanceModal();
            }
        });
        function switchTab(tabName, element) {
            const items = document.querySelectorAll('.sidebar-nav-item');
            items.forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            const panels = document.querySelectorAll('.app-view');
            panels.forEach(p => p.classList.remove('active'));

            const headerTitle = document.getElementById('currentTabTitle');
            const headerSubtitle = document.getElementById('currentTabSubtitle');

            // Show selected panel
            if (tabName === 'leaves') {
                document.getElementById('tab-leaves').classList.add('active');
                headerTitle.textContent = "Leave Approvals";
                headerSubtitle.textContent = "Manage and respond to student leave requests.";
            } else if (tabName === 'assignments') {
                document.getElementById('tab-assignments').classList.add('active');
                headerTitle.textContent = "Manage Assignments";
                headerSubtitle.textContent = "Create assignments and grade student submissions.";
            } else if (tabName === 'notices') {
                document.getElementById('tab-notices').classList.add('active');
                headerTitle.textContent = "Publish Notices";
                headerSubtitle.textContent = "Create and broadcast important announcements to students.";
            } else if (tabName === 'grievances') {
                document.getElementById('tab-grievances').classList.add('active');
                headerTitle.textContent = "Grievance";
                headerSubtitle.textContent = "Review and address student issues and complaints.";
            } else if (tabName === 'dashboard') {
                document.getElementById('tab-dashboard').classList.add('active');
                headerTitle.textContent = "Dashboard";
                headerSubtitle.textContent = "Quick access to all essential faculty services.";
            } else if (tabName === 'attendance') {
                document.getElementById('tab-attendance').classList.add('active');
                headerTitle.textContent = "Mark Attendance";
                headerSubtitle.textContent = "Select your teaching class and mark student lecture attendance.";
            } else if (tabName === 'profile') {
                document.getElementById('tab-profile').classList.add('active');
                headerTitle.textContent = "My Profile";
                headerSubtitle.textContent = "View and manage your professional credentials.";
            }
        }
    </script>
    <script>
        function markAllAttendance(status) {
            document.querySelectorAll(`input[type="radio"][value="${status}"]`).forEach(radio => {
                radio.checked = true;
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // File upload logic for faculty assignment
            let selectedFacultyFile = null;
            const dropZone = document.getElementById('facultyDropZone');
            const fileInput = document.getElementById('file-upload');
            const uploadTitleText = document.getElementById('upload-title-text');
            const uploadStatusText = document.getElementById('upload-status-text');
            const uploadAllowedText = document.getElementById('upload-allowed-text');
            const dynamicBtnArea = document.getElementById('dynamic-btn-area');
            const publishForm = document.getElementById('publishAssignmentForm');
            const progressContainer = document.getElementById('uploadProgressContainer');
            const progressBarFill = document.getElementById('progressBarFill');
            const progressPercentText = document.getElementById('progressPercentText');
            const publishBtn = document.getElementById('btnPublishAssignment');

            function showToastNotification(message, type = 'success') {
                const toast = document.createElement('div');
                toast.className = `toast-notification toast-${type}`;
                toast.style.position = 'fixed';
                toast.style.top = '20px';
                toast.style.right = '20px';
                toast.style.zIndex = '99999';
                toast.style.display = 'flex';
                toast.style.alignItems = 'center';
                toast.style.gap = '0.5rem';
                toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
                toast.style.color = 'white';
                toast.style.padding = '0.75rem 1.5rem';
                toast.style.borderRadius = '8px';
                toast.style.boxShadow = '0 10px 15px -3px rgba(0, 0, 0, 0.1)';
                toast.style.transition = 'opacity 0.3s ease';
                toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'}"></i><span>${message}</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            }

            if (dropZone && fileInput) {
                // Prevent default drag behaviors
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, e => {
                        e.preventDefault();
                        e.stopPropagation();
                    }, false);
                });

                // Highlight/unhighlight drop zone
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => {
                        dropZone.style.borderColor = '#4f46e5';
                        dropZone.style.background = '#f5f3ff';
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    dropZone.addEventListener(eventName, () => {
                        dropZone.style.borderColor = '#cbd5e1';
                        dropZone.style.background = '#f8fafc';
                    }, false);
                });

                // Handle dropped files
                dropZone.addEventListener('drop', e => {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    if (files.length > 0) {
                        validateAndSetFile(files[0]);
                    }
                });

                // Handle file input selection
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        validateAndSetFile(this.files[0]);
                    }
                });

                // Click zone to trigger file browser
                dropZone.addEventListener('click', e => {
                    if (e.target.closest('#btn-remove-file') || e.target.closest('#btn-change-file')) {
                        return; // Let button click handlers handle it
                    }
                    fileInput.click();
                });

                function validateAndSetFile(file) {
                    const ext = file.name.split('.').pop().toLowerCase();
                    const allowedExts = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
                    if (!allowedExts.includes(ext)) {
                        showToastNotification('Unsupported file type. Only PDF, DOC, DOCX, JPG, JPEG, and PNG are allowed.', 'error');
                        resetFileSelection();
                        return;
                    }

                    if (file.size > 10 * 1024 * 1024) {
                        showToastNotification('File is too large. Maximum size is 10 MB.', 'error');
                        resetFileSelection();
                        return;
                    }

                    selectedFacultyFile = file;
                    
                    // Update UI
                    uploadTitleText.textContent = file.name;
                    const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                    uploadStatusText.innerHTML = `<span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> File selected successfully.</span> (${sizeMB} MB, Type: ${ext.toUpperCase()})`;
                    
                    dynamicBtnArea.innerHTML = `
                        <div style="display: flex; gap: 0.5rem;">
                            <button type="button" id="btn-change-file" style="background: white; border: 1px solid var(--border-color); padding: 0.5rem 1rem; border-radius: 6px; color: #4f46e5; font-weight: 600; cursor: pointer; font-size: 0.85rem;"><i class="fa-solid fa-arrows-rotate"></i> Change File</button>
                            <button type="button" id="btn-remove-file" style="background: #fee2e2; border: 1px solid #fecaca; padding: 0.5rem 1rem; border-radius: 6px; color: #b91c1c; font-weight: 600; cursor: pointer; font-size: 0.85rem;"><i class="fa-solid fa-trash-can"></i> Remove</button>
                        </div>
                    `;

                    // Bind change/remove buttons
                    document.getElementById('btn-change-file').addEventListener('click', (e) => {
                        e.stopPropagation();
                        fileInput.click();
                    });

                    document.getElementById('btn-remove-file').addEventListener('click', (e) => {
                        e.stopPropagation();
                        resetFileSelection();
                    });
                }

                function resetFileSelection() {
                    selectedFacultyFile = null;
                    fileInput.value = '';
                    uploadTitleText.textContent = "Upload Question File *";
                    uploadStatusText.innerHTML = 'Click here to <span style="color: #4f46e5; font-weight: 600;">browse</span> and select a file';
                    dynamicBtnArea.innerHTML = `
                        <label for="file-upload" style="background: white; border: 1px solid var(--border-color); padding: 0.65rem 1.25rem; border-radius: 6px; color: #4f46e5; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background 0.2s;">
                            <i class="fa-solid fa-arrow-up-from-bracket"></i> Choose File
                        </label>
                    `;
                }
            }

            // Form Submit via AJAX
            if (publishForm) {
                publishForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!selectedFacultyFile) {
                        showToastNotification('Please select a valid question file first.', 'error');
                        return;
                    }

                    publishBtn.disabled = true;
                    publishBtn.style.opacity = '0.7';
                    progressContainer.style.display = 'block';
                    progressBarFill.style.width = '0%';
                    progressPercentText.textContent = '0%';

                    const formData = new FormData(this);
                    formData.set('assignment_file', selectedFacultyFile);

                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'faculty_dashboard.php', true);
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            const percent = Math.round((e.loaded / e.total) * 100);
                            progressBarFill.style.width = percent + '%';
                            progressPercentText.textContent = percent + '%';
                        }
                    });

                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === XMLHttpRequest.DONE) {
                            publishBtn.disabled = false;
                            publishBtn.style.opacity = '1';
                            progressContainer.style.display = 'none';

                            if (xhr.status === 200) {
                                try {
                                    const res = JSON.parse(xhr.responseText);
                                    if (res.success) {
                                        showToastNotification('✓ Assignment published successfully.', 'success');
                                        setTimeout(() => {
                                            window.location.href = 'faculty_dashboard.php?tab=assignments';
                                        }, 1500);
                                    } else {
                                        showToastNotification(res.message || 'Upload failed.', 'error');
                                    }
                                } catch(err) {
                                    showToastNotification('Server response parse failed.', 'error');
                                }
                            } else {
                                try {
                                    const res = JSON.parse(xhr.responseText);
                                    showToastNotification(res.message || 'An error occurred during publication.', 'error');
                                } catch(err) {
                                    showToastNotification('Server error: ' + xhr.statusText, 'error');
                                }
                            }
                        }
                    };

                    xhr.send(formData);
                });
            }

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
            if (localStorage.getItem('theme_preference') === 'dark') {
                document.body.classList.add('dark-mode');
                updateThemeIcon(true);
            }
        });

        function filterFacultyGrievances() {
            const subjectVal = document.getElementById('facGrievanceSubjectFilter').value;
            const statusVal = document.getElementById('facGrievanceStatusFilter').value;
            
            document.querySelectorAll('.assignment-grievance-row').forEach(row => {
                const matchSub = (subjectVal === 'all' || row.getAttribute('data-subject') === subjectVal);
                const matchStatus = (statusVal === 'all' || row.getAttribute('data-status') === statusVal);
                
                if (matchSub && matchStatus) {
                    row.style.display = 'table-row';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        function toggleFacultyPreview(path, type, divId) {
            const pane = document.getElementById(divId);
            if (!pane) return;
            if (pane.style.display === 'block') {
                pane.style.display = 'none';
                pane.innerHTML = '';
            } else {
                pane.style.display = 'block';
                const ext = type.toLowerCase();
                if (['jpg', 'jpeg', 'png'].includes(ext)) {
                    pane.innerHTML = `<img src="${path}" style="max-width:100%; max-height:200px; object-fit:contain; border-radius:4px;">`;
                } else if (ext === 'pdf') {
                    pane.innerHTML = `<iframe src="${path}" style="width:100%; height:200px; border:none; border-radius:4px;"></iframe>`;
                } else {
                    pane.innerHTML = `<div style="text-align:center; font-size:0.75rem; color:#64748b; padding:1rem;"><i class="fa-solid fa-file-word" style="font-size:2rem; color:#2b579a; display:block; margin-bottom:0.25rem;"></i> Preview unavailable for ${ext.toUpperCase()}</div>`;
                }
            }
        }
    </script>
    <script>
        /* ===== ASSIGNMENT MODULE — ENHANCED JS ===== */

        function switchAssignSubTab(name, btn) {
            document.querySelectorAll('.assign-subpanel').forEach(function(p) { p.style.display = 'none'; });
            document.querySelectorAll('.assign-subtab-btn').forEach(function(b) {
                b.style.background = 'transparent';
                b.style.color = '#64748b';
            });
            var panel = document.getElementById('assign-sub-' + name);
            if (panel) panel.style.display = 'block';
            if (btn) { btn.style.background = '#4f46e5'; btn.style.color = 'white'; }
        }

        function loadAssignmentStudents(assignmentId) {
            var f_dept = document.getElementById('filter_dept').value;
            var f_year = document.getElementById('filter_year').value;
            var f_sem  = document.getElementById('filter_sem').value;
            var f_div  = document.getElementById('filter_div').value;
            var f_assign = assignmentId;

            if (!f_assign) {
                alert('Please select an Assignment first.');
                return;
            }

            var gradingCont = document.getElementById('grading_container');
            if (gradingCont) {
                gradingCont.style.display = 'block';
                gradingCont.scrollIntoView({behavior: 'smooth', block: 'start'});
            }

            var tbody = document.getElementById('grading_tbody');
            tbody.innerHTML = '';

            var subs = _allSubmissions[f_assign] || {};
            var total = 0;
            var submitted = 0;

            _allStudents.forEach(function(stu) {
                var stuDept = stu.department || (stu.dept ? stu.dept.split(' - ')[0] : '');
                var m_dept = (stuDept && (stuDept.indexOf(f_dept) !== -1 || f_dept.indexOf(stuDept) !== -1));
                var m_year = (stu.year === f_year);
                var m_sem  = (stu.semester === f_sem);
                var m_div  = (stu.division === f_div);

                if (m_dept && m_year && m_sem && m_div) {
                    total++;
                    var sub = subs[stu.id];
                    var hasSub = !!sub;
                    if (hasSub) submitted++;

                    var tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #e2e8f0';
                    
                    var statusHtml = hasSub 
                        ? '<span style="background:#dcfce7; color:#15803d; padding:0.25rem 0.65rem; border-radius:20px; font-weight:700; font-size:0.75rem;"><i class="fa-solid fa-check"></i> ' + (sub.status || 'Submitted') + '</span>'
                        : '<span style="background:#fee2e2; color:#b91c1c; padding:0.25rem 0.65rem; border-radius:20px; font-weight:700; font-size:0.75rem;"><i class="fa-solid fa-xmark"></i> Pending</span>';
                    
                    var filePath = hasSub ? (sub.file_path || sub.file) : '';
                    var fileExt = filePath ? filePath.split('.').pop() : 'pdf';
                    var fileHtml = hasSub
                        ? `<div style="display:flex; gap:0.5rem; flex-direction:column;">
                               <div style="display:flex; gap:0.5rem; align-items:center;">
                                   <a href="uploads/${filePath}" target="_blank" style="display:inline-flex; align-items:center; gap:0.35rem; color:#0284c7; font-weight:600; text-decoration:none; font-size:0.85rem;" download><i class="fa-solid fa-download"></i> Download</a>
                                   <button type="button" onclick="toggleFacultyPreview('uploads/${filePath}','${fileExt}','mgmt_prev_${sub.id}')" style="background:#f1f5f9; color:#475569; border:1px solid #cbd5e1; padding:0.2rem 0.5rem; font-size:0.75rem; border-radius:4px; cursor:pointer; font-weight:600; display:inline-flex; align-items:center; gap:4px;"><i class="fa-solid fa-eye"></i> Preview</button>
                               </div>
                               <div id="mgmt_prev_${sub.id}" style="display:none; border:1px solid #cbd5e1; border-radius:6px; padding:0.25rem; background:#fafafa; margin-top:0.5rem; width:250px;"></div>
                           </div>`
                        : '<span style="color:#94a3b8; font-size:0.85rem;">No File</span>';
                    
                    var marksHtml = hasSub ? (sub.marks || '') : '-';
                    var subId = hasSub ? sub.id : '';
                    
                    var actionHtml = '';
                    if (hasSub) {
                        actionHtml = `
                            <form method="POST" action="faculty_dashboard.php" style="margin:0; display:flex; align-items:center; gap:0.5rem; justify-content:center;">
                                <input type="hidden" name="action" value="grade_assignment">
                                <input type="hidden" name="assignment_id" value="${subId}">
                                <input type="text" name="marks" value="${sub.marks || ''}" placeholder="Marks" required style="width:70px; padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px; text-align:center; font-size:0.85rem;">
                                <select name="status" style="padding:0.4rem; border:1px solid #cbd5e1; border-radius:4px; font-size:0.85rem; width:100px;">
                                    <option value="Graded" ${sub.status === 'Graded' ? 'selected' : ''}>Graded</option>
                                    <option value="Returned for Resubmission" ${sub.status === 'Returned for Resubmission' ? 'selected' : ''}>Resubmit</option>
                                </select>
                                <button type="submit" style="padding:0.4rem 0.75rem; background:#3b82f6; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size:0.8rem;">Save</button>
                            </form>
                        `;
                    } else {
                        actionHtml = '<span style="color:#94a3b8; font-size:0.85rem;">-</span>';
                    }

                    tr.innerHTML = `
                        <td style="padding:0.85rem 1.25rem; font-size:0.9rem; color:#475569; font-family:monospace;">${stu.id}</td>
                        <td style="padding:0.85rem 1.25rem; font-size:0.9rem; font-weight:600; color:#1e293b;">${stu.name}</td>
                        <td style="padding:0.85rem 1.25rem; text-align:center;">${statusHtml}</td>
                        <td style="padding:0.85rem 1.25rem;">${fileHtml}</td>
                        <td style="padding:0.85rem 1.25rem; font-weight:700;">${marksHtml}</td>
                        <td style="padding:0.85rem 1.25rem; text-align:center;">${actionHtml}</td>
                    `;
                    tbody.appendChild(tr);
                }
            });

            if (total === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="padding:2rem; text-align:center; color:#94a3b8;">No students match the selected filters.</td></tr>';
            }
            
            var pending = total - submitted;
            document.getElementById('grading_stats').innerText = 'Total: ' + total + ' | Submitted: ' + submitted + ' | Pending: ' + pending;
            document.getElementById('grading_container').style.display = 'block';
        }

        function prefillUploadForm(subject, unit) {
            var uploadBtn = null;
            document.querySelectorAll('.assign-subtab-btn').forEach(function(b){
                if ((b.getAttribute('onclick')||'').indexOf("'upload'") !== -1) uploadBtn = b;
            });
            if (uploadBtn) switchAssignSubTab('upload', uploadBtn);
            setTimeout(function(){
                var subSel = document.getElementById('upload_subject');
                if (subSel) { for (var o of subSel.options) { if (o.value === subject){ o.selected=true; break; } } }
                var unitSel = document.getElementById('upload_unit');
                if (unitSel) { for (var o of unitSel.options) { if (o.value == unit){ o.selected=true; break; } } }
                var form = document.getElementById('publishAssignmentForm');
                if (form) form.scrollIntoView({behavior:'smooth', block:'start'});
            }, 80);
        }
        function updateCascadingFilters() {
            var f_dept = document.getElementById('filter_dept').value;
            var f_year = document.getElementById('filter_year').value;
            var f_sem = document.getElementById('filter_sem').value;
            var f_div = document.getElementById('filter_div').value;
            var f_subj = document.getElementById('filter_subject').value;
            
            var cardsCont = document.getElementById('unit_cards_container');
            var gradingCont = document.getElementById('grading_container');

            // Hide grading initially
            if (gradingCont) gradingCont.style.display = 'none';

            if (f_dept && f_year && f_sem && f_div && f_subj) {
                cardsCont.style.display = 'grid';
                cardsCont.innerHTML = '';
                
                var units = [1, 2, 3, 4, 5, 6];
                
                units.forEach(function(u) {
                    var filteredAssigns = _myAssignments.filter(function(a) {
                        var matchDept = (!f_dept || a.department.indexOf(f_dept) !== -1 || f_dept.indexOf(a.department) !== -1);
                        var matchDiv  = (!f_div || a.division === f_div);
                        var matchSubj = (a.subject_name.trim().toLowerCase() === f_subj.trim().toLowerCase());
                        return matchDept && matchDiv && matchSubj && a.unit == u;
                    });
                    
                    var html = '<div style="background:white; border:1px solid var(--border-color); border-radius:12px; padding:1.25rem; box-shadow:var(--box-shadow-subtle);">';
                    html += '<h4 style="margin:0 0 1rem 0; font-size:1.1rem; color:#4f46e5; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><i class="fa-solid fa-layer-group" style="margin-right:0.4rem;"></i>Unit ' + u + '</h4>';
                    
                    if (filteredAssigns.length > 0) {
                        html += '<ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.6rem;">';
                        filteredAssigns.forEach(function(a) {
                            var escapedTitle = a.assignment_title.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            html += '<li><button type="button" onclick="loadAssignmentStudents(' + a.id + ')" style="width:100%; text-align:left; background:#f8fafc; border:1px solid #e2e8f0; padding:0.75rem; border-radius:8px; cursor:pointer; color:#334155; font-weight:600; font-family:inherit; font-size:0.95rem; transition:all 0.2s;" onmouseover="this.style.background=\'#eff6ff\'; this.style.borderColor=\'#bfdbfe\'; this.style.color=\'#1e40af\';" onmouseout="this.style.background=\'#f8fafc\'; this.style.borderColor=\'#e2e8f0\'; this.style.color=\'#334155\';"><i class="fa-solid fa-file-alt" style="color:#64748b; margin-right:0.5rem;"></i>' + escapedTitle + '</button></li>';
                        });
                        html += '</ul>';
                    } else {
                        html += '<div style="font-size:0.85rem; color:#94a3b8; text-align:center; padding:1rem 0;">No assignments</div>';
                    }
                    
                    html += '</div>';
                    cardsCont.innerHTML += html;
                });
                
            } else {
                cardsCont.style.display = 'none';
                cardsCont.innerHTML = '';
            }
        }
    </script>

    <!-- Assignment Grievance Details Modal -->
    <div id="assignGrievanceModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1050; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s ease;">
        <div class="modal-content" style="background: #fff; width: 100%; max-width: 600px; border-radius: 16px; padding: 30px; transform: translateY(20px); transition: transform 0.3s ease; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0;">
                <h3 style="font-size: 1.25rem; font-weight: 700; color: #1e293b; margin: 0;">Assignment Grievance Details</h3>
                <button type="button" onclick="closeAssignGrievanceModal()" style="background: none; border: none; font-size: 1.25rem; color: #64748b; cursor: pointer; transition: color 0.2s;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            
            <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                <div style="flex: 1;">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Student Name & ID</div>
                    <div id="modal-ag-student" style="font-size: 0.95rem; color: #1e293b; font-weight: 600;"></div>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Issue Type</div>
                    <div id="modal-ag-category" style="font-size: 0.95rem; color: #b91c1c; font-weight: 600;"></div>
                </div>
            </div>
            
            <div style="margin-bottom: 20px;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Description</div>
                <div id="modal-ag-desc" style="font-size: 0.95rem; color: #475569; line-height: 1.5; background: #f8fafc; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; white-space: pre-wrap;"></div>
            </div>
            
            <div id="modal-ag-screenshot-container" style="margin-bottom: 25px; display: none;">
                <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Screenshot</div>
                <a id="modal-ag-screenshot" href="#" target="_blank" style="color: #3b82f6; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa-regular fa-image"></i> View Attached Screenshot
                </a>
            </div>

            <!-- Response Form -->
            <form method="POST" action="faculty_dashboard.php" enctype="multipart/form-data" style="margin: 0; display: flex; flex-direction: column; gap: 1rem;">
                <input type="hidden" name="action" value="respond_assignment_grievance">
                <input type="hidden" name="grievance_id" id="modal-ag-id" value="">
                
                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Faculty Response</div>
                    <textarea name="reply" id="modal-ag-reply" rows="3" placeholder="Write response to student..." style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-family: inherit; resize: vertical;" required></textarea>
                </div>

                <div>
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Status</div>
                    <select name="status" id="modal-ag-status" style="width: 100%; padding: 0.6rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-family: inherit; background: white;">
                        <option value="Pending">Pending</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Resolved">Resolved</option>
                    </select>
                </div>
                
                <div style="border-top: 1px dashed #e2e8f0; padding-top: 1rem;">
                    <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #94a3b8; margin-bottom: 4px;">Optional: Replace Question File</div>
                    <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem; line-height: 1.3;">If the issue was a corrupted or incorrect file, upload a new one here. This will replace the original assignment file.</p>
                    <input type="hidden" name="subject_assignment_id" id="modal-ag-sa-id" value="">
                    <input type="file" name="new_question_pdf" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif" style="font-size: 0.85rem; width: 100%; border: 1px solid #cbd5e1; padding: 0.4rem; border-radius: 6px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 0.5rem;">
                    <button type="button" onclick="closeAssignGrievanceModal()" style="background: #e2e8f0; color: #475569; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">Cancel</button>
                    <button type="submit" style="background: #10b981; color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.2s;">Save Response</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssignGrievanceModal(data) {
            document.getElementById('modal-ag-id').value = data.id;
            document.getElementById('modal-ag-sa-id').value = data.sa_id;
            
            document.getElementById('modal-ag-student').textContent = data.student_name + ' (PRN: ' + data.student_id + ')';
            document.getElementById('modal-ag-category').textContent = data.issue_type;
            document.getElementById('modal-ag-desc').textContent = data.description;
            
            if (data.screenshot) {
                document.getElementById('modal-ag-screenshot-container').style.display = 'block';
                document.getElementById('modal-ag-screenshot').href = 'uploads/' + data.screenshot;
            } else {
                document.getElementById('modal-ag-screenshot-container').style.display = 'none';
            }
            
            document.getElementById('modal-ag-reply').value = data.reply || '';
            
            const statusSelect = document.getElementById('modal-ag-status');
            let matched = false;
            for (let i = 0; i < statusSelect.options.length; i++) {
                if (statusSelect.options[i].value === data.status) {
                    statusSelect.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched && data.status === 'In Review') {
                statusSelect.value = 'In Progress'; // Map legacy status
            }

            const modal = document.getElementById('assignGrievanceModal');
            modal.style.display = 'flex';
            setTimeout(() => { modal.style.opacity = '1'; modal.querySelector('.modal-content').style.transform = 'translateY(0)'; }, 10);
        }

        function closeAssignGrievanceModal() {
            const modal = document.getElementById('assignGrievanceModal');
            modal.style.opacity = '0';
            modal.querySelector('.modal-content').style.transform = 'translateY(20px)';
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        }
    </script>
</body>
</html>
