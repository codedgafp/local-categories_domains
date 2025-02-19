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

use moodle_url;

class categories_domains_renderer extends \plugin_renderer_base {
    
    /**
     * Render the categories domains button
     *
     * @param int $entityid The ID of the entity
     * @return string The rendered output
     * @throws \moodle_exception
     */       
    public function render_manage_domains_button($entityid) {
     // Initialize params
        $params = array();
        $params['can_manage_domains'] = is_siteadmin();
        $params['url'] = new moodle_url('/local/categories_domains/index.php?entityid=' . $entityid);
        return $this->render_from_template('local_categories_domains/manage_domains_button', $params);
    }

    /**
     * Render the manage domains main page
     * 
     * @return bool|string
     */
    public function render_manage_domains(): bool|string {
        $entityid = required_param('entityid', PARAM_INT);
        $this->page->requires->js_call_amd(
            'local_categories_domains/categories_domains',
            'init',
            ['entityid' => $entityid]
        );

        $this->page->requires->strings_for_js(
            ['langfile'],
            'local_categories_domains'
        );

        return $this->output->render_from_template('local_categories_domains/manage_domains', []);
    }

}
