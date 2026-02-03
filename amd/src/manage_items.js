define(['jquery', 'core/notification'], function($, Notification) {

    return {
        init: function(config) {

            $('#phAiModal').appendTo('body');

            // 1. Toggle Drop Options
            $('#ai-drop').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ai-drop-options').slideDown();
                } else {
                    $('#ai-drop-options').slideUp();
                }
            });

            // 2. Delete Confirmation
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

            $('#ph-ai-form').on('submit', function(e) {
                e.preventDefault();
                $('#ph-btn-conjure').click();
            });

            // 3. AI Logic
            $('#ph-btn-conjure').click(function(e) {
                e.preventDefault();
                var btn = $(this);

                if (btn.prop('disabled')) {
                    return;
                }

                var theme = $('#ai-theme').val();
                var xp = $('#ai-xp').val();
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
                        'create_drop': createDrop ? 1 : 0,
                        'drop_location': locName,
                        'drop_max': maxUsage,
                        'drop_time': respawn,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(resp) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                        if (resp.success) {
                            var modalBody = $('#phAiModal .modal-body');
                            var modalFooter = $('#phAiModal .modal-footer');
                            var modalTitle = $('#phAiModalLabel');

                            // --- LAYOUT DE SUCESSO MELHORADO ---
                            modalTitle.text(config.strings.success_title);

                            // eslint-disable-next-line max-len
                            var successHtml = '<div id="ph-success-container" tabindex="-1" class="text-center py-3 animate__animated animate__fadeIn" style="outline: none;">';

                            // Ícone de Sucesso
                            // eslint-disable-next-line max-len
                            successHtml += '<div class="mb-3" style="font-size: 3rem; color: #28a745;" aria-hidden="true"><i class="fa fa-check-circle"></i></div>';

                            // Nome do Item
                            successHtml += '<h2 class="fw-bold text-primary mb-3">' + resp.item_name + '</h2>';
                            successHtml += '<p class="lead text-muted mb-4">' + config.strings.success + '</p>';

                            // Se houve drop criado, exibe o shortcode completo com botão de copiar
                            if (resp.drop_code) {
                                // Monta o código completo
                                var fullShortcode = '[PLAYERHUD_DROP code=' + resp.drop_code + ']';

                                successHtml += '<div class="card bg-light border-0 p-3 mx-auto" style="max-width: 90%;">';
                                // eslint-disable-next-line max-len
                                successHtml += '<label class="small text-muted mb-2 fw-bold text-start w-100" for="ph-gen-code-input">' + config.strings.copy + ':</label>';

                                // Input Group do Bootstrap para juntar Input + Botão
                                successHtml += '<div class="input-group">';
                                // eslint-disable-next-line max-len
                                successHtml += '<input type="text" class="form-control font-monospace text-center" value="' + fullShortcode + '" id="ph-gen-code-input" readonly>';
                                // eslint-disable-next-line max-len
                                successHtml += '<button class="btn btn-primary" type="button" id="ph-btn-copy-code"><i class="fa fa-copy"></i></button>';
                                successHtml += '</div>';

                                successHtml += '</div>';
                            }
                            successHtml += '</div>';

                            modalBody.html(successHtml);

                            // Botão "Legal" para fechar/recarregar
                            // eslint-disable-next-line max-len
                            var btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' + config.strings.great + '</button>');
                            btnReload.on('click', function() {
                                window.location.reload();
                            });

                            modalFooter.empty().append(btnReload);

                            // --- LÓGICA DO BOTÃO COPIAR ---
                            if (resp.drop_code) {
                                setTimeout(function() {
                                    $('#ph-btn-copy-code').on('click', function() {
                                        var copyText = document.getElementById("ph-gen-code-input");

                                        // Seleciona o texto
                                        copyText.select();
                                        copyText.setSelectionRange(0, 99999); // Mobile

                                        // Copia para a área de transferência
                                        document.execCommand("copy");

                                        // Feedback Visual no Botão
                                        var $btn = $(this);
                                        var originalIcon = '<i class="fa fa-copy"></i>';

                                        $btn.removeClass('btn-primary').addClass('btn-success').html('<i class="fa fa-check"></i>');

                                        // Volta ao normal após 2 segundos
                                        setTimeout(function() {
                                            $btn.removeClass('btn-success').addClass('btn-primary').html(originalIcon);
                                        }, 2000);
                                    });

                                    // Foca no container para acessibilidade
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
