<?php
/**
 * Neighborhood Watch member portal sidebar navigation.
 * Expects: $nwActiveNav = 'report' | 'reports' | 'account'
 */
if (!isset($nwActiveNav)) {
    $nwActiveNav = 'report';
}
?>
            <a href="neighborhood-watcher-dashboard.php#reportSection" class="nav-submodule <?php echo $nwActiveNav === 'report' ? 'active' : ''; ?>" data-tooltip="Report Incident" data-section="reportSection">
                <span class="nav-submodule-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <span class="nav-submodule-text">Report Incident</span>
            </a>
            <a href="neighborhood-watcher-dashboard.php#reportsSection" class="nav-submodule <?php echo $nwActiveNav === 'reports' ? 'active' : ''; ?>" data-tooltip="My Reports" data-section="reportsSection">
                <span class="nav-submodule-icon"><i class="fas fa-clipboard-list"></i></span>
                <span class="nav-submodule-text">My Reports</span>
            </a>
            <a href="neighborhood-watcher-account-settings.php" class="nav-submodule <?php echo $nwActiveNav === 'account' ? 'active' : ''; ?>" data-tooltip="Account Settings">
                <span class="nav-submodule-icon"><i class="fas fa-user-cog"></i></span>
                <span class="nav-submodule-text">Account Settings</span>
            </a>
