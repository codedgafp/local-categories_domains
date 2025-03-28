<?php
/**
 * Tests for dbinterface class
 *
 * @package local_categories_domains
 */



defined('MOODLE_INTERNAL') || die();
use \local_categories_domains\repository\categories_domains_repository;
use \local_categories_domains\model\domain_name;
global $CFG;

require_once "$CFG->dirroot/local/categories_domains/classes/repository/categories_domains_repository.php";
require_once "$CFG->dirroot/local/categories_domains/classes/model/domain_name.php";

class local_categories_domains_repository_testcase extends advanced_testcase
{
    private $db;
    private categories_domains_repository $categoriesdomainsrepository;

    /**
     * Setup test
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();
        $this->categoriesdomainsrepository = new categories_domains_repository();

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
     * @covers \local_categories_domains\categories_domains_repository::get_active_domains_by_category
     */
    public function test_get_active_domains_with_valid_category() {
        $category = $this->getDataGenerator()->create_category();
        
        $domain1 = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'domain1.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $domain2 = (object)[
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
     * @covers \local_categories_domains\categories_domains_repository::get_active_domains_by_category
     */
    public function test_get_active_domains_with_non_existent_category() {
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
     * @covers \local_categories_domains\categories_domains_repository::get_active_domains_by_category
     */
    public function test_get_active_domains_with_disabled_domains() {
        $category = $this->getDataGenerator()->create_category();
        
        $activeDomain = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'active.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $disabledDomain = (object)[
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
    public function test_get_course_categories_by_domain_with_existing_domain() {
        // Prepare test data
        $category = $this->getDataGenerator()->create_category();

        $domainname = 'testdomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();
        
        // Insert a test record
        $this->db->execute(
            "INSERT INTO {course_categories_domains} 
            (domain_name, course_categories_id, created_at) 
            VALUES (?, ?, ?)",
            [$domainname, $category->id, time()]
        );

        // Call the method
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentity);
        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($category->id, reset($result)->id);

        $this->resetAllData();
    }

    /**
     * Test retrieving entities when domain does not exist
     */
    public function test_get_course_categories_by_domain_with_non_existing_domain() {
        // Prepare test data
        $domainname = 'nonexistentdomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();

        // Call the method
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentity);

        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($defaultmainentity, reset($result));

        $this->resetAllData();
    }

    /**
     * Test retrieving entities with multiple records for a domain
     */
    public function test_get_course_categories_by_domain_with_multiple_records() {
        // Prepare test data
        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $domainname = 'multidomain.com';
        $defaultmainentity = $this->getDataGenerator()->create_category();
        
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
        $result = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultmainentity);

        // Assertions
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Verify each record
        $entityids = array_map(function($item) { return $item->id; }, $result);
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
     * @covers \local_categories_domains\categories_domains_repository::delete_domain
     */
    public function test_delete_domain() {
        $category = $this->getDataGenerator()->create_category();
        
        $domain = (object)[
            'course_categories_id' => $category->id,
            'domain_name' => 'domain.com',
            'created_at' => time(),
            'disabled_at' => null
        ];
        $this->db->insert_record('course_categories_domains', $domain, false);

        $domainslist = $this->categoriesdomainsrepository->get_active_domains_by_category($category->id);
        $result = $this->categoriesdomainsrepository->delete_domain($category->id,$domainslist['domain.com']->domain_name);
        $this->assertTrue($result);

        $deletedDomain = $this->db->get_record('course_categories_domains', ['domain_name' =>$domainslist['domain.com']->domain_name, 'course_categories_id' => $category->id]);
        $this->assertEquals($deletedDomain->disabled_at, time());
    }

    /**
     * Test add domain name ok
     */
    public function test_successful_domain_addition_ok() {
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
    public function test_domain_existence_true() {
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
    public function test_domain_existence_false() {
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
    public function test_domain_whitelisting() {
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
     * @covers \local_categories_domains\categories_domains_repository::get_active_domains_by_category
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

        $result = categories_domains_repository::get_active_domains_by_category($category->id, "ASC", "domain_name");
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
        $result = categories_domains_repository::get_active_domains_by_category($category->id);
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

        $result = $this->categoriesDomainsRepository->is_domain_disabled($domain);

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

        $result = $this->categoriesDomainsRepository->is_domain_disabled($domain);

        $this->assertFalse($result);
    }

    /**
     * Test reactivate_domain reactivates a disabled domain
     *
     * @throws dml_exception
     */
    public function test_reactivate_domain() {
        // Create a category.
        $category = $this->getDataGenerator()->create_category();

        // Insert a disabled domain.
        $domain = new domain_name();
        $domain->course_categories_id = $category->id;
        $domain->domain_name = 'disabled.com';
        $domain->created_at = time();
        $domain->disabled_at = time();

        $this->db->insert_record('course_categories_domains', $domain, false);

        // Reactivate the domain.
        $result = $this->categoriesDomainsRepository->reactivate_domain($domain);

        // Assert the reactivation was successful.
        $this->assertTrue($result);

        // Fetch the domain from the database.
        $reactivatedDomain = $this->db->get_record('course_categories_domains', ['domain_name' => $domain->domain_name]);

        // Assert the domain is no longer disabled.
        $this->assertNull($reactivatedDomain->disabled_at);
    }


}
