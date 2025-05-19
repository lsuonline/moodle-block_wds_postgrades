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
 * WDS Post Grades utility class.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_wds_postgrades;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/grade/lib.php');
require_once($CFG->dirroot . '/grade/report/lib.php');

/**
 * Utility class for WDS Post Grades block operations.
 */
class wdspg {

    /**
     * Get enrolled students and their associated data.
     *
     * @param @int $courseid The course ID.
     * @return @array The enrolled students data.
     */
    public static function get_enrolled_students($courseid) {
        global $DB;

        $params = ['courseid' => $courseid];
        $sql = "SELECT
                    stuenr.id AS studentenrollid,
                    COALESCE(stu.preferred_firstname, stu.firstname) AS firstname,
                    COALESCE(stu.preferred_lastname, stu.lastname) AS lastname,
                    u.id AS userid,
                    stu.universal_id,
                    stuenr.grading_scheme,
                    stuenr.grading_basis,
                    sec.course_section_definition_id,
                    sec.section_listing_id,
                    gi.id AS coursegradeitem
                FROM {course} c
                INNER JOIN {enrol_wds_sections} sec
                    ON sec.moodle_status = c.id
                INNER JOIN {enrol_wds_student_enroll} stuenr
                    ON stuenr.section_listing_id = sec.section_listing_id
                    AND stuenr.status = 'enrolled'
                INNER JOIN {enrol_wds_students} stu
                    ON stu.universal_id = stuenr.universal_id
                INNER JOIN {user} u
                    ON stu.userid = u.id
                INNER JOIN {grade_items} gi
                    ON gi.courseid = c.id
                    AND gi.itemtype = 'course'
                WHERE
                    c.id = :courseid";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get the course grade item.
     *
     * @param @int $gradeitemid The grade item ID.
     * @return @object $formattedgrade The grade item object.
     */
    public static function get_course_grade_item($gradeitemid) {
        return \grade_item::fetch(['id' => $gradeitemid]);
    }

    /**
     * Get formatted grade for a student.
     *
     * @param @int $gradeitemid The grade item ID.
     * @param @int $userid The user ID.
     * @param @int $courseid The course ID.
     * @return @string The formatted grade.
     */
    public static function get_formatted_grade($gradeitemid, $userid, $courseid) {
        global $CFG;

        // Build this to store the formatted grades later.
        $formattedgrades = new \stdClass();
        $formattedgrades->real = get_string('nograde', 'block_wds_postgrades');
        $formattedgrades->percent = get_string('nograde', 'block_wds_postgrades');
        $formattedgrades->letter = get_string('nograde', 'block_wds_postgrades');

        // Get the grade item.
        $gradeitem = self::get_course_grade_item($gradeitemid);

        if ($gradeitem === false) {
            return $formattedgrades;
        }

        // Get the grade.
        $grade = new \grade_grade(['itemid' => $gradeitemid, 'userid' => $userid]);

        // Check if grade exists.
        if (!isset($grade->finalgrade) || $grade->finalgrade === null) {
            return $formattedgrades;
        }

        // Get grade decimal points setting.
        $gradedecimalpoints = grade_get_setting($courseid, 'decimalpoints', 2);

        // Format the grade according to different display types.
        $formattedgrades->real = grade_format_gradevalue(
            $grade->finalgrade,
            $gradeitem,
            true,
            GRADE_DISPLAY_TYPE_REAL,
            $gradedecimalpoints
        );
        $formattedgrades->percent = grade_format_gradevalue(
            $grade->finalgrade,
            $gradeitem,
            true,
            GRADE_DISPLAY_TYPE_PERCENTAGE,
            $gradedecimalpoints
        );
        $formattedgrades->letter = grade_format_gradevalue(
            $grade->finalgrade,
            $gradeitem,
            true,
            GRADE_DISPLAY_TYPE_LETTER,
            $gradedecimalpoints
        );

        return $formattedgrades;
    }

    /**
     * Get the required grade code for the grade in question.
     *
     * @param @object $student The student object with grade and section.
     * @param @object $finalgrade The student final grade with all variations.
     * @return @string The student's final grade code to be sent to WDS.
     */
    public static function get_graded_wds_gradecode($student, $finalgrade) {
        global $DB;

        // Set the table.
        $table = 'enrol_wds_grade_schemes';

        // Deal with graded ones 1st as they should always be 1:1.
        if ($student->grading_basis == 'Graded') {

            // Build out the parms for the graded codes.
            $parms = [
                'grading_scheme_id' => $student->grading_scheme,
                'grading_basis' => $student->grading_basis,
                'grade_display' => $finalgrade->letter
            ];

        // Pass / Fail grades.
        } else if ($student->grading_basis == 'Pass/Fail') {

            // Build out an array for passing grades.
            $keywordarray = [
                'A+' => 'Pass',
                'A' => 'Pass',
                'A-' => 'Pass',
                'B+' => 'Pass',
                'B' => 'Pass',
                'B-' => 'Pass',
                'C+' => 'Pass',
                'C' => 'Pass',
                'Pass' => 'Pass',
                'C-' => 'F',
                'D+' => 'F',
                'D' => 'F',
                'D-' => 'F',
                'F' => 'F',
                'Fail' => 'F'
            ];

            // Get the appropriate keyword to use to look up the code.
            $pfletter = $keywordarray[$finalgrade->letter] ?? 'Unknown';

            // Build out the parms for the PF codes.
            $parms = [
                'grading_scheme_id' => $student->grading_scheme,
                'grading_basis' => $student->grading_basis,
                'grade_display' => $pfletter
            ];

        // Auditors.
        } else if ($student->grading_basis == 'Audit') {

            // Build out the parms for the PF codes.
            $parms = [
                'grading_scheme_id' => $student->grading_scheme,
                'grading_basis' => $student->grading_basis,
                'grade_display' => 'Audit'
            ];
        }

        // Get the data.
        $gradecode = $DB->get_records($table, $parms);

        if (count($gradecode) > 1) {

echo"<pre>";
var_dump($gradecode);
echo"</pre>";
mtrace("More than one record returned.");
die();

        } else {
            $gradecode = reset($gradecode);
        }

        return $gradecode;
    }

    /**
     * Generate HTML table for the grades.
     *
     * @param array $enrolledstudents Array of enrolled students.
     * @param int $courseid The course ID.
     * @return string HTML representation of the table.
     */
    public static function generate_grades_table($enrolledstudents, $courseid) {
        if (empty($enrolledstudents)) {
            return get_string('nostudents', 'block_wds_postgrades');
        }

        $table = new \html_table();
        $table->attributes['class'] = 'wdspgrades generaltable';
        $table->head = [
            get_string('firstname', 'block_wds_postgrades'),
            get_string('lastname', 'block_wds_postgrades'),
            get_string('universalid', 'block_wds_postgrades'),
            get_string('gradingscheme', 'block_wds_postgrades'),
            get_string('gradingbasis', 'block_wds_postgrades'),
            get_string('real', 'grades'),
            get_string('percentage', 'grades'),
            get_string('letter', 'grades'),
            get_string('finalgrade', 'block_wds_postgrades'),
            get_string('gradecode', 'block_wds_postgrades')
        ];

        // Get course grade item from first student.
        $firststudent = reset($enrolledstudents);
        $coursegradeitemid = $firststudent->coursegradeitem;

        // Check if we have a valid grade item.
        $gradeitem = self::get_course_grade_item($coursegradeitemid);

        // We have no grades. Rethink your life.
        if ($gradeitem === false) {
            return get_string('nocoursegrade', 'block_wds_postgrades');
        }

        // Build the table rows.
        foreach ($enrolledstudents as $student) {

            // Get the formatted grade.
            $finalgrade = self::get_formatted_grade($student->coursegradeitem, $student->userid, $courseid);

            // Get the grade code.
            $gradecode = self::get_graded_wds_gradecode($student, $finalgrade);

            // Build out the table.
            $tablerow = [
                $student->firstname,
                $student->lastname,
                $student->universal_id,
                $student->grading_scheme,
                $student->grading_basis,
                $finalgrade->real,
                $finalgrade->percent,
                $finalgrade->letter,
                $gradecode->grade_display,
                $gradecode->grade_id,
            ];

            // Populate it.
            $table->data[] = $tablerow;
        }

        // Burn it to disk.
        return \html_writer::table($table);
    }
}
