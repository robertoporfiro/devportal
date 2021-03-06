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

namespace Drupal\ibm_apim\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Checks whether monetization is enabled.
 */
class BillingAccessCheck implements AccessInterface {

  public function access() {
    $allowed = FALSE;
    $billing_enabled = \Drupal::state()->get('ibm_apim.billing_enabled');
    $current_user = \Drupal::currentUser();
    if (!$current_user->isAnonymous() && $current_user->id() != 1 && $billing_enabled) {
      $allowed = TRUE;
    }

    return AccessResult::allowedIf($allowed);
  }
}