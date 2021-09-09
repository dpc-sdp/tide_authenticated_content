<?php

namespace Drupal\tide_authenticated_content\Routing;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class AuthenticatedContentRouteSubscriber extends RouteSubscriberBase {

  /**
   * Drupal core config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  private $configFactory;

  /**
   * AuthenticatedContentRouteSubscriber constructor.
   *
   * @param Drupal\Core\Config\ConfigFactory $configFactory
   *   Drupal core config factory.
   */
  public function __construct(ConfigFactory $configFactory) {
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    $tide_config = $this->configFactory->get('tide_authenticated_content.module');
    $protectJsonapiUserRoute = $tide_config->get("protect_jsonapi_user_route");
    if ($route = $collection->get('user.register')) {
      // Block access to the register route from the Back end.
      $blockBeUserRegistration = $tide_config->get("block_be_user_registration");
      if ($blockBeUserRegistration) {
        $route->setRequirement('_custom_access', 'tide_authenticated_content.access_block_checker::access');
      }
    }
    // Block access to the user routes on json api.
    if ($route = $collection->get('jsonapi.user--user.collection')) {
      if ($protectJsonapiUserRoute) {
        $route->setRequirement('_custom_access', 'tide_authenticated_content.access_jsonapi_checker::access');
      }
    }
    if ($route = $collection->get('jsonapi.user--user.individual')) {
      // Block access to the user routes.
      if ($protectJsonapiUserRoute) {
        $route->setRequirement('_custom_access', 'tide_authenticated_content.access_jsonapi_checker::access');
      }
    }
    $route_config = \Drupal::config('tide_authenticated_content.route_settings');
    $route_settings = $route_config->get("route_settings");
    $route_list = [
      'login' => 'tide_authenticated_content.user.login',
      'logout' => 'tide_authenticated_content.user.logout',
      'register' => 'tide_authenticated_content.user.register',
      'update' => 'tide_authenticated_content.user.update',
      'request_reset' => 'tide_authenticated_content.user.request_reset',
      'reset' => 'tide_authenticated_content.user.reset',
    ];
    foreach ($route_list as $key => $route_name) {
      if ($route = $collection->get($route_name)) {
        if (!$route_settings[$key]) {
          $route->setRequirement('_access', 'FALSE');
        }
      }
    }
  }

}
