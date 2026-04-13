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
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Manage story AMD module — chapter delete confirmation and teacher preview.
 *
 * @module     block_playerhud/manage_story
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var instanceid = 0;
    var courseid   = 0;

    var testModal    = null;
    var testContent  = null;
    var testChoices  = null;
    var bsTestModal  = null;

    /**
     * Open the test/preview modal.
     */
    function openTestModal() {
        if (!testModal) {
            return;
        }
        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
            if (!bsTestModal) {
                bsTestModal = new window.bootstrap.Modal(testModal);
            }
            bsTestModal.show();
        }
    }

    /**
     * Show a loading spinner in the test modal body.
     */
    function showTestLoader() {
        if (testContent) {
            testContent.innerHTML =
                '<div class="text-center text-muted py-5">' +
                '<i class="fa fa-circle-o-notch fa-spin fa-3x" aria-hidden="true"></i>' +
                '</div>';
        }
        if (testChoices) {
            testChoices.innerHTML = '';
        }
    }

    /**
     * Render a preview node in the test modal.
     *
     * @param {Object} data Web service response data.
     */
    function renderTestNode(data) {
        if (data.finished) {
            if (testContent) {
                testContent.innerHTML =
                    '<div class="text-center py-5">' +
                    '<i class="fa fa-check-circle fa-3x text-success" aria-hidden="true"></i>' +
                    '<h4 class="mt-3">' + (data.message || '') + '</h4>' +
                    '</div>';
            }
            if (testChoices) {
                testChoices.innerHTML =
                    '<button type="button" class="btn btn-secondary"' +
                    ' data-bs-dismiss="modal">Close</button>';
            }
            return;
        }

        if (!data.node) {
            return;
        }

        if (testContent) {
            testContent.innerHTML = data.node.content;
        }
        if (!testChoices) {
            return;
        }
        testChoices.innerHTML = '';

        if (data.node.choices && data.node.choices.length > 0) {
            data.node.choices.forEach(function(ch) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn ' + ch.btnclass + ' m-1 px-4 py-2';
                btn.innerHTML = ch.text;
                if (ch.disabled) {
                    btn.disabled = true;
                }
                btn.addEventListener('click', function() {
                    previewNav(ch.id);
                });
                testChoices.appendChild(btn);
            });
        } else {
            testChoices.innerHTML =
                '<button type="button" class="btn btn-secondary"' +
                ' data-bs-dismiss="modal">Close</button>';
        }
    }

    /**
     * Load the starting node of a chapter for preview.
     *
     * @param {number} chapterid Chapter ID.
     */
    function previewStart(chapterid) {
        showTestLoader();
        Ajax.call([{
            methodname: 'block_playerhud_load_scene',
            args: {
                instanceid: instanceid,
                courseid: courseid,
                chapterid: chapterid,
                preview: true,
            },
            done: function(data) {
                renderTestNode(data);
            },
            fail: function(ex) {
                if (testContent) {
                    testContent.innerHTML =
                        '<div class="alert alert-danger">' + (ex.message || 'Error') + '</div>';
                }
                Notification.exception(ex);
            },
        }]);
    }

    /**
     * Navigate to the next preview node.
     *
     * @param {number} choiceid Choice ID.
     */
    function previewNav(choiceid) {
        showTestLoader();
        Ajax.call([{
            methodname: 'block_playerhud_make_choice',
            args: {
                instanceid: instanceid,
                courseid: courseid,
                choiceid: choiceid,
                preview: true,
            },
            done: function(data) {
                renderTestNode(data);
            },
            fail: function(ex) {
                if (testContent) {
                    testContent.innerHTML =
                        '<div class="alert alert-danger">' + (ex.message || 'Error') + '</div>';
                }
                Notification.exception(ex);
            },
        }]);
    }

    /**
     * Wire up the delete confirmation modal for chapters.
     */
    function initChapterDelete() {
        var msgEl = document.getElementById('ph-delete-chapter-msg');
        var urlEl = document.getElementById('ph-delete-chapter-url');

        if (!msgEl || !urlEl) {
            return;
        }

        document.querySelectorAll('[data-action="delete-chapter"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                msgEl.textContent = btn.getAttribute('data-confirm-msg');
                urlEl.href = btn.getAttribute('data-delete-url');
            });
        });
    }

    return {
        /**
         * Initialise for the chapter management tab.
         *
         * @param {number} iid Block instance ID.
         * @param {number} cid Course ID.
         */
        init: function(iid, cid) {
            instanceid = iid;
            courseid   = cid;

            initChapterDelete();

            testModal   = document.getElementById('ph-story-test-modal');
            testContent = document.getElementById('ph-test-content');
            testChoices = document.getElementById('ph-test-choices');

            if (testModal) {
                testModal.addEventListener('hidden.bs.modal', function() {
                    bsTestModal = null;
                });
            }

            document.body.addEventListener('click', function(e) {
                var testBtn = e.target.closest('[data-action="test-chapter"]');
                if (testBtn) {
                    e.preventDefault();
                    var cid = parseInt(testBtn.getAttribute('data-chapterid'), 10);
                    openTestModal();
                    previewStart(cid);
                }
            });
        },

        /**
         * Initialise the scene delete confirmation on manage_scenes.php.
         */
        initSceneDelete: function() {
            var msgEl = document.getElementById('ph-delete-scene-msg');
            var urlEl = document.getElementById('ph-delete-scene-url');

            if (!msgEl || !urlEl) {
                return;
            }

            document.querySelectorAll('[data-action="delete-scene"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    msgEl.textContent = btn.getAttribute('data-confirm-msg');
                    urlEl.href = btn.getAttribute('data-delete-url');
                });
            });
        },
    };
});
