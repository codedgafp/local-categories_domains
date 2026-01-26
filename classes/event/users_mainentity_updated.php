<?php
namespace local_categories_domains\event;

defined('MOODLE_INTERNAL') || die();

class users_mainentity_updated extends \core\event\base {

    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    public static function get_name() {
        return get_string('usersmainentityupdated', 'local_categories_domains');
    }

    public function get_description() {
        return "Main entity updated for users: " . implode(',', $this->other['userids']);
    }
}
