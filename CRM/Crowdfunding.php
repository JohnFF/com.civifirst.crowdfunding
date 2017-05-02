<?php

class CRM_Crowdfunding {
  private $apiParentContributionIdFieldId;

  public function __construct() {
    $this->apiParentContributionIdFieldId = self::getApiFieldName();
  }

  private function refreshParentContributionStatus($parentContributionId) {
    
    $parentContributionGoal = civicrm_api3('Contribution', 'getvalue', array(
      'sequential' => 1,
      'return' => 'total_amount',
      'id' => $parentContributionId,
    ));

    $childContributions = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'return' => array('total_amount'),
      $this->apiParentContributionIdFieldId = $parentContributionId,
    ));

    $childContributionsTotal = 0;

    foreach ($childContributions['values'] as $childContribution) {
      $childContributionsTotal += $childContribution['total_amount'];
    }

    if ($childContributionsTotal == 0) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => "Pending",
      ));
    }
    elseif ($childContributionsTotal < $parentContributionGoal) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => "Partially paid",
      ));
    }
    else {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => "Completed",
      ));

      // Update Participants if needed.
      $participantStatuses = CRM_Event_PseudoConstant::participantStatus();
      $ids = CRM_Event_BAO_Participant::getParticipantIds($parentContributionId);
      foreach ($ids as $val) {
        $participantUpdate['id'] = $val;
        $participantUpdate['status_id'] = array_search('Registered', $participantStatuses);
        CRM_Event_BAO_Participant::add($participantUpdate);
      }
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

