<?php

if (!function_exists('getNeighborhoodWatchNavPage')) {
    function getNeighborhoodWatchNavPage(): string
    {
        if (defined('NW_PAGE_MODE')) {
            return NW_PAGE_MODE;
        }

        $base = basename($_SERVER['PHP_SELF'] ?? '');
        if ($base === 'review-neighborhood-watcher-incidents.php') {
            return 'incidents';
        }
        if ($base === 'neighborhood-watch-member-list.php') {
            return 'members';
        }
        if ($base === 'neighborhood-watch-application.php') {
            return 'applications';
        }

        return '';
    }
}

$nwNavPage = getNeighborhoodWatchNavPage();

?>
                    <a href="neighborhood-watch-application.php" class="nav-submodule <?php echo $nwNavPage === 'applications' ? 'active' : ''; ?>" data-tooltip="Neighborhood Watch Application">
                        <span class="nav-submodule-icon"><i class="fas fa-clipboard-list"></i></span>
                        <span class="nav-submodule-text">Neighborhood Watch Application</span>
                    </a>
                    <a href="neighborhood-watch-member-list.php" class="nav-submodule <?php echo $nwNavPage === 'members' ? 'active' : ''; ?>" data-tooltip="Member List">
                        <span class="nav-submodule-icon"><i class="fas fa-user-check"></i></span>
                        <span class="nav-submodule-text">Member List</span>
                    </a>
                    <a href="review-neighborhood-watcher-incidents.php" class="nav-submodule <?php echo $nwNavPage === 'incidents' ? 'active' : ''; ?>" data-tooltip="Member Incident Reports">
                        <span class="nav-submodule-icon"><i class="fas fa-exclamation-circle"></i></span>
                        <span class="nav-submodule-text">Member Incident Reports</span>
                    </a>
