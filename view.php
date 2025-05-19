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

// Start output.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('gradesfor', 'block_wds_postgrades', $course->fullname));

// Generate and output the table.
$tablehtml = \block_wds_postgrades\wdspg::generate_grades_table($enrolledstudents, $courseid);
echo $tablehtml;

// Add a back button.
$courseurl = new moodle_url('/course/view.php', ['id' => $courseid]);
echo $OUTPUT->single_button($courseurl, get_string('backtocourse', 'block_wds_postgrades'), 'get');

// Complete output.
echo $OUTPUT->footer();
