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
 * Celebration overlay with the Huddy mascot (level-up and one-time milestones).
 *
 * @module     block_playerhud/levelup
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const OVERLAY_ID = 'ph-levelup-overlay';

/**
 * Per-type preset: which lang strings to use and whether the level number is
 * drawn over the shield in the artwork.
 *
 * @type {Object<string, {titleKey: string, descKey: string, showNumber: boolean}>}
 */
const PRESETS = {
    levelup: {titleKey: 'levelup_title', descKey: 'levelup_subtitle', showNumber: true},
    coin: {titleKey: 'coin_intro_title', descKey: 'coin_intro_desc', showNumber: false},
    win: {titleKey: 'win_title', descKey: 'win_desc', showNumber: false},
    firstquest: {titleKey: 'firstquest_title', descKey: 'firstquest_desc', showNumber: false},
    intro: {titleKey: 'intro_title', descKey: 'intro_desc', showNumber: false},
};

/**
 * Removes the current overlay, if any, and detaches its listeners.
 *
 * @param {HTMLElement} overlay The overlay element to dismiss.
 */
const dismiss = (overlay) => {
    if (!overlay || !overlay.parentNode) {
        return;
    }
    document.removeEventListener('keydown', overlay.phKeyHandler);
    overlay.classList.add('ph-levelup-out');
    const previousFocus = overlay.phPreviousFocus;
    // Remove after the fade-out transition; fall back to immediate removal.
    window.setTimeout(() => {
        if (overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
        }
        // Return focus to whatever was focused before the overlay opened.
        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }
    }, 300);
};

/**
 * Shows a celebration overlay with the mascot.
 *
 * Safe to call from both an AJAX response (item collection) and a page-load
 * trigger (quest claim flash). Re-entrant: any existing overlay is replaced.
 *
 * @param {Object} options Celebration descriptor.
 * @param {String} options.type Preset key (e.g. 'levelup', 'coin').
 * @param {String} options.image Absolute URL of the mascot image.
 * @param {Number} [options.level] Reached level (required by the 'levelup' preset).
 * @return {Promise<void>}
 */
export const celebrate = async(options) => {
    const opts = options || {};
    const preset = PRESETS[opts.type];
    if (!preset || !opts.image) {
        return;
    }

    const reachedLevel = parseInt(opts.level, 10);
    const showNumber = preset.showNumber && !isNaN(reachedLevel) && reachedLevel > 0;
    if (preset.showNumber && !showNumber) {
        return;
    }

    // Remember the focused element so it can be restored when the overlay closes.
    const previousFocus = document.activeElement;

    // Replace any overlay already on screen.
    const existing = document.getElementById(OVERLAY_ID);
    if (existing) {
        dismiss(existing);
    }

    const descArg = preset.showNumber ? reachedLevel : undefined;
    const [title, subtitle, closeLabel] = await Promise.all([
        getString(preset.titleKey, 'block_playerhud'),
        getString(preset.descKey, 'block_playerhud', descArg),
        getString('closebuttontitle', 'moodle'),
    ]);

    const titleId = `${OVERLAY_ID}-title`;
    const subtitleId = `${OVERLAY_ID}-subtitle`;

    const overlay = document.createElement('div');
    overlay.id = OVERLAY_ID;
    overlay.className = 'ph-levelup-overlay';
    overlay.phPreviousFocus = previousFocus;
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-labelledby', titleId);
    overlay.setAttribute('aria-describedby', subtitleId);

    const card = document.createElement('div');
    card.className = 'ph-levelup-card';

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ph-levelup-close';
    closeBtn.setAttribute('aria-label', closeLabel);
    closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';

    const titleEl = document.createElement('div');
    titleEl.id = titleId;
    titleEl.className = 'ph-levelup-title';
    titleEl.textContent = title;

    const figure = document.createElement('div');
    figure.className = 'ph-levelup-figure';

    const img = document.createElement('img');
    img.className = 'ph-levelup-img';
    img.src = opts.image;
    img.alt = '';

    figure.appendChild(img);

    // The level number sits over the (text-free) shield in the artwork.
    if (showNumber) {
        const number = document.createElement('span');
        number.className = 'ph-levelup-number';
        number.setAttribute('aria-hidden', 'true');
        number.textContent = `${reachedLevel}`;
        figure.appendChild(number);
    }

    const subtitleEl = document.createElement('div');
    subtitleEl.id = subtitleId;
    subtitleEl.className = 'ph-levelup-subtitle';
    subtitleEl.textContent = subtitle;

    card.appendChild(closeBtn);
    card.appendChild(titleEl);
    card.appendChild(figure);
    card.appendChild(subtitleEl);
    overlay.appendChild(card);
    document.body.appendChild(overlay);

    // Stays open until the user dismisses it: close button, a click on the
    // backdrop (outside the card) or the Escape key. Card clicks do not close.
    closeBtn.addEventListener('click', () => dismiss(overlay));
    overlay.addEventListener('click', (ev) => {
        if (ev.target === overlay) {
            dismiss(overlay);
        }
    });
    overlay.phKeyHandler = (ev) => {
        if (ev.key === 'Escape') {
            dismiss(overlay);
        } else if (ev.key === 'Tab') {
            // The close button is the only focusable control, so trap focus on it.
            ev.preventDefault();
            closeBtn.focus();
        }
    };
    document.addEventListener('keydown', overlay.phKeyHandler);

    // Trigger the entrance animation on the next frame so the transition runs,
    // then move focus to the close button for keyboard users.
    window.requestAnimationFrame(() => {
        overlay.classList.add('ph-levelup-in');
        closeBtn.focus();
    });
};
