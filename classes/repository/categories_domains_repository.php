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
}
