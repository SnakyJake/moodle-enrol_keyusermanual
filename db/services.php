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
 * Manual plugin external functions and service definitions.
 *
 * @package    enrol_keyusermanual
 * @category   webservice
 * @copyright  2021 Jakob Heinemann, 2011 Jerome Mouneyrac
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    // === enrol related functions ===
    'enrol_keyusermanual_enrol_users' => array(
        'classname'   => 'enrol_keyusermanual_external',
        'methodname'  => 'enrol_users',
        'classpath'   => 'enrol/keyusermanual/externallib.php',
        'description' => 'keyuser manual enrol users',
        'capabilities'=> 'enrol/keyusermanual:enrol',
        'type'        => 'write',
    ),
    'enrol_keyusermanual_unenrol_users' => array(
        'classname'   => 'enrol_keyusermanual_external',
        'methodname'  => 'unenrol_users',
        'classpath'   => 'enrol/keyusermanual/externallib.php',
        'description' => 'keyuser manual unenrol users',
        'capabilities'=> 'enrol/keyusermanual:unenrol',
        'type'        => 'write',
    ),
    'enrol_keyusermanual_get_potential_users' => array(
        'classname'   => 'enrol_keyusermanual_external',
        'methodname'  => 'get_potential_users',
        'classpath'   => 'enrol/keyusermanual/externallib.php',
        'description' => 'Get the list of potential users to enrol',
        'capabilities'=> 'enrol/keyusermanual:enrol',
        'type'        => 'read',
        'ajax' => true,
    ),
);
