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
 * Manage Quests module for PlayerHUD.
 *
 * @module     block_playerhud/manage_quests
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/notification'], function($, Notification) {
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {

            // Single Delete Button.
            $('body').on('click', '.js-delete-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var targetUrl = $btn.attr('href');
                var msg = $btn.attr('data-confirm-msg');

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

            // Select All Checkbox.
            $('#ph-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            // Suggestion form: toggle all checkboxes.
            $('body').on('click', '#ph-sug-toggle-all', function() {
                var $checks = $('input[type="checkbox"][name^="sug_"]');
                var allChecked = $checks.length === $checks.filter(':checked').length;
                $checks.prop('checked', !allChecked);
            });

            // Update "Delete Selected" button state.
            $('body').on('change', '.ph-bulk-check, #ph-select-all', function() {
                var count = $('.ph-bulk-check:checked').length;
                var $btn = $('#ph-btn-bulk-delete');

                if (count > 0) {
                    $btn.removeClass('disabled').removeAttr('disabled');
                    var btnText = config.strings.delete_n_items.replace('%d', count);
                    $btn.html('<i class="fa fa-trash" aria-hidden="true"></i> ' + btnText);
                } else {
                    $btn.addClass('disabled').attr('disabled', 'disabled');
                    $btn.html('<i class="fa fa-trash" aria-hidden="true"></i> ' + config.strings.delete_selected);
                }
            });

            // Confirm Bulk Delete.
            $('#ph-btn-bulk-delete').on('click', function(e) {
                e.preventDefault();
                if ($('.ph-bulk-check:checked').length === 0) {
                    return;
                }

                Notification.confirm(
                    config.strings.confirm_title,
                    config.strings.confirm_bulk,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        $('#bulk-action-form').submit();
                    }
                );
            });
        }
    };
});
