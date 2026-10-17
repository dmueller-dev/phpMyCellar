<?php
/**
 * Legacy redirect stub for addOrder.php.
 *
 * @deprecated 1.1.0 Scheduled for removal in version 2.0.0. Use /backend/manageOrders.php?action=create directly.
 * @see /backend/manageOrders.php
 */

@trigger_error(
  'addOrder.php is deprecated since version 1.1.0 and will be removed in version 2.0.0. Access /backend/manageOrders.php directly.',
  E_USER_DEPRECATED
);

$queryParams = $_GET;
if (!isset($queryParams['action']) && !isset($queryParams['edit'])) {
  $queryParams['action'] = 'create';
}
$queryString = '?' . http_build_query($queryParams);
header("Location: /backend/manageOrders.php" . $queryString, true, 301);
exit;
