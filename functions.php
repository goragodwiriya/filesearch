<?php
/**
 * Security and CSRF Token Management
 * Features:
 * - Multiple token support
 * - Token expiration
 * - Automatic cleanup
 * - Request validation
 */

/**
 * Generate a new CSRF token
 * @return string New CSRF token
 */
function generateCsrfToken()
{
    if (empty($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    // Generate new token
    $newToken = bin2hex(random_bytes(32));

    // Store token with timestamp
    $_SESSION['csrf_tokens'][$newToken] = time();

    // Clean expired tokens (older than 1 hour)
    foreach ($_SESSION['csrf_tokens'] as $token => $time) {
        if (time() - $time > 3600) {
            unset($_SESSION['csrf_tokens'][$token]);
        }
    }

    // Limit number of stored tokens (keep 10 most recent)
    if (count($_SESSION['csrf_tokens']) > 10) {
        array_shift($_SESSION['csrf_tokens']);
    }

    return $newToken;
}

/**
 * Validate CSRF token
 * @param string|null $token Token to validate
 * @return bool True if valid
 * @throws Exception if token is invalid or missing
 */
function validateCsrfToken($token = null)
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return true;
    }

    if ($token === null) {
        $headers = getallheaders();
        $token = $headers['X-CSRF-Token'] ?? '';
    }

    if (empty($token)) {
        throw new Exception('Missing CSRF token');
    }

    if (isset($_SESSION['csrf_tokens'][$token])) {
        $tokenTime = $_SESSION['csrf_tokens'][$token];
        if (time() - $tokenTime <= 3600) {
            return true;
        }
        unset($_SESSION['csrf_tokens'][$token]);
    }

    throw new Exception('Invalid or expired CSRF token');
}

/**
 * Log security events
 * @param string $action Action performed
 * @param string $status Success or failure
 * @param string $details Additional details
 */
function logSecurityEvent($action, $status, $details = '')
{
    $logEntry = sprintf(
        "[%s] User: %s IP: %s Action: %s Status: %s Details: %s\n",
        date('Y-m-d H:i:s'),
        $_SESSION['user_id'] ?? 'anonymous',
        $_SERVER['REMOTE_ADDR'],
        $action,
        $status,
        $details
    );
    file_put_contents(__DIR__.'/logs/security.log', $logEntry, FILE_APPEND);
}
