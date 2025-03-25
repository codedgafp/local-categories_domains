<?php

/**
 * Tests for libclass
 *
 * @package local_categories_domains
 */
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
    }
}
