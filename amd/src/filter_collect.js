/* global bootstrap */
define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Restaura o estado do botão em caso de erro ou necessidade.
     *
     * @param {Object} btn O botão jQuery (trigger).
     * @param {string} html O HTML original do botão.
     * @param {string} mode O modo de exibição ('card', 'text', 'image').
     * @param {string} width A largura original do botão (para modo card).
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
     * Manipula o estado visual do modo Cartão (Card).
     *
     * @param {Object} trigger O elemento jQuery clicado.
     * @param {Object} card O elemento jQuery do cartão pai.
     * @param {boolean} hasTimer Se existe cooldown ativo.
     * @param {boolean} isLimit Se o limite de coleta foi atingido.
     * @param {Object} resp A resposta do servidor contendo dados do cooldown.
     * @param {Object} strings As strings de idioma traduzidas.
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
            // Sucesso Rápido (Feedback verde).
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
     * Atualiza o HUD principal (Widget) e o Bloco Lateral (Sidebar).
     *
     * @param {Object} data Dados gerais do jogo (XP, Nível).
     * @param {Object|null} itemData Dados do item recém coletado (opcional).
     */
    var updateHud = function(data, itemData) {
        // 1. Atualiza o Widget Principal (Topo) se existir na página
        var widget = $('.playerhud-widget-container');
        if (widget.length) {
            widget.find('.progress-bar').css('width', data.progress + '%');
        }

        // 2. Atualiza o Bloco Lateral (Sidebar)
        var sidebar = $('.block_playerhud_sidebar');
        if (sidebar.length) {
            // Atualiza Barra de XP
            sidebar.find('.progress-bar')
                   .css('width', data.progress + '%')
                   .attr('aria-valuenow', data.progress);

            // Atualiza Texto de XP (procura qualquer span que contenha "XP")
            sidebar.find('span').each(function() {
                var txt = $(this).text();
                if (txt.indexOf('XP') > -1) {
                    $(this).text(data.currentxp + ' XP');
                }
                // Atualiza Nível mantendo o prefixo (Level/Nível)
                if (txt.indexOf('Level') > -1 || txt.indexOf('Nível') > -1) {
                    var prefix = txt.split(' ')[0];
                    $(this).text(prefix + ' ' + data.level);
                }
            });

            // 3. Atualiza Itens (Adiciona o novo item à lista)
            if (itemData) {
                var wrapper = sidebar.find('.ph-stash-wrapper');
                var stash = sidebar.find('.ph-sidebar-stash');

                // Se o container estava oculto (primeira coleta), mostra ele
                wrapper.show();

                // Monta o HTML do item (Imagem ou Emoji)
                var contentHtml = '';
                if (itemData.isimage == 1) {
                    contentHtml = '<img src="' + itemData.image + '" alt="' + itemData.name + '">';
                } else {
                    contentHtml = '<span class="ph-mini-emoji" aria-hidden="true">' + itemData.image + '</span>';
                }

                // Cria o elemento com as classes e dados necessários para o Modal
                var newItem = $('<div class="ph-mini-item ph-item-trigger" role="button" tabindex="0"></div>');
                newItem.attr('data-name', itemData.name);
                newItem.attr('data-xp', itemData.xp);
                newItem.attr('data-image', itemData.image);
                newItem.attr('data-isimage', itemData.isimage);
                newItem.attr('data-date', itemData.date);
                newItem.attr('title', itemData.name);

                // Adiciona descrição oculta (para o modal ler)
                newItem.append('<div class="d-none ph-item-description-content">' + itemData.description + '</div>');
                newItem.append(contentHtml);

                // --- CORREÇÃO DE DUPLICAÇÃO ---
                // Verifica se já existe um item com esse nome na lista e remove o antigo
                // Isso garante que ele "salte" para a primeira posição
                stash.children().filter(function() {
                    return $(this).attr('data-name') === itemData.name;
                }).remove();

                // Animação de entrada: oculta, insere no início, faz fade in
                newItem.hide();
                stash.prepend(newItem);
                newItem.fadeIn();

                // Mantém limite visual de 6 itens (remove o último se exceder)
                var items = stash.children('.ph-mini-item');
                if (items.length > 6) {
                    items.last().remove();
                }
            }
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
                // Dados podem estar no próprio elemento ou num pai (card).
                var container = trigger.closest('.playerhud-item-card');
                if (container.length === 0) {
                    container = trigger;
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

            // 2. Coleta AJAX.
            $('body').on('click', '.ph-action-collect', function(e) {
                e.preventDefault();
                var trigger = $(this);
                var mode = trigger.attr('data-mode') || 'card';

                if (trigger.hasClass('disabled') || trigger.attr('disabled')) {
                    return;
                }

                // Feedback visual de "Carregando"
                var originalHtml = trigger.html();
                var originalWidth = trigger.css('width');

                if (mode === 'card') {
                    trigger.css('width', trigger.outerWidth() + 'px');
                    trigger.html('<i class="fa fa-spinner fa-spin"></i>').addClass('disabled');
                } else {
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
                            // Atualiza Badge do Card (se existir)
                            var card = trigger.closest('.playerhud-item-card');
                            if (card.length) {
                                var badge = card.find('.ph-badge-count');
                                var currentCount = parseInt(badge.text().replace('x', ''), 10) || 0;
                                badge.text('x' + (currentCount + 1)).show();
                            }

                            // Variáveis de estado
                            var hasTimer = (resp.cooldown_deadline && resp.cooldown_deadline > 0);
                            var isLimit = resp.limit_reached;

                            // Atualiza o estado do botão/link no texto
                            if (mode === 'text' || mode === 'image') {
                                handleTextImageMode(trigger, mode, hasTimer, isLimit, resp);
                            } else {
                                handleCardMode(trigger, card, hasTimer, isLimit, resp, strings, originalHtml);
                            }

                            // ATUALIZAÇÃO DA SIDEBAR (Barra, Nível e Lista de Itens)
                            if (resp.game_data) {
                                updateHud(resp.game_data, resp.item_data);
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
