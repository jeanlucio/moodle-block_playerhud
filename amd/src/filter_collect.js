/* global bootstrap */
define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Helper to retrieve modal elements, prioritizing the Block's modal if present.
     * This ensures strict visual standardization and centralized maintenance.
     *
     * @return {Object} An object containing jQuery references to modal elements.
     */
    var getModalElements = function() {
        // 1. Tenta encontrar o Modal "Original" do Bloco (Prioridade).
        // IDs definidos em: blocks/playerhud/templates/modal_item.mustache.
        var root = $('#phItemModalView');
        var suffix = 'View';

        // 2. Se não existir (ex: página sem blocos), usa o Modal do Filtro (Fallback).
        // IDs definidos em: filter/playerhud/templates/modals.mustache.
        if (!root.length) {
            root = $('#phItemModalFilter');
            suffix = 'F';
        }

        return {
            root: root,
            // Seletores dinâmicos: adiciona o sufixo 'View' ou 'F' conforme o modal encontrado.
            title: $('#phModalTitle' + suffix),
            name: $('#phModalName' + suffix),
            desc: $('#phModalDesc' + suffix),
            imgContainer: $('#phModalImageContainer' + suffix),
            xp: $('#phModalXP' + suffix),
            countBadge: $('#phModalCountBadge' + suffix),
            date: $('#phModalDate' + suffix),
            dateContainer: $('#phModalDateContainer' + suffix)
        };
    };

    /**
     * Restores the button state in case of error or completion.
     *
     * @param {Object} btn The jQuery button element.
     * @param {String} html The original HTML content of the button.
     * @param {String} mode The display mode ('card', 'text', 'image').
     * @param {String} width The original width of the button (for card mode).
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
     * Handles the visual state for Card Mode.
     *
     * @param {Object} trigger The clicked jQuery element.
     * @param {Object} card The parent card jQuery element.
     * @param {Boolean} hasTimer Whether the item is in cooldown.
     * @param {Boolean} isLimit Whether the collection limit is reached.
     * @param {Object} resp The server response object.
     * @param {Object} strings The language strings object.
     * @param {String} originalHtml The original HTML of the trigger.
     */
    var handleCardMode = function(trigger, card, hasTimer, isLimit, resp, strings, originalHtml) {
        if (hasTimer) {
            trigger.removeClass('btn-primary ph-action-collect').removeAttr('href');
            var tHtml = '⏳ <span class="ph-timer" data-deadline="' +
                resp.cooldown_deadline + '">...</span>';
            var tBtn = $('<div class="btn btn-light btn-sm w-100 ph-text-dimmed" tabindex="0">' +
                tHtml + '</div>');
            trigger.replaceWith(tBtn);
            tBtn.focus();
        } else if (isLimit) {
            trigger.removeClass('btn-primary ph-action-collect')
                .addClass('btn-light text-success disabled border-success')
                .css('cursor', 'default').removeAttr('href')
                .html('<i class="fa fa-check" aria-hidden="true"></i> ' + strings.collected);
            card.find('.ph-item-details-trigger').focus();
        } else {
            trigger.removeClass('btn-primary disabled').addClass('btn-success')
                .html('<i class="fa fa-check" aria-hidden="true"></i> ' + strings.collected).css('width', '');
            setTimeout(function() {
                trigger.removeClass('btn-success').addClass('btn-primary')
                    .html(originalHtml);
                trigger.focus();
            }, 1500);
        }
    };

    /**
     * Handles the visual state for Text and Image modes.
     *
     * @param {Object} trigger The clicked jQuery element.
     * @param {String} mode The display mode ('text' or 'image').
     * @param {Boolean} hasTimer Whether the item is in cooldown.
     * @param {Boolean} isLimit Whether the collection limit is reached.
     * @param {Object} resp The server response object.
     */
    var handleTextImageMode = function(trigger, mode, hasTimer, isLimit, resp) {
        if (hasTimer || isLimit) {
            if (mode === 'text') {
                trigger.removeAttr('href').removeClass('ph-action-collect');
                trigger.addClass('ph-item-details-trigger');
                trigger.css('opacity', '1').css('pointer-events', 'auto').css('cursor', 'pointer');

                if (isLimit) {
                    trigger.addClass('text-success fw-bold')
                        .html('<i class="fa fa-check" aria-hidden="true"></i> ' + trigger.text());
                } else {
                    trigger.addClass('ph-text-dimmed')
                        .html('<span aria-hidden="true">⏳</span> ' + trigger.text() +
                        ' <small class="ph-timer" data-deadline="' +
                        resp.cooldown_deadline + '">...</small>');
                }
            } else {
                var container = trigger.closest('.ph-drop-image-container');
                var imgWrapper = container.find('> div').first();
                trigger.remove();

                // Transforma container em gatilho.
                container.addClass('ph-item-details-trigger')
                         .attr('tabindex', '0')
                         .attr('role', 'button')
                         .css('cursor', 'pointer');

                if (isLimit) {
                    imgWrapper.addClass('ph-state-grayscale');
                    container.append('<span class="badge bg-success rounded-circle ph-badge-bottom-right">' +
                        '<i class="fa fa-check" aria-hidden="true"></i></span>');
                } else {
                    imgWrapper.addClass('ph-state-dimmed');
                    container.append('<div class="ph-timer badge bg-light text-dark border shadow-sm ph-badge-bottom-center" ' +
                        'data-deadline="' + resp.cooldown_deadline + '" data-no-label="1">...</div>');
                }
            }
        } else {
            if (mode === 'text') {
                trigger.css('opacity', '1').css('pointer-events', 'auto');
                var oldColor = trigger.css('color');
                trigger.css('color', '#198754');
                setTimeout(function() {
                    trigger.css('color', oldColor);
                }, 1000);
            } else {
                trigger.css('opacity', '1').css('pointer-events', 'auto');
            }
        }
    };

    /**
     * Updates the Stash (Recent Items) UI.
     * Separated to reduce complexity.
     *
     * @param {Object} itemData Data of the collected item.
     */
    var updateStash = function(itemData) {
        var stashes = $('.ph-sidebar-stash, .ph-widget-stash');

        stashes.each(function() {
            var stash = $(this);
            var wrapper = stash.closest('.ph-stash-wrapper');
            if (wrapper.length) {
                wrapper.show();
            }

            stash.find('.text-muted').remove();
            stash.find('span.small.text-muted').remove();

            var contentHtml = '';
            if (String(itemData.isimage) === '1') {
                contentHtml = '<img src="' + itemData.image + '" alt="" ' +
                    'style="width: 100%; height: 100%; object-fit: contain;">';
            } else {
                contentHtml = '<span class="ph-mini-emoji" aria-hidden="true" ' +
                    'style="font-size:1.2rem; line-height: 1;">' + itemData.image + '</span>';
            }

            var classes = 'ph-mini-item ph-item-trigger border bg-white rounded ' +
                'd-flex align-items-center justify-content-center overflow-hidden position-relative shadow-sm';

            var newItem = $('<div class="' + classes + '" role="button" tabindex="0"></div>');

            newItem.css({
                'width': '34px',
                'height': '34px',
                'min-width': '34px',
                'margin-right': '2px',
                'margin-bottom': '2px'
            });

            // Atributos de dados
            newItem.attr('data-name', itemData.name);
            newItem.attr('data-xp', itemData.xp);
            newItem.attr('data-image', itemData.image);
            newItem.attr('data-isimage', itemData.isimage);
            newItem.attr('data-date', itemData.date); // Texto fallback

            // [NOVO] Adiciona o timestamp para formatação correta
            if (itemData.timestamp) {
                newItem.attr('data-timestamp', itemData.timestamp);
            }

            newItem.attr('title', itemData.name);
            newItem.attr('aria-label', itemData.name);

            newItem.append('<div class="d-none ph-item-description-content">' + (itemData.description || '') + '</div>');
            newItem.append(contentHtml);

            stash.children().filter(function() {
                return $(this).attr('data-name') === itemData.name;
            }).remove();

            newItem.hide();
            stash.prepend(newItem);
            newItem.fadeIn();

            var limit = stash.hasClass('ph-widget-stash') ? 14 : 6;
            var items = stash.children('.ph-mini-item');
            if (items.length > limit) {
                items.last().remove();
            }
        });
    };

    /**
     * Updates the main Widget and Sidebar HUDs.
     *
     * @param {Object} data General game data (XP, Level, Progress).
     * @param {Object|null} itemData Data of the collected item (optional).
     */
    var updateHud = function(data, itemData) {
        var containers = $('.playerhud-widget-container, .block_playerhud_sidebar');

        containers.each(function() {
            var container = $(this);

            if (container.hasClass('playerhud-widget-container')) {
                container.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);
            }

            var sidebarGrid = container.find('.ph-sidebar-grid');
            if (sidebarGrid.length) {
                sidebarGrid.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);
            }

            var progressBar = container.find('.progress-bar');
            progressBar.css('width', data.progress + '%').attr('aria-valuenow', data.progress);
            progressBar.removeClass(function(index, className) {
                return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
            }).addClass(data.level_class);

            var levelBadge = container.find('.badge').filter(function() {
                return $(this).text().match(/(Level|Nível)/) || $(this).attr('class').match(/ph-lvl-tier-/);
            });

            if (levelBadge.length) {
                levelBadge.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);

                var label = (levelBadge.text().indexOf('Nível') > -1) ? 'Nível' : 'Level';
                var lvlString = data.level;
                if (typeof data.max_levels !== 'undefined' && data.max_levels > 0) {
                    lvlString += '/' + data.max_levels;
                }
                levelBadge.text(label + ' ' + lvlString);
            }

            container.find('span, div, strong').each(function() {
                var el = $(this);
                if (el.children().length === 0 && el.text().indexOf('XP') > -1) {
                    var xpString = data.currentxp;
                    if (typeof data.xp_target !== 'undefined' && data.xp_target > 0) {
                        xpString += ' / ' + data.xp_target;
                    }
                    xpString += ' XP';
                    if (data.is_win) {
                        xpString += ' 🏆';
                    }
                    el.text(xpString);
                }
            });
        });

        if (itemData) {
            updateStash(itemData);
        }
    };

    return {
        /**
         * Initialize the collect script.
         *
         * @param {Object} strings Language strings.
         */
        init: function(strings) {
            var $filterModal = $('#phItemModalFilter');
            if ($filterModal.length) {
                $filterModal.appendTo('body');
            }

            // --- CLICK: ABRIR DETALHES DO ITEM ---
            // eslint-disable-next-line complexity
            $('body').on('click keydown', '.ph-item-details-trigger', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();

                var trigger = $(this);
                var container = trigger.closest('.playerhud-item-card, .ph-drop-image-container, .ph-mini-item');
                if (container.length === 0) {
                    container = trigger;
                }

                // Extração de Dados
                var name = container.attr('data-name');
                var descB64 = container.attr('data-desc-b64');
                var descDirect = container.find('.ph-item-description-content').html();
                var img = container.attr('data-image');
                var isImg = container.attr('data-isimage');
                var xp = container.attr('data-xp');
                var date = container.attr('data-date'); // Fallback (Texto PHP)
                var timestamp = container.attr('data-timestamp'); // [NOVO] Timestamp numérico

                // *** OBTENÇÃO INTELIGENTE DO MODAL ***
                var modalEls = getModalElements();

                // Se nenhum modal existir (erro grave), sai.
                if (!modalEls.root.length) {
                    return;
                }

                // Preenche Campos.
                modalEls.title.text(name);
                modalEls.name.text(name);

               // Badge XP.
                if (xp && xp !== '0' && xp.indexOf('???') === -1) {
                    // CORREÇÃO: Verificação mais robusta.
                    // Se for apenas número, adiciona ' XP'. Se já vier com texto (do PHP novo), usa direto.
                    var xpText = xp;
                    if ($.isNumeric(xp)) {
                        xpText = xp + ' XP';
                    }
                    modalEls.xp.text(xpText).removeClass('d-none').show();
                } else {
                    modalEls.xp.hide();
                }

                // Descrição.
                var descHtml = '...';
                if (descDirect) {
                    descHtml = descDirect;
                } else if (descB64) {
                    try {
                        descHtml = decodeURIComponent(escape(window.atob(descB64)));
                    } catch (err) {
                        descHtml = '...';
                    }
                }
                modalEls.desc.html(descHtml);

                // --- Internacionalização da Data ---
                var dateEl = (modalEls.root.attr('id') === 'phItemModalView') ? modalEls.date : $('#phModalDateF');
                var dateContainer = (modalEls.root.attr('id') === 'phItemModalView') ?
                    modalEls.date :
                    modalEls.dateContainer;

                var formattedDate = '';

                if (timestamp && timestamp > 0) {
                    var lang = $('html').attr('lang') || 'en';
                    lang = lang.replace('_', '-');
                    try {
                        formattedDate = new Date(parseInt(timestamp) * 1000).toLocaleDateString(lang, {
                            day: '2-digit', month: '2-digit', year: '2-digit'
                        });
                    } catch (err) {
                        formattedDate = date;
                    }
                } else {
                    formattedDate = date; // Fallback
                }

                // Aplica a data formatada
                if (formattedDate) {
                    var prefix = (strings.last_collected ? strings.last_collected + ' ' : '');

                    if (modalEls.root.attr('id') !== 'phItemModalView') {
                        // Modal do Filtro (texto direto)
                        dateEl.text(prefix + formattedDate);
                        if (dateContainer) {
                            dateContainer.removeClass('d-none');
                        }
                    } else {
                        // Modal do Bloco (span interno)
                        dateEl.find('span').text(prefix + formattedDate);
                        dateEl.show();
                    }
                } else {
                    if (modalEls.root.attr('id') !== 'phItemModalView') {
                        if (dateContainer) {
                            dateContainer.addClass('d-none');
                        }
                    } else {
                        dateEl.hide();
                    }
                }
                // -----------------------------------

                // Imagem.
                modalEls.imgContainer.empty();
                if (isImg == '1') {
                    modalEls.imgContainer.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img',
                        alt: ''
                    }));
                } else {
                    modalEls.imgContainer.append($('<span>', {
                        'class': 'ph-modal-emoji',
                        'aria-hidden': 'true',
                        text: img
                    }));
                }

                // Show Modal (Bootstrap 5 Check).
                var modalEl = modalEls.root[0];
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                } else {
                    modalEls.root.modal('show');
                }
            });

            // --- CLICK: COLETAR ITEM (AJAX) ---
            $('body').on('click', '.ph-action-collect', function(e) {
                e.preventDefault();
                var trigger = $(this);
                var mode = trigger.attr('data-mode') || 'card';

                if (trigger.hasClass('disabled') || trigger.attr('disabled')) {
                    return;
                }

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
                            // Pega os dados do servidor
                            var serverDate = (resp.item_data && resp.item_data.date) ? resp.item_data.date : '';
                            // [NOVO] Pega o timestamp
                            var serverTs = (resp.item_data && resp.item_data.timestamp) ? resp.item_data.timestamp : 0;

                            var card = trigger.closest('.playerhud-item-card');

                            if (card.length) {
                                var badge = card.find('.ph-badge-count');
                                var isUnique = card.attr('data-unique') === '1';

                                if (!isUnique) {
                                    var currentCount = parseInt(badge.text().replace('x', ''), 10) || 0;
                                    badge.text('x' + (currentCount + 1)).removeClass('d-none').show();
                                }

                                // [ATUALIZAÇÃO] Grava os dois formatos
                                card.attr('data-date', serverDate);
                                card.attr('data-timestamp', serverTs);
                            }

                            if (mode === 'image') {
                                var imgContainer = trigger.closest('.ph-drop-image-container');
                                // [ATUALIZAÇÃO] Grava os dois formatos
                                imgContainer.attr('data-date', serverDate);
                                imgContainer.attr('data-timestamp', serverTs);
                            }

                            var hasTimer = (resp.cooldown_deadline && resp.cooldown_deadline > 0);
                            var isLimit = resp.limit_reached;

                            if (mode === 'text' || mode === 'image') {
                                handleTextImageMode(trigger, mode, hasTimer, isLimit, resp);
                            } else {
                                handleCardMode(trigger, card, hasTimer, isLimit, resp, strings, originalHtml);
                            }

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
