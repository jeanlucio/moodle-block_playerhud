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

        const formEl = document.getElementById('ph-wizard-form');
        const historyViewEl = document.getElementById('ph-wizard-history-view');
        const historyListEl = document.getElementById('ph-wizard-history-list');
        const historyEmptyEl = document.getElementById('ph-wizard-history-empty');
        const historyBtn = document.getElementById('ph-wizard-history-btn');
        const historyBackBtn = document.getElementById('ph-wizard-history-back-btn');

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

        /**
         * Switches between the generation form and the run history list.
         *
         * @param {boolean} showHistory True to show the history view, false for the form.
         */
        const setHistoryView = (showHistory) => {
            formEl.classList.toggle('ph-display-none', showHistory);
            historyViewEl.classList.toggle('ph-display-none', !showHistory);
            historyBtn.classList.toggle('ph-display-none', showHistory);
            historyBackBtn.classList.toggle('ph-display-none', !showHistory);
            undoBtn.classList.toggle('ph-display-none', showHistory || !lastRunId);
            generateBtn.classList.toggle('ph-display-none', showHistory);
        };

        // Always reopen on the generation form, regardless of which view was
        // showing when the modal was last closed.
        modalEl.addEventListener('hidden.bs.modal', () => setHistoryView(false));

        /**
         * Undoes a wizard run and removes its row from the history list, if present.
         *
         * @param {number} runid The run ID to undo.
         * @param {HTMLElement} [row] The history row element to remove on success.
         * @param {HTMLElement} [button] The button that triggered this, disabled while running.
         */
        const rollbackRun = async(runid, row, button) => {
            if (button) {
                button.disabled = true;
            }

            try {
                await Ajax.call([{
                    methodname: 'block_playerhud_wizard_rollback',
                    args: {
                        instanceid,
                        courseid,
                        runid,
                    },
                }])[0];

                if (runid === lastRunId) {
                    lastRunId = null;
                    undoBtn.classList.add('ph-display-none');
                }
                errorEl.classList.add('ph-display-none');
                resultEl.classList.add('ph-display-none');

                if (row) {
                    row.remove();
                    if (!historyListEl.children.length) {
                        historyEmptyEl.classList.remove('ph-display-none');
                    }
                }
            } catch (e) {
                showAlert(errorEl, (e && e.message) ? e.message : String(e));
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        };

        /**
         * Builds one history list row for a past run, with its own undo button.
         *
         * @param {Object} run {runid, timecreated, summary}.
         * @return {HTMLElement}
         */
        const buildHistoryRow = (run) => {
            const row = document.createElement('div');
            row.className = 'list-group-item d-flex justify-content-between align-items-center';

            const info = document.createElement('div');
            const summary = document.createElement('div');
            summary.textContent = run.summary;
            const date = document.createElement('small');
            date.className = 'text-muted';
            date.textContent = run.timecreated;
            info.appendChild(summary);
            info.appendChild(date);

            const rowUndoBtn = document.createElement('button');
            rowUndoBtn.type = 'button';
            rowUndoBtn.className = 'btn btn-sm btn-outline-danger';
            const icon = document.createElement('i');
            icon.className = 'fa fa-undo me-1';
            icon.setAttribute('aria-hidden', 'true');
            rowUndoBtn.appendChild(icon);
            rowUndoBtn.appendChild(document.createTextNode(undoBtn.textContent.trim()));
            rowUndoBtn.addEventListener('click', () => rollbackRun(run.runid, row, rowUndoBtn));

            row.appendChild(info);
            row.appendChild(rowUndoBtn);
            return row;
        };

        historyBtn.addEventListener('click', async() => {
            historyListEl.innerHTML = '';
            historyEmptyEl.classList.add('ph-display-none');
            setHistoryView(true);

            try {
                const response = await Ajax.call([{
                    methodname: 'block_playerhud_wizard_list_runs',
                    args: {instanceid, courseid},
                }])[0];

                if (!response.runs.length) {
                    historyEmptyEl.classList.remove('ph-display-none');
                    return;
                }
                response.runs.forEach((run) => historyListEl.appendChild(buildHistoryRow(run)));
            } catch (e) {
                setHistoryView(false);
                showAlert(errorEl, (e && e.message) ? e.message : String(e));
            }
        });

        historyBackBtn.addEventListener('click', () => setHistoryView(false));

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

                const createdCount = response.created_items.length + response.created_quests.length;
                if (createdCount === 0) {
                    showAlert(resultEl, await Str.get_string('wizard_nothing_generated', 'block_playerhud'));
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

        undoBtn.addEventListener('click', () => {
            if (!lastRunId) {
                return;
            }
            rollbackRun(lastRunId, null, undoBtn);
        });
    };

    return {init};
});
