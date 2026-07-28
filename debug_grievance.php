<?php
// TEMPORARY DEBUG FILE - DELETE AFTER FIXING
session_start();
require_once 'db.php';

header('Content-Type: application/json');

$db = get_db();

// Dump structure to trace the problem
$out = [];

// 1. subject_assignments - show created_by field
$out['subject_assignments'] = array_map(function($sa) {
    return [
        'id' => $sa['id'],
        'subject_name' => $sa['subject_name'],
        'assignment_title' => $sa['assignment_title'],
        'created_by' => $sa['created_by'] ?? 'MISSING'
    ];
}, array_slice($db['subject_assignments'] ?? [], 0, 5));

// 2. faculty list - show id, username, name
$out['faculty'] = array_map(function($f) {
    return [
        'id' => $f['id'],
        'username' => $f['username'],
        'name' => $f['name'],
        'role' => $f['role'] ?? 'NOT IN ARRAY'
    ];
}, $db['faculty'] ?? []);

// 3. departments
$out['departments'] = $db['departments'] ?? [];

// 4. students (first 3)
$out['students_sample'] = array_map(function($s) {
    return ['id' => $s['id'], 'name' => $s['name'], 'department' => $s['department'] ?? 'MISSING'];
}, array_slice($db['students'] ?? [], 0, 3));

// 5. existing assignment_grievances
$out['assignment_grievances'] = $db['assignment_grievances'] ?? [];

// 6. Test faculty_id resolution for first subject_assignment
if (!empty($db['subject_assignments'])) {
    $sa = $db['subject_assignments'][0];
    $faculty_name_search = trim($sa['created_by'] ?? '');
    $resolved_faculty_id = 'NOT FOUND';
    foreach ($db['faculty'] as $f) {
        if (strcasecmp(trim($f['name']), $faculty_name_search) === 0) {
            $resolved_faculty_id = $f['username'];
            break;
        }
    }
    $out['faculty_resolution_test'] = [
        'sa_id' => $sa['id'],
        'created_by_field' => $faculty_name_search,
        'resolved_faculty_id' => $resolved_faculty_id
    ];
}

// 7. Test department resolution (session user if logged in)
if (isset($_SESSION['user'])) {
    $out['session_user'] = $_SESSION['user'];
    $out['session_role'] = $_SESSION['role'] ?? '';
}

echo json_encode($out, JSON_PRETTY_PRINT);
