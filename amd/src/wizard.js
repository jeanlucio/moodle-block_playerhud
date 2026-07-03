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
        const huddyBaseUrl = config.huddy_base_url;

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
        const undoBtn = document.getElementById('ph-wizard-undo-btn');
        const errorEl = document.getElementById('ph-wizard-error');
        const resultEl = document.getElementById('ph-wizard-result');

        const formEl = document.getElementById('ph-wizard-form');
        const historyViewEl = document.getElementById('ph-wizard-history-view');
        const historyListEl = document.getElementById('ph-wizard-history-list');
        const historyEmptyEl = document.getElementById('ph-wizard-history-empty');
        const historyBtn = document.getElementById('ph-wizard-history-btn');
        const historyBackBtn = document.getElementById('ph-wizard-history-back-btn');

        const progressViewEl = document.getElementById('ph-wizard-progress-view');
        const progressCloseWarningEl = document.getElementById('ph-wizard-progress-close-warning');
        const progressLabelEl = document.getElementById('ph-wizard-progress-label');
        const progressSubstepEl = document.getElementById('ph-wizard-progress-substep');
        const progressBarEl = document.getElementById('ph-wizard-progress-bar');
        const progressBarWrapEl = document.getElementById('ph-wizard-progress-bar-wrap');
        const progressSlowWarningEl = document.getElementById('ph-wizard-progress-slow-warning');
        const progressReportEl = document.getElementById('ph-wizard-progress-report');
        const progressReportTextEl = document.getElementById('ph-wizard-progress-report-text');
        const progressOkBtn = document.getElementById('ph-wizard-progress-ok-btn');
        const progressBackBtn = document.getElementById('ph-wizard-progress-back-btn');
        const progressErrorEl = document.getElementById('ph-wizard-progress-error');
        const progressErrorTextEl = document.getElementById('ph-wizard-progress-error-text');
        const progressRetryBtn = document.getElementById('ph-wizard-progress-retry-btn');
        const progressUndoBtn = document.getElementById('ph-wizard-progress-undo-btn');
        const huddyCarouselEl = document.getElementById('ph-wizard-huddy-carousel');
        const huddyImgEl = document.getElementById('ph-wizard-huddy-img');
        const huddyPulseEl = document.getElementById('ph-wizard-huddy-pulse');
        const huddyTipEl = document.getElementById('ph-wizard-huddy-tip');

        let lastRunId = null;
        let contentChanged = false;
        let pendingRetry = null;

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

        /**
         * Switches between the generation form and the live step-by-step progress view.
         *
         * @param {boolean} showProgress True to show the progress view, false for the form.
         */
        const setProgressView = (showProgress) => {
            formEl.classList.toggle('ph-display-none', showProgress);
            progressViewEl.classList.toggle('ph-display-none', !showProgress);
            historyBtn.classList.toggle('ph-display-none', showProgress);
            generateBtn.classList.toggle('ph-display-none', showProgress);
            undoBtn.classList.toggle('ph-display-none', showProgress || !lastRunId);
        };

        // Huddy carousel: the wizard's own "game loading screen" — 5 mascot images alternating
        // with pedagogical tips, masking the ~10-40s an AI story-arc step spends without moving
        // the progress bar. It is the activity indicator, not decoration (see § 5.9 Fatia 3): it
        // runs for the whole progress view, not only during story steps, so there is never a
        // separate spinner to keep in sync with it.
        const HUDDY_IMAGES = ['hello.webp', 'coins.webp', 'achievement.webp', 'levelup.webp', 'quest.webp'];
        const HUDDY_TIP_KEYS = [
            'wizard_huddy_tip1', 'wizard_huddy_tip2', 'wizard_huddy_tip3', 'wizard_huddy_tip4', 'wizard_huddy_tip5',
        ];
        const HUDDY_INTERVAL_MS = 4000;
        let huddyTimer = null;
        let huddyTips = null;

        huddyImgEl.addEventListener('error', () => {
            // The art failing to load must never leave the UI looking frozen — falls back to an
            // indeterminate pulsing placeholder instead, per § 5.9 Fatia 3's decision.
            huddyImgEl.classList.add('ph-display-none');
            huddyPulseEl.classList.remove('ph-display-none');
        });
        huddyImgEl.addEventListener('load', () => {
            huddyImgEl.classList.remove('ph-display-none');
            huddyPulseEl.classList.add('ph-display-none');
        });

        const showHuddySlide = (index) => {
            huddyImgEl.src = huddyBaseUrl + HUDDY_IMAGES[index % HUDDY_IMAGES.length];
            if (huddyTips) {
                huddyTipEl.textContent = huddyTips[index % huddyTips.length];
            }
        };

        const startHuddyCarousel = async() => {
            // Shows the carousel and its first image synchronously — waiting on the tip strings
            // first (a network round-trip) would delay the mascot showing up at all, defeating
            // the point of it being the activity indicator from the very first instant.
            huddyCarouselEl.classList.remove('ph-display-none');
            let index = 0;
            showHuddySlide(index);
            huddyTimer = setInterval(() => {
                index += 1;
                showHuddySlide(index);
            }, HUDDY_INTERVAL_MS);

            if (!huddyTips) {
                try {
                    huddyTips = await Str.get_strings(HUDDY_TIP_KEYS.map((key) => ({key, component: 'block_playerhud'})));
                    showHuddySlide(index);
                } catch (e) {
                    huddyTips = [];
                }
            }
        };

        const stopHuddyCarousel = () => {
            if (huddyTimer) {
                clearInterval(huddyTimer);
                huddyTimer = null;
            }
            huddyCarouselEl.classList.add('ph-display-none');
        };

        // Reload the page on close so generated/undone content shows up immediately,
        // matching the reload-on-close pattern used by the other AI generation modals
        // (manage_items.js, ai_story.js, ai_oracle.js). Otherwise just reopen on the
        // generation form, regardless of which view was showing when it was last closed.
        modalEl.addEventListener('hidden.bs.modal', () => {
            stopHuddyCarousel();
            if (contentChanged) {
                window.location.reload();
                return;
            }
            setHistoryView(false);
            setProgressView(false);
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

        /**
         * Shows the step-by-step progress view's error state with a "try again" action that
         * resumes from the failed step, plus the always-available undo.
         *
         * @param {string} message The error message to display.
         * @param {Function} retry Called when the teacher clicks "Try again".
         */
        const showProgressError = (message, retry) => {
            contentChanged = true;
            stopHuddyCarousel();
            progressCloseWarningEl.classList.add('ph-display-none');
            progressErrorTextEl.textContent = message;
            progressErrorEl.classList.remove('ph-display-none');
            pendingRetry = retry;
        };

        /**
         * Formats the run's accumulated counts into the same "N items, N quests…" quantity
         * summary the run history list already builds server-side in wizard_list_runs.
         *
         * @param {Object} totals {items, quests, trades, chapters, classes}.
         * @return {Promise<string>}
         */
        const formatTotals = async(totals) => {
            const [items, quests, classes, chapters, trades] = await Str.get_strings([
                {key: 'wizard_history_items', component: 'block_playerhud'},
                {key: 'wizard_history_quests', component: 'block_playerhud'},
                {key: 'wizard_history_classes', component: 'block_playerhud'},
                {key: 'wizard_history_chapters', component: 'block_playerhud'},
                {key: 'wizard_history_trades', component: 'block_playerhud'},
            ]);
            const labels = {items, quests, classes, chapters, trades};

            const parts = Object.keys(labels)
                .map((key) => (totals[key] ? `${totals[key]} ${labels[key]}` : ''))
                .filter(Boolean);

            return parts.length ? parts.join(', ') : Str.get_string('wizard_nothing_generated', 'block_playerhud');
        };

        /**
         * Shows the step-by-step progress view's final report: quantities generated plus the
         * OK (close + reload) and Back (stay on the form, no reload) actions.
         *
         * @param {Object} totals {items, quests, trades, chapters, classes}.
         * @param {string[]} stepMessages Notes collected from individual steps (e.g.
         *     auto_distribute's "no activities yet" note), in step order.
         * @param {string} economyMessage XP economy summary, or an empty string.
         */
        const showProgressReport = async(totals, stepMessages, economyMessage) => {
            contentChanged = true;
            stopHuddyCarousel();
            progressCloseWarningEl.classList.add('ph-display-none');
            progressSlowWarningEl.classList.add('ph-display-none');

            let text = await formatTotals(totals);
            [...stepMessages, economyMessage].filter(Boolean).forEach((message) => {
                text += ' — ' + message;
            });
            progressReportTextEl.textContent = text;
            progressReportEl.classList.remove('ph-display-none');
        };

        /**
         * Runs the wizard's step plan from `startIndex` onwards, updating the progress bar live
         * after each step and stopping (with a retry/undo choice) on the first failure — retrying
         * resumes from the same failed step rather than restarting the whole run.
         *
         * @param {Object} runParams The generation params (theme, tone, size, include_*…).
         * @param {number} runid The wizard run ID, from wizard_start.
         * @param {Array} steps Ordered step plan, from wizard_start.
         * @param {number} startIndex Index to resume from (0 for a fresh run).
         * @param {Object} totals Accumulated counts, mutated in place across retries.
         * @param {number[]} dropids Accumulated drop IDs, mutated in place across retries.
         * @param {string[]} stepMessages Accumulated step notes, mutated in place across retries.
         * @param {string[]} arcBeats Story arc beats, mutated in place once "story_outline" runs.
         * @param {number[]} itemxpshares XP shares for the "items" step, from wizard_start.
         * @param {number[]} missionxpshares XP shares for the "missions" step, from wizard_start.
         * @param {number} pillBonusXp Reward XP for the "pill" step's trade quest, from wizard_start.
         * @param {number} latepenaltyBonusXp Reward XP for the "latepenalty" step's quest, from wizard_start.
         */
        const runStepsFrom = async(
            runParams, runid, steps, startIndex, totals, dropids, stepMessages, arcBeats,
            itemxpshares, missionxpshares, pillBonusXp, latepenaltyBonusXp
        ) => {
            const reporteconomy = steps.some((step) => step.type === 'items' || step.type === 'missions');

            for (let index = startIndex; index < steps.length; index++) {
                const step = steps[index];
                const islaststep = index === steps.length - 1;

                progressLabelEl.textContent = step.label;
                progressSubstepEl.textContent = await Str.get_string('wizard_progress_step_of', 'block_playerhud',
                    {current: index + 1, total: steps.length});

                const resume = () => runStepsFrom(
                    runParams, runid, steps, index, totals, dropids, stepMessages, arcBeats,
                    itemxpshares, missionxpshares, pillBonusXp, latepenaltyBonusXp
                );

                let result;
                try {
                    result = await Ajax.call([{
                        methodname: 'block_playerhud_wizard_run_step',
                        args: {
                            instanceid,
                            courseid,
                            runid,
                            steptype: step.type,
                            theme: runParams.theme,
                            tone: runParams.tone,
                            'tone_key': runParams.tone_key,
                            size: runParams.size,
                            'item_xp_shares': step.type === 'items' ? itemxpshares : [],
                            'mission_xp_shares': step.type === 'missions' ? missionxpshares : [],
                            'drop_ids': step.type === 'auto_distribute' ? dropids : [],
                            'arc_beats': step.type.startsWith('story_chapter_') ? arcBeats : [],
                            'is_last_step': islaststep,
                            'report_economy': islaststep && reporteconomy,
                            'pill_bonus_xp': step.type === 'pill' ? pillBonusXp : 0,
                            'latepenalty_bonus_xp': step.type === 'latepenalty' ? latepenaltyBonusXp : 0,
                        },
                    }])[0];
                } catch (e) {
                    showProgressError((e && e.message) ? e.message : String(e), resume);
                    return;
                }

                if (!result.success) {
                    const label = await Str.get_string('wizard_progress_error_at', 'block_playerhud', step.label);
                    showProgressError(result.message ? `${label} — ${result.message}` : label, resume);
                    return;
                }

                totals.items += result.counts.items;
                totals.quests += result.counts.quests;
                totals.trades += result.counts.trades;
                totals.chapters += result.counts.chapters;
                totals.classes += result.counts.classes;
                dropids.push(...result.drop_ids);
                arcBeats.push(...result.arc_beats);
                if (result.message) {
                    stepMessages.push(result.message);
                }

                const pct = Math.round(((index + 1) / steps.length) * 100);
                progressBarEl.style.width = `${pct}%`;
                progressBarWrapEl.setAttribute('aria-valuenow', String(pct));

                if (islaststep) {
                    await showProgressReport(totals, stepMessages, result.economy_message || '');
                }
            }
        };

        /**
         * Starts a fresh wizard run: asks the server for its step plan, then drives it live.
         *
         * @param {Object} runParams The generation params (theme, tone, size, include_*…).
         */
        const runWizard = async(runParams) => {
            let started;
            try {
                started = await Ajax.call([{methodname: 'block_playerhud_wizard_start', args: runParams}])[0];
            } catch (e) {
                showProgressError((e && e.message) ? e.message : String(e), () => runWizard(runParams));
                return;
            }

            lastRunId = started.runid;
            progressSlowWarningEl.classList.toggle('ph-display-none', !started.has_slow_step);

            const totals = {items: 0, quests: 0, trades: 0, chapters: 0, classes: 0};
            await runStepsFrom(
                runParams, started.runid, started.steps, 0, totals, [], [], [],
                started.item_xp_shares, started.mission_xp_shares,
                started.pill_bonus_xp, started.latepenalty_bonus_xp
            );
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

            const runParams = {
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
            };

            setProgressView(true);
            progressReportEl.classList.add('ph-display-none');
            progressErrorEl.classList.add('ph-display-none');
            progressCloseWarningEl.classList.remove('ph-display-none');
            progressBarEl.style.width = '0%';
            progressBarWrapEl.setAttribute('aria-valuenow', '0');
            progressSubstepEl.textContent = '';

            await startHuddyCarousel();
            await runWizard(runParams);
        });

        progressRetryBtn.addEventListener('click', async() => {
            if (!pendingRetry) {
                return;
            }
            const retry = pendingRetry;
            pendingRetry = null;
            progressErrorEl.classList.add('ph-display-none');
            progressCloseWarningEl.classList.remove('ph-display-none');
            await startHuddyCarousel();
            await retry();
        });

        progressUndoBtn.addEventListener('click', () => {
            if (!lastRunId) {
                return;
            }
            rollbackRun(lastRunId, null, progressUndoBtn);
        });

        progressOkBtn.addEventListener('click', () => {
            contentChanged = true;
            modalEl.querySelector('.btn-close').click();
        });

        progressBackBtn.addEventListener('click', () => setProgressView(false));

        undoBtn.addEventListener('click', () => {
            if (!lastRunId) {
                return;
            }
            rollbackRun(lastRunId, null, undoBtn);
        });
    };

    return {init};
});
