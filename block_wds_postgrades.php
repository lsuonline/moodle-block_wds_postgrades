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
 * WDS Post Grades Block for Moodle 4.5.
 *
 * This block displays student final grades in a tabular format.
 * It uses the Moodle gradebook API to retrieve grades properly formatted.
 *
 * @package    block_wds_postgrades
 * @copyright  2025 onwards Louisiana State University
 * @copyright  2025 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Block definition class for WDS Post Grades block.
 */
class block_wds_postgrades extends block_base {

    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_wds_postgrades');
    }

    /**
     * Indicates whether the block has settings.
     *
     * @return boolean True if the block has settings.
     */
    public function has_config() {
        return false;
    }

    /**
     * Returns the content object.
     *
     * @return stdClass The content object.
     */
    public function get_content() {
        global $COURSE, $OUTPUT;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Check capabilities.
        $context = context_course::instance($COURSE->id);
        if (!has_capability('block/wds_postgrades:view', $context)) {
            $this->content->text = get_string('nopermission', 'block_wds_postgrades');
            return $this->content;
        }

        // Create a link to the view page.
        $viewurl = new moodle_url('/blocks/wds_postgrades/view.php', ['courseid' => $COURSE->id]);
        $linktext = get_string('viewgrades', 'block_wds_postgrades');

        // Create a button to view grades.
        $button = $OUTPUT->single_button($viewurl, $linktext, 'get', ['class' => 'wdspgradesbutton']);

        $this->content->text = $button;

        return $this->content;
    }

    /**
     * This block can be added to courses only.
     *
     * @return array The array of applicable formats.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'mod' => false,
            'my' => false,
        ];
    }
}
