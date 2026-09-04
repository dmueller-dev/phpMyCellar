<?php
/**
 * Legacy redirect stub for individual tasting notes.
 *
 * @deprecated 1.0.0 Scheduled for removal in version 2.0.0. Use /tnotes.php directly.
 * @see /tnotes.php
 */

@trigger_error(
  'tnote.php is deprecated since version 1.0.0 and will be removed in version 2.0.0. Access /tnotes.php directly.',
  E_USER_DEPRECATED
);

$queryString = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /tnotes.php" . $queryString, true, 301);
exit;
