/* global bootstrap */
define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Manipula o estado visual do modo Cartão (Card).
     *
     * @param {Object} trigger O elemento jQuery clicado.
     * @param {Object} card O elemento jQuery do cartão pai.
     * @param {boolean} hasTimer Se existe cooldown ativo.
     * @param {boolean} isLimit Se o limite de coleta foi atingido.
     * @param {Object} resp A resposta do servidor.
     * @param {Object} strings As strings de idioma.
     * @param {string} originalHtml O HTML original do botão.
     */
    var handleCardMode = function(trigger, card, hasTimer, isLimit, resp, strings, originalHtml) {
        if (hasTimer) {
            trigger.removeClass('btn-primary ph-action-collect').removeAttr('href');
            var tHtml = '⏳ <span class="ph-timer" data-deadline="' +
                resp.cooldown_deadline + '">...</span>';
            var tBtn = $('<div class="btn btn-light btn-sm w-100 text-muted" tabindex="0">' +
                tHtml + '</div>');
            trigger.replaceWith(tBtn);
            tBtn.focus();
        } else if (isLimit) {
            trigger.removeClass('btn-primary ph-action-collect')
                .addClass('btn-success disabled')
                .css('cursor', 'default').removeAttr('href')
                .html('<i class="fa fa-check"></i> ' + strings.collected);
            // Foca na imagem (trigger de detalhes) pois o botão morreu.
            card.find('.ph-item-details-trigger').focus();
        } else {
            // Sucesso Rápido.
            trigger.removeClass('btn-primary disabled').addClass('btn-success')
                .html('<i class="fa fa-check"></i> ' + strings.collected).css('width', '');
            setTimeout(function() {
                trigger.removeClass('btn-success').addClass('btn-primary')
                    .html(originalHtml);
                trigger.focus();
            }, 1500);
        }
    };

    /**
     * Manipula o estado visual dos modos Texto e Imagem.
     *
     * @param {Object} trigger O elemento jQuery clicado.
     * @param {string} mode O modo de exibição ('text' ou 'image').
     * @param {boolean} hasTimer Se existe cooldown ativo.
     * @param {boolean} isLimit Se o limite de coleta foi atingido.
     * @param {Object} resp A resposta do servidor.
     */
    var handleTextImageMode = function(trigger, mode, hasTimer, isLimit, resp) {
        // Se entrou em cooldown ou limite, transformamos em "Visualizador de Detalhes".
        if (hasTimer || isLimit) {
            // Remove href e classes de ação.
            trigger.removeAttr('href').removeClass('ph-action-collect');
            trigger.addClass('ph-item-details-trigger'); // Agora abre modal!
            trigger.css('opacity', '1').css('pointer-events', 'auto').css('cursor', 'pointer');

            if (mode === 'text') {
                if (isLimit) {
                    trigger.addClass('text-success')
                        .html('<i class="fa fa-check"></i> ' + trigger.text());
                } else {
                    // Cooldown Texto: Nome + Timer.
                    trigger.addClass('text-muted')
                        .html('⏳ ' + trigger.text() +
                        ' <small class="ph-timer" data-deadline="' +
                        resp.cooldown_deadline + '">...</small>');
                }
            } else {
                // Image Mode.
                if (isLimit) {
                    // Adiciona badge de check.
                    trigger.css('position', 'relative');
                    trigger.append('<span class="badge bg-success rounded-circle" ' +
                        'style="position:absolute; bottom:-5px; right:-5px; font-size:0.6rem;">' +
                        '<i class="fa fa-check"></i></span>');
                    trigger.find('img, span').css('filter', 'grayscale(100%)').css('opacity', '0.6');
                } else {
                    // Adiciona timer embaixo.
                    trigger.css('position', 'relative');
                    trigger.find('img, span').css('opacity', '0.6');
                    // Quebrando a linha longa para passar no max-len (132 chars)
                    var badgeStyle = 'position:absolute; bottom:-10px; left:50%; ' +
                                     'transform:translateX(-50%); font-size:0.6rem;';
                    trigger.append('<div class="ph-timer badge bg-light text-dark border shadow-sm" ' +
                        'style="' + badgeStyle + '" ' +
                        'data-deadline="' + resp.cooldown_deadline + '">...</div>');
                }
            }
        } else {
            // Coleta rápida (sem cooldown): Feedback visual breve.
            trigger.css('opacity', '1').css('pointer-events', 'auto');
            // Piscar verde.
            var oldColor = trigger.css('color');
            trigger.css('color', '#28a745');
            setTimeout(function() {
                trigger.css('color', oldColor);
            }, 1000);
        }
    };

    /**
     * Restores the button state in case of error.
     *
     * @param {Object} btn jQuery object of the button.
     * @param {string} html Original HTML content.
     * @param {string} mode The display mode.
     * @param {string} width Original width.
     */
    var restaurar = function(btn, html, mode, width) {
        btn.html(html).removeClass('disabled');
        if (mode === 'card') {
            btn.css('width', width);
        } else {
            btn.css('opacity', '1').css('pointer-events', 'auto');
        }
    };

    /**
     * Updates the HUD widget progress bar.
     *
     * @param {Object} data Game data.
     */
    var updateHud = function(data) {
        var widget = $('.playerhud-widget-container');
        if (widget.length) {
            widget.find('.progress-bar').css('width', data.progress + '%');
        }
    };

    return {
        init: function(strings) {

            var $modal = $('#phItemModalFilter');
            if ($modal.length) {
                $modal.appendTo('body');
            }

            // 1. Modal de Detalhes (Unificado para Card, Texto e Imagem).
            $('body').on('click keydown', '.ph-item-details-trigger', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();

                var trigger = $(this);
                // Dados podem estar no próprio elemento (texto/imagem) ou num pai (card).
                var container = trigger.closest('.playerhud-item-card');
                if (container.length === 0) {
                    container = trigger; // Caso Texto/Imagem.
                }

                var name = container.attr('data-name');
                var descB64 = container.attr('data-desc-b64');
                var img = container.attr('data-image');
                var isImg = container.attr('data-isimage');

                $('#phModalTitleF, #phModalNameF').text(name);

                var descHtml = '';
                try {
                    descHtml = decodeURIComponent(escape(window.atob(descB64)));
                } catch (err) {
                    descHtml = '...';
                }
                $('#phModalDescF').html(descHtml);

                var imgCont = $('#phModalImageContainerF');
                imgCont.empty();
                if (isImg == '1') {
                    imgCont.append($('<img>', {src: img, style: 'max-width:80px;'}));
                } else {
                    imgCont.append($('<span>', {style: 'font-size:50px;', text: img}));
                }

                var modalEl = document.getElementById('phItemModalFilter');
                if (modalEl) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        bootstrap.Modal.getOrCreateInstance(modalEl).show();
                    } else {
                        $(modalEl).modal('show');
                    }
                }
            });

            // 2. Coleta.
            $('body').on('click', '.ph-action-collect', function(e) {
                e.preventDefault();
                var trigger = $(this);
                var mode = trigger.attr('data-mode') || 'card';

                if (trigger.hasClass('disabled') || trigger.attr('disabled')) {
                    return;
                }

                // Feedback de "Carregando" varia por modo.
                var originalHtml = trigger.html();
                var originalWidth = trigger.css('width');

                if (mode === 'card') {
                    trigger.css('width', trigger.outerWidth() + 'px');
                    trigger.html('<i class="fa fa-spinner fa-spin"></i>').addClass('disabled');
                } else {
                    // Texto/Imagem: Efeito de opacidade para não pular layout.
                    trigger.css('opacity', '0.5').css('pointer-events', 'none');
                }

                var url = trigger.attr('href');
                var ajaxUrl = url + (url.indexOf('?') === -1 ? '?' : '&') + 'ajax=1';

                $.ajax({
                    url: ajaxUrl,
                    method: 'GET',
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            // Atualiza Badge do Card (se existir).
                            var card = trigger.closest('.playerhud-item-card');
                            if (card.length) {
                                var badge = card.find('.ph-badge-count');
                                var currentCount = parseInt(badge.text().replace('x', ''), 10) || 0;
                                badge.text('x' + (currentCount + 1)).show();
                            }

                            // Variáveis de estado.
                            var hasTimer = (resp.cooldown_deadline && resp.cooldown_deadline > 0);
                            var isLimit = resp.limit_reached;

                            // Delegação para funções auxiliares para evitar aninhamento profundo (max-depth).
                            if (mode === 'text' || mode === 'image') {
                                handleTextImageMode(trigger, mode, hasTimer, isLimit, resp);
                            } else {
                                handleCardMode(trigger, card, hasTimer, isLimit, resp, strings, originalHtml);
                            }

                            if (resp.game_data) {
                                updateHud(resp.game_data);
                            }

                        } else {
                            restaurar(trigger, originalHtml, mode, originalWidth);
                            Notification.alert('Ops', resp.message, 'OK');
                        }
                    },
                    error: function() {
                        restaurar(trigger, originalHtml, mode, originalWidth);
                        Notification.alert('Erro', strings.error, 'OK');
                    }
                });
            });
        }
    };
});
