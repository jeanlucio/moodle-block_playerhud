/* global bootstrap */
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
            $('#phItemModalView').appendTo('body');

            // 1. Disable HUD Confirmation.
            $('.js-disable-hud').on('click', function(e) {
                e.preventDefault();
                var url = $(this).attr('href');
                var msg = $(this).attr('data-confirm-msg');

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
            function openItemModal() {
                var el = document.getElementById('phItemModalView');
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $(el).modal('show');
                } else {
                    // Fallback for strict Moodle 4.x BS5.
                    try {
                        var m = bootstrap.Modal.getOrCreateInstance(el);
                        m.show();
                    } catch (e) {
                        // eslint-disable-next-line no-console
                        console.error(e);
                    }
                }
            }

            // Event Delegation for clicking on items.
            $(document).on('click', '.ph-item-trigger', function(e) {
                e.preventDefault();
                var trigger = $(this);

                // Extract data.
                var name = trigger.attr('data-name');
                var xp = trigger.attr('data-xp');
                var img = trigger.attr('data-image');
                var isImg = trigger.attr('data-isimage'); // String "1" or "0".
                var date = trigger.attr('data-date');
                var count = trigger.attr('data-count');
                var desc = trigger.find('.ph-item-description-content').html();

                // Populate Modal.
                $('#phModalTitleView, #phModalNameView').text(name);
                $('#phModalXPView').text(xp);

                var descEl = $('#phModalDescView');
                if (desc && desc.trim() !== '') {
                    descEl.html(desc);
                } else {
                    descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                var badgeEl = $('#phModalCountBadgeView');
                if (count && count > 0) {
                    badgeEl.text('x' + count).show();
                } else {
                    badgeEl.hide();
                }

                // Image Handling.
                var imgCont = $('#phModalImageContainerView');
                imgCont.empty();

                if (isImg == '1' || isImg === 'true') {
                    imgCont.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img',
                        alt: '',
                        style: 'max-width:120px; max-height:120px; object-fit:contain;'
                    }));
                } else {
                    // Emoji.
                    imgCont.append($('<span>', {
                        'class': 'ph-modal-emoji',
                        'aria-hidden': 'true',
                        style: 'font-size:80px; line-height:1;',
                        text: img
                    }));
                }

                // Date.
                var dateEl = $('#phModalDateView');
                if (date) {
                    dateEl.find('span').text(date);
                    dateEl.show();
                } else {
                    dateEl.hide();
                }

                openItemModal();
            });
        }
    };
});
