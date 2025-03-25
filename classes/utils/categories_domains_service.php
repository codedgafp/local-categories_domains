<?php

namespace local_categories_domains\utils;

use local_categories_domains\model\domain_name;

defined('MOODLE_INTERNAL') || die();

use \local_categories_domains\repository\categories_domains_repository;

require_once "$CFG->dirroot/local/mentor_specialization/classes/models/mentor_entity.php";

class categories_domains_service
{
    public $categoriesdomainsrepository;

    public function __construct()
    {
        $this->categoriesdomainsrepository = new categories_domains_repository();
    }

    /**
     * Relie les utilisateurs avec la catégorie auquel il doit être associé
     * 
     * @param array $users
     * @return bool
     */
    public function link_categories_to_users(array $users): bool
    {
        global $DB;

        $defaultcategory = \local_mentor_specialization\mentor_entity::get_default_entity();

        $domainnames = array_unique(array_map(fn($user): string => (new domain_name())->get_user_domain($user->email), $users));

        foreach ($domainnames as $domainname) {
            $categoriesbydomain = $this->categoriesdomainsrepository->get_course_categories_by_domain($domainname, $defaultcategory);
            $userstoupdate = $this->get_user_to_update($users, $domainname);
            
            if (count($categoriesbydomain) > 1) {
                continue; // TODO: à traiter, cas où plusieurs category sont trouvés
            }

            $this->categoriesdomainsrepository->update_users_course_category(reset($categoriesbydomain)->name, $userstoupdate);
        }

        return true;
    }

    private function get_user_to_update($users, $domaintocheck)
    {
        $userstoupdate = array_filter($users, function ($user) use ($domaintocheck): bool {
            $domain = new domain_name();
            $domainname = $domain->get_user_domain($user->email);
            return $domaintocheck === $domainname;
        });

        return array_map(fn($user): string => $user->id, $userstoupdate);
    }
}
