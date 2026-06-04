/* global bootstrap */
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Student View JS for PlayerHUD.
 *
 * @module     block_playerhud/view
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification', 'core/ajax'], function($, Notification, Ajax) {

    return {
        /**
         * Initialize the view script.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {
            // Move modals to body end to avoid z-index/stacking-context issues.
            $('#ph-item-modal-view').appendTo('body');
            $('#ph-char-modal').appendTo('body');

            // Char modal — explicit handler (cross-version safe: BS4 + BS5).
            // data-bs-toggle declarative may not fire in Moodle 4.5 if Bootstrap's
            // document-level listener hasn't been registered yet via AMD.
            $(document).off('click.phcharmodal').on('click.phcharmodal', '[data-ph-modal="ph-char-modal"]', function() {
                var el = document.getElementById('ph-char-modal');
                if (!el) {
                    return;
                }
                document.body.appendChild(el);
                require(['theme_boost/bootstrap/modal'], function(BootstrapModal) {
                    var inst = (BootstrapModal.getInstance && BootstrapModal.getInstance(el))
                        || $(el).data('bs.modal')
                        || new BootstrapModal(el);
                    inst.show();
                });
            });

            // Hoist the history/help shortcut buttons into the block's title row
            // so they appear on the same line as "PlayerHUD".
            (function() {
                var sidebar = document.querySelector('.block_playerhud_sidebar');
                if (!sidebar) {
                    return;
                }
                var btnRow = sidebar.querySelector('.ph-header-actions');
                if (!btnRow) {
                    return;
                }
                var block = sidebar.closest('.block_playerhud');
                if (!block) {
                    return;
                }
                var titleEl = block.querySelector('.card-title');
                if (!titleEl) {
                    return;
                }
                // Wrap the title + buttons in a flex row so they appear side by side.
                var wrapper = document.createElement('div');
                wrapper.className = 'd-flex align-items-center mb-2';
                titleEl.parentElement.insertBefore(wrapper, titleEl);
                titleEl.classList.add('mb-0');
                wrapper.appendChild(titleEl);
                btnRow.remove();
                btnRow.classList.add('ms-auto');
                wrapper.appendChild(btnRow);
            }());

            // 1. Disable HUD Confirmation.
            $('.js-disable-hud').on('click', function(e) {
                e.preventDefault();
                const url = $(this).attr('href');
                const msg = $(this).attr('data-confirm-msg');

                Notification.confirm(
                    config.strings.confirm_title,
                    msg,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        window.location.href = url;
                    }
                );
            });

            // Initialize tooltips for quest description info buttons.
            const questInfoEls = document.querySelectorAll('.js-ph-quest-info');
            if (questInfoEls.length) {
                require(['theme_boost/bootstrap/tooltip'], function(BSTooltip) {
                    questInfoEls.forEach(function(el) {
                        const opts = {trigger: 'hover focus', placement: 'bottom'};
                        if (typeof BSTooltip === 'function') {
                            new BSTooltip(el, opts);
                        } else {
                            $(el).tooltip(opts);
                        }
                    });
                });
            }

            // Initialize popovers for compact shop item icons (> 3 items in trade).
            const shopPopoverEls = document.querySelectorAll('.ph-shop-popover');
            if (shopPopoverEls.length) {
                require(['theme_boost/bootstrap/popover'], function(BSPopover) {
                    shopPopoverEls.forEach(function(el) {
                        const opts = {
                            trigger: 'hover click focus',
                            title: el.dataset.phTitle || '',
                            content: el.dataset.phContent || '',
                            html: true,
                            placement: 'top'
                        };
                        if (typeof BSPopover === 'function') {
                            new BSPopover(el, opts);
                        } else {
                            $(el).popover(opts);
                        }
                    });
                });
            }

            // Accessibility: Allow opening items with Enter or Space.
            $(document).on('keydown', '.ph-item-trigger', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });

            // 2. Item Details Modal Logic.
            /**
             * Helper to open/close bootstrap modal safely.
             */
            const openItemModal = () => {
                const el = document.getElementById('ph-item-modal-view');
                if (!el) {
                    return;
                }
                document.body.appendChild(el);
                require(['theme_boost/bootstrap/modal'], function(BootstrapModal) {
                    var inst = (BootstrapModal.getInstance && BootstrapModal.getInstance(el))
                        || $(el).data('bs.modal')
                        || new BootstrapModal(el);
                    inst.show();
                });
            };

            // Live Search for History.
            $('#ph-live-search').on('input', function() {
                const term = $(this).val().toLowerCase();
                $('.ph-searchable-row').each(function() {
                    const text = $(this).text().toLowerCase();
                    $(this).toggle(text.indexOf(term) > -1);
                });
            });

            // Handle sort selector redirects.
            $(document).on('change', '.ph-sort-selector', function() {
                const targetUrl = $(this).val();
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });

            // Client-side filter for story chapters.
            $(document).on('change', '.ph-story-filter-selector', function() {
                const value = $(this).val();
                $('#ph-story-grid .playerhud-item-card').each(function() {
                    const status = $(this).data('status');
                    const show = value === 'all' ||
                        (value === 'read' && status === 'completed') ||
                        (value === 'unread' && status !== 'completed');
                    $(this).toggle(show);
                });
            });

            // Client-side filter for collection items by action type.
            $(document).on('change', '.ph-collection-filter-selector', function() {
                const value = $(this).val();
                $('.playerhud-inventory-grid .playerhud-item-card').each(function() {
                    $(this).toggle(value === 'all' || $(this).data('filtertype') === value);
                });
            });

            // Event Delegation for clicking on items.
            // Use namespaced event to prevent duplicate handlers when init() is called more than once.
            $(document).off('click.phitemview').on('click.phitemview', '.ph-item-trigger', function(e) {
                e.preventDefault();
                const trigger = $(this);

                // Extract data.
                const name = trigger.attr('data-name');
                const img = trigger.attr('data-image');
                const isImg = trigger.attr('data-isimage'); // String "1" or "0".
                const date = trigger.attr('data-date'); // Fallback (PHP Text).
                const timestamp = trigger.attr('data-timestamp'); // Raw Timestamp.
                const count = trigger.attr('data-count');
                const desc = trigger.find('.ph-item-description-content').html();

                // Populate Modal.
                $('#phModalNameView').text(name);

                const descEl = $('#phModalDescView');
                if (desc && desc.trim() !== '') {
                    descEl.html(desc);
                } else {
                    descEl.html('<i class="text-muted">' + config.strings.no_desc + '</i>');
                }

                const badgeEl = $('#phModalCountBadgeView');
                if (count && count > 0) {
                    badgeEl.text('x' + count).show();
                } else {
                    badgeEl.hide();
                }

                // Image Handling.
                const imgCont = $('#phModalImageContainerView');
                imgCont.empty();

                if (isImg == '1' || isImg === 'true') {
                    imgCont.append($('<img>', {
                        src: img,
                        'class': 'ph-modal-img ph-img-contain-120',
                        alt: ''
                    }));
                } else {
                    // Emoji.
                    imgCont.append($('<span>', {
                        'class': 'ph-modal-emoji ph-emoji-80',
                        'aria-hidden': 'true',
                        text: img
                    }));
                }

                // --- Date Internationalization Logic ---
                const dateEl = $('#phModalDateView');
                let formattedDate = '';

                if (timestamp && timestamp > 0) {
                    let lang = $('html').attr('lang') || 'en';
                    lang = lang.replace('_', '-');

                    try {
                        formattedDate = new Date(parseInt(timestamp) * 1000).toLocaleDateString(lang, {
                            day: '2-digit',
                            month: '2-digit',
                            year: '2-digit'
                        });
                    } catch (err) {
                        formattedDate = date;
                    }
                } else {
                    formattedDate = date;
                }

                if (formattedDate) {
                    const prefix = (config.strings && config.strings.last_collected) ?
                        config.strings.last_collected + ' ' : '';

                    dateEl.find('span').text(prefix + formattedDate);
                    dateEl.show();
                } else {
                    dateEl.hide();
                }

                openItemModal();
            });

            // Overflow popover: clicking the +N stash badge shows hidden item thumbnails.
            $(document).off('click.phstashmore').on('click.phstashmore', '.ph-stash-more', function(e) {
                e.stopPropagation();
                const trigger = this;
                const raw = trigger.dataset.overflow;
                if (!raw) {
                    return;
                }

                let items;
                try {
                    items = JSON.parse(raw);
                } catch (err) {
                    return;
                }
                if (!items || !items.length) {
                    return;
                }

                const existing = document.getElementById('ph-overflow-card');
                if (existing) {
                    existing.remove();
                    if (existing._trigger === trigger) {
                        return;
                    }
                }

                const card = document.createElement('div');
                card.id = 'ph-overflow-card';
                card.className = 'ph-overflow-card';
                card._trigger = trigger;

                const grid = document.createElement('div');
                grid.className = 'ph-overflow-grid';

                items.forEach(item => {
                    const thumb = document.createElement('div');
                    thumb.className = 'ph-overflow-thumb';
                    thumb.title = item.n;

                    if (item.i === 1) {
                        const img = document.createElement('img');
                        img.src = item.u;
                        img.alt = '';
                        img.className = 'ph-overflow-img';
                        thumb.appendChild(img);
                    } else {
                        const span = document.createElement('span');
                        span.className = 'ph-overflow-emoji';
                        span.setAttribute('aria-hidden', 'true');
                        span.textContent = item.u;
                        thumb.appendChild(span);
                    }
                    grid.appendChild(thumb);
                });

                card.appendChild(grid);
                document.body.appendChild(card);

                const cr = card.getBoundingClientRect();
                const tr = trigger.getBoundingClientRect();
                let top = tr.top - cr.height - 8;
                let left = tr.left + (tr.width / 2) - (cr.width / 2);

                if (top < 8) {
                    top = tr.bottom + 8;
                }
                if (left < 8) {
                    left = 8;
                }
                if (left + cr.width > window.innerWidth - 8) {
                    left = window.innerWidth - cr.width - 8;
                }

                card.style.top = top + 'px';
                card.style.left = left + 'px';

                setTimeout(() => {
                    document.addEventListener('click', function dismissOverflow() {
                        const c = document.getElementById('ph-overflow-card');
                        if (c) {
                            c.remove();
                        }
                        document.removeEventListener('click', dismissOverflow);
                    });
                    document.addEventListener('keydown', function escOverflow(ev) {
                        if (ev.key === 'Escape') {
                            const c = document.getElementById('ph-overflow-card');
                            if (c) {
                                c.remove();
                            }
                            document.removeEventListener('keydown', escOverflow);
                        }
                    });
                }, 0);
            });

            $(document).off('keydown.phstashmore').on('keydown.phstashmore', '.ph-stash-more', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });

            // Item powers — Equipar avatar.
            $(document).off('click.phequip').on('click.phequip', '.ph-item-equip-btn', function(e) {
                e.stopPropagation();
                const $btn = $(this);
                $btn.prop('disabled', true);

                Ajax.call([{
                    methodname: 'block_playerhud_use_item',
                    args: {
                        instanceid: config.instanceid,
                        courseid: config.courseid,
                        itemid: parseInt($btn.data('itemid'), 10),
                        targetcmid: 0
                    }
                }])[0].done(function(resp) {
                    if (resp.success) {
                        // Update all userpicture containers in the page.
                        if (resp.avatar_html) {
                            $('.ph-userpicture-wrap').html(resp.avatar_html);
                        }
                        // Reload to sync button state from server.
                        window.location.reload();
                    } else {
                        $btn.prop('disabled', false);
                        Notification.addNotification({message: resp.message, type: 'error'});
                    }
                }).fail(function(ex) {
                    $btn.prop('disabled', false);
                    Notification.exception(ex);
                });
            });

            // Prevent select click from bubbling up to the card trigger.
            $(document).off('click.phlpselect mousedown.phlpselect')
                .on('click.phlpselect mousedown.phlpselect', '.ph-lp-activity-select', function(e) {
                    e.stopPropagation();
                });

            // Item powers — Usar deadline extension.
            $(document).off('click.phuse').on('click.phuse', '.ph-item-use-btn', function(e) {
                e.stopPropagation();
                const $btn = $(this);
                const $card = $btn.closest('.playerhud-item-card');
                const $select = $card.find('.ph-lp-activity-select');
                const targetcmid = $select.length ? parseInt($select.val() || '0', 10) : 0;

                if ($select.is('select') && !targetcmid) {
                    Notification.addNotification({
                        message: config.strings.item_use_pick,
                        type: 'warning'
                    });
                    return;
                }

                Notification.confirm(
                    config.strings.confirm_title,
                    config.strings.item_use_confirm,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        $btn.prop('disabled', true);
                        Ajax.call([{
                            methodname: 'block_playerhud_use_item',
                            args: {
                                instanceid: config.instanceid,
                                courseid: config.courseid,
                                itemid: parseInt($btn.data('itemid'), 10),
                                targetcmid: targetcmid
                            }
                        }])[0].done(function(resp) {
                            if (resp.success) {
                                Notification.addNotification({message: resp.message, type: 'success'});
                                window.location.reload();
                            } else {
                                $btn.prop('disabled', false);
                                Notification.addNotification({message: resp.message, type: 'error'});
                            }
                        }).fail(function(ex) {
                            $btn.prop('disabled', false);
                            Notification.exception(ex);
                        });
                    }
                );
            });
        }
    };
});
