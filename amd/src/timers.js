/**
 * Timer logic for PlayerHUD Block.
 *
 * @module     block_playerhud/timers
 * @copyright  2026 Jean Lúcio
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(strings) {
            // Evita rodar múltiplos intervalos se o init for chamado várias vezes
            if (window.phTimerInterval) {
                return;
            }

            // Função de atualização
            var updateTimers = function() {
                var now = Math.floor(Date.now() / 1000);
                var reloadNeeded = false;

                $('.ph-timer').each(function() {
                    var el = $(this);
                    // Se já estiver marcado para recarregar, ignora
                    if (el.data('reloading')) {
                        return;
                    }

                    var deadline = parseInt(el.attr('data-deadline'));
                    if (isNaN(deadline)) {
                        return;
                    }

                    var diff = deadline - now;

                    if (diff <= 0) {
                        // --- TEMPO ACABOU ---
                        el.text(strings.ready);
                        el.removeClass('text-muted').addClass('text-success fw-bold');

                        // Marca o elemento para não processar de novo
                        el.data('reloading', true);
                        reloadNeeded = true;

                    } else {
                        // --- CONTANDO ---
                        var m = Math.floor(diff / 60);
                        var s = diff % 60;
                        var timeString = m + 'm ' + (s < 10 ? '0' : '') + s + 's';

                        var label = strings.label ? strings.label + ' ' : '';
                        el.text(label + timeString);
                    }
                });

                // Se algum timer acabou, agenda o reload da página
                if (reloadNeeded) {
                    setTimeout(function() {
                        location.reload();
                    }, 1500); // Espera 1.5 segundos antes de recarregar
                }
            };

            // Inicia o loop (1 segundo)
            window.phTimerInterval = setInterval(updateTimers, 1000);
            // Roda imediatamente
            updateTimers();
        }
    };
});