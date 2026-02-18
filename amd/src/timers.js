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
 * Timer logic for PlayerHUD Block.
 *
 * @module     block_playerhud/timers
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(strings) {
            // Avoid running multiple intervals if init is called multiple times.
            if (window.phTimerInterval) {
                return;
            }

            // Update function.
            const updateTimers = () => {
                const now = Math.floor(Date.now() / 1000);
                let reloadNeeded = false;

                $('.ph-timer').each(function() {
                    const el = $(this);
                    // If already marked for reloading, skip.
                    if (el.data('reloading')) {
                        return;
                    }

                    const deadline = parseInt(el.attr('data-deadline'));
                    if (isNaN(deadline)) {
                        return;
                    }

                    const diff = deadline - now;
                    if (diff <= 0) {
                        // --- TIME UP ---
                        el.text(strings.ready);
                        el.removeClass('text-muted').addClass('text-success fw-bold');

                        // Mark element to avoid processing again.
                        el.data('reloading', true);
                        reloadNeeded = true;

                    } else {
                        // --- COUNTING ---
                        const m = Math.floor(diff / 60);
                        const s = diff % 60;
                        const timeString = m + 'm ' + (s < 10 ? '0' : '') + s + 's';

                        // Logic: Check if label "Next collection..." should be hidden.
                        // If data-no-label attribute exists, use empty string.
                        const showLabel = !el.attr('data-no-label');
                        const label = (showLabel && strings.label) ? strings.label + ' ' : '';

                        el.text(label + timeString);
                    }
                });

                // If any timer finished, schedule page reload.
                if (reloadNeeded) {
                    setTimeout(() => {
                        location.reload();
                    }, 1500); // Wait 1.5 seconds before reloading.
                }
            };

            // Start loop (1 second).
            window.phTimerInterval = setInterval(updateTimers, 1000);
            // Run immediately.
            updateTimers();
        }
    };
});
