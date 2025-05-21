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
$sectionid = required_param('sectionid', PARAM_INT);
$gradetype = required_param('gradetype', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

// Get course.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Set the table.
$stable = 'enrol_wds_sections';
$ctable = 'enrol_wds_courses';

// Build out the section parms.
$sparms = ['id' => $sectionid, 'moodle_status' => $courseid];

// Get section details.
$section = $DB->get_record($stable, $sparms, '*', MUST_EXIST);

// Build out the course parms.
$cparms = ['course_listing_id' => $section->course_listing_id];

// Get course details.
$wdscourse = $DB->get_record($ctable, $cparms, '*', MUST_EXIST);

$sectiontitle = $section->course_subject_abbreviation .
    ' ' .
    $wdscourse->course_number .
    ' ' .
    $section->section_number;

// Build out the typeword for the lang string.
If ($gradetype == 'interim') {
    $typeword = 'Interim';
} else {
    $typeword = 'Final';
}

// We need this.
$section->gradetype = $typeword;

$stringvar = [
    'coursename' => $course->fullname,
    'sectiontitle' => $sectiontitle,
    'typeword' => $typeword
];

// Setup page.
$PAGE->set_url(new moodle_url('/blocks/wds_postgrades/view.php',
    ['courseid' => $courseid, 'sectionid' => $sectionid, 'gradetype' => $gradetype]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_course($course);
$PAGE->set_pagelayout('standard');

// Set appropriate title for a specific section.
$PAGE->set_title(get_string('viewgradesfor', 'block_wds_postgrades', $stringvar));
//$PAGE->set_heading(get_string('viewgradesfor', 'block_wds_postgrades', $stringvar));

$PAGE->navbar->add(get_string('pluginname', 'block_wds_postgrades'));
$PAGE->navbar->add($sectiontitle);

// Check permissions.
require_login($course);
require_capability('block/wds_postgrades:view', $PAGE->context);

// Get enrolled students data - filtered by section if section ID is provided.
$enrolledstudents = \block_wds_postgrades\wdspg::get_enrolled_students($courseid, $sectionid);

// Process form submission if the post grades action is triggered.
if ($action === 'postgrades' && confirm_sesskey()) {

    // Check if user has capability to post grades.
    require_capability('block/wds_postgrades:post', $PAGE->context);

    // Array to store the grade objects.
    $grades = array();

    // Properly set the section listing id.
    $sectionlistingid = $section->section_listing_id;

    // Process each student's grade.
    foreach ($enrolledstudents as $student) {

        // Get the student's formatted grade.
        $finalgrade = \block_wds_postgrades\wdspg::get_formatted_grade(
            $student->coursegradeitem,
            $student->userid,
            $courseid
        );

        // Get the grade code.
        $gradecode = \block_wds_postgrades\wdspg::get_graded_wds_gradecode($student, $finalgrade);

        // Create grade object.
        $gradeobj = new stdClass();
        $gradeobj->section_listing_id = $student->section_listing_id;
        $gradeobj->universal_id = $student->universal_id;
        $gradeobj->student_fullname = $student->firstname . ' ' . $student->lastname;
        $gradeobj->grade_id = $gradecode->grade_id;
        $gradeobj->grade_display = $gradecode->grade_display;

        // If we're required to post a note.
        if ($gradecode->grade_note_required == "1") {

            // Set this so we can use isset later.
            $gradeobj->grade_note_required = $gradecode->grade_note_required;
        }

        // If we're required to post a last attendance date.
        if ($gradecode->requires_last_attendance == "1" && $gradetype == "final") {

            // Set this so we can use isset later.
            $gradeobj->requires_last_attendance = $gradecode->requires_last_attendance;

            // Set this to the date they last accessed the course in Moodle.
            $gradeobj->last_attendance_date = \block_wds_postgrades\wdspg::get_wds_sla(
                $student->userid, $student->courseid
            );

            $gradeobj->wdladate = date('Y-m-d', $gradeobj->last_attendance_date);
        }

        // Add to grades array.
        $grades[] = $gradeobj;
    }

    // Use the new method to post grades based on the configured posting method.
    $resultdata = \block_wds_postgrades\wdspg::post_grades_with_method($grades, $gradetype, $sectionlistingid);

    // Create results URL with appropriate parameters.
    $resultsurl = new moodle_url(
        '/blocks/wds_postgrades/results.php',
        ['courseid' => $courseid, 'sectionid' => $section->id, 'gradetype' => $gradetype]);

    // Add section title for context in results page.
    $resultsurl->param('sectiontitle', $sectiontitle);
    $resultsurl->param('typeword', $typeword);

    // Determine overall result type.
    if (empty($resultdata->failures) && !empty($resultdata->successes)) {
        $resultsurl->param('resulttype', 'success');
    } else if (!empty($resultdata->failures) && !empty($resultdata->successes)) {
        $resultsurl->param('resulttype', 'partial');
    } else {
        $resultsurl->param('resulttype', 'error');
    }

    // Add section listing ID for reference.
    $resultsurl->param('sectionlistingid', $sectionlistingid);

    // Store result data in session for the results page.
    $SESSION->wds_postgrades_results = $resultdata;

    // Redirect to the results page.
    redirect($resultsurl);
}

// Start output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradesfor', 'block_wds_postgrades', $stringvar));

// Check if grades posting is allowed for this period.
$isopen = \block_wds_postgrades\period_settings::is_grading_open($section);
$openstatus = \block_wds_postgrades\period_settings::get_grading_status($section);

// Display status message about interim grades availability.
echo $OUTPUT->notification($openstatus, $isopen ? 'info' : 'warning');

// Only show the form if interim grades are available.
if ($isopen) {

    // Start form.
    $formaction = new moodle_url('/blocks/wds_postgrades/view.php');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formaction]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sectionid', 'value' => $sectionid]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'gradetype', 'value' => $gradetype]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'postgrades']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // Generate and output the table.
    $tablehtml = \block_wds_postgrades\wdspg::generate_grades_table($enrolledstudents, $courseid);
    echo $tablehtml;

    // Add a container for buttons.
    echo html_writer::start_div('buttons');

    // Post Grades button (only visible if user has the capability to post grades).
    if (has_capability('block/wds_postgrades:post', $PAGE->context) && !empty($enrolledstudents)) {
        echo html_writer::tag('button', get_string('postgrades', 'block_wds_postgrades'),
            ['type' => 'submit', 'class' => 'btn btn-primary']);
        echo ' ';
    }

    // End the form.
    echo html_writer::end_tag('form');
} else {
    echo $OUTPUT->notification(get_string('gradesnotavailable', 'block_wds_postgrades', $typeword), 'error');
}

// Back to course button (outside the form).
$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
echo $OUTPUT->single_button($courseurl, get_string('backtocourse', 'block_wds_postgrades'), 'get');

echo html_writer::end_div();

// Complete output.
echo $OUTPUT->footer();
