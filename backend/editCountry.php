<?php
/**
 * Legacy redirect stub for editCountry.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageCountries.php directly.
 * @see /backend/manageCountries.php
 */

@trigger_error(
  'editCountry.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageCountries.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (isset($queryParams['country_select']) && !isset($queryParams['country'])) {
  $queryParams['country'] = $queryParams['country_select'];
  unset($queryParams['country_select']);
}
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageCountries.php" . $queryString, true, 301);
exit;
