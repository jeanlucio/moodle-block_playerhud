// eslint-disable-next-line no-redeclare
/* global bootstrap, M */
define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Manage Items module.
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

            // Move modais para o body para evitar problemas de z-index/overflow
            $('#phAiModal').appendTo('body');
            $('#phItemModalView').appendTo('body');

            // --- 1. LÓGICA DE AÇÕES EM MASSA (Bulk Actions) ---

            // "Selecionar Todos"
            $('#ph-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            // Atualiza estado do botão "Excluir Selecionados"
            $('body').on('change', '.ph-bulk-check, #ph-select-all', function() {
                var count = $('.ph-bulk-check:checked').length;
                var btn = $('#ph-btn-bulk-delete');

                if (count > 0) {
                    btn.removeClass('disabled').removeAttr('disabled');
                    // Substitui o placeholder %d pelo número
                    var btnText = config.strings.delete_n_items.replace('%d', count);
                    btn.html('<i class="fa fa-trash"></i> ' + btnText);
                } else {
                    btn.addClass('disabled').attr('disabled', 'disabled');
                    btn.html('<i class="fa fa-trash"></i> ' + config.strings.delete_selected);
                }
            });

            // Confirmação de Exclusão em Massa
            $('#ph-btn-bulk-delete').on('click', function(e) {
                e.preventDefault();
                var count = $('.ph-bulk-check:checked').length;

                if (count === 0) {
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

            // --- 2. LÓGICA DE VISUALIZAÇÃO (Preview Modal) ---

            $('body').on('click', '.ph-preview-trigger', function(e) {
                e.preventDefault();
                var trigger = $(this);

                // Extrair dados
                var name = trigger.attr('data-name');
                var xp = trigger.attr('data-xp');
                var img = trigger.attr('data-image');
                var isImg = trigger.attr('data-isimage'); // "1" ou "0"

                // Descrição (buscada de div oculta para segurança HTML)
                var descTarget = trigger.attr('data-desc-target');
                var descHtml = '';
                if (descTarget) {
                    descHtml = $('#' + descTarget).html();
                }

                // Povoar Modal
                $('#phModalNameView, #phModalTitleView').text(name);
                $('#phModalXPView').text(xp);

                var descEl = $('#phModalDescView');
                if (descHtml && descHtml.trim() !== '') {
                    descEl.html(descHtml);
                } else {
                    descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                // Imagem / Emoji
                var imgCont = $('#phModalImageContainerView');
                imgCont.empty();

                if (isImg === '1') {
                    imgCont.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img',
                        alt: '',
                        style: 'max-width:100px; max-height:100px; object-fit:contain;'
                    }));
                } else {
                    imgCont.append($('<span>', {
                        'class': 'ph-modal-emoji',
                        'aria-hidden': 'true',
                        style: 'font-size:60px; line-height:1;',
                        text: img
                    }));
                }

                // Abrir Modal (Compatibilidade BS5)
                var modalEl = document.getElementById('phItemModalView');
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    $(modalEl).modal('show');
                }
            });

            // --- 3. LÓGICA EXISTENTE (Delete Único e IA) ---

            // Toggle Opções Drop (IA)
            $('#ai-drop').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ai-drop-options').slideDown();
                } else {
                    $('#ai-drop-options').slideUp();
                }
            });

            // Confirmação de Delete Único
            $('body').on('click', '.js-delete-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var targetUrl = btn.attr('href');
                var msg = btn.attr('data-confirm-msg');

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

            // Submissão do Form IA via Enter
            $('#ph-ai-form').on('submit', function(e) {
                e.preventDefault();
                $('#ph-btn-conjure').click();
            });

            // Lógica AJAX da IA
            $('#ph-btn-conjure').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);

                if (btn.prop('disabled')) {
                    return;
                }

                var theme = $('#ai-theme').val();
                var xp = $('#ai-xp').val();
                var amount = $('#ai-amount').val() || 1;
                var createDrop = $('#ai-drop').is(':checked');

                var locName = $('#ai-location').val();
                var maxUsage = $('#ai-maxusage').val();
                var respawn = $('#ai-respawn').val();

                if (!theme) {
                    Notification.alert('Error', config.strings.err_theme, 'OK');
                    return;
                }

                var originalText = btn.text();
                btn.prop('disabled', true).text(config.strings.ai_creating);
                btn.attr('aria-busy', 'true');

                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/playerhud/ajax_ai.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        instanceid: config.instanceid,
                        id: config.courseid,
                        theme: theme,
                        xp: xp ? xp : 0,
                        amount: amount,
                        // eslint-disable-next-line camelcase
                        create_drop: createDrop ? 1 : 0,
                        // eslint-disable-next-line camelcase
                        drop_location: locName,
                        // eslint-disable-next-line camelcase
                        drop_max: maxUsage,
                        // eslint-disable-next-line camelcase
                        drop_time: respawn,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(resp) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                        if (resp.success) {
                            var modalBody = $('#phAiModal .modal-body');
                            var modalFooter = $('#phAiModal .modal-footer');
                            var modalTitle = $('#phAiModalLabel');

                            // Recarregar ao fechar
                            $('#phAiModal').one('hidden.bs.modal', function() {
                                window.location.reload();
                            });

                            // Layout Sucesso
                            modalTitle.text(config.strings.success_title);

                            var successHtml = '<div id="ph-success-container" tabindex="-1" ' +
                                'class="text-center py-3 animate__animated animate__fadeIn" style="outline: none;">';

                            successHtml += '<div class="mb-3" style="font-size: 3rem; color: #28a745;" aria-hidden="true">' +
                                '<i class="fa fa-check-circle"></i></div>';

                            var itemsList = Array.isArray(resp.created_items) ? resp.created_items.join(', ') : resp.item_name;
                            var count = Array.isArray(resp.created_items) ? resp.created_items.length : 1;

                            successHtml += '<h2 class="fw-bold text-primary mb-3">' + count + 'x Itens Criados!</h2>';
                            successHtml += '<p class="text-muted">' + itemsList + '</p>';

                            if (resp.warning_msg) {
                                successHtml += '<div class="alert alert-warning small mb-3">' +
                                    '<i class="fa fa-exclamation-triangle"></i> ' + resp.warning_msg + '</div>';
                            } else if (resp.info_msg) {
                                successHtml += '<div class="alert alert-success small mb-3">' +
                                    '<i class="fa fa-check-circle"></i> ' + resp.info_msg + '</div>';
                            }

                            successHtml += '<p class="lead text-muted mb-4">' + config.strings.success + '</p>';

                            // Se criou apenas 1 drop, mostra o código para copiar
                            if (count === 1 && resp.drop_code) {
                                var fullShortcode = '[PLAYERHUD_DROP code=' + resp.drop_code + ']';

                                successHtml += '<div class="card bg-light border-0 p-3 mx-auto" style="max-width: 90%;">';
                                successHtml += '<label class="small text-muted mb-2 fw-bold text-start w-100" ' +
                                    'for="ph-gen-code-input">' + config.strings.copy + ':</label>';

                                successHtml += '<div class="input-group">';
                                successHtml += '<input type="text" class="form-control font-monospace text-center" ' +
                                    'value="' + fullShortcode + '" id="ph-gen-code-input" readonly>';
                                // eslint-disable-next-line max-len
                                successHtml += '<button class="btn btn-primary" type="button" id="ph-btn-copy-code" aria-label="' + config.strings.copy + '">' +
                                    '<i class="fa fa-copy" aria-hidden="true"></i></button>';
                                successHtml += '</div></div>';
                            } else if (count > 1 && createDrop) {
                                successHtml += '<div class="alert alert-info">Os drops foram criados. ' +
                                    'Use o botão "Gerar Código" na lista para pegar cada um.</div>';
                            }

                            successHtml += '</div>';

                            modalBody.html(successHtml);

                            var btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' +
                                config.strings.great + '</button>');

                            btnReload.on('click', function() {
                                window.location.reload();
                            });

                            modalFooter.empty().append(btnReload);

                            if (count === 1 && resp.drop_code) {
                                setTimeout(function() {
                                    $('#ph-btn-copy-code').on('click', function() {
                                        var copyText = document.getElementById("ph-gen-code-input");
                                        copyText.select();
                                        copyText.setSelectionRange(0, 99999);
                                        document.execCommand("copy");

                                        var $btn = $(this);
                                        var originalIcon = '<i class="fa fa-copy" aria-hidden="true"></i>';
                                        $btn.removeClass('btn-primary').addClass('btn-success')
                                            .html('<i class="fa fa-check" aria-hidden="true"></i>');
                                        setTimeout(function() {
                                            $btn.removeClass('btn-success').addClass('btn-primary').html(originalIcon);
                                        }, 2000);
                                    });
                                    $('#ph-success-container').focus();
                                }, 200);
                            }

                        } else {
                            Notification.alert('Error', resp.message, 'OK');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');
                        var errorMsg = error;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.message) {
                                    errorMsg = r.message;
                                }
                            } catch (e) {
                                /* Empty */
                            }
                        }
                        Notification.alert('Ops!', errorMsg, 'OK');
                    }
                });
            });
        }
    };
});
