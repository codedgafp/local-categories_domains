<?php
defined('MOODLE_INTERNAL') || die();
use local_categories_domains\model\domain_name;

require_once($CFG->dirroot . '/local/mentor_core/api/entity.php');


/**
 * Validate domains CSV content.
 *
 * @param array $content CSV content lines.
 * @return bool True if there are no fatal errors, false otherwise.
 */
function local_categories_domains_validate_domains_csv(array $content)
{
    // Define the valid separator and required fields
    $separator = ';';
    $requiredFields = ['domain_name', 'idnumber'];

    // check if the content is empty or if it only contains one empty line.
    if ((count($content) == 1 && empty($content[0])) || count($content) <= 1) {
        \core\notification::error(get_string('errorimport', 'local_categories_domains', 1));
        return false;
    }

    // Check each line of the CSV content
    foreach ($content as $index => $line) {

        if (empty($line)) {
            continue;
        }
        // Check if the file uses the correct separator in all lines
        if (strpos($line, $separator) === false) {
            \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1));
            return false;
        }
        $fields = str_getcsv(trim($line), $separator);

        $columns = [];
        clean_columns($fields, $columns);

        // Check if the header contains the correct fields
        if ($index == 0) {
            if (!in_array('domain_name', $columns) || !in_array('idnumber', $columns)) {
                \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1));
                return false;
            }
            continue;
        }

        // Check if the line contains the correct number of fields
        if (count($columns) != count($requiredFields)) {
            \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1));
            return false;
        }

        // Check if the idnumber(entity name) exists
        $idnumber = $columns[1];
        $entity = local_mentor_core\entity_api::get_main_entity_by_shortname($idnumber);
        if (empty($entity)) {
            \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1));
            return false;
        }

        // Check if the domain_name is in the whitelist
        $domain = new domain_name();
        $domain->domain_name = trim(strtolower($columns[0]));
        $domain->course_categories_id = $entity->id;
        
        if (!$domain->is_whitelisted()) {
            \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1));
            return false;
        }
    }

    return true;
}

/**
 * Clean columns function.
 *
 * @param array $fields CSV fields.
 * @param array $columns Cleaned columns.
 */
function clean_columns(array $fields, array &$columns)
{
    foreach ($fields as $column) {

        $column = trim($column);
        // Remove hidden caracters.
        $column = preg_replace('/\p{C}+/u', "", $column);
        if ($column === '') {
            continue;
        }
        $columns[] = $column;
    }
}
