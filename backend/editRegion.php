<?php
/**
 * Legacy redirect stub for editRegion.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageRegions.php directly.
 * @see /backend/manageRegions.php
 */

@trigger_error(
  'editRegion.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageRegions.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (isset($queryParams['region_select']) && !isset($queryParams['region_id'])) {
  $queryParams['region_id'] = $queryParams['region_select'];
  unset($queryParams['region_select']);
}
$queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
header("Location: /backend/manageRegions.php" . $queryString, true, 301);
exit;
