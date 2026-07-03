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
define(['core/ajax', 'core/str', 'block_playerhud/wizard_octalysis'], function(Ajax, Str, WizardOctalysis) {
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

        WizardOctalysis.init();

        const themeEl = document.getElementById('ph-wizard-theme');
        const toneEl = document.getElementById('ph-wizard-tone');
        const sizeEl = document.getElementById('ph-wizard-size');
        const itemsModuleEl = document.getElementById('ph-wizard-module-items');
        const missionsModuleEl = document.getElementById('ph-wizard-module-missions');
        const playercoinModuleEl = document.getElementById('ph-wizard-module-playercoin');
        const avatarsModuleEl = document.getElementById('ph-wizard-module-avatars');
        const pillModuleEl = document.getElementById('ph-wizard-module-pill');
        // Only rendered when local_latepenalty is installed — every other module checkbox
        // always exists, so this is the one spot in the form that needs a null guard.
        const latepenaltyModuleEl = document.getElementById('ph-wizard-module-latepenalty');
        const latepenaltyChecked = () => (latepenaltyModuleEl ? latepenaltyModuleEl.checked : false);
        const rpgModuleEl = document.getElementById('ph-wizard-module-rpg');
        const nextChapterModuleEl = document.getElementById('ph-wizard-module-nextchapter');
        const tradeModuleEl = document.getElementById('ph-wizard-module-trade');
        const progressItemModuleEl = document.getElementById('ph-wizard-module-progressitem');
        const autoDistributeModuleEl = document.getElementById('ph-wizard-module-autodistribute');
        const secretModuleEl = document.getElementById('ph-wizard-module-secret');
        const rankingModuleEl = document.getElementById('ph-wizard-module-ranking');
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
        let contentChanged = false;
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
         * Wires a "select all" group checkbox that cascades its checked state down to every
         * other checkbox in the given container, and reflects the children's combined state
         * back up: checked when all are checked, indeterminate when only some are, unchecked
         * when none are — the standard "table header checkbox" pattern.
         *
         * @param {HTMLInputElement} groupEl The group toggle checkbox.
         * @param {HTMLElement} containerEl The container whose other checkboxes this toggle
         *     controls (every checkbox inside it except groupEl itself).
         */
        const wireGroupToggle = (groupEl, containerEl) => {
            const children = () => Array.from(containerEl.querySelectorAll('input[type="checkbox"]'))
                .filter((el) => el !== groupEl);

            const syncGroupState = () => {
                const all = children();
                const checkedcount = all.filter((el) => el.checked).length;
                groupEl.checked = all.length > 0 && checkedcount === all.length;
                groupEl.indeterminate = checkedcount > 0 && checkedcount < all.length;
            };

            groupEl.addEventListener('change', () => {
                // Captured once: each child's dispatched 'change' below synchronously re-enters
                // syncGroupState(), which would otherwise overwrite groupEl.checked mid-loop (once
                // some but not all children are updated) and make later iterations compare against
                // that clobbered value instead of the user's original click intent.
                const target = groupEl.checked;
                children().forEach((child) => {
                    if (child.checked !== target) {
                        child.checked = target;
                        child.dispatchEvent(new Event('change'));
                    }
                });
                groupEl.checked = target;
                groupEl.indeterminate = false;
            });

            children().forEach((child) => child.addEventListener('change', syncGroupState));
            syncGroupState();
        };

        const groupItemsEl = document.getElementById('ph-wizard-group-items');
        const cardItemsEl = document.getElementById('ph-wizard-card-items');
        if (groupItemsEl && cardItemsEl) {
            wireGroupToggle(groupItemsEl, cardItemsEl);
        }

        // Item H: each mechanic card and the external-recommendations panel start collapsed to
        // fit the modal on a common desktop without scrolling; a chevron button reveals details.
        document.querySelectorAll('.ph-wizard-card-toggle').forEach((toggleBtn) => {
            const body = document.getElementById(toggleBtn.getAttribute('aria-controls'));
            if (!body) {
                return;
            }
            toggleBtn.addEventListener('click', () => {
                const expanded = toggleBtn.getAttribute('aria-expanded') === 'true';
                toggleBtn.setAttribute('aria-expanded', String(!expanded));
                body.classList.toggle('ph-display-none', expanded);
            });
        });

        // Only rendered when the instance's level settings are still at the edit form's
        // defaults (100 XP per level, 20 levels) — every other element in the modal always
        // exists, so this is the other spot (besides latepenaltyModuleEl) needing a null guard.
        const levelsSuggestionEl = document.getElementById('ph-wizard-levels-suggestion');
        if (levelsSuggestionEl) {
            const levelsSuggestionTextEl = document.getElementById('ph-wizard-levels-suggestion-text');
            const applyLevelsBtn = document.getElementById('ph-wizard-apply-levels-btn');

            Str.get_strings([
                {key: 'wizard_levels_suggestion_short', component: 'block_playerhud'},
                {key: 'wizard_levels_suggestion_medium', component: 'block_playerhud'},
                {key: 'wizard_levels_suggestion_long', component: 'block_playerhud'},
            ]).then(([short, medium, long]) => {
                const suggestionBySize = {short, medium, long};
                const updateSuggestionText = () => {
                    levelsSuggestionTextEl.textContent = suggestionBySize[sizeEl.value] || short;
                };
                updateSuggestionText();
                sizeEl.addEventListener('change', updateSuggestionText);
                return null;
            }).catch(() => {
                // A failed string lookup should not break the rest of the modal; the suggestion
                // box just stays without text and can still be dismissed via Apply/generation.
            });

            applyLevelsBtn.addEventListener('click', async() => {
                applyLevelsBtn.disabled = true;
                try {
                    const result = await Ajax.call([{
                        methodname: 'block_playerhud_wizard_apply_suggested_levels',
                        args: {instanceid, size: sizeEl.value},
                    }])[0];
                    if (result.applied) {
                        levelsSuggestionEl.classList.add('ph-display-none');
                    }
                } finally {
                    applyLevelsBtn.disabled = false;
                }
            });
        }

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

        // Reload the page on close so generated/undone content shows up immediately,
        // matching the reload-on-close pattern used by the other AI generation modals
        // (manage_items.js, ai_story.js, ai_oracle.js). Otherwise just reopen on the
        // generation form, regardless of which view was showing when it was last closed.
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (contentChanged) {
                window.location.reload();
                return;
            }
            setHistoryView(false);
        });

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

                contentChanged = true;

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
            const moduleCheckedFlags = [
                itemsModuleEl.checked, missionsModuleEl.checked, playercoinModuleEl.checked,
                avatarsModuleEl.checked, rpgModuleEl.checked, progressItemModuleEl.checked,
                nextChapterModuleEl.checked, tradeModuleEl.checked, pillModuleEl.checked,
                latepenaltyChecked(), secretModuleEl.checked, rankingModuleEl.checked,
            ];
            if (!moduleCheckedFlags.some(Boolean)) {
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
                        'include_playercoin': playercoinModuleEl.checked,
                        'include_avatars': avatarsModuleEl.checked,
                        'include_rpg': rpgModuleEl.checked,
                        'tone_key': toneEl.value,
                        'include_auto_distribute': autoDistributeModuleEl.checked,
                        'include_progress_item': progressItemModuleEl.checked,
                        'include_next_chapter': nextChapterModuleEl.checked,
                        'include_comercio': tradeModuleEl.checked,
                        'include_pill': pillModuleEl.checked,
                        'include_latepenalty': latepenaltyChecked(),
                        'include_secret_drops': secretModuleEl.checked,
                        'include_ranking': rankingModuleEl.checked,
                    },
                }])[0];

                if (!response.success) {
                    showAlert(errorEl, response.message || '');
                    return;
                }

                const createdCount = response.created_items.length + response.created_quests.length +
                    response.created_trades.length;
                if (createdCount === 0) {
                    showAlert(resultEl, await Str.get_string('wizard_nothing_generated', 'block_playerhud'));
                    return;
                }

                let names = [...response.created_items, ...response.created_quests, ...response.created_trades]
                    .join(', ');
                if (response.distribute_message) {
                    names += ' — ' + response.distribute_message;
                }
                if (response.economy_message) {
                    names += ' — ' + response.economy_message;
                }
                lastRunId = response.runid;
                contentChanged = true;
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
