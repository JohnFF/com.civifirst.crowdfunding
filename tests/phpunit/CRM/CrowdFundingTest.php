<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

define('CONTRIBUTION_STATUS_ID_COMPLETED', 1);
define('FINANCIAL_TYPE_ID_EVENT_FEE', 4);
define('PARTICIPANT_STATUS_ID_REGISTERED', 1);

/**
 * This tests the CrowdFunding functionality, to ensure that Parent Contributions'
 * statuses are updated when child Contributions are.
 *
 * @group headless
 */
class CRM_CrowdFundingTest extends \PHPUnit_Framework_TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://github.com/civicrm/org.civicrm.testapalooza/blob/master/civi-test.md
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp() {
    parent::setUp();
  }

  public function tearDown() {
    parent::tearDown();
  }

  /**
   * Test that the Parent Contribution Status is updated as child Contributions
   * are made.
   */
  public function testContributionStatusProgression() {

    $iCollector = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testcollector@example.org',
    ));

    $iDonor = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testdonor@example.org',
    ));

    $parentContribution = civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'financial_type_id' => 'Event Fee',
      'total_amount' => '15.00',
      'contact_id' => $iCollector['id'],
      'contribution_status_id' => 'Pending',
    ));

    $apiFieldName = CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_PARENT_CONTRIBUTION_ID);

    // Add enough payments to exceed the needed amount.
    for ($iPayment = 0; $iPayment < 3; $iPayment++) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'total_amount' => '5.00',
        'contact_id' => $iDonor['id'],
        $apiFieldName => $parentContribution['id'],
        'contribution_status_id' => 'Completed',
        'financial_type_id' => 'Donation',
      ));
    }

    $newParentContributionData = civicrm_api3('Contribution', 'getsingle', array(
      'sequential' => 1,
      'id' => $parentContribution['id'],
    ));

    $accumulatedFundsFieldName = CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_ACCUMULATED_FUNDS);

    $this->assertEquals(CONTRIBUTION_STATUS_ID_COMPLETED, $newParentContributionData['contribution_status_id']);
    $this->assertEquals(15.00, $newParentContributionData[$accumulatedFundsFieldName]);
  }

  public function testNormalContribution() {
    $iDonor = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testdonor@example.org',
    ));

    $normalContributionResult = civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'total_amount' => '15.00',
      'contact_id' => $iDonor['id'],
      'contribution_status_id' => 'Completed',
      'financial_type_id' => 'Donation',
    ));

    $this->assertEquals(0, $normalContributionResult['is_error']);
  }

  /**
   * Test that event participations are updated when Parent Contributions Status
   * changes.
   */
  public function testEventParticipationStatus() {
    $testDetails = self::createTestPaidParticipant();

    $iDonor = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testcollector@example.org',
    ));

    $apiFieldName = CRM_Crowdfunding::getApiFieldName('parent_contribution_id');

    // Add enough payments to exceed the needed amount.
    for ($iPayment = 0; $iPayment < 3; $iPayment++) {
      $newDonation = civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'total_amount' => "15.00",
        'contact_id' => $iDonor['id'],
        $apiFieldName => $testDetails['contribution_id'],
        'contribution_status_id' => 'Completed',
        'financial_type_id' => 'Donation',
      ));
    }

    $newParticipantStatus = civicrm_api3('Participant', 'getvalue', array(
      'sequential' => 1,
      'id' => $testDetails['participant_id'],
      'return' => "participant_status_id",
    ));

    $this->assertEquals(PARTICIPANT_STATUS_ID_REGISTERED, $newParticipantStatus);

  }

  public function testGetApiFieldName() {
    $this->assertNotEquals('', CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_PARENT_CONTRIBUTION_ID));
    $this->assertNotEquals('', CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_ACCUMULATED_FUNDS));
  }

  public static function createTestPaidParticipant() {
    $iCollector = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testcollector@example.org',
    ));

    $event = civicrm_api3('Event', 'create', array(
      'sequential' => 1,
      'event_type_id' => "Exhibition",
      'start_date' => '2017-01-01',
      'end_date' => '2017-01-02',
      'title' => "test booking 100",
      'is_active' => 1,
      'is_partial_payment' => 1,
      'requires_approval' => 0,
      'financial_type_id' => 'Event Fee',
      'is_monetary' => 1,
    ));

    // Set prices
    $priceSet = civicrm_api3('PriceSet', 'create', array(
      'title' => $event['values'][0]['title'] . $event['id'],
      'is_quick_config' => 1,
      'financial_type_id' => 'Event Fee',
      'entity' => array("civicrm_event" => array($event['id'])),
      'is_active' => 1,
      'extends' => 'CiviEvent',
    ));

    $insertPriceSetEntitySql = 'INSERT INTO civicrm_price_set_entity (entity_table, entity_id, price_set_id) VALUES ("civicrm_event", %1, %2)';

    $insertPriceSetEntityParams = array(
      1 => array($event['id'], 'Integer'),
      2 => array($priceSet['id'], 'Integer'),
    );

    CRM_Core_DAO::executeQuery($insertPriceSetEntitySql, $insertPriceSetEntityParams);

    $priceField = civicrm_api3('PriceField', 'create', array(
      'price_set_id' => $priceSet['id'],
      "name" => "contribution_amount",
      "label" => "Contribution Amount",
      "html_type" => "Text",
      "is_enter_qty" => "0",
      "is_display_amounts" => "1",
      "options_per_line" => "1",
      "is_active" => "1",
      "is_required" => "1",
      "visibility_id" => "1",
    ));

    $priceSetField = civicrm_api3('PriceFieldValue', 'create', array(
      'price_field_id' => $priceField['id'],
      'name' => 'test_fee_amount',
      'label' => 'Test Fee Amount',
      'amount' => 10.00,
      'financial_type_id' => FINANCIAL_TYPE_ID_EVENT_FEE,
      "non_deductible_amount" => "0.00",
      "contribution_type_id" => "1",
      'is_active' => 1,
    ));

    $participant = civicrm_api3('Participant', 'create', array(
      'sequential' => 1,
      'event_id' => $event['id'],
      'contact_id' => $iCollector['id'],
      'role_id' => 'Attendee',
      'fee_amount' => 10.00,
      'status_id' => 'Pending from pay later',
    ));

    $contribution = civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'financial_type_id' => FINANCIAL_TYPE_ID_EVENT_FEE,
      'total_amount' => 10.00,
      'contact_id' => $iCollector['id'],
      'contribution_status_id' => 'Pending',
      'is_pay_later' => 1,
      'payment_processor' => 0,
    ));

    civicrm_api3('ParticipantPayment', 'create', array(
      'sequential' => 1,
      'participant_id' => $participant['values'][0]['id'],
      'contribution_id' => $contribution['id'],
    ));

    return array(
      'participant_id' => $participant['id'],
      'contribution_id' => $contribution['id'],
    );
  }

  public function testGetParentContributionIdRemainingAmount() {
    $iCollector = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testcollector@example.org',
    ));

    $iDonor = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testdonor@example.org',
    ));

    $parentContribution = civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'financial_type_id' => 'Event Fee',
      'total_amount' => '15.00',
      'contribution_status_id' => 'Pending',
      'contact_id' => $iCollector['id'],
    ));

    $apiFieldName = CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_PARENT_CONTRIBUTION_ID);

    for ($iPayment = 0; $iPayment < 2; $iPayment++) {
      civicrm_api3('Contribution', 'create', array(
        'sequential' => 1,
        'total_amount' => '5.00',
        $apiFieldName => $parentContribution['id'],
        'contribution_status_id' => 'Completed',
        'financial_type_id' => 'Donation',
        'contact_id' => $iDonor['id'],
      ));
    }

    $crowdfunding = new CRM_Crowdfunding();
    $this->assertEquals(5, $crowdfunding->getParentContributionIdRemainingAmount($parentContribution['id']));

    civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'total_amount' => '5.00',
      $apiFieldName => $parentContribution['id'],
      'contribution_status_id' => 'Completed',
      'financial_type_id' => 'Donation',
      'contact_id' => $iDonor['id'],
    ));

    $this->assertEquals(0, $crowdfunding->getParentContributionIdRemainingAmount($parentContribution['id']));
  }

  public function testCivicrmCustomUpdateHook() {
    $iCollector = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testcollector@example.org',
    ));

    $parentContribution = civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'financial_type_id' => 'Event Fee',
      'total_amount' => '15.00',
      'contribution_status_id' => 'Pending',
      'contact_id' => $iCollector['id'],
    ));

    $apiFieldName = CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_PARENT_CONTRIBUTION_ID);

    $iDonor = civicrm_api3('Contact', 'create', array(
      'contact_type' => 'Individual',
      'email' => 'testdonor@example.org',
    ));

    civicrm_api3('Contribution', 'create', array(
      'sequential' => 1,
      'total_amount' => '5.00',
      $apiFieldName => "" . $parentContribution['id'] . "",
      'contribution_status_id' => 'Completed',
      'financial_type_id' => 'Donation',
      'contact_id' => $iDonor['id'],
    ));

    $crowdfunding = new CRM_Crowdfunding();
    $crowdfunding->onContributionCustomUpdate("'" . $parentContribution['id'] . "'");

    $parentContribution = civicrm_api3('contribution', 'getsingle', array('id' => $parentContribution['id']));

    $this->assertEquals(5, $parentContribution[CRM_Crowdfunding::getApiFieldName(CRM_Crowdfunding::CUSTOM_FIELD_NAME_ACCUMULATED_FUNDS)]);
  }
}
