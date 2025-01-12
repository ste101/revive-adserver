<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

require_once MAX_PATH . '/lib/OA/Maintenance/Priority/Ad.php';
require_once MAX_PATH . '/lib/OA/Maintenance/Priority/AdServer/Task/AllocateZoneImpressions.php';
require_once MAX_PATH . '/lib/OA/ServiceLocator.php';
require_once MAX_PATH . '/lib/pear/Date.php';

/**
 * A class for testing the Maintenance_Priority_AdServer_AdvertisementZoneImpressionAllocation class.
 *
 * @package    OpenXMaintenance
 * @subpackage TestSuite
 */
class Test_OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions extends UnitTestCase
{
    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
        Mock::generate('MAX_Dal_Entities');
        Mock::generate('OA_Dal_Maintenance_Priority');
        Mock::generate('OA_DB_Table_Priority');
        Mock::generate('MDB2_Driver_Common');
    }

    /**
     * A method to be called before every test to store default
     * mocked data access layers in the service locator.
     */
    public function setUp()
    {
        $oServiceLocator = OA_ServiceLocator::instance();
        $oDBh = new MockMDB2_Driver_Common($this);
        $oDBh->setReturnValue('quoteIdentifier', 'qTbl');
        $oMaxDalEntites = new MockMAX_Dal_Entities($this);
        $oServiceLocator->register('MAX_Dal_Entities', $oMaxDalEntites);
        $oMaxDalMaintenancePriority = new MockOA_Dal_Maintenance_Priority($this);
        $oMaxDalMaintenancePriority->setReturnReference('_getDbConnection', $oDBh);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oMaxDalMaintenancePriority);
        $oTable = new MockOA_DB_Table_Priority($this);
        $oServiceLocator->register('OA_DB_Table_Priority', $oTable);
    }

    /**
     * A method to be called after every test to remove the
     * mocked data access layers from the service locator.
     *
     */
    public function tearDown()
    {
        $oServiceLocator = OA_ServiceLocator::instance();
        $oServiceLocator->remove('MAX_Dal_Entities');
        $oServiceLocator->remove('OA_Dal_Maintenance_Priority');
        $oServiceLocator->remove('OA_DB_Table_Priority');
    }

    /**
     * A method to test the _setZoneForecasts() method.
     *
     * Test 1: Test that both arrays used to store zone impression data are
     *         correctly stored when no zone impression forecast data is
     *         returned from the data access layer.
     * Test 2: Test that both arrays used to store zone impression data are
     *         correctly stored when zone impression forecast data is
     *         returned from the data access layer.
     */
    public function test_setZoneForecasts()
    {
        // Prepare the DAL return values for the tests
        $oServiceLocator = OA_ServiceLocator::instance();
        $oDal = $oServiceLocator->get('OA_Dal_Maintenance_Priority');
        $oDal->setReturnValueAt(0, 'getZonesForecastsForAllZones', []);
        $oDal->setReturnValueAt(1, 'getZonesForecastsForAllZones', [1 => 5, 2 => 7, 9 => 9]);
        $oDal->expectCallCount('getZonesForecastsForAllZones', 2);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oDal);

        // Prepare the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object for testing
        $oAllocateZoneImpressions = new OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions();

        // Test 1
        $oAllocateZoneImpressions->_setZoneForecasts();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAvailableForecastZoneImpressions));
        $this->assertTrue(empty($oAllocateZoneImpressions->aAvailableForecastZoneImpressions));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aOverSubscribedZones));
        $this->assertTrue(empty($oAllocateZoneImpressions->aOverSubscribedZones));

        // Test 2
        $oAllocateZoneImpressions->_setZoneForecasts();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAvailableForecastZoneImpressions));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAvailableForecastZoneImpressions));
        $this->assertEqual(count($oAllocateZoneImpressions->aAvailableForecastZoneImpressions), 3);
        $this->assertEqual($oAllocateZoneImpressions->aAvailableForecastZoneImpressions[1], 5);
        $this->assertEqual($oAllocateZoneImpressions->aAvailableForecastZoneImpressions[2], 7);
        $this->assertEqual($oAllocateZoneImpressions->aAvailableForecastZoneImpressions[9], 9);
        $this->assertTrue(is_array($oAllocateZoneImpressions->aOverSubscribedZones));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aOverSubscribedZones));
        $this->assertEqual(count($oAllocateZoneImpressions->aOverSubscribedZones), 3);
        $this->assertTrue(is_array($oAllocateZoneImpressions->aOverSubscribedZones[1]));
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[1]['zoneId'], 1);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[1]['availableImpressions'], 5);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[1]['desiredImpressions'], 0);
        $this->assertTrue(!empty($oAllocateZoneImpressions->aOverSubscribedZones[1]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aOverSubscribedZones[2]));
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[2]['zoneId'], 2);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[2]['availableImpressions'], 7);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[2]['desiredImpressions'], 0);
        $this->assertTrue(!empty($oAllocateZoneImpressions->aOverSubscribedZones[2]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aOverSubscribedZones[9]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aOverSubscribedZones[9]));
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[9]['zoneId'], 9);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[9]['availableImpressions'], 9);
        $this->assertEqual($oAllocateZoneImpressions->aOverSubscribedZones[9]['desiredImpressions'], 0);
    }

    /**
     * A method to test the _getAllCampaigns() method.
     *
     * Test 1: Test with no campaigns returned from the DAL.
     * Test 2: Test with campaigns in the DAL.
     */
    public function test_getAllCampaigns()
    {
        // Prepare the DAL return values for the tests
        $oServiceLocator = OA_ServiceLocator::instance();
        $oDal = $oServiceLocator->get('OA_Dal_Maintenance_Priority');
        $oDal->setReturnValueAt(0, 'getCampaigns', []);
        $oDal->setReturnValueAt(
            1,
            'getCampaigns',
            [
                new OX_Maintenance_Priority_Campaign([
                    'campaignid' => 1,
                    'views' => 1000,
                    'clicks' => 0,
                    'conversions' => 0,
                    'expire_time' => '2006-01-27',
                    'target_impression' => 0,
                    'target_click' => 0,
                    'target_conversion' => 0,
                    'priority' => 5
                ]),
                new OX_Maintenance_Priority_Campaign([
                    'campaignid' => 2,
                    'views' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'target_impression' => 1000,
                    'target_click' => 0,
                    'target_conversion' => 0,
                    'priority' => 4
                ])
            ]
        );
        $oDal->expectCallCount('getCampaigns', 2);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oDal);

        // Prepare the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object for testing
        $oAllocateZoneImpressions = new OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions();

        // Test 1
        $aCampaigns = $oAllocateZoneImpressions->_getAllCampaigns();
        $this->assertTrue(is_array($aCampaigns));
        $this->assertTrue(empty($aCampaigns));

        // Test 2
        $aCampaigns = $oAllocateZoneImpressions->_getAllCampaigns();
        $this->assertTrue(is_array($aCampaigns));
        $this->assertTrue(!empty($aCampaigns));
        $this->assertEqual(count($aCampaigns), 2);
        $this->assertIsA($aCampaigns[0], 'OX_Maintenance_Priority_Campaign');
        $this->assertEqual($aCampaigns[0]->id, 1);
        $this->assertEqual($aCampaigns[0]->impressionTargetTotal, 1000);
        $this->assertEqual($aCampaigns[0]->clickTargetTotal, 0);
        $this->assertEqual($aCampaigns[0]->conversionTargetTotal, 0);
        $this->assertEqual($aCampaigns[0]->impressionTargetDaily, 0);
        $this->assertEqual($aCampaigns[0]->clickTargetDaily, 0);
        $this->assertEqual($aCampaigns[0]->conversionTargetDaily, 0);
        $this->assertEqual($aCampaigns[0]->priority, 5);
        $this->assertIsA($aCampaigns[1], 'OX_Maintenance_Priority_Campaign');
        $this->assertEqual($aCampaigns[1]->id, 2);
        $this->assertEqual($aCampaigns[1]->impressionTargetTotal, 0);
        $this->assertEqual($aCampaigns[1]->clickTargetTotal, 0);
        $this->assertEqual($aCampaigns[1]->conversionTargetTotal, 0);
        $this->assertEqual($aCampaigns[1]->impressionTargetDaily, 1000);
        $this->assertEqual($aCampaigns[1]->clickTargetDaily, 0);
        $this->assertEqual($aCampaigns[1]->conversionTargetDaily, 0);
        $this->assertEqual($aCampaigns[1]->priority, 4);
    }

    /**
     * A method to test the _setRequiredImpressions() method.
     *
     * Test 1: Test with an empty array passed in.
     * Test 2: Test with an array of Adverts passed in, but no required impressions set
     *         in the database.
     * Test 3: Test with an array of Adverts passed in, and required impressions set
     *         in the database.
     */
    public function test_setRequiredImpressions()
    {
        // Prepare the DAL return values for the tests
        $oServiceLocator = OA_ServiceLocator::instance();
        $oDal = $oServiceLocator->get('OA_Dal_Maintenance_Priority');
        $oDal->setReturnValueAt(0, 'getRequiredAdImpressions', []);
        $oDal->setReturnValueAt(1, 'getRequiredAdImpressions', [1 => 1, 3 => 10]);
        $oDal->expectCallCount('getRequiredAdImpressions', 2);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oDal);

        // Prepare the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object for testing
        $oAllocateZoneImpressions = new OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions();

        // Test 1
        $aAdverts = [];
        $oAllocateZoneImpressions->_setRequiredImpressions($aAdverts);
        $this->assertTrue(is_array($aAdverts));
        $this->assertTrue(empty($aAdverts));

        // Test 2
        $aAdverts = [];
        $aAdParams = [
            'ad_id' => 1,
            'status' => OA_ENTITY_STATUS_RUNNING,
            'type' => 'sql',
            'weight' => 1
        ];
        $oAd = new OA_Maintenance_Priority_Ad($aAdParams);
        $aAdverts[] = $oAd;
        $aAdParams = [
            'ad_id' => 2,
            'status' => OA_ENTITY_STATUS_RUNNING,
            'type' => 'sql',
            'weight' => 1
        ];
        $oAd = new OA_Maintenance_Priority_Ad($aAdParams);
        $aAdverts[] = $oAd;
        $aAdParams = [
            'ad_id' => 3,
            'status' => OA_ENTITY_STATUS_RUNNING,
            'type' => 'sql',
            'weight' => 1
        ];
        $oAd = new OA_Maintenance_Priority_Ad($aAdParams);
        $aAdverts[] = $oAd;
        $oAllocateZoneImpressions->_setRequiredImpressions($aAdverts);
        $this->assertTrue(is_array($aAdverts));
        $this->assertTrue(!empty($aAdverts));
        $this->assertEqual(count($aAdverts), 3);
        $this->assertTrue(isset($aAdverts[0]));
        $this->assertIsA($aAdverts[0], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[0]->id, 1);
        $this->assertEqual($aAdverts[0]->requiredImpressions, 0);
        $this->assertTrue(isset($aAdverts[1]));
        $this->assertIsA($aAdverts[1], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[1]->id, 2);
        $this->assertEqual($aAdverts[1]->requiredImpressions, 0);
        $this->assertTrue(isset($aAdverts[2]));
        $this->assertIsA($aAdverts[2], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[2]->id, 3);
        $this->assertEqual($aAdverts[2]->requiredImpressions, 0);

        // Test 3
        $oAllocateZoneImpressions->_setRequiredImpressions($aAdverts);
        $this->assertTrue(is_array($aAdverts));
        $this->assertTrue(!empty($aAdverts));
        $this->assertEqual(count($aAdverts), 3);
        $this->assertTrue(isset($aAdverts[0]));
        $this->assertIsA($aAdverts[0], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[0]->id, 1);
        $this->assertEqual($aAdverts[0]->requiredImpressions, 1);
        $this->assertTrue(isset($aAdverts[1]));
        $this->assertIsA($aAdverts[1], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[1]->id, 2);
        $this->assertEqual($aAdverts[1]->requiredImpressions, 0);
        $this->assertTrue(isset($aAdverts[2]));
        $this->assertIsA($aAdverts[2], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($aAdverts[2]->id, 3);
        $this->assertEqual($aAdverts[2]->requiredImpressions, 10);
    }

    /**
     * A method to test the _setCampaigns() method.
     *
     * Test 1: Run a combination test of the tests used in testing the
     *         _getAllCampaigns() and _setRequiredImpressions() method
     *         tests above, and ensure that the methods work when used
     *         together.
     */
    public function test_setCampaigns()
    {
        // Prepare the DAL return values for the tests
        $oServiceLocator = OA_ServiceLocator::instance();
        $oMaxDalMaintenancePriority = $oServiceLocator->get('OA_Dal_Maintenance_Priority');
        $oMaxDalMaintenancePriority->setReturnValueAt(
            0,
            'getCampaigns',
            [
                new OX_Maintenance_Priority_Campaign([
                    'campaignid' => 1,
                    'views' => 1000,
                    'clicks' => 0,
                    'conversions' => 0,
                    'expire_time' => '2006-01-27',
                    'target_impression' => 0,
                    'target_click' => 0,
                    'target_conversion' => 0,
                    'priority' => 5
                ]),
                new OX_Maintenance_Priority_Campaign([
                    'campaignid' => 2,
                    'views' => 0,
                    'clicks' => 0,
                    'conversions' => 0,
                    'target_impression' => 1000,
                    'target_click' => 0,
                    'target_conversion' => 0,
                    'priority' => 4
                ])
            ]
        );
        $oMaxDalMaintenancePriority->expectCallCount('getCampaigns', 1);
        $oMaxDalMaintenancePriority->setReturnValueAt(0, 'getRequiredAdImpressions', [1 => 1, 2 => 9]);
        $oMaxDalMaintenancePriority->setReturnValueAt(1, 'getRequiredAdImpressions', [3 => 5, 4 => 0]);
        $oMaxDalMaintenancePriority->expectCallCount('getRequiredAdImpressions', 2);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oMaxDalMaintenancePriority);

        $oMaxDalEntities = &$oServiceLocator->get('MAX_Dal_Entities');
        $oMaxDalEntities->setReturnValue(
            'getAdsByCampaignId',
            [
                [
                    'ad_id' => 1,
                    'type' => 'web',
                    'weight' => 1,
                    'status' => OA_ENTITY_STATUS_RUNNING
                ],
                [
                    'ad_id' => 2,
                    'type' => 'web',
                    'weight' => 1,
                    'status' => OA_ENTITY_STATUS_RUNNING
                ]
            ],
            [1]
        );
        $oMaxDalEntities->setReturnValue(
            'getAdsByCampaignId',
            [
                [
                    'ad_id' => 3,
                    'type' => 'web',
                    'weight' => 1,
                    'status' => OA_ENTITY_STATUS_RUNNING
                ],
                [
                    'ad_id' => 4,
                    'type' => 'web',
                    'weight' => 2,
                    'status' => OA_ENTITY_STATUS_AWAITING
                ]
            ],
            [2]
        );
        $oMaxDalEntities->expectCallCount('getAdsByCampaignId', 2);
        $oServiceLocator->register('MAX_Dal_Entities', $oMaxDalEntities);

        // Prepare the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object for testing
        $oAllocateZoneImpressions = new OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions();

        // Test 1
        $oAllocateZoneImpressions->_setCampaigns();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aCampaigns));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aCampaigns));
        $this->assertEqual(count($oAllocateZoneImpressions->aCampaigns), 2);
        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[0], 'OX_Maintenance_Priority_Campaign');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->id, 1);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->impressionTargetTotal, 1000);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->clickTargetTotal, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->conversionTargetTotal, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->impressionTargetDaily, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->clickTargetDaily, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->conversionTargetDaily, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->priority, 5);

        $this->assertTrue(is_array($oAllocateZoneImpressions->aCampaigns[0]->aAds));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aCampaigns[0]->aAds));
        $this->assertEqual(count($oAllocateZoneImpressions->aCampaigns[0]->aAds), 2);
        $this->assertTrue(isset($oAllocateZoneImpressions->aCampaigns[0]->aAds[0]));
        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[0]->aAds[0], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->aAds[0]->id, 1);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->aAds[0]->requiredImpressions, 1);
        $this->assertTrue(isset($oAllocateZoneImpressions->aCampaigns[0]->aAds[1]));
        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[0]->aAds[1], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->aAds[1]->id, 2);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[0]->aAds[1]->requiredImpressions, 9);

        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[1], 'OX_Maintenance_Priority_Campaign');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->id, 2);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->impressionTargetTotal, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->clickTargetTotal, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->conversionTargetTotal, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->impressionTargetDaily, 1000);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->clickTargetDaily, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->conversionTargetDaily, 0);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->priority, 4);

        $this->assertTrue(is_array($oAllocateZoneImpressions->aCampaigns[1]->aAds));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aCampaigns[1]->aAds));
        $this->assertEqual(count($oAllocateZoneImpressions->aCampaigns[1]->aAds), 2);
        $this->assertTrue(isset($oAllocateZoneImpressions->aCampaigns[1]->aAds[0]));
        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[1]->aAds[0], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->aAds[0]->id, 3);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->aAds[0]->requiredImpressions, 5);
        $this->assertTrue(isset($oAllocateZoneImpressions->aCampaigns[1]->aAds[1]));
        $this->assertIsA($oAllocateZoneImpressions->aCampaigns[1]->aAds[1], 'OA_Maintenance_Priority_Ad');
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->aAds[1]->id, 4);
        $this->assertEqual($oAllocateZoneImpressions->aCampaigns[1]->aAds[1]->requiredImpressions, 0);
    }

    /**
     * A method to test the _setAdZoneAssociations() method.
     *
     * Test 1: Test with no Campaigns set in the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object.
     * Test 2: Test with a Campaign, but no Advert objects.
     * Test 3: Test with a Campaign and Advert objects, but no ad/zone associations.
     * Test 4: Test with a Campaign and Advert objects, and ad/zone associations.
     */
    public function test_setAdZoneAssociations()
    {
        // Prepare the DAL return values for the tests
        $oServiceLocator = OA_ServiceLocator::instance();
        $oDal = $oServiceLocator->get('OA_Dal_Maintenance_Priority');
        $oDal->setReturnValueAt(0, 'getAdZoneAssociationsByAds', []);
        $oDal->setReturnValueAt(
            1,
            'getAdZoneAssociationsByAds',
            [
                1 => [
                         ['zone_id' => 5],
                         ['zone_id' => 6],
                         ['zone_id' => 7]
                     ],
                2 => [
                         ['zone_id' => 5]
                     ]
            ]
        );
        $oDal->expectCallCount('getAdZoneAssociationsByAds', 2);
        $oServiceLocator->register('OA_Dal_Maintenance_Priority', $oDal);

        // Prepare the OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions object for testing
        $oAllocateZoneImpressions = new OA_Maintenance_Priority_AdServer_Task_AllocateZoneImpressions();

        // Test 1
        $oAllocateZoneImpressions->_setAdZoneAssociations();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations));
        $this->assertTrue(empty($oAllocateZoneImpressions->aAdZoneAssociations));

        // Test 2
        $oCampaign = new OX_Maintenance_Priority_Campaign(
            [
                'campaignid' => 1,
                'views' => 1000,
                'clicks' => 0,
                'conversions' => 0,
                'expire_time' => '2006-01-27 23:59:59',
                'target_impression' => 0,
                'target_click' => 0,
                'target_conversion' => 0,
                'priority' => 5
            ]
        );
        $oAllocateZoneImpressions->aCampaigns[] = $oCampaign;
        $oAllocateZoneImpressions->_setAdZoneAssociations();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations));
        $this->assertTrue(empty($oAllocateZoneImpressions->aAdZoneAssociations));

        // Test 3
        $aAdverts = [];
        $oAd = new OA_Maintenance_Priority_Ad(['ad_id' => 1]);
        $aAdverts[] = $oAd;
        $oAd = new OA_Maintenance_Priority_Ad(['ad_id' => 2]);
        $aAdverts[] = $oAd;
        $oAllocateZoneImpressions->aCampaigns[0]->aAds = $aAdverts;
        $oAllocateZoneImpressions->_setAdZoneAssociations();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations));
        $this->assertTrue(empty($oAllocateZoneImpressions->aAdZoneAssociations));

        // Test 4
        $oAllocateZoneImpressions->_setAdZoneAssociations();
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations));
        $this->assertEqual(count($oAllocateZoneImpressions->aAdZoneAssociations), 1);
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][1]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][1]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][0]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][1][0]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][1][0]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][0]['zone_id']));
        $this->assertEqual($oAllocateZoneImpressions->aAdZoneAssociations[1][1][0]['zone_id'], 5);
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][1]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][1][1]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][1][1]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][1]['zone_id']));
        $this->assertEqual($oAllocateZoneImpressions->aAdZoneAssociations[1][1][1]['zone_id'], 6);
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][2]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][1][2]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][1][2]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][1][2]['zone_id']));
        $this->assertEqual($oAllocateZoneImpressions->aAdZoneAssociations[1][1][2]['zone_id'], 7);
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][2]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][2]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][2]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][2][0]));
        $this->assertTrue(is_array($oAllocateZoneImpressions->aAdZoneAssociations[1][2][0]));
        $this->assertTrue(!empty($oAllocateZoneImpressions->aAdZoneAssociations[1][2][0]));
        $this->assertTrue(isset($oAllocateZoneImpressions->aAdZoneAssociations[1][2][0]['zone_id']));
        $this->assertEqual($oAllocateZoneImpressions->aAdZoneAssociations[1][2][0]['zone_id'], 5);
    }
}
