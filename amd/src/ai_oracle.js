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

define(['jquery', 'core/notification', 'core/ajax', 'core/str'], function($, Notification, Ajax, Str) {

    /**
     * Class Oracle AI module for PlayerHUD.
     *
     * Handles the AI-powered class generation modal on the manage classes tab.
     *
     * @module     block_playerhud/ai_oracle
     * @copyright  2026 Jean Lúcio
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    return {
        /**
         * Initialize the Class Oracle module.
         *
         * @param {number} instanceid Block instance ID.
         * @param {number} courseid   Course ID.
         * @param {Object} strings    Localised strings keyed by name.
         */
        init: function(instanceid, courseid, strings) {

            // Move modal to body to avoid z-index issues inside Moodle block regions.
            $('#ph-ai-oracle-modal').appendTo('body');

            // Submit handler for the generate button inside the modal.
            $('body').on('click', '[data-action="ai-oracle-submit"]', function() {
                var $btn = $(this);
                var theme = $('#ph-oracle-theme').val().trim();
                var count = parseInt($('#ph-oracle-count').val(), 10) || 1;

                if (!theme) {
                    Str.get_strings([
                        {key: 'error', component: 'core'},
                        {key: 'ok', component: 'core'}
                    ]).then(function(strs) {
                        Notification.alert(strs[0], strings.validation_theme, strs[1]);
                        return true;
                    }).catch(Notification.exception);
                    return;
                }

                var originalText = $btn.html();
                var names = [];
                var current = 0;

                /**
                 * Update button label showing generation progress.
                 */
                function updateProgress() {
                    $btn.html(
                        '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ' +
                        strings.ai_creating + ' (' + current + '/' + count + ')'
                    );
                }

                /**
                 * Generate the next character and recurse until count is reached.
                 *
                 * @returns {Promise}
                 */
                function generateNext() {
                    current++;
                    updateProgress();

                    return Ajax.call([{
                        methodname: 'block_playerhud_generate_class_oracle',
                        args: {
                            instanceid: instanceid,
                            courseid:   courseid,
                            theme:      theme
                        }
                    }])[0].then(function(resp) {
                        if (resp.success) {
                            names.push(resp.class_name);
                        }
                        if (current < count) {
                            return generateNext();
                        }
                        return true;
                    });
                }

                $btn.prop('disabled', true).attr('aria-busy', 'true');

                generateNext().then(function() {
                    $btn.prop('disabled', false).html(originalText).removeAttr('aria-busy');

                    if (names.length > 0) {
                        // Build result using jQuery DOM methods so AI-returned names are never
                        // treated as markup, regardless of what the model returns.
                        var $result = $('<div>', {
                            'class': 'text-center py-3 ph-animate-fadein',
                            tabindex: '-1',
                            id: 'ph-oracle-result'
                        });
                        $('<div>', {'class': 'mb-3 text-success', 'aria-hidden': 'true', css: {'font-size': '3rem'}})
                            .html('<i class="fa fa-check-circle"></i>')
                            .appendTo($result);

                        if (names.length === 1) {
                            var successMsg = strings.oracle_success.replace('{$a}', names[0]);
                            $('<h5>', {'class': 'fw-bold mb-1'}).text(names[0]).appendTo($result);
                            $('<p>', {'class': 'text-muted small'}).text(successMsg).appendTo($result);
                        } else {
                            var successMsgMulti = strings.oracle_success_multi.replace('{$a}', names.length);
                            var $ul = $('<ul>', {'class': 'list-unstyled mb-2'});
                            for (var i = 0; i < names.length; i++) {
                                $('<li>', {'class': 'fw-bold'}).text(names[i]).appendTo($ul);
                            }
                            $ul.appendTo($result);
                            $('<p>', {'class': 'text-muted small'}).text(successMsgMulti).appendTo($result);
                        }

                        $('#ph-ai-oracle-modal .modal-body').empty().append($result);
                        $('#ph-ai-oracle-modal .modal-footer').html(
                            '<button type="button" class="btn btn-success fw-bold px-4" ' +
                            'data-action="oracle-reload">' + strings.ok_reload + '</button>'
                        );

                        setTimeout(function() {
                            $('#ph-oracle-result').focus();
                        }, 200);
                    }
                    return true;
                }).fail(function(ex) {
                    $btn.prop('disabled', false).html(originalText).removeAttr('aria-busy');
                    Notification.exception(ex);
                });
            });

            // Reload page when user dismisses after a successful generation.
            $('body').on('click', '[data-action="oracle-reload"]', function() {
                window.location.reload();
            });

            // Reset modal form when it closes (so it is clean for the next use).
            $('body').on('hidden.bs.modal', '#ph-ai-oracle-modal', function() {
                if ($('#ph-oracle-result').length === 0) {
                    return;
                }
                window.location.reload();
            });
        }
    };
});
