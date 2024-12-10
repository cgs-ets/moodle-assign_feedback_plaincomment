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
 * Copy the plain text submission to the plaincomment feedback
 *
 * @package   assignfeedback_plaincomment\task
 * @copyright 2024 Veronica Bermegui
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_plaincomment\task;

defined('MOODLE_INTERNAL') || die();

class cron_copy_to_gradefeedback extends \core\task\scheduled_task {


    use \core\task\logging_trait;
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cron_copy_to_gradefeedback', 'assignfeedback_plaincomment');
    }

    /**
     * Execute the scheduled task.
     */
    public function execute() {
        global $DB;

        $this->log('Starting cron_copy_to_gradefeedback task');

        // Get last run.
        $lastrun = $DB->get_field('config', 'value', ['name' => 'assignfeedback_plaincomment_copy_to_gradefeedback_lastrun']);

        if ($lastrun === false) {
                // First run ever.
                $DB->insert_record('config',
                                    ['name' => 'assignfeedback_plaincomment_copy_to_gradefeedback_lastrun',
                                    'value' => time()]);
                $lastrun = time();
        } else {
            // Immediately update last run time.
            $DB->execute("UPDATE {config}
                          SET value = ?
                          WHERE name = 'assignfeedback_plaincomment_copy_to_gradefeedback_lastrun'",
                           [time()]);
        }

        $this->make_update($lastrun);

        //  Get the plaincom
        $this->log('Finishing cron_copy_to_gradefeedback task');

        return 1;
    }

    /**
     * Undocumented function
     *
     * @return bool
     */
    public function can_run(): bool {
        return true;
    }


    /**
     * Get graded assignments with plain comment
     * feedback type and that have been updated after the
     * last time this job ran.
     *
     * @param mixed $lastrun
     * @return array
     */
    private function get_latest_assign_graded($lastrun) {
        global $DB;

        $sql = "SELECT pc.grade, ag.userid, pc.plaincomment, pc.assignment, ag.timemodified
                FROM {assignfeedback_plaincomment} pc
                JOIN {assign_grades} ag ON pc.grade = ag.id
                WHERE ag.timemodified >= :lastrun";

        $params = ['lastrun' => $lastrun];

        $result = $DB->get_records_sql($sql, $params);

        return $result;

    }

    /**
     * Get the grades for the the student with userid and the assignment with
     *  id iteminstance.
     * This has the feedback column we will need to change
     * @param mixed $iteminstance
     * @param mixed $userid
     * @return void
     */
    private function get_grade_grades($iteminstance, $userid) {
        global $DB;

        $sql = "SELECT g.*
                FROM {grade_grades} g,
                     {grade_items}  i
                WHERE g.itemid = i.id
                AND iteminstance = :iteminstance
                AND g.userid = :userid";

        $param = ['iteminstance' => $iteminstance, 'userid' => $userid];

        $result = $DB->get_record_sql($sql, $param);

        return $result;
    }

    /**
     * Update the  feedback column from  mdl_grade_grades  table.
    */
    private function update_grade_grades_feedback($dataobject, $feedback) {
        global $DB;

        // $dataobject->id = $dataobject->id;
        $dataobject->feedback = $feedback;
        $dataobject->feedbackformat = 0; // 0 = plaintext
        $dataobject->feedbackformat = 0;
        $grade = $DB->update_record('grade_grades',  $dataobject);

        $this->log("Record updated in grade_grades. ID: $dataobject->id  ", 1);

        return $grade;

    }
    /**
     *  Make the update
     *
     * @param mixed $lastrun
     * @return void
     */
    private function make_update($lastrun) {

        $assigngrades = $this->get_latest_assign_graded($lastrun);

        error_log(print_r("GET ASSIGN GRADES", true));
        error_log(print_r($assigngrades, true));
        foreach( $assigngrades as $assigngrade) {

            $gg = $this->get_grade_grades($assigngrade->assignment, $assigngrade->userid);
            $this->update_grade_grades_feedback($gg, $assigngrade->plaincomment);

        }
    }

}