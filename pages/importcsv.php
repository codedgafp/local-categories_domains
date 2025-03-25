<?php

require_once('../../../config.php');

use local_categories_domains\forms\importdomainscsv_form;

require_once($CFG->dirroot . '/local/mentor_core/lib.php');

require_once($CFG->dirroot . '/local/categories_domains/forms/importdomainscsv_form.php');
require_once($CFG->dirroot . '/local/categories_domains/lib.php');

// Require login.
require_login();

// Require entity id.
$entityid = required_param('entityid', PARAM_INT);

// Check permissions.
if (!is_siteadmin($USER)) {
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

// Call the renderer for Export
$renderer = $PAGE->get_renderer('local_categories_domains', 'categories_domains');
echo $renderer->render_export_domains_csv();

// Import CSV form.
$csvmform = new importdomainscsv_form($url->out(), ['entityid' => $entityid]);
$csvformdata = $csvmform->get_data();
// Validate given data from CSV.
if (null !== $csvformdata) {
    $out .= $csvmform->render();

    echo html_writer::div($out);
    $out = '';

    // Convert line breaks into standard line breaks.
    $filecontent = local_mentor_core_decode_csv_content($csvmform->get_file_content('domainscsv'));
    // Check if file is valid UTF-8.
    if (false === mb_detect_encoding($filecontent, 'UTF-8', false)) {
        \core\notification::error(get_string('errorimport', 'local_categories_domains', 1));
    } else {
        // Convert lines into array.
        $content = str_getcsv($filecontent, "\n");
        if(local_categories_domains_validate_domains_csv($content))
        {

            // Import domains.
            $imported = local_categories_domains_import_domains($content);
            if ($imported) {
                \core\notification::success(get_string('domainsimported', 'local_categories_domains', $imported));
            } else {
                \core\notification::error(get_string('errorimport', 'local_categories_domains', 1));
            }
        }
    }
} else {

    $csvmform->set_data($csvformdata);
    $csvmform->display();
}
echo $OUTPUT->footer();
