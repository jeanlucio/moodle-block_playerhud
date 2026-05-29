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
 * Manage Trades module for PlayerHUD.
 *
 * @module     block_playerhud/manage_trades
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification', 'core/copy_to_clipboard'], function($, Notification) {
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {
            // Initialize popovers for compact trade item icons (> 3 items).
            var shopPopoverEls = document.querySelectorAll('.ph-shop-popover');
            if (shopPopoverEls.length) {
                require(['theme_boost/bootstrap/popover'], function(BSPopover) {
                    shopPopoverEls.forEach(function(el) {
                        var opts = {
                            trigger: 'hover click focus',
                            title: el.dataset.phTitle || '',
                            content: el.dataset.phContent || '',
                            html: true,
                            placement: 'top'
                        };
                        if (typeof BSPopover === 'function') {
                            new BSPopover(el, opts);
                        } else {
                            $(el).popover(opts);
                        }
                    });
                });
            }

            // Toggle-all for trade suggestion checkboxes.
            $('body').on('click', '#ph-trade-sug-toggle-all', function() {
                const $checks = $('input[type="checkbox"][name^="sug_"]');
                const allChecked = $checks.length === $checks.filter(':checked').length;
                $checks.prop('checked', !allChecked);
            });

            // Clean interceptor to use Moodle's native Confirmation Box.
            $('body').on('click', '.js-delete-btn', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const targetUrl = $btn.attr('href');
                const msg = $btn.attr('data-confirm-msg');

                Notification.confirm(
                    config.strings.confirm_title,
                    msg,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        window.location.href = targetUrl;
                    }
                );
            });
        }
    };
});
