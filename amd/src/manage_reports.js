/**
 * Reports management module.
 *
 * @module     block_playerhud/manage_reports
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/notification'], function($, Notification) {
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

            // Confirmação padrão do Moodle para deletar itens.
            $('.js-delete-report-btn').on('click', function(e) {
                e.preventDefault();
                var targetUrl = $(this).attr('href');
                var msg = $(this).attr('data-confirm-msg');

                Notification.confirm(
                    config.strConfirmTitle,
                    msg,
                    config.strYes,
                    config.strCancel,
                    function() {
                        window.location.href = targetUrl;
                    }
                );
            });
        }
    };
});
