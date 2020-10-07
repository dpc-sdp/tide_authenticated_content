<?php

namespace Drupal\tide_authenticated_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\jwt_auth_issuer\Controller\JwtAuthIssuerController;
use Drupal\profile\Entity\ProfileType;
use Drupal\user\Controller\UserAuthenticationController;
use Drupal\user\Entity\User;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Drupal\profile\Entity\Profile;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Utility\Html;

/**
 * Class AuthenticatedContentController.
 *
 * @package Drupal\tide_authenticated_content\Controller
 */
class AuthenticatedContentController extends ControllerBase {

  /**
   * Container.
   *
   * @var Symfony\Component\DependencyInjection\ContainerInterface
   */
  private $container;

  /**
   * User Storage.
   *
   * @var Drupal\user\UserStorageInterface
   */
  private $userStorage;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * AuthenticatedContentController constructor.
   *
   * @param Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   * @param Drupal\user\UserStorageInterface $user_storage
   *   User storage.
   * @param \Drupal\Core\Config\ModuleHandlerInterface $module_handler
   *   The handle of module objects.
   */
  public function __construct(
    ContainerInterface $container,
    UserStorageInterface $user_storage,
    ModuleHandlerInterface $module_handler
  ) {
    $this->container = $container;
    $this->userStorage = $user_storage;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Create.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container.
   *
   * @return \Drupal\Core\Controller\ControllerBase|\Drupal\tide_authenticated_content\Controller\AuthenticatedContentController
   *   Returns new AuthenticatedContentController.
   */
  public static function create(
    ContainerInterface $container
  ) {
    return new static($container,
      $container->get('entity.manager')
        ->getStorage('user'),
      $container->get('module_handler'));
  }

  /**
   * Login.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   *
   * @throws \Exception
   *   Expection.
   */
  public function loginAction(
    Request $request
  ) {
    // TODO: implement flood control.
    $auth = UserAuthenticationController::create($this->container);
    try {
      $resp = $auth->login($request);
    }
    catch (BadRequestHttpException $exception) {
      if ($exception->getMessage() == "The user has not been activated or is blocked.") {
        throw new BadRequestHttpException("Sorry, unrecognized username or password.",
          $exception);
      }
      else {
        throw $exception;
      }
    }
    if ($resp->getStatusCode() == 200) {
      $body = json_decode($resp->getContent(),
        TRUE);
      $jwt = JwtAuthIssuerController::create($this->container);
      // @var \Symfony\Component\HttpFoundation\Response $tokenResp
      $tokenResp = $jwt->tokenResponse();
      if ($tokenResp->getStatusCode() == 200) {
        $token = json_decode($tokenResp->getContent(),
          TRUE);
        $body['auth_token'] = $token['token'];
        return new JsonResponse($body);
      }
      return $tokenResp;
    }
    return $resp;
  }

  /**
   * Logout.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response.
   *
   * @throws \Exception
   *   Expection.
   */
  public function logoutAction() {
    $auth = UserAuthenticationController::create($this->container);
    $resp = $auth->logout();
    return $resp;
  }

  /**
   * Sanitize the input entered by users via the API.
   *
   * @param string $profileFieldValue
   *   The Profile field value entered by the user.
   *
   * @return string
   *   The actual value updated after escaping HTML.
   */
  private function sanitizeProfiles($profileFieldValue) {
    $profileFieldValue = Html::escape($profileFieldValue);
    return $profileFieldValue;
  }

  /**
   * Register.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function registerAction(
    Request $request
  ) {

    $config = $this->config('user.settings');

    if ($config->get("register") === USER_REGISTER_ADMINISTRATORS_ONLY) {
      if ($this->currentUser === NULL || !$this->currentUser->hasPermission('administer users')) {
        return new JsonResponse(["message" => $this->t('Registration by administrators only')],
          403);
      }
    }

    $user = json_decode($request->getContent(),
      TRUE);

    if (isset($user['roles'])) {
      unset($user['roles']);
    }

    if (empty($user['pass'])) {
      $user['pass'] = bin2hex(random_bytes(20));
    }
    $u = User::create($user);
    $u->setEmail($user['mail']);
    if (!isset($user['name'])) {
      $u->setUsername($user['mail']);
    }
    if (isset($user['pass'])) {
      $u->setPassword($user['pass']);
    }
    $u->enforceIsNew(TRUE);
    if ($config->get("register") === USER_REGISTER_VISITORS || $config->get("register") === USER_REGISTER_ADMINISTRATORS_ONLY) {
      $u->activate();
      $successMessage = $this->t('User account created.');
    }
    else {
      $successMessage = $this->t('User account requested.');
    }

    $this->updateEntityFields($u, $user, 'register');

    if ($u->hasField('field_site')) {
      if ($request->query->get("site")) {
        $u->set("field_site", [["target_id" => $request->query->get("site")]]);
      }
    }

    $violationHints = $this->validateUser($u);
    if (!empty($violationHints)) {
      return new JsonResponse([
        'message' => $this->t('An error occurred and we’re unable to create your account. If you already have an account, please use the reset password feature to update your password.'),
      ],
        400);
    }

    try {
      $u->save();
      // If there are auto roles settings, add and save them now.
      $tide_config = $this->config('tide_authenticated_content.module');
      if ($autoRoles = $tide_config->get("auto_apply_user_roles")) {
        foreach ($autoRoles as $role) {
          $u->addRole($role);
        }
        $u->save();
      }
      // If Profile module is installed, check if a profile needs to be created.
      if ($this->moduleHandler->moduleExists('profile')) {
        if (isset($user['profiles'])) {
          $profile_types = ProfileType::loadMultiple();
          foreach ($user['profiles'] as $profile) {
            if (!isset($profile_types[$profile['type']])) {
              $successMessage .= $this->t('<br>The requested @profile profile does not exist and has not been added to your user account, please contact support.',
                ['@profile' => $profile['type']]);
            }
            else {
              $profile = array_map([$this, 'sanitizeProfiles'], $profile);
              $profile['uid'] = $u->id();
              $p = Profile::create($profile);
              $p->save();
            }
          }
        }
      }
      return new JsonResponse(["message" => $successMessage]);
    }
    catch (EntityStorageException $exception) {
      if (strpos($exception->getMessage(),
          'Duplicate entry') !== FALSE && strpos($exception->getMessage(),
          "for key 'user__name'") !== FALSE) {
        return new JsonResponse(["message" => $this->t('The account name is unavailable.')],
          400);
      }
      return new JsonResponse(['message' => $this->t('Failed to create account')],
        400);
    }
    catch (\Exception $exception) {
      return new JsonResponse(['message' => $exception->getMessage()],
        400);
    }
  }

  /**
   * Update.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function updateAction(
    Request $request
  ) {

    $user = json_decode($request->getContent(), TRUE);

    // Ensure the users is only able to edit their own account.
    if (\Drupal::currentUser()->id() != $user['uid']) {
      return new JsonResponse(["message" => $this->t('Unable to find this user.')],
        400);
    }

    $u = User::load($user['uid']);

    if (isset($user['roles'])) {
      unset($user['roles']);
    }
    // The user must provide auth details to change their email or username.
    if (isset($user['name']) || isset($user['mail'])) {
      $auth = UserAuthenticationController::create($this->container);
      try {
        $auth->login($request);
      }
      catch (BadRequestHttpException $exception) {
        throw new BadRequestHttpException("You must provide valid login details to update your username or password.", $exception);
      }
      $u->setExistingPassword($user['pass']);
    }
    if (isset($user['mail'])) {
      $u->setEmail($user['mail']);
    }
    if (isset($user['name'])) {
      $u->setUsername($user['name']);
    }
    // The password cannot be edited via this method.
    if (isset($user['pass'])) {
      unset($user['pass']);
    }

    $successMessage = $this->t('User account updated.');

    $this->updateEntityFields($u, $user, 'register');

    $violationHints = $this->validateUser($u);
    if (!empty($violationHints)) {
      return new JsonResponse([
        'message' => $this->t('Invalid fields:') . ' ' . implode('<br>', $violationHints),
      ],
        400);
    }

    try {
      $u->save();
      // If Profile module is installed, check if a profile needs to be created.
      if ($this->moduleHandler->moduleExists('profile')) {
        if (isset($user['profiles'])) {
          $profile_types = ProfileType::loadMultiple();
          foreach ($user['profiles'] as $profile) {
            if (!isset($profile_types[$profile['type']])) {
              $successMessage .= $this->t('<br>The requested @profile profile does not exist and has not been added to your user account, please contact support.',
                ['@profile' => $profile['type']]);
            }
            else {
              $profile['uid'] = $u->id();
              if ($p = Profile::load($profile['id'])) {
                $this->updateEntityFields($p, $profile, 'default');
                $p->save($profile);
              }
              else {
                $successMessage .= $this->t('<br>Your profile information could not be found or updated, please contact support.');
              }
            }
          }
        }
      }
      return new JsonResponse(["message" => $successMessage]);
    }
    catch (EntityStorageException $exception) {
      return new JsonResponse(['message' => $this->t('Failed to update account')],
        400);
    }
    catch (\Exception $exception) {
      return new JsonResponse(['message' => $exception->getMessage()],
        400);
    }
  }

  /**
   * Request Password Reset.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   */
  public function requestResetAction(
    Request $request
  ) {
    $message = $this->t('If your account is registered in our system a forgot password email has been sent to your email address.');
    $data = json_decode($request->getContent(),
      TRUE);
    // @var \Drupal\user\Entity\User[] $users
    $users = [];
    if (isset($data['name'])) {
      $name = $data['name'];
      $users = $this->userStorage->loadByProperties(['name' => $name]);
    }
    if (isset($data['mail'])) {
      $mail = $data['mail'];
      $users = $this->userStorage->loadByProperties(['mail' => $mail]);
    }
    foreach ($users as $user) {
      if (!$user->isActive()) {
        return new JsonResponse(['message' => $this->$message]);
      }
      if (_user_mail_notify('password_reset',
          $user) === TRUE) {
        return new JsonResponse(['message' => $this->$message]);
      }
    }
    return new JsonResponse(['message' => $this->$message]);
  }

  /**
   * Reset Password.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Response.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Expection.
   */
  public function resetAction(
    Request $request
  ) {
    try {
      $data = json_decode($request->getContent(),
        TRUE);

      $id = $data['id'];
      $hash = $data['hash'];
      $time = $data['time'];
      $pass = $data['pass'];
      if (empty($pass)) {
        return new JsonResponse(['message' => $this->t('The password field cannot be empty.')],
          400);
      }
      /** @var \Drupal\user\Entity\User $user */
      $user = $this->userStorage->load($id);
      if ($user) {
        if (!$user->isActive()) {
          return new JsonResponse(['message' => $this->t('Reset Request Failed (blocked)')],
            400);
        }
        $rehash = user_pass_rehash($user,
          $time);
        // TODO: Replace hard-coded link expiry.
        // Expire in 24 hours.
        if (REQUEST_TIME - $time > 3600 * 24) {
          return new JsonResponse(['message' => $this->t('Link Expired.')],
            400);
        }
        if ($hash === $rehash) {
          $user->setPassword($pass);
          $user->save();
          return new JsonResponse(['message' => $this->t('Password reset successful.')]);
        }
      }
      return new JsonResponse(['message' => $this->t('Password Reset Failed')],
        400);
    }
    catch (\Exception $e) {
      return new JsonResponse(['message' => $this->t('Password Reset Failed')],
        400);
    }
  }

  /**
   * Load an Entity form and update the related fields.
   *
   * @param \Drupal\user\Entity\User $entity
   *   User entity to update.
   * @param array $values
   *   Array of values converted from JSON.
   * @param string $form
   *   Entity form to update.
   */
  protected function updateEntityFields(User &$entity, array $values, string $form) {
    $form = $this->entityFormBuilder()->getForm($entity,
      $form);
    foreach ($form as $key => $field) {
      if (strpos($key,
          'field_') === 0) {
        $fieldName = substr($key,
          strlen('field_'));
        if (isset($values[$fieldName])) {
          $entity->set($key,
            Html::escape($values[$fieldName]));
        }
      }
    }
  }

  /**
   * Validate an updated user.
   *
   * @param Drupal\user\Entity\User $user
   *   The user to be validated.
   *
   * @return array
   *   An array of validation errors keyed against the field raising the error.
   */
  protected function validateUser(User $user): array {
    $violationHints = [];
    /** @var \Drupal\Core\Entity\EntityConstraintViolationList $violations */
    $violations = $user->validate();
    foreach ($violations->getFieldNames() as $name) {
      $vl = $violations->getByField($name);
      if ($vl instanceof ConstraintViolationListInterface) {
        $violationHints[$vl->get(0)->getPropertyPath()] = $vl->get(0)
          ->getMessage();
      }
    }
    return $violationHints;
  }

}
