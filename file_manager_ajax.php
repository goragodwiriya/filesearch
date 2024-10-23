<?php
/**
 * AJAX Handler for Secure File Search
 * Features:
 * - Real-time File Search with Content Matching
 * - Secure File Viewing and Deletion
 * - CSRF Protection with Multi-Token Support
 * - Rate Limiting and Access Control
 * - Memory Efficient File Processing
 */

session_start();
require_once 'functions.php';

// Basic Configuration
define('ROOT_DIR', __DIR__); // Change this to your desired directory
define('MAX_FILESIZE', 10 * 1024 * 1024); // 10MB max file size
define('BATCH_SIZE', 20); // Results per batch
define('RATE_LIMIT', 100); // Max requests per user
define('RATE_LIMIT_WINDOW', 3600); // Time window in seconds
define('EXCLUDED_DIRS', ['node_modules', '.git', 'vendor', '.svn']);
define('EXCLUDED_FILES', ['.htaccess', '.env', 'php.ini']);
define('ALLOWED_EXTENSIONS', [
    'txt', 'php', 'html', 'htm', 'css', 'js', 'json', 'xml', 'md', 'log', 'csv',
    'yml', 'yaml', 'ini', 'conf', 'config', 'sh', 'bash', 'env', 'example'
]);

// Security Headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

/**
 * Validate file path and permissions
 * @param string $path File path to validate
 * @return string Sanitized absolute path
 * @throws Exception if path is invalid
 */
function validatePath($path)
{
    // Remove null bytes
    $path = str_replace(chr(0), '', $path);

    // Normalize directory separators
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    // Resolve real path
    $realPath = realpath($path);
    if ($realPath === false) {
        throw new Exception('Invalid file path');
    }

    // Verify path is within root directory
    if (strpos($realPath, realpath(ROOT_DIR).DIRECTORY_SEPARATOR) !== 0) {
        throw new Exception('Access denied: Path outside root directory');
    }

    // Verify file extension
    $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        throw new Exception('File type not allowed');
    }

    return $realPath;
}

/**
 * Perform file search with content matching
 * @param string $searchText Text to search for
 */
function searchFiles($searchText)
{
    try {
        $searchText = trim($searchText);
        if (strlen($searchText) < 2) {
            throw new Exception('Search term too short');
        }

        $iterator = new RecursiveDirectoryIterator(ROOT_DIR, RecursiveDirectoryIterator::SKIP_DOTS);
        $filter = new RecursiveCallbackFilterIterator($iterator, function ($current, $key, $iterator) {
            $name = $current->getFilename();

            // Skip excluded directories
            if ($iterator->hasChildren() && in_array($name, EXCLUDED_DIRS)) {
                return false;
            }

            // Skip excluded files
            if ($current->isFile() && in_array($name, EXCLUDED_FILES)) {
                return false;
            }

            // Check file extension
            if ($current->isFile()) {
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                return in_array($extension, ALLOWED_EXTENSIONS);
            }

            return true;
        });

        $recursiveIterator = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);
        $results = [];
        $batchCount = 0;

        foreach ($recursiveIterator as $file) {
            if (connection_status() !== CONNECTION_NORMAL) {
                break;
            }

            if ($file->isFile() && $file->isReadable()) {
                if ($file->getSize() > MAX_FILESIZE) {
                    continue;
                }

                if (fileContainsText($file->getPathname(), $searchText)) {
                    $fileInfo = [
                        'path' => str_replace('\\', '/', $file->getPathname()),
                        'name' => basename($file->getPathname()),
                        'size' => formatFileSize($file->getSize()),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'permissions' => substr(sprintf('%o', fileperms($file->getPathname())), -4)
                    ];

                    $results[] = ['type' => 'file', 'data' => $fileInfo];
                    $batchCount++;

                    if ($batchCount >= BATCH_SIZE) {
                        foreach ($results as $result) {
                            echo json_encode($result)."\n";
                            flush();
                        }
                        $results = [];
                        $batchCount = 0;
                        usleep(10000); // 10ms delay
                    }
                }
            }
        }

        // Send remaining results
        if (!empty($results)) {
            foreach ($results as $result) {
                echo json_encode($result)."\n";
                flush();
            }
        }

        echo json_encode([
            'type' => 'status',
            'message' => 'Search completed'
        ])."\n";

    } catch (Exception $e) {
        echo json_encode([
            'type' => 'error',
            'message' => $e->getMessage()
        ])."\n";
    }
}

