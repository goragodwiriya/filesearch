<?php
/**
 * Secure File Manager Frontend
 * Features:
 * - Real-time Search with Content Matching
 * - Secure File Viewing and Deletion
 * - CSRF Protection with Multi-Token Support
 * - Session Management
 * - Responsive UI with Modern Design
 */

session_start();
require_once 'functions.php';

// Authentication check
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Generate initial CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = generateCsrfToken();
}

// Get current timestamp for cache busting
$timestamp = time();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure File Search</title>
    <link rel="stylesheet" href="assets/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Secure File Search</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_id']); ?></span>
                <a href="logout.php" class="logout-btn" title="Logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <form class="search-form" id="searchForm">
            <input type="hidden" name="csrf_token" id="csrfToken"
                   value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" id="requestTimestamp" value="<?php echo $timestamp; ?>">

            <div class="search-group">
                <input type="text"
                       class="search-input"
                       id="searchInput"
                       placeholder="Search files (min. 2 characters)..."
                       required
                       minlength="2"
                       autocomplete="off">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Search
                </button>
                <button type="button" class="btn btn-danger" id="cancelSearch" style="display: none;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>

        <div id="resultCount" class="result-count"></div>
        <div id="searchResults" class="search-results"></div>

        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Searching files...</p>
        </div>
    </div>

    <!-- File Content Modal -->
    <div id="fileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <button class="close" id="closeModal">&times;</button>
            </div>
            <div id="modalContent" class="file-content"></div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        let searchController = null;
        let resultCounter = 0;

        /**
         * Refresh CSRF token
         * @returns {Promise<string|null>} New token or null if failed
         */
        async function refreshCsrfToken() {
            try {
                const response = await fetch('get_csrf_token.php');
                const data = await response.json();
                if (data.csrf_token) {
                    document.getElementById('csrfToken').value = data.csrf_token;
                    return data.csrf_token;
                }
            } catch (error) {
                console.error('Error refreshing CSRF token:', error);
            }
            return null;
        }

        /**
         * Show toast notification
         * @param {string} message Message to display
         * @param {string} type Success or error
         */
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            toast.style.display = 'block';

            setTimeout(() => {
                toast.classList.remove('show');
                toast.style.display = 'none';
            }, 3000);
        }

        /**
         * View file content
         * @param {string} filePath Path to file
         */
        async function viewFile(filePath) {
            const csrfToken = await refreshCsrfToken();
            if (!csrfToken) {
                showToast('Security validation failed', 'error');
                return;
            }

            try {
                const response = await fetch(
                    `file_manager_ajax.php?ajax=search&operation=view&file=${encodeURIComponent(filePath)}`,
                    {
                        headers: {
                            'X-CSRF-Token': csrfToken
                        }
                    }
                );

                const data = await response.json();
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }

                document.getElementById('modalTitle').textContent = data.name;
                document.getElementById('modalContent').innerHTML = data.content;
                document.getElementById('fileModal').style.display = 'block';
            } catch (error) {
                showToast('Error viewing file', 'error');
                console.error('View error:', error);
            }
        }

        /**
         * Delete file
         * @param {string} filePath Path to file
         */
        async function deleteFile(filePath) {
            if (!confirm('Are you sure you want to delete this file?')) {
                return;
            }

            const csrfToken = await refreshCsrfToken();
            if (!csrfToken) {
                showToast('Security validation failed', 'error');
                return;
            }

            try {
                const response = await fetch(
                    `file_manager_ajax.php?ajax=search&operation=delete&file=${encodeURIComponent(filePath)}`,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': csrfToken
                        }
                    }
                );

                const data = await response.json();
                if (data.error) {
                    showToast(data.error, 'error');
                    return;
                }

                showToast(data.message);
                const fileElement = document.querySelector(`[data-file="${filePath}"]`);
                if (fileElement) {
                    fileElement.style.animation = 'slideOut 0.3s ease-out';
                    setTimeout(() => {
                        fileElement.remove();
                        resultCounter--;
                        updateResultCount();
                    }, 300);
                }
            } catch (error) {
                showToast('Error deleting file', 'error');
                console.error('Delete error:', error);
            }
        }

        /**
         * Get appropriate icon for file type
         * @param {string} filename Filename
         * @returns {string} Icon class
         */
        function getFileIcon(filename) {
            const extension = filename.split('.').pop().toLowerCase();
            const iconMap = {
                'php': 'php',
                'html': 'html5',
                'css': 'css3-alt',
                'js': 'js',
                'json': 'file-code',
                'txt': 'file-alt',
                'md': 'markdown',
                'pdf': 'file-pdf',
                'doc': 'file-word',
                'docx': 'file-word',
                'xls': 'file-excel',
                'xlsx': 'file-excel',
                'zip': 'file-archive',
                'rar': 'file-archive'
            };
            return iconMap[extension] || 'file';
        }

        /**
         * Format file permissions
         * @param {string} permissions File permissions
         * @returns {string} Formatted permissions
         */
        function formatPermissions(permissions) {
            const readable = (permissions & 4) ? 'R' : '-';
            const writable = (permissions & 2) ? 'W' : '-';
            const executable = (permissions & 1) ? 'X' : '-';
            return readable + writable + executable;
        }

        /**
         * Update result count display
         */
        function updateResultCount() {
            const resultCount = document.getElementById('resultCount');
            if (resultCounter === 0) {
                resultCount.textContent = 'No results found';
            } else {
                resultCount.textContent = `Found ${resultCounter} result${resultCounter !== 1 ? 's' : ''}`;
            }
        }

        // Search form handler
        document.getElementById('searchForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (searchController) {
                searchController.abort();
            }

            const searchInput = document.getElementById('searchInput');
            const searchTerm = searchInput.value.trim();
            const cancelBtn = document.getElementById('cancelSearch');

            if (searchTerm.length < 2) {
                showToast('Search term must be at least 2 characters', 'error');
                return;
            }

            const csrfToken = await refreshCsrfToken();
            if (!csrfToken) {
                showToast('Security validation failed', 'error');
                return;
            }

            const searchResults = document.getElementById('searchResults');
            const loading = document.getElementById('loading');

            searchResults.innerHTML = '';
            loading.style.display = 'flex';
            cancelBtn.style.display = 'block';
            resultCounter = 0;
            updateResultCount();

            try {
                searchController = new AbortController();

                const response = await fetch(
                    `file_manager_ajax.php?ajax=search&search=${encodeURIComponent(searchTerm)}`,
                    {
                        signal: searchController.signal,
                        headers: {
                            'X-CSRF-Token': csrfToken
                        }
                    }
                );

                const reader = response.body.getReader();
                const decoder = new TextDecoder();

                while (true) {
                    const {value, done} = await reader.read();
                    if (done) break;

                    const items = decoder.decode(value).trim().split('\n');
                    for (const item of items) {
                        if (!item) continue;

                        const data = JSON.parse(item);
                        if (data.type === 'error') {
                            showToast(data.message, 'error');
                            continue;
                        }

                        if (data.type === 'status') continue;

                        resultCounter++;
                        updateResultCount();

                        const fileInfo = data.data;
                        const fileElement = document.createElement('div');
                        fileElement.className = 'file-item';
                        fileElement.dataset.file = fileInfo.path;

                        fileElement.innerHTML = `
                            <div class="file-header">
                                <div class="file-info">
                                    <i class="fas fa-${getFileIcon(fileInfo.name)}"></i>
                                    <strong>${fileInfo.name}</strong>
                                </div>
                                <div class="file-actions">
                                    <button class="btn btn-primary" onclick="viewFile('${fileInfo.path.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn btn-danger" onclick="deleteFile('${fileInfo.path.replace(/'/g, "\\'")}')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            <div class="file-path" title="${fileInfo.path}">
                                <i class="fas fa-folder"></i> ${fileInfo.path}
                            </div>
                            <div class="file-meta">
                                <span title="File size">
                                    <i class="fas fa-weight-hanging"></i> ${fileInfo.size}
                                </span>
                                <span title="Last modified">
                                    <i class="fas fa-clock"></i> ${fileInfo.modified}
                                </span>
                                <span title="File permissions">
                                    <i class="fas fa-shield-alt"></i> ${formatPermissions(fileInfo.permissions)}
                                </span>
                            </div>
                        `;
                        searchResults.appendChild(fileElement);
                    }
                }

                if (resultCounter === 0) {
                    showToast('No files found', 'error');
                }
            } catch (error) {
                if (error.name === 'AbortError') {
                    showToast('Search cancelled');
                } else {
                    showToast('Error performing search', 'error');
                    console.error('Search error:', error);
                  }
            } finally {
                loading.style.display = 'none';
                cancelBtn.style.display = 'none';
                searchController = null;
            }
        });

        // Cancel search button handler
        document.getElementById('cancelSearch').addEventListener('click', () => {
            if (searchController) {
                searchController.abort();
                showToast('Search cancelled');
            }
        });

        // Modal close handlers
        document.getElementById('closeModal').addEventListener('click', () => {
            document.getElementById('fileModal').style.display = 'none';
        });

        window.addEventListener('click', (event) => {
            const modal = document.getElementById('fileModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Keyboard handlers
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.getElementById('fileModal').style.display = 'none';
            }
        });

        // Error handling for network issues
        window.addEventListener('online', () => {
            showToast('Connection restored', 'success');
        });

        window.addEventListener('offline', () => {
            showToast('Connection lost', 'error');
        });

        // Session timeout handling
        let sessionTimeout;
        function resetSessionTimeout() {
            clearTimeout(sessionTimeout);
            sessionTimeout = setTimeout(() => {
                showToast('Session expired. Please login again.', 'error');
                window.location.href = 'logout.php';
            }, 3600000); // 1 hour
        }

        // Reset timeout on user activity
        ['click', 'mousemove', 'keypress'].forEach(event => {
            document.addEventListener(event, resetSessionTimeout);
        });
        resetSessionTimeout();

        // Handle browser back/forward
        window.addEventListener('popstate', (event) => {
            if (document.getElementById('fileModal').style.display === 'block') {
                document.getElementById('fileModal').style.display = 'none';
                event.preventDefault();
            }
        });

        // Additional styles for animations and responsiveness
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(-100%);
                }
            }

            .highlight {
                background-color: yellow;
                padding: 0.2em;
                border-radius: 0.2em;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
