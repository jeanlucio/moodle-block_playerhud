/* global bootstrap */
define(['jquery', 'core/notification'], function($, Notification) {

    return {
        init: function(config) {
            $('#codeGenModal').appendTo('body');

            var currentItem = config.item;
            var langStrings = config.strings;
            var currentDropCode = 0;

            // Elementos
            var inputCode = document.getElementById('finalCode');
            // Var inputText = document.getElementById('customText'); // Removido pois é redeclarado abaixo como inputLinkText
            var previewBox = document.getElementById('previewContainer');

            // Inputs de Personalização
            var groupTextLink = document.getElementById('textInputGroup'); // Grupo do modo Texto
            var inputLinkText = document.getElementById('customText');// Input do modo Texto

            var groupCardOptions = document.getElementById('cardCustomOptions'); // Grupo do modo Card (NOVO)
            var inputBtnText = document.getElementById('customBtnText');// Input Texto Botão
            var inputBtnEmoji = document.getElementById('customBtnEmoji');// Input Emoji Botão

            // --- Listeners ---
            document.body.addEventListener('click', function(e) {
                // Delete
                var deleteBtn = e.target.closest('.js-delete-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    Notification.confirm(
                        langStrings.confirm_title,
                        deleteBtn.getAttribute('data-confirm-msg'),
                        langStrings.yes,
                        langStrings.cancel,
                        function() {
                            window.location.href = deleteBtn.getAttribute('href');
                        }
                    );
                    return;
                }

                // Open Modal
                var trigger = e.target.closest('.js-open-gen-modal');
                if (trigger) {
                    e.preventDefault();
                    currentDropCode = trigger.getAttribute('data-dropcode');

                    // Reset inputs
                    var radioCard = document.getElementById('modeCard');
                    if (radioCard) {
                        radioCard.checked = true;
                    }

                    if (inputLinkText) {
                        inputLinkText.value = langStrings.defaultText;
                    }
                    if (inputBtnText) {
                        inputBtnText.value = langStrings.takeBtn;
                    }
                    if (inputBtnEmoji) {
                        inputBtnEmoji.value = '';
                    }

                    updateGenerator();

                    var el = document.getElementById('codeGenModal');
                    if (el) {
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            bootstrap.Modal.getOrCreateInstance(el).show();
                        } else {
                            $(el).modal('show');
                        }
                    }
                }

                // Copy
                if (e.target.closest('#copyFinalCode')) {
                    if (inputCode) {
                        inputCode.select();
                        inputCode.setSelectionRange(0, 99999);
                        document.execCommand('copy');
                        var fb = document.getElementById('copyFeedback');
                        if (fb) {
                            fb.style.display = 'inline-block';
                            setTimeout(function() {
                                fb.style.display = 'none';
                            }, 3000);
                        }
                    }
                }
            });

            // Listeners de Input para atualização em tempo real
            document.body.addEventListener('change', handleChange);
            document.body.addEventListener('input', handleChange);

            /**
             * Handles input changes to update preview.
             *
             * @param {Event} e The event object.
             */
            function handleChange(e) {
                if (e.target.classList.contains('js-mode-trigger') ||
                    e.target.id === 'customText' ||
                    e.target.id === 'customBtnText' ||
                    e.target.id === 'customBtnEmoji') {
                    updateGenerator();
                }
            }

            /**
             * Updates the generator preview and code.
             */
            // eslint-disable-next-line complexity
            function updateGenerator() {
                var modeRadio = document.querySelector('input[name="codeMode"]:checked');
                var mode = modeRadio ? modeRadio.value : 'card';

                var param = isNaN(currentDropCode) ? 'code=' + currentDropCode : 'id=' + currentDropCode;
                var code = '[PLAYERHUD_DROP ' + param + ']'; // Base code
                var previewHtml = '';

                // Visibilidade dos grupos de input
                if (groupTextLink) {
                    groupTextLink.style.display = (mode === 'text') ? 'block' : 'none';
                }
                if (groupCardOptions) {
                    groupCardOptions.style.display = (mode === 'card') ? 'block' : 'none';
                }

                // --- MODO TEXTO ---
                if (mode === 'text') {
                    var linkTxt = inputLinkText.value.trim() || langStrings.defaultText;
                    code = '[PLAYERHUD_DROP ' + param + ' mode=text text="' + linkTxt + '"]';
                    previewHtml = '<a href="#" onclick="return false;" class="text-primary fw-bold text-decoration-underline">' +
                        linkTxt + '</a>';
                    // eslint-disable-next-line brace-style
                    }
                // --- MODO IMAGEM ---
                else if (mode === 'image') {
                    code = '[PLAYERHUD_DROP ' + param + ' mode=image]';

                    var imgContent = currentItem.isImage
                        ? '<img src="' + currentItem.url + '" style="width:50px; height:50px; object-fit:contain;" alt="">'
                        : '<span style="font-size:40px;" aria-hidden="true">' + currentItem.content + '</span>';

                    previewHtml = '<div style="cursor:pointer; filter: drop-shadow(0 4px 2px rgba(0,0,0,0.1));">' +
                        imgContent + '</div>';
                // eslint-disable-next-line brace-style
                }
                // --- MODO CARD (Padrão) ---
                else {
                    // Captura personalizações
                    var btnTxt = (inputBtnText && inputBtnText.value.trim()) ? inputBtnText.value.trim() : langStrings.takeBtn;
                    var btnEmo = (inputBtnEmoji && inputBtnEmoji.value.trim()) ? inputBtnEmoji.value.trim() : '';

                    // Constrói shortcode com atributos opcionais
                    var extraAttrs = '';
                    if (btnTxt !== langStrings.takeBtn) {
                        extraAttrs += ' button_text="' + btnTxt + '"';
                    }
                    if (btnEmo !== '') {
                        extraAttrs += ' button_emoji="' + btnEmo + '"';
                    }

                    code = '[PLAYERHUD_DROP ' + param + extraAttrs + ']';

                    // Constrói Prévia Fiel (Usando a estrutura do Filtro e a nova classe CSS)
                    var iconHtml = currentItem.isImage
                        ? '<img src="' + currentItem.url + '" alt="">' // CSS controla tamanho
                        : '<div style="font-size:2.5em; line-height:1;">' + currentItem.content + '</div>';

                    // HTML do Botão
                    var btnContent = btnTxt;
                    if (btnEmo) {
                        btnContent = '<span aria-hidden="true" class="me-1">' + btnEmo + '</span> ' + btnTxt;
                    }

                    previewHtml =
                    '<div class="ph-gen-preview-real-card">' +
                        '<span class="badge bg-info text-dark rounded-pill position-absolute" ' +
                        'style="top:5px; right:5px; font-size:0.7rem;">' + langStrings.yours + '</span>' +
                        '<div class="mb-2 d-flex align-items-center justify-content-center" style="height:60px;">' +
                            iconHtml +
                        '</div>' +
                        '<strong class="d-block mb-2 text-truncate" style="font-size:0.9rem;">' + currentItem.name + '</strong>' +
                        '<button class="btn btn-primary btn-sm w-100 shadow-sm">' + btnContent + '</button>' +
                    '</div>';
                }

                if (inputCode) {
                    inputCode.value = code;
                }
                if (previewBox) {
                    previewBox.innerHTML = previewHtml;
                }
            }
        }
    };
});
