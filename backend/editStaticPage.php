<?php
/**
 * Legacy redirect stub for editStaticPage.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageStaticPages.php directly.
 * @see /backend/manageStaticPages.php
 */

@trigger_error(
  'editStaticPage.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageStaticPages.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageStaticPages.php" . $queryString, true, 301);
exit;
