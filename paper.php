<?php
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['current_user'])) {
    header('Location: index.php');
    exit;
}

$currentUser = $_SESSION['current_user'];
updateActivity($currentUser);

// Handle control requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'take_control') {
        // Check if control is available
        if (!getControlHolder()) {
            setControl($currentUser);
            logActivity($currentUser, 'Took control');
        }
    } elseif ($action === 'leave_control') {
        if (getControlHolder() === $currentUser) {
            logActivity($currentUser, 'Left control');
            releaseControl();
        }
    } elseif ($action === 'save_paper') {
        if (getControlHolder() === $currentUser) {
            $content = $_POST['content'] ?? '';
            file_put_contents(__DIR__ . '/paper_content.txt', $content);
            // Reset control timeout by updating the control file
            setControl($currentUser);
            logActivity($currentUser, 'Saved paper');
        }
    }
}

// Get active users
$activeUsers = getActiveUsers();

// Check if current user has control
$hasControl = getControlHolder() === $currentUser;

// Load paper content
$paperContent = '';
if (file_exists(__DIR__ . '/paper_content.txt')) {
    $paperContent = file_get_contents(__DIR__ . '/paper_content.txt');
}

// Get log files
$logFiles = [];
if (file_exists(LOG_DIR)) {
    $files = scandir(LOG_DIR);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'txt') {
            $logFiles[] = $file;
        }
    }
    rsort($logFiles); // Most recent first
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Samarbejdspapir</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 0.9em;
            transition: background 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .panel h2 {
            font-size: 1.1em;
            color: #333;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .users-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .user-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #ccc;
        }

        .user-item.control {
            border-left-color: #4caf50;
            background: #f1f8f4;
        }

        .user-item.control .user-name {
            color: #4caf50;
            font-weight: 700;
        }

        .user-item.current-user {
            border-left-color: #667eea;
            background: #f0f2ff;
        }

        .user-item.current-user.control {
            border-left-color: #4caf50;
            background: linear-gradient(135deg, #f0f2ff 0%, #f1f8f4 100%);
        }

        .user-item.current-user.control .user-name {
            color: #2e7d32;
        }

        .user-name {
            font-weight: 600;
            color: #333;
            font-size: 0.95em;
        }

        .user-status {
            font-size: 0.8em;
            color: #666;
        }

        .control-badge {
            background: #4caf50;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 600;
        }

        .control-panel {
            text-align: center;
        }

        .control-status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }

        .control-status.available {
            background: #e3f2fd;
            color: #1976d2;
        }

        .control-status.taken {
            background: #fff3e0;
            color: #f57c00;
        }

        .control-status.you-have {
            background: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #4caf50;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.95em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .paper-container {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            min-height: 600px;
            display: flex;
            flex-direction: column;
        }

        .paper-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .paper-header h2 {
            color: #333;
            font-size: 1.3em;
        }

        .save-status {
            font-size: 0.85em;
            color: #666;
        }

        .paper-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        textarea {
            flex: 1;
            width: 100%;
            min-height: 500px;
            padding: 20px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-family: 'Courier New', monospace;
            line-height: 1.6;
            resize: vertical;
            transition: border-color 0.2s;
        }

        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .log-section {
            margin-top: 20px;
        }

        .log-files {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .log-file {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            transition: background 0.2s;
        }

        .log-file:hover {
            background: #e9ecef;
        }

        .log-file-name {
            font-size: 0.9em;
            font-family: 'Courier New', monospace;
        }

        .log-file-size {
            font-size: 0.8em;
            color: #666;
        }

        .notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            z-index: 999;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(100px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .notification.success {
            background: #4caf50;
        }

        .notification.info {
            background: #2196f3;
        }

        @media (max-width: 968px) {
            .main-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                order: 2;
            }

            .paper-container {
                order: 1;
            }
        }

        @media (max-width: 600px) {
            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .user-info {
                justify-content: space-between;
            }

            .paper-container {
                padding: 20px;
            }

            textarea {
                min-height: 400px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>📝 Samarbejdspapir</h1>
            <div class="user-info">
                <span class="user-badge">👤 <?php echo htmlspecialchars($currentUser); ?></span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>

    <div class="main-container">
        <div class="sidebar">
            <div class="panel">
                <h2>👥 Active brugere (<?php echo count($activeUsers); ?>/10)</h2>
                <div class="users-list" id="usersList">
                    <?php foreach ($activeUsers as $user): ?>
                        <div class="user-item <?php echo $user['has_control'] ? 'control' : ''; ?> <?php echo $user['name'] === $currentUser ? 'current-user' : ''; ?>">
                            <div>
                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div class="user-status">Active</div>
                            </div>
                            <?php if ($user['has_control']): ?>
                                <span class="control-badge">✏️ Skriverr</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($activeUsers)): ?>
                        <p style="color: #999; text-align: center; padding: 20px;">Ingen aktive brugere</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel control-panel">
                <h2>🎮 Token</h2>
                <div class="control-status <?php echo $hasControl ? 'you-have' : (getControlHolder() ? 'taken' : 'available'); ?>">
                    <?php if ($hasControl): ?>
                        ✓ Du har Token
                    <?php elseif (getControlHolder()): ?>
                        ✗ <?php echo htmlspecialchars(getControlHolder()); ?> har Token
                    <?php else: ?>
                        ○ Token fri
                    <?php endif; ?>
                </div>
                
                <?php if ($hasControl): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="leave_control">
                        <button type="submit" class="btn btn-danger">Slip Token</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="take_control">
                        <button type="submit" class="btn btn-success" <?php echo getControlHolder() ? 'disabled' : ''; ?> id="takeTokenBtn">
                            <?php echo getControlHolder() ? 'Token taget' : 'Tag token'; ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="panel log-section">
                <h2>📋 Log Files</h2>
                <div class="log-files">
                    <?php foreach ($logFiles as $file): ?>
                        <a href="logs/<?php echo urlencode($file); ?>" class="log-file" target="_blank">
                            <span class="log-file-name">📄 <?php echo htmlspecialchars($file); ?></span>
                            <span class="log-file-size"><?php echo number_format(filesize(LOG_DIR . '/' . $file)); ?> B</span>
                        </a>
                    <?php endforeach; ?>
                    <?php if (empty($logFiles)): ?>
                        <p style="color: #999; text-align: center; padding: 10px;">Endnu ingen log filer</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="paper-container">
            <div class="paper-header">
                <h2>📄 Papiret</h2>
                <span class="save-status" id="saveStatus"></span>
            </div>
            <div class="paper-content">
                <form method="POST" action="" id="paperForm">
                    <input type="hidden" name="action" value="save_paper" id="saveAction">
                    <textarea 
                        id="paperContent" 
                        name="content" 
                        placeholder="Start writing on the paper..."
                        <?php echo $hasControl ? '' : 'disabled'; ?>
                    ><?php echo htmlspecialchars($paperContent); ?></textarea>
                </form>
            </div>
        </div>
    </div>

    <div id="notification"></div>

    <script>
        let hasControl = <?php echo $hasControl ? 'true' : 'false'; ?>;
        let currentUser = '<?php echo htmlspecialchars($currentUser); ?>';
        let saveTimeout = null;

        // Auto-save functionality
        const paperContent = document.getElementById('paperContent');
        const saveStatus = document.getElementById('saveStatus');

        paperContent.addEventListener('input', function() {
            if (!hasControl) return;
            
            saveStatus.textContent = 'Typing...';
            
            // Debounce save
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                savePaper();
            }, 1000);
        });

        function savePaper() {
            if (!hasControl) return;
            
            const form = document.getElementById('paperForm');
            const formData = new FormData(form);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                saveStatus.textContent = '✓ Saved ' + new Date().toLocaleTimeString();
                showNotification('Paper saved', 'success');
            })
            .catch(error => {
                saveStatus.textContent = '✗ Save failed';
                console.error('Error:', error);
            });
        }

        // Poll for updates every 3 seconds
        function pollUpdates() {
            fetch('api.php?action=status')
                .then(response => response.json())
                .then(data => {
                    updateUsersList(data.activeUsers);
                    updateControlStatus(data.control);
                })
                .catch(error => console.error('Error:', error));
        }

        function updateUsersList(users) {
            const usersList = document.getElementById('usersList');
            usersList.innerHTML = users.map(user => `
                <div class="user-item ${user.has_control ? 'control' : ''} ${user.name === currentUser ? 'current-user' : ''}">
                    <div>
                        <div class="user-name">${escapeHtml(user.name)}</div>
                        <div class="user-status">Active</div>
                    </div>
                    ${user.has_control ? '<span class="control-badge">✏️ Writing</span>' : ''}
                </div>
            `).join('');
        }

        function updateControlStatus(control) {
            const controlStatus = document.querySelector('.control-status');
            const buttons = document.querySelectorAll('.control-panel button');
            
            if (control === currentUser) {
                hasControl = true;
                controlStatus.className = 'control-status you-have';
                controlStatus.textContent = '✓ Du har Token';
                paperContent.disabled = false;
                
                // Update buttons
                const takeTokenBtn = document.getElementById('takeTokenBtn');
                if (takeTokenBtn) {
                    takeTokenBtn.disabled = true;
                    takeTokenBtn.textContent = 'Token taget';
                }
            } else if (control) {
                hasControl = false;
                controlStatus.className = 'control-status taken';
                controlStatus.textContent = '✗ ' + escapeHtml(control) + ' har Token';
                paperContent.disabled = true;
                
                // Update buttons
                const takeTokenBtn2 = document.getElementById('takeTokenBtn');
                if (takeTokenBtn2) {
                    takeTokenBtn2.disabled = true;
                    takeTokenBtn2.textContent = 'Token taget';
                }
            } else {
                hasControl = false;
                controlStatus.className = 'control-status available';
                controlStatus.textContent = '○ Token fri';
                paperContent.disabled = true;
                
                // Update buttons
                const takeTokenBtn3 = document.getElementById('takeTokenBtn');
                if (takeTokenBtn3) {
                    takeTokenBtn3.disabled = false;
                    takeTokenBtn3.textContent = 'Tag token';
                }
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.className = 'notification ' + type;
            
            setTimeout(() => {
                notification.className = 'notification';
            }, 3000);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Start polling
        setInterval(pollUpdates, 3000);

        // Initial poll
        pollUpdates();
    </script>
</body>
</html>