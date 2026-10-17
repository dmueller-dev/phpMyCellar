<?php
/**
 * Legacy redirect stub for addCountry.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageCountries.php directly.
 * @see /backend/manageCountries.php
 */

@trigger_error(
  'addCountry.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageCountries.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageCountries.php" . $queryString, true, 301);
exit;
