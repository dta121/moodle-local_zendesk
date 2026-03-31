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

namespace local_zendesk\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Reply form for Zendesk ticket conversations.
 *
 * @package   local_zendesk
 */
final class reply_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $buttonlabel = $this->_customdata['buttonlabel'] ?? get_string('replyreopenbutton', 'local_zendesk');
        $mform = $this->_form;

        $mform->addElement(
            'textarea',
            'replymessage',
            get_string('replymessage', 'local_zendesk'),
            [
                'rows' => 5,
                'cols' => 80,
                'placeholder' => get_string('replyplaceholder', 'local_zendesk'),
            ]
        );
        $mform->setType('replymessage', PARAM_RAW);
        $mform->addRule('replymessage', null, 'required', null, 'client');

        $mform->addElement('submit', 'submitbutton', $buttonlabel);
    }

    /**
     * Validation.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (trim((string) ($data['replymessage'] ?? '')) === '') {
            $errors['replymessage'] = get_string('replyrequired', 'local_zendesk');
        }

        return $errors;
    }
}
