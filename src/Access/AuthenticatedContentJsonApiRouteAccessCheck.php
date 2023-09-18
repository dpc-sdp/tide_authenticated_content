<?php

namespace Drupal\tide_authenticated_content\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks access for displaying configuration translation page.
 */
class AuthenticatedContentJsonApiRouteAccessCheck implements AccessInterface {

  /**
   * Drupal core Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityManager;

  /**
   * Drupal core Current Path Stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  private $pathStack;

  /**
   * Drupal core config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;

  /**
   * Drupal core Request Stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * AuthenticatedContentController constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager
   *   Drupal core Entity Type Manager.
   * @param \Drupal\Core\Path\CurrentPathStack $pathStack
   *   Drupal core Current Path Stack.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   Drupal core config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   Drupal core Request Stack.
   */
  public function __construct(EntityTypeManagerInterface $entityManager, CurrentPathStack $pathStack, ConfigFactory $configFactory, RequestStack $requestStack) {
    $this->entityManager = $entityManager;
    $this->pathStack = $pathStack;
    $this->configFactory = $configFactory;
    $this->requestStack = $requestStack;
  }

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
    $tide_config = $this->configFactory->get('tide_authenticated_content.module');
    $jsonapiUserRoutes = $tide_config->get("jsonapi_user_route");
    $currentPath = $this->pathStack->getPath();
    $currentQuery = $this->requestStack->getCurrentRequest()->query;
    $protectRoute = FALSE;
    // Loop through any user based routes that need to be protected.
    foreach ($jsonapiUserRoutes as $jsonapiUserRoute) {
      if (strpos($currentPath, $jsonapiUserRoute) === 0) {
        $protectRoute = TRUE;
      }
    }
    if ($protectRoute) {
      // Check if the user is requesting access via a filter.
      if ($filter = $currentQuery->get('filter')) {
        if (isset($filter["anon"]["condition"]["value"])) {
          $uid = $filter["anon"]["condition"]["value"];
          $user = $this->entityManager->getStorage('user')->load($uid);
        }
      }
      // Check if this is an exact user request via JSONAPI.
      $uuid = substr($currentPath, strrpos($currentPath, '/') + 1);
      if (strlen($uuid) == 36) {
        $user = $this->entityManager->getStorage('user')->loadByProperties(['uuid' => $uuid]);
        if (is_array($user)) {
          $user = reset($user);
        }
      }
      if (!empty($user) && $user->id() != 0) {
        // If the current user is requesting their own account, allow it.
        if ($user->id() !== 0 && $user->id() == $account->id()) {
          return AccessResult::allowed();
        }
        return AccessResult::forbidden();
      }
      // If this is the generic user collection route, block it.
      if (!$account->hasPermission('administer users')) {
        return AccessResult::forbidden();
      }
    }
    return AccessResult::allowed();
  }

}
