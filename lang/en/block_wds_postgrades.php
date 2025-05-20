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
 * Language strings for the WDS Post Grades block.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'WDS Post Grades';
$string['wds_postgrades:addinstance'] = 'Add a new WDS Post Grades block.';
$string['wds_postgrades:view'] = 'View WDS Post Grades block.';
$string['firstname'] = 'First Name';
$string['lastname'] = 'Last Name';
$string['fullname'] = 'Student';
$string['universalid'] = 'Universal ID';
$string['gradingscheme'] = 'Grading Scheme';
$string['gradingbasis'] = 'Grading Basis';
$string['grade'] = 'Workday Grade';
$string['finalgrade'] = 'Workday {$a->typeword} Grade';
$string['gradecode'] = 'Workday Grade Code';
$string['nograde'] = 'No grade';
$string['letter'] = 'Letter grade';
$string['nopermission'] = 'You do not have permission to view this information.';
$string['nostudents'] = '<strong>No graded students found in this course. Please add grades to the course.</strong>';
$string['nocoursegrade'] = 'No course grade item found.';
$string['viewgrades'] = 'View {$a->typeword} Grades';
$string['gradesfor'] = '{$a->typeword} Grades for {$a->sectiontitle}';
$string['viewgradesfor'] = 'View {$a->typeword} Grades for {$a->sectiontitle}';
$string['backtocourse'] = 'Back to course';

// Form stuffs.
$string['wds_postgrades:post'] = 'Post grades to Workday Student';
$string['postgrades'] = 'Post Grades to Workday';
$string['postgradessuccess'] = 'Grades successfully posted to Workday';
$string['postgradefailed'] = 'Failed to post grades to Workday';
$string['postgradeservererror'] = 'Server error occurred while posting grades: {$a->sectiontitle}';

// Results page stuffs.
$string['postgraderesults'] = 'Grade Posting Results';
$string['errordetails'] = 'Error Details';
$string['successdetails'] = 'Successfully Posted Grades';
$string['errormessage'] = 'Error Message';
$string['status'] = 'Status';
$string['gradeposted'] = 'Grade posted successfully';
$string['unknownerror'] = 'Unknown error occurred';
$string['sectionlisting'] = 'Section Listing: {$a->sectiontitle}';
$string['sectiongraded'] = 'All students already have grades for the section {$a->sectiontitle}.';

// Multiple section postings.
$string['postallgrades'] = 'Post all course grades';
$string['individualsections'] = 'Post grades by section';
$string['postgradesfor'] = 'Post Grades for {$a->sectiontitle}';
$string['viewgradesfor'] = 'View Grades for {$a->sectiontitle}';
$string['section'] = 'Section';
