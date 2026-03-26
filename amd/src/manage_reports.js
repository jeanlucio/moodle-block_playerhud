/**
 * Reports management module.
 *
 * @module     block_playerhud/manage_reports
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(config) {
            // Seletor de usuário para redirecionamento.
            $('#r_userid').on('change', function() {
                var url = config.baseUrl + '&r_userid=' + $(this).val();
                window.location.href = url;
            });

            // Toggle para exibir/esconder logs antigos da IA.
            $('#btn-ai-toggle').on('click', function(e) {
                e.preventDefault();
                var rows = $('.ph-ai-hidden');
                if (!rows.length) {
                    return;
                }

                var isHidden = rows.first().is(':hidden');

                if (isHidden) {
                    rows.show();
                    $(this).html('<i class="fa fa-chevron-up me-1" aria-hidden="true"></i> ' + config.strLess);
                } else {
                    rows.hide();
                    $(this).html('<i class="fa fa-chevron-down me-1" aria-hidden="true"></i> ' + config.strMore);
                }
            });
        }
    };
});
