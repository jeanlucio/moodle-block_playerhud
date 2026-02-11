/* global bootstrap */
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
            var currentItem = config.item;
            var langStrings = config.strings;
            var currentDropCode = 0;

            // Elementos DOM
            var $inputCode = $('#finalCode');
            var $previewBox = $('#previewContainer');
            var $groupTextLink = $('#textInputGroup');
            var $inputLinkText = $('#customText');
            var $groupCardOptions = $('#cardCustomOptions');
            var $inputBtnText = $('#customBtnText');
            var $inputBtnEmoji = $('#customBtnEmoji');

            $('#codeGenModal').appendTo('body');

            /**
             * Atualiza o preview e o código gerado no modal.
             */
            var updateGenerator = function() {
                var modeRadio = $('input[name="codeMode"]:checked');
                var mode = modeRadio.length ? modeRadio.val() : 'card';

                var param = isNaN(currentDropCode) ? 'code=' + currentDropCode : 'id=' + currentDropCode;
                var code = '[PLAYERHUD_DROP ' + param + ']';
                var previewHtml = '';

                // Visibilidade dos grupos
                if (mode === 'text') {
                    $groupTextLink.show();
                    $groupCardOptions.hide();

                    var linkTxt = ($inputLinkText.val() && $inputLinkText.val().trim()) ?
                        $inputLinkText.val().trim() : langStrings.defaultText;

                    code = '[PLAYERHUD_DROP ' + param + ' mode=text text="' + linkTxt + '"]';

                    previewHtml = '<a href="#" onclick="return false;" ' +
                        'class="text-primary fw-bold text-decoration-underline">' + linkTxt + '</a>';

                } else if (mode === 'image') {
                    $groupTextLink.hide();
                    $groupCardOptions.hide();

                    code = '[PLAYERHUD_DROP ' + param + ' mode=image]';

                    // Uso das classes ph-gen-* definidas no CSS
                    var imgContent = currentItem.isImage ?
                        '<img src="' + currentItem.url + '" class="ph-gen-img-lg" alt="">' :
                        '<span class="ph-gen-emoji-lg" aria-hidden="true">' + currentItem.content + '</span>';

                    previewHtml = '<div class="ph-gen-preview-wrapper-img">' +
                        imgContent + '</div>';

                } else {
                    // Mode Card
                    $groupTextLink.hide();
                    $groupCardOptions.show();

                    var userTxt = ($inputBtnText.val() && $inputBtnText.val().trim()) ? $inputBtnText.val().trim() : '';
                    var userEmo = ($inputBtnEmoji.val() && $inputBtnEmoji.val().trim()) ? $inputBtnEmoji.val().trim() : '';
                    var previewTxt = userTxt || langStrings.takeBtn;
                    var previewEmo = userEmo || '🖐';

                    var extraAttrs = '';
                    if (userTxt !== '') {
                        extraAttrs += ' button_text="' + userTxt + '"';
                    }
                    if (userEmo !== '') {
                        extraAttrs += ' button_emoji="' + userEmo + '"';
                    }

                    code = '[PLAYERHUD_DROP ' + param + extraAttrs + ']';

                    // CLEANUP: Substituído style="..." por classes utilitárias ou Bootstrap
                    // fs-1 equivale a ~2.5rem
                    var iconHtml = currentItem.isImage ?
                        '<img src="' + currentItem.url + '" class="ph-icon-contain" alt="">' :
                        '<div class="fs-1 lh-1">' + currentItem.content + '</div>';

                    var btnContent = previewTxt;
                    if (previewEmo) {
                        btnContent = '<span aria-hidden="true" class="me-1">' + previewEmo + '</span> ' + previewTxt;
                    }

                    // CLEANUP: Estrutura sem estilos inline
                    previewHtml = '<div class="ph-gen-preview-real-card card p-2 border shadow-sm position-relative">' +
                        '<span class="badge bg-info text-dark rounded-pill ph-badge-preview-corner">' +
                        langStrings.yours + '</span>' +
                        '<div class="mb-2 d-flex align-items-center justify-content-center ph-h-60">' +
                        iconHtml + '</div>' +
                        '<strong class="d-block mb-2 text-truncate ph-fs-09">' +
                        currentItem.name + '</strong>' +
                        '<button class="btn btn-primary btn-sm w-100 shadow-sm">' + btnContent + '</button>' +
                        '</div>';
                }

                $inputCode.val(code);
                $previewBox.html(previewHtml);
            };

            var handleChange = function(e) {
                var $target = $(e.target);
                if ($target.hasClass('js-mode-trigger') ||
                    $target.is('#customText') ||
                    $target.is('#customBtnText') ||
                    $target.is('#customBtnEmoji')) {
                    updateGenerator();
                }
            };

            // Listeners
            $('#ph-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            $('body').on('change', '.ph-bulk-check, #ph-select-all', function() {
                var count = $('.ph-bulk-check:checked').length;
                var $btn = $('#ph-btn-bulk-delete');

                if (count > 0) {
                    $btn.removeClass('disabled').removeAttr('disabled');
                    var btnText = langStrings.delete_n_items.replace('%d', count);
                    $btn.html('<i class="fa fa-trash"></i> ' + btnText);
                } else {
                    $btn.addClass('disabled').attr('disabled', 'disabled');
                    $btn.html('<i class="fa fa-trash"></i> ' + langStrings.delete_selected);
                }
            });

            $('body').on('click', '#ph-btn-bulk-delete', function(e) {
                e.preventDefault();
                var count = $('.ph-bulk-check:checked').length;
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
                var $target = $(e.target);

                // A. Delete Único
                var $deleteBtn = $target.closest('.js-delete-btn');
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

                // B. Abrir Modal Gerador
                var $trigger = $target.closest('.js-open-gen-modal');
                if ($trigger.length) {
                    e.preventDefault();
                    currentDropCode = $trigger.attr('data-dropcode');

                    $('#modeCard').prop('checked', true);
                    $inputLinkText.val('');
                    $inputBtnText.val('');
                    $inputBtnEmoji.val('');

                    updateGenerator();

                    var modalEl = document.getElementById('codeGenModal');
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

            // Copy to Clipboard com Feedback Visual
            $('body').on('click', '.js-copy-code', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.attr('data-clipboard-text');

                if (text && navigator.clipboard) {
                    // eslint-disable-next-line promise/always-return
                    navigator.clipboard.writeText(text).then(function() {
                        var originalHtml = $btn.html();
                        var originalWidth = $btn.outerWidth();

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
                        Notification.alert('Erro', 'Não foi possível copiar automaticamente.', 'OK');
                    });
                }
            });
        }
    };
});