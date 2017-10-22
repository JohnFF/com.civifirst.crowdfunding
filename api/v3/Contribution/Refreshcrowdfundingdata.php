<?php
use CRM_Crowdfunding_ExtensionUtil as E;

/**
 * Contribution.Refreshcrowdfundingdata API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRMDOC/API+Architecture+Standards
 */
function _civicrm_api3_contribution_Refreshcrowdfundingdata_spec(&$spec) {
  $spec['contribution']['api.required'] = 1;
}

/**
 * Contribution.Refreshcrowdfundingdata API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contribution_Refreshcrowdfundingdata($params) {
  $crowdfunding = new CRM_Crowdfunding();
  $crowdfunding->refreshParentContributionStatus($params['id']);
}
