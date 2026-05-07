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
        const xptext = el.dataset.xptext || '';

        const $m = $(modalEl);
        $m.find('#phModalNameView').text(name);
        $m.find('#phModalDescView').html(description);
        $m.find('#phModalDateView').hide();
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

        if (xptext) {
            $m.find('#phModalXPView').text(xptext).show().removeClass('ph-display-none');
        } else {
            $m.find('#phModalXPView').hide().addClass('ph-display-none');
        }

        openModal();
    };

    /**
     * Initialize click and keyboard handlers on all profile items.
     *
     * The modal HTML is embedded in the page via the profile_content template
     * ({{> block_playerhud/modal_item }}), so no config is needed.
     *
     * We look for the modal that lives INSIDE the profile content wrapper to avoid
     * conflicts when the block is also rendered on the page sidebar (which causes
     * two elements with id="ph-item-modal-view" in the DOM).
     */
    const init = () => {
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
    };

    return {init};
});
