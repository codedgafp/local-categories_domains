<?php

/**
 * Tests for dbinterface class
 *
 * @package local_categories_domains
 */

defined('MOODLE_INTERNAL') || die();

use \local_categories_domains\repository\categories_domains_repository;
use local_categories_domains\utils\categories_domains_service;
use \local_categories_domains\model\domain_name;
use \local_mentor_specialization\mentor_entity;
use \local_mentor_core\profile_api;
use \local_mentor_core\database_interface;

global $CFG;
require_once "$CFG->dirroot/local/categories_domains/classes/repository/categories_domains_repository.php";
require_once "$CFG->dirroot/local/categories_domains/classes/model/domain_name.php";

class local_categories_domains_repository_testcase extends advanced_testcase
{
    private $db;
    private categories_domains_repository $categoriesdomainsrepository;
    private categories_domains_service $categoriesdomainsservice;
    private database_interface $mentorcoredbi;

    /**
     * Setup test
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAfterTest();

        $this->categoriesdomainsrepository = new categories_domains_repository();
        $this->categoriesdomainsservice = new categories_domains_service();
        $this->mentorcoredbi = database_interface::get_instance();

        global $DB;
        $this->db = $DB;
    }

    /**
     * Test when their is domains and valid category, and it exist
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_domains_with_valid_category()
    {
        $category = $this->getDataGenerator()->create_category();

        $domain1 = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'domain1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $domain2 = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'domain2.com',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $domain1, false);
        $this->db->insert_record('course_categories_domains', $domain2, false);

        $result = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id);

        $this->assertCount(2, $result);
        $this->assertTrue(in_array('domain1.com', array_column($result, 'domain_name')));
        $this->assertTrue(in_array('domain2.com', array_column($result, 'domain_name')));
    }

    /**
     * Test when a non existent category
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_domains_with_non_existent_category()
    {
        $result = $this->categoriesdomainsrepository->get_active_domains_by_category(99999);

        $this->assertEmpty($result);
    }

    /**
     * Test when their is domain and category with a disabled_at not null
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_domains_with_disabled_domains()
    {
        $category = $this->getDataGenerator()->create_category();

        $activeDomain = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'active.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $disabledDomain = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'disabled.com',
            'created_at' => time(),
            'disabled_at' => time()
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain, false);
        $this->db->insert_record('course_categories_domains', $disabledDomain, false);

        $result = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id);

        $this->assertCount(1, $result);
        $this->assertTrue(in_array('active.com', array_column($result, 'domain_name')));
        $this->assertFalse(in_array('disabled.com', array_column($result, 'domain_name')));

        $this->resetAllData();
    }

    /**
     * Test retrieving entities when domain exists
     */
    public function test_get_course_categories_by_domain_with_existing_domain()
    {
        // Prepare test data
        $category = $this->getDataGenerator()->create_category();

        $domainname = 'testdomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();
        $defaultmainentityStdClass = (object) [
            'id' => $defaultmainentity->id,
            'name' => $defaultmainentity->name,
            'description' => $defaultmainentity->description,
            'parent' => $defaultmainentity->parent
        ];
        // Insert a test record
        $this->db->execute(
            "INSERT INTO {course_categories_domains} 
            (domain_name, course_categories_id, created_at) 
            VALUES (?, ?, ?)",
            [$domainname, $category->id, time()]
        );

        // Call the method
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentityStdClass);
        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($category->id, reset($result)->id);

