<?php
/**
 * Entity controller
 *
 * @package local_categories_domains
 */

namespace local_categories_domains;

use local_categories_domains\controller_base;

defined('MOODLE_INTERNAL') || die();

require_once __DIR__ . '/../../../../config.php';
require_once "$CFG->dirroot/local/categories_domains/classes/controllers/controller_base.php";
require_once "$CFG->dirroot/local/categories_domains/classes/repository/categories_domains_repository.php";

class categories_domains_controller extends controller_base
{
    /**
     * Prepare all domains to display in the table
     * 
     * @return array{actions: array, domain_name: string}
     */
    public function get_categories_domains(): array
    {
        $entityid = $this->get_param('entityid', PARAM_INT);

        if (empty($entityid)) {
            throw new \moodle_exception('entityidnotset', 'error');
        }

        $domainsbycategory = categories_domains_repository::get_active_domains_by_category($entityid);

        $tablearray = [];

        foreach ($domainsbycategory as $domain) {
            $tablearray['data'][] = [
                'domain_name' => $domain->domain_name, 
                'actions' => []
            ];
        }

        return $tablearray;
    }
}
