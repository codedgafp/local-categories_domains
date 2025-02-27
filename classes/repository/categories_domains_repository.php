<?php

/**
 * Class categories_domains_admin_api
 *
 * @package local_categories_domains
 */

namespace local_categories_domains;

defined('MOODLE_INTERNAL') || die();

class categories_domains_repository
{
    public static function get_active_domains_by_category(int $coursecategoryid): array
    {
        global $DB;

        $sql = "SELECT ccd.domain_name
                FROM {course_categories_domains} ccd
                WHERE ccd.course_categories_id = :coursecategoryid
                AND ccd.disabled_at IS NULL
                ";

        $params["coursecategoryid"] = $coursecategoryid;

        return $DB->get_records_sql($sql, $params);
    }
    
    /**
     * Check if the user is authorized to access the list of domains.
     * 
     * @param int $coursecategoryid
     */
    public static function admindedie_can_manage_domains(int $coursecategoryid): bool
    {
        global $USER;
        // Get user's main entity.
        $usermainentity = \local_mentor_core\profile_api::get_user_main_entity($USER->id);        

        $currententity = \local_mentor_core\entity_api::get_entity($coursecategoryid);
        $ismainentity = $currententity->is_main_entity();
        // Check if user is authorized to access the list of domains. 
        // User must be a manager of the current main entity that it must be  his main entity .

        return !( $ismainentity && $coursecategoryid == $usermainentity->id && $usermainentity->is_manager($USER));
    }
}
