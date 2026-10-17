<?php
/**
 * Alias forward stub for manageStories.php -> manageBlogposts.php.
 *
 * @see /backend/manageBlogposts.php
 */

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageBlogposts.php" . $queryString, true, 302);
exit;
