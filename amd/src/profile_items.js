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
 * Profile page item click handler for PlayerHUD.
 *
 * Opens the same item detail modal used in the student view, reusing
 * the existing modal_item template injected via AMD config.
 *
 * @module     block_playerhud/profile_items
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'theme_boost/bootstrap/modal'], function($, BootstrapModal) {

    let modalEl = null;
    let strings = {};

    /**
     * Show the Bootstrap modal.
     */
    const openModal = () => {
        if (!modalEl) {
            return;
        }
        document.body.appendChild(modalEl);
        var inst = (BootstrapModal.getInstance && BootstrapModal.getInstance(modalEl))
            || $(modalEl).data('bs.modal')
            || new BootstrapModal(modalEl);
        inst.show();
    };

    /**
     * Populate and open the item modal for the given profile item element.
     *
     * @param {HTMLElement} el The clicked .ph-profile-item-clickable element.
     */
    const showItemModal = (el) => {
        const name = el.dataset.name || '';
        const isimage = el.dataset.isimage === '1';
        const imageurl = el.dataset.imageurl || '';
        const imagecontent = el.dataset.imagecontent || '';
        const description = $(el).find('.ph-item-desc-profile').html() || '';
        const timestamp = el.dataset.timestamp || '';
        const date = el.dataset.date || '';

        const $m = $(modalEl);
        $m.find('#phModalNameView').text(name);
        $m.find('#phModalDescView').html(description);
        $m.find('#phModalXPView').hide().addClass('ph-display-none');
        $m.find('#phModalCountBadgeView').hide().addClass('ph-display-none');

        const imgCont = $m.find('#phModalImageContainerView');
        imgCont.empty();

        if (isimage && imageurl) {
            imgCont.append($('<img>', {
                src: imageurl,
                'class': 'ph-modal-img ph-img-contain-120',
                alt: ''
            }));
        } else if (imagecontent) {
            imgCont.append($('<span>', {
                'class': 'ph-modal-emoji ph-emoji-80',
                'aria-hidden': 'true',
                text: imagecontent
            }));
        }

        const dateEl = $m.find('#phModalDateView');
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
            const prefix = strings.last_collected ? strings.last_collected + ' ' : '';
            dateEl.find('span').text(prefix + formattedDate);
            dateEl.show().removeClass('ph-display-none');
        } else {
            dateEl.hide();
        }

        openModal();
    };

    /**
     * Initialize click and keyboard handlers on all profile items.
     *
     * Looks for the modal inside .ph-profile-wrap to avoid conflicts when the
     * block is also rendered in the page sidebar (two #ph-item-modal-view in DOM).
     *
     * @param {Object} config Optional config object with strings.
     */
    const init = (config) => {
        if (config && config.strings) {
            strings = config.strings;
        }
        const profileWrap = document.querySelector('.ph-profile-wrap');
        modalEl = profileWrap
            ? profileWrap.querySelector('#ph-item-modal-view')
            : document.getElementById('ph-item-modal-view');
        if (!modalEl) {
            return;
        }

        document.querySelectorAll('.ph-profile-item-clickable').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                showItemModal(el);
            });
            el.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    showItemModal(el);
                }
            });
        });

        const moreEl = document.querySelector('.ph-profile-wrap .ph-profile-more[data-overflow]');
        if (moreEl) {
            const openOverflow = (e) => {
                e.stopPropagation();
                let items;
                try {
                    items = JSON.parse(moreEl.dataset.overflow);
                } catch (err) {
                    return;
                }
                if (!items || !items.length) {
                    return;
                }

                const existing = document.getElementById('ph-overflow-card');
                if (existing) {
                    existing.remove();
                    if (existing._trigger === moreEl) {
                        return;
                    }
                }

                const card = document.createElement('div');
                card.id = 'ph-overflow-card';
                card.className = 'ph-overflow-card';
                card._trigger = moreEl;

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
                const tr = moreEl.getBoundingClientRect();
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

                card.style.top = `${top}px`;
                card.style.left = `${left}px`;

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
            };

            moreEl.addEventListener('click', openOverflow);
            moreEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    openOverflow(e);
                }
            });
        }
    };

    return {init};
});
