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
 * This file contains the definition for the library class for plain feedback plugin
 *
 * @package   assignfeedback_plaincomment
 * @copyright 2024 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

// File component for feedback plaincomment.
define('ASSIGNFEEDBACK_PLAINCOMMENT_COMPONENT', 'assignfeedback_plaincomment');

// File area for feedback plaincomment.
define('ASSIGNFEEDBACK_PLAINCOMMENT_FILEAREA', 'feedback');

/**
 * Library class for plain feedback plugin extending feedback plugin base class.
 *
 * @package   assignfeedback_plaincomment
 * @copyright 2024 Michael Vangelovski
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_plaincomment extends assign_feedback_plugin {

    /**
     * Get the name of the online plain feedback plugin.
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_plaincomment');
    }

    /**
     * Get the feedback plain text rom the database.
     *
     * @param int $gradeid
     * @return stdClass|false The feedback plaincomment for the given grade if it exists.
     *                        False if it doesn't.
     */
    public function get_feedback_plaincomment($gradeid) {
        global $DB;
        return $DB->get_record('assignfeedback_plaincomment', array('grade'=>$gradeid));
    }

    /**
     * Get quickgrading form elements as html.
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param mixed $grade - The grade data - may be null if there are no grades for this user (yet)
     * @return mixed - A html string containing the html form elements required for quickgrading
     */
    public function get_quickgrading_html($userid, $grade) {
        $plaincomment = '';
        if ($grade) {
            $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
            if ($feedbackplaincomment) {
                $plaincomment = $feedbackplaincomment->plaincomment;
            }
        }

        $pluginname = get_string('pluginname', 'assignfeedback_plaincomment');
        $labeloptions = array('for'=>'quickgrade_plaincomment_' . $userid,
                              'class'=>'accesshide');
        $textareaoptions = array('name'=>'quickgrade_plaincomment_' . $userid,
                                 'id'=>'quickgrade_plaincomment_' . $userid,
                                 'class'=>'quickgrade');
        return html_writer::tag('label', $pluginname, $labeloptions) .
               html_writer::tag('textarea', $plaincomment, $textareaoptions);
    }

    /**
     * Has the plugin quickgrading form element been modified in the current form submission?
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param stdClass $grade The grade
     * @return boolean - true if the quickgrading form element has been modified
     */
    public function is_quickgrading_modified($userid, $grade) {
        $plaincomment = '';
        if ($grade) {
            $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
            if ($feedbackplaincomment) {
                $plaincomment = $feedbackplaincomment->plaincomment;
            }
        }
        // Note that this handles the difference between empty and not in the quickgrading
        // form at all (hidden column).
        $newvalue = optional_param('quickgrade_plaincomment_' . $userid, false, PARAM_RAW);
        return ($newvalue !== false) && ($newvalue != $plaincomment);
    }

    /**
     * Has the plain feedback been modified?
     *
     * @param stdClass $grade The grade object.
     * @param stdClass $data Data from the form submission.
     * @return boolean True if the plain feedback has been modified, else false.
     */
    public function is_feedback_modified(stdClass $grade, stdClass $data) {
        $plaincomment = '';
        if ($grade) {
            $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
            if ($feedbackplaincomment) {
                $plaincomment = $feedbackplaincomment->plaincomment;
            }
        }

        $formtext = $data->assignfeedbackplaincomment_textarea;

        if ($plaincomment == $formtext) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Override to indicate a plugin supports quickgrading.
     *
     * @return boolean - True if the plugin supports quickgrading
     */
    public function supports_quickgrading() {
        return true;
    }

    /**
     * Save quickgrading changes.
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param stdClass $grade The grade
     * @return boolean - true if the grade changes were saved correctly
     */
    public function save_quickgrading_changes($userid, $grade) {
        global $DB;
        $feedbackplain = $this->get_feedback_plaincomment($grade->id);
        $quickgradeplaincomment = optional_param('quickgrade_plaincomment_' . $userid, null, PARAM_RAW);
        if (!$quickgradeplaincomment && $quickgradeplaincomment !== '') {
            return true;
        }
        if ($feedbackplain) {
            $feedbackplain->plaincomment = $quickgradeplaincomment;
            return $DB->update_record('assignfeedback_plaincomment', $feedbackplain);
        } else {
            $feedbackplain = new stdClass();
            $feedbackplain->plaincomment = $quickgradeplaincomment;
            $feedbackplain->grade = $grade->id;
            $feedbackplain->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignfeedback_plaincomment', $feedbackplain) > 0;
        }
    }

    /**
     * Save the settings for feedback plaincomment plugin
     *
     * @param stdClass $data
     * @return bool
     */
    public function save_settings(stdClass $data) {
        $this->set_config('plaininline', !empty($data->assignfeedback_plaincomment_plaininline));

        /*if (empty($data->assignfeedback_plaincomment_wordlimit) || empty($data->assignfeedback_plaincomment_wordlimit_enabled)) {
            $wordlimit = 0;
            $wordlimitenabled = 0;
        } else {
            $wordlimit = $data->assignfeedback_plaincomment_wordlimit;
            $wordlimitenabled = 1;
        }

        $this->set_config('wordlimit', $wordlimit);
        $this->set_config('wordlimitenabled', $wordlimitenabled);*/

        return true;
    }

    /**
     * Get the default setting for feedback plaincomment plugin
     *
     * @param MoodleQuickForm $mform The form to add elements to
     * @return void
     */
    public function get_settings(MoodleQuickForm $mform) {
        $default = $this->get_config('plaininline');
        if ($default === false) {
            // Apply the admin default if we don't have a value yet.
            $default = get_config('assignfeedback_plaincomment', 'inline');
        }
        $mform->addElement('selectyesno',
                           'assignfeedback_plaincomment_plaininline',
                           get_string('plaininline', 'assignfeedback_plaincomment'));
        $mform->addHelpButton('assignfeedback_plaincomment_plaininline', 'plaininline', 'assignfeedback_plaincomment');
        $mform->setDefault('assignfeedback_plaincomment_plaininline', $default);
        // Disable plain online if plain feedback plugin is disabled.
        $mform->hideIf('assignfeedback_plaincomment_plaininline', 'assignfeedback_plaincomment_enabled', 'notchecked');


        /*$defaultwordlimit = $this->get_config('wordlimit') == 0 ? '' : $this->get_config('wordlimit');
        $defaultwordlimitenabled = $this->get_config('wordlimitenabled');

        $options = array('size' => '6', 'maxlength' => '6');
        $name = get_string('wordlimit', 'assignfeedback_plaincomment');

        // Create a text box that can be enabled/disabled for plaintext word limit.
        $wordlimitgrp = array();
        $wordlimitgrp[] = $mform->createElement('text', 'assignfeedback_plaincomment_wordlimit', '', $options);
        $wordlimitgrp[] = $mform->createElement('checkbox', 'assignfeedback_plaincomment_wordlimit_enabled',
                '', get_string('enable'));
        $mform->addGroup($wordlimitgrp, 'assignfeedback_plaincomment_wordlimit_group', $name, ' ', false);
        $mform->addHelpButton('assignfeedback_plaincomment_wordlimit_group',
                              'wordlimit',
                              'assignfeedback_plaincomment');
        $mform->disabledIf('assignfeedback_plaincomment_wordlimit',
                           'assignfeedback_plaincomment_wordlimit_enabled',
                           'notchecked');
        $mform->hideIf('assignfeedback_plaincomment_wordlimit',
                       'assignfeedback_plaincomment_enabled',
                       'notchecked');

        // Add numeric rule to text field.
        $wordlimitgrprules = array();
        $wordlimitgrprules['assignfeedback_plaincomment_wordlimit'][] = array(null, 'numeric', null, 'client');
        $mform->addGroupRule('assignfeedback_plaincomment_wordlimit_group', $wordlimitgrprules);

        // Rest of group setup.
        $mform->setDefault('assignfeedback_plaincomment_wordlimit', $defaultwordlimit);
        $mform->setDefault('assignfeedback_plaincomment_wordlimit_enabled', $defaultwordlimitenabled);
        $mform->setType('assignfeedback_plaincomment_wordlimit', PARAM_INT);
        $mform->hideIf('assignfeedback_plaincomment_wordlimit_group',
                       'assignfeedback_plaincomment_enabled',
                       'notchecked');
        */


   }

    /**
     * Convert the text from any submission plugin that has an editor field to
     * a format suitable for inserting in the feedback text field.
     *
     * @param stdClass $submission
     * @param stdClass $data - Form data to be filled with the converted submission text and format.
     * @param stdClass|null $grade
     * @return boolean - True if feedback text was set.
     */
    protected function convert_submission_text_to_feedback($submission, $data, $grade) {
        global $DB;

        $text = '';
        foreach ($this->assignment->get_submission_plugins() as $plugin) {
            $fields = $plugin->get_editor_fields();
            if ($plugin->is_enabled() && $plugin->is_visible() && !$plugin->is_empty($submission) && !empty($fields)) {
                $user = $DB->get_record('user', ['id' => $submission->userid]);
                foreach ($fields as $key => $description) {
                    $rawtext = clean_text($plugin->get_editor_text($key, $submission->id));
                    $text .= $rawtext;
                }
            }
        }
        
        $data->assignfeedbackplaincomment_textarea = $text;

        return true;
    }

    /**
     * Get form elements for the grading page
     *
     * @param stdClass|null $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool true if elements were added to the form
     */
    public function get_form_elements_for_user($grade, MoodleQuickForm $mform, stdClass $data, $userid) {
        $plaininlinenabled = $this->get_config('plaininline');
        $submission = $this->assignment->get_user_submission($userid, false);
        $feedbackplaincomment = false;

        if ($grade) {
            $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
        }

        // Check first for data from last form submission in case grading validation failed.
        if (!empty($data->assignfeedbackplaincomment_textarea)) {
            // Roll with it.
        } else if ($feedbackplaincomment && !empty($feedbackplaincomment->plaincomment)) {
            $data->assignfeedbackplaincomment_textarea = $feedbackplaincomment->plaincomment;
        } else {
            // No feedback given yet - maybe we need to copy the text from the submission?
            if (!empty($plaininlinenabled) && $submission) {
                $this->convert_submission_text_to_feedback($submission, $data, $grade);
            } else { // Set it to empty.
                $data->assignfeedbackplaincomment_textarea = '';
            }
        }

        $mform->addElement('textarea', 'assignfeedbackplaincomment_textarea', $this->get_name(), 'wrap="virtual" rows="10" cols="50"');
        //$mform->addRule('assignfeedbackplaincomment_textarea', "something", 'maxlength', 100, 'client');
        return true;
    }

    /**
     * Saving the plain content into database.
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $grade, stdClass $data) {
        global $DB;

        $feedbackplain = $this->get_feedback_plaincomment($grade->id);

        // Check word count before submitting anything.
        //$exceeded = $this->check_word_count(trim($data->assignfeedbackplaincomment_textarea));
        //if ($exceeded) {
        //    $this->set_error($exceeded);
        //    return false;
        //}

        if ($feedbackplain) {
            $feedbackplain->plaincomment = $data->assignfeedbackplaincomment_textarea;
            return $DB->update_record('assignfeedback_plaincomment', $feedbackplain);
        } else {
            $feedbackplain = new stdClass();
            $feedbackplain->plaincomment = $data->assignfeedbackplaincomment_textarea;
            $feedbackplain->grade = $grade->id;
            $feedbackplain->assignment = $this->assignment->get_instance()->id;
            return $DB->insert_record('assignfeedback_plaincomment', $feedbackplain) > 0;
        }
    }

    /**
     * Display the plain in the feedback table.
     *
     * @param stdClass $grade
     * @param bool $showviewlink Set to true to show a link to view the full feedback
     * @return string
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
        if ($feedbackplaincomment) {
            // Show the view all link if the text has been shortened.
            $short = shorten_text($feedbackplaincomment->plaincomment, 140);
            $showviewlink = $short != $feedbackplaincomment->plaincomment;
            return $short;
        }
        return '';
    }

    /**
     * Display the plain in the feedback table.
     *
     * @param stdClass $grade
     * @return string
     */
    public function view(stdClass $grade) {
        $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
        if ($feedbackplaincomment) {
            return $feedbackplaincomment->plaincomment;
        }
        return '';
    }

    /**
     * If this plugin adds to the gradebook comments field, it must specify the format of the text
     * of the comment
     *
     * Only one feedback plugin can push comments to the gradebook and that is chosen by the assignment
     * settings page.
     *
     * @param stdClass $grade The grade
     * @return int
     */
    public function format_for_gradebook(stdClass $grade) {
        return FORMAT_MOODLE;
    }

    /**
     * If this plugin adds to the gradebook plaincomment field, it must format the text
     * of the plain
     *
     * Only one feedback plugin can push plaincomment to the gradebook and that is chosen by the assignment
     * settings page.
     *
     * @param stdClass $grade The grade
     * @return string
     */
    public function text_for_gradebook(stdClass $grade) {
        $feedbackplaincomment = $this->get_feedback_plaincomment($grade->id);
        if ($feedbackplaincomment) {
            return $feedbackplaincomment->plaincomment;
        }
        return '';
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        // Will throw exception on failure.
        $DB->delete_records('assignfeedback_plaincomment',
                            array('assignment'=>$this->assignment->get_instance()->id));
        return true;
    }

    /**
     * Returns true if there are no feedback plaincomment for the given grade.
     *
     * @param stdClass $grade
     * @return bool
     */
    public function is_empty(stdClass $grade) {
        return $this->view($grade) == '';
    }

    /**
     * Get file areas returns a list of areas this plugin stores files
     * @return array - An array of fileareas (keys) and descriptions (values)
     */
    public function get_file_areas() {
        return array(ASSIGNFEEDBACK_PLAINCOMMENT_FILEAREA => $this->get_name());
    }

    /**
     * Return a description of external params suitable for uploading an feedback plain text from a webservice.
     *
     * @return \core_external\external_description|null
     */
    public function get_external_parameters() {
        return array(
            'assignfeedbackplaincomment_textarea' => new external_value(PARAM_RAW, 'The text for this feedback.')
        );
    }

    /**
     * Compare word count of plaintext submission to word limit, and return result.
     *
     * @param string $feedacktext PLAINTEXT submission text from editor
     * @return string Error message if limit is enabled and exceeded, otherwise null
     */
    public function check_word_count($feedacktext) {
        global $OUTPUT;

        $wordlimitenabled = $this->get_config('wordlimitenabled');
        $wordlimit = $this->get_config('wordlimit');

        if ($wordlimitenabled == 0) {
            return null;
        }

        // Count words and compare to limit.
        $wordcount = count_words($feedacktext);
        if ($wordcount <= $wordlimit) {
            return null;
        } else {
            $errormsg = get_string('wordlimitexceeded', 'assignfeedback_plaincomment',
                    array('limit' => $wordlimit, 'count' => $wordcount));
            //return $OUTPUT->error_text($errormsg);
            return $errormsg;
        }
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of settings
     * @since Moodle 3.2
     */
    public function get_config_for_external() {
        return (array) $this->get_config();
    }

}
