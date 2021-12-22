<?php

namespace Drupal\tide_authenticated_content\Auth;

use Drupal\jwt\Authentication\Event\JwtAuthEvents;
use Drupal\jwt\Authentication\Event\JwtAuthValidateEvent;
use Drupal\jwt\Authentication\Event\JwtAuthValidEvent;
use Drupal\jwt\Authentication\Provider\JwtAuth;
use Drupal\jwt\Transcoder\JwtDecodeException;
use Symfony\Component\HttpFoundation\Request;

/**
 * JWT Authentication Provider.
 */
class AuthProvider extends JwtAuth {

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
  public static function getJwtFromRequest(Request $request) {
    $auth_header = $request->headers->get('X-Authorization');
    $matches = [];
    if (!preg_match('/^Bearer (.*)/', $auth_header, $matches)) {
      return FALSE;
    }

    return $matches[1];
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $raw_jwt = self::getJwtFromRequest($request);

    // Decode JWT and validate signature.
    try {
      $jwt = $this->transcoder->decode($raw_jwt);
    } catch (JwtDecodeException $e) {
      return NULL;
    }

    $validate = new JwtAuthValidateEvent($jwt);
    // Signature is validated, but allow modules to do additional validation.
    $this->eventDispatcher->dispatch(JwtAuthEvents::VALIDATE, $validate);
    if (!$validate->isValid()) {
      return NULL;
    }

    $valid = new JwtAuthValidEvent($jwt);
    $this->eventDispatcher->dispatch(JwtAuthEvents::VALID, $valid);
    $user = $valid->getUser();

    if (!$user) {
      return NULL;
    }

    return $user;
  }

}
