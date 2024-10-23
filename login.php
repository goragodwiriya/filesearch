<?php
/**
 * User Login Page for Secure File Search
 * Features:
 * - User Authentication
 * - Password Verification using password_hash() and password_verify()
 * - Session Management
 */

session_start();

// If the user is already logged in, redirect to the file manager
if (!empty($_SESSION['user_id'])) {
    header('Location: file_manager.php');
    exit;
}

// Define users with hashed passwords (do not store plain-text passwords)
$users = [
    'admin' => '$2y$10$PFA9E.o45g7B74arZ4TkRORkk1y5Oxqb3IqMSGAGurM8JLXK/fKA6' // Password: 1234
    // Add more users as needed
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize input
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (!array_key_exists($username, $users)) {
        $error = 'Invalid username or password.';
    } else {
        $hashedPassword = $users[$username];
        // Verify the password
        if (password_verify($password, $hashedPassword)) {
            // Password is correct; start the session
            $_SESSION['user_id'] = $username;
            // Generate CSRF token
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: file_manager.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Secure File Search</title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
    <div class="login-container">
        <h2>Secure File Search Login</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif;?>
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="Username" required autocomplete="username">
            <input type="password" name="password" placeholder="Password" required autocomplete="current-password">
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>
