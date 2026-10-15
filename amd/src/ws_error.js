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
 * Tells the refusals PlayerHUD's web services raise on purpose apart from real failures.
 *
 * The services throw a moodle_exception with a translated message for outcomes a user reaches
 * through normal use: an item already spent, a choice no longer available, a feature the teacher
 * turned off. Those deserve a plain message. Notification.exception() is the generic dialog meant
 * for bugs: it titles the dialog with the raw error code and adds a stack trace in developer mode.
 *
 * @module     block_playerhud/ws_error
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {getString} from 'core/str';

/** @type {string[]} Error code prefixes of this plugin's user-facing refusals. */
const EXPECTED_PREFIXES = ['error_', 'story_error_', 'ai_error_'];

/** @type {string[]} User-facing refusals whose codes predate the prefixes. */
const EXPECTED_CODES = ['itemnotfound', 'item_not_usable'];

/**
 * Whether a web service failure is a refusal the plugin raised on purpose.
 *
 * @param {Object} ex The failure core/ajax rejected with.
 * @returns {boolean}
 */
export const isExpected = (ex) => {
    const code = ex && typeof ex.errorcode === 'string' ? ex.errorcode : '';
    return EXPECTED_CODES.includes(code) || EXPECTED_PREFIXES.some((prefix) => code.startsWith(prefix));
};

/**
 * Shows a web service failure: a plain alert for an expected refusal, the exception dialog otherwise.
 *
 * Do not call this while a Bootstrap modal is open: a core/notification dialog on top of it makes
 * the two focus traps fight (see block_playerhud/modal_error). Show the message inside the modal
 * and use isExpected() to decide whether the exception dialog is still needed.
 *
 * @param {Object} ex The failure core/ajax rejected with.
 */
export const show = async(ex) => {
    if (isExpected(ex)) {
        Notification.alert(await getString('error', 'moodle'), ex.message);
        return;
    }
    Notification.exception(ex);
};
