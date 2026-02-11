/* global bootstrap */
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Collect items via Filter Shortcodes.
 *
 * @module     block_playerhud/filter_collect
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Notification from 'core/notification';
import Ajax from 'core/ajax';
import Str from 'core/str';

/**
 * Get modal elements ensuring reusability.
 *
 * @return {Object} jQuery objects for modal elements.
 */
const getModalElements = () => {
    let root = $('#phItemModalView');
    let suffix = 'View';

    if (!root.length) {
        root = $('#phItemModalFilter');
        suffix = 'F';
    }

    return {
        root: root,
        title: $(`#phModalTitle${suffix}`),
        name: $(`#phModalName${suffix}`),
        desc: $(`#phModalDesc${suffix}`),
        imgContainer: $(`#phModalImageContainer${suffix}`),
        xp: $(`#phModalXP${suffix}`),
        countBadge: $(`#phModalCountBadge${suffix}`),
        date: $(`#phModalDate${suffix}`),
        dateContainer: $(`#phModalDateContainer${suffix}`)
    };
};

/**
 * Updates the Player HUD sidebar with new stats.
 *
 * @param {Object} data Game data from server.
 * @param {Object} itemData Collected item data.
 */
const updateHud = (data, itemData) => {
    const containers = $('.playerhud-widget-container, .block_playerhud_sidebar');

    containers.each(function() {
        const container = $(this);

        // Update Level Classes.
        const tierRegex = /(^|\s)ph-lvl-tier-\S+/g;
        const removeTierClasses = (idx, className) => (className.match(tierRegex) || []).join(' ');

        if (container.hasClass('playerhud-widget-container')) {
            container.removeClass(removeTierClasses).addClass(data.level_class);
        }

        container.find('.ph-sidebar-grid, .progress-bar, .badge').each(function() {
            $(this).removeClass(removeTierClasses).addClass(data.level_class);
        });

        // Update Progress Bar.
        const progressBar = container.find('.progress-bar');
        progressBar.css('width', `${data.progress}%`).attr('aria-valuenow', data.progress);

        // Update Level Badge.
        const levelBadge = container.find('.badge').filter(function() {
            return $(this).text().match(/(Level|Nível)/) || $(this).attr('class').match(/ph-lvl-tier-/);
        });

        if (levelBadge.length) {
            const labelText = (levelBadge.text().indexOf('Nível') > -1) ? 'Nível' : 'Level';
            let lvlString = `${data.level}`;
            if (data.max_levels > 0) {
                lvlString += `/${data.max_levels}`;
            }
            levelBadge.text(`${labelText} ${lvlString}`);
        }

        // Update XP Text.
        container.find('span, div, strong').each(function() {
            const el = $(this);
            if (el.children().length === 0 && el.text().indexOf('XP') > -1) {
                let xpString = `${data.currentxp}`;
                if (data.xp_target > 0) {
                    xpString += ` / ${data.xp_target}`;
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

/**
 * Updates the stash (recent items) in the sidebar.
 *
 * @param {Object} itemData
 */
const updateStash = (itemData) => {
    const stashes = $('.ph-sidebar-stash, .ph-widget-stash');

    stashes.each(function() {
        const stash = $(this);
        stash.closest('.ph-stash-wrapper').removeClass('d-none').show();
        stash.find('.text-muted, span.small').remove();

        // Check duplicated by name and remove.
        stash.children().filter((i, el) => $(el).attr('data-name') === itemData.name).remove();

        const isImage = String(itemData.isimage) === '1';
        let contentHtml = '';

        if (isImage) {
            contentHtml = `<img src="${itemData.image}" alt="" class="ph-mini-item-img">`;
        } else {
            contentHtml = `<span class="ph-mini-emoji" aria-hidden="true">${itemData.image}</span>`;
        }

        const newItem = $('<div>', {
            'class': 'ph-mini-item ph-item-trigger border bg-white rounded ' +
                     'd-flex align-items-center justify-content-center overflow-hidden position-relative shadow-sm',
            'role': 'button',
            'tabindex': '0',
            'data-name': itemData.name,
            'data-xp': itemData.xp,
            'data-image': itemData.image,
            'data-isimage': itemData.isimage,
            'data-date': itemData.date,
            'data-timestamp': itemData.timestamp || 0,
            'title': itemData.name,
            'aria-label': itemData.name
        });

        newItem.append(`<div class="d-none ph-item-description-content">${itemData.description || ''}</div>`);
        newItem.append(contentHtml);

        newItem.hide().prependTo(stash).fadeIn();

        const limit = stash.hasClass('ph-widget-stash') ? 14 : 6;
        if (stash.children('.ph-mini-item').length > limit) {
            stash.children('.ph-mini-item').last().remove();
        }
    });
};

/**
 * Handles the collection button loading state.
 *
 * @param {Object} trigger jQuery element.
 * @param {Boolean} isLoading
 * @param {String} originalHtml
 */
const toggleLoading = (trigger, isLoading, originalHtml = '') => {
    const mode = trigger.attr('data-mode') || 'card';

    if (isLoading) {
        trigger.addClass('disabled').attr('aria-disabled', 'true');
        if (mode === 'card') {
            // Set fixed width to prevent layout jump.
            trigger.css('width', trigger.outerWidth() + 'px');
            trigger.html('<i class="fa fa-spinner fa-spin" aria-hidden="true"></i>');
        } else {
            trigger.addClass('ph-opacity-50').css('pointer-events', 'none');
        }
    } else {
        trigger.removeClass('disabled').removeAttr('aria-disabled');
        if (mode === 'card') {
            trigger.css('width', '');
            trigger.html(originalHtml);
        } else {
            trigger.removeClass('ph-opacity-50').css('pointer-events', 'auto');
        }
    }
};

/**
 * Updates UI for Text or Image mode.
 *
 * @param {Object} trigger The jQuery element.
 * @param {String} mode 'text' or 'image'.
 * @param {Boolean} hasTimer If has cooldown.
 * @param {Boolean} isLimit If limit reached.
 * @param {Object} resp Response data.
 */
const updateTextOrImageUi = (trigger, mode, hasTimer, isLimit, resp) => {
    if (hasTimer || isLimit) {
        if (mode === 'text') {
            trigger.removeAttr('href').removeClass('ph-action-collect').addClass('ph-item-details-trigger');
            trigger.css({'cursor': 'pointer', 'pointer-events': 'auto', 'opacity': '1'});

            if (isLimit) {
                trigger.addClass('text-success fw-bold')
                    .html(`<i class="fa fa-check" aria-hidden="true"></i> ${trigger.text()}`);
            } else {
                trigger.addClass('ph-text-dimmed')
                    .html(`<span aria-hidden="true">⏳</span> ${trigger.text()} ` +
                          `<small class="ph-timer" data-deadline="${resp.cooldown_deadline}">...</small>`);
            }
        } else {
            // Image Mode.
            const container = trigger.closest('.ph-drop-image-container');
            const imgWrapper = container.find('> div').first();
            trigger.remove(); // Remove link.

            container.addClass('ph-item-details-trigger ph-cursor-pointer')
                     .attr({'tabindex': '0', 'role': 'button'});

            if (isLimit) {
                imgWrapper.addClass('ph-state-grayscale');
                container.append('<span class="badge bg-success rounded-circle ph-badge-bottom-right">' +
                    '<i class="fa fa-check" aria-hidden="true"></i></span>');
            } else {
                imgWrapper.addClass('ph-state-dimmed');
                container.append(`<div class="ph-timer badge bg-light text-dark border shadow-sm ph-badge-bottom-center"
                    data-deadline="${resp.cooldown_deadline}" data-no-label="1">...</div>`);
            }
        }
    } else if (mode === 'text') {
        // Success flash for text.
        const oldColor = trigger.css('color');
        trigger.css('color', '#198754'); // Bootstrap success.
        setTimeout(() => trigger.css('color', oldColor), 1000);
    }
};

/**
 * Updates UI for Card mode.
 *
 * @param {Object} trigger The jQuery element.
 * @param {Boolean} hasTimer If has cooldown.
 * @param {Boolean} isLimit If limit reached.
 * @param {Object} resp Response data.
 * @param {String} originalHtml Original button HTML.
 * @param {Object} strings Localized strings.
 */
const updateCardUi = (trigger, hasTimer, isLimit, resp, originalHtml, strings) => {
    if (hasTimer) {
        trigger.removeClass('btn-primary ph-action-collect').removeAttr('href');
        const tHtml = `⏳ <span class="ph-timer" data-deadline="${resp.cooldown_deadline}">...</span>`;
        const tBtn = $(`<div class="btn btn-light btn-sm w-100 ph-text-dimmed" tabindex="0">${tHtml}</div>`);
        trigger.replaceWith(tBtn);
        tBtn.focus();
    } else if (isLimit) {
        trigger.removeClass('btn-primary ph-action-collect')
            .addClass('btn-light text-success disabled border-success')
            .css('cursor', 'default').removeAttr('href')
            .html(`<i class="fa fa-check" aria-hidden="true"></i> ${strings.collected}`);
        trigger.closest('.playerhud-item-card').find('.ph-item-details-trigger').focus();
    } else {
        trigger.removeClass('btn-primary disabled').addClass('btn-success')
            .html(`<i class="fa fa-check" aria-hidden="true"></i> ${strings.collected}`).css('width', '');

        setTimeout(() => {
            trigger.removeClass('btn-success').addClass('btn-primary').html(originalHtml);
            trigger.focus();
        }, 1500);
    }
};

/**
 * Process the collection result UI updates (Orchestrator).
 *
 * @param {Object} trigger The jQuery element.
 * @param {Object} resp The ajax response.
 * @param {String} originalHtml The original HTML.
 * @param {Object} strings Localized strings.
 */
const handleCollectionSuccess = (trigger, resp, originalHtml, strings) => {
    const mode = trigger.attr('data-mode') || 'card';
    const hasTimer = (resp.cooldown_deadline && resp.cooldown_deadline > 0);
    const isLimit = resp.limit_reached;

    // Update item data on card attributes.
    if (resp.item_data) {
        const card = trigger.closest('.playerhud-item-card');
        if (card.length) {
            const badge = card.find('.ph-badge-count');
            if (card.attr('data-unique') !== '1') {
                const currentCount = parseInt(badge.text().replace('x', ''), 10) || 0;
                badge.text('x' + (currentCount + 1)).removeClass('d-none').show();
            }
            card.attr('data-date', resp.item_data.date);
            card.attr('data-timestamp', resp.item_data.timestamp);
        }

        if (mode === 'image') {
            trigger.closest('.ph-drop-image-container').attr({
                'data-date': resp.item_data.date,
                'data-timestamp': resp.item_data.timestamp
            });
        }
    }

    // Call specific UI updaters to reduce complexity.
    if (mode === 'text' || mode === 'image') {
        updateTextOrImageUi(trigger, mode, hasTimer, isLimit, resp);
    } else {
        updateCardUi(trigger, hasTimer, isLimit, resp, originalHtml, strings);
    }

    if (resp.game_data) {
        updateHud(resp.game_data, resp.item_data);
    }
};

/**
 * Initialize.
 *
 * @param {Object} config Configuration object passed from PHP.
 */
export const init = (config) => {
    // Ensure strings are available.
    const strings = config.strings;

    // Move modal to body.
    const $filterModal = $('#phItemModalFilter');
    if ($filterModal.length) {
        $filterModal.appendTo('body');
    }

    // Disable HUD Confirmation.
    $('body').on('click', '.js-disable-hud', function(e) {
        e.preventDefault();
        const url = $(this).attr('href');
        const msg = $(this).attr('data-confirm-msg');

        Notification.confirm(
            strings.confirm_title,
            msg,
            strings.yes,
            strings.cancel,
            () => {
                window.location.href = url;
            }
        );
    });

    // Item Details Modal.
    // eslint-disable-next-line complexity
    $('body').on('click keydown', '.ph-item-details-trigger', function(e) {
        if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        e.preventDefault();

        const trigger = $(this);
        let container = trigger.closest('.playerhud-item-card, .ph-drop-image-container, .ph-mini-item');
        if (!container.length) {
            container = trigger;
        }

        const data = {
            name: container.attr('data-name'),
            descB64: container.attr('data-desc-b64'),
            descDirect: container.find('.ph-item-description-content').html(),
            img: container.attr('data-image'),
            isImg: container.attr('data-isimage'),
            xp: container.attr('data-xp'),
            date: container.attr('data-date'),
            timestamp: container.attr('data-timestamp')
        };

        const modalEls = getModalElements();
        if (!modalEls.root.length) {
            return;
        }

        modalEls.title.text(data.name);
        modalEls.name.text(data.name);

        // CORREÇÃO CRÍTICA: Convertendo XP para string antes de usar indexOf
        // Isso previne o erro "data.xp.indexOf is not a function" quando XP é numérico.
        if (data.xp && data.xp !== '0') {
            const xpStr = String(data.xp);
            if (xpStr.indexOf('???') === -1) {
                const xpText = $.isNumeric(data.xp) ? `${data.xp} XP` : data.xp;
                modalEls.xp.text(xpText).removeClass('d-none').show();
            } else {
                modalEls.xp.hide();
            }
        } else {
            modalEls.xp.hide();
        }

        if (modalEls.countBadge.length) {
            modalEls.countBadge.hide();
        }

        let descHtml = '...';
        if (data.descDirect) {
            descHtml = data.descDirect;
        } else if (data.descB64) {
            try {
                descHtml = decodeURIComponent(escape(window.atob(data.descB64)));
            } catch (err) { /* Ignore */ }
        }
        modalEls.desc.html(descHtml);

        // Date Handling.
        let formattedDate = data.date;
        if (data.timestamp && data.timestamp > 0) {
            const lang = $('html').attr('lang').replace('_', '-') || 'en';
            try {
                formattedDate = new Date(parseInt(data.timestamp) * 1000).toLocaleDateString(lang, {
                    day: '2-digit', month: '2-digit', year: '2-digit'
                });
            } catch (err) { /* Ignore */ }
        }

        if (formattedDate) {
            const prefix = strings.last_collected ? `${strings.last_collected} ` : '';
            if (modalEls.root.attr('id') === 'phItemModalView') {
                modalEls.date.find('span').text(prefix + formattedDate);
                modalEls.date.show();
            } else {
                modalEls.date.text(prefix + formattedDate);
                if (modalEls.dateContainer) {
                    modalEls.dateContainer.removeClass('d-none');
                }
            }
        } else {
            if (modalEls.root.attr('id') === 'phItemModalView') {
                modalEls.date.hide();
            } else if (modalEls.dateContainer) {
                modalEls.dateContainer.addClass('d-none');
            }
        }

        // Image.
        modalEls.imgContainer.empty();
        if (String(data.isImg) === '1') {
            modalEls.imgContainer.append($('<img>', {src: data.img, 'class': 'ph-modal-img', alt: ''}));
        } else {
            modalEls.imgContainer.append($('<span>', {
                'class': 'ph-modal-emoji',
                'aria-hidden': 'true',
                text: data.img
            }));
        }

        // Open Modal.
        const modalEl = modalEls.root[0];
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        } else {
            modalEls.root.modal('show');
        }
    });

    // ACTION: COLLECT ITEM.
    $('body').on('click', '.ph-action-collect', function(e) {
        e.preventDefault();
        const trigger = $(this);

        if (trigger.hasClass('disabled') || trigger.attr('aria-disabled')) {
            return;
        }

        const originalHtml = trigger.html();
        toggleLoading(trigger, true);

        // Extract params safely.
        const href = trigger.attr('href');
        if (!href) {
            toggleLoading(trigger, false, originalHtml);
            return;
        }

        const urlObj = new URL(href, window.location.href);
        const params = {
            instanceid: parseInt(urlObj.searchParams.get('instanceid')),
            dropid: parseInt(urlObj.searchParams.get('dropid')),
            courseid: parseInt(urlObj.searchParams.get('courseid'))
        };

        // CORREÇÃO CRÍTICA: Validar parâmetros numéricos antes do Ajax
        // Evita "Invalid parameter value detected"
        if (isNaN(params.instanceid) || isNaN(params.dropid) || isNaN(params.courseid)) {
            toggleLoading(trigger, false, originalHtml);
            // eslint-disable-next-line no-console
            console.error('PlayerHUD: Invalid collection parameters.', params);
            return;
        }

        Ajax.call([{
            methodname: 'block_playerhud_collect_item',
            args: params
        }])[0].then((resp) => {
            if (resp.success) {
                handleCollectionSuccess(trigger, resp, originalHtml, strings);
            } else {
                toggleLoading(trigger, false, originalHtml);
                // eslint-disable-next-line promise/no-nesting
                Str.get_strings([
                    {key: 'error', component: 'core'},
                    {key: 'ok', component: 'core'}
                ]).then((strs) => {
                    Notification.alert(strs[0], resp.message, strs[1]);
                    return;
                }).catch(() => { /* Ignore */ });
            }
            return;
        }).catch((ex) => {
            toggleLoading(trigger, false, originalHtml);
            Notification.exception(ex);
        });
    });
};
