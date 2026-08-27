<?php
// Backward-compatible redirect after rename to patrol-login.php
header('Location: patrol-login.php' . (empty($_SERVER['QUERY_STRING']) ? '' : ('?' . $_SERVER['QUERY_STRING'])));
exit;
