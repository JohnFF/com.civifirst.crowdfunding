<?php

class CRM_Crowdfunding {
  private $apiParentContributionIdFieldId;

  public function __construct() {
    $this->apiParentContributionIdFieldId = self::getApiFieldName();
  }

  /**
   * This updates the parent Contribution's status with the new data.
   *
   * @param int $parentContributionId
   */
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
      // TODO check sub payments' status.
      $childContributionsTotal += $childContribution['total_amount'];
    }

    if ($childContributionsTotal <= 0) {
      // There are no related payments, or the value is negative.
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => 'Pending',
      ));
    }
    elseif ($childContributionsTotal < $parentContributionGoal) {
      // There are payments, but not enough.
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => 'Partially paid',
      ));
    }
    else {
      // Paid in full, maybe in excess.
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'id' => $parentContributionId,
        'contribution_status_id' => 'Completed',
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

  /**
   * When a Contribution is updated, use this functionality.
   *
   * @param int $contributeId
   */
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

  /**
   * In the case of a Crowd Funding custom field being updated, just recalculate
   * the amount.
   *
   * @param int $contributeId
   */
  public function onContributionCustomUpdate($contributeId) {
    $this->refreshParentContributionStatus($contributeId);
  }

  /**
   * Returns the parent Contribution Id's custom field's api key in the form custom_XYZ.
   *
   * @return string
   */
  public static function getApiFieldName() {
    $parentContributionIdFieldId = civicrm_api3('CustomField', 'getvalue', array(
      'sequential' => 1,
      'return' => 'id',
      'name' => 'parent_contribution_id',
    ));
    return 'custom_' . $parentContributionIdFieldId;
  }
}

