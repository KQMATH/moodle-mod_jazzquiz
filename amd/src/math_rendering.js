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
 * @module    mod_jazzquiz
 * @author    Sebastian S. Gundersen <sebastian@sgundersen.com>
 * @copyright 2024 NTNU
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {notifyFilterContentUpdated} from 'core_filters/events';
import Ajax from 'core/ajax';
import selectors from 'mod_jazzquiz/selectors';

// Used for caching the latex of maxima input.
let cache = [];

/**
 * Triggers a dynamic content update event, which MathJax listens to.
 */
export function renderAllMathjax() {
    notifyFilterContentUpdated(document.querySelectorAll(selectors.quiz.responseContainer));
}

/**
 * Sets the body of the target, and triggers an event letting MathJax know about the element.
 * @param {HTMLElement} target
 * @param {string} latex
 */
export function addMathjaxElement(target, latex) {
    target.innerHTML = `<span class="filter_mathjaxloader_equation">${latex}</span>`;
    renderAllMathjax();
}

/**
 * Converts the input to LaTeX and renders it to the target with MathJax.
 * @param {string} input
 * @param {string} targetId
 */
export function renderMaximaEquation(input, targetId) {
    const target = document.getElementById(targetId);
    if (target === null) {
        return;
    }
    if (cache[input] !== undefined) {
        addMathjaxElement(document.getElementById(targetId), cache[input]);
        return;
    }
    Ajax.get('stack', {input: encodeURIComponent(input)}, data => {
        cache[data.original] = data.latex;
        addMathjaxElement(document.getElementById(targetId), data.latex);
    });
}
