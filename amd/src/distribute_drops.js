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

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], function($, Ajax, Notification, Str) {

    /**
     * Distribute Drops module for PlayerHUD.
     *
     * @module     block_playerhud/distribute_drops
     * @copyright  2026 Jean Lúcio
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */

    var strings = {};
    var cfg = {};

    /**
     * Parse the inserted cmids list from a row's data attribute.
     *
     * @param {jQuery} $row
     * @return {number[]}
     */
    function getInsertedCmids($row) {
        try {
            return JSON.parse($row.attr('data-inserted-cmids') || '[]');
        } catch (e) {
            return [];
        }
    }

    /**
     * Get the currently selected cmid from a row's module select.
     *
     * @param {jQuery} $row
     * @return {number}
     */
    function getSelectedCmid($row) {
        return parseInt($row.find('.ph-select-module').val(), 10);
    }

    /**
     * Update the field selector options based on the selected module.
     * Only applies to pending rows that still have the interactive selects.
     *
     * @param {jQuery} $row
     */
    function updateFieldOptions($row) {
        var $moduleSelect = $row.find('.ph-select-module');
        if ($moduleSelect.length === 0) {
            return;
        }
        var $selected = $('option:selected', $moduleSelect);
        var modname = $selected.data('modname');
        var supportsContent = $selected.data('supportsContent') === 1 ||
            $selected.data('supportsContent') === '1';
        var isLabel = $selected.data('isLabel') === 1 ||
            $selected.data('isLabel') === '1';

        var $fieldSelect = $row.find('.ph-select-field');
        $fieldSelect.empty();

        var introLabel = isLabel ? strings.field_label : strings.field_intro;
        $fieldSelect.append($('<option>', {value: 'intro', text: introLabel}));

        if (supportsContent || modname === 'page') {
            $fieldSelect.append($('<option>', {value: 'content', text: strings.field_content}));
        }
    }

    /**
     * Enforce initial checkbox state for a server-pre-rendered inserted row.
     * Cells already contain static text rendered server-side — do not touch them.
     *
     * @param {jQuery} $row
     */
    function initInsertedRow($row) {
        $row.addClass('ph-row-inserted');
        $row.find('.ph-dist-check').prop('checked', false);
    }

    /**
     * Mark a row as newly inserted during this session.
     * Replaces interactive selects with static text and updates the status badge.
     *
     * @param {jQuery} $row
     */
    function markRowInserted($row) {
        $row.addClass('ph-row-inserted');

        var $modSelect = $row.find('.ph-select-module');
        var activityName = $modSelect.find('option:selected').data('name') ||
            $modSelect.find('option:selected').text().trim();
        var fieldVal = $row.find('.ph-select-field').val();
        var fieldLabel = (fieldVal === 'content') ? strings.field_content : strings.field_intro;

        // Replace Activity cell with static text.
        $modSelect.closest('td').html(
            '<span class="fw-semibold">' + $('<span>').text(activityName).html() + '</span>'
        );

        // Replace Field cell with static text.
        $row.find('.ph-select-field').closest('td').html(
            '<span>' + $('<span>').text(fieldLabel).html() + '</span>'
        );

        // Replace Position cell with dash.
        $row.find('.ph-select-position').closest('td').html(
            '<span class="text-muted">\u2014</span>'
        );

        // Update checkbox data-inserted so bulk buttons count it correctly.
        $row.find('.ph-dist-check').data('inserted', '1').attr('data-inserted', '1').prop('checked', false);

        // Update status badge.
        $row.find('.ph-dist-status')
            .html('<span class="badge bg-success"><i class="fa fa-check me-1" aria-hidden="true"></i>' +
                strings.inserted + '</span>');
    }

    /**
     * Update both action buttons based on current checkbox state.
     * Insert button counts pending rows checked; Remove button counts inserted rows checked.
     */
    function updateActionButtons() {
        var insertCount = $('.ph-dist-check:checked[data-inserted="0"]').length;
        var removeCount = $('.ph-dist-check:checked[data-inserted="1"]').length;

        var $insertBtn = $('#ph-btn-bulk-insert');
        if (insertCount > 0) {
            var insertLabel = strings.insert_selected.replace('__N__', insertCount);
            $insertBtn.removeClass('disabled').removeAttr('disabled')
                .html('<i class="fa fa-check me-1" aria-hidden="true"></i> ' + insertLabel);
        } else {
            $insertBtn.addClass('disabled').attr('disabled', 'disabled')
                .html('<i class="fa fa-check me-1" aria-hidden="true"></i> ' + strings.btn_insert);
        }

        var $removeBtn = $('#ph-btn-bulk-remove');
        if (removeCount > 0) {
            var removeLabel = strings.undo_selected.replace('__N__', removeCount);
            $removeBtn.removeClass('disabled').removeAttr('disabled')
                .html('<i class="fa fa-undo me-1" aria-hidden="true"></i> ' + removeLabel);
        } else {
            $removeBtn.addClass('disabled').attr('disabled', 'disabled')
                .html('<i class="fa fa-undo me-1" aria-hidden="true"></i> ' + strings.remove);
        }
    }

    /**
     * Process the bulk removal of inserted drop shortcodes.
     * Extracted to prevent max-nested-callbacks ESLint warning.
     *
     * @param {jQuery} $btn The clicked button.
     * @param {jQuery} $checked The selected checkboxes.
     */
    function processBulkRemove($btn, $checked) {
        $btn.addClass('disabled').attr('disabled', 'disabled')
            .html('<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> ' + strings.removing);

        var requests = [];

        $checked.each(function() {
            var $row = $(this).closest('tr');
            var cmids = getInsertedCmids($row);
            var cmid = cmids.length > 0 ? cmids[0] : 0;
            var field = $row.attr('data-inserted-field') || 'intro';

            requests.push({
                methodname: 'block_playerhud_remove_drop_shortcode',
                args: {
                    instanceid: cfg.instanceid,
                    courseid: cfg.courseid,
                    dropid: parseInt($row.data('dropId'), 10),
                    cmid: cmid,
                    field: field
                }
            });
        });

        var calls = Ajax.call(requests);
        var allOk = true;

        calls.forEach(function(promise) {
            promise.fail(function(ex) {
                allOk = false;
                Notification.exception(ex);
            });
        });

        $.when.apply($, calls).always(function() {
            if (allOk) {
                window.location.reload();
            } else {
                updateActionButtons();
            }
        });
    }

    return {
        /**
         * Initialise the distribute drops page.
         *
         * @param {Object} config Configuration passed from PHP.
         */
        init: function(config) {
            strings = config.strings;
            cfg = config;

            // Initialise field options for pending rows; set class for inserted rows.
            $('.ph-distribute-row').each(function() {
                var $row = $(this);
                var isInserted = $row.data('insertedAnywhere') === 1 ||
                    $row.data('insertedAnywhere') === '1';
                if (isInserted) {
                    initInsertedRow($row);
                } else {
                    updateFieldOptions($row);
                }
            });

            updateActionButtons();

            // Select All checkbox.
            $('#ph-dist-select-all').on('change', function() {
                var checked = $(this).is(':checked');
                $('.ph-dist-check').prop('checked', checked);
                updateActionButtons();
            });

            // Individual checkbox changes.
            $('body').on('change', '.ph-dist-check', function() {
                var total = $('.ph-dist-check').length;
                var checkedCount = $('.ph-dist-check:checked').length;
                $('#ph-dist-select-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
                $('#ph-dist-select-all').prop('checked', checkedCount === total && total > 0);
                updateActionButtons();
            });

            // Module select change: update field options for pending rows.
            $('body').on('change', '.ph-select-module', function() {
                updateFieldOptions($(this).closest('tr'));
            });

            // Bulk Insert button.
            $('#ph-btn-bulk-insert').on('click', function() {
                var $btn = $(this);
                if ($btn.hasClass('disabled')) {
                    return;
                }

                var $checked = $('.ph-dist-check:checked[data-inserted="0"]');
                if ($checked.length === 0) {
                    Notification.addNotification({message: strings.no_selection, type: 'warning'});
                    return;
                }

                $btn.addClass('disabled').attr('disabled', 'disabled')
                    .html('<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> ' +
                        strings.inserting);

                var requests = [];
                var $rows = [];

                $checked.each(function() {
                    var $row = $(this).closest('tr');
                    $rows.push($row);
                    requests.push({
                        methodname: 'block_playerhud_insert_drop_shortcode',
                        args: {
                            instanceid: cfg.instanceid,
                            courseid: cfg.courseid,
                            dropid: parseInt($row.data('dropId'), 10),
                            cmid: getSelectedCmid($row),
                            field: $row.find('.ph-select-field').val(),
                            position: $row.find('.ph-select-position').val()
                        }
                    });
                });

                var calls = Ajax.call(requests);

                calls.forEach(function(promise, index) {
                    var $row = $rows[index];
                    var cmid = getSelectedCmid($row);

                    promise.done(function(resp) {
                        if (resp.success) {
                            var list = getInsertedCmids($row);
                            if (list.indexOf(cmid) === -1) {
                                list.push(cmid);
                            }
                            $row.attr('data-inserted-cmids', JSON.stringify(list));
                            $row.attr('data-inserted-anywhere', '1');
                            markRowInserted($row);
                        } else {
                            Notification.addNotification({message: resp.message, type: 'error'});
                        }
                    }).fail(function(ex) {
                        Notification.exception(ex);
                    });
                });

                $.when.apply($, calls).always(function() {
                    updateActionButtons();
                });
            });

            // Bulk Remove button.
            $('#ph-btn-bulk-remove').on('click', function() {
                var $btn = $(this);
                if ($btn.hasClass('disabled')) {
                    return;
                }

                var $checked = $('.ph-dist-check:checked[data-inserted="1"]');
                if ($checked.length === 0) {
                    return;
                }

                Str.get_strings([
                    {key: 'confirm', component: 'core'},
                    {key: 'yes', component: 'core'},
                    {key: 'no', component: 'core'}
                ]).then(function(strs) {
                    Notification.confirm(
                        strs[0],
                        strings.remove_confirm,
                        strs[1],
                        strs[2],
                        function() {
                            processBulkRemove($btn, $checked);
                        }
                    );
                    return;
                }).catch(function(ex) {
                    Notification.exception(ex);
                });
            });
        }
    };
});
