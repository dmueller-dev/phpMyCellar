<?php
/**
 * Legacy redirect stub for addRegion.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageRegions.php directly.
 * @see /backend/manageRegions.php
 */

@trigger_error(
  'addRegion.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageRegions.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageRegions.php" . $queryString, true, 301);
exit;
