<?php
/********************************************************* {COPYRIGHT-TOP} ***
 * Licensed Materials - Property of IBM
 * 5725-L30, 5725-Z22
 *
 * (C) Copyright IBM Corporation 2018
 *
 * All Rights Reserved.
 * US Government Users Restricted Rights - Use, duplication or disclosure
 * restricted by GSA ADP Schedule Contract with IBM Corp.
 ********************************************************** {COPYRIGHT-END} **/

namespace Drupal\apic_app\Form;

use Drupal\apic_app\Application;
use Drupal\apic_app\Service\ApplicationRestInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Extension\ThemeHandler;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\ibm_apim\Service\UserUtils;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Remove form for applications.
 */
class ApplicationDeleteForm extends ConfirmFormBase {

  /**
   * The node representing the application.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  protected $userUtils;

  protected $appRestService;

  protected $currentUser;

  protected $themeHandler;

  protected $moduleHandler;

  /**
   * ApplicationDeleteForm constructor.
   *
   * @param \Drupal\apic_app\Service\ApplicationRestInterface $appRestService
   * @param \Drupal\ibm_apim\Service\UserUtils $userUtils
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * @param \Drupal\Core\Extension\ThemeHandler $themeHandler
   * @param \Drupal\Core\Extension\ModuleHandler $moduleHandler
   */
  public function __construct(ApplicationRestInterface $appRestService, UserUtils $userUtils, AccountProxyInterface $current_user, ThemeHandler $themeHandler, ModuleHandler $moduleHandler) {
    $this->appRestService = $appRestService;
    $this->userUtils = $userUtils;
    $this->currentUser = $current_user;
    $this->themeHandler = $themeHandler;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Load the service required to construct this class
    return new static(
      $container->get('apic_app.rest_service'),
      $container->get('ibm_apim.user_utils'),
      $container->get('current_user'),
      $container->get('theme_handler'),
      $container->get('module_handler')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'application_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $appId = NULL): array {
    $this->node = $appId;
    $form = parent::buildForm($form, $form_state);
    if ($this->themeHandler->themeExists('bootstrap')) {
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#icon'] = \Drupal\bootstrap\Bootstrap::glyphicon('trash');
      }
      if (isset($form['actions']['cancel'])) {
        $form['actions']['cancel']['#icon'] = \Drupal\bootstrap\Bootstrap::glyphicon('remove');
      }
    }
    $form['#attached']['library'][] = 'apic_app/basic';

    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Are you sure you want to delete this application? This action cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Delete application %title?', ['%title' => $this->node->title->value]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return $this->node->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
    $appId = $this->node->application_id->value;
    $url = $this->node->apic_url->value;
    $result = $this->appRestService->deleteApplication($url);
    if ($result !== NULL && $result->code >= 200 && $result->code < 300) {
      // create copy of the node we're deleting for use in hooks later
      $node = $this->node;

      \Drupal::logger('apic_app')->notice('Application @appName deleted by @username', [
        '@appName' => $node->getTitle(),
        '@username' => $this->currentUser->getAccountName(),
      ]);

      // also delete the node from the drupal DB too
      Application::deleteNode($this->node->id(), 'delete');
      // Calling all modules implementing 'hook_apic_app_delete':
      $this->moduleHandler->invokeAll('apic_app_delete', [
        'node' => $node,
        'data' => $result->data,
        'appId' => $appId,
      ]);

      drupal_set_message($this->t('Application deleted successfully.'));
      $form_state->setRedirectUrl(Url::fromRoute('view.applications.page_1'));
    }
    else {
      $form_state->setRedirectUrl($this->getCancelUrl());
    }
    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }

}
