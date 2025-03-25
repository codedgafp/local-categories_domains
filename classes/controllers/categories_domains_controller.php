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
     * @var array
     */
    protected array $params = [];

    /**
     * @var categories_domains_repository
     */
    protected categories_domains_repository $categoriesdomainsrepository;

    public function __construct($params)
    {
        $this->params = $params;
        $this->categoriesdomainsrepository = new categories_domains_repository();
    }

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
        $order = $this->get_param('order');
        $orderdir = $order[0]['dir'] ?? "DESC";
        $orderBy = isset($order[0]['column']) ? ($order[0]['column'] === 0 ? "domain_name" : "created_at") : "created_at";

        if (empty($entityid)) {
            throw new \moodle_exception('entityidnotset', 'error');
        }

        $domainsbycategory = $this->categoriesdomainsrepository->get_active_domains_by_category($entityid, $orderdir, $orderBy);

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
        $entityid = $this->get_param('entityid', PARAM_INT);
        $domain_name = $this->get_param('domainname', PARAM_TEXT);

        if (empty($entityid)) {
            throw new \moodle_exception('entityidnotset', 'error');
        }

        return $this->categoriesdomainsrepository->delete_domain($entityid, $domain_name);
    }

    /**
     * Add a new domain for a category
     * @return array JSON response
     */
    public function add_domain(): array
    {
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
            
            // Check if domain already exists for main entity && is DISABLED
            if ( $repo->is_domain_disabled($domain))
            {
                $result =  $repo->reactivate_domain($domain);
                return ['status' => true, 'message' => $result];
            }

            // Check if domain already exists for main entity
            if ($domain->is_exist()) {
                return ['status' => false, 'message' => get_string('domainexists', 'local_categories_domains')];
            }

            $result = $this->categoriesdomainsrepository->add_domain($domain);
            return ['status' => true, 'message' => $result];
        } catch (\Exception $e) {
            return ['status' => false, 'message' => get_string('erroraddingdomain', 'local_categories_domains')];
        }
    }
}
