// eslint-disable-next-line no-redeclare
/* global M */
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

            $('#phAiModal').appendTo('body');

            // 1. Toggle Drop Options.
            $('#ai-drop').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#ai-drop-options').slideDown();
                } else {
                    $('#ai-drop-options').slideUp();
                }
            });

            // 2. Delete Confirmation.
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

            // 3. AI Logic.
            $('#ph-btn-conjure').click(function(e) {
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

                            // --- CORREÇÃO AQUI ---
                            // Adiciona um gatilho único: Se o modal fechar (X, ESC, ou clique fora), recarrega a página.
                            // Usamos .one() para garantir que execute apenas uma vez para este sucesso específico.
                            $('#phAiModal').one('hidden.bs.modal', function() {
                                window.location.reload();
                            });

                            // --- LAYOUT DE SUCESSO ---
                            modalTitle.text(config.strings.success_title);

                            var successHtml = '<div id="ph-success-container" tabindex="-1" ' +
                                'class="text-center py-3 animate__animated animate__fadeIn" style="outline: none;">';

                            successHtml += '<div class="mb-3" style="font-size: 3rem; color: #28a745;" aria-hidden="true">' +
                                '<i class="fa fa-check-circle"></i></div>';

                            // Tratamento para múltiplos itens.
                            var itemsList = Array.isArray(resp.created_items) ? resp.created_items.join(', ') : resp.item_name;
                            var count = Array.isArray(resp.created_items) ? resp.created_items.length : 1;

                            successHtml += '<h2 class="fw-bold text-primary mb-3">' + count + 'x Itens Criados!</h2>';
                            successHtml += '<p class="text-muted">' + itemsList + '</p>';

                            // Avisos de Balanceamento.
                            if (resp.warning_msg) {
                                successHtml += '<div class="alert alert-warning small mb-3">' +
                                    '<i class="fa fa-exclamation-triangle"></i> ' + resp.warning_msg + '</div>';
                            } else if (resp.info_msg) {
                                successHtml += '<div class="alert alert-success small mb-3">' +
                                    '<i class="fa fa-check-circle"></i> ' + resp.info_msg + '</div>';
                            }

                            successHtml += '<p class="lead text-muted mb-4">' + config.strings.success + '</p>';

                            // Bloco do Shortcode (Drop) - Apenas se for 1 item.
                            if (count === 1 && resp.drop_code) {
                                var fullShortcode = '[PLAYERHUD_DROP code=' + resp.drop_code + ']';

                                successHtml += '<div class="card bg-light border-0 p-3 mx-auto" style="max-width: 90%;">';
                                successHtml += '<label class="small text-muted mb-2 fw-bold text-start w-100" ' +
                                    'for="ph-gen-code-input">' + config.strings.copy + ':</label>';

                                successHtml += '<div class="input-group">';
                                successHtml += '<input type="text" class="form-control font-monospace text-center" ' +
                                    'value="' + fullShortcode + '" id="ph-gen-code-input" readonly>';
                                successHtml += '<button class="btn btn-primary" type="button" id="ph-btn-copy-code">' +
                                    '<i class="fa fa-copy"></i></button>';
                                successHtml += '</div></div>';
                            } else if (count > 1 && createDrop) {
                                successHtml += '<div class="alert alert-info">Os drops foram criados. ' +
                                    'Use o botão "Gerar Código" na lista para pegar cada um.</div>';
                            }

                            successHtml += '</div>';

                            modalBody.html(successHtml);

                            var btnReload = $('<button class="btn btn-success w-100 py-2 fw-bold">' +
                                config.strings.great + '</button>');

                            // Mantemos o clique no botão explicitamente recarregando também
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
                                        var originalIcon = '<i class="fa fa-copy"></i>';
                                        $btn.removeClass('btn-primary').addClass('btn-success')
                                            .html('<i class="fa fa-check"></i>');
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