define(['jquery', 'core/notification'], function($, Notification) {

    return {
        init: function(config) {

            $('#phAiModal').appendTo('body');
            // 1. Delete Confirmation
            $('body').on('click', '.js-delete-btn', function(e) {
                e.preventDefault();
                var btn = $(this);
                var targetUrl = btn.attr('href');
                var msg = btn.attr('data-confirm-msg');

                Notification.confirm(
                    config.strings.confirm_title,
                    msg,
                    config.strings.yes,
                    config.strings.cancel,
                    function() {
                        window.location.href = targetUrl;
                    }
                );
            });

            // 2. AI Logic (Bootstrap Native)
            $('#ph-btn-conjure').click(function(e) {
                e.preventDefault();
                var btn = $(this);

                // Get values
                var theme = $('#ai-theme').val();
                var xp = $('#ai-xp').val();
                var createDrop = $('#ai-drop').is(':checked');

                if (!theme) {
                    Notification.alert('Error', config.strings.err_theme, 'OK');
                    return;
                }

                // Loading state
                var originalText = btn.text();
                btn.prop('disabled', true).text('...');

                // AJAX Call
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/playerhud/ajax_ai.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        instanceid: config.instanceid,
                        id: config.courseid,
                        theme: theme,
                        xp: xp ? xp : 0,
                        'create_drop': createDrop ? 1 : 0,
                        sesskey: M.cfg.sesskey
                    },
                    success: function(resp) {
                        btn.prop('disabled', false).text(originalText);

                        // Hide modal using jQuery/Bootstrap
                        $('#phAiModal').modal('hide');

                        if (resp.success) {
                            var rMsg = config.strings.success;
                            if (resp.drop_code) {
                                var dHtml = '<div class="mt-3 p-3 bg-light border rounded text-center">';
                                dHtml += '<h4 class="text-primary">' + resp.drop_code + '</h4>';
                                dHtml += '<p class="small text-muted mb-0">' + config.strings.copy + '</p>';
                                dHtml += '</div>';
                                rMsg += dHtml;
                            }

                            Notification.alert(
                                resp.item_name,
                                rMsg,
                                config.strings.great
                            ).then(function() {
                                window.location.reload();
                                return true;
                            }).catch(Notification.exception);
                        } else {
                            Notification.alert('Error', resp.message, 'OK');
                        }
                    },
                    error: function(xhr, status, error) {
                        btn.prop('disabled', false).text(originalText);
                        Notification.exception(error);
                    }
                });
            });
        }
    };
});
