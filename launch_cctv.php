<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get the project directory
$project_dir = __DIR__;
$bat_file = $project_dir . '\\start_cctv_app.bat';
$python_file = $project_dir . '\\cctv.py';

// Check if files exist
if (!file_exists($python_file)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'CCTV app not found']);
    exit;
}

// Determine which method to use
$use_bat = file_exists($bat_file);
$command = '';

if ($use_bat) {
    // Use batch file
    $command = 'start "" "' . $bat_file . '"';
} else {
    // Use Python directly
    $command = 'start "" py -3.13 "' . $python_file . '"';
}

// Execute command (Windows)
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    // Hide window and run in background
    $command = 'start /B ' . $command;
    
    // Execute
    $output = [];
    $return_var = 0;
    exec($command, $output, $return_var);
    
    // Small delay to ensure process starts
    usleep(500000); // 0.5 seconds
    
    header('Content-Type: application/json');
    if ($return_var === 0 || $return_var === 1) {
        echo json_encode([
            'success' => true, 
            'message' => 'CCTV application launched successfully',
            'method' => $use_bat ? 'batch_file' : 'python_direct'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Failed to launch application',
            'error_code' => $return_var
        ]);
    }
} else {
    // Non-Windows system
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Auto-launch only supported on Windows']);
}
?>









