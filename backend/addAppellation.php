<?php
/**
 * Legacy redirect stub for addAppellation.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageAppellations.php directly.
 * @see /backend/manageAppellations.php
 */

@trigger_error(
  'addAppellation.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageAppellations.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageAppellations.php" . $queryString, true, 301);
exit;
