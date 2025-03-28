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

    public function __construct()
    {
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
    public function get_active_domains_by_category(int $coursecategoryid, string $orderdir = "DESC", string $orderBy = "created_at"): array
    {
        $sql = "SELECT ccd.domain_name
                FROM {course_categories_domains} ccd
                WHERE ccd.course_categories_id = :coursecategoryid
                AND ccd.disabled_at IS NULL
                ORDER BY ccd.$orderBy $orderdir
                ";

        $params["coursecategoryid"] = $coursecategoryid;

        return $this->db->get_records_sql($sql, $params);
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
    public function delete_domain(int $coursecategoryid, string $domain_name): bool
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
        return $ismainentity && $currententity->is_manager($USER);
    }

    /**
     * Get all the entities by user domain. If no entities have been found, the default entity is return
     * 
     * @param string $domainname
     * @param mixed defaultcategory
     * @return array
     */
    public function get_course_categories_by_domain(string $domainname, \stdClass $defaultcategory,string $order = "ASC",string $orderBy = "mcc.name"): array
    {
        $sql = "SELECT mcc.*
                FROM {course_categories} mcc
                INNER JOIN {course_categories_domains} mccd on mcc.id = mccd.course_categories_id
                WHERE mccd.domain_name = ?
                 AND mccd.disabled_at IS NULL
                order by $orderBy $order
                ";
        $coursecategories = $this->db->get_records_sql($sql, [$domainname]);

        return $coursecategories ?: [$defaultcategory->id => $defaultcategory];
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
    public function add_domain(domain_name $domain)
    {
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

    /**
     * Update the users who needs to update their main entity
     * 
     * @param string $categoryname
     * @param array $users
     * @throws \moodle_exception
     * @return void
     */
    public function update_users_course_category(string $categoryname, array $users): void
    {
        [$whereclause, $params] = $this->db->get_in_or_equal(
            $users,
            SQL_PARAMS_NAMED,
            'userid'
        );

        $sql = "UPDATE {user_info_data}
                SET data = :categoryname
                WHERE userid $whereclause
                AND fieldid = (
                    SELECT id
                    FROM {user_info_field}
                    WHERE shortname = :fieldname
                    LIMIT 1
                )";
        $params['categoryname'] = $categoryname;
        $params['fieldname'] = 'mainentity';

        try {
            $this->db->execute($sql, $params);
        } catch (\dml_exception $e) {
            throw new \moodle_exception('errorupdatinguser', 'local_categories_domains', '', $e->getMessage());
        }
    }

    /**
     * Get domain by domain name
     * 
     * @param domain_name $domain The domain to check    
     */
    public function get_domain($domain)
    {
        return $this->db->get_record('course_categories_domains', ['domain_name' => $domain->domain_name, 'course_categories_id' => $domain->course_categories_id]);
    }

    /**
     * Get all domains
     * 
     * @return array
     */
    public function get_all_domains(): array
    {
        return $this->db->get_records('course_categories_domains');
    }

    public function reactivate_domain(int $coursecategoryid, string $domain_name): bool
    {
        global $DB;

        $sql = "UPDATE {course_categories_domains}
                SET disabled_at = NULL
                WHERE course_categories_id = :coursecategoryid 
                AND domain_name = :domain_name
                ";

        $params["coursecategoryid"] = $coursecategoryid;
        $params["domain_name"] = $domain_name;
        return $DB->execute($sql, $params);
    }

    public function get_all_activated_domains(): array
    {
        return $this->db->get_records('course_categories_domains', ['disabled_at' => null]);
    }

    /**
     * Get all non deleted categories domains
     * 
     */
    public function get_all_active_categories_domains(): array
    {
        $sql = "SELECT ccd.domain_name domain_name, cc.idnumber idnumber
                FROM {course_categories_domains} ccd
                JOIN {course_categories} cc ON cc.id = ccd.course_categories_id
                AND ccd.disabled_at IS NULL
                ";
        return $this->db->get_records_sql($sql);
    }

    public function get_user_link_category(int $userid)
    {
        $sql = "SELECT uid.data as categoryname
                FROM {user_info_data} uid
                INNER JOIN {user} u ON u.id = uid.userid
                WHERE u.id = :userid
                AND uid.fieldid = (
                    SELECT id
                    FROM {user_info_field}
                    WHERE shortname = :fieldname
                    LIMIT 1
                )";
        $params = [
            'userid' => $userid,
            'fieldname' => 'mainentity'
        ];

        return $this->db->get_record_sql($sql, $params);
    }
}
