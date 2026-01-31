define(['jquery', 'core/notification'], function($, Notification) {

    return {
        init: function(config) {

            $('#phAiModal').appendTo('body');

            // 1. Delete Confirmation
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

            // 2. AI Logic
            $('#ph-btn-conjure').click(function(e) {
                e.preventDefault();
                var btn = $(this);

                if (btn.prop('disabled')) {
                    return;
                }

                var theme = $('#ai-theme').val();
                var xp = $('#ai-xp').val();
                var createDrop = $('#ai-drop').is(':checked');

                if (!theme) {
                    Notification.alert('Error', config.strings.err_theme, 'OK');
                    return;
                }

                // --- MUDANÇA 1: Texto de Carregamento Personalizado ---
                var originalText = btn.text();
                // Usa a string 'ai_creating' ("Conjurando item...") passada pelo PHP
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
                        sesskey: M.cfg.sesskey
                    },
                    success: function(resp) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                        if (resp.success) {
                            var modalBody = $('#phAiModal .modal-body');
                            var modalFooter = $('#phAiModal .modal-footer');
                            var modalTitle = $('#phAiModalLabel');

                            // --- MUDANÇA 2: Layout do Modal de Sucesso ---

                            // Título genérico "Sucesso!" no topo
                            modalTitle.text(config.strings.success_title);

                            // Conteúdo centralizado
                            // eslint-disable-next-line max-len
                            var successHtml = '<div id="ph-success-container" tabindex="-1" class="text-center py-3 animate__animated animate__fadeIn" style="outline: none;">';

                            // Ícone
                            // eslint-disable-next-line max-len
                            successHtml += '<div class="mb-3" style="font-size: 3rem; color: #28a745;" aria-hidden="true"><i class="fa fa-check-circle"></i></div>';

                            // Nome do Item (DESTAQUE NO CENTRO)
                            successHtml += '<h2 class="fw-bold text-primary mb-3">' + resp.item_name + '</h2>';

                            // Mensagem "Item criado com sucesso!" logo abaixo
                            successHtml += '<p class="lead text-muted">' + config.strings.success + '</p>';

                            if (resp.drop_code) {
                                successHtml += '<div class="mt-4 p-3 bg-light border rounded mx-auto" style="max-width: 80%;">';
                                successHtml += '<label class="small text-muted mb-1 d-block">' + config.strings.copy + '</label>';
                                successHtml += '<h4 class="font-monospace select-all m-0">' + resp.drop_code + '</h4>';
                                successHtml += '</div>';
                            }
                            successHtml += '</div>';

                            modalBody.html(successHtml);

                            // eslint-disable-next-line max-len
                            var btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' + config.strings.great + '</button>');
                            btnReload.on('click', function() {
                                window.location.reload();
                            });

                            modalFooter.empty().append(btnReload);

                            setTimeout(function() {
                                $('#ph-success-container').focus();
                            }, 100);

                        } else {
                            // Caso o PHP retorne sucesso=false mas status 200 (raro na sua config atual)
                            Notification.alert('Error', resp.message, 'OK');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');

                        // --- MUDANÇA 3: Correção do Erro "Undefined" ---

                        // O PHP envia um JSON com 'message' mesmo no erro 400.
                        // Precisamos ler o responseJSON em vez de passar o objeto de erro cru.
                        var errorMsg = error; // Fallback

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            // Pega a mensagem amigável enviada pelo PHP (Ex: "Nenhuma chave configurada...")
                            errorMsg = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                             // Tenta fazer parse manual se responseJSON falhar
                            try {
                                var r = JSON.parse(xhr.responseText);
                                if (r.message) {
                                    errorMsg = r.message;
                                }
                            } catch (e) {
                                // Se não for JSON, mantém o erro padrão
                            }
                        }

                        // Agora usamos Alert normal em vez de Exception, pois temos uma mensagem de texto limpa
                        Notification.alert('Ops!', errorMsg, 'OK');
                    }
                });
            });
        }
    };
});
