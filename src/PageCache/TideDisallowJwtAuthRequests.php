<?php

namespace Drupal\tide_authenticated_content\PageCache;

use Drupal\jwt\PageCache\DisallowJwtAuthRequests;
use Symfony\Component\HttpFoundation\Request;

/**
 * Extend JWT Disallow to use 'X-Authorization' header.
 *
 * This policy disallows caching of requests that use jwt_auth for security
 * reasons. Otherwise responses for authenticated requests can get into the
 * page cache and could be delivered to unprivileged users.
 */
class TideDisallowJwtAuthRequests extends DisallowJwtAuthRequests {

  /**
   * {@inheritdoc}
   */
  public function check(Request $request) {
    $auth = $request->headers->get('X-Authorization');
    if (preg_match('/^Bearer .+/', $auth)) {
      return self::DENY;
    }

    return NULL;
  }

}
