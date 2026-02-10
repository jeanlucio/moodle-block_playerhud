/* global bootstrap */
// Importando core/copy_to_clipboard para que os botões com data-action funcionem automaticamente
define(['jquery', 'core/notification', 'core/copy_to_clipboard'], function($, Notification) {

    /**
     * Manage Drops module.
     *
     * @module     block_playerhud/manage_drops
     * @copyright  2026 Jean Lúcio
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    return {
        /**
         * Initialize the module.
         *
         * @param {Object} config The configuration object passed from PHP.
         */
        init: function(config) {
            var currentItem = config.item;
            var langStrings = config.strings;
            var currentDropCode = 0;

            // Elementos DOM (Cacheados como jQuery objects para consistência).
            var $inputCode = $('#finalCode');
            var $previewBox = $('#previewContainer');
            var $groupTextLink = $('#textInputGroup');
            var $inputLinkText = $('#customText');
            var $groupCardOptions = $('#cardCustomOptions');
            var $inputBtnText = $('#customBtnText');
            var $inputBtnEmoji = $('#customBtnEmoji');

            // Move modal para o body.
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

                    // MUDANÇA: Classes Bootstrap em vez de style inline
                    previewHtml = '<a href="#" onclick="return false;" ' +
                        'class="text-primary fw-bold text-decoration-underline">' + linkTxt + '</a>';

                } else if (mode === 'image') {
                    $groupTextLink.hide();
                    $groupCardOptions.hide();

                    code = '[PLAYERHUD_DROP ' + param + ' mode=image]';

                    // MUDANÇA: Uso das novas classes CSS .ph-gen-*
                    var imgContent = currentItem.isImage ?
                        '<img src="' + currentItem.url + '" class="ph-gen-img-lg" alt="">' :
                        '<span class="ph-gen-emoji-lg" aria-hidden="true">' + currentItem.content + '</span>';

                    // Wrapper com classe
                    previewHtml = '<div class="ph-gen-preview-wrapper-img">' +
                        imgContent + '</div>';

                } else {
                    // Mode Card
                    $groupTextLink.hide();
                    $groupCardOptions.show();

                    // ... (lógica de texto/emoji do botão permanece igual) ...
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

                    // HTML do Ícone no Card (Também limpo de estilos inline complexos)
                    // Note que usamos classes utilitárias ou herdamos o estilo do card
                    // MUDANÇA: Substituímos style="..." pela classe .ph-icon-contain criada no CSS
                    var iconHtml = currentItem.isImage ?
                        '<img src="' + currentItem.url + '" class="ph-icon-contain" alt="">' :
                        '<div style="font-size:2.5em; line-height:1;">' + currentItem.content + '</div>';
                    // Nota: Mantive um style mínimo acima apenas para garantir o fit dentro do container flex de 60px do card,
                    // eslint-disable-next-line max-len
                    // pois criar classes para cada div interna pode ser excessivo, mas o ideal seria .ph-card-icon-container img {} no CSS.

                    var btnContent = previewTxt;
                    if (previewEmo) {
                        btnContent = '<span aria-hidden="true" class="me-1">' + previewEmo + '</span> ' + previewTxt;
                    }

                    // MUDANÇA: Removido style="width: 160px..." e adicionada classe ph-gen-preview-real-card
                    previewHtml = '<div class="ph-gen-preview-real-card card p-2 border shadow-sm">' +
                        '<span class="badge bg-info text-dark rounded-pill position-absolute" ' +
                        'style="top:5px; right:5px; font-size:0.7rem;">' + langStrings.yours + '</span>' +
                        '<div class="mb-2 d-flex align-items-center justify-content-center" style="height:60px;">' +
                        iconHtml + '</div>' +
                        '<strong class="d-block mb-2 text-truncate" style="font-size:0.9rem;">' +
                        currentItem.name + '</strong>' +
                        '<button class="btn btn-primary btn-sm w-100 shadow-sm">' + btnContent + '</button>' +
                        '</div>';
                }

                $inputCode.val(code);
                $previewBox.html(previewHtml);
            };

            /**
             * Handler para mudanças nos inputs do gerador.
             *
             * @param {Event} e Evento.
             */
            var handleChange = function(e) {
                var $target = $(e.target);
                if ($target.hasClass('js-mode-trigger') ||
                    $target.is('#customText') ||
                    $target.is('#customBtnText') ||
                    $target.is('#customBtnEmoji')) {
                    updateGenerator();
                }
            };

            // --- 1. LÓGICA DE AÇÕES EM MASSA ---

            // "Selecionar Todos"
            $('#ph-select-all').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.ph-bulk-check').prop('checked', isChecked).trigger('change');
            });

            // Habilitar/Desabilitar botão
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

            // Confirmação Bulk Delete (Com Delegação de Evento)
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

            // --- 2. LISTENERS GERAIS ---

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

                    // Reset Inputs
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

            // Listeners de Input
            $('body').on('change input', handleChange);
// --- 3. COPY TO CLIPBOARD MANUAL (Fallback robusto) ---
            $('body').on('click', '.js-copy-code', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var text = $btn.attr('data-clipboard-text');

                if (text && navigator.clipboard) {
                    // eslint-disable-next-line promise/always-return
                    navigator.clipboard.writeText(text).then(function() {
                        // Feedback Visual
                        var originalHtml = $btn.html();
                        var originalWidth = $btn.outerWidth(); // Fixa largura para não pular

                        // Estado de Sucesso
                        $btn.css('width', originalWidth + 'px');
                        $btn.removeClass('btn-outline-secondary').addClass('btn-success');
                        $btn.html('<i class="fa fa-check"></i> ' + langStrings.gen_copied);

                        // Restaura após 2 segundos
                        setTimeout(function() {
                            $btn.html(originalHtml);
                            $btn.removeClass('btn-success').addClass('btn-outline-secondary');
                            $btn.css('width', '');
                        }, 2000);

                    }).catch(function(err) {
                        // Em caso de erro (ex: permissão), loga no console mas não quebra a UI
                        // eslint-disable-next-line no-console
                        console.error('Clipboard error:', err);
                        Notification.alert('Erro', 'Não foi possível copiar automaticamente.', 'OK');
                    });
                }
            });
        }
    };
});
