// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

define(['jquery', 'core/ajax', 'core/notification', 'core/str'], ($, Ajax, Notification, Str) => {

    /**
     * Distribute Drops module for PlayerHUD.
     *
     * @module     block_playerhud/distribute_drops
     * @copyright  2026 Jean Lúcio
     * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */

    let strings = {};
    let cfg = {};

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
        const $moduleSelect = $row.find('.ph-select-module');
        if ($moduleSelect.length === 0) {
            return;
        }
        const $selected = $('option:selected', $moduleSelect);
        const modname = $selected.data('modname');
        const supportsContent = $selected.data('supportsContent') === 1 ||
            $selected.data('supportsContent') === '1';
        const isLabel = $selected.data('isLabel') === 1 ||
            $selected.data('isLabel') === '1';

        const $fieldSelect = $row.find('.ph-select-field');
        $fieldSelect.empty();

        const introLabel = isLabel ? strings.field_label : strings.field_intro;
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

        const $modSelect = $row.find('.ph-select-module');
        const activityName = $modSelect.find('option:selected').data('name') ||
            $modSelect.find('option:selected').text().trim();
        const fieldVal = $row.find('.ph-select-field').val();
        const fieldLabel = (fieldVal === 'content') ? strings.field_content : strings.field_intro;

        // Replace Activity cell with static text.
        $modSelect.closest('td').html(
            `<span class="fw-semibold">${$('<span>').text(activityName).html()}</span>`
        );

        // Replace Field cell with static text.
        $row.find('.ph-select-field').closest('td').html(
            `<span>${$('<span>').text(fieldLabel).html()}</span>`
        );

        // Replace Position cell with dash.
        $row.find('.ph-select-position').closest('td').html(
            '<span class="text-muted">\u2014</span>'
        );

        // Update checkbox data-inserted so bulk buttons count it correctly.
        $row.find('.ph-dist-check').data('inserted', '1').attr('data-inserted', '1').prop('checked', false);

        // Update status badge.
        $row.find('.ph-dist-status')
            .html('<span class="badge bg-success text-white"><i class="fa fa-check me-1" aria-hidden="true"></i>' +
                `${strings.inserted}</span>`);
    }

    /**
     * Update both action buttons based on current checkbox state.
     * Insert button counts pending rows checked; Remove button counts inserted rows checked.
     */
    function updateActionButtons() {
        const insertCount = $('.ph-dist-check:checked[data-inserted="0"]').length;
        const removeCount = $('.ph-dist-check:checked[data-inserted="1"]').length;

        const $insertBtn = $('#ph-btn-bulk-insert');
        if (insertCount > 0) {
            const insertLabel = strings.insert_selected.replace('__N__', insertCount);
            $insertBtn.removeClass('disabled').removeAttr('disabled')
                .html(`<i class="fa fa-check me-1" aria-hidden="true"></i> ${insertLabel}`);
        } else {
            $insertBtn.addClass('disabled').attr('disabled', 'disabled')
                .html(`<i class="fa fa-check me-1" aria-hidden="true"></i> ${strings.btn_insert}`);
        }

        const $removeBtn = $('#ph-btn-bulk-remove');
        if (removeCount > 0) {
            const removeLabel = strings.undo_selected.replace('__N__', removeCount);
            $removeBtn.removeClass('disabled').removeAttr('disabled')
                .html(`<i class="fa fa-undo me-1" aria-hidden="true"></i> ${removeLabel}`);
        } else {
            $removeBtn.addClass('disabled').attr('disabled', 'disabled')
                .html(`<i class="fa fa-undo me-1" aria-hidden="true"></i> ${strings.remove}`);
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
            .html(`<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> ${strings.removing}`);

        const requests = [];

        $checked.each(function() {
            const $row = $(this).closest('tr');
            const cmids = getInsertedCmids($row);
            const cmid = cmids.length > 0 ? cmids[0] : 0;
            const field = $row.attr('data-inserted-field') || 'intro';

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

        const calls = Ajax.call(requests);
        let allOk = true;

        calls.forEach((promise) => {
            promise.fail((ex) => {
                allOk = false;
                Notification.exception(ex);
            });
        });

        $.when.apply($, calls).always(() => {
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
                const $row = $(this);
                const isInserted = $row.data('insertedAnywhere') === 1 ||
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
                const checked = $(this).is(':checked');
                $('.ph-dist-check').prop('checked', checked);
                updateActionButtons();
            });

            // Individual checkbox changes.
            $('body').on('change', '.ph-dist-check', () => {
                const total = $('.ph-dist-check').length;
                const checkedCount = $('.ph-dist-check:checked').length;
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
                const $btn = $(this);
                if ($btn.hasClass('disabled')) {
                    return;
                }

                const $checked = $('.ph-dist-check:checked[data-inserted="0"]');
                if ($checked.length === 0) {
                    Notification.addNotification({message: strings.no_selection, type: 'warning'});
                    return;
                }

                $btn.addClass('disabled').attr('disabled', 'disabled')
                    .html(`<i class="fa fa-spinner fa-spin me-1" aria-hidden="true"></i> ${strings.inserting}`);

                const requests = [];
                const $rows = [];

                $checked.each(function() {
                    const $row = $(this).closest('tr');
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

                const calls = Ajax.call(requests);

                calls.forEach((promise, index) => {
                    const $row = $rows[index];
                    const cmid = getSelectedCmid($row);

                    promise.done((resp) => {
                        if (resp.success) {
                            const list = getInsertedCmids($row);
                            if (list.indexOf(cmid) === -1) {
                                list.push(cmid);
                            }
                            $row.attr('data-inserted-cmids', JSON.stringify(list));
                            $row.attr('data-inserted-anywhere', '1');
                            markRowInserted($row);
                        } else {
                            Notification.addNotification({message: resp.message, type: 'error'});
                        }
                    }).fail((ex) => {
                        Notification.exception(ex);
                    });
                });

                $.when.apply($, calls).always(() => {
                    updateActionButtons();
                });
            });

            // Bulk Remove button.
            $('#ph-btn-bulk-remove').on('click', async function() {
                const $btn = $(this);
                if ($btn.hasClass('disabled')) {
                    return;
                }

                const $checked = $('.ph-dist-check:checked[data-inserted="1"]');
                if ($checked.length === 0) {
                    return;
                }

                try {
                    const [title, yes, no] = await Str.get_strings([
                        {key: 'confirm', component: 'core'},
                        {key: 'yes', component: 'core'},
                        {key: 'no', component: 'core'}
                    ]);
                    Notification.confirm(title, strings.remove_confirm, yes, no, () => {
                        processBulkRemove($btn, $checked);
                    });
                } catch (ex) {
                    Notification.exception(ex);
                }
            });
        }
    };
});
