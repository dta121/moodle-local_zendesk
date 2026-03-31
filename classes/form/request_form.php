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
 * Ticket submission form.
 *
 * @package   local_zendesk
 */
final class request_form extends \moodleform {
    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $defaults = $this->_customdata['defaults'] ?? [];
        $mform = $this->_form;

        $mform->addElement('text', 'subject', get_string('subject', 'local_zendesk'), ['size' => 64]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        $mform->addElement(
            'textarea',
            'details',
            get_string('details', 'local_zendesk'),
            ['rows' => 12, 'cols' => 80]
        );
        $mform->setType('details', PARAM_RAW);
        $mform->addRule('details', null, 'required', null, 'client');

        $mform->addElement('hidden', 'courseid', $defaults['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'contextid', $defaults['contextid'] ?? 0);
        $mform->setType('contextid', PARAM_INT);

        $this->add_action_buttons(true, get_string('submitrequestbutton', 'local_zendesk'));
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

        if (\core_text::strlen(trim((string) ($data['subject'] ?? ''))) > 255) {
            $errors['subject'] = get_string('errorsubjecttoolong', 'local_zendesk');
        }

        if (trim((string) ($data['details'] ?? '')) === '') {
            $errors['details'] = get_string('errordetailsrequired', 'local_zendesk');
        }

        return $errors;
    }
}
