<?php
/**
 * Tests for dbinterface class
 *
 * @package local_categories_domains
 */

use \local_categories_domains\categories_domains_repository;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once "$CFG->dirroot/local/categories_domains/classes/repository/categories_domains_repository.php";

class local_categories_domains_repository_testcase extends advanced_testcase
{
    private $db;
    private $categoriesDomainsRepository;

    /**
     * Setup test
     */
    protected function setUp(): void {
        parent::setUp();

        $this->resetAfterTest();

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

        $result = categories_domains_repository::get_active_domains_by_category($category->id);

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
        $result = categories_domains_repository::get_active_domains_by_category(99999);
        
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

        $result = categories_domains_repository::get_active_domains_by_category($category->id);

        $this->assertCount(1, $result);
        $this->assertTrue(in_array('active.com', array_column($result, 'domain_name')));
        $this->assertFalse(in_array('disabled.com', array_column($result, 'domain_name')));

        $this->resetAllData();
    }
}
