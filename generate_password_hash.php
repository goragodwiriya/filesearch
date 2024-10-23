<?php
/**
 * Password Hash Generator
 * Use this script to generate hashed passwords for your users.
 *
 * **Usage:**
 * - Access this script via a web browser.
 * - Enter the desired password and submit the form.
 * - Copy the generated hash and use it in the `$users` array in login.php.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');

    if (empty($password)) {
        $error = 'Please enter a password.';
    } else {
        // Generate the hashed password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $success = "Hashed Password: ".$hashedPassword;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Password Hash</title>
    <link rel="stylesheet" href="assets/styles.css">
    <style>
        /* Additional styles specific to the password hash generator page */
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f1f5f9;
        }
        .hash-container {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .hash-container h2 {
            margin-bottom: 1.5rem;
            text-align: center;
            color: #1e293b;
        }
        .hash-container form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .hash-container input[type="password"] {
            padding: 0.75rem 1rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.375rem;
            font-size: 1rem;
        }
        .hash-container button {
            padding: 0.75rem 1rem;
            background-color: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 0.375rem;
            cursor: pointer;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .hash-container button:hover {
            background-color: #1d4ed8;
        }
        .error {
            color: #dc2626;
            text-align: center;
        }
        .success {
            color: #10b981;
            text-align: center;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="hash-container">
        <h2>Password Hash Generator</h2>
        <?php if (isset($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif;?>
        <?php if (isset($success)): ?>
            <p class="success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif;?>
        <form method="POST" action="generate_password_hash.php">
            <input type="password" name="password" placeholder="Enter Password to Hash" required autocomplete="new-password">
            <button type="submit">Generate Hash</button>
        </form>
    </div>
</body>
</html>
