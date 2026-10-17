<?php
/**
 * Legacy redirect stub for addProducer.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageProducers.php directly.
 * @see /backend/manageProducers.php
 */

@trigger_error(
  'addProducer.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageProducers.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageProducers.php" . $queryString, true, 301);
exit;
