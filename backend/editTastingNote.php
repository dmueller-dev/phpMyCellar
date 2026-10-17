<?php
/**
 * Legacy redirect stub for editTastingNote.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageTastingNotes.php?mode=edit directly.
 * @see /backend/manageTastingNotes.php
 */

@trigger_error(
  'editTastingNote.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageTastingNotes.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (!isset($queryParams['mode'])) {
  $queryParams['mode'] = 'edit';
}
$queryString = '?' . http_build_query($queryParams);
header("Location: /backend/manageTastingNotes.php" . $queryString, true, 301);
exit;
