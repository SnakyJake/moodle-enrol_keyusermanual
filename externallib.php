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
 * External course participation api.
 *
 * This api is mostly read only, the actual enrol and unenrol
 * support is in each enrol plugin.
 *
 * @package    enrol_keyusermanual
 * @category   external
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");

require_once($CFG->dirroot.'/enrol/locallib.php');
require_once($CFG->dirroot.'/user/lib.php');

require_once($CFG->dirroot.'/local/keyuser/locallib.php');

class keyuser_course_enrolment_manager extends course_enrolment_manager {
    /**
     * Gets an array of the users that can be enrolled in this course.
     *
     * @global moodle_database $DB
     * @param int $enrolid
     * @param string $search
     * @param bool $searchanywhere
     * @param int $page Defaults to 0
     * @param int $perpage Defaults to 25
     * @param int $addedenrollment Defaults to 0
     * @param bool $returnexactcount Return the exact total users using count_record or not.
     * @return array with two or three elements:
     *      int totalusers Number users matching the search. (This element only exist if $returnexactcount was set to true)
     *      array users List of user objects returned by the query.
     *      boolean moreusers True if there are still more users, otherwise is False.
     * @throws dml_exception
     */
    public function get_potential_users($enrolid, $search = '', $searchanywhere = false, $page = 0, $perpage = 25,
            $addedenrollment = 0, $returnexactcount = false) {
        global $DB,$USER;

        list($ufields, $params, $wherecondition) = $this->get_basic_search_conditions($search, $searchanywhere);

        keyuser_user_append_where($wherecondition, $params,'u');

        $fields      = 'SELECT '.$ufields;
        $countfields = 'SELECT COUNT(1)';
        $sql = " FROM {user} u
            LEFT JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = :enrolid)
                WHERE $wherecondition
                      AND ue.id IS NULL";
        $params['enrolid'] = $enrolid;

        return $this->execute_search_queries($search, $fields, $countfields, $sql, $params, $page, $perpage, $addedenrollment,
                $returnexactcount);
    }
}

/**
 * Manual enrolment external functions.
 *
 * @package    enrol_keyusermanual
 * @category   external
 * @copyright  2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.2
 */
class enrol_keyusermanual_external extends external_api {

