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

define([], function() {

    /**
     * Tab config module for PlayerHUD — API key visibility toggle.
     *
     * @module     block_playerhud/tab_config
     * @copyright  2026 Jean Lúcio
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */

    /**
     * Attach click handlers to all show/hide toggle buttons.
     */
    function init() {
        document.querySelectorAll('.ph-toggle-key').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = btn.getAttribute('data-target');
                var input = document.getElementById(targetId);
                if (!input) {
                    return;
                }
                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                btn.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
            });
        });
    }

    return {
        init: init
    };
});
