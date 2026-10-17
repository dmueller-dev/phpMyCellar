<?php
/**
 * Legacy redirect stub for addWineMaster.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageWineMasters.php directly.
 * @see /backend/manageWineMasters.php
 */

@trigger_error(
  'addWineMaster.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageWineMasters.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageWineMasters.php" . $queryString, true, 301);
exit;
