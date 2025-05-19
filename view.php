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

    // Now post the grades to Workday.
    $gradetype = 'interim';
    $result = \block_wds_postgrades\wdspg::post_grade($grades, $gradetype, $sectionlistingid);

    // Create results URL with appropriate parameters.
    $resultsurl = new moodle_url('/blocks/wds_postgrades/results.php', ['courseid' => $courseid]);

    // Prepare to store detailed results.
    $resultdata = new stdClass();

    // Handle response/result.
    if ($result === 'error') {
        $resultsurl->param('resulttype', 'error');
    } else if (is_object($result) && isset($result->error)) {
        $resultsurl->param('resulttype', 'error');
        $resultsurl->param('errorcode', $result->error);

        // Process detailed errors.
        if (isset($result->xmlstring)) {
            $errors = \block_wds_postgrades\wdspg::parseerrors($result->xmlstring);

            // Check for section status issues.
            $sectionstatus = \block_wds_postgrades\wdspg::pg_section_status($result->xmlstring);

            if ($sectionstatus) {
                $resultdata->section_status = get_string('sectiongraded', 'block_wds_postgrades', $sectionlistingid);
            }

            // Process error details.
            if (!empty($errors)) {
                $failures = array();
                $successful = array();

                foreach ($errors as $error) {
                    $errindex = $error->index;

                    if (is_numeric($errindex)) {

                        // Build the new object for the failure.
                        $stugrade = clone $grades[$errindex - 1];
                        $stugrade->errormessage = $error->message;
                        $failures[] = $stugrade;

                        // Remove this grade from the successful grades.
                        unset($grades[$errindex - 1]);
                    }
                }

                // Store failures and successes.
                $resultdata->errors = $failures;

                // Reindex array.
                $resultdata->successes = array_values($grades);
            }
        }
    } else {
        $resultsurl->param('resulttype', 'success');
        $resultsurl->param('sectionlistingid', $sectionlistingid);
        $resultdata->successes = $grades;
    }

    // Store result data in session for the results page.
    $SESSION->wds_postgrades_results = $resultdata;

    // Redirect to the results page.
    redirect($resultsurl);
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
