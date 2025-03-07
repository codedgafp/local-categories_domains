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

use local_categories_domains\categories_domains_repository;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');
require_once "$CFG->dirroot/local/categories_domains/classes/repository/categories_domains_repository.php";



$context = context_system::instance();
$site = get_site();

// Set context.
$PAGE->set_context($context);

// Get entity id.
$entityid = required_param('entityid', PARAM_INT); 
$repository = new categories_domains_repository();

// Check permissions.
if (isloggedin() && ( !is_siteadmin($USER) && !($repository->admindedie_can_manage_domains($entityid)) )) {
    redirect($CFG->wwwroot, get_string('nopermissions', 'local_catalog'));
    exit;
}

// Get entity.
$entity = \local_mentor_core\entity_api::get_entity($entityid);

// Get local user edadmin course for navbar link.
$usercourse = $entity->get_edadmin_courses('user');
$usermanagementurl = new moodle_url('/course/view.php', ['id' => $usercourse['id']]);


// Set navbar.
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('managespaces', 'format_edadmin'), new moodle_url('/local/entities/index.php'));
$PAGE->navbar->add(get_string('edadminusercoursetitle', 'local_user'), $usermanagementurl->out());
$PAGE->navbar->add($entity->get_name() . ' - '.new lang_string('categoriesdomainstitle', 'local_categories_domains'));


// Add jQuery UI plugins
$PAGE->requires->jquery_plugin('ui');
$PAGE->requires->jquery_plugin('ui-css');

$PAGE->set_url('/local/categories_domains/manage_domains.php', array('entityid' => $entityid));
$PAGE->set_title( new lang_string('categoriesdomainstitle', 'local_categories_domains'));
$PAGE->set_pagelayout('standard');

// Setting header page.
$PAGE->set_heading($entity->get_name() . ' - '.new lang_string('categoriesdomainstitle', 'local_categories_domains'));
echo $OUTPUT->header();
echo $OUTPUT->skip_link_target();

// Call the renderer.
$renderer = $PAGE->get_renderer('local_categories_domains', 'categories_domains');
// Call the renderer method to render the 'manage_domains.mustache' template
echo $renderer->render_manage_domains();

// Display footer.
echo $OUTPUT->footer();
