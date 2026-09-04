<?php
/**
 * Legacy redirect stub for individual blog posts.
 *
 * @deprecated 1.0.0 Scheduled for removal in version 2.0.0. Use /blog.php directly.
 * @see /blog.php
 */

@trigger_error(
  'blogpost.php is deprecated since version 1.0.0 and will be removed in version 2.0.0. Access /blog.php directly.',
  E_USER_DEPRECATED
);

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /blog.php" . $queryString, true, 301);
exit;
