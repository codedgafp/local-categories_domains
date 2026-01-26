<?php
namespace local_categories_domains\event;

defined('MOODLE_INTERNAL') || die();

class user_secondaryentities_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('usersecondaryentitiesupdated', 'local_categories_domains');
    }

    public function get_description() {
        return "Secondary entities updated for user: " . implode(',', $this->other['userid']);
    }
}
