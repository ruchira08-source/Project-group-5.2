<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_item') {
    $type = $_POST['type'] ?? '';
    $id = intval($_POST['id'] ?? 0);
    $db = get_db();
    
    $allowed_types = ['leaves', 'grievances', 'notices', 'assignments', 'subject_assignments'];
    
    if (in_array($type, $allowed_types) && $id > 0) {
        $found = false;
        foreach ($db[$type] as $key => $item) {
            if ($item['id'] === $id) {
                unset($db[$type][$key]);
                // Re-index array
                $db[$type] = array_values($db[$type]);
                $found = true;
                break;
            }
        }
        
        if ($found) {
            save_db($db);
            $_SESSION['success_message'] = ucfirst(rtrim($type, 's')) . " deleted successfully.";
        } else {
            $_SESSION['error_message'] = "Item not found.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid delete request.";
    }
    
    // Redirect back to the referrer
    $referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
    header("Location: " . $referer);
    exit;
}
