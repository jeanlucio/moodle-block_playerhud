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
     * Story Generation AI module for PlayerHUD.
     *
     * Handles the AI-powered story chapter generation modal on the manage chapters tab.
     *
     * @module     block_playerhud/ai_story
     * @copyright  2026 Jean Lúcio
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    /**
     * Builds the success panel shown after a chapter is generated.
     *
     * The title comes from the AI, so it is only ever set as text: it is never concatenated into
     * markup, whatever the model answered with.
     *
     * @param {string} title Chapter title.
     * @param {string} template Success message, with the '{$a}' placeholder for the title.
     * @returns {jQuery} The panel element.
     */
    const renderResult = (title, template) => {
        const $panel = $('<div class="text-center py-3 ph-animate-fadein" tabindex="-1" id="ph-story-result"></div>');
        $panel.append(
            $('<div class="mb-3 text-success" style="font-size:3rem" aria-hidden="true"></div>')
                .append('<i class="fa fa-check-circle"></i>')
        );
        $panel.append($('<h5 class="fw-bold mb-1"></h5>').text(title));
        $panel.append($('<p class="text-muted small"></p>').text(template.replace('{$a}', title)));
        return $panel;
    };

    return {
        renderResult: renderResult,

        /**
         * Initialize the Story Generation module.
         *
         * @param {number} instanceid Block instance ID.
         * @param {number} courseid   Course ID.
         * @param {Object} strings    Localised strings keyed by name.
         */
        init: function(instanceid, courseid, strings) {

            // Move modal to body to avoid z-index issues inside Moodle block regions.
            $('#ph-ai-story-modal').appendTo('body');

            // Submit handler for the generate button inside the modal.
            $('body').on('click', '[data-action="ai-story-submit"]', function() {
                var $btn = $(this);
                var theme = $('#ph-story-theme').val().trim();

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

                var karmaGain = parseInt($('#ph-story-karma-gain').val(), 10) || 0;
                var karmaLoss = parseInt($('#ph-story-karma-loss').val(), 10) || 0;
                var itemId = parseInt($('#ph-story-item-id').val(), 10) || 0;
                var itemQty = parseInt($('#ph-story-item-qty').val(), 10) || 0;

                var originalText = $btn.html();
                $btn.prop('disabled', true)
                    .html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> ' +
                          strings.ai_creating)
                    .attr('aria-busy', 'true');

                Ajax.call([{
                    methodname: 'block_playerhud_generate_story',
                    args: {
                        instanceid: instanceid,
                        courseid: courseid,
                        theme: theme,
                        karmagain: karmaGain,
                        karmaloss: karmaLoss,
                        itemid: itemId,
                        itemqty: itemQty
                    }
                }])[0].done(function(resp) {
                    $btn.prop('disabled', false).html(originalText).removeAttr('aria-busy');

                    if (resp.success) {
                        // Replace modal body with success message.
                        $('#ph-ai-story-modal .modal-body').empty().append(
                            renderResult(resp.chapter_title, strings.story_success)
                        );
                        $('#ph-ai-story-modal .modal-footer').html(
                            '<button type="button" class="btn btn-success fw-bold px-4" ' +
                            'data-action="story-reload">' + strings.ok_reload + '</button>'
                        );

                        setTimeout(function() {
                            $('#ph-story-result').focus();
                        }, 200);
                    } else {
                        Str.get_strings([
                            {key: 'error', component: 'core'},
                            {key: 'ok', component: 'core'}
                        ]).then(function(strs) {
                            Notification.alert(strs[0], resp.message, strs[1]);
                            return true;
                        }).catch(Notification.exception);
                    }
                }).fail(function(ex) {
                    $btn.prop('disabled', false).html(originalText).removeAttr('aria-busy');
                    Notification.exception(ex);
                });
            });

            // Reload page when user dismisses after a successful generation.
            $('body').on('click', '[data-action="story-reload"]', function() {
                window.location.reload();
            });

            // Reload if user closes modal after a successful generation.
            $('body').on('hidden.bs.modal', '#ph-ai-story-modal', function() {
                if ($('#ph-story-result').length === 0) {
                    return;
                }
                window.location.reload();
            });
        }
    };
});
