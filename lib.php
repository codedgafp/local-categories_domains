<?php
defined('MOODLE_INTERNAL') || die();
use local_categories_domains\model\domain_name;
use \local_categories_domains\repository\categories_domains_repository;

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
/**
 * Import domains from CSV content.
 * 
 * @param array $content CSV content lines.
 * @return bool True if domains were imported successfully, false otherwise.
 */
function local_categories_domains_import_domains(array $content)
{
    $separator = ';';
    $domains = [];
    $repo = new categories_domains_repository();
    foreach ($content as $index => $line) {
        if (empty($line)) {
            continue;
        }
        $fields = str_getcsv(trim($line), $separator);
        $columns = [];
        clean_columns($fields, $columns);

        if ($index == 0) {
            continue;
        }

        $domain = new domain_name();
        $domain->domain_name = trim(strtolower($columns[0]));
        $domain->course_categories_id = \local_mentor_core\entity_api::get_main_entity_by_shortname($columns[1])->id;
        $domains[] = $domain;

        // Check if the domain already exists in the database
        $existingDomain = $repo->get_domain($domain);
        if ($existingDomain) {
            if ($existingDomain->disabled_at != null) {
                // If the domain exists && disabled, update it to be diactivated
                try {
                    $repo->reactivate_domain($existingDomain->course_categories_id, $existingDomain->domain_name);
                    $domains[] = $domain;
                } catch (moodle_exception $e) {
                    \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1) . ' : ' . $e->getMessage());
                    continue;
                }
            } else {
                continue;
            }
        } else {
            // If the domain does not exist, add it to the list of new domains to be inserted
            try {
                $repo->add_domain($domain);
            } catch (moodle_exception $e) {
                \core\notification::error(get_string('errorimport', 'local_categories_domains', $index + 1) . ' : ' . $e->getMessage());
                continue;
            }
        }
    }
    return deactivate_domains_not_in_csv($domains);
}



/**
 * Deactivate domains that are not in the CSV content.
 *
 * @param array $domains List of domains to keep active.
 * @return bool True if domains were deactivated successfully, false otherwise.
 *  
 */
function deactivate_domains_not_in_csv(array $domains)
{
    $repo = new categories_domains_repository();
    $existingDomains = $repo->get_all_activated_domains();
    $found = [];
    foreach ($existingDomains as $existingDomain) {
        $found = false;
        $found = find_matching_domain($domains, $existingDomain);
        if (!$found) {
            try {
                $repo->delete_domain($existingDomain->course_categories_id, $existingDomain->domain_name);
            } catch (moodle_exception $e) {
                \core\notification::error(get_string('errorimport', 'local_categories_domains') . ' : ' . $e->getMessage());
            }
        }
    }

    return true;
}

/**
 * Find matching domain in the list of domains.
 *
 * @param array $domains List of domains.
 * @param object $existingDomain Existing domain object.
 * @return bool True if a matching domain is found, false otherwise.
 */
function find_matching_domain(array $domains, $existingDomain)
{
    $found = array_filter($domains, function ($domain) use ($existingDomain) {
        return $domain->domain_name == $existingDomain->domain_name && $domain->course_categories_id == $existingDomain->course_categories_id;
    });
    return !empty($found);
}