    /**
     * Returns description of method parameters value
     *
     * @return external_description
     */
    public static function get_potential_users_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'course id'),
                'enrolid' => new external_value(PARAM_INT, 'enrolment id'),
                'search' => new external_value(PARAM_RAW, 'query'),
                'searchanywhere' => new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning'),
                'page' => new external_value(PARAM_INT, 'Page number'),
                'perpage' => new external_value(PARAM_INT, 'Number per page'),
            )
        );
    }

    /**
     * Get potential users.
     *
     * @param int $courseid Course id
     * @param int $enrolid Enrolment id
     * @param string $search The query
     * @param boolean $searchanywhere Match anywhere in the string
     * @param int $page Page number
     * @param int $perpage Max per page
     * @return array An array of users
     */
    public static function get_potential_users($courseid, $enrolid, $search, $searchanywhere, $page, $perpage) {
        global $PAGE, $DB, $CFG;

        $params = self::validate_parameters(
            self::get_potential_users_parameters(),
            array(
                'courseid' => $courseid,
                'enrolid' => $enrolid,
                'search' => $search,
                'searchanywhere' => $searchanywhere,
                'page' => $page,
                'perpage' => $perpage
            )
        );
        $context = context_course::instance($params['courseid']);
        try {
            self::validate_context($context);
        } catch (Exception $e) {
            $exceptionparam = new stdClass();
            $exceptionparam->message = $e->getMessage();
            $exceptionparam->courseid = $params['courseid'];
            throw new moodle_exception('errorcoursecontextnotvalid' , 'webservice', '', $exceptionparam);
        }
        require_capability('enrol/keyusermanual:enrol', $context);

        $course = $DB->get_record('course', array('id' => $params['courseid']));
        $manager = new keyuser_course_enrolment_manager($PAGE, $course);

        $users = $manager->get_potential_users($params['enrolid'],
                                               $params['search'],
                                               $params['searchanywhere'],
                                               $params['page'],
                                               $params['perpage']);

        $results = array();
        // Add also extra user fields.
        $requiredfields = array_merge(
            ['id', 'fullname', 'profileimageurl', 'profileimageurlsmall'],
            get_extra_user_fields($context)
        );
        foreach ($users['users'] as $id => $user) {
            // Note: We pass the course here to validate that the current user can at least view user details in this course.
            // The user we are looking at is not in this course yet though - but we only fetch the minimal set of
            // user records, and the user has been validated to have course:enrolreview in this course. Otherwise
            // there is no way to find users who aren't in the course in order to enrol them.
            if ($userdetails = user_get_user_details($user, $course, $requiredfields)) {
                $results[] = $userdetails;
            }
        }
        return $results;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_potential_users_returns() {
        global $CFG;
        require_once($CFG->dirroot . '/user/externallib.php');
        return new external_multiple_structure(core_user_external::user_description());
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     * @since Moodle 2.2
     */
    public static function enrol_users_parameters() {
        return new external_function_parameters(
                array(
                    'enrolments' => new external_multiple_structure(
                            new external_single_structure(
                                    array(
                                        'roleid' => new external_value(PARAM_INT, 'Role to assign to the user'),
                                        'userid' => new external_value(PARAM_INT, 'The user that is going to be enrolled'),
                                        'courseid' => new external_value(PARAM_INT, 'The course to enrol the user role in'),
                                        'timestart' => new external_value(PARAM_INT, 'Timestamp when the enrolment start', VALUE_OPTIONAL),
                                        'timeend' => new external_value(PARAM_INT, 'Timestamp when the enrolment end', VALUE_OPTIONAL),
                                        'suspend' => new external_value(PARAM_INT, 'set to 1 to suspend the enrolment', VALUE_OPTIONAL)
                                    )
                            )
                    )
                )
        );
    }

    /**
     * Enrolment of users.
     *
     * Function throw an exception at the first error encountered.
     * @param array $enrolments  An array of user enrolment
     * @since Moodle 2.2
     */
    public static function enrol_users($enrolments) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/enrollib.php');

        $params = self::validate_parameters(self::enrol_users_parameters(),
                array('enrolments' => $enrolments));

        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs
                                                           // (except if the DB doesn't support it).

        // Retrieve the manual enrolment plugin.
        $enrol = enrol_get_plugin('keyusermanual');
        if (empty($enrol)) {
            throw new moodle_exception('manualpluginnotinstalled', 'enrol_keyusermanual');
        }

        foreach ($params['enrolments'] as $enrolment) {
            // Ensure the current user is allowed to run this function in the enrolment context.
            $context = context_course::instance($enrolment['courseid'], IGNORE_MISSING);
            self::validate_context($context);

            // Check that the user has the permission to manual enrol.
            require_capability('enrol/keyusermanual:enrol', $context);

            // Throw an exception if user is not able to assign the role.
            $roles = get_assignable_roles($context);
            if (!array_key_exists($enrolment['roleid'], $roles)) {
                $errorparams = new stdClass();
                $errorparams->roleid = $enrolment['roleid'];
                $errorparams->courseid = $enrolment['courseid'];
                $errorparams->userid = $enrolment['userid'];
                throw new moodle_exception('wsusercannotassign', 'enrol_keyusermanual', '', $errorparams);
            }

            // Check manual enrolment plugin instance is enabled/exist.
            $instance = null;
            $enrolinstances = enrol_get_instances($enrolment['courseid'], true);
            foreach ($enrolinstances as $courseenrolinstance) {
              if ($courseenrolinstance->enrol == 'keyusermanual') {
                  $instance = $courseenrolinstance;
                  break;
              }
            }
            if (empty($instance)) {
              $errorparams = new stdClass();
              $errorparams->courseid = $enrolment['courseid'];
              throw new moodle_exception('wsnoinstance', 'enrol_keyusermanual', $errorparams);
            }

            // Check that the plugin accept enrolment (it should always the case, it's hard coded in the plugin).
            if (!$enrol->allow_enrol($instance)) {
                $errorparams = new stdClass();
                $errorparams->roleid = $enrolment['roleid'];
                $errorparams->courseid = $enrolment['courseid'];
                $errorparams->userid = $enrolment['userid'];
                throw new moodle_exception('wscannotenrol', 'enrol_keyusermanual', '', $errorparams);
            }

            // Finally proceed the enrolment.
            $enrolment['timestart'] = isset($enrolment['timestart']) ? $enrolment['timestart'] : 0;
            $enrolment['timeend'] = isset($enrolment['timeend']) ? $enrolment['timeend'] : 0;
            $enrolment['status'] = (isset($enrolment['suspend']) && !empty($enrolment['suspend'])) ?
                    ENROL_USER_SUSPENDED : ENROL_USER_ACTIVE;

            $enrol->enrol_user($instance, $enrolment['userid'], $enrolment['roleid'],
                    $enrolment['timestart'], $enrolment['timeend'], $enrolment['status']);

        }

        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     * @since Moodle 2.2
     */
    public static function enrol_users_returns() {
        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function unenrol_users_parameters() {
        return new external_function_parameters(array(
            'enrolments' => new external_multiple_structure(
                new external_single_structure(
                    array(
                        'userid' => new external_value(PARAM_INT, 'The user that is going to be unenrolled'),
                        'courseid' => new external_value(PARAM_INT, 'The course to unenrol the user from'),
                        'roleid' => new external_value(PARAM_INT, 'The user role', VALUE_OPTIONAL),
                    )
                )
            )
        ));
    }

    /**
     * Unenrolment of users.
     *
     * @param array $enrolments an array of course user and role ids
     * @throws coding_exception
     * @throws dml_transaction_exception
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     * @throws required_capability_exception
     * @throws restricted_context_exception
     */
    public static function unenrol_users($enrolments) {
        global $CFG, $DB;
        $params = self::validate_parameters(self::unenrol_users_parameters(), array('enrolments' => $enrolments));
        require_once($CFG->libdir . '/enrollib.php');
        $transaction = $DB->start_delegated_transaction(); // Rollback all enrolment if an error occurs.
        $enrol = enrol_get_plugin('keyusermanual');
        if (empty($enrol)) {
            throw new moodle_exception('manualpluginnotinstalled', 'enrol_keyusermanual');
        }

        foreach ($params['enrolments'] as $enrolment) {
            $context = context_course::instance($enrolment['courseid']);
            self::validate_context($context);
            require_capability('enrol/keyusermanual:unenrol', $context);
            $instance = $DB->get_record('enrol', array('courseid' => $enrolment['courseid'], 'enrol' => 'keyusermanual'));
            if (!$instance) {
                throw new moodle_exception('wsnoinstance', 'enrol_keyusermanual', $enrolment);
            }
            $user = $DB->get_record('user', array('id' => $enrolment['userid']));
            if (!$user) {
                throw new invalid_parameter_exception('User id not exist: '.$enrolment['userid']);
            }
            if (!$enrol->allow_unenrol($instance)) {
                throw new moodle_exception('wscannotunenrol', 'enrol_keyusermanual', '', $enrolment);
            }
            $enrol->unenrol_user($instance, $enrolment['userid']);
        }
        $transaction->allow_commit();
    }

    /**
     * Returns description of method result value.
     *
     * @return null
     */
    public static function unenrol_users_returns() {
        return null;
    }

}