/**
 * Check if file contains search text
 * @param string $filePath Path to file
 * @param string $searchText Text to search for
 * @return bool True if text is found
 */
function fileContainsText($filePath, $searchText)
{
    $handle = fopen($filePath, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            if (stripos($line, $searchText) !== false) {
                fclose($handle);
                return true;
            }
        }
        fclose($handle);
    }
    return false;
}

/**
 * Format file size for display
 * @param int $bytes File size in bytes
 * @return string Formatted size
 */
function formatFileSize($bytes)
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2).' '.$units[$pow];
}

/**
 * View file contents
 * @param string $filePath Path to file
 * @return array File details and content
 */
function viewFile($filePath)
{
    try {
        $filePath = validatePath($filePath);

        if (!is_readable($filePath)) {
            throw new Exception('Cannot read file: Permission denied');
        }

        $fileSize = filesize($filePath);
        if ($fileSize > MAX_FILESIZE) {
            throw new Exception('File is too large to view');
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception('Error reading file content');
        }

        return [
            'name' => basename($filePath),
            'path' => str_replace('\\', '/', $filePath),
            'content' => htmlspecialchars($content, ENT_QUOTES, 'UTF-8'),
            'size' => formatFileSize($fileSize),
            'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4)
        ];

    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

/**
 * Delete file with backup
 * @param string $filePath Path to file
 * @return array Success or error message
 */
function deleteFile($filePath)
{
    try {
        $filePath = validatePath($filePath);

        if (!is_writable($filePath)) {
            throw new Exception('Cannot delete file: Permission denied');
        }

        // Create backup
        $backupDir = __DIR__.'/backup/';
        if (!file_exists($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                throw new Exception('Failed to create backup directory');
            }
        }

        $backupPath = $backupDir.basename($filePath).'.'.microtime(true).'.bak';
        if (!copy($filePath, $backupPath)) {
            throw new Exception('Failed to create backup');
        }

        if (!unlink($filePath)) {
            throw new Exception('Failed to delete file');
        }

        logSecurityEvent('delete_file', 'success', "Deleted: {$filePath}");
        return ['message' => 'File successfully deleted'];

    } catch (Exception $e) {
        logSecurityEvent('delete_file', 'error', $e->getMessage());
        return ['error' => $e->getMessage()];
    }
}

// Handle AJAX requests
try {
    // Validate authentication
    if (empty($_SESSION['user_id'])) {
        throw new Exception('Authentication required');
    }

    // Validate CSRF token
    validateCsrfToken();

    // Handle specific operations
    if (isset($_GET['operation']) && isset($_GET['file'])) {
        header('Content-Type: application/json');
        $file = filter_input(INPUT_GET, 'file', FILTER_SANITIZE_STRING);

        if (empty($file)) {
            throw new Exception('Invalid file parameter');
        }

        switch ($_GET['operation']) {
            case 'view':
                echo json_encode(viewFile($file));
                break;

            case 'delete':
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    throw new Exception('Invalid request method for delete operation');
                }
                echo json_encode(deleteFile($file));
                break;

            default:
                throw new Exception('Invalid operation');
        }
    }
    // Handle search operation
    elseif (isset($_GET['search'])) {
        header('Content-Type: application/json');
        $searchTerm = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

        if (empty($searchTerm)) {
            throw new Exception('Invalid search term');
        }

        searchFiles($searchTerm);
    } else {
        throw new Exception('Invalid request');
    }

} catch (Exception $e) {
    logSecurityEvent('ajax_request', 'error', $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
