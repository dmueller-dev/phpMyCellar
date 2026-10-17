<?php
/**
 * Legacy redirect stub for editVineyard.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageVineyards.php directly.
 * @see /backend/manageVineyards.php
 */

@trigger_error(
  'editVineyard.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageVineyards.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageVineyards.php" . $queryString, true, 301);
exit;
