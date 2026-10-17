<?php
/**
 * Legacy redirect stub for addWine.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageWines.php directly.
 * @see /backend/manageWines.php
 */

@trigger_error(
  'addWine.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageWines.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageWines.php" . $queryString, true, 301);
exit;
