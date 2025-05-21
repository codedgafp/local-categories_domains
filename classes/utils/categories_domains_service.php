<?php

namespace local_categories_domains\utils;

defined('MOODLE_INTERNAL') || die();

use \local_categories_domains\repository\categories_domains_repository;
use \local_categories_domains\model\domain_name;
use \local_mentor_specialization\mentor_entity;
use \local_mentor_core\database_interface;
use \local_mentor_core\profile_api;
use core_course_category;

require_once "$CFG->dirroot/local/mentor_core/classes/database_interface.php";
require_once "$CFG->dirroot/local/mentor_specialization/classes/models/mentor_entity.php";
require_once "$CFG->dirroot/local/mentor_core/api/profile.php";

class categories_domains_service
{
    public $categoriesdomainsrepository;
    public $db;

    public function __construct()
    {
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

        $userstocohort = [];

        foreach ($domainsdata as $domain) {
            $userstoupdate = $this->get_users_to_update($users, $domain['domainname']);

            $usersnomainentityfield = $this->categoriesdomainsrepository->get_only_users_no_info_field_mainentity_data($userstoupdate);
            if ($usersnomainentityfield) {
                $this->categoriesdomainsrepository->insert_user_info_data_main_entity($usersnomainentityfield);
            }

            if ($domain['iswhitelist'] == false) {
                // RG01-MEN-474
                $categorytoset = $defaultcategory;

                if ($category) {
                    $mentorentity = new mentor_entity($category->id);
                    $categorytoset = $mentorentity->can_be_main_entity(true) ? $category : $defaultcategory;
                }
                // RG01-MEN-474

                $this->manage_users_external_role($userstoupdate, true);
                $this->categoriesdomainsrepository->update_users_course_category($categorytoset->name, $userstoupdate);

                $userstocohort = array_merge($userstoupdate, $userstocohort);

                continue;
            }

            $this->manage_users_external_role($userstoupdate, false);

            $categoriesbydomain = $this->categoriesdomainsrepository->get_course_categories_by_domain($domain['domainname'], $defaultcategory);

            if (count($categoriesbydomain) > 1) {
                $categoriesname = array_map(fn($category): string => $category->name, $categoriesbydomain);

                $userstoupdatearray = $this->categoriesdomainsrepository->get_users_missmatch_categories($userstoupdate, $categoriesname);

                if ($userstoupdatearray) {
                    $userstoupdate = array_map(fn($user): string => $user->id, $userstoupdatearray);

                    $emptycoursecategory = new \stdClass();
                    $emptycoursecategory->name = "";
                    $this->categoriesdomainsrepository->update_users_course_category($emptycoursecategory->name, $userstoupdate);
                }

                $userstocohort = array_merge($userstoupdate, $userstocohort);

                continue;
            }

            $categoryname = reset($categoriesbydomain)->name;
            $this->categoriesdomainsrepository->update_users_course_category($categoryname, $userstoupdate);

            $userstocohort = array_merge($userstoupdate, $userstocohort);
        }

        foreach ($userstocohort as $user) {
            $profile = profile_api::get_profile($user);
            $profile->sync_entities();
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
        });

        return array_map(fn($user): string => $user->id, $userstoupdate);
    }


    /**
     * Get list of entities by email
     * 
     * @param string $email
     * @return array
     */
    public function get_list_entities_by_email(string $email): array
    {
        $domain = new domain_name();
        $domain->set_user_domain($email);
        $emailDomain = $domain->domain_name;
        $courseCategories = $this->categoriesdomainsrepository->get_course_categories_by_domain($emailDomain, mentor_entity::get_default_entity());

        $courseCategories = array_map(fn($entity): string => $entity->name, $courseCategories);

        usort($courseCategories, fn($a, $b) => strcmp(local_mentor_core_sanitize_string($a), local_mentor_core_sanitize_string($b)));

        return array_combine($courseCategories, $courseCategories);
    }

    /**
     * Set or unset the external role of users to update
     * If 'setexternalrole' is set at true, the 'userstoupdate' array is updated
     * to contain only users who do not yet have a main entity
     * 
     * @param array $userstoupdate
     * @param bool $setexternalrole
     * @return void
     */
    private function manage_users_external_role(array &$userstoupdate, bool $setexternalrole)
    {
        $dbinterface = database_interface::get_instance();
        $externalrole = $this->db->get_record('role', ['shortname' => 'utilisateurexterne']);

        foreach ($userstoupdate as $userid) {
            toggle_external_user_role($userid, $setexternalrole);

            $isexternal = $this->db->get_record('role_assignments', ['userid' => $userid, 'roleid' => $externalrole->id]);
            if ($isexternal && $setexternalrole) {
                $dbinterface->set_profile_field_value($userid, 'roleMentor', $externalrole->shortname);
            }
        }

        // RG03-MEN-474
        if ($setexternalrole === true) {
            $userstoupdate = $this->categoriesdomainsrepository->get_only_users_no_info_data_mainentity($userstoupdate);
        }
        // RG03-MEN-474
    }
}
