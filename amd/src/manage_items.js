define(['jquery', 'core/notification', 'core/ajax', 'core/copy_to_clipboard'], function($, Notification, Ajax) {

    /**
     * Manage Items module for PlayerHUD.
     *
     * @module     block_playerhud/manage_items
     * @copyright  2026 Jean Lúcio
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {

            // Move modals to body to avoid z-index issues.
            // IDs atualizados para kebab-case conforme Stylelint
            $('#ph-ai-modal').appendTo('body');
            $('#ph-item-modal-view').appendTo('body');

            // --- FIXED: Explicitly handle AI Modal opening to ensure it works even if moved ---
            $('body').on('click', '#btn-open-ai-modal', function(e) {
                e.preventDefault();
                var modalEl = document.getElementById('ph-ai-modal'); // ID ATUALIZADO
                if (modalEl) {
                    // eslint-disable-next-line no-undef
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        // eslint-disable-next-line no-undef
                        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        modal.show();
                    } else if ($(modalEl).modal) {
                        $(modalEl).modal('show');
                    }
                }
            });

            // --- 1. BULK ACTIONS ---

            // Select All Checkbox.
            $('#ph-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
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

            // --- 2. PREVIEW MODAL ---

            $('body').on('click', '.ph-preview-trigger', function(e) {
                e.preventDefault();
                var trigger = $(this);

                // Extract data attributes.
                var name = trigger.attr('data-name');
                var xp = trigger.attr('data-xp');
                var img = trigger.attr('data-image');
                var isImg = trigger.attr('data-isimage'); // "1" or "0"
                var descTarget = trigger.attr('data-desc-target');
                var descHtml = descTarget ? $('#' + descTarget).html() : '';

                // Populate Modal (Internal IDs kept as camelCase in Mustache, so we use them here)
                $('#phModalNameView, #phModalTitleView').text(name);
                $('#phModalXPView').text(xp);

                var $descEl = $('#phModalDescView');
                if (descHtml && descHtml.trim() !== '') {
                    $descEl.html(descHtml);
                } else {
                    $descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                // Image Handling.
                var $imgCont = $('#phModalImageContainerView');
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

                // Open Modal (Bootstrap 5 compatible)
                // CORREÇÃO: Usando o ID atualizado com hífen
                var modalEl = document.getElementById('ph-item-modal-view');

                // eslint-disable-next-line no-undef
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    // eslint-disable-next-line no-undef
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    // Fallback for older themes.
                    $(modalEl).modal('show');
                }
            });

            // --- 3. AI GENERATION (EXTERNAL API) ---

            // Toggle AI Drop Options visibility.
            $('#ai-drop').on('change', function() {
                var isChecked = $(this).is(':checked');
                if (isChecked) {
                    $('#ai-drop-options').slideDown();
                } else {
                    $('#ai-drop-options').slideUp();
                }
            });

            // Single Item Delete Confirmation.
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

            // Submit AI form on Enter key.
            $('#ph-ai-form').on('submit', function(e) {
                e.preventDefault();
                $('#ph-btn-conjure').click();
            });

            // AI Logic using Core AJAX.
            $('#ph-btn-conjure').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);

                if ($btn.prop('disabled')) {
                    return;
                }

                var theme = $('#ai-theme').val();
                if (!theme) {
                    Notification.alert('Error', config.strings.err_theme, 'OK');
                    return;
                }

                var originalText = $btn.text();
                $btn.prop('disabled', true).text(config.strings.ai_creating).attr('aria-busy', 'true');

                // Call Moodle External Function.
                /* eslint-disable camelcase */
                var request = {
                    methodname: 'block_playerhud_generate_ai_content',
                    args: {
                        instanceid: config.instanceid,
                        courseid: config.courseid,
                        theme: theme,
                        xp: parseInt($('#ai-xp').val()) || 0,
                        amount: parseInt($('#ai-amount').val()) || 1,
                        create_drop: $('#ai-drop').is(':checked'),
                        drop_location: $('#ai-location').val() || '',
                        drop_max: parseInt($('#ai-maxusage').val()) || 0,
                        drop_time: parseInt($('#ai-respawn').val()) || 0
                    }
                };
                /* eslint-enable camelcase */

                Ajax.call([request])[0].done(function(resp) {
                    $btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                    if (resp.success) {
                        // Reload page on modal close.
                        // CORREÇÃO: ID atualizado
                        $('#ph-ai-modal').one('hidden.bs.modal', function() {
                            window.location.reload();
                        });

                        var $modalTitle = $('#phAiModalLabel');
                        $modalTitle.text(config.strings.success_title);

                        // Container Principal
                        var successHtml = '<div id="ph-success-container" tabindex="-1" ';
                        successHtml += 'class="text-center py-3 ph-animate-fadein" style="outline: none;">';

                        // Ícone de Sucesso Animado
                        successHtml += '<div class="mb-3 text-success" style="font-size: 3rem;" aria-hidden="true">';
                        successHtml += '<i class="fa fa-check-circle"></i></div>';

                        // Handle item list display.
                        var items = resp.created_items || [];
                        if (items.length === 0 && resp.item_name) {
                            items.push(resp.item_name);
                        }
                        var itemsList = items.join(', ');
                        var count = items.length;

                        // TÍTULO
                        var titleText = config.strings.created_count.replace('{$a}', count);
                        successHtml += '<h5 class="text-muted text-uppercase small fw-bold mb-2">' + titleText + '</h5>';

                        // DESTAQUE
                        successHtml += '<h2 class="fw-bold text-dark mb-4 display-6">' + itemsList + '</h2>';

                        // Warnings and Info messages
                        if (resp.warning_msg) {
                            successHtml += '<div class="alert alert-warning small mb-3 text-start">';
                            successHtml += '<i class="fa fa-exclamation-triangle me-2" aria-hidden="true"></i> ';
                            successHtml += resp.warning_msg + '</div>';
                        } else if (resp.info_msg) {
                            // eslint-disable-next-line max-len
                            successHtml += '<div class="alert alert-success small mb-3 text-start border-success bg-success-subtle text-success-emphasis">';
                            successHtml += '<i class="fa fa-info-circle me-2" aria-hidden="true"></i> ';
                            successHtml += resp.info_msg + '</div>';
                        }

                        // Drop Code
                        if (resp.drop_code && count === 1) {
                            var fullCode = '[PLAYERHUD_DROP code=' + resp.drop_code + ']';

                            successHtml += '<div class="card bg-light border-0 p-3 mx-auto mt-4" style="max-width: 90%;">';
                            successHtml += '<label class="small text-muted mb-1 fw-bold text-start w-100" ';
                            successHtml += 'for="ph-gen-code-input">' + config.strings.copy + ':</label>';
                            successHtml += '<div class="input-group">';
                            successHtml += '<input type="text" class="form-control font-monospace text-center ph-code-input" ';
                            successHtml += 'value="' + fullCode + '" id="ph-gen-code-input" readonly>';
                            successHtml += '<button class="btn btn-primary" type="button" ';
                            successHtml += 'data-action="copytoclipboard" data-clipboard-target="#ph-gen-code-input">';
                            successHtml += '<i class="fa fa-copy" aria-hidden="true"></i> ';
                            successHtml += config.strings.copy + '</button>';
                            successHtml += '</div></div>';
                        }

                        successHtml += '</div>';

                        // CORREÇÃO: IDs atualizados
                        $('#ph-ai-modal .modal-body').html(successHtml);

                        // Botão de Fechar/Recarregar
                        var $btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' +
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
                        Notification.alert('Error', resp.message, 'OK');
                    }
                }).fail(function(ex) {
                    $btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');
                    Notification.exception(ex);
                });
            });
        }
    };
});
