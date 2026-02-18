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

define(['jquery', 'core/notification', 'core/copy_to_clipboard'], function($, Notification) {

    /**
     * Manage Drops module.
     *
     * @module     block_playerhud/manage_drops
     * @copyright  2026 Jean Lúcio
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    return {
        init: function(config) {
            const currentItem = config.item;
            const langStrings = config.strings;
            let currentDropCode = 0;

            // DOM Elements.
            const $inputCode = $('#finalCode');
            const $previewBox = $('#previewContainer');
            const $groupTextLink = $('#textInputGroup');
            const $inputLinkText = $('#customText');
            const $groupCardOptions = $('#cardCustomOptions');
            const $inputBtnText = $('#customBtnText');
            const $inputBtnEmoji = $('#customBtnEmoji');

            $('#ph-codegen-modal').appendTo('body');

            /**
             * Updates the preview and generated code in the modal.
             */
            const updateGenerator = () => {
                const modeRadio = $('input[name="codeMode"]:checked');
                const mode = modeRadio.length ? modeRadio.val() : 'card';

                const param = isNaN(currentDropCode) ? 'code=' + currentDropCode : 'id=' + currentDropCode;
                let code = '[PLAYERHUD_DROP ' + param + ']';
                let previewHtml = '';

                // Handle visibility and preview generation based on selected mode.
                if (mode === 'text') {
                    $groupTextLink.show();
                    $groupCardOptions.hide();

                    const linkTxt = ($inputLinkText.val() && $inputLinkText.val().trim()) ?
                        $inputLinkText.val().trim() : langStrings.defaultText;

                    code = '[PLAYERHUD_DROP ' + param + ' mode=text text="' + linkTxt + '"]';

                    // eslint-disable-next-line max-len
                    previewHtml = `<a href="#" onclick="return false;" class="text-primary fw-bold text-decoration-underline">${linkTxt}</a>`;

                } else if (mode === 'image') {
                    $groupTextLink.hide();
                    $groupCardOptions.hide();

                    code = '[PLAYERHUD_DROP ' + param + ' mode=image]';

                    const imgContent = currentItem.isImage ?
                        `<img src="${currentItem.url}" class="ph-gen-img-lg" alt="">` :
                        `<span class="ph-gen-emoji-lg" aria-hidden="true">${currentItem.content}</span>`;

                    previewHtml = `<div class="ph-gen-preview-wrapper-img">${imgContent}</div>`;

                } else {
                    // Card Mode.
                    $groupTextLink.hide();
                    $groupCardOptions.show();

                    const userTxt = ($inputBtnText.val() && $inputBtnText.val().trim()) ? $inputBtnText.val().trim() : '';
                    const userEmo = ($inputBtnEmoji.val() && $inputBtnEmoji.val().trim()) ? $inputBtnEmoji.val().trim() : '';
                    const previewTxt = userTxt || langStrings.takeBtn;
                    const previewEmo = userEmo || '🖐';

                    let extraAttrs = '';
                    if (userTxt !== '') {
                        extraAttrs += ' button_text="' + userTxt + '"';
                    }
                    if (userEmo !== '') {
                        extraAttrs += ' button_emoji="' + userEmo + '"';
                    }

                    code = '[PLAYERHUD_DROP ' + param + extraAttrs + ']';

                    const iconHtml = currentItem.isImage ?
                        `<img src="${currentItem.url}" class="ph-icon-contain" alt="">` :
                        `<div class="fs-1 lh-1">${currentItem.content}</div>`;

                    let btnContent = previewTxt;
                    if (previewEmo) {
                        btnContent = `<span aria-hidden="true" class="me-1">${previewEmo}</span> ${previewTxt}`;
                    }

                    previewHtml = `
                        <div class="ph-gen-preview-real-card card p-2 border shadow-sm position-relative">
                            <span class="badge bg-info text-dark rounded-pill ph-badge-preview-corner">${langStrings.yours}</span>
                            <div class="mb-2 d-flex align-items-center justify-content-center ph-h-60">
                                ${iconHtml}
                            </div>
                            <strong class="d-block mb-2 text-truncate ph-fs-09">${currentItem.name}</strong>
                            <button class="btn btn-primary btn-sm w-100 shadow-sm">${btnContent}</button>
                        </div>`;
                }

                $inputCode.val(code);
                $previewBox.html(previewHtml);
            };

            /**
             * Handles input changes to update the generator preview.
             *
             * @param {Event} e The change/input event.
             */
            const handleChange = (e) => {
                const $target = $(e.target);
                if ($target.hasClass('js-mode-trigger') ||
                    $target.is('#customText') ||
                    $target.is('#customBtnText') ||
                    $target.is('#customBtnEmoji')) {
                    updateGenerator();
                }
            };

            // Event Listeners.
            $('#ph-select-all').on('change', function() {
                const isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            $('body').on('change', '.ph-bulk-check, #ph-select-all', function() {
                const count = $('.ph-bulk-check:checked').length;
                const $btn = $('#ph-btn-bulk-delete');

                if (count > 0) {
                    $btn.removeClass('disabled').removeAttr('disabled');
                    const btnText = langStrings.delete_n_items.replace('%d', count);
                    $btn.html('<i class="fa fa-trash"></i> ' + btnText);
                } else {
                    $btn.addClass('disabled').attr('disabled', 'disabled');
                    $btn.html('<i class="fa fa-trash"></i> ' + langStrings.delete_selected);
                }
            });

            $('body').on('click', '#ph-btn-bulk-delete', function(e) {
                e.preventDefault();
                const count = $('.ph-bulk-check:checked').length;
                if (count === 0) {
                    return;
                }

                Notification.confirm(
                    langStrings.confirm_title,
                    langStrings.confirm_bulk,
                    langStrings.yes,
                    langStrings.cancel,
                    function() {
                        $('#bulk-action-form').submit();
                    }
                );
            });

            $('body').on('click', function(e) {
                const $target = $(e.target);

                // A. Single Delete Action.
                const $deleteBtn = $target.closest('.js-delete-btn');
                if ($deleteBtn.length) {
                    e.preventDefault();
                    Notification.confirm(
                        langStrings.confirm_title,
                        $deleteBtn.attr('data-confirm-msg'),
                        langStrings.yes,
                        langStrings.cancel,
                        function() {
                            window.location.href = $deleteBtn.attr('href');
                        }
                    );
                    return;
                }

                // B. Open Generator Modal.
                const $trigger = $target.closest('.js-open-gen-modal');
                if ($trigger.length) {
                    e.preventDefault();
                    currentDropCode = $trigger.attr('data-dropcode');

                    $('#modeCard').prop('checked', true);
                    $inputLinkText.val('');
                    $inputBtnText.val('');
                    $inputBtnEmoji.val('');

                    updateGenerator();

                    const modalEl = document.getElementById('ph-codegen-modal');
                    if (modalEl) {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(modalEl).show();
                        } else {
                            $(modalEl).modal('show');
                        }
                    }
                }
            });

            $('body').on('change input', handleChange);

            // Copy to Clipboard with Visual Feedback.
            $('body').on('click', '.js-copy-code', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const text = $btn.attr('data-clipboard-text');

                if (text && navigator.clipboard) {
                    // eslint-disable-next-line promise/always-return
                    navigator.clipboard.writeText(text).then(function() {
                        const originalHtml = $btn.html();
                        const originalWidth = $btn.outerWidth();

                        $btn.css('width', (originalWidth + 25) + 'px');
                        $btn.removeClass('btn-outline-secondary').addClass('btn-success');
                        $btn.html('<i class="fa fa-check"></i> ' + langStrings.gen_copied);

                        setTimeout(function() {
                            $btn.html(originalHtml);
                            $btn.removeClass('btn-success').addClass('btn-outline-secondary');
                            $btn.css('width', '');
                        }, 2000);

                    }).catch(function(err) {
                        // eslint-disable-next-line no-console
                        console.error('Clipboard error:', err);
                        Notification.alert('Error', 'Unable to copy to clipboard.', 'OK');
                    });
                }
            });
        }
    };
});
