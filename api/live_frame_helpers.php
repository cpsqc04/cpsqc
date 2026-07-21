<?php
/**
 * Resolve the newest live camera frame written by detect.py.
 */
function newestLiveFramePath(): ?string
{
    $dir = dirname(__DIR__);
    $newest = null;
    $newestMtime = 0;

    foreach (['current_frame.jpg', 'current_frame_alt.jpg'] as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path)) {
            continue;
        }
        clearstatcache(true, $path);
        $mtime = filemtime($path);
        if ($mtime !== false && $mtime >= $newestMtime) {
            $newestMtime = $mtime;
            $newest = $path;
        }
    }

    return $newest;
}
