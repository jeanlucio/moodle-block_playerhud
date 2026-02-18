/* global bootstrap */
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Student View JS for PlayerHUD.
 *
 * @module     block_playerhud/view
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification'], function($, Notification) {

    return {
        /**
         * Initialize the view script.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {
            // Move modal to body end to avoid z-index issues.
            $('#ph-item-modal-view').appendTo('body');

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
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    // Bootstrap 5 (Moodle 4.x Default).
                    try {
                        const m = bootstrap.Modal.getOrCreateInstance(el);
                        m.show();
                    } catch (e) {
                        // eslint-disable-next-line no-console
                        console.error(e);
                    }
                } else {
                    // Fallback for older themes.
                    $(el).modal('show');
                }
            };

            // Event Delegation for clicking on items.
            $(document).on('click', '.ph-item-trigger', function(e) {
                e.preventDefault();
                const trigger = $(this);

                // Extract data.
                const name = trigger.attr('data-name');
                const xp = trigger.attr('data-xp');
                const img = trigger.attr('data-image');
                const isImg = trigger.attr('data-isimage'); // String "1" or "0".
                const date = trigger.attr('data-date'); // Fallback (PHP Text).
                const timestamp = trigger.attr('data-timestamp'); // Raw Timestamp.
                const count = trigger.attr('data-count');
                const desc = trigger.find('.ph-item-description-content').html();

                // Populate Modal.
                $('#phModalTitleView, #phModalNameView').text(name);
                $('#phModalXPView').text(xp);

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
