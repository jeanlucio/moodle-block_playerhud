define(['jquery', 'core/notification'], function($) {

    return {
        init: function(config) {
            // Configurações passadas pelo PHP
            var currentItem = config.item;
            var langStrings = config.strings;
            var currentDropCode = 0;

            // Elementos do DOM
            var inputCode = document.getElementById('finalCode');
            var inputText = document.getElementById('customText');
            var textGroup = document.getElementById('textInputGroup');
            var previewBox = document.getElementById('previewContainer');
            var copyFeedback = document.getElementById('copyFeedback');

            // --- Listener Global (Event Delegation) ---
            document.body.addEventListener('click', function(e) {

                // A. Abrir Modal
                var trigger = e.target.closest('.js-open-gen-modal');
                if (trigger) {
                    e.preventDefault();
                    currentDropCode = trigger.getAttribute('data-dropcode');

                    // Reset do formulário
                    var radioCard = document.getElementById('modeCard');
                    if (radioCard) {
                        radioCard.checked = true;
                    }

                    if (inputText) {
                        inputText.value = langStrings.defaultText;
                        textGroup.style.display = 'none';
                    }

                    updateGenerator();

                    // Abrir Modal (Bootstrap 5 Nativo)
                    var el = document.getElementById('codeGenModal');
                    if (el) {
                        // Verifica se o bootstrap está disponível globalmente ou via AMD shim
                        // Fallback seguro para Moodle 4.x
                        // eslint-disable-next-line no-undef
                        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            // eslint-disable-next-line no-undef
                            var modal = bootstrap.Modal.getOrCreateInstance(el);
                            modal.show();
                        } else {
                            // Fallback jQuery (se o tema não expuser o bootstrap global)
                            $(el).modal('show');
                        }
                    }
                }

                // B. Botão Copiar
                if (e.target.closest('#copyFinalCode')) {
                    if (inputCode) {
                        inputCode.select();
                        inputCode.setSelectionRange(0, 99999);
                        document.execCommand('copy');

                        if (copyFeedback) {
                            copyFeedback.style.display = 'inline-block';
                            setTimeout(function() {
                                copyFeedback.style.display = 'none';
                            }, 3000);
                        }
                    }
                }
            });

            // --- Listener de Mudanças (Radio Buttons e Input) ---
            document.body.addEventListener('change', function(e) {
                if (e.target.classList.contains('js-mode-trigger')) {
                    updateGenerator();
                }
            });

            if (inputText) {
                inputText.addEventListener('input', updateGenerator);
                inputText.addEventListener('focus', function() {
                    if (this.value === langStrings.defaultText) {
                        this.value = '';
                        updateGenerator();
                    }
                });
            }

            // --- Função Principal de Geração ---
            /**
             *
             */
            function updateGenerator() {
                var modeRadio = document.querySelector('input[name="codeMode"]:checked');
                var mode = modeRadio ? modeRadio.value : 'card';

                // Lógica Híbrida: ID ou Hash
                // Se não for número (ex: 3C815F), usa 'code='. Se for número (15), usa 'id='.
                var param = isNaN(currentDropCode) ? 'code=' + currentDropCode : 'id=' + currentDropCode;

                var code = '[PLAYERHUD_DROP ' + param + ']';
                var previewHtml = '';

                if (mode === 'text') {
                    if (textGroup) {
                        textGroup.style.display = 'block';
                    }

                    var displayTxt = inputText.value.trim();
                    if (!displayTxt) {
                        displayTxt = langStrings.defaultText;
                    }

                    var codeTxt = inputText.value.trim() || langStrings.defaultText;

                    code = '[PLAYERHUD_DROP ' + param + ' mode=text text="' + codeTxt + '"]';

                    // eslint-disable-next-line max-len
                    previewHtml = '<a href="#" onclick="return false;" class="text-primary" style="text-decoration:underline;">' + displayTxt + '</a>';

                } else if (mode === 'image') {
                    if (textGroup) {
                        textGroup.style.display = 'none';
                    }
                    code = '[PLAYERHUD_DROP ' + param + ' mode=image]';

                    var iconHtml = currentItem.isImage
                        ? '<img src="' + currentItem.url + '" style="width:50px; height:50px; object-fit:contain;" alt="">'
                        : '<span style="font-size:40px;" aria-hidden="true">' + currentItem.content + '</span>';

                    // eslint-disable-next-line max-len
                    previewHtml = '<div style="cursor:pointer; filter: drop-shadow(0 4px 2px rgba(0,0,0,0.1)); text-align:center;">' + iconHtml + '</div>';

                } else {
                    // Modo Card (Padrão)
                    if (textGroup) {
                        textGroup.style.display = 'none';
                    }

                    var iconHtmlCard = currentItem.isImage
                        ? '<img src="' + currentItem.url + '" style="width:40px; height:40px; object-fit:contain;" alt="">'
                        : '<span style="font-size:30px;" aria-hidden="true">' + currentItem.content + '</span>';

                    previewHtml =
                    // eslint-disable-next-line max-len
                    '<div style="padding: 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 15px; border: 2px dashed #0f6cbf; background: #f8f9fa;">' +
                        '<div>' + iconHtmlCard + '</div>' +
                        '<div class="text-start">' +
                            '<strong>' + currentItem.name + '</strong> ' +
                            '<span class="badge bg-info rounded-pill text-dark">' + langStrings.yours + '</span><br>' +
                            '<small class="text-muted">' + currentItem.xp + '</small>' +
                        '</div>' +
                        '<button class="btn btn-primary btn-sm ms-3">' + langStrings.takeBtn + '</button>' +
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