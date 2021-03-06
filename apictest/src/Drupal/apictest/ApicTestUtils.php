<?php
/**
 * Created by PhpStorm.
 * User: aearl
 * Date: 16/04/18
 * Time: 16:11
 */

namespace Drupal\apictest;

use Drupal\consumerorg\ApicType\ConsumerOrg;
use Drupal\consumerorg\ApicType\Member;
use Drupal\consumerorg\ApicType\Role;
use Drupal\ibm_apim\ApicType\ApicUser;

class ApicTestUtils {

  /**
   * Generates a "unique" id based on the current system timestamp.
   * (May not be unique if called very quickly in sequence but seems to be)
   *
   * @return mixed
   */
  public static function makeId(){
    return str_replace('.', '', microtime(1));
  }

  /**
   * Create a role with no permissions. Provide the corg so that the role's URL can be set.
   * You will need to set a name, title, summary and permissions for this role.
   *
   * @param \Drupal\consumerorg\ApicType\ConsumerOrg $org
   *
   * @return \Drupal\consumerorg\ApicType\Role
   */
  public static function makeNoPermissionsRole(ConsumerOrg $org) {
    $blank_role = new Role();
    $blank_role->setId('generated-role-' . ApicTestUtils::makeId());
    $blank_role->setUrl('/orgs/' . $org->getId() . '/roles/' . $blank_role->getId());
    $blank_role->setScope('org');
    $blank_role->setOrgUrl($org->getOrgUrl());

    $blank_role->setName('blank-test-role');
    $blank_role->setTitle('Blank Test Role');
    $blank_role->setSummary('This role was created during the behat test runs. The title and name should have been overridden by the test that created this role!!');

    return $blank_role;
  }

  /**
   * Create an org owner role with all relevant permissions.
   *
   * @param \Drupal\consumerorg\ApicType\ConsumerOrg $org
   *
   * @return \Drupal\consumerorg\ApicType\Role
   */
  public static function makeOwnerRole(ConsumerOrg $org) {
    $owner = ApicTestUtils::makeNoPermissionsRole($org);
    $owner->setName('owner');
    $owner->setTitle('Owner');
    $owner->setSummary('Owns and administers the app developer organization');

    // Owner gets every permission under the sun
    $perms = \Drupal::service('ibm_apim.permissions')->getAll();
    if (isset($perms) && !empty($perms)) {
      $perms = array_keys($perms);
    }
    $owner->setPermissions($perms);
    $org->addRole($owner);

    // Update the org in the database
    \Drupal::service('ibm_apim.consumerorg')->createOrUpdateNode($org, 'ApicTestUtils::makeOwnerRole');

    return $owner;
  }

  /**
   * Create a developer role for the given org.
   *
   * @param \Drupal\consumerorg\ApicType\ConsumerOrg $org
   *
   * @return \Drupal\consumerorg\ApicType\Role
   */
  public static function makeDeveloperRole(ConsumerOrg $org) {
    $developer = ApicTestUtils::makeNoPermissionsRole($org);
    $developer->setName('developer');
    $developer->setTitle('Developer');
    $developer->setSummary('A developer inside an org owned by another user');

    // Developers have a handful of view and manage permissions
    $developer->setPermissions(array("member:view", "view", "product:view", "app:view", "app-dev:manage", "app:manage", "app-analytics:view"));
    $org->addRole($developer);

    // Update the org in the database
    \Drupal::service('ibm_apim.consumerorg')->createOrUpdateNode($org, 'ApicTestUtils::makeDeveloperRole');

    return $developer;
  }

  /**
   * Create a viewer role for the given org.
   *
   * @param \Drupal\consumerorg\ApicType\ConsumerOrg $org
   *
   * @return \Drupal\consumerorg\ApicType\Role
   */
  public static function makeViewerRole(ConsumerOrg $org) {
    $viewer = ApicTestUtils::makeNoPermissionsRole($org);
    $viewer->setName('viewer');
    $viewer->setTitle('Viewer');
    $viewer->setSummary('A viewer inside an org owned by another user');

    // Viewers only have a set of view permissions and can't manage / change stuff
    $viewer->setPermissions(array("member:view", "settings:view", "view", "product:view", "app:view", "subscription:view", "app-analytics:view"));
    $org->addRole($viewer);

    // Update the org in the database
    \Drupal::service('ibm_apim.consumerorg')->createOrUpdateNode($org, 'ApicTestUtils::makeViewerRole');

    return $viewer;
  }

  /**
   * Adds the user specified to the given org with all of the roles that are passed in.
   *
   * @param \Drupal\consumerorg\ApicType\ConsumerOrg $org
   * @param \Drupal\ibm_apim\ApicType\ApicUser $user
   * @param array $roles
   */
  public static function addMemberToOrg(ConsumerOrg $org, ApicUser $user, array $roles) {
    $member = new Member();
    $member->setUser($user);
    $member->setUserUrl($user->getUrl());
    $member->setUrl('/generated-member/' . ApicTestUtils::makeId());
    $member->setState('active');

    $roleUrls = array();
    foreach($roles as $role){
      $roleUrls[] = $role->getUrl();
    }
    $member->setRoleUrls($roleUrls);

    $org->addMember($member);

    // Update the org in the database
    \Drupal::service('ibm_apim.consumerorg')->createOrUpdateNode($org, 'ApicTestUtils::addMember');
  }
}