<?php
/**
 * Neighborhood Watch member portal sidebar navigation.
 * Expects: $nwActiveNav = 'dashboard' | 'account'
 */
if (!isset($nwActiveNav)) {
    $nwActiveNav = '';
}
?>
            <a href="nw-dashboard.php" class="nav-submodule <?php echo $nwActiveNav === 'dashboard' ? 'active' : ''; ?>" data-tooltip="Report Incident">
                <span class="nav-submodule-icon"><i class="fas fa-exclamation-triangle"></i></span>
                <span class="nav-submodule-text">Report Incident</span>
            </a>
            <a href="nw-dashboard.php#reportsSection" class="nav-submodule" data-tooltip="My Reports">
                <span class="nav-submodule-icon"><i class="fas fa-clipboard-list"></i></span>
                <span class="nav-submodule-text">My Reports</span>
            </a>
            <a href="nw-account-settings.php" class="nav-submodule <?php echo $nwActiveNav === 'account' ? 'active' : ''; ?>" data-tooltip="Account Settings">
                <span class="nav-submodule-icon"><i class="fas fa-user-cog"></i></span>
                <span class="nav-submodule-text">Account Settings</span>
            </a>
