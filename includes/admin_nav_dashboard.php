<?php
/**
 * Dashboard + Bulletin Board top-level admin nav links.
 */
$adminCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');
$dashboardActive = $adminCurrentPage === 'index.php';
$bulletinActive = $adminCurrentPage === 'bulletin-board.php';
$activeStyle = 'background: rgba(76, 138, 137, 0.25); border-left: 3px solid #4c8a89;';
?>
            <a href="index.php" class="nav-module-header" data-tooltip="Dashboard" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo $dashboardActive ? $activeStyle : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-home"></i></span>
                <span class="nav-module-header-text">Dashboard</span>
            </a>
            <a href="bulletin-board.php" class="nav-module-header" data-tooltip="Bulletin Board" style="text-decoration: none; display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.5rem; color: rgba(255, 255, 255, 0.9); cursor: pointer; transition: background-color 0.2s ease; font-weight: 500; user-select: none; gap: 0.75rem; <?php echo $bulletinActive ? $activeStyle : ''; ?>">
                <span class="nav-module-icon"><i class="fas fa-bullhorn"></i></span>
                <span class="nav-module-header-text">Bulletin Board</span>
            </a>
