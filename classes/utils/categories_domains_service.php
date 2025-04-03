<?php

namespace local_categories_domains\utils;

defined('MOODLE_INTERNAL') || die();

require_once "$CFG->dirroot/local/mentor_specialization/classes/models/mentor_entity.php";

use \local_categories_domains\repository\categories_domains_repository;
use \local_categories_domains\model\domain_name;
use \local_mentor_specialization\mentor_entity;
use core_course_category;

class categories_domains_service
{
    public $categoriesdomainsrepository;
    public $db;
    public $db;

    public function __construct()
    {
        global $DB;
        $this->db = $DB;

        global $DB;
        $this->db = $DB;

        $this->categoriesdomainsrepository = new categories_domains_repository();
    }

    /**
     * Lie automatiquement les utilisateurs à l'entité auquel il doit être relié.
     * De plus gère automatiquement l'attribution du rôle externe.
     * 
     * @param array $users
     * @param \stdClass|\core_course_category $category
     * @return bool
     */
    public function link_categories_to_users(array $users, \stdClass|core_course_category $category = null): bool
    {
        $defaultcategory = mentor_entity::get_default_entity();

        $domainsdata = array_unique(array_map([$this, 'get_domains_data'], $users), SORT_REGULAR);

        foreach ($domainsdata as $domain) {
            $userstoupdate = $this->get_users_to_update($users, $domain['domainname']);

            if ($domain['iswhitelist'] == false) {
                // RG01-MEN-474
                $categorytoset = $defaultcategory;

                if ($category) {
                    $mentorentity = new mentor_entity($category->id);
                    $categorytoset = $mentorentity->can_be_main_entity(true) ? $category : $defaultcategory;
                }
                // RG01-MEN-474

                $this->update_domain_users($categorytoset, $userstoupdate, true);

                continue;
            }

            $categoriesbydomain = $this->categoriesdomainsrepository->get_course_categories_by_domain($domain['domainname'], $defaultcategory);

            if (count($categoriesbydomain) > 1) {
                continue; // TODO: à traiter, cas où plusieurs category sont trouvés
            }

            $this->update_domain_users(reset($categoriesbydomain), $userstoupdate, false);
        }

        return true;
    }

    /**
     * Get the domain name and if the domain is in the white list
     * 
     * @param mixed $user
     * @return array{domainname: string, iswhitelist: bool}
     */
    private function get_domains_data($user): array
    {
        $domain = new domain_name();
        $domain->set_user_domain($user->email);
        return [
            "domainname" => $domain->domain_name,
            "iswhitelist" => $domain->is_whitelisted()
        ];
    }

    /**
     * Get all the users to update depends of the checking domain
     * 
     * @param array $users
     * @param string $domaintocheck
     * @return array[]
     */
    private function get_users_to_update(array $users, string $domaintocheck): array
    {
        $userstoupdate = array_filter($users, function ($user) use ($domaintocheck): bool {
            $domain = new domain_name();
            $domain->set_user_domain($user->email);
            return $domaintocheck === $domain->domain_name;
            $domain->set_user_domain($user->email);
            return $domaintocheck === $domain->domain_name;
        });

        return array_map(fn($user): string => $user->id, $userstoupdate);
    }

    /**
     * Update all the users linked entity and the external role
     * 
     * @param \stdClass|core_course_category $categorytoset
     * @param array $userstoupdate
     * @param bool $setexternalrole
     * @return void
     */
    private function update_domain_users(\stdClass|core_course_category $categorytoset, array $userstoupdate, bool $setexternalrole): void
    {
        $dbinterface = \local_mentor_core\database_interface::get_instance();
        $externalrole = $this->db->get_record('role', ['shortname' => 'utilisateurexterne']);

        foreach ($userstoupdate as $userid) {
            toggle_external_user_role($userid, $setexternalrole);

            $isexternal = $this->db->get_record('role_assignments', ['userid' => $userid, 'roleid' => $externalrole->id]);
            if ($isexternal && $setexternalrole) {
                $dbinterface->set_profile_field_value($userid, 'roleMentor', $externalrole->shortname);
            }
        }

        $this->categoriesdomainsrepository->update_users_course_category($categorytoset->name, $userstoupdate);
    }
}
