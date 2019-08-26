<?php

namespace Drupal\tide_authenticated_content\Auth;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use Drupal\jwt\Transcoder\JwtTranscoderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * JWT Authentication Provider.
 */
class AuthProvider extends JwtAuth {

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManager $manager, JwtTranscoderInterface $transcoder, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($transcoder, $event_dispatcher);
  }

  /**
   * Override to use alternate headers (X-Authorization)
   *
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    $auth = $request->headers->get('X-Authorization');
    return preg_match('/^Bearer .+/', $auth);
  }

  /**
   * Override to use alternate headers (X-Authorization).
   *
   * Gets a raw JsonWebToken from the current request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string|bool
   *   Raw JWT String if on request, false if not.
   */
  protected function getJwtFromRequest(Request $request) {
    $auth_header = $request->headers->get('X-Authorization');
    $matches = [];
    if (!preg_match('/^Bearer (.*)/', $auth_header, $matches)) {
      return FALSE;
    }

    return $matches[1];
  }

}
