<?php


use local_categories_domains\forms\importdomainscsv_form;

require_once('../../../config.php');
require_once($CFG->dirroot . '/local/mentor_core/lib.php');

require_once($CFG->dirroot . '/local/categories_domains/forms/importdomainscsv_form.php');

// Require login.
require_login();

// Require entity id.
$entityid = required_param('entityid', PARAM_INT);

// Check permissions.
if ( !is_siteadmin($USER)) {
    redirect($CFG->wwwroot, get_string('nopermissions', 'local_catalog'));
    exit;
}

// Get entity.
$entity = \local_mentor_core\entity_api::get_entity($entityid);

// Get local user edadmin course for navbar link.
$usercourse = $entity->get_edadmin_courses('user');

// Get entity context.
$entitycontext = $entity->get_context();


$title = get_string('importdomains', 'local_categories_domains');
$url = new moodle_url('/local/categories_domains/pages/importcsv.php', ['entityid' => $entityid]);

// Set navbar.
$PAGE->navbar->ignore_active();
$PAGE->navbar->add(get_string('managespaces', 'format_edadmin'), new moodle_url('/local/entities/index.php'));
$PAGE->navbar->add($entity->get_name());
$PAGE->navbar->add(get_string('edadminusercoursetitle', 'local_user'), new moodle_url('/course/view.php', [
    'id' => $usercourse['id'],
]));
$PAGE->navbar->add($title, $url);

// Settings first element page.
$PAGE->set_url($url);
$PAGE->set_context($entitycontext);
$PAGE->set_title($title);
$PAGE->set_heading($title);


// Output content.
$out = '';

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

// Anchor directly to the import report.
$anchorurl = new moodle_url('/local/categories_domains/pages/importcsv.php', ['entityid' => $entityid], 'import-reports');

// Import CSV form.
$csvmform = new importdomainscsv_form($anchorurl->out(), ['entityid' => $entityid]);
$csvformdata = $csvmform->get_data();

$csvmform->set_data($csvformdata);
$csvmform->display();

echo $OUTPUT->footer();
