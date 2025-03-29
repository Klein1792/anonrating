<?php
function handleAuthActions($action) {
    if ($action === 'logout') {
        session_destroy();
        echo json_encode(['status' => 'logged out']);
        return true;
    }
    return false;
}