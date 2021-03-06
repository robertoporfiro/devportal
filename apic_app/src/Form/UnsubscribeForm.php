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

use Drupal\apic_app\Event\SubscriptionDeleteEvent;
use Drupal\apic_app\Service\ApplicationRestInterface;
use Drupal\apic_app\Subscription;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\ibm_apim\Service\UserUtils;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to unsubscribe an application from a plan.
 */
class UnsubscribeForm extends ConfirmFormBase {

  /**
   * The node representing the application.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $node;

  /**
   * This represents the subscription ID
   *
   * @var string
   */
  protected $subId;

  protected $restService;

  protected $userUtils;

  /**
   * ApplicationCreateForm constructor.
   *
   * @param ApplicationRestInterface $restService
   * @param UserUtils $userUtils
   */
  public function __construct(ApplicationRestInterface $restService, UserUtils $userUtils) {
    $this->restService = $restService;
    $this->userUtils = $userUtils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Load the service required to construct this class
    return new static($container->get('apic_app.rest_service'), $container->get('ibm_apim.user_utils'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'application_unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $appId = NULL, $subId = NULL) {
    $this->node = $appId;
    $this->subId = Html::escape($subId);
    $form = parent::buildForm($form, $form_state);
    $themeHandler = \Drupal::service('theme_handler');
    if ($themeHandler->themeExists('bootstrap')) {
      if (isset($form['actions']['submit'])) {
        $form['actions']['submit']['#icon'] = \Drupal\bootstrap\Bootstrap::glyphicon('ok');
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
    return $this->t('Are you sure you want to unsubscribe from this plan?');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unsubscribe');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Unsubscribe %title from this plan?', ['%title' => $this->node->title->value]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $analytics_service = \Drupal::service('ibm_apim.analytics')->getDefaultService();
    if(isset($analytics_service) && $analytics_service->getClientEndpoint() !== NULL) {
      return Url::fromRoute('apic_app.subscriptions', ['node' => $this->node->id()]);
    } else {
      return Url::fromRoute('entity.node.canonical', ['node' => $this->node->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    ibm_apim_entry_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
    $appId = $this->node->application_id->value;
    $url = $this->node->apic_url->value . '/subscriptions/' . $this->subId;
    $result = $this->restService->deleteSubscription($url);
    if (isset($result) && $result->code >= 200 && $result->code < 300) {
      $planname = '';
      // get details of the subscription before removing it
      if (!empty($this->node->application_subscriptions->getValue())) {
        $existingsubs = array();
        foreach($this->node->application_subscriptions->getValue() as $nextSub){
          $existingsubs[] = unserialize($nextSub['value']);
        }
        if (is_array($existingsubs)) {
          foreach ($existingsubs as $sub) {
            if (isset($sub['id']) && $sub['id'] == $this->subId) {
              // found the one we want
              $product_url = $sub['product_url'];
              $planname = $sub['plan'];
              break;
            }
          }
        }
      }
      // Find name and version of product
      $query = \Drupal::entityQuery('node');
      $query->condition('type', 'product');
      $query->condition('apic_url.value', $product_url);
      $nids = $query->execute();
      if (isset($nids) && !empty($nids)) {
        $nids = array_values($nids);
      }
      if(count($nids) < 1){
        \Drupal::logger('apic_app')->warning('Unable to find product name and version for @producturl. Found @size matches in db.',
          array('@producturl' => $product_url, '@size' => count($nids)));
        $productTitle = 'unknown';
        $theProduct = NULL;
      }
      else {
        $theProduct = Node::load($nids[0]);
        $productTitle = $theProduct->getTitle();
      }

      Subscription::delete($this->node->apic_url->value, $this->subId);

      drupal_set_message(t('Application unsubscribed successfully.'));
      $current_user = \Drupal::currentUser();
      \Drupal::logger('apic_app')->notice('Application @appname unsubscribed from @product @plan plan by @username', [
        '@appname' => $this->node->getTitle(),
        '@product' => $productTitle,
        '@plan' => $planname,
        '@username' => $current_user->getAccountName(),
      ]);
      // Calling all modules implementing 'hook_apic_app_unsubscribe':
      \Drupal::moduleHandler()->invokeAll('apic_app_unsubscribe', [
        'node' => $this->node,
        'data' => $result->data,
        'appId' => $appId,
        'product_url' => $product_url,
        'plan' => $planname,
        'subId' => $this->subId,
      ]);

      // Rules
      $moduleHandler = \Drupal::service('module_handler');
      if ($moduleHandler->moduleExists('rules')) {
        // Set the args twice on the event: as the main subject but also in the
        // list of arguments.
        $event = new SubscriptionDeleteEvent($this->node, $theProduct, $planname, ['application' => $this->node, 'product' => $theProduct, 'planName' => $planname]);
        $event_dispatcher = \Drupal::service('event_dispatcher');
        $event_dispatcher->dispatch(SubscriptionDeleteEvent::EVENT_NAME, $event);
      }
    }
    $form_state->setRedirectUrl($this->getCancelUrl());
    ibm_apim_exit_trace(__CLASS__ . '::' . __FUNCTION__, NULL);
  }
}
