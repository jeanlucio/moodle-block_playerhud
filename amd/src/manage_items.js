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

define(['jquery', 'core/notification', 'core/ajax', 'core/str', 'core/copy_to_clipboard'], function($, Notification, Ajax, Str) {

    /**
     * Manage Items module for PlayerHUD.
     *
     * @module     block_playerhud/manage_items
     * @copyright  2026 Jean Lúcio
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {

            // Move modals to body to avoid z-index issues.
            // IDs updated to kebab-case per Stylelint.
            $('#ph-ai-modal').appendTo('body');
            $('#ph-item-modal-view').appendTo('body');

            // Explicitly handle AI Modal opening to ensure it works even if moved.
            $('body').on('click', '#btn-open-ai-modal', function(e) {
                e.preventDefault();
                const modalEl = document.getElementById('ph-ai-modal');
                if (modalEl) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    } else if ($(modalEl).modal) {
                        $(modalEl).modal('show');
                    }
                }
            });

            // --- 1. BULK ACTIONS ---

            // Select All Checkbox.
            $('#ph-select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            // Update "Delete Selected" button state.
            $('body').on('change', '.ph-bulk-check, #ph-select-all', function() {
                const count = $('.ph-bulk-check:checked').length;
                const $btn = $('#ph-btn-bulk-delete');

                if (count > 0) {
                    $btn.removeClass('disabled').removeAttr('disabled');
                    const btnText = config.strings.delete_n_items.replace('%d', count);
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

            // --- 2. PREVIEW MODAL ---

            $('body').on('click', '.ph-preview-trigger', function(e) {
                e.preventDefault();
                const trigger = $(this);

                // Extract data attributes.
                const name = trigger.attr('data-name');
                const xp = trigger.attr('data-xp');
                const img = trigger.attr('data-image');
                const isImg = trigger.attr('data-isimage'); // "1" or "0"
                const descTarget = trigger.attr('data-desc-target');
                const descHtml = descTarget ? $('#' + descTarget).html() : '';

                // Populate Modal.
                $('#phModalNameView').text(name);

                if (xp && xp.trim() !== '') {
                    $('#phModalXPView').text(xp).removeClass('ph-display-none').show();
                } else {
                    $('#phModalXPView').hide();
                }

                // Hide badges by default. They can be shown if needed based on item type or other logic.
                $('#phModalCountBadgeView').hide();

                const $descEl = $('#phModalDescView');
                if (descHtml && descHtml.trim() !== '') {
                    $descEl.html(descHtml);
                } else {
                    $descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                // Image Handling.
                const $imgCont = $('#phModalImageContainerView');
                $imgCont.empty();

                if (isImg === '1') {
                    $imgCont.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img',
                        alt: ''
                    }));
                } else {
                    $imgCont.append($('<span>', {
                        'class': 'ph-modal-emoji',
                        'aria-hidden': 'true',
                        text: img
                    }));
                }

                // Open Modal (Bootstrap 5 compatible).
                const modalEl = document.getElementById('ph-item-modal-view');

                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    // Fallback for older themes.
                    $(modalEl).modal('show');
                }
            });

            // --- 3. AI GENERATION (EXTERNAL API) ---

            // Toggle AI Drop Options visibility.
            $('#ai-drop').on('change', function() {
                const isChecked = $(this).is(':checked');
                if (isChecked) {
                    $('#ai-drop-options').slideDown();
                } else {
                    $('#ai-drop-options').slideUp();
                }
            });

            // Single Item Delete Confirmation.
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

            // Submit AI form on Enter key.
            $('#ph-ai-form').on('submit', function(e) {
                e.preventDefault();
                $('#ph-btn-conjure').click();
            });

            // AI Logic using Core AJAX.
            $('#ph-btn-conjure').on('click', function(e) {
                e.preventDefault();
                const $btn = $(this);

                if ($btn.prop('disabled')) {
                    return;
                }

                const theme = $('#ai-theme').val();
                if (!theme) {
                    Str.get_strings([
                        {key: 'error', component: 'core'},
                        {key: 'ok', component: 'core'}
                    ]).then(function(strs) {
                        Notification.alert(strs[0], config.strings.err_theme, strs[1]);
                        return true;
                    }).catch(Notification.exception);
                    return;
                }

                const originalText = $btn.text();
                $btn.prop('disabled', true).text(config.strings.ai_creating).attr('aria-busy', 'true');

                // Handle XP input: if empty, send -1 to indicate "no change". Otherwise, parse integer.
                const xpInput = $('#ai-xp').val();
                const xpToSend = xpInput === '' ? -1 : parseInt(xpInput, 10);

                // Call Moodle External Function.
                const requestArgs = {
                    instanceid: config.instanceid,
                    courseid: config.courseid,
                    theme: theme,
                    xp: isNaN(xpToSend) ? -1 : xpToSend,
                    amount: parseInt($('#ai-amount').val()) || 1,
                    // eslint-disable-next-line camelcase
                    create_drop: $('#ai-drop').is(':checked'),
                    // eslint-disable-next-line camelcase
                    drop_location: $('#ai-location').val() || '',
                    // eslint-disable-next-line camelcase
                    drop_max: parseInt($('#ai-maxusage').val()) || 0,
                    // eslint-disable-next-line camelcase
                    drop_time: parseInt($('#ai-respawn').val()) || 0
                };

                const request = {
                    methodname: 'block_playerhud_generate_ai_content',
                    args: requestArgs
                };

                Ajax.call([request])[0].done(function(resp) {
                    $btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                    if (resp.success) {
                        // Reload page on modal close.
                        $('#ph-ai-modal').one('hidden.bs.modal', function() {
                            window.location.reload();
                        });

                        const $modalTitle = $('#phAiModalLabel');
                        $modalTitle.text(config.strings.success_title);

                        // Handle item list display.
                        const items = resp.created_items || [];
                        if (items.length === 0 && resp.item_name) {
                            items.push(resp.item_name);
                        }
                        const count = items.length;

                        // Title.
                        const titleText = config.strings.created_count.replace('{$a}', count);

                        // Build the static structure; AI-sourced text is set via .text() below.
                        let successHtml = '<div id="ph-success-container" tabindex="-1" ';
                        successHtml += 'class="text-center py-3 ph-animate-fadein" style="outline: none;">';
                        successHtml += '<div class="mb-3 text-success" style="font-size: 3rem;" aria-hidden="true">';
                        successHtml += '<i class="fa fa-check-circle"></i></div>';
                        successHtml += '<h5 class="text-muted text-uppercase small fw-bold mb-2">' + titleText + '</h5>';
                        successHtml += '<h2 class="fw-bold text-dark mb-4 display-6" id="ph-gen-names-heading"></h2>';

                        // Warnings and Info messages (sourced from PHP get_string — trusted).
                        if (resp.warning_msg) {
                            successHtml += '<div class="alert alert-warning small mb-3 text-start">';
                            successHtml += '<i class="fa fa-exclamation-triangle me-2" aria-hidden="true"></i> ';
                            successHtml += resp.warning_msg + '</div>';
                        } else if (resp.info_msg) {
                            successHtml += '<div class="alert alert-success small mb-3 text-start ' +
                                'border-success bg-success-subtle text-success-emphasis">';
                            successHtml += '<i class="fa fa-info-circle me-2" aria-hidden="true"></i> ';
                            successHtml += resp.info_msg + '</div>';
                        }

                        // Drop Code.
                        if (resp.drop_code && count === 1) {
                            successHtml += '<div class="card bg-light border-0 p-3 mx-auto mt-4" style="max-width: 90%;">';
                            successHtml += '<label class="small text-muted mb-1 fw-bold text-start w-100" ';
                            successHtml += 'for="ph-gen-code-input">' + config.strings.copy + ':</label>';
                            successHtml += '<div class="input-group">';
                            successHtml += '<input type="text" class="form-control font-monospace text-center ph-code-input" ';
                            successHtml += 'id="ph-gen-code-input" value="" readonly>';
                            successHtml += '<button class="btn btn-primary" type="button" ';
                            successHtml += 'data-action="copytoclipboard" data-clipboard-target="#ph-gen-code-input">';
                            successHtml += '<i class="fa fa-copy" aria-hidden="true"></i> ';
                            successHtml += config.strings.copy + '</button>';
                            successHtml += '</div></div>';
                        }

                        successHtml += '</div>';

                        $('#ph-ai-modal .modal-body').html(successHtml);

                        // Set AI-generated content safely via .text() to prevent XSS.
                        $('#ph-gen-names-heading').text(items.join(', '));
                        if (resp.drop_code && count === 1) {
                            $('#ph-gen-code-input').val('[PLAYERHUD_DROP code=' + resp.drop_code + ']');
                        }

                        // Reload/Close Button.
                        const $btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' +
                             config.strings.great + '</button>');
                        $btnReload.on('click', function() {
                            window.location.reload();
                        });

                        $('#ph-ai-modal .modal-footer').empty().append($btnReload);

                        // Accessibility focus.
                        setTimeout(function() {
                            $('#ph-success-container').focus();
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
                    $btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');
                    Notification.exception(ex);
                });
            });
        }
    };
});
