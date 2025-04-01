<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_categories_domains\output;

defined('MOODLE_INTERNAL') || die();

use local_categories_domains\repository\categories_domains_repository;
use local_mentor_core\entity_api;
use moodle_url;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');

class categories_domains_renderer extends \plugin_renderer_base {
    
    /**
     * Render the categories domains button
     *
     * @param int $entityid The ID of the entity
     * @return string The rendered output
     * @throws \moodle_exception
     */       
    public function render_manage_domains_button($entityid) {
        global $USER;
     // Initialize params
        $params = array();        
        $params['url'] = new moodle_url('/local/categories_domains/index.php?entityid=' . $entityid);
        $params['entityid'] = $entityid;
        $objentity = entity_api::get_entity($entityid);
        $is_main_entity = $objentity->can_be_main_entity();
        $repository = new categories_domains_repository();
        $params['user_can_manage_domains'] = $is_main_entity && ($repository->admindedie_can_manage_domains($entityid) || is_siteadmin($USER));
        return $this->render_from_template('local_categories_domains/manage_domains_button', $params);
    }

    /**
     * Render the manage domains main page
     * 
     * @return bool|string
     */
    public function render_manage_domains(): bool|string {
        global $USER;
        
        $entityid = required_param('entityid', PARAM_INT);
        //admindeidie cannot manage domains only siteadmin
        $repository = new categories_domains_repository();
        $user_can_manage_domains = !$repository->admindedie_can_manage_domains($entityid)  || is_siteadmin($USER);

        $this->page->requires->strings_for_js([
            'langfile',
            'add_domain',
            'cancel',
            'confirm',
            'requiredfield',
            'import_csv_domain',
            'delete_domain',
            'delete_domain_confirmation_text',
            'delete'
        ], 'local_categories_domains');
        
        $this->page->requires->js_call_amd(
            'local_categories_domains/categories_domains',
            'init',
            ['entityid' => $entityid,
            'user_can_manage_domains' => $user_can_manage_domains]
        );
        echo $this->render_entity_selector($entityid);
        return $this->output->render_from_template('local_categories_domains/manage_domains', ["user_can_manage_domains" => $user_can_manage_domains]);
    }

    public function render_entity_selector($entityid) {
        global $USER, $PAGE;

        // Get managed entities if user has any.
        $managedentities = entity_api::get_managed_entities($USER);

        if (count($managedentities) <= 1) {
            return '';
        }

        // Create an entity selector if it manages several entities.
        $data = new \stdClass();
        $data->switchentities = [];

        foreach ($managedentities as $managedentity) {

            if (!$managedentity->can_be_main_entity()) {
                continue;
            }
            $entitydata = new \stdClass();
            $entitydata->name = $managedentity->shortname;
            $entitydata->link =  new moodle_url('/local/categories_domains/index.php', ['entityid' => $managedentity->id]);
            $entitydata->selected = $entityid == $managedentity->id;
            $data->switchentities[] = $entitydata;
        }
        if (count($data->switchentities) <= 1) {
            return '';
        }
        // Call template.
        $PAGE->requires->string_for_js('pleaserefresh', 'format_edadmin');
        $PAGE->requires->js_call_amd('format_edadmin/format_edadmin', 'selectEntity');
        return $this->render_from_template('format_edadmin/entity_select', $data);
    }

    public function render_action_buttons(string $domain_name): string
    {
        $this->page->requires->strings_for_js([

            'delete_domain',
            'delete_domain_confirmation_text',
            'delete'
        ], 'local_categories_domains');
        return $this->render_from_template('local_categories_domains/delete_domain_button', array('domain_name' => $domain_name));
    }

}
