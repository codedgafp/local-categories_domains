<?php

/**
 * Class domainname
 *
 * @package    local_categories_domains
 * @copyright  2025 CGI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_categories_domains;
use \local_categories_domains\categories_domains_repository;

defined('MOODLE_INTERNAL') || die();

class domain_name
{

    public string $domain_name;

    public int $course_categories_id;

    public int $created_at;

    public int $disabled_at;

    public function __construct()
    {
        $this->created_at = time();
    }

    /**
     * Check if the domain is in the whitelist
     * 
     * @param string $domain The domain name to check
     * @return bool True if domain is whitelisted
     */
    public function is_whitelisted(): bool
    {
        global $CFG;
        $whitelistConfig = $CFG->allowemailaddresses;

        if (empty($whitelistConfig)) {
            return false;
        }

        $whitelist = array_map('trim', explode(' ', $whitelistConfig));
        foreach ($whitelist as $allowed) {
            if ($this->domain_name === $allowed || ($allowed[0] === '.' && str_ends_with($this->domain_name, $allowed))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the domain already exists for the same entity
     * 
     * @param string $domain The domain name to check
     * @return bool True if domain exists
     */
    public function is_exist(): bool
    {
        $categories_domains_repository = new categories_domains_repository();
        return $categories_domains_repository->is_domain_exists($this);
    }

}