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
 * Octalysis coverage octagon for the gamification wizard modal.
 *
 * Builds an 8-segment SVG octagon that lights up according to which module checkboxes are
 * checked (via their data-drives attribute), plus a coverage score bar and text warnings for
 * uncovered drives. Purely a read-only visualisation of the wizard form's own state — it never
 * calls the server and does not affect what the wizard actually ends up generating.
 *
 * @module     block_playerhud/wizard_octalysis
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {
    'use strict';

    /** @var {Object[]} The 8 Octalysis core drives, in wheel order starting at the top. */
    const DRIVES = [
        {id: 1, color: '#e8a020', labelkey: 'wizard_octalysis_drive1_label'},
        {id: 2, color: '#d44e2a', labelkey: 'wizard_octalysis_drive2_label'},
        {id: 3, color: '#4a9eca', labelkey: 'wizard_octalysis_drive3_label'},
        {id: 4, color: '#6bb86b', labelkey: 'wizard_octalysis_drive4_label'},
        {id: 5, color: '#9b5fc0', labelkey: 'wizard_octalysis_drive5_label'},
        {id: 6, color: '#d44a8a', labelkey: 'wizard_octalysis_drive6_label'},
        {id: 7, color: '#3dbfb8', labelkey: 'wizard_octalysis_drive7_label'},
        {id: 8, color: '#c9a84c', labelkey: 'wizard_octalysis_drive8_label'},
    ];

    /**
     * Converts a wheel angle/radius into SVG x/y coordinates.
     *
     * @param {number} cx Circle centre x.
     * @param {number} cy Circle centre y.
     * @param {number} angle Angle in degrees, 0 = top.
     * @param {number} r Radius.
     * @return {number[]} [x, y].
     */
    const polar = (cx, cy, angle, r) => {
        const rad = (angle - 90) * Math.PI / 180;
        return [cx + r * Math.cos(rad), cy + r * Math.sin(rad)];
    };

    /**
     * Builds the 8 octagon segment paths and their labels into the given SVG element.
     *
     * @param {SVGElement} svg The target SVG element (assumed empty).
     * @param {Object[]} drives DRIVES, each with a resolved `label` string.
     * @param {string} centerlabel Resolved text for the octagon's centre label.
     */
    const buildSegments = (svg, drives, centerlabel) => {
        const cx = 170;
        const cy = 170;
        const outerradius = 150;
        const innerradius = 56;
        const gap = 1.8;

        drives.forEach((drive, index) => {
            const a0 = (index * 45) - 22.5;
            const a1 = a0 + 45;
            const [ox0, oy0] = polar(cx, cy, a0 + gap, innerradius);
            const [ox1, oy1] = polar(cx, cy, a1 - gap, innerradius);
            const [ix0, iy0] = polar(cx, cy, a0 + gap, outerradius);
            const [ix1, iy1] = polar(cx, cy, a1 - gap, outerradius);

            const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            path.setAttribute('d', `M ${ox0} ${oy0} L ${ix0} ${iy0} ` +
                `A ${outerradius} ${outerradius} 0 0 1 ${ix1} ${iy1} L ${ox1} ${oy1} ` +
                `A ${innerradius} ${innerradius} 0 0 0 ${ox0} ${oy0} Z`);
            path.setAttribute('fill', drive.color);
            path.setAttribute('class', 'ph-oct-segment ph-oct-active');
            path.setAttribute('id', `ph-oct-seg-${drive.id}`);
            path.setAttribute('tabindex', '0');
            path.setAttribute('role', 'img');
            svg.appendChild(path);

            const mid = (a0 + a1) / 2;
            const labelradius = ((outerradius + innerradius) / 2) + 4;
            const [lx, ly] = polar(cx, cy, mid, labelradius);
            const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            text.setAttribute('x', lx);
            text.setAttribute('y', ly);
            text.setAttribute('class', 'ph-oct-label');
            text.setAttribute('id', `ph-oct-lbl-${drive.id}`);

            const words = drive.label.split(' ');
            if (words.length > 1) {
                const half = Math.ceil(words.length / 2);
                [words.slice(0, half).join(' '), words.slice(half).join(' ')].forEach((line, lineindex) => {
                    const tspan = document.createElementNS('http://www.w3.org/2000/svg', 'tspan');
                    tspan.setAttribute('x', lx);
                    tspan.setAttribute('dy', lineindex === 0 ? '-4' : '10');
                    tspan.textContent = line;
                    text.appendChild(tspan);
                });
            } else {
                text.textContent = drive.label;
            }
            svg.appendChild(text);
        });

        const ring = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        ring.setAttribute('cx', cx);
        ring.setAttribute('cy', cy);
        ring.setAttribute('r', innerradius - 2);
        ring.setAttribute('fill', 'var(--body-bg, #fff)');
        ring.setAttribute('stroke', 'var(--border-color, #dee2e6)');
        svg.appendChild(ring);

        const centertext = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        centertext.setAttribute('x', cx);
        centertext.setAttribute('y', cy);
        centertext.setAttribute('class', 'ph-oct-center-label');
        centertext.textContent = centerlabel;
        svg.appendChild(centertext);
    };

    /**
     * Initialises the Octalysis panel: builds the SVG once, then wires every module checkbox
     * with a data-drives attribute to recompute the active drive set on change.
     */
    const init = async() => {
        const svg = document.getElementById('ph-wizard-octalysis-svg');
        if (!svg) {
            return;
        }

        const stringrequests = [
            {key: 'wizard_octalysis_center_label', component: 'block_playerhud'},
            {key: 'wizard_octalysis_invite', component: 'block_playerhud'},
        ];
        DRIVES.forEach((drive) => {
            stringrequests.push({key: drive.labelkey, component: 'block_playerhud'});
        });
        DRIVES.forEach((drive) => {
            stringrequests.push({key: `wizard_octalysis_drive${drive.id}_sub`, component: 'block_playerhud'});
        });
        const strings = await Str.get_strings(stringrequests);
        const centerlabel = strings[0];
        const invitetext = strings[1];

        DRIVES.forEach((drive, index) => {
            drive.label = strings[2 + index];
            drive.sub = strings[2 + DRIVES.length + index];
        });

        buildSegments(svg, DRIVES, centerlabel);

        // Each segment gets its own hover/focus tooltip instead of a list below the octagon —
        // same trigger pattern as the form's "i" info buttons. The title is a function so
        // Bootstrap re-reads it from the dataset at show-time, picking up whatever recompute()
        // last wrote there rather than the stale string captured at init.
        require(['theme_boost/bootstrap/tooltip'], (BSTooltip) => {
            DRIVES.forEach((drive) => {
                const segment = document.getElementById(`ph-oct-seg-${drive.id}`);
                new BSTooltip(segment, {
                    trigger: 'hover focus',
                    placement: 'top',
                    title: () => segment.dataset.tooltipText || '',
                });
            });
        });

        const checkboxes = Array.from(document.querySelectorAll('#ph-wizard-form [data-drives]'));
        const scorepct = document.getElementById('ph-wizard-octalysis-score-pct');
        const scorebar = document.getElementById('ph-wizard-octalysis-score-bar');

        /**
         * Whether a mechanic checkbox is disabled specifically because it was already
         * generated (or, for Ranking, is already active) — as opposed to disabled for some
         * other reason, e.g. Trade's own unmet-requirement gate, which must NOT count towards
         * coverage since nothing has actually happened for it yet. Detected via the "✓ Já
         * gerado"/"✓ Já ativo" note rendered as a direct sibling of the checkbox's own
         * form-check wrapper — scoped with :scope > so Trade's nested card (inside Avatar
         * Pack's own card) never matches on Avatar Pack's own note, or vice versa.
         *
         * @param {HTMLInputElement} checkbox
         * @return {boolean}
         */
        const isAlreadyGenerated = (checkbox) => {
            const wrapper = checkbox.closest('.form-check');
            const localcontainer = wrapper ? wrapper.parentElement : null;
            return Boolean(checkbox.disabled && localcontainer
                && localcontainer.querySelector(':scope > .ph-wizard-generated-note'));
        };

        // A drive is active when its checkbox is either checked (about to be generated) or
        // already generated — an untouched, empty course still honestly reads 0% (nothing
        // checked, nothing generated yet), but a course that already has content keeps
        // crediting it instead of the octagon zeroing out the moment that card locks itself
        // (see the wizard's plan doc, § 10.2 Item E, for the original all-or-nothing baseline
        // this refines).
        const recompute = () => {
            const active = new Set();
            checkboxes.forEach((checkbox) => {
                if (!checkbox.checked && !isAlreadyGenerated(checkbox)) {
                    return;
                }
                checkbox.dataset.drives.split(',').forEach((id) => active.add(Number(id)));
            });

            // Every drive gets its own tooltip text — what marking it earns when active, or a
            // gentle nudge toward it when not — instead of only ever calling out what is missing.
            DRIVES.forEach((drive) => {
                const segment = document.getElementById(`ph-oct-seg-${drive.id}`);
                const label = document.getElementById(`ph-oct-lbl-${drive.id}`);
                const isactive = active.has(drive.id);
                segment.classList.toggle('ph-oct-active', isactive);
                segment.classList.toggle('ph-oct-inactive', !isactive);
                label.classList.toggle('ph-oct-inactive', !isactive);

                const tooltiptext = isactive
                    ? `✓ ${drive.label} — ${drive.sub}`
                    : `${drive.label} — ${invitetext}`;
                segment.dataset.tooltipText = tooltiptext;
                segment.setAttribute('aria-label', tooltiptext);
            });

            const pct = Math.round((active.size / DRIVES.length) * 100);
            scorepct.textContent = `${pct}%`;
            scorebar.style.width = `${pct}%`;
            scorebar.setAttribute('aria-valuenow', String(pct));
        };

        checkboxes.forEach((checkbox) => checkbox.addEventListener('change', recompute));
        recompute();
    };

    return {init};
});
