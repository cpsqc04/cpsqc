<?php
// Get frame file modification time and size
header('Content-Type: application/json');

$frameFiles = [
    'current_frame.jpg',
    'current_frame_alt.jpg'
];

$result = [];

foreach ($frameFiles as $file) {
    // API is in api/ folder, frames are in parent directory
    $filePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . $file;
    
    if (file_exists($filePath)) {
        $result[$file] = [
            'exists' => true,
            'lastModified' => filemtime($filePath),
            'size' => filesize($filePath),
            'url' => $file
        ];
    } else {
        $result[$file] = [
            'exists' => false,
            'lastModified' => 0,
            'size' => 0,
            'url' => $file
        ];
    }
}

echo json_encode($result);

