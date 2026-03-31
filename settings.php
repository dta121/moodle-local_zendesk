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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_zendesk', get_string('pluginname', 'local_zendesk'));

    $settings->add(new admin_setting_configcheckbox(
        'local_zendesk/enabled',
        get_string('enabled', 'local_zendesk'),
        get_string('enabled_desc', 'local_zendesk'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/subdomain',
        get_string('subdomain', 'local_zendesk'),
        get_string('subdomain_desc', 'local_zendesk'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/serviceemail',
        get_string('serviceemail', 'local_zendesk'),
        get_string('serviceemail_desc', 'local_zendesk'),
        '',
        PARAM_EMAIL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_zendesk/apitoken',
        get_string('apitoken', 'local_zendesk'),
        get_string('apitoken_desc', 'local_zendesk'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_zendesk/skipverifyemail',
        get_string('skipverifyemail', 'local_zendesk'),
        get_string('skipverifyemail_desc', 'local_zendesk'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/ticketformid',
        get_string('ticketformid', 'local_zendesk'),
        get_string('ticketformid_desc', 'local_zendesk'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/brandid',
        get_string('brandid', 'local_zendesk'),
        get_string('brandid_desc', 'local_zendesk'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/groupid',
        get_string('groupid', 'local_zendesk'),
        get_string('groupid_desc', 'local_zendesk'),
        '',
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/dashboardlimit',
        get_string('dashboardlimit', 'local_zendesk'),
        get_string('dashboardlimit_desc', 'local_zendesk'),
        5,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_zendesk/syncbatchsize',
        get_string('syncbatchsize', 'local_zendesk'),
        get_string('syncbatchsize_desc', 'local_zendesk'),
        100,
        PARAM_INT
    ));

    $ADMIN->add('localplugins', $settings);
}
