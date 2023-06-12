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
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @author    André Storhaug <andr3.storhaug@gmail.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @copyright 2019 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2023033102; // The current module version (Date: YYYYMMDDXX).
$plugin->requires = 2022041900; // Moodle 4.0 (or above).
$plugin->cron = 0; // Period in seconds for cron to run.
$plugin->component = 'mod_jazzquiz';
$plugin->maturity = MATURITY_STABLE;
$plugin->release = '1.2.0 (Build: 2023033100)';

