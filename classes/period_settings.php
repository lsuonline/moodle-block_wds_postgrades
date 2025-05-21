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

        // Build the SQL query.
        $sql = "SELECT id, academic_period_id
                FROM {enrol_wds_periods}
                WHERE enabled = :enabled
                AND start_date < :currenttime1
                AND end_date > :currenttime2";

        $parms = [
            'enabled' => '1',
            'currenttime1' => $currenttime,
            'currenttime2' => $currenttime
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
    public static function is_grading_open($section) {
        global $DB;

        // Get this.
        $academicperiodid = $section->academic_period_id;

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
    public static function get_grading_status($section) {
        global $DB;

        // Get this.
        $academicperiodid = $section->academic_period_id;

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
            $timer = ['typeword' => $section->gradetype, 'time' => $timeuntilend]; 
            return get_string('gradesopen', 'block_wds_postgrades', $timer);
        }
    }

    /**
     * Get the interim grading sections.
     *
     * @param @string $courseid The course ID to check.
     * @return @array of @objects the sections.
     */
    public static function get_interim_grading_sections($courseid) {
        global $DB;

        // Get all interim ready sections for this course.
        $isql = "SELECT DISTINCT
                sec.id,
                sec.section_listing_id,
                sec.section_number,
                sec.course_subject_abbreviation,
                cou.course_number,
                'Interim' AS gradetype
            FROM {enrol_wds_sections} sec
                INNER JOIN {enrol_wds_courses} cou
                    ON cou.course_listing_id = sec.course_listing_id
                INNER JOIN {block_wds_postgrades_periods} pp
                    ON pp.academic_period_id = sec.academic_period_id
                    AND pp.start_time < UNIX_TIMESTAMP()
                    AND pp.end_time > UNIX_TIMESTAMP()
            WHERE sec.moodle_status = :courseid
            ORDER BY sec.section_number ASC";

        $isections = $DB->get_records_sql($isql, ['courseid' => $courseid]);

        return $isections;
    }

    /**
     * Get the final grading sections.
     *
     * @param @string $courseid The course ID to check.
     * @return @array of @objects the sections.
     */
    public static function get_final_grading_sections($courseid) {
        global $DB;

        // Get all final ready sections for this course.
        $fsql = "SELECT DISTINCT
                sec.id,
                sec.section_listing_id,
                sec.section_number,
                sec.course_subject_abbreviation,
                cou.course_number,
                'Final' AS gradetype
            FROM {enrol_wds_sections} sec
                INNER JOIN {enrol_wds_courses} cou
                    ON cou.course_listing_id = sec.course_listing_id
                INNER JOIN {enrol_wds_pgc_dates} pp1
                    ON pp1.academic_period_id = sec.academic_period_id
                    AND pp1.date_type = 'Final Grading Start'
                    AND pp1.date < UNIX_TIMESTAMP()
                INNER JOIN {enrol_wds_pgc_dates} pp2
                    ON pp2.academic_period_id = sec.academic_period_id
                    AND pp2.date_type = 'Final Grading End'
                    AND pp2.date > UNIX_TIMESTAMP()
            WHERE sec.moodle_status = :courseid
            ORDER BY sec.section_number ASC";

        $fsections = $DB->get_records_sql($fsql, ['courseid' => $courseid]);

        return $fsections;
    }
}
