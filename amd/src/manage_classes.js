// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * AMD module for the RPG Classes management tab.
 *
 * Wires delete buttons to a shared Bootstrap 5 confirmation modal,
 * populating it with the class name and the correct delete URL before showing.
 *
 * @module     block_playerhud/manage_classes
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
    return {
        /**
         * Initialise: bind delete buttons to the shared confirmation modal.
         */
        init: function() {
            var msgEl = document.getElementById('ph-delete-class-msg');
            var urlEl = document.getElementById('ph-delete-class-url');

            if (!msgEl || !urlEl) {
                return;
            }

            document.querySelectorAll('[data-action="delete-class"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    msgEl.textContent = btn.getAttribute('data-confirm-msg');
                    urlEl.href = btn.getAttribute('data-delete-url');
                });
            });
        }
    };
});
