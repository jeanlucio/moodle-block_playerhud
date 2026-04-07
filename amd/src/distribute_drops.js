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

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

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
     *
     * @param {jQuery} $row
     */
    function updateFieldOptions($row) {
        var $moduleSelect = $row.find('.ph-select-module');
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
     * Mark a row as already inserted: disable its controls, check its checkbox.
     *
     * @param {jQuery} $row
     */
    function markRowInserted($row) {
        $row.addClass('ph-row-inserted');
        $row.find('.ph-select-module, .ph-select-field, .ph-select-position').prop('disabled', true);
        var $chk = $row.find('.ph-dist-check');
        $chk.prop('checked', true).prop('disabled', true);
        $row.find('.ph-dist-status')
            .html('<span class="badge bg-success"><i class="fa fa-check me-1" aria-hidden="true"></i>' +
                strings.inserted + '</span>');
    }

    /**
     * Mark a row as pending: enable its controls, uncheck its checkbox.
     *
     * @param {jQuery} $row
     */
    function markRowPending($row) {
        $row.removeClass('ph-row-inserted');
        $row.find('.ph-select-module, .ph-select-field, .ph-select-position').prop('disabled', false);
        var $chk = $row.find('.ph-dist-check');
        $chk.prop('checked', false).prop('disabled', false);
        $row.find('.ph-dist-status').empty();
    }

    /**
     * Evaluate whether this drop has already been distributed anywhere in the course.
     * Uses the server-side pre-computed flag, not the selected module.
     *
     * @param {jQuery} $row
     */
    function evaluateRow($row) {
        var insertedAnywhere = $row.data('insertedAnywhere') === 1 ||
            $row.data('insertedAnywhere') === '1';

        if (insertedAnywhere) {
            // Pre-select the actual field where the drop was found.
            var insertedField = $row.data('insertedField') || 'intro';
            $row.find('.ph-select-field').val(insertedField);
            markRowInserted($row);
        } else {
            markRowPending($row);
        }
    }

    /**
     * Add a cmid to the row's inserted list.
     *
     * @param {jQuery} $row
     * @param {number} cmid
     */
    function recordInserted($row, cmid) {
        var list = getInsertedCmids($row);
        if (list.indexOf(cmid) === -1) {
            list.push(cmid);
        }
        $row.attr('data-inserted-cmids', JSON.stringify(list));
    }

    /**
     * Update the bulk insert button label with the current selection count.
     */
    function updateBulkButton() {
        var count = $('.ph-dist-check:checked:not(:disabled)').length;
        var $btn = $('#ph-btn-bulk-insert');
        if (count > 0) {
            var label = strings.insert_selected.replace('__N__', count);
            $btn.removeClass('disabled').removeAttr('disabled')
                .html('<i class="fa fa-check me-1" aria-hidden="true"></i> ' + label);
        } else {
            $btn.addClass('disabled').attr('disabled', 'disabled')
                .html('<i class="fa fa-check me-1" aria-hidden="true"></i> ' + strings.btn_insert);
        }
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

            // Set initial field options and insertion state for every row.
            $('.ph-distribute-row').each(function() {
                var $row = $(this);
                updateFieldOptions($row);
                evaluateRow($row);
            });

            updateBulkButton();

            // Select All checkbox.
            $('#ph-dist-select-all').on('change', function() {
                var checked = $(this).is(':checked');
                $('.ph-dist-check:not(:disabled)').prop('checked', checked);
                updateBulkButton();
            });

            // Individual checkbox changes.
            $('body').on('change', '.ph-dist-check', function() {
                var total = $('.ph-dist-check:not(:disabled)').length;
                var checkedCount = $('.ph-dist-check:checked:not(:disabled)').length;
                $('#ph-dist-select-all').prop('indeterminate', checkedCount > 0 && checkedCount < total);
                $('#ph-dist-select-all').prop('checked', checkedCount === total && total > 0);
                updateBulkButton();
            });

            // Module select change: update field options only (state depends on drop, not on activity).
            $('body').on('change', '.ph-select-module', function() {
                var $row = $(this).closest('tr');
                updateFieldOptions($row);
            });

            // Bulk Insert button.
            $('#ph-btn-bulk-insert').on('click', function() {
                var $btn = $(this);
                if ($btn.hasClass('disabled')) {
                    return;
                }

                var $checked = $('.ph-dist-check:checked:not(:disabled)');
                if ($checked.length === 0) {
                    Notification.addNotification({message: strings.no_selection, type: 'warning'});
                    return;
                }

                $btn.addClass('disabled').attr('disabled', 'disabled')
                    .html('<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> ' +
                        strings.inserting);

                // Build one request per selected row and batch them in a single HTTP call.
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
                            recordInserted($row, cmid);
                            $row.attr('data-inserted-anywhere', '1');
                            markRowInserted($row);
                        } else {
                            Notification.addNotification({message: resp.message, type: 'error'});
                        }
                    }).fail(function(ex) {
                        Notification.exception(ex);
                    });
                });

                // Wait for all to settle then refresh the button state.
                $.when.apply($, calls).always(function() {
                    updateBulkButton();
                });
            });
        }
    };
});
