<?php

require_once __DIR__ . '/neighborhood-watcher-member-auth.php';

$nwSidebarMemberName = htmlspecialchars(getNwMemberName());
$nwSidebarMemberId = htmlspecialchars(getNwMemberCode());
?>
        <div class="sidebar-header">
            <div class="logo-container">
                <a href="neighborhood-watcher-dashboard.php">
                    <img src="images/tara.png" alt="Alertara Logo">
                </a>
                <div class="user-name-display" id="sidebarMemberName"><?php echo $nwSidebarMemberName; ?></div>
                <div class="user-id-display" id="sidebarMemberId"><?php echo $nwSidebarMemberId !== '' ? $nwSidebarMemberId : ''; ?></div>
            </div>
        </div>
