/* global bootstrap */
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
 * Student View JS for PlayerHUD.
 *
 * @module     block_playerhud/view
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification'], function($, Notification) {

    return {
        /**
         * Initialize the view script.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {
            // Move modals to body end to avoid z-index/stacking-context issues.
            $('#ph-item-modal-view').appendTo('body');
            $('#ph-char-modal').appendTo('body');

            // Char modal — explicit handler (cross-version safe: BS4 + BS5).
            // data-bs-toggle declarative may not fire in Moodle 4.5 if Bootstrap's
            // document-level listener hasn't been registered yet via AMD.
            $(document).off('click.phcharmodal').on('click.phcharmodal', '[data-ph-modal="ph-char-modal"]', function() {
                var el = document.getElementById('ph-char-modal');
                if (!el) {
                    return;
                }
                document.body.appendChild(el);
                require(['theme_boost/bootstrap/modal'], function(BootstrapModal) {
                    var inst = (BootstrapModal.getInstance && BootstrapModal.getInstance(el))
                        || $(el).data('bs.modal')
                        || new BootstrapModal(el);
                    inst.show();
                });
            });

            // Hoist the history/help shortcut buttons into the block's title row
            // so they appear on the same line as "PlayerHUD".
            (function() {
                var sidebar = document.querySelector('.block_playerhud_sidebar');
                if (!sidebar) {
                    return;
                }
                var btnRow = sidebar.querySelector('.ph-header-actions');
                if (!btnRow) {
                    return;
                }
                var block = sidebar.closest('.block_playerhud');
                if (!block) {
                    return;
                }
                var titleEl = block.querySelector('.card-title');
                if (!titleEl) {
                    return;
                }
                // Wrap the title + buttons in a flex row so they appear side by side.
                var wrapper = document.createElement('div');
                wrapper.className = 'd-flex align-items-center mb-2';
                titleEl.parentElement.insertBefore(wrapper, titleEl);
                titleEl.classList.add('mb-0');
                wrapper.appendChild(titleEl);
                btnRow.remove();
                btnRow.classList.add('ms-auto');
                wrapper.appendChild(btnRow);
            }());

            // 1. Disable HUD Confirmation.
            $('.js-disable-hud').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const msg = $(this).attr('data-confirm-msg');

                Notification.confirm(
                    config.strings.confirm_title,
                    msg,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        window.location.href = url;
                    }
                );
            });

            // Initialize tooltips for quest description info buttons.
            document.querySelectorAll('.js-ph-quest-info[data-bs-toggle="tooltip"]').forEach(function(el) {
                if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                    new bootstrap.Tooltip(el, {trigger: 'hover focus'});
                }
            });

            // Accessibility: Allow opening items with Enter or Space.
            $(document).on('keydown', '.ph-item-trigger', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // 2. Item Details Modal Logic.
            /**
             * Helper to open/close bootstrap modal safely.
             */
            const openItemModal = () => {
                const el = document.getElementById('ph-item-modal-view');
                if (!el) {
                    return;
                }
                document.body.appendChild(el);
                require(['theme_boost/bootstrap/modal'], function(BootstrapModal) {
                    var inst = (BootstrapModal.getInstance && BootstrapModal.getInstance(el))
                        || $(el).data('bs.modal')
                        || new BootstrapModal(el);
                    inst.show();
                });
            };

            // Live Search for History.
            $('#ph-live-search').on('input', function() {
                const term = $(this).val().toLowerCase();
                $('.ph-searchable-row').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) > -1);
                });
            });

            // Handle sort selector redirects.
            $(document).on('change', '.ph-sort-selector', function() {
                const targetUrl = $(this).val();
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });

            // Client-side filter for story chapters.
            $(document).on('change', '.ph-story-filter-selector', function() {
                const value = $(this).val();
                $('#ph-story-grid .playerhud-item-card').each(function() {
                    const status = $(this).data('status');
                    const show = value === 'all' ||
                        (value === 'read' && status === 'completed') ||
                        (value === 'unread' && status !== 'completed');
                    $(this).toggle(show);
                });
            });

            // Event Delegation for clicking on items.
            // Use namespaced event to prevent duplicate handlers when init() is called more than once.
            $(document).off('click.phitemview').on('click.phitemview', '.ph-item-trigger', function(e) {
                e.preventDefault();
                const trigger = $(this);

                // Extract data.
                const name = trigger.attr('data-name');
                const img = trigger.attr('data-image');
                const isImg = trigger.attr('data-isimage'); // String "1" or "0".
                const date = trigger.attr('data-date'); // Fallback (PHP Text).
                const timestamp = trigger.attr('data-timestamp'); // Raw Timestamp.
                const count = trigger.attr('data-count');
                const desc = trigger.find('.ph-item-description-content').html();

                // Populate Modal.
                $('#phModalNameView').text(name);

                const descEl = $('#phModalDescView');
                if (desc && desc.trim() !== '') {
                    descEl.html(desc);
                } else {
                    descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                const badgeEl = $('#phModalCountBadgeView');
                if (count && count > 0) {
                    badgeEl.text('x' + count).show();
                } else {
                    badgeEl.hide();
                }

                // Image Handling.
                const imgCont = $('#phModalImageContainerView');
                imgCont.empty();

                if (isImg == '1' || isImg === 'true') {
                    imgCont.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img ph-img-contain-120',
                        alt: ''
                    }));
                } else {
                    // Emoji.
                    imgCont.append($('<span>', {
                        'class': 'ph-modal-emoji ph-emoji-80',
                        'aria-hidden': 'true',
                        text: img
                    }));
                }

                // --- Date Internationalization Logic ---
                const dateEl = $('#phModalDateView');
                let formattedDate = '';

                if (timestamp && timestamp > 0) {
                    let lang = $('html').attr('lang') || 'en';
                    lang = lang.replace('_', '-');

                    try {
                        formattedDate = new Date(parseInt(timestamp) * 1000).toLocaleDateString(lang, {
                            day: '2-digit',
                            month: '2-digit',
                            year: '2-digit'
                        });
                    } catch (err) {
                        formattedDate = date;
                    }
                } else {
                    formattedDate = date;
                }

                if (formattedDate) {
                    const prefix = (config.strings && config.strings.last_collected) ?
                        config.strings.last_collected + ' ' : '';

                    dateEl.find('span').text(prefix + formattedDate);
                    dateEl.show();
                } else {
                    dateEl.hide();
                }

                openItemModal();
            });
        }
    };
});
