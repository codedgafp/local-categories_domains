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

/**
 * Import CSV form
 *
 * @package    local_categories_domains
 */

namespace local_categories_domains\forms;

use moodleform;
use moodle_url;
use html_writer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');
require_once($CFG->dirroot . '/lib/csvlib.class.php');

class importdomainscsv_form extends moodleform
{

    /**
     * Maximum bytes of uploaded file.
     */
    private const _MAXBYTES = 512000;

    public $entityid;

    /**
     * import csv constructor.
     *
     * @param string $action
     * @param \stdClass $data
     */
    public function __construct($action, $data)
    {

        if (isset($data['entityid'])) {
            $this->entityid = $data['entityid'];
        }

        parent::__construct($action);
    }

    /**
     * Define form fields
     */
    public function definition()
    {
        $mform = $this->_form;

        $mform->addElement('header', 'importcsvheader', get_string('upload'));

        $link = html_writer::tag('button', "example.csv", [
            'type' => 'button',
            'id' => 'export_csv_domains',
            'class' => 'button-as-link',
        ]);

        $mform->addElement('static', 'examplecsv', html_writer::tag('span', get_string('textexamplecsv', 'local_categories_domains'), ['style' => 'color: red;']), $link);
        
        $mform->addElement(
            'filepicker',
            'domainscsv',
            get_string('file'),
            null,
            ['maxbytes' => self::_MAXBYTES, 'accepted_types' => 'text/csv']
        );
        $mform->addRule('domainscsv', get_string('required', 'local_categories_domains'), 'required');
       

        $this->add_action_buttons(false, get_string('validate_import', 'local_categories_domains'));
    }
}
