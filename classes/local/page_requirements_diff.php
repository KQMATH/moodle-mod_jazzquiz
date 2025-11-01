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

namespace mod_jazzquiz\local;

use page_requirements_manager;

/**
 * Handles new page requirements without needing to refresh the page.
 *
 * To load a question without refreshing the page, we need the JavaScript for the question.
 * Moodle stores this in page_requirements_manager, but there is no way to read the JS that is required.
 * This class takes in the manager and keeps the JS for when we want to get a diff.
 *
 * This will only ever be used by {@see renderer::render_question_form()}
 *
 * @package   mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2018 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_requirements_diff extends page_requirements_manager {
    /** @var array */
    private array $beforeinitjs;

    /** @var array */
    private array $beforeamdjs;

    /** @var array */
    private array $beforecss;

    /**
     * Constructor.
     *
     * @param page_requirements_manager $manager
     */
    public function __construct(page_requirements_manager $manager) {
        $this->beforeinitjs = $manager->jsinitcode;
        $this->beforeamdjs = $manager->amdjscode;
        $this->beforecss = $manager->cssurls;
    }

    /**
     * Run an array_diff on the required JavaScript when this
     * was constructed and the one passed to this function.
     *
     * @param page_requirements_manager $manager
     * @return array the JavaScript that was added in-between constructor and this call.
     */
    public function get_js_diff(page_requirements_manager $manager): array {
        $jsinitcode = array_diff($manager->jsinitcode, $this->beforeinitjs);
        $amdjscode = array_diff($manager->amdjscode, $this->beforeamdjs);
        return array_merge($jsinitcode, $amdjscode);
    }

    /**
     * Run an array_diff on the required CSS when this was constructed and the one passed to this function.
     *
     * @param page_requirements_manager $manager
     * @return array the CSS that was added in-between constructor and this call.
     */
    public function get_css_diff(page_requirements_manager $manager): array {
        return array_keys(array_diff($manager->cssurls, $this->beforecss));
    }
}
