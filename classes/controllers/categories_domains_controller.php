<?php
/**
 * Entity controller
 *
 * @package local_categories_domains
 */

namespace local_categories_domains\controllers;

use local_categories_domains\controllers\controller_base;
use local_categories_domains\model\domain_name;
use local_categories_domains\repository\categories_domains_repository;
use \context_system;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../../../config.php';

class categories_domains_controller extends controller_base
{
    /**
     * Prepare all domains to display in the table
     * 
     * @return array{actions: array, domain_name: string}
     */
    public function get_categories_domains(): array
    {
        global $PAGE;
        
        $context = context_system::instance();
        // Set context.
        $PAGE->set_context($context);
        $entityid = $this->get_param('entityid', PARAM_INT);

        if (empty($entityid)) {
            throw new \moodle_exception('entityidnotset', 'error');
        }

        $domainsbycategory = categories_domains_repository::get_active_domains_by_category($entityid);

        $tablearray = [];
        $categoriesDomainsRenderer = $PAGE->get_renderer('local_categories_domains', 'categories_domains');
        foreach ($domainsbycategory as $domain) {
            $tablearray['data'][] = [
                'domain_name' => $domain->domain_name,
                
                'actions' => $categoriesDomainsRenderer->render_action_buttons($domain->domain_name)
            ];
        }

        return $tablearray;
    }


    /**
     * Delete a domain => disable it
     * 
     * @return bool
     */
    public function delete_categorie_domain(): bool
    {
        $repo = new categories_domains_repository();

        $entityid = $this->get_param('entityid', PARAM_INT);
        $domain_name = $this->get_param('domainname', PARAM_TEXT);

        if (empty($entityid)) {
            throw new \moodle_exception('entityidnotset', 'error');
        }

        return  $repo->delete_domain($entityid, $domain_name);
   
    }


    /**
     * Add a new domain for a category
     * @return array JSON response
     */
    public function add_domain(): array
    {
        $repo = new categories_domains_repository();
        
        $entityid = $this->get_param('entityid', PARAM_INT);
        $name = $this->get_param('domainname', PARAM_TEXT);

        if (empty($entityid)) {
            return ['status' => false, 'message' => get_string('entityidnotset', 'local_categories_domains')];
        }

        if (empty($name) || trim($name) === '') {
            return ['status' => false, 'message' => get_string('requiredfield', 'local_categories_domains')];
        }

        try {
            $domain = new domain_name();
            $domain->domain_name = trim(strtolower($name));
            $domain->course_categories_id = $entityid;

            // Check if domain is in whitelist
            if (!$domain->is_whitelisted()) {
                return ['status' => false, 'message' => get_string('domainnotwhitelisted', 'local_categories_domains')];
            }

            // Check if domain already exists for main entity
            if ($domain->is_exist()) {
                return ['status' => false, 'message' => get_string('domainexists', 'local_categories_domains')];
            }
            
            $result = $repo->add_domain($domain);
            return ['status' => true, 'message' => $result];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => get_string('erroraddingdomain', 'local_categories_domains')];
        }
    }
    
}
