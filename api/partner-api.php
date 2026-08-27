<?php

/**
 * AlertaraQC Partner API — share this URL with partners.
 *
 *   https://surveillance.alertaraqc.com/api/partner-api.php
 *
 * Opens the full integration catalog (pretty JSON).
 */

$_GET['pretty'] = '1';
require __DIR__ . '/integration.php';
