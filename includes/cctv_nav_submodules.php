<?php
/**
 * Shared CCTV Surveillance sidebar links.
 * Set $cctvNavActive to: open-surveillance | playback | camera-management | cctv-request
 */
$cctvNavActive = $cctvNavActive ?? '';
?>
<a href="open-surveillance.php" class="nav-submodule<?php echo $cctvNavActive === 'open-surveillance' ? ' active' : ''; ?>" data-tooltip="Open Surveillance">
    <span class="nav-submodule-icon"><i class="fas fa-tv"></i></span>
    <span class="nav-submodule-text">Open Surveillance</span>
</a>
<a href="playback.php" class="nav-submodule<?php echo $cctvNavActive === 'playback' ? ' active' : ''; ?>" data-tooltip="Playback">
    <span class="nav-submodule-icon"><i class="fas fa-play-circle"></i></span>
    <span class="nav-submodule-text">Playback</span>
</a>
<a href="camera-management.php" class="nav-submodule<?php echo $cctvNavActive === 'camera-management' ? ' active' : ''; ?>" data-tooltip="Camera Management">
    <span class="nav-submodule-icon"><i class="fas fa-cog"></i></span>
    <span class="nav-submodule-text">Camera Management</span>
</a>
<a href="cctv-request.php" class="nav-submodule<?php echo $cctvNavActive === 'cctv-request' ? ' active' : ''; ?>" data-tooltip="CCTV Request">
    <span class="nav-submodule-icon"><i class="fas fa-file-video"></i></span>
    <span class="nav-submodule-text">CCTV Request</span>
</a>
