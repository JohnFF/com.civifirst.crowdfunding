<?php

require_once 'crowdfunding.civix.php';

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function crowdfunding_civicrm_config(&$config) {
  _crowdfunding_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function crowdfunding_civicrm_xmlMenu(&$files) {
  _crowdfunding_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function crowdfunding_civicrm_install() {
  _crowdfunding_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function crowdfunding_civicrm_postInstall() {
  _crowdfunding_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function crowdfunding_civicrm_uninstall() {
  _crowdfunding_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function crowdfunding_civicrm_enable() {
  _crowdfunding_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function crowdfunding_civicrm_disable() {
  _crowdfunding_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function crowdfunding_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _crowdfunding_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function crowdfunding_civicrm_managed(&$entities) {
  _crowdfunding_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function crowdfunding_civicrm_caseTypes(&$caseTypes) {
  _crowdfunding_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function crowdfunding_civicrm_angularModules(&$angularModules) {
  _crowdfunding_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function crowdfunding_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _crowdfunding_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function crowdfunding_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function crowdfunding_civicrm_navigationMenu(&$menu) {
  _crowdfunding_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'com.civifirst.crowdfunding')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _crowdfunding_civix_navigationMenu($menu);
} // */

/**
 * When Contributions are created, edited, or deleted, this can impact our goal.
 *
 * Implements hook_civicrm_post.
 *
 * @return void
 */
function crowdfunding_civicrm_post($op, $objectName, $objectId, &$objectRef) {
  if ($objectName != 'Contribution') {
    return;
  }

  switch ($op) {
    // No need for create, as this is handled in custom.

    case 'edit':
      // case 'delete' takes place in pre hook, otherwise it's looking for info on a dead item.
      // Probably no need to fire on $op == 'create' as our custom fields won't exist then.
      $crowdfunding = new CRM_Crowdfunding();
      $crowdfunding->onContributionUpdate($objectId);
      break;

    default:
      break;
  }
}

/**
 * Some actions need to take place before the item is deleted.
 *
 * Implements hook_civicrm_pre.
 */
function crowdfunding_civicrm_pre($op, $objectName, $objectId, &$objectRef) {
  if ($objectName != 'Contribution') {
    return;
  }

  $crowdfunding = new CRM_Crowdfunding();

  switch ($op) {
    case 'delete':
      $crowdfunding->onContributionUpdate($objectId);
      break;

    default:
      break;
  }
}

/**
 * CiviCRM has a quirk whereby, on creating entities, the post hook is called
 * before the custom fields are added. So we call this hook to cover for it.
 *
 * @return void
 */
function crowdfunding_civicrm_custom($op, $groupID, $entityID, &$params) {
  if (!in_array($op, array('create', 'edit', 'delete'))) {
    return;
  }

  foreach ($params as $eachParam) {
    if ($eachParam['column_name'] != 'parent_contribution_id') {
      return;
    }

    $crowdfunding = new CRM_Crowdfunding();
    $crowdfunding->onContributionCustomUpdate($eachParam['value']);
  }
}

/**
 *
 * @param type $formName
 * @param type $form
 */
function crowdfunding_civicrm_postProcess($formName, &$form) {
  $crowdfunding = new CRM_Crowdfunding();
  $crowdfunding->setParentContributionIdOnFormSubmission($form);
}
