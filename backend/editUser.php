<?php
/**
 * Legacy redirect stub for editUser.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageUsers.php?mode=edit directly.
 * @see /backend/manageUsers.php
 */

@trigger_error(
  'editUser.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageUsers.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (!isset($queryParams['mode'])) {
  $queryParams['mode'] = 'edit';
}
$queryString = '?' . http_build_query($queryParams);
header("Location: /backend/manageUsers.php" . $queryString, true, 301);
exit;
