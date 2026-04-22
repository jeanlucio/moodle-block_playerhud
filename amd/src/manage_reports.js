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
 * Reports management module.
 *
 * @module     block_playerhud/manage_reports
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification'], function($, Notification) {
    return {
        init: function(config) {
            // User selector for redirection.
            $('#r_userid').on('change', function() {
                const url = config.baseUrl + '&r_userid=' + $(this).val();
                window.location.href = url;
            });

            // Live Search for Audit Logs.
            $('#ph-live-search').on('input', function() {
                const term = $(this).val().toLowerCase();
                $('.ph-searchable-row').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) > -1);
                });
            });

            // Toggle for showing/hiding old AI logs.
            $('#btn-ai-toggle').on('click', function(e) {
                e.preventDefault();
                const rows = $('.ph-ai-hidden');

                if (!rows.length) {
                    return;
                }

                const isHidden = rows.first().is(':hidden');

                if (isHidden) {
                    rows.show();
                    $(this).html('<i class="fa fa-chevron-up me-1" aria-hidden="true"></i> ' + config.strLess);
                } else {
                    rows.hide();
                    $(this).html('<i class="fa fa-chevron-down me-1" aria-hidden="true"></i> ' + config.strMore);
                }
            });

            // Default Moodle confirmation for deleting items.
            $('.js-delete-report-btn').on('click', function(e) {
                e.preventDefault();
                const targetUrl = $(this).attr('href');
                const msg = $(this).attr('data-confirm-msg');

                Notification.confirm(
                    config.strConfirmTitle,
                    msg,
                    config.strYes,
                    config.strCancel,
                    function() {
                        window.location.href = targetUrl;
                    }
                );
            });
        }
    };
});
