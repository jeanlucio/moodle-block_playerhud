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
 * Shows an error message inside an open Bootstrap modal.
 *
 * Opening a core/notification alert on top of a Bootstrap modal makes the two focus traps
 * fight, and jQuery throws "n.slice is not a function" while handling the focus events. The
 * message is shown in the modal's own error region instead, announced through role="alert".
 *
 * @module     block_playerhud/modal_error
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the error region of a modal.
 *
 * @param {string} modalSelector Selector of the modal.
 * @returns {HTMLElement|null}
 */
const getRegion = (modalSelector) => document.querySelector(`${modalSelector} [data-region="ph-modal-error"]`);

/**
 * Shows a message in the modal's error region.
 *
 * @param {string} modalSelector Selector of the modal.
 * @param {string} message Plain-text message.
 */
export const show = (modalSelector, message) => {
    const region = getRegion(modalSelector);
    if (!region) {
        return;
    }
    region.textContent = message;
    region.hidden = false;
};

/**
 * Hides the modal's error region.
 *
 * @param {string} modalSelector Selector of the modal.
 */
export const clear = (modalSelector) => {
    const region = getRegion(modalSelector);
    if (!region) {
        return;
    }
    region.textContent = '';
    region.hidden = true;
};
