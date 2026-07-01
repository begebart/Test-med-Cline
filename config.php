<?php
// Configuration file
session_start();

// Define allowed users (up to 10)
$allowed_users = [
    'Os',
    'Bt',
    'Pr',
    'Be',
    'PLL',
    'PL',
    'Lr',
    'Jmn',
    'Xx',
    'Yy'
];

// Session timeout for inactivity (10 minutes)
define('INACTIVITY_TIMEOUT', 600);

// Log file directory
define('LOG_DIR', __DIR__ . '/logs');

// Ensure log directory exists
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Helper function to log activity
function logActivity($username, $action) {
    $logFile = LOG_DIR . '/activity_' . date('Y-m-d') . '.txt';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $username - $action" . PHP_EOL;
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// File-based storage for cross-session data
define('ACTIVE_USERS_FILE', LOG_DIR . '/active_users.txt');
define('CONTROL_FILE', LOG_DIR . '/control.txt');

// Helper function to get all active users (across all browsers)
function getActiveUsers() {
    global $allowed_users;
    $activeUsers = [];
    
    if (!file_exists(ACTIVE_USERS_FILE)) {
        return $activeUsers;
    }
    
    $content = file_get_contents(ACTIVE_USERS_FILE);
    $lines = explode("\n", trim($content));
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        $parts = explode('|', $line);
        if (count($parts) >= 2) {
            $username = trim($parts[0]);
            $lastActivity = (int)trim($parts[1]);
            
            // Check if user is in allowed list and still active
            if (in_array($username, $allowed_users) && 
                (time() - $lastActivity) < INACTIVITY_TIMEOUT) {
                
                $activeUsers[] = [
                    'name' => $username,
                    'last_activity' => $lastActivity,
                    'has_control' => getControlHolder() === $username
                ];
            }
        }
    }
    
    return $activeUsers;
}

// Helper function to update user activity (file-based for cross-browser visibility)
function updateActivity($username) {
    $users = [];
    
    // Read existing users
    if (file_exists(ACTIVE_USERS_FILE)) {
        $content = file_get_contents(ACTIVE_USERS_FILE);
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $parts = explode('|', $line);
            if (count($parts) >= 2) {
                $users[trim($parts[0])] = (int)trim($parts[1]);
            }
        }
    }
    
    // Update or add current user
    $users[$username] = time();
    
    // Clean up old entries (older than timeout)
    $cleanUsers = [];
    foreach ($users as $user => $timestamp) {
        if ((time() - $timestamp) < INACTIVITY_TIMEOUT) {
            $cleanUsers[$user] = $timestamp;
        }
    }
    
    // Write back to file
    $content = '';
    foreach ($cleanUsers as $user => $timestamp) {
        $content .= "$user|$timestamp\n";
    }
    
    file_put_contents(ACTIVE_USERS_FILE, $content, LOCK_EX);
}

// Helper function to get control holder (across all browsers)
function getControlHolder() {
    if (!file_exists(CONTROL_FILE)) {
        return null;
    }
    
    $content = trim(file_get_contents(CONTROL_FILE));
    if (empty($content)) {
        return null;
    }
    
    $parts = explode('|', $content);
    if (count($parts) >= 2) {
        $username = trim($parts[0]);
        $controlTime = (int)trim($parts[1]);
        
        // Check if control has timed out
        if ((time() - $controlTime) > INACTIVITY_TIMEOUT) {
            releaseControl();
            return null;
        }
        
        return $username;
    }
    
    return null;
}

// Helper function to set control holder
function setControl($username) {
    file_put_contents(CONTROL_FILE, "$username|" . time(), LOCK_EX);
}

// Helper function to release control
function releaseControl() {
    if (file_exists(CONTROL_FILE)) {
        file_put_contents(CONTROL_FILE, '', LOCK_EX);
    }
}

// Helper function to check if control should be released
function checkControlTimeout() {
    if (file_exists(CONTROL_FILE)) {
        $content = trim(file_get_contents(CONTROL_FILE));
        if (!empty($content)) {
            $parts = explode('|', $content);
            if (count($parts) >= 2) {
                $controlTime = (int)trim($parts[1]);
                if ((time() - $controlTime) > INACTIVITY_TIMEOUT) {
                    $username = trim($parts[0]);
                    logActivity($username, 'Control released (timeout)');
                    releaseControl();
                    return true;
                }
            }
        }
    }
    return false;
}

// Check control timeout on every request
checkControlTimeout();
?>