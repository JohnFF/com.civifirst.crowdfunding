<?php

/**
 * This class encapsulates the Crowd Funding functionality.
 */
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

    // See if the Parent Contribution's total amount is met by the Child Contributions.
    $parentContributionDetails = civicrm_api3('Contribution', 'getsingle', array(
      'sequential' => 1,
      'return' => array('total_amount', 'contribution_status'),
      'id' => $parentContributionId,
    ));

    $childContributionsTotal = $this->getChildContributionTotal($parentContributionId);

    $newContributionStatus = '';
    if ($childContributionsTotal <= 0) {
      // There are no related payments, or the value is negative.
      $newContributionStatus = 'Pending';
    }
    elseif ($childContributionsTotal < $parentContributionDetails['total_amount']) {
      // There are payments, but not enough.
      $newContributionStatus = 'Partially paid';
    }
    else {
      // Paid in full, maybe in excess.
      $newContributionStatus = 'Completed';
    }

    if ($newContributionStatus === $parentContributionDetails['contribution_status']) {
      // The Contribution's status hasn't changed, so updating here would throw the hook unnecessarily.
      return;
    }

    // Update the Contribution's status.
    $this->updateContributionStatus($parentContributionId, $newContributionStatus, $parentContributionDetails['total_amount']); // Bypasses an error with updating contribution statuses where the total amount is needed.

    if ($newContributionStatus === 'Completed') {
      $this->onParentContributionComplete($parentContributionId);
    }
  }

  /**
   *
   * @param int $parentContributionId
   * @param string $newContributionStatus
   * @param float $totalAmount
   */
  private function updateContributionStatus($parentContributionId, $newContributionStatus) {
    // Buggy in versions of CiviCRM before 4.7.20.
     civicrm_api3('Contribution', 'create', array(
       'id' => $parentContributionId,
       'contribution_status_id' => $newContributionStatus,
     ));
  }

  /**
   *
   * @param int $parentContributionId
   * return void
   */
  private function onParentContributionComplete($parentContributionId) {
    // If the Contribution is Completed, update Participants' status if needed.
    $participantStatuses = CRM_Event_PseudoConstant::participantStatus();
    $ids = CRM_Event_BAO_Participant::getParticipantIds($parentContributionId);
    foreach ($ids as $val) {
      $participantUpdate['id'] = $val;
      $participantUpdate['status_id'] = array_search('Registered', $participantStatuses);
      CRM_Event_BAO_Participant::add($participantUpdate);
    }
  }

  /**
   * @param int $parentContributionId
   * @return int $childContributionsTotal
   */
  private function getChildContributionTotal($parentContributionId) {

    $childContributions = civicrm_api3('Contribution', 'get', array(
      'sequential' => 1,
      'return' => array('total_amount', 'contribution_status'),
      $this->apiParentContributionIdFieldId => $parentContributionId,
    ));

    $childContributionsTotal = 0;

    foreach ($childContributions['values'] as $childContribution) {
      if ('Completed' === $childContribution['contribution_status']) {
        $childContributionsTotal += $childContribution['total_amount'];
      }
    }
    return $childContributionsTotal;
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
      $this->refreshParentContributionStatus($parentContributionId);
    }
  }

  /**
   * In the case of a Crowd Funding custom field being updated, just recalculate
   * the amount.
   *
   * @param int $parentContributionId
   */
  public function onContributionCustomUpdate($parentContributionId) {
    if (!empty($parentContributionId)) {
      $this->refreshParentContributionStatus($parentContributionId);
    }
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
