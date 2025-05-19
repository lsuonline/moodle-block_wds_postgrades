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
 * View page for WDS Post Grades block.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/wds_postgrades/classes/wdspg.php');

// Get parameters.
$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Get course.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Setup page.
$PAGE->set_url(new moodle_url('/blocks/wds_postgrades/view.php', ['courseid' => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('viewgradesfor', 'block_wds_postgrades', $course->fullname));
$PAGE->set_heading(get_string('viewgradesfor', 'block_wds_postgrades', $course->fullname));
$PAGE->navbar->add(get_string('pluginname', 'block_wds_postgrades'));

// Check permissions.
require_login($course);
require_capability('block/wds_postgrades:view', $PAGE->context);

// Get enrolled students data.
$enrolledstudents = \block_wds_postgrades\wdspg::get_enrolled_students($courseid);

// Process form submission if the post grades action is triggered.
if ($action === 'postgrades' && confirm_sesskey()) {
    // Check if user has capability to post grades.
    require_capability('block/wds_postgrades:post', $PAGE->context);

    // Array to store the grade objects.
    $grades = array();

    // Get section listing ID from the first student (assuming all students in this view are from the same section).
    $sectionlistingid = '';
    if (!empty($enrolledstudents)) {
        $firststudent = reset($enrolledstudents);
        $sectionlistingid = $firststudent->section_listing_id;
    }

    // Process each student's grade.
    foreach ($enrolledstudents as $student) {
        // Get the student's formatted grade.
        $finalgrade = \block_wds_postgrades\wdspg::get_formatted_grade($student->coursegradeitem, $student->userid, $courseid);

        // Get the grade code.
        $gradecode = \block_wds_postgrades\wdspg::get_graded_wds_gradecode($student, $finalgrade);

        // Create grade object.
        $gradeobj = new stdClass();
        $gradeobj->section_listing_id = $student->section_listing_id;
        $gradeobj->universal_id = $student->universal_id;
        $gradeobj->grade_id = $gradecode->grade_id;

        // Add to grades array.
        $grades[] = $gradeobj;
    }

/*
echo"<pre>";
var_dump($grades);
echo"</pre>";
die();
*/

    // Now post the grades to Workday.
    $result = \block_wds_postgrades\wdspg::post_grade($grades, 'interim', $sectionlistingid);

    // Handle response/result.
    if ($result === 'error') {
        \core\notification::error(get_string('postgradefailed', 'block_wds_postgrades'));
    } else if (is_object($result) && isset($result->error)) {
        \core\notification::error(get_string('postgradeservererror', 'block_wds_postgrades', $result->error));
    } else {
        \core\notification::success(get_string('postgradessuccess', 'block_wds_postgrades'));
    }

    // Redirect back to the same page to prevent form resubmission.
    redirect(new moodle_url('/blocks/wds_postgrades/view.php', ['courseid' => $courseid]));
}

// Start output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradesfor', 'block_wds_postgrades', $course->fullname));

// Start form.
$formaction = new moodle_url('/blocks/wds_postgrades/view.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formaction]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'postgrades']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Generate and output the table.
$tablehtml = \block_wds_postgrades\wdspg::generate_grades_table($enrolledstudents, $courseid);
echo $tablehtml;

// Add a container for buttons.
echo html_writer::start_div('buttons');

// Post Grades button (only visible if user has the capability to post grades).
if (has_capability('block/wds_postgrades:post', $PAGE->context)) {
    echo html_writer::tag('button', get_string('postgrades', 'block_wds_postgrades'),
        ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo ' ';
}

// End the form.
echo html_writer::end_tag('form');

// Back to course button (outside the form).
$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
echo $OUTPUT->single_button($courseurl, get_string('backtocourse', 'block_wds_postgrades'), 'get');

echo html_writer::end_div();

// Complete output.
echo $OUTPUT->footer();
