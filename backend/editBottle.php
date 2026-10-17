<?php
/**
 * Legacy redirect stub for editBottle.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageBottles.php?mode=edit directly.
 * @see /backend/manageBottles.php
 */

@trigger_error(
  'editBottle.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageBottles.php?mode=edit directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (!isset($queryParams['mode'])) {
  $queryParams['mode'] = 'edit';
}
$queryString = '?' . http_build_query($queryParams);
header("Location: /backend/manageBottles.php" . $queryString, true, 301);
exit;
