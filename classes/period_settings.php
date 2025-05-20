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
 * Period settings class for WDS Post Grades block.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_wds_postgrades;

defined('MOODLE_INTERNAL') || die();

/**
 * Class to manage period settings for interim grades.
 */
class period_settings {

    /**
     * Get active academic periods.
     *
     * @return array Array of active academic period objects.
     */
    public static function get_active_periods() {
        global $DB;

        // Get current timestamp.
        $currenttime = time();

        // Build the SQL query using Moodle's DB API.
        $sql = "SELECT id, academic_period_id
                FROM {enrol_wds_periods}
                WHERE enabled = :enabled
                AND end_date > :currenttime";

        $parms = [
            'enabled' => '1',
            'currenttime' => $currenttime
        ];

        // Execute the query and return the results.
        return $DB->get_records_sql($sql, $parms);
    }

    /**
     * Check if interim grades are currently allowed for a specific period.
     *
     * @param string $academicperiodid The academic period ID to check.
     * @return bool True if interim grades are allowed at the current time.
     */
    public static function is_interim_grading_open($academicperiodid) {
        global $DB;

        // Get the configured start and end times for this period from our custom table.
        $record = $DB->get_record('block_wds_postgrades_periods', ['academic_period_id' => $academicperiodid]);

        // Get current time.
        $currenttime = time();

        // Check if record exists and current time is within the allowed range.
        if ($record && $record->start_time && $record->end_time) {
            return ($currenttime >= $record->start_time && $currenttime <= $record->end_time);
        }

        // Default to false if settings are not configured.
        return false;
    }

    /**
     * Get the status message for interim grading for a specific period.
     *
     * @param string $academicperiodid The academic period ID to check.
     * @return string Status message.
     */
    public static function get_interim_grading_status($academicperiodid) {
        global $DB;

        // Get the configured start and end times from our custom table.
        $record = $DB->get_record('block_wds_postgrades_periods', ['academic_period_id' => $academicperiodid]);

        // Get current time.
        $currenttime = time();

        if (!$record || !$record->start_time || !$record->end_time) {
            return get_string('interimgradesnotconfigured', 'block_wds_postgrades');
        } else if ($currenttime < $record->start_time) {
            $timeuntilstart = format_time($record->start_time - $currenttime);
            return get_string('interimgradesfuture', 'block_wds_postgrades', $timeuntilstart);
        } else if ($currenttime > $record->end_time) {
            return get_string('interimgradespast', 'block_wds_postgrades');
        } else {
            $timeuntilend = format_time($record->end_time - $currenttime);
            return get_string('interimgradesopen', 'block_wds_postgrades', $timeuntilend);
        }
    }
}