        $this->resetAllData();
    }

    /**
     * Test retrieving entities when domain does not exist
     */
    public function test_get_course_categories_by_domain_with_non_existing_domain()
    {
        // Prepare test data
        $domainname = 'nonexistentdomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();

        $defaultmainentityStdClass = (object) [
            'id' => $defaultmainentity->id,
            'name' => $defaultmainentity->name,
            'description' => $defaultmainentity->description,
            'parent' => $defaultmainentity->parent
        ];
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentityStdClass);

        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($defaultmainentityStdClass->id, reset($result)->id);

        $this->resetAllData();
    }

    /**
     * Test retrieving entities with multiple records for a domain
     */
    public function test_get_course_categories_by_domain_with_multiple_records()
    {
        // Prepare test data
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $domainname = 'multidomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();
        $defaultmainentityStdClass = (object) [
            'id' => $defaultmainentity->id,
            'name' => $defaultmainentity->name,
            'description' => $defaultmainentity->description,
            'parent' => $defaultmainentity->parent
        ];
        // Insert multiple test records
        $this->db->execute(
            "INSERT INTO {course_categories_domains} 
            (domain_name, course_categories_id, created_at) 
            VALUES (?, ?, ?)",
            [$domainname, $category1->id, time()]
        );

        $this->db->execute(
            "INSERT INTO {course_categories_domains} 
            (domain_name, course_categories_id, created_at) 
            VALUES (?, ?, ?)",
            [$domainname, $category2->id, time()]
        );

        // Call the method
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentityStdClass);

        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        // Verify each record
        $entityids = array_map(function ($item) {
            return $item->id;
        }, $result);
        $this->assertContains($category1->id, $entityids);
        $this->assertContains($category2->id, $entityids);

        $this->resetAllData();
    }

    /**
     * Test deleting a domain
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_delete_domain()
    {
        $category = $this->getDataGenerator()->create_category();

        $domain = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'domain.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $this->db->insert_record('course_categories_domains', $domain, false);

        $domainslist = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id);
        $result = $this->categoriesdomainsrepository->delete_domain($category->id, $domainslist['domain.com']->domain_name);
        $this->assertTrue($result);

        $deletedDomain = $this->db->get_record('course_categories_domains', ['domain_name' => $domainslist['domain.com']->domain_name, 'course_categories_id' => $category->id]);
        $this->assertEquals($deletedDomain->disabled_at, time());
    }

    /**
     * Test add domain name ok
     */
    public function test_successful_domain_addition_ok()
    {
        global $DB;

        $category = $this->getDataGenerator()->create_category();

        $repo = $this->categoriesdomainsrepository;

        $domain = new domain_name();
        $domain->domain_name = 'test.com';
        $domain->course_categories_id = $category->id;

        $result = $repo->add_domain($domain);

        $this->assertTrue($result);

        $domains = $DB->get_records('course_categories_domains', [
            'course_categories_id' => $category->id,
            'domain_name' => 'test.com'
        ]);

        $this->assertCount(1, $domains);
    }

    /**
     * Test domain existence True
     */
    public function test_domain_existence_true()
    {
        $category = $this->getDataGenerator()->create_category();

        $repo = $this->categoriesdomainsrepository;

        $domain = new domain_name();
        $domain->domain_name = 'test.com';
        $domain->course_categories_id = $category->id;

        $repo->add_domain($domain);

        $exists = $repo->is_domain_exists($domain);

        $this->assertTrue($exists);
    }

    /**
     * Test domain existence False
     */
    public function test_domain_existence_false()
    {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();

        $repo = $this->categoriesdomainsrepository;

        $domain = new domain_name();
        $domain->domain_name = 'test_does_not_exist.com';
        $domain->course_categories_id = $category->id;

        $exists = $repo->is_domain_exists($domain);

        $this->assertFalse($exists);
    }

    /**
     * Test domain whitelisting
     */
    public function test_domain_whitelisting()
    {
        global $CFG;

        $CFG->allowemailaddresses = 'test.com example.com .subdomain.com';

        $domain1 = new domain_name();
        $domain1->domain_name = 'test.com';
        $this->assertTrue($domain1->is_whitelisted());

        $domain2 = new domain_name();
        $domain2->domain_name = 'sub.subdomain.com';
        $this->assertTrue($domain2->is_whitelisted());

        $domain3 = new domain_name();
        $domain3->domain_name = 'notwhitelisted.com';
        $this->assertFalse($domain3->is_whitelisted());

        $domain4 = new domain_name();
        $domain4->domain_name = 'subdomain.com.com';
        $this->assertFalse($domain4->is_whitelisted());
    }
    /**
     * Test get_active_domains_by_category with order by domain_name
     *
     * @throws ReflectionException
     * @throws coding_exception
     * @throws dml_exception
     * @throws moodle_exception
     */
    public function test_get_active_domains_order_by_domain_name() {
        $this->resetAllData();
        $category = $this->getDataGenerator()->create_category();
        
        $domain1 = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'zdomain.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $domain2 = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'adomain.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        
        $this->db->insert_record('course_categories_domains', $domain1, false);
        $this->db->insert_record('course_categories_domains', $domain2, false);

        $result = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id, "ASC", "domain_name");
        $this->assertCount(2, $result);
        $this->assertEquals('adomain.com', $result["adomain.com"]->domain_name);
        $this->assertEquals('zdomain.com', $result["zdomain.com"]->domain_name);

        //by default order by created_at desc
        $domain3 = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'sdomain.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        
        $this->db->insert_record('course_categories_domains', $domain3, false);
        $result = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id);
        $this->assertCount(3, $result);
        $this->assertEquals('sdomain.com', $result["sdomain.com"]->domain_name);
        $this->assertEquals('adomain.com', $result["adomain.com"]->domain_name);
        $this->assertEquals('zdomain.com', $result["zdomain.com"]->domain_name);

        $this->resetAllData();
    }


        /**
     * Test is_domain_disabled returns true for a disabled domain
     *
     * @throws dml_exception
     */
    public function test_is_domain_disabled_true() {
        $category = $this->getDataGenerator()->create_category();

        $domain = new domain_name();
        $domain->course_categories_id = $category->id;
        $domain->domain_name = 'disabled.com';
        $domain->created_at = time();
        $domain->disabled_at = time();

        $this->db->insert_record('course_categories_domains', $domain, false);

        $result = $this->categoriesdomainsrepository->is_domain_disabled($domain);

        $this->assertTrue($result);
    }

    /**
     * Test is_domain_disabled returns false for an active domain
     *
     * @throws dml_exception
     */
    public function test_is_domain_disabled_false() {
        $category = $this->getDataGenerator()->create_category();

        $domain = new domain_name();
        $domain->course_categories_id = $category->id;
        $domain->domain_name = 'active.com';
        $domain->created_at = time();
        $domain->disabled_at = null;
        
        $this->db->insert_record('course_categories_domains', $domain, false);

        $result = $this->categoriesdomainsrepository->is_domain_disabled($domain);

        $this->assertFalse($result);
    }

    /**
     * Test get active domains by search value
     */
    public function test_get_active_domains_by_serach_value()
    {
        $category = $this->getDataGenerator()->create_category();

        $domain1 = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'domain1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $domain2 = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'domain2.com',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $domain1, false);
        $this->db->insert_record('course_categories_domains', $domain2, false);

        $result = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id, 'DESC', 'domain_name', 'domain1');

        $this->assertCount(1, $result);
        $this->assertTrue(in_array('domain1.com', array_column($result, 'domain_name')));
        $this->assertFalse(in_array('domain2.com', array_column($result, 'domain_name')));
    }

    /**
     * Test get all domains
     */
    public function test_get_all_domains()
    {
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $domain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'domain1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $domain2 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => 'domain2.com',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $domain1, false);
        $this->db->insert_record('course_categories_domains', $domain2, false);

        $result = $this->categoriesdomainsrepository->get_all_domains();

        $this->assertCount(2, $result);
        $this->assertTrue(in_array('domain1.com', array_column($result, 'domain_name')));
        $this->assertTrue(in_array('domain2.com', array_column($result, 'domain_name')));
    }

    /**
     * Test reactivating a domain
     */
    public function test_reactivate_domain()
    {
        $category = $this->getDataGenerator()->create_category();

        $domain = (object) [
            'course_categories_id' => $category->id,
            'domain_name' => 'reactivate.com',
            'created_at' => time(),
            'disabled_at' => time()
        ];
        $this->db->insert_record('course_categories_domains', $domain, false);

        $result = $this->categoriesdomainsrepository->reactivate_domain($category->id, 'reactivate.com');
        $this->assertTrue($result);

        $reactivatedDomain = $this->db->get_record('course_categories_domains', ['domain_name' => 'reactivate.com', 'course_categories_id' => $category->id]);
        $this->assertNull($reactivatedDomain->disabled_at);
    }

    /**
     * Test get all activated domains
     */
    public function test_get_all_activated_domains()
    {
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'active1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => 'active2.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $disabledDomain = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'disabled.com',
            'created_at' => time(),
            'disabled_at' => time()
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);
        $this->db->insert_record('course_categories_domains', $disabledDomain, false);

        $result = $this->categoriesdomainsrepository->get_all_activated_domains();

        $this->assertCount(2, $result);
        $this->assertTrue(in_array('active1.com', array_column($result, 'domain_name')));
        $this->assertTrue(in_array('active2.com', array_column($result, 'domain_name')));
        $this->assertFalse(in_array('disabled.com', array_column($result, 'domain_name')));
    }

    public function test_link_categories_to_users_ok()
    {
        global $CFG;

        $CFG->allowemailaddresses = '.archi.fr .interieur.gouv.fr ira-nantes.fr';

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => '.archi.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain3 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);
        $this->db->insert_record('course_categories_domains', $activeDomain3, false);

        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@user.archi.fr']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'user1@user.interieur.gouv.fr']);
        $user3 = $this->getDataGenerator()->create_user(['email' => 'user1@ira-nantes.fr']);
        $user4 = $this->getDataGenerator()->create_user(['email' => 'user1@user.baddomain.fr']);

        $externalrole = $this->db->get_record('role', ['shortname' => 'utilisateurexterne']);

        $user1linkentity = $this->categoriesdomainsrepository->get_user_link_category($user1->id);
        $this->assertEquals($category1->name, $user1linkentity->categoryname);

        $user2linkentity = $this->categoriesdomainsrepository->get_user_link_category($user2->id);
        $this->assertEquals(false, $user2linkentity);

        $defaultcategory = mentor_entity::get_default_entity();
        $user3linkentity = $this->categoriesdomainsrepository->get_user_link_category($user3->id);
        $this->assertEquals($defaultcategory->name, $user3linkentity->categoryname);

        $user4linkentity = $this->categoriesdomainsrepository->get_user_link_category($user4->id);
        $this->assertEquals($defaultcategory->name, $user4linkentity->categoryname);
        $isuser4external = $this->db->get_record('role_assignments', ['userid' => $user4->id, 'roleid' => $externalrole->id]);
        $this->assertTrue(!empty($isuser4external));
    }

    public function test_link_categories_to_users_with_main_category()
    {
        global $CFG;

        $this::setAdminUser();

        $CFG->allowemailaddresses = '.archi.fr .interieur.gouv.fr ira-nantes.fr';

        $categoryname1 = 'category1';
        $categoryname2 = 'category2';
        $categoryid1 = \local_mentor_core\entity_api::create_entity(['name' => $categoryname1, 'shortname' => $categoryname1]);
        $categoryid2 = \local_mentor_core\entity_api::create_entity(['name' => $categoryname2, 'shortname' => $categoryname2]);

        $maincategory = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $categoryid1,
            'domain_name' => '.archi.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $categoryid1,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain3 = (object) [
            'course_categories_id' => $categoryid2,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);
        $this->db->insert_record('course_categories_domains', $activeDomain3, false);

        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@user.archi.fr']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'user2@user.archi.fr']);
        $user3 = $this->getDataGenerator()->create_user(['email' => 'user3@user.interieur.gouv.fr']);
        $user4 = $this->getDataGenerator()->create_user(['email' => 'user4@ira-nantes.fr']);
        $user5 = $this->getDataGenerator()->create_user(['email' => 'user5@user.baddomain.fr']);

        // Reset all linked users categories to empty
        $userstoreset = [$user1->id, $user2->id, $user3->id, $user4->id, $user5->id];
        $this->categoriesdomainsrepository->update_users_course_category("", $userstoreset);

        $externalrole = $this->db->get_record('role', ['shortname' => 'utilisateurexterne']);

        $userstotest = [$user1, $user2, $user3, $user4, $user5];
        $this->categoriesdomainsservice->link_categories_to_users($userstotest, $maincategory);

        $user1linkentity = $this->categoriesdomainsrepository->get_user_link_category($user1->id);
        $this->assertEquals($categoryname1, $user1linkentity->categoryname);

        $user2linkentity = $this->categoriesdomainsrepository->get_user_link_category($user2->id);
        $this->assertEquals($categoryname1, $user2linkentity->categoryname);

        $user3linkentity = $this->categoriesdomainsrepository->get_user_link_category($user3->id);
        $this->assertEquals(false, $user3linkentity);

        $defaultcategory = mentor_entity::get_default_entity();
        $user4linkentity = $this->categoriesdomainsrepository->get_user_link_category($user4->id);
        $this->assertEquals($defaultcategory->name, $user4linkentity->categoryname);

        $user5linkentity = $this->categoriesdomainsrepository->get_user_link_category($user5->id);
        $this->assertEquals($maincategory->name, $user5linkentity->categoryname);
        $isuser5external = $this->db->get_record('role_assignments', ['userid' => $user5->id, 'roleid' => $externalrole->id]);
        $this->assertTrue(!empty($isuser5external));
    }

    public function test_link_categories_to_users_no_users()
    {
        global $CFG;

        $CFG->allowemailaddresses = '.archi.fr .interieur.gouv.fr ira-nantes.fr';

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => '.archi.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain3 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => '.interieur.gouv.fr',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);
        $this->db->insert_record('course_categories_domains', $activeDomain3, false);

        $result = $this->categoriesdomainsservice->link_categories_to_users([]);

        $this->assertTrue($result === true);
    }

    /**
     * Test linking users to course categories (entities) based on their emails.
     * Case: Many course-categories could be set to the user, the user has an invalid entity
     */
    public function test_link_categories_to_users_multiple_choices_invalid_enity()
    {
        global $CFG;

        $CFG->allowemailaddresses = 'test1.com test2.com';

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'test1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => 'test1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);

        // link_categories_to_users() function trigged by observer on user created
        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@test1.com']);

        $this->categoriesdomainsrepository->update_users_course_category($category1->name, [$user1->id]);

        $user1linkentity = $this->categoriesdomainsrepository->get_user_link_category($user1->id);

        $this->assertEquals($category1->name, $user1linkentity->categoryname);
    }

    /**
     * Test linking users to course categories (entities) based on their emails.
     * Case: Many course-categories could be set to the user, the user alreadty has a valid entity
     */
    public function test_link_categories_to_users_multiple_choices_valid_enity()
    {
        global $CFG;

        $CFG->allowemailaddresses = 'test1.com test2.com';

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $activeDomain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'test1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $activeDomain2 = (object) [
            'course_categories_id' => $category2->id,
            'domain_name' => 'test1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];

        $this->db->insert_record('course_categories_domains', $activeDomain1, false);
        $this->db->insert_record('course_categories_domains', $activeDomain2, false);

        // link_categories_to_users() function trigged by observer on user created
        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@test1.com']);

        $user1linkentity = $this->categoriesdomainsrepository->get_user_link_category($user1->id);
        $this->assertEquals(false, $user1linkentity);
    }

    /**
     * Test get_user_cohort_categories_name
     */
    public function test_get_user_cohort_categories_name_request()
    {
        global $CFG;

        $CFG->allowemailaddresses = 'mail.com';

        $category1 = $this->getDataGenerator()->create_category();
        $domain1 = (object) [
            'course_categories_id' => $category1->id,
            'domain_name' => 'mail.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $this->db->insert_record('course_categories_domains', $domain1, false);

        $user = $this->getDataGenerator()->create_user(['email' => 'user@mail.com']);

        $usercohorts = $this->categoriesdomainsrepository->get_user_cohort_categories_name($user->id);

        $this->assertCount(1, $usercohorts);
        $this->assertTrue(in_array($category1->name, $usercohorts));

        $profile = profile_api::get_profile($user);
        foreach ($profile->get_entities_cohorts() as $usercohort) {
            $this->mentorcoredbi->remove_cohort_member($usercohort->cohortid, $user->id);
        }

        $usercohorts = $this->categoriesdomainsrepository->get_user_cohort_categories_name($user->id);

        $this->assertCount(0, $usercohorts);
        $this->assertFalse(in_array($category1->name, $usercohorts));
    }

    public function test_get_all_users_by_domain_name()
    {
        $this->db->delete_records('user');

        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@mail.com']);
        $user2 = $this->getDataGenerator()->create_user(['email' => 'user2@mail.com']);
        $user3 = $this->getDataGenerator()->create_user(['email' => 'user3@domain.mail.com']);
        $user4 = $this->getDataGenerator()->create_user(['email' => 'user4@sous.domain.mail.com']);

        // === Get users by their domain ===
        $getusermailcom = $this->categoriesdomainsrepository->get_all_users_by_domain_name('mail.com');
        $this->assertCount(2, $getusermailcom);
        $this->assertTrue(in_array($user1->id, array_column($getusermailcom, 'id')));
        $this->assertTrue(in_array($user2->id, array_column($getusermailcom, 'id')));
        $this->assertFalse(in_array($user3->id, array_column($getusermailcom, 'id')));
        $this->assertFalse(in_array($user4->id, array_column($getusermailcom, 'id')));

        $getuserdomainmailcom = $this->categoriesdomainsrepository->get_all_users_by_domain_name('domain.mail.com');
        $this->assertCount(1, $getuserdomainmailcom);
        $this->assertFalse(in_array($user1->id, array_column($getuserdomainmailcom, 'id')));
        $this->assertFalse(in_array($user2->id, array_column($getuserdomainmailcom, 'id')));
        $this->assertTrue(in_array($user3->id, array_column($getuserdomainmailcom, 'id')));
        $this->assertFalse(in_array($user4->id, array_column($getuserdomainmailcom, 'id')));

        $getusersousdomainmailcom = $this->categoriesdomainsrepository->get_all_users_by_domain_name('sous.domain.mail.com');
        $this->assertCount(1, $getusersousdomainmailcom);
        $this->assertFalse(in_array($user1->id, array_column($getusersousdomainmailcom, 'id')));
        $this->assertFalse(in_array($user2->id, array_column($getusersousdomainmailcom, 'id')));
        $this->assertFalse(in_array($user3->id, array_column($getusersousdomainmailcom, 'id')));
        $this->assertTrue(in_array($user4->id, array_column($getusersousdomainmailcom, 'id')));

        // === Get only valid users ===
        $user5 = $this->getDataGenerator()->create_user(['email' => 'user5@mail.com']);
        $recorduser5 = new \stdClass;
        $recorduser5->id = $user5->id;
        $recorduser5->confirmed = 0;
        $this->db->update_record('user', $recorduser5);

        $user6 = $this->getDataGenerator()->create_user(['email' => 'user6@mail.com']);
        $recorduser6 = new \stdClass;
        $recorduser6->id = $user6->id;
        $recorduser6->deleted = 1;
        $this->db->update_record('user', $recorduser6);

        $getvalidusers = $this->categoriesdomainsrepository->get_all_users_by_domain_name('mail.com', true);
        $this->assertCount(2, $getvalidusers);
        $this->assertTrue(in_array($user1->id, array_column($getvalidusers, 'id')));
        $this->assertTrue(in_array($user2->id, array_column($getvalidusers, 'id')));
        $this->assertFalse(in_array($user5->id, array_column($getvalidusers, 'id')));
        $this->assertFalse(in_array($user6->id, array_column($getvalidusers, 'id')));

        $getvalidusersmail = $this->categoriesdomainsrepository->get_all_users_by_domain_name('.mail.com', true);
        $this->assertCount(2, $getvalidusersmail);
        $this->assertTrue(in_array($user3->id, array_column($getvalidusersmail, 'id')));
        $this->assertTrue(in_array($user4->id, array_column($getvalidusersmail, 'id')));
        $this->assertFalse(in_array($user5->id, array_column($getvalidusersmail, 'id')));
        $this->assertFalse(in_array($user6->id, array_column($getvalidusersmail, 'id')));
    }

    public function test_get_users_without_main_entity()
    {
        $this->db->delete_records('user');

        $user1 = $this->getDataGenerator()->create_user(['email' => 'user1@mail.com']);
        $this->categoriesdomainsrepository->update_users_course_category('', [$user1->id]);

        $user2 = $this->getDataGenerator()->create_user(['email' => 'user2@mail.com']);
        $usermainentityfield = $this->db->get_record('user_info_field', ['shortname' => 'mainentity']);
        $usermainentitydata = $this->db->get_record('user_info_data', ['userid' => $user2->id, 'fieldid' => $usermainentityfield->id]);
        $this->db->delete_records('user_info_data', ['id' => $usermainentitydata->id]);

        $user3 = $this->getDataGenerator()->create_user(['email' => 'user3@mail.com']);

        $getinvalidmainentity = $this->categoriesdomainsrepository->get_users_without_main_entity();
        $this->assertCount(2, $getinvalidmainentity);
        $this->assertTrue(in_array($user1->id, array_column($getinvalidmainentity, 'id')));
        $this->assertTrue(in_array($user2->id, array_column($getinvalidmainentity, 'id')));
        $this->assertFalse(in_array($user3->id, array_column($getinvalidmainentity, 'id')));
    }
}
