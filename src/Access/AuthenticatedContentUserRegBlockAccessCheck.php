<?php

namespace Drupal\tide_authenticated_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Checks access for displaying configuration translation page.
 */
class AuthenticatedContentUserRegBlockAccessCheck implements AccessInterface {

  /**
   * An access check for User Access on the jsonapi user route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    if ($account->hasPermission('administer site configuration')) {
      return AccessResult::allowed();
    }
    return AccessResult::forbidden();
  }

}
