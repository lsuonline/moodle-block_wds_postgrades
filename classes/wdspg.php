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
     * Builds the XML structure for student grades to be posted to Workday.
     *
     * This function constructs the XML data required for posting either final or interim grades
     * to Workday's API. It processes each student grade and generates the appropriate XML structure
     * based on the grade type (finals or interim).
     *
     * @param @array $grades An array of grade objects containing student and grade information.
     * @param @string $gradetype The type of grades being posted ('finals' or any other value for interim).
     * @return @string The constructed XML string representing student grades data.
     */
    public static function buildgradestopost($grades, $gradetype) {
        $today = date('Y-m-d');

        $studentgrades = '';
        foreach ($grades as $grade) {

            // Student Registration Data.
            $sectionlistingid = $grade->section_listing_id;
            $universalid = $grade->universal_id;

            // Grade for the registration in question.
            $gradeid = $grade->grade_id;

            // Check to see if we're in finals or this is an interim grade.
            if ($gradetype == "finals") {

                // Posting final grades.
                $sdtype = "Student_Grades_Data";

                // If we have a last date of attendance set, send it.
                if (isset($grade->requires_last_attendance)) {
                    $ld = date('Y-m-d', $grade->last_attendance_date);
                    $ldoa = "<wd:Student_Last_Date_of_Attendance>$ld</wd:Student_Last_Date_of_Attendance>";
                } else {
                    $ldoa = "";
                }

                // If we have an interim grade note, use it.
                if (isset($grade->grade_note_required)) {
                    $note = $grade->grade_note_required;
                    $gnote = "<wd:Student_Grade_Note>$note</wd:Student_Grade_Note>";
                } else {
                    $gnote = "";
                }

                $gdate = "";
            } else {

                // Posting interim grades.
                $sdtype = "Student_Interim_Grades_Data";

                // If we have an interim grade note, use it.
                if (isset($grade->grade_note_required)) {
                    $note = $grade->grade_note_required;
                    $gnote = "<wd:Student_Interim_Grade_Note>$note</wd:Student_Interim_Grade_Note>";
                } else {
                    $gnote = "";
                }

                // Set the interim grade date to today and send it.
                $gdate =  "<wd:Student_Interim_Grade_Date>$today</wd:Student_Interim_Grade_Date>";

                // Last date of attendance never required for interim grades?
                $ldoa = "";
            }

            // Build out the xml.
            $studentsgrade = '
                            <wd:' . $sdtype . '>
                                <wd:Student_Reference>
                                    <wd:ID wd:type="Universal_Identifier_ID">' . $universalid . '</wd:ID>
                                </wd:Student_Reference>
                                <wd:Student_Grade_Reference>
                                    <wd:ID wd:type="Student_Grade_ID">' . $gradeid . '</wd:ID>
                                </wd:Student_Grade_Reference>
                                ' . $gnote . '
                                ' . $gdate . '
                                ' . $ldoa . '
                            </wd:' . $sdtype . '>';

            // Send this to the $studentgrades loop.
            $studentgrades .= $studentsgrade;
        }

        return $studentgrades;
    }

    /**
     * Grabs the workday student settings from config_plugins.
     *
     * @return @object $s
     */
    public static function get_settings() {
        $s = new stdClass();

        // Get the settings.
        $s = get_config('enrol_workdaystudent');

        return $s;
    }

    /**
     * Posts grades to Workday via SOAP API.
     *
     * This function sends the constructed grade data to Workday's API using a SOAP request.
     * It handles both final and interim grades, builds the necessary XML structure,
     * and processes the response.
     *
     * @param @object $s Object containing API credentials and configuration.
     * @param @array $grades Array of grade objects to be posted.
     * @param @string $gradetype Type of grades being posted ('finals' or any other value for interim).
     * @param @string $sectionlistingid The Workday Section Listing ID for the course section.
     * @return @string | @object The cleaned XML response string on success, error or object on failure.
     */
    public static function post_grade($grades, $gradetype, $sectionlistingid) {

        // Get settings.
        $s = self::get_settings();

        // Build out the xml.
        $xml = self::buildsoapxml($s, $grades, $gradetype, $sectionlistingid);

        // Workday API credentials.
        $username = $s->username . "@lsu14";
        $password = $s->password;

        $version = "v" . $s->apiversion;

        // TODO: Make this a setting.
        // Workday API endpoint for the Submit_Grades_for_Registrations SOAP operation.
        $workdayurl = "https://wd2-impl-services1.workday.com/ccx/service/lsu/Student_Records/$version";

        // Initiate the curl handler.
        $ch = curl_init($workdayurl);

        // Set cURL options for curl request.
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xml)
        ]);

        // Execute cURL request.
        $response = curl_exec($ch);

        // Get the http code for later.
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Store any curl error before closing the handle.
        $curlerrno = curl_errno($ch);
        $curlerror = $curlerrno ? curl_error($ch) : null;

        // Close the curl handle to free resources.
        curl_close($ch);

        // Check if the cURL request was successful.
        if(!is_null($curlerror)) {

            // Return the error.
            mtrace("cURL ERROR: $curlerror. Aborting.");
            return "error";

        // Check to see that we have a proper response.
        } else if ($httpcode != "200") {
            if ($httpcode != "500") {

                // Return the HTTP status code.
                mtrace("SERVER ERROR - HTTP Status Code: $httpcode. Aborting.");
                return "error";
            } else {

                // Clean the resulting response.
                $xmlstring = self::cleanxml($response);

                // Build an object to store the error code and XML string.
                $xmlobj = new \stdClass();

                // Add the error.
                $xmlobj->error = $httpcode;

                // Add ths XML string.
                $xmlobj->xmlstring = $xmlstring;

                return $xmlobj;
            }
        }

        // Clean the resulting response.
        $xmlstring = self::cleanxml($response);

        return $xmlstring;
    }

    /**
     * Builds the complete SOAP XML request for submitting grades to Workday.
     *
     * This function constructs the complete SOAP envelope with authentication headers and
     * the appropriate payload structure based on whether final or interim grades are being submitted.
     * It integrates the student grades data generated by buildgradestopost() into the full SOAP request.
     *
     * @param @object $s Object containing API credentials and configuration.
     * @param @array $grades Array of grade objects to be posted.
     * @param @string $gradetype Type of grades being posted ('finals' or any other value for interim).
     * @param @string $sectionlistingid The Workday Section Listing ID for the course section.
     * @return @string The complete SOAP XML request as a cleaned string.
     */
    public static function buildsoapxml($s, $grades, $gradetype, $sectionlistingid) {

        // Build out if it's interim or final grades.
        if ($gradetype == "finals") {
            $wdendpoint = "Submit_Grades_for_Registrations_Request";
            $wddata = "Submit_Grades_for_Registrations_Data";
            $bpparms = "<wd:Business_Process_Parameters>" .
                "<wd:Auto_Complete>true</wd:Auto_Complete>" .
                "<wd:Run_Now>true</wd:Run_Now>" .
                "</wd:Business_Process_Parameters>";
        } else {
            $wdendpoint = "Put_Interim_Grades_for_Registrations_Request";
            $wddata = "Put_Interim_Grades_for_Registrations_Data";
            $bpparms = "";
        }

        // Workday API credentials.
        $username = $s->username . "@lsu14";
        $password = $s->password;
        $version = "v" . $s->apiversion;

        // Build out the student grades portion of the xml.
        $gradesxml = self::buildgradestopost($grades, $gradetype);

        // Create SOAP Envelope.
        $xml = new SimpleXMLElement('<env:Envelope
            xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"
            xmlns:xsd="http://www.w3.org/2001/XMLSchema"
            xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                <env:Header>
                    <wsse:Security env:mustUnderstand="1">
                        <wsse:UsernameToken>
                            <wsse:Username>'
                                . $username .
                            '</wsse:Username>
                            <wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'
                                . $password .
                            '</wsse:Password>
                        </wsse:UsernameToken>
                    </wsse:Security>
                </env:Header>
                <env:Body>
                    <wd:' . $wdendpoint . '
                        xmlns:wd="urn:com.workday/bsvc"
                        wd:version="' . $version . '">
                        ' . $bpparms . '
                        <wd:' . $wddata . '>
                            <wd:Section_Listing_Reference>
                                <wd:ID wd:type="Section_Listing_ID">' . $sectionlistingid . '</wd:ID>
                            </wd:Section_Listing_Reference>
                            ' . $gradesxml . '
                        </wd:' . $wddata . '>
                    </wd:' . $wdendpoint . '>
                </env:Body>
           </env:Envelope>');

        // Convert SimpleXMLElement to string.
        $xmlstring = $xml->asXML();

        $xmlstr = self::cleanxml($xmlstring);

        // Return the XML as a string.
        return $xmlstr;
    }

    /**
     * Cleans and validates XML strings.
     *
     * This function processes XML strings to ensure they are well-formed and properly formatted.
     * It removes unwanted patterns (like '{+1}'), validates the XML structure using DOMDocument,
     * and formats the output for better readability.
     *
     * @param @string $xmlstring The XML string to be cleaned and validated.
     * @return @string | @null The cleaned and formatted XML string, or null if the XML is invalid.
     */
    public static function cleanxml($xmlstring) {

        // Use a regex to remove `{+1}` entirely.
        $xmlstring = preg_replace('/\{[^}]*\}/', '', $xmlstring);

        // Ensure that the XML is well-formed using DOMDocument.
        $dom = new DOMDocument('1.0', 'UTF-8');

        // Suppress warnings to handle them programmatically.
        libxml_use_internal_errors(true);

        // Load the XML string into the DOMDocument.
        if (!$dom->loadXML($xmlstring)) {

            // If there's an error loading XML, print the errors for debugging.
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                echo ("XML Error: " . $error->message . "\n");
            }

            libxml_clear_errors();

            // Return null if XML is invalid.
            return null;
        }

        // Format the output (pretty print) for easier reading.
        $dom->formatOutput = true;

        // Return the cleaned and formatted XML string.
        return $dom->saveXML();
    }

    /**
     * Get enrolled students and their associated data.
     *
     * @param @int $courseid The course ID.
     * @return @array The enrolled students data.
     */
    public static function get_enrolled_students($courseid) {
        global $DB;

        // Build out the parms for getting students.
        $params = ['courseid' => $courseid];

        // The sql for getting students.
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

        // Get em.
        $enrollments = $DB->get_records_sql($sql, $params);

        return $enrollments;
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

        // We don't have grades yet. Deal.
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

        // Format the grade according to different display types. Real.
        $formattedgrades->real = grade_format_gradevalue(
            $grade->finalgrade,
            $gradeitem,
            true,
            GRADE_DISPLAY_TYPE_REAL,
            $gradedecimalpoints
        );

        // Format the grade according to different display types. Percent.
        $formattedgrades->percent = grade_format_gradevalue(
            $grade->finalgrade,
            $gradeitem,
            true,
            GRADE_DISPLAY_TYPE_PERCENTAGE,
            $gradedecimalpoints
        );

        // Format the grade according to different display types. Letter.
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
