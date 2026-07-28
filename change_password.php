<?php
session_start();
if (!isset($_SESSION['temp_zprn'])) {
    header("Location: login.php?role=student");
    exit;
}

$zprn = $_SESSION['temp_zprn'];
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        require_once 'config.php';
        try {
            // Get student info
            $stmt = $pdo->prepare("SELECT * FROM students WHERE zprn = ?");
            $stmt->execute([$zprn]);
            $student = $stmt->fetch();

            if ($student) {
                $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE students SET password = ?, must_change_password = 0 WHERE zprn = ?");
                $update->execute([$hashed, $zprn]);

                // Auto login after password change
                $_SESSION['user'] = [
                    'id' => $student['zprn'],
                    'username' => $student['zprn'],
                    'name' => $student['student_name'],
                    'dept' => $student['department'] . ' - Div ' . $student['division'],
                    'avatar' => $student['avatar']
                ];
                $_SESSION['role'] = 'student';
                unset($_SESSION['temp_zprn']);

                header("Location: student_dashboard.php");
                exit;
            } else {
                $error_message = 'Student record not found.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Default Password - College ERP Portal</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="theme-student">
    <div class="login-container" style="justify-content: center; align-items: center; min-height: 100vh;">
        <div class="login-card glass-container" style="width: 100%; max-width: 450px; margin: 2rem auto;">
            <div class="login-card-header">
                <div class="avatar-box">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h3>Change Default Password</h3>
                <p>For security reasons, you must change your default password on first login.</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="error-message" style="display: flex;">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="change_password.php">
                <div class="form-group">
                    <label for="new_password">
                        <i class="fa-solid fa-lock"></i> 
                        <span>New Password</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required minlength="6" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fa-solid fa-shield-halved"></i> 
                        <span>Confirm Password</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required minlength="6">
                    </div>
                </div>

                <button type="submit" class="btn-login" style="margin-top: 1.5rem;">
                    <span>Update Password & Login</span>
                    <i class="fa-solid fa-right-to-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
