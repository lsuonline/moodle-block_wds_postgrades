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
 * Settings for the WDS Post Grades block.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    if (!is_siteadmin()) {
        redirect(new moodle_url('/'));
    }

    // Add a link to the period configuration page.
    $settings->add(new admin_setting_heading(
        'block_wds_postgrades/periodconfig',
        get_string('settings', 'block_wds_postgrades'),
        ''
    ));

    // Register the external page for period configuration.
    $ADMIN->add('blocksettings', new admin_externalpage(
        'block_wds_postgrades_periodconfig',
        get_string('periodconfig', 'block_wds_postgrades'),
        new moodle_url('/blocks/wds_postgrades/period_config.php')
    ));

    // Create a link to the period configuration page.
    $periodconfigurl = new moodle_url('/blocks/wds_postgrades/period_config.php');
    $settings->add(new admin_setting_description(
        'block_wds_postgrades/periodconfiglink',
        '',
        html_writer::link($periodconfigurl, get_string('periodconfiglinktext', 'block_wds_postgrades'),
            ['class' => 'btn btn-primary'])
    ));
}
