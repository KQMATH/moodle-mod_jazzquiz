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
 * Upgrade script for JazzQuiz.
 *
 * @package mod_jazzquiz
 * @copyright 2024 NTNU
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * The upgrade script for JazzQuiz.
 *
 * @package mod_jazzquiz
 * @param int $oldversion
 */
function xmldb_jazzquiz_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();
    if ($oldversion < 2019061911) {
        // Add anonymity field to session.
        $table = new xmldb_table('jazzquiz_sessions');
        $field = new xmldb_field('anonymity', XMLDB_TYPE_INTEGER, 10, null, true, null, 1);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add allowguests field to session.
        $table = new xmldb_table('jazzquiz_sessions');
        $field = new xmldb_field('allowguests', XMLDB_TYPE_INTEGER, 10, null, true, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add cfganonymity field to jazzquiz instance.
        $table = new xmldb_table('jazzquiz');
        $field = new xmldb_field('cfganonymity', XMLDB_TYPE_INTEGER, 10, null, true, null, 1);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add cfgallowguests field to jazzquiz instance.
        $table = new xmldb_table('jazzquiz');
        $field = new xmldb_field('cfgallowguests', XMLDB_TYPE_INTEGER, 10, null, true, null, 0);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Remove attempt's userid foreign key, change 'not null' to false, then add the foreign key back.
        $table = new xmldb_table('jazzquiz_attempts');
        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, 11, false, false);
        $key = new xmldb_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $dbman->drop_key($table, $key);
        $dbman->change_field_notnull($table, $field);
        $dbman->add_key($table, $key);

        // Remove responded_count from attempt.
        $table = new xmldb_table('jazzquiz_attempts');
        $field = new xmldb_field('responded_count', XMLDB_TYPE_INTEGER, 11, null, false);
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Add guest session id to attempt.
        $table = new xmldb_table('jazzquiz_attempts');
        $field = new xmldb_field('guestsession', XMLDB_TYPE_CHAR, 50, null, false);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add attendance table.
        $table = new xmldb_table('jazzquiz_attendance');
        $table->add_field('id', XMLDB_TYPE_INTEGER, 11, false, true, true);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, 11, false, true);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, 11);
        $table->add_field('numresponses', XMLDB_TYPE_INTEGER, 11, false, true, false, 0);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_Key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'jazzquiz_sessions', ['id']);
        $table->add_Key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2019061911, 'jazzquiz');
    }
    return true;
}
