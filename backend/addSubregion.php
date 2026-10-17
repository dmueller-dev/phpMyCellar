<?php
/**
 * Legacy redirect stub for addSubregion.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageSubregions.php directly.
 * @see /backend/manageSubregions.php
 */

@trigger_error(
  'addSubregion.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageSubregions.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageSubregions.php" . $queryString, true, 301);
exit;
