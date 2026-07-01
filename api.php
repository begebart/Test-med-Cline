<?php
require_once 'config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['current_user'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit;
}

$currentUser = $_SESSION['current_user'];
updateActivity($currentUser);

$action = $_GET['action'] ?? '';

if ($action === 'status') {
    // Get active users
    $activeUsers = getActiveUsers();
    
    // Get control status (file-based)
    $control = getControlHolder();
    
    echo json_encode([
        'activeUsers' => $activeUsers,
        'control' => $control,
        'currentUser' => $currentUser
    ]);
} elseif ($action === 'take_control') {
    if (!getControlHolder()) {
        setControl($currentUser);
        logActivity($currentUser, 'Took control');
        echo json_encode(['success' => true, 'message' => 'Control taken']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Control already taken by ' . getControlHolder()]);
    }
} elseif ($action === 'leave_control') {
    if (getControlHolder() === $currentUser) {
        logActivity($currentUser, 'Left control');
        releaseControl();
        echo json_encode(['success' => true, 'message' => 'Control released']);
    } else {
        echo json_encode(['success' => false, 'message' => 'You do not have control']);
    }
} elseif ($action === 'save_paper') {
    if (getControlHolder() === $currentUser) {
        $content = file_get_contents('php://input');
        file_put_contents(__DIR__ . '/paper_content.txt', $content);
        setControl($currentUser); // Reset timeout
        logActivity($currentUser, 'Saved paper');
        echo json_encode(['success' => true, 'message' => 'Paper saved']);
    } else {
        echo json_encode(['success' => false, 'message' => 'You do not have control']);
    }
} else {
    echo json_encode(['error' => 'Invalid action']);
}
?>