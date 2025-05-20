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
    // Create the interim settings page.
    $settings = new admin_settingpage(
        'block_wds_postgrades_settings',
        get_string('interimgradesettings', 'block_wds_postgrades')
    );

    // Add the settings page to the blocks section.
    $ADMIN->add('blocksettings', $settings);

    // Fetch active academic periods using standard Moodle DB methods.
    $periods = \block_wds_postgrades\period_settings::get_active_periods();

    // For each period, add date settings.
    if (!empty($periods)) {
        foreach ($periods as $period) {

            // Create a heading for each academic period.
            $settings->add(new admin_setting_heading(
                'period_' . $period->academic_period_id,
                get_string('periodheading', 'block_wds_postgrades', $period->academic_period_id),
                get_string('perioddescription', 'block_wds_postgrades')
            ));

            // Add start date setting for this period.
            $settings->add(new admin_setting_configdatetime(
                'block_wds_postgrades/period_' . $period->academic_period_id . '_start',
                get_string('periodstartdate', 'block_wds_postgrades'),
                get_string('periodstartdatedesc', 'block_wds_postgrades'),
                time()
            ));

            // Add end date setting for this period.
            $settings->add(new admin_setting_configdatetime(
                'block_wds_postgrades/period_' . $period->academic_period_id . '_end',
                get_string('periodenddate', 'block_wds_postgrades'),
                get_string('periodenddatedesc', 'block_wds_postgrades'),
                time() + WEEKSECS * 2
            ));
        }
    } else {

        // Display a message if no active periods are found.
        $settings->add(new admin_setting_heading(
            'no_periods',
            get_string('noperiods', 'block_wds_postgrades'),
            get_string('noperiodsdesc', 'block_wds_postgrades')
        ));
    }
}
