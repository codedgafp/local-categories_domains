<?php

/**
 * Class categories_domains_admin_api
 *
 * @package local_categories_domains
 */

namespace local_categories_domains\repository;

defined('MOODLE_INTERNAL') || die();

use local_categories_domains\model\domain_name;

class categories_domains_repository
{
    /**
     * @var \moodle_database
     */
    protected $db;

    public function __construct() {
        global $DB;
        $this->db = $DB;
    }

    /**
     * Get all the active domains by the category ID, order by created_at column DESC by default
     * 
     * @param int $coursecategoryid
     * @param string $orderdir
     * @return array
     */
    public static function get_active_domains_by_category(int $coursecategoryid, string $orderdir = "DESC"): array
    {
        global $DB;

        $sql = "SELECT ccd.domain_name
                FROM {course_categories_domains} ccd
                WHERE ccd.course_categories_id = :coursecategoryid
                AND ccd.disabled_at IS NULL
                ORDER BY ccd.created_at $orderdir
                ";

        $params["coursecategoryid"] = $coursecategoryid;

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Delete a domain => disable it
     *  
     * @param int $coursecategoryid
     * @param string $domain_name
     * 
     * @return bool
     * @throws \moodle_exception
     */
    public function delete_domain(int $coursecategoryid,string  $domain_name): bool
    {
        global $DB;

        $sql = "UPDATE {course_categories_domains}
                SET disabled_at = :disabled_at
                WHERE course_categories_id = :coursecategoryid 
                  AND domain_name = :domain_name
                ";

        $params["coursecategoryid"] = $coursecategoryid;
        $params["disabled_at"] = time();
        $params["domain_name"] = $domain_name;

        try {
            return $DB->execute($sql, $params);
        } catch (\dml_exception $e) {
            throw new \moodle_exception('errordeletingdomain', 'local_categories_domains', '', $e->getMessage());
        }
    }



    
    /**
     * Check if the user is authorized to manage domains.
     * 
     * @param int $coursecategoryid
     */
    public function admindedie_can_manage_domains(int $coursecategoryid): bool
    {
        global $USER;
        
        $currententity = \local_mentor_core\entity_api::get_entity($coursecategoryid);
        $ismainentity = $currententity->is_main_entity();
       // User must be a manager of the current main entity
        return  $ismainentity &&  $currententity->is_manager($USER);
    }


    /**
     * Check if the domain already exists for the same entity
     * 
     * @param domain_name $domain The domain name to check
     * @return bool True if domain exists
     */
    public function is_domain_exists(domain_name $domain): bool
    {
            return $this->db->record_exists_sql(
                "SELECT 1 FROM {course_categories_domains} 
                WHERE domain_name = :domainname
                AND course_categories_id = :entity
                AND disabled_at IS NULL",
                ['domainname' => $domain->domain_name, 'entity' => $domain->course_categories_id]
            );
    }

    /**
     * Insert domain name into database
     * @param domain_name $domain The domain name to check
     * @return bool
     */
    public function add_domain(domain_name $domain){
        return $this->db->insert_record_raw('course_categories_domains', $domain, false);
    }


        /**
     * Check if the domain already exists for the same entity and disabled
     * 
     * @param domain_name $domain The domain name to check
     * @return bool True if domain exists
     */
    public function is_domain_disabled(domain_name $domain): bool
    {
            return $this->db->record_exists_sql(
                "SELECT 1 FROM {course_categories_domains} 
                WHERE domain_name = :domainname
                AND course_categories_id = :entity
                AND disabled_at IS NOT NULL",
                ['domainname' => $domain->domain_name, 'entity' => $domain->course_categories_id]
            );
    }

    public function reactivate_domain(domain_name $domain): bool
    {

        global $DB;

        $sql = "UPDATE {course_categories_domains}
                SET disabled_at = null
                WHERE course_categories_id = :coursecategoryid 
                  AND domain_name = :domain_name
                ";

        $params["coursecategoryid"] = $domain->course_categories_id;
        $params["domain_name"] = $domain->domain_name;

        try {
            return $DB->execute($sql, $params);
        } catch (\dml_exception $e) {
            throw new \moodle_exception('errordeletingdomain', 'local_categories_domains', '', $e->getMessage());
        }
    }
}
