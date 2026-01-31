define(['jquery', 'core/notification'], function($, Notification) {

    return {
        init: function(strings) {

            $('body').on('click', '.ph-action-collect', function(e) {
                e.preventDefault();

                var trigger = $(this);

                // Ignora se já estiver desabilitado
                if (trigger.hasClass('disabled') || trigger.attr('disabled')) {
                    return;
                }

                var originalHtml = trigger.html();
                var originalWidth = trigger.outerWidth();

                trigger.css('width', originalWidth + 'px');
                trigger.html('<i class="fa fa-spinner fa-spin"></i>');
                trigger.addClass('disabled').attr('aria-busy', 'true');

                var url = trigger.attr('href');
                var ajaxUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'ajax=1';

                $.ajax({
                    url: ajaxUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {

                            // Remove classes antigas
                            trigger.removeClass('btn-primary ph-action-collect')
                                .removeAttr('href')
                                .removeAttr('aria-busy');

                            // --- LÓGICA DO CONTADOR ---
                            if (resp.cooldown_deadline && resp.cooldown_deadline > 0) {
                                // 1. MODO TEMPORIZADOR
                                // eslint-disable-next-line max-len
                                var timerHtml = '⏳ <span class="ph-timer" data-deadline="' + resp.cooldown_deadline + '">...</span>';

                                // eslint-disable-next-line max-len
                                var timerBtn = $('<div class="btn btn-light btn-sm w-100 text-muted" tabindex="0" role="timer" aria-live="off">' + timerHtml + '</div>');

                                trigger.replaceWith(timerBtn);

                                // Foca no novo elemento do timer
                                timerBtn.focus();

                            } else {
                                // 2. MODO COLETADO
                                trigger.addClass('btn-success disabled')
                                    .css('cursor', 'default')
                                    .attr('aria-disabled', 'true')
                                    .html('<i class="fa fa-check"></i> ' + strings.collected);

                                trigger.attr('tabindex', '0');
                                trigger.focus();
                            }

                            // --- ATUALIZAÇÃO DO HUD (XP/Level) ---
                            if (resp.game_data) {
                                var widget = $('.playerhud-widget-container');
                                if (widget.length) {
                                    var data = resp.game_data;
                                    // eslint-disable-next-line max-len
                                    widget.find('.progress-bar').css('width', data.progress + '%').attr('aria-valuenow', data.progress);

                                    widget.find('.badge').each(function() {
                                        var text = $(this).text();
                                        if (text.indexOf('Level') !== -1 || text.indexOf('Nível') !== -1) {
                                            $(this).text('Level ' + data.level);
                                            $(this).addClass('animate__animated animate__pulse');
                                        }
                                    });

                                    widget.find('span.text-muted').each(function() {
                                        var el = $(this);
                                        if (el.text().indexOf('XP') !== -1) {
                                            el.text(data.currentxp + ' XP');
                                            el.addClass('text-success fw-bold');
                                            setTimeout(function() {
                                                el.removeClass('text-success fw-bold');
                                            }, 2000);
                                        }
                                    });
                                }
                            }

                            // Removida a variável 'card' que não estava sendo usada para evitar erro de lint.

                        } else {
                            restaurarBotao();
                            Notification.alert('Ops', resp.message, 'OK');
                        }
                    },
                    error: function() {
                        restaurarBotao();
                        Notification.alert('Erro', strings.error, 'OK');
                    }
                });

                /**
                 *
                 */
                function restaurarBotao() {
                    trigger.html(originalHtml);
                    trigger.removeClass('disabled').removeAttr('aria-busy');
                    trigger.css('width', '');
                }
            });
        }
    };
});
