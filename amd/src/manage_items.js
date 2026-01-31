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

                var originalText = btn.text();
                btn.prop('disabled', true).text('...');
                btn.attr('aria-busy', 'true'); // Acessibilidade: Indica processamento

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

                            // 1. Atualiza o Título do Modal (Ex: "Espada Mágica")
                            // Usamos o nome do item gerado para dar feedback claro
                            modalTitle.text(resp.item_name);

                            // 2. Constrói HTML de Sucesso Acessível e SEM texto chumbado
                            // tabindex="-1" permite focar via JS
                            // eslint-disable-next-line max-len
                            var successHtml = '<div id="ph-success-container" tabindex="-1" class="text-center py-3 animate__animated animate__fadeIn" style="outline: none;">';

                            // Ícone decorativo (aria-hidden)
                            // eslint-disable-next-line max-len
                            successHtml += '<div class="mb-3" style="font-size: 3rem; color: #28a745;" aria-hidden="true"><i class="fa fa-check-circle"></i></div>';

                            // Mensagem de sucesso vinda do PHP ('Item criado com sucesso!')
                            successHtml += '<h3 class="fw-bold h4">' + config.strings.success + '</h3>';

                            if (resp.drop_code) {
                                successHtml += '<div class="mt-3 p-3 bg-light border rounded">';
                                successHtml += '<label class="small text-muted mb-1">' + config.strings.copy + '</label>';
                                successHtml += '<h4 class="text-primary font-monospace select-all">' + resp.drop_code + '</h4>';
                                successHtml += '</div>';
                            }
                            successHtml += '</div>';

                            // 3. Substitui o conteúdo
                            modalBody.html(successHtml);

                            // 4. Cria botão de Reload usando a string 'great' ('Legal!')
                            // E removemos o texto hardcoded "OK, Atualizar Página"
                            // eslint-disable-next-line max-len
                            var btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' + config.strings.great + '</button>');
                            btnReload.on('click', function() {
                                window.location.reload();
                            });

                            modalFooter.empty().append(btnReload);

                            // 5. ACESSIBILIDADE: Gerenciamento de Foco
                            // Move o foco para o container de sucesso para o leitor de tela narrar o resultado
                            setTimeout(function() {
                                $('#ph-success-container').focus();
                            }, 100);

                        } else {
                            Notification.alert('Error', resp.message, 'OK');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text(originalText).removeAttr('aria-busy');
                        Notification.exception(error);
                    }
                });
            });
        }
    };
});
