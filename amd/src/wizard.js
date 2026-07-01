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
 * Gamification wizard AMD module for PlayerHUD.
 *
 * Opens the wizard modal, runs the selected generation modules (Items & Trade,
 * Missions), and offers an immediate undo of the generated content.
 *
 * @module     block_playerhud/wizard
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {
    'use strict';

    /**
     * Initialise the gamification wizard.
     *
     * @param {Object} config Configuration object passed from PHP.
     * @param {number} config.instanceid Block instance ID.
     * @param {number} config.courseid Course ID.
     */
    const init = (config) => {
        const {instanceid, courseid} = config;

        const modalEl = document.getElementById('ph-wizard-modal');
        if (!modalEl) {
            return;
        }

        const themeEl = document.getElementById('ph-wizard-theme');
        const toneEl = document.getElementById('ph-wizard-tone');
        const sizeEl = document.getElementById('ph-wizard-size');
        const itemsModuleEl = document.getElementById('ph-wizard-module-items');
        const missionsModuleEl = document.getElementById('ph-wizard-module-missions');
        const generateBtn = document.getElementById('ph-wizard-generate-btn');
        const generateLabelEl = generateBtn.querySelector('.ph-wizard-btn-label');
        const undoBtn = document.getElementById('ph-wizard-undo-btn');
        const errorEl = document.getElementById('ph-wizard-error');
        const resultEl = document.getElementById('ph-wizard-result');

        let lastRunId = null;
        const defaultLabel = generateLabelEl.textContent;

        /**
         * Opens the wizard modal, hoisting it to the body first (established
         * cross-theme pattern for Bootstrap modals in this plugin).
         */
        const openModal = () => {
            document.body.appendChild(modalEl);
            require(['theme_boost/bootstrap/modal'], (BootstrapModal) => {
                new BootstrapModal(modalEl).show();
            });
        };

        const openBtn = document.getElementById('ph-wizard-open-btn');
        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }

        document.querySelectorAll('.ph-wizard-open-btn-banner').forEach((btn) => {
            btn.addEventListener('click', openModal);
        });

        /**
         * Shows a message in one of the modal's alert boxes, hiding the other.
         *
         * @param {HTMLElement} el The alert element to show.
         * @param {string} text The message to display.
         */
        const showAlert = (el, text) => {
            errorEl.classList.add('ph-display-none');
            resultEl.classList.add('ph-display-none');
            el.textContent = text;
            el.classList.remove('ph-display-none');
        };

        const setGenerating = async(isGenerating) => {
            generateBtn.disabled = isGenerating;
            if (!isGenerating) {
                generateLabelEl.textContent = defaultLabel;
                return;
            }
            try {
                generateLabelEl.textContent = await Str.get_string('wizard_generating', 'block_playerhud');
            } catch (e) {
                generateLabelEl.textContent = defaultLabel;
            }
        };

        generateBtn.addEventListener('click', async() => {
            if (!itemsModuleEl.checked && !missionsModuleEl.checked) {
                return;
            }

            await setGenerating(true);
            undoBtn.classList.add('ph-display-none');

            try {
                const response = await Ajax.call([{
                    methodname: 'block_playerhud_wizard_generate',
                    args: {
                        instanceid,
                        courseid,
                        theme: themeEl.value.trim(),
                        tone: toneEl.options[toneEl.selectedIndex].text,
                        size: sizeEl.value,
                        'include_items': itemsModuleEl.checked,
                        'include_missions': missionsModuleEl.checked,
                    },
                }])[0];

                if (!response.success) {
                    showAlert(errorEl, response.message || '');
                    return;
                }

                const names = [...response.created_items, ...response.created_quests].join(', ');
                lastRunId = response.runid;
                showAlert(resultEl, names);
                undoBtn.classList.remove('ph-display-none');
            } catch (e) {
                showAlert(errorEl, (e && e.message) ? e.message : String(e));
            } finally {
                await setGenerating(false);
            }
        });

        undoBtn.addEventListener('click', async() => {
            if (!lastRunId) {
                return;
            }

            undoBtn.disabled = true;

            try {
                await Ajax.call([{
                    methodname: 'block_playerhud_wizard_rollback',
                    args: {
                        instanceid,
                        courseid,
                        runid: lastRunId,
                    },
                }])[0];

                lastRunId = null;
                undoBtn.classList.add('ph-display-none');
                errorEl.classList.add('ph-display-none');
                resultEl.classList.add('ph-display-none');
            } catch (e) {
                showAlert(errorEl, (e && e.message) ? e.message : String(e));
            } finally {
                undoBtn.disabled = false;
            }
        });
    };

    return {init};
});
