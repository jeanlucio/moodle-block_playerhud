/* global bootstrap */
define(['jquery', 'core/notification'], function($, Notification) {

    /**
     * Restores the button state in case of error or completion.
     *
     * @param {Object} btn The jQuery button element.
     * @param {string} html The original HTML content of the button.
     * @param {string} mode The display mode ('card', 'text', 'image').
     * @param {string} width The original width of the button (for card mode).
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
     * @param {boolean} hasTimer Whether the item is in cooldown.
     * @param {boolean} isLimit Whether the collection limit is reached.
     * @param {Object} resp The server response object.
     * @param {Object} strings The language strings object.
     * @param {string} originalHtml The original HTML of the trigger.
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
            card.find('.ph-item-details-trigger').focus();
        } else {
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
     * Handles the visual state for Text and Image modes.
     *
     * @param {Object} trigger The clicked jQuery element.
     * @param {string} mode The display mode ('text' or 'image').
     * @param {boolean} hasTimer Whether the item is in cooldown.
     * @param {boolean} isLimit Whether the collection limit is reached.
     * @param {Object} resp The server response object.
     */
    var handleTextImageMode = function(trigger, mode, hasTimer, isLimit, resp) {
        if (hasTimer || isLimit) {
            if (mode === 'text') {
                trigger.removeAttr('href').removeClass('ph-action-collect');
                trigger.addClass('ph-item-details-trigger');
                trigger.css('opacity', '1').css('pointer-events', 'auto').css('cursor', 'pointer');

                if (isLimit) {
                    trigger.addClass('text-success')
                        .html('<i class="fa fa-check"></i> ' + trigger.text());
                } else {
                    trigger.addClass('text-muted')
                        .html('⏳ ' + trigger.text() +
                        ' <small class="ph-timer" data-deadline="' +
                        resp.cooldown_deadline + '">...</small>');
                }
            } else {
                var container = trigger.closest('.ph-drop-image-container');
                var imgWrapper = container.find('> div').first();
                trigger.remove();
                container.addClass('ph-item-details-trigger')
                         .attr('tabindex', '0')
                         .attr('role', 'button')
                         .css('cursor', 'pointer');

                if (isLimit) {
                    imgWrapper.addClass('ph-state-grayscale');
                    container.append('<span class="badge bg-success rounded-circle ph-badge-bottom-right">' +
                        '<i class="fa fa-check"></i></span>');
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
                trigger.css('color', '#28a745');
                setTimeout(function() {
                    trigger.css('color', oldColor);
                }, 1000);
            } else {
                trigger.css('opacity', '1').css('pointer-events', 'auto');
            }
        }
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

            // 1. Update Container Class (Only for Widget)
            if (container.hasClass('playerhud-widget-container')) {
                container.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);
            }

            // 2. Sidebar Grid Specific Update
            var sidebarGrid = container.find('.ph-sidebar-grid');
            if (sidebarGrid.length) {
                sidebarGrid.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);
            }

            // 3. Update Progress Bar
            var progressBar = container.find('.progress-bar');
            progressBar.css('width', data.progress + '%').attr('aria-valuenow', data.progress);
            progressBar.removeClass(function(index, className) {
                return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
            }).addClass(data.level_class);

            // 4. Update Level Badge
            var levelBadge = container.find('.badge').filter(function() {
                return $(this).text().match(/(Level|Nível)/) || $(this).attr('class').match(/ph-lvl-tier-/);
            });

            if (levelBadge.length) {
                levelBadge.removeClass(function(index, className) {
                    return (className.match(/(^|\s)ph-lvl-tier-\S+/g) || []).join(' ');
                }).addClass(data.level_class);

                var oldTxt = levelBadge.text();
                var label = (oldTxt.indexOf('Nível') > -1) ? 'Nível' : 'Level';
                var lvlString = data.level;
                if (typeof data.max_levels !== 'undefined' && data.max_levels > 0) {
                    lvlString += '/' + data.max_levels;
                }
                levelBadge.text(label + ' ' + lvlString);
            }

            // 5. Update XP Text
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

        // 6. Update Item Stash
        if (itemData) {
            var stashes = $('.ph-sidebar-stash, .ph-widget-stash');

            stashes.each(function() {
                var stash = $(this);
                var wrapper = stash.closest('.ph-stash-wrapper');
                if (wrapper.length) {
                    wrapper.show();
                }
                stash.find('.text-muted').remove();

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

                newItem.attr('data-name', itemData.name);
                newItem.attr('data-xp', itemData.xp);
                newItem.attr('data-image', itemData.image);
                newItem.attr('data-isimage', itemData.isimage);
                newItem.attr('data-date', itemData.date);
                newItem.attr('title', itemData.name);
                newItem.attr('aria-label', itemData.name);

                newItem.append('<div class="d-none ph-item-description-content">' + itemData.description + '</div>');
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
        }
    };

    return {
        /**
         * Initialize the module.
         *
         * @param {Object} strings Language strings passed from PHP.
         */
        init: function(strings) {
            var $modal = $('#phItemModalFilter');
            if ($modal.length) {
                $modal.appendTo('body');
            }

            // Details Modal Trigger
            $('body').on('click keydown', '.ph-item-details-trigger', function(e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
                    return;
                }
                e.preventDefault();

                var trigger = $(this);
                var container = trigger.closest('.playerhud-item-card, .ph-drop-image-container');
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

            // Collect Action (AJAX)
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
                            var card = trigger.closest('.playerhud-item-card');
                            if (card.length) {
                                var badge = card.find('.ph-badge-count');
                                var currentCount = parseInt(badge.text().replace('x', ''), 10) || 0;
                                badge.text('x' + (currentCount + 1)).removeClass('d-none').show();
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
