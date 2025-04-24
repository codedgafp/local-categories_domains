<?php

/**
 * Tests for libclass
 *
 * @package local_categories_domains
 */
use \local_categories_domains\repository\categories_domains_repository;

defined('MOODLE_INTERNAL') || die();
global $CFG;

require_once($CFG->dirroot . '/local/categories_domains/lib.php'); 

class lib_testcase extends advanced_testcase
{
    public function test_local_categories_domains_validate_domains_csv()
    {
        global $CFG;
        $this->resetAfterTest();
        parent::setUp();

        // Test case: Empty content
        $content = [];
      
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);

        // Test case: Only one empty line
        $content = [''];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);

        // Test case: Missing required fields in header
        $content = ['wrong_field;another_field'];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);

        // Test case: Correct header but missing fields in data line
        $content = ['domain_name;idnumber', 'example.com'];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);

        // Test case: Correct header and correct idnumber and domainname
        $CFG->allowemailaddresses = 'test.com example.com .subdomain.com';
        $category = $this->getDataGenerator()->create_category(['idnumber' => 'entity1']);
        $content = ['domain_name;idnumber', '.subdomain.com;'.$category->idnumber];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertTrue($result);

        // Test case: Correct header abut not correct idnumber
        $content = ['domain_name;idnumber', 'example.com;entity2'];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);

        // Test case: Correct header and correct idnumber but wrong domainname
        $CFG->allowemailaddresses = 'test.com example.com .subdomain.com';
        $content = ['domain_name;idnumber', 'notexisteddomainname;'.$category->idnumber];
        $result = local_categories_domains_validate_domains_csv($content);
        self::assertFalse($result);


        $this->resetAfterTest();
    }


     /**
     * Test for local_categories_domains_import_domains function.
     */
    public function test_local_categories_domains_import_domains() {
        global $DB;

        $this->resetAfterTest();

        // Prepare test data.
        $content = [
            'domain_name;idnumber',
            'example.com;entity1',
            'test.com;entity2'
        ];

        // Create actual categories for testing.
        $category1 = $this->getDataGenerator()->create_category(['idnumber' => 'entity1']);
        $category2 = $this->getDataGenerator()->create_category(['idnumber' => 'entity2']);

        // Call the function.
        $result = local_categories_domains_import_domains($content);
        $this->assertTrue($result);
        // Verify the domains were added to the database.
        $domains = $DB->get_records('course_categories_domains');
        $this->assertCount(2, $domains);

        $this->assertNotEmpty(array_filter($domains, function ($domain) use ($category1) {
            return $domain->domain_name === 'example.com' && $domain->course_categories_id == $category1->id;
        }));

        $this->assertNotEmpty(array_filter($domains, function ($domain) use ($category2) {
            return $domain->domain_name === 'test.com' && $domain->course_categories_id == $category2->id;
        }));

        //Test the case where : desactivate the domains that are not vailable in the csv file
        $newContent = [
            'domain_name;idnumber',
            'example.com;entity1',
        ];
        // Call the function.
        $newResult = local_categories_domains_import_domains($newContent);
        $this->assertTrue($newResult);

        $getDomains = $DB->get_records('course_categories_domains');
        $this->assertCount(2, $getDomains);

        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category1)   {
            return $domain->domain_name === 'example.com' && $domain->course_categories_id == $category1->id && $domain->disabled_at === null;
        }));
        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category2) {
            return $domain->domain_name === 'test.com' && $domain->course_categories_id == $category2->id && $domain->disabled_at !== null;
        }));

         //Test the case where : re-activate the domains that are vailable in the csv file an dwhere disabled in DB
         $reactivateContent = [
            'domain_name;idnumber',
            'example.com;entity1',
            'test.com;entity2'
        ];

        $reactivateResult = local_categories_domains_import_domains($reactivateContent);
        $this->assertTrue($reactivateResult);

        $getDomains = $DB->get_records('course_categories_domains');
        $this->assertCount(2, $getDomains);

        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category1) {
            return $domain->domain_name === 'example.com' && $domain->course_categories_id == $category1->id && $domain->disabled_at === null;
        }));
        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category2) {
            return $domain->domain_name === 'test.com' && $domain->course_categories_id == $category2->id && $domain->disabled_at === null;
        }));

        //Test the case where : add domain to a not main entity ( should be ignored)
        // Create categorie for testing.
        $category3 = $this->getDataGenerator()->create_category(['idnumber' => 'entity3','can_be_main_entity'=>0]);
        //update the entity to be not main entity
        $entity = new \local_mentor_specialization\mentor_entity($category3->id);
        $entity->update_can_be_main_entity(false);

        $reactivateContent = [
            'domain_name;idnumber',
            'example.com;entity1',
            'test.com;entity2',
             'test.com;entity3'
        ];

        $reactivateResult = local_categories_domains_import_domains($reactivateContent);
        $this->assertTrue($reactivateResult);

        $getDomains = $DB->get_records('course_categories_domains');
        $this->assertCount(2, $getDomains);

        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category1) {
            return $domain->domain_name === 'example.com' && $domain->course_categories_id == $category1->id && $domain->disabled_at === null;
        }));
        $this->assertNotEmpty(array_filter($getDomains, function ($domain) use ($category2) {
            return $domain->domain_name === 'test.com' && $domain->course_categories_id == $category2->id && $domain->disabled_at === null;
        }));
    }


}
