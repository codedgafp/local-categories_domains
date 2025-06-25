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
    public function get_active_domains_by_category(int $coursecategoryid, string $orderdir = "DESC", string $orderBy = "created_at", ?string $search = null): array
    {
        $sql = "SELECT ccd.domain_name
                FROM {course_categories_domains} ccd
                WHERE ccd.course_categories_id = :coursecategoryid
                AND ccd.disabled_at IS NULL
                ";
        $params["coursecategoryid"] = $coursecategoryid;

        if (!empty($search) && mb_strlen(trim($search)) > 0) {
            $sql .= $this->apply_search_conditions($params, $search);
        }

        $sql .= "ORDER BY ccd.$orderBy $orderdir";

        return $this->db->get_records_sql($sql, $params);
    }

    private function apply_search_conditions(array &$params, string $search): string
    {
        $searchvalue = trim($search);
        $searchvalue = str_replace("&#39;", "\'", $searchvalue);

        // Limit search length
        if (mb_strlen($searchvalue) > 100) {
            $searchvalue = mb_substr($searchvalue, 0, 100);
        }
        // Add a cleaning param layer 
        $searchvalue = clean_param($searchvalue, PARAM_TEXT);
        $listsearchvalue = explode(" ", $searchvalue);

        $searchConditions = [];

        foreach ($listsearchvalue as $key => $partsearchvalue) {
            if (!$partsearchvalue) {
                continue;
            }
            $domainSearchConditions = [
                $this->db->sql_like('ccd.domain_name', ':domainname' . $key, false, false)
            ];
            $searchConditions[] = '(' . implode(' OR ', $domainSearchConditions) . ')';
            $params["domainname" . $key] = '%' . $this->db->sql_like_escape($partsearchvalue) . '%';
        }

        return (!empty($searchConditions)) ? ' AND (' . implode(' AND ', $searchConditions) . ')' : "";
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
     * @param \stdClass $defaultcategory
     * @param string $order
     * @param string $orderBy
     * @return array
     */
    public function get_course_categories_by_domain(string $domainname, \stdClass $defaultcategory, string $order = "ASC", string $orderBy = "mcc.name"): array
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
     * Check if the domain already exists, can link an entity
     * 
     * @param domain_name $domain The domain name to check
     * @return bool True if domain exists
     */
    public function is_domain_exists(domain_name $domain): bool
    {
        $whereclause = "";
        if (isset($domain->course_categories_id)) {
            $whereclause = " AND course_categories_id = :entity";
            $params['entity'] = $domain->course_categories_id;
        }

        $sql = "SELECT 1 FROM {course_categories_domains} 
                WHERE domain_name = :domainname
                AND disabled_at IS NULL
                $whereclause
                ";

        $params['domainname'] = $domain->domain_name;

        return $this->db->record_exists_sql($sql, $params);
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
        if (empty($users))
            return;

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
                SET created_at = :createdat,
                    disabled_at = NULL
                WHERE course_categories_id = :coursecategoryid 
                AND domain_name = :domain_name
                ";

        $params["createdat"] = time();
        $params["coursecategoryid"] = $coursecategoryid;
        $params["domain_name"] = $domain_name;
        return $DB->execute($sql, $params);
    }

    public function get_all_activated_domains(): array
    {
        $sql = "SELECT ROW_NUMBER() OVER (ORDER BY domain_name ASC, course_categories_id ASC) AS line,
                    domain_name domain_name, 
                    course_categories_id course_categories_id
                FROM {course_categories_domains}
                WHERE disabled_at IS NULL
        ";

        return array_values($this->db->get_records_sql($sql));
    }

    /**
     * Get all non deleted categories domains
     *
     */
    public function get_all_active_categories_domains(): array
    {
        $sql = "SELECT 
        ROW_NUMBER() OVER (ORDER BY ccd.domain_name ASC, ccd.course_categories_id ASC) AS ligne,
        ccd.domain_name domain_name, cc.idnumber idnumber
                FROM {course_categories_domains} ccd
                JOIN {course_categories} cc ON cc.id = ccd.course_categories_id 
                WHERE ccd.disabled_at IS NULL
                ";
        return array_values($this->db->get_records_sql($sql));
    }

    /**
     * Get the category name linked to a user
     * 
     * @param int $userid
     * @return \stdClass|bool
     */
    public function get_user_link_category(int $userid): \stdClass|bool
    {
        $sql = "SELECT
                    cc.id,
                    cc.name AS categoryname
                FROM {course_categories} cc
                INNER JOIN {user_info_data} uid ON uid.data = cc.name
                INNER JOIN {user_info_field} uif ON uif.id = uid.fieldid
                    AND uif.shortname = :fieldname
                INNER JOIN {user} u ON u.id = uid.userid
                    AND u.id = :userid
                ";
        $params = [
            'fieldname' => 'mainentity',
            'userid' => $userid
        ];

        return $this->db->get_record_sql($sql, $params);
    }

    /**
     * Get user main entity
     */
    public function get_users_missmatch_categories(array $userstoupdate, array $categories)
    {
        [$whereusers, $paramsusers] = $this->db->get_in_or_equal(
            $userstoupdate,
            SQL_PARAMS_NAMED,
            'userid'
        );

        [$wherecategories, $paramscategories] = $this->db->get_in_or_equal(
            $categories,
            SQL_PARAMS_NAMED,
            'mainentityid',
            false
        );

        $sql = "SELECT u.id
                FROM {user} u
                INNER JOIN {user_info_data} uid ON uid.userid = u.id
                INNER JOIN {user_info_field} uif ON uif.id = uid.fieldid AND uif.shortname = :fieldname
                WHERE u.id $whereusers
                AND uid.data $wherecategories
                ";
        $params["fieldname"] = 'mainentity';

        $params = array_merge($paramsusers, $paramscategories, $params);

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get only the users who don't have the data of his mainentity
     * 
     * @param array $usersid
     * @return array
     */
    public function get_only_users_no_info_field_mainentity_data(array $usersid): array
    {
        [$whereclause, $params] = $this->db->get_in_or_equal(
            $usersid,
            SQL_PARAMS_NAMED,
            'userid'
        );

        $sql = "SELECT DISTINCT(u.id)
                FROM {user} u
                INNER JOIN {user_info_data} uid ON uid.userid = u.id
                INNER JOIN {user_info_field} uif ON uif.id = uid.fieldid AND uif.shortname = :fieldname
                WHERE u.id $whereclause
                ";

        $params['fieldname'] = 'mainentity';

        $result = array_map(
            fn($user): int => $user->id,
            $this->db->get_records_sql(
                $sql,
                $params
            )
        );

        return array_diff($usersid, $result);
    }

    /**
     * Get only the users who don't have the data in mainentity field
     * 
     * @param array $usersid
     * @return array
     */
    public function get_only_users_no_info_data_mainentity(array $usersid): array
    {
        [$whereclause, $params] = $this->db->get_in_or_equal(
            $usersid,
            SQL_PARAMS_NAMED,
            'userid'
        );

        $sql = "SELECT DISTINCT(u.id)
                FROM {user} u
                INNER JOIN {user_info_data} uid ON uid.userid = u.id
                INNER JOIN {user_info_field} uif ON uif.id = uid.fieldid AND uif.shortname = :fieldname
                WHERE u.id $whereclause
                AND uid.data IS NOT NULL AND uid.data <> ''
                ";

        $params['fieldname'] = 'mainentity';

        $result = array_map(
            fn($user): int => $user->id,
            $this->db->get_records_sql(
                $sql,
                $params
            )
        );

        return array_diff($usersid, $result);
    }

    /**
     * Create the mainentity data line for all the given users
     * 
     * @param array $users
     * @return void
     */
    public function insert_user_info_data_main_entity(array $users): void
    {
        $mainentityfield = $this->db->get_record('user_info_field', ['shortname' => 'mainentity']);

        $params = [];

        $valuesclause = implode(
            ", ",
            array_map(function ($index, $userid) use (&$params, $mainentityfield): string {
                $paramsuser = "userid$index";
                $paramsfield = "mainentityfield$index";

                $params[$paramsuser] = $userid;
                $params[$paramsfield] = $mainentityfield->id;

                return "(:$paramsuser, :$paramsfield, '', 0)";
            }, array_keys($users), $users)
        );

        $sql = "INSERT INTO {user_info_data} (userid, fieldid, data, dataformat)
                VALUES $valuesclause
                ";

        $this->db->execute($sql, $params);
    }

    /**
     * Get all the last created or updated course_categories and categories_domains link
     * 
     * @param int $datenow
     * @param int $datedelay
     * @return array
     */
    public function get_all_created_or_updated_domains(int $nexttimesheduled, int $lasttimerun): array
    {
        $sql = "SELECT DISTINCT(domain_name)
                FROM {course_categories_domains}
                WHERE (
                    created_at < :timecreatedat
                    AND created_at > :delaycreatedat
                    AND disabled_at IS NULL
                )
                OR (
                    disabled_at < :timedisabledat
                    AND disabled_at > :delaydisabledat
                )";

        $params = [
            'timecreatedat' => $nexttimesheduled,
            'delaycreatedat' => $lasttimerun,
            'timedisabledat' => $nexttimesheduled,
            'delaydisabledat' => $lasttimerun,
        ];

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get all users with a defined domain name
     * Exemple of domain : domain.fr - .domain.fr - sub.domain.fr - .sub.domain.fr
     * 
     * @param string $domainname
     * @return array
     */
    public function get_all_users_by_domain_name(string $domainname, bool $arevalid = false): array
    {
        $whereclause = "";

        if ($arevalid) {
            $whereclause = " 
                AND confirmed = 1
                AND deleted = 0
                ";
        }

        $regex = "CONCAT('@', :domainname::text, '$')";
        if (substr($domainname, 0, 1) === '.') {
            $regex = "CONCAT('@.*', :domainname::text, '$')";
        }

        $sql = "SELECT id, email
                FROM {user}
                WHERE email ~* $regex
                $whereclause
                ";

        $params['domainname'] = $domainname;

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get all valid users with no main entity or is empty in database
     * 
     * @return array
     */
    public function get_users_without_main_entity(): array
    {
        $sql = "SELECT u.id, u.email
                FROM {user} u
                LEFT JOIN {user_info_data} uid
                    ON u.id = uid.userid
                    AND uid.fieldid = (
                        SELECT id
                        FROM {user_info_field}
                        WHERE shortname = :fieldname
                    )
                WHERE (uid.data is null or trim(uid.data) = '')
                AND u.confirmed = 1
                AND u.deleted = 0
                ";
        $params['fieldname'] = 'mainentity';

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Get the domains that are no more whitelisted
     * 
     */
    public function get_domains_no_more_whitelisted(): array
    {
        global $CFG;
        // Get domains that are no more whitelisted in course_categories_domains.
        $whitelistdomains = array_filter(explode(' ', $CFG->allowemailaddresses));
        $params = [];
        $placeholders = [];
        $where = "";
        if (!empty($whitelistdomains)) {
            foreach ($whitelistdomains as $i => $domain) {
                $key = "email$i";
                $placeholders[] = ':' . $key;
                $params[$key] = $domain;
            }
            $where = "AND  domain_name NOT IN (" . implode(', ', $placeholders) . ")";
        }

        $sql = "SELECT DISTINCT domain_name 
                FROM {course_categories_domains}
                WHERE created_at IS NOT NULL
                $where";

        return $this->db->get_records_sql($sql, $params);
    }

    /**
     * Delete domains => disable them
     *
     * @param array $domains
     * @return bool
     */
    public function delete_domains(array $domains): bool
    {
        if (empty($domains)) {
            return false;
        }
        $params = ['disabled_at' => time()];
        list($inSql, $inParams) = $this->db->get_in_or_equal($domains, SQL_PARAMS_NAMED, 'domain');
        $sql = "UPDATE {course_categories_domains} 
                SET disabled_at = :disabled_at 
                WHERE created_at IS NOT NULL
                AND disabled_at IS NULL
                AND domain_name $inSql";

        $params = array_merge($params, $inParams);

        try {
            return $this->db->execute($sql, $params);
        } catch (\dml_exception $e) {
            throw new \moodle_exception('errordeletingdomain', 'local_categories_domains', '', $e->getMessage());
        }
    }

    /**
     * Get all cohort categories by userid
     * 
     * @param int $userid
     * @return \stdClass[]
     * @throws \dml_exception
     */
    public function get_user_cohort_categories_name(int $userid): array
    {
        $sql = "SELECT cc.name
                FROM {course_categories} cc
                INNER JOIN {context} c ON c.instanceid = cc.id
                    AND c.contextlevel = :contextcoursecat
                INNER JOIN {cohort} coh ON coh.contextid = c.id
                INNER JOIN {cohort_members} cm ON cm.cohortid = coh.id
                    AND cm.userid = :userid
                WHERE cc.depth = 1
                ";

        $params = [
            'contextcoursecat' => CONTEXT_COURSECAT,
            'userid' => $userid,
        ];

        return array_map(
            fn($category): string => $category->name,
            $this->db->get_records_sql($sql, $params)
        );
    }
}
