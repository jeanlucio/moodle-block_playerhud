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

        // Same info-icon tooltip pattern as view.js's quest description button. No jQuery
        // fallback needed here (unlike view.js): this file already relies on
        // theme_boost/bootstrap/modal exporting a plain constructor on both BS4 and BS5 (see
        // openModal() below), and the sibling tooltip module works the same way.
        const infoEls = document.querySelectorAll('.js-ph-wizard-info');
        if (infoEls.length) {
            require(['theme_boost/bootstrap/tooltip'], (BSTooltip) => {
                infoEls.forEach((el) => new BSTooltip(el, {trigger: 'hover focus', placement: 'bottom'}));
            });
        }

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
        const tradeModuleEl = document.getElementById('ph-wizard-module-trade');
        const tradeRequirementEl = document.getElementById('ph-wizard-trade-requirement');
        const progressItemModuleEl = document.getElementById('ph-wizard-module-progressitem');
        const secretModuleEl = document.getElementById('ph-wizard-module-secret');
        const rankingModuleEl = document.getElementById('ph-wizard-module-ranking');

        // Each of these only exists in the DOM while its own mechanic is still available to run
        // (the template hides the checkbox once gen_* is true) — every read goes through
        // distributeChecked(), never el.checked directly, so a missing element (harmless: its
        // module can no longer run anyway) never throws.
        const distributeItemsEl = document.getElementById('ph-wizard-distribute-items');
        const distributeProgressItemEl = document.getElementById('ph-wizard-distribute-progressitem');
        const distributePlayercoinEl = document.getElementById('ph-wizard-distribute-playercoin');
        const distributePillEl = document.getElementById('ph-wizard-distribute-pill');
        const distributeSecretEl = document.getElementById('ph-wizard-distribute-secret');
        const distributeChecked = (el) => (el ? el.checked : true);
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
        const externalViewEl = document.getElementById('ph-wizard-external-view');
        const externalBtn = document.getElementById('ph-wizard-external-btn');
        const helpViewEl = document.getElementById('ph-wizard-help-view');
        const helpBtn = document.getElementById('ph-wizard-help-btn');

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
        const huddyNumberEl = document.getElementById('ph-wizard-huddy-number');
        const huddyTipEl = document.getElementById('ph-wizard-huddy-tip');
        const huddyPrevBtn = document.getElementById('ph-wizard-huddy-prev');
        const huddyNextBtn = document.getElementById('ph-wizard-huddy-next');

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

        // Trade silently creates 0 trades without a PlayerCoin and an Avatar Pack to wire
        // together (game::build_trade_suggestions() returns empty otherwise) — disabled here
        // until both are available, instead of letting a teacher select it and get nothing.
        // A prerequisite counts as available when checked for this run OR already generated
        // (its checkbox disabled by the template): generate_comercio() works off whatever
        // already exists in the instance, not just this run's output. When Trade itself was
        // already generated, the requirement note is not rendered at all and this stays off.
        const syncTradeRequirement = async() => {
            if (!tradeRequirementEl) {
                return;
            }
            const met = (playercoinModuleEl.checked || playercoinModuleEl.disabled)
                && (avatarsModuleEl.checked || avatarsModuleEl.disabled);
            tradeModuleEl.disabled = !met;
            if (!met) {
                tradeModuleEl.checked = false;
            }
            tradeRequirementEl.classList.toggle('ph-wizard-trade-requirement-met', met);
            tradeRequirementEl.textContent = await Str.get_string(
                met ? 'wizard_module_trade_requirement_met' : 'wizard_module_trade_requirement_unmet',
                'block_playerhud'
            );
        };
        playercoinModuleEl.addEventListener('change', syncTradeRequirement);
        avatarsModuleEl.addEventListener('change', syncTradeRequirement);
        syncTradeRequirement();

        // Each mechanic's own "distribute into activities" checkbox is meaningless once its
        // parent module checkbox is unchecked — nothing new would be created to distribute.
        const syncDistributeCheckbox = (moduleEl, distributeEl) => {
            if (!distributeEl) {
                return;
            }
            distributeEl.disabled = !moduleEl.checked;
        };
        [
            [itemsModuleEl, distributeItemsEl],
            [progressItemModuleEl, distributeProgressItemEl],
            [playercoinModuleEl, distributePlayercoinEl],
            [pillModuleEl, distributePillEl],
            [secretModuleEl, distributeSecretEl],
        ].forEach(([moduleEl, distributeEl]) => {
            moduleEl.addEventListener('change', () => syncDistributeCheckbox(moduleEl, distributeEl));
            syncDistributeCheckbox(moduleEl, distributeEl);
        });

        // "Select all" (sits in the Economy section header, but controls every mechanic
        // checkbox in the whole form) checks/unchecks every enabled one at once, including
        // Comércio — appended explicitly since it has no ph-wizard-mech-module class of its own
        // (its own requirement-gating decides whether it can be checked at all) and pushed to
        // the END of the list so it is always processed after PlayerCoin/Avatar Pack: by then
        // syncTradeRequirement() has already re-evaluated (and, when checking, cleared) its
        // disabled state from their own dispatched change events. Reflects back — including an
        // indeterminate state — when the teacher toggles any of those checkboxes individually.
        const selectAllEl = document.getElementById('ph-wizard-select-all');
        if (selectAllEl) {
            const selectAllModuleEls = Array.from(
                document.querySelectorAll('#ph-wizard-form .ph-wizard-mech-module')
            );
            if (tradeModuleEl) {
                selectAllModuleEls.push(tradeModuleEl);
            }

            const syncSelectAll = () => {
                const selectable = selectAllModuleEls.filter((checkbox) => !checkbox.disabled);
                const checkedcount = selectable.filter((checkbox) => checkbox.checked).length;
                selectAllEl.checked = selectable.length > 0 && checkedcount === selectable.length;
                selectAllEl.indeterminate = checkedcount > 0 && checkedcount < selectable.length;
            };

            selectAllEl.addEventListener('change', () => {
                // Captured once: each dispatched change below re-enters syncSelectAll(), which
                // recomputes and overwrites selectAllEl.checked mid-loop — reading the live
                // property on every iteration would corrupt later checkboxes with an
                // intermediate (not the teacher's intended) value.
                const shouldCheck = selectAllEl.checked;
                selectAllModuleEls.forEach((checkbox) => {
                    if (checkbox.disabled) {
                        return;
                    }
                    checkbox.checked = shouldCheck;
                    checkbox.dispatchEvent(new Event('change'));
                });
            });

            selectAllModuleEls.forEach((checkbox) => checkbox.addEventListener('change', syncSelectAll));
            syncSelectAll();
        }

        // Checked by default only when the instance is still at the edit form's defaults (100
        // XP per level, 20 levels) — see levels_at_default in the template. While checked, every
        // change to either the checkbox itself or the journey size re-applies the matching
        // suggestion; unchecking it just stops future syncing, it never reverts a past one.
        const applyLevelsEl = document.getElementById('ph-wizard-apply-levels');
        const applySuggestedLevels = async() => {
            if (!applyLevelsEl.checked) {
                return;
            }
            await Ajax.call([{
                methodname: 'block_playerhud_wizard_apply_suggested_levels',
                args: {instanceid, size: sizeEl.value},
            }])[0];
        };
        applyLevelsEl.addEventListener('change', applySuggestedLevels);
        sizeEl.addEventListener('change', applySuggestedLevels);

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

        // The form and its three footer-triggered alternates (history, external
        // recommendations, help) all swap into the same spot and share one back button —
        // showing one always means hiding the form and the other two.
        const sideViews = [historyViewEl, externalViewEl, helpViewEl];
        const sideTriggerBtns = [historyBtn, externalBtn, helpBtn];

        /**
         * Switches between the generation form and one of the footer-triggered side views.
         *
         * @param {?HTMLElement} viewEl The side view to show, or null to show the form instead.
         */
        const setSideView = (viewEl) => {
            const showing = viewEl !== null;
            formEl.classList.toggle('ph-display-none', showing);
            sideViews.forEach((el) => el.classList.toggle('ph-display-none', el !== viewEl));
            sideTriggerBtns.forEach((btn) => btn.classList.toggle('ph-display-none', showing));
            historyBackBtn.classList.toggle('ph-display-none', !showing);
            undoBtn.classList.toggle('ph-display-none', showing || !lastRunId);
            generateBtn.classList.toggle('ph-display-none', showing);
        };

        /**
         * Switches between the generation form and the live step-by-step progress view.
         *
         * @param {boolean} showProgress True to show the progress view, false for the form.
         */
        const setProgressView = (showProgress) => {
            formEl.classList.toggle('ph-display-none', showProgress);
            progressViewEl.classList.toggle('ph-display-none', !showProgress);
            sideTriggerBtns.forEach((btn) => btn.classList.toggle('ph-display-none', showProgress));
            generateBtn.classList.toggle('ph-display-none', showProgress);
            undoBtn.classList.toggle('ph-display-none', showProgress || !lastRunId);
            // The form can be scrolled deep down (e.g. a mechanic near the bottom was just
            // checked) — carrying that same scroll position into the progress view would leave
            // Huddy and the progress bar off-screen above the visible area.
            if (showProgress) {
                const modalBodyEl = modalEl.querySelector('.modal-body');
                if (modalBodyEl) {
                    modalBodyEl.scrollTop = 0;
                }
            }
        };

        // Huddy carousel: the wizard's own "game loading screen" — 5 mascot images (reused
        // across more tips than images, cycling independently) alternating with pedagogical
        // tips, masking the ~10-40s an AI story-arc step spends without moving the progress
        // bar. It is the activity indicator, not decoration (see § 5.9 Fatia 3): it runs for
        // the whole progress view, not only during story steps, so there is never a separate
        // spinner to keep in sync with it. It also stays up (manually navigable) once the
        // final report shows, so the tips remain browsable after generation finishes.
        const HUDDY_IMAGES = ['hello.webp', 'coins.webp', 'achievement.webp', 'levelup.webp', 'quest.webp'];
        const HUDDY_TIP_KEYS = [
            'wizard_huddy_tip1', 'wizard_huddy_tip2', 'wizard_huddy_tip3', 'wizard_huddy_tip4',
            'wizard_huddy_tip5', 'wizard_huddy_tip6', 'wizard_huddy_tip7', 'wizard_huddy_tip8',
            'wizard_huddy_tip9', 'wizard_huddy_tip10', 'wizard_huddy_tip11', 'wizard_huddy_tip12',
        ];
        const HUDDY_INTERVAL_MS = 7000;
        let huddyTimer = null;
        let huddyTips = null;
        let huddyTipLabels = null;
        let huddyIndex = 0;

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
            huddyIndex = index;
            const image = HUDDY_IMAGES[index % HUDDY_IMAGES.length];
            huddyImgEl.src = huddyBaseUrl + image;
            if (huddyTips) {
                const label = huddyTipLabels ? huddyTipLabels[index % huddyTipLabels.length] : '';
                huddyTipEl.textContent = label + huddyTips[index % huddyTips.length];
            }
            // Same technique as the level-up celebration (levelup.js): the number is painted
            // over the shield in levelup.webp specifically — the other 4 images have no shield
            // to paint it on, so it only shows up for that one slide.
            huddyNumberEl.classList.toggle('ph-display-none', image !== 'levelup.webp');
        };

        huddyPrevBtn.addEventListener('click', () => {
            showHuddySlide((huddyIndex - 1 + HUDDY_TIP_KEYS.length) % HUDDY_TIP_KEYS.length);
        });
        huddyNextBtn.addEventListener('click', () => {
            showHuddySlide(huddyIndex + 1);
        });

        const startHuddyCarousel = async() => {
            // Shows the carousel and its first image synchronously — waiting on the tip strings
            // first (a network round-trip) would delay the mascot showing up at all, defeating
            // the point of it being the activity indicator from the very first instant.
            huddyCarouselEl.classList.remove('ph-display-none');
            showHuddySlide(0);
            huddyTimer = setInterval(() => {
                showHuddySlide(huddyIndex + 1);
            }, HUDDY_INTERVAL_MS);

            if (huddyTips === null) {
                try {
                    huddyTips = await Str.get_strings(HUDDY_TIP_KEYS.map((key) => ({key, component: 'block_playerhud'})));
                    huddyTipLabels = await Str.get_strings(HUDDY_TIP_KEYS.map((key, i) => ({
                        key: 'wizard_huddy_tip_label', component: 'block_playerhud', param: i + 1,
                    })));
                    showHuddySlide(huddyIndex);
                } catch (e) {
                    // Left as null (not []) so the next carousel start retries the fetch instead
                    // of permanently rendering with no tip text for the rest of the page session.
                    huddyTips = null;
                }
            }
        };

        // Stops only the automatic advance — used once the final report shows, so the teacher
        // can keep browsing tips manually with the prev/next arrows instead of the carousel
        // disappearing outright.
        const pauseHuddyCarousel = () => {
            if (huddyTimer) {
                clearInterval(huddyTimer);
                huddyTimer = null;
            }
        };

        const stopHuddyCarousel = () => {
            pauseHuddyCarousel();
            huddyCarouselEl.classList.add('ph-display-none');
        };

        /**
         * Runs once the modal has actually finished closing: reloads the page on close so
         * generated/undone content shows up immediately, matching the reload-on-close pattern
         * used by the other AI generation modals (manage_items.js, ai_story.js, ai_oracle.js).
         * Otherwise just reopens on the generation form, regardless of which view was showing
         * when it was last closed.
         *
         * @return {void}
         */
        const onModalHidden = () => {
            stopHuddyCarousel();
            if (contentChanged) {
                window.location.reload();
                return;
            }
            setSideView(null);
            setProgressView(false);
        };

        // Detected via the 'show' class instead of listening for the 'hidden.bs.modal' event:
        // Bootstrap 4 (Moodle 4.5, still jQuery-based under the hood) dispatches that event only
        // through jQuery's own event system, which a vanilla addEventListener here never
        // receives — the modal still closes correctly, but this code silently never ran, so the
        // page never reloaded after a run. Bootstrap 4 and 5 both add/remove the same 'show'
        // class to signal the modal's visibility, so watching for its removal works identically
        // on both, regardless of which event-dispatch system is firing underneath. The short
        // delay lets the close transition finish before reloading/resetting, roughly matching
        // when 'hidden.bs.modal' would have fired anyway.
        let modalWasVisible = false;
        new MutationObserver(() => {
            const isVisible = modalEl.classList.contains('show');
            if (modalWasVisible && !isVisible) {
                window.setTimeout(onModalHidden, 300);
            }
            modalWasVisible = isVisible;
        }).observe(modalEl, {attributes: true, attributeFilter: ['class']});

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
            setSideView(historyViewEl);

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
                setSideView(null);
                showAlert(errorEl, (e && e.message) ? e.message : String(e));
            }
        });

        historyBackBtn.addEventListener('click', () => setSideView(null));
        externalBtn.addEventListener('click', () => setSideView(externalViewEl));
        helpBtn.addEventListener('click', () => setSideView(helpViewEl));

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
            // Huddy stays up so its tips remain browsable — only the automatic advance stops.
            pauseHuddyCarousel();
            progressCloseWarningEl.classList.add('ph-display-none');
            progressSlowWarningEl.classList.add('ph-display-none');
            progressBarEl.classList.remove('progress-bar-striped', 'progress-bar-animated', 'bg-primary');
            progressBarEl.classList.add('bg-success');

            let text = await formatTotals(totals);
            [...stepMessages, economyMessage].filter(Boolean).forEach((message) => {
                text += ' — ' + message;
            });
            progressReportTextEl.textContent = text;
            progressReportEl.classList.remove('ph-display-none');
        };

        /**
         * Resolves the "distribute" flag to send for a step — only the mechanics with no
         * built-in distribution of their own (playercoin, pill and secret_drops each place
         * their own drops directly) read this flag; every other step type ignores it.
         *
         * @param {string} steptype The step type identifier.
         * @param {Object} runParams The generation params (theme, tone, size, include_*…).
         * @return {boolean}
         */
        const distributeFlagForStep = (steptype, runParams) => {
            const distributebystep = {
                playercoin: runParams.distribute_playercoin,
                pill: runParams.distribute_pill,
                'secret_drops': runParams.distribute_secret,
            };
            return steptype in distributebystep ? distributebystep[steptype] : true;
        };

        /**
         * Whether a step's own drop_ids should be forwarded into the accumulator for a later
         * auto_distribute step — Items and the RPG item are the only step types that ever
         * return a non-empty drop_ids, each gated by its own card's distribute checkbox.
         *
         * @param {string} steptype The step type identifier.
         * @param {Object} runParams The generation params (theme, tone, size, include_*…).
         * @return {boolean}
         */
        const shouldForwardDropIds = (steptype, runParams) => !(
            (steptype === 'items' && !runParams.distribute_items)
            || (steptype === 'progress_item' && !runParams.distribute_progress_item)
        );

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
                            'distribute': distributeFlagForStep(step.type, runParams),
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
                if (shouldForwardDropIds(step.type, runParams)) {
                    dropids.push(...result.drop_ids);
                }
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
                tradeModuleEl.checked, pillModuleEl.checked,
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
                'distribute_items': distributeChecked(distributeItemsEl),
                'include_progress_item': progressItemModuleEl.checked,
                // The wizard now bundles "RPG classes" and "full story arc" into a single
                // checkbox — see wizard_module_rpg in the template — so both server-side flags
                // are always sent together.
                'include_next_chapter': rpgModuleEl.checked,
                'include_comercio': tradeModuleEl.checked,
                'include_pill': pillModuleEl.checked,
                'include_latepenalty': latepenaltyChecked(),
                'include_secret_drops': secretModuleEl.checked,
                'include_ranking': rankingModuleEl.checked,
                'distribute_progress_item': distributeChecked(distributeProgressItemEl),
                'distribute_playercoin': distributeChecked(distributePlayercoinEl),
                'distribute_pill': distributeChecked(distributePillEl),
                'distribute_secret': distributeChecked(distributeSecretEl),
            };

            setProgressView(true);
            progressReportEl.classList.add('ph-display-none');
            progressErrorEl.classList.add('ph-display-none');
            progressCloseWarningEl.classList.remove('ph-display-none');
            progressBarEl.style.width = '0%';
            progressBarWrapEl.setAttribute('aria-valuenow', '0');
            progressSubstepEl.textContent = '';
            // A previous run in the same modal session may have left the bar green and static
            // (showProgressReport()'s completion state) — back to the in-progress look here.
            progressBarEl.classList.remove('bg-success');
            progressBarEl.classList.add('bg-primary', 'progress-bar-striped', 'progress-bar-animated');

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
