<?php
/**
 * Legacy redirect stub for individual wines.
 *
 * @deprecated 1.0.0 Scheduled for removal in version 2.0.0. Use /wines.php directly.
 * @see /wines.php
 */

@trigger_error(
  'wine.php is deprecated since version 1.0.0 and will be removed in version 2.0.0. Access /wines.php directly.',
  E_USER_DEPRECATED
);

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /wines.php" . $queryString, true, 301);
exit;
