<?php

class CRM_Crowdfunding {
  private $apiParentContributionIdFieldId;

  public function __construct() {
    $this->apiParentContributionIdFieldId = self::getApiFieldName();
  }

  private function refreshParentContributionStatus($parentContributeId) {
    
    $parentContributionGoal = civicrm_api3('Contribution', 'getvalue', array(
      'sequential' => 1,
      'return' => 'total_amount',
      'id'=> $parentContributeId,
    ));

    $childContributions = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'return' => array('total_amount'),
      $this->apiParentContributionIdFieldId => $parentContributeId,
    ));

    $childContributionsTotal = 0;

    foreach ($childContributions['values'] as $childContribution) {
      $childContributionsTotal += $childContribution['total_amount'];
    }

    if ($childContributionsTotal == 0) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributeId,
        'contribution_status_id' => "Pending",
      ));
    }
    elseif ($childContributionsTotal < $parentContributionGoal) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributeId,
        'contribution_status_id' => "Partially paid",
      ));
    }
    else {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributeId,
        'contribution_status_id' => "Completed",
      ));
    }
  }

  public function onContributionUpdate($contributeId) {
    $apiParentIdFieldName = self::getApiFieldName();
    
    $parentContributionId = civicrm_api3('Contribution', 'getvalue', array(
      'sequential' => 1,
      'return' => $this->apiParentContributionIdFieldId,
      'id' => $contributeId,
    ));

    if (!empty($parentContributionId)) {
     $this->refreshParentContributionStatus($parentContributeId);
    }
  }

  public function onContributionCustomUpdate($contributeId) {
    $this->refreshParentContributionStatus($contributeId);
  }

  public static function getApiFieldName() {
    $parentContributionIdFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'sequential' => 1,
      'return' => 'id',
      'name' => 'parent_contribution_id',
    ));
    return 'custom_' . $parentContributionIdFieldId;
  }
}

