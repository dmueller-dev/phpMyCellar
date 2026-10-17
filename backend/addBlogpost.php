<?php
/**
 * Legacy redirect stub for addBlogpost.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageBlogposts.php directly.
 * @see /backend/manageBlogposts.php
 */

@trigger_error(
  'addBlogpost.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageBlogposts.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (!isset($queryParams['mode'])) {
  $queryParams['mode'] = 'add';
}
$queryString = '?' . http_build_query($queryParams);
header("Location: /backend/manageBlogposts.php" . $queryString, true, 301);
exit;
