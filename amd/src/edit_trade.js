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
 * JavaScript for Trade editing form.
 *
 * @module     block_playerhud/edit_trade
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} itemsMap Map of item IDs to their image URLs or emojis.
         */
        init: function(itemsMap) {
            const updatePreview = (selectEl) => {
                const $select = $(selectEl);
                let targetId = $select.attr('data-target');

                if (!targetId && $select.attr('name')) {
                    const match = $select.attr('name').match(/([a-z]+)_itemid_(\d+)/);
                    if (match) {
                        targetId = `preview_${match[1]}_${match[2]}`;
                    }
                }

                if (!targetId) {
                    return;
                }

                const $previewBox = $(`#${targetId}`);
                if (!$previewBox.length) {
                    return;
                }

                const itemId = String($select.val());
                $previewBox.empty();

                if (itemId && itemsMap.hasOwnProperty(itemId)) {
                    const content = itemsMap[itemId];
                    if (content.startsWith('EMOJI:')) {
                        const emoji = content.replace('EMOJI:', '');
                        $previewBox.append($('<span aria-hidden="true">').text(emoji));
                    } else {
                        $previewBox.append($('<img>', {src: content, alt: ''}));
                    }
                    $previewBox.addClass('border-success bg-white');
                } else {
                    $previewBox.append($('<span class="text-muted ph-text-xs" aria-hidden="true">?</span>'));
                    $previewBox.removeClass('border-success bg-white');
                }
            };

            // Event delegation for dynamically added elements or normal changes.
            $('body').on('change', '.ph-item-selector', function() {
                updatePreview(this);
            });

            // Initial trigger on page load.
            setTimeout(() => {
                $('.ph-item-selector').each(function() {
                    updatePreview(this);
                });
            }, 100);
        }
    };
});
