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
 * Story player AMD module — handles the chapter reading modal for students.
 *
 * @module     block_playerhud/story_player
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var instanceid = 0;
    var courseid = 0;
    var strings = {};

    var modal = null;
    var contentEl = null;
    var choicesEl = null;
    var titleEl = null;
    var bsModal = null;

    /**
     * Show the loading spinner inside the modal body.
     */
    function showLoader() {
        if (contentEl) {
            contentEl.innerHTML =
                '<div class="text-center text-muted py-5">' +
                '<i class="fa fa-circle-o-notch fa-spin fa-3x" aria-hidden="true"></i>' +
                '<p class="mt-2">' + strings.loading + '</p>' +
                '</div>';
        }
        if (choicesEl) {
            choicesEl.innerHTML = '';
        }
    }

    /**
     * Open the story modal.
     */
    function openModal() {
        if (!modal) {
            return;
        }
        if (bsModal) {
            bsModal.show();
            return;
        }
        if (typeof window.bootstrap !== 'undefined' && window.bootstrap.Modal) {
            bsModal = new window.bootstrap.Modal(modal);
            bsModal.show();
        }
    }

    /**
     * Update the modal title text.
     *
     * @param {string} title New title text.
     */
    function updateTitle(title) {
        if (titleEl && title) {
            titleEl.innerHTML =
                '<i class="fa fa-book me-2" aria-hidden="true"></i>' +
                document.createTextNode(title).nodeValue;
        }
    }

    /**
     * Render a node (scene) into the modal.
     *
     * @param {Object} data Web service response data.
     * @param {number} chapterid Current chapter ID.
     */
    function renderNode(data, chapterid) {
        if (data.finished) {
            if (contentEl) {
                contentEl.innerHTML =
                    '<div class="text-center py-5">' +
                    '<i class="fa fa-check-circle fa-3x text-success" aria-hidden="true"></i>' +
                    '<h4 class="mt-3">' + strings.completed + '</h4>' +
                    '</div>';
            }
            if (choicesEl) {
                var footer = '';
                footer += '<button type="button" class="btn btn-secondary me-2"' +
                          ' data-bs-dismiss="modal">' + strings.close + '</button>';
                footer += '<button type="button" class="btn btn-outline-info"' +
                          ' data-action="read-recap"' +
                          ' data-chapterid="' + chapterid + '">' +
                          '<i class="fa fa-history" aria-hidden="true"></i> ' +
                          strings.readAgain + '</button>';
                choicesEl.innerHTML = footer;
            }
            updateChapterCardUI(chapterid);
            return;
        }

        if (!data.node) {
            return;
        }

        if (contentEl) {
            contentEl.innerHTML = data.node.content;
        }
        if (!choicesEl) {
            return;
        }
        choicesEl.innerHTML = '';

        if (data.node.choices && data.node.choices.length > 0) {
            data.node.choices.forEach(function(ch) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn ' + ch.btnclass + ' m-1 px-4 py-2 text-start';
                if (ch.disabled) {
                    btn.disabled = true;
                }

                var textSpan = document.createElement('span');
                textSpan.textContent = ch.text;
                btn.appendChild(textSpan);

                // Requirement: class.
                if (ch.req_class_name) {
                    var classTag = document.createElement('small');
                    classTag.className = ch.req_class_met ? 'd-block text-info' : 'd-block text-danger';
                    if (!ch.req_class_met) {
                        var lockIcon = document.createElement('i');
                        lockIcon.className = 'fa fa-lock me-1';
                        lockIcon.setAttribute('aria-hidden', 'true');
                        classTag.appendChild(lockIcon);
                    }
                    classTag.appendChild(document.createTextNode(ch.str_req_class));
                    btn.appendChild(classTag);
                }

                // Requirement: karma.
                if (ch.req_karma_min !== 0) {
                    var karmaTag = document.createElement('small');
                    if (ch.req_karma_met) {
                        karmaTag.className = 'd-block text-info';
                        karmaTag.textContent = ch.str_req_karma;
                    } else {
                        karmaTag.className = 'd-block text-danger';
                        karmaTag.textContent = ch.str_low_karma;
                    }
                    btn.appendChild(karmaTag);
                }

                // Cost: item.
                if (ch.cost_item_name) {
                    var costTag = document.createElement('small');
                    if (ch.cost_item_met) {
                        costTag.className = 'd-block text-warning';
                        costTag.textContent = ch.str_cost_item;
                    } else {
                        costTag.className = 'd-block text-danger';
                        costTag.textContent = ch.str_missing_item;
                    }
                    btn.appendChild(costTag);
                }

                btn.addEventListener('click', function() {
                    makeChoice(ch.id, chapterid);
                });
                choicesEl.appendChild(btn);
            });
        } else {
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn btn-secondary';
            closeBtn.setAttribute('data-bs-dismiss', 'modal');
            closeBtn.textContent = strings.close;
            choicesEl.appendChild(closeBtn);
        }

        if (data.events && data.events.length > 0) {
            data.events.forEach(function(evt) {
                Notification.addNotification({
                    message: evt.msg,
                    type: 'info',
                });
            });
        }
    }

    /**
     * Update the chapter card in the list to reflect completion.
     *
     * @param {number} chapterid Chapter ID.
     */
    function updateChapterCardUI(chapterid) {
        var card = document.querySelector('[data-action="open-chapter"][data-chapterid="' + chapterid + '"]');
        if (!card) {
            return;
        }
        card.classList.remove('list-group-item-action', 'ph-chapter-item--available');
        card.classList.add('ph-chapter-item--completed');
        card.removeAttribute('data-action');
        card.removeAttribute('role');
        card.removeAttribute('tabindex');

        var icon = card.querySelector('i.fa');
        if (icon) {
            icon.className = 'fa fa-check-circle text-success';
        }
        var statusEl = card.querySelector('small');
        if (statusEl) {
            statusEl.textContent = strings.completed;
        }

        if (!card.querySelector('[data-action="read-recap"]')) {
            var recapDiv = document.createElement('div');
            recapDiv.className = 'mt-2 pt-2 border-top';
            recapDiv.innerHTML =
                '<button class="btn btn-sm btn-outline-info"' +
                ' data-action="read-recap"' +
                ' data-chapterid="' + chapterid + '">' +
                '<i class="fa fa-history" aria-hidden="true"></i> ' + strings.readAgain +
                '</button>';
            card.appendChild(recapDiv);
        }
    }

    /**
     * Load the current or starting scene for a chapter.
     *
     * @param {number} chapterid Chapter ID.
     */
    function loadScene(chapterid) {
        showLoader();
        Ajax.call([{
            methodname: 'block_playerhud_load_scene',
            args: {
                instanceid: instanceid,
                courseid: courseid,
                chapterid: chapterid,
                preview: false,
            },
            done: function(data) {
                renderNode(data, chapterid);
            },
            fail: function(ex) {
                if (contentEl) {
                    var errDiv = document.createElement('div');
                    errDiv.className = 'alert alert-danger';
                    errDiv.textContent = ex.message || strings.error;
                    contentEl.replaceChildren(errDiv);
                }
                Notification.exception(ex);
            },
        }]);
    }

    /**
     * Process a player choice and advance the story.
     *
     * @param {number} choiceid Choice ID.
     * @param {number} chapterid Current chapter ID.
     */
    function makeChoice(choiceid, chapterid) {
        showLoader();
        Ajax.call([{
            methodname: 'block_playerhud_make_choice',
            args: {
                instanceid: instanceid,
                courseid: courseid,
                choiceid: choiceid,
                preview: false,
            },
            done: function(data) {
                renderNode(data, chapterid);
            },
            fail: function(ex) {
                if (contentEl) {
                    var errDiv = document.createElement('div');
                    errDiv.className = 'alert alert-danger';
                    errDiv.textContent = ex.message || strings.error;
                    contentEl.replaceChildren(errDiv);
                }
                Notification.exception(ex);
            },
        }]);
    }

    /**
     * Load the full story recap for a completed chapter.
     *
     * @param {number} chapterid Chapter ID.
     */
    function loadRecap(chapterid) {
        showLoader();
        Ajax.call([{
            methodname: 'block_playerhud_load_recap',
            args: {
                instanceid: instanceid,
                courseid: courseid,
                chapterid: chapterid,
            },
            done: function(data) {
                if (contentEl) {
                    contentEl.innerHTML = data.html;
                }
                if (choicesEl) {
                    var closeBtn = document.createElement('button');
                    closeBtn.type = 'button';
                    closeBtn.className = 'btn btn-secondary';
                    closeBtn.setAttribute('data-bs-dismiss', 'modal');
                    closeBtn.textContent = strings.close;
                    choicesEl.innerHTML = '';
                    choicesEl.appendChild(closeBtn);
                }
            },
            fail: function(ex) {
                if (contentEl) {
                    var errDiv = document.createElement('div');
                    errDiv.className = 'alert alert-danger';
                    errDiv.textContent = ex.message || strings.error;
                    contentEl.replaceChildren(errDiv);
                }
                Notification.exception(ex);
            },
        }]);
    }

    return {
        /**
         * Initialise the story player.
         *
         * @param {number} iid Block instance ID.
         * @param {number} cid Course ID.
         * @param {Object} strs Localised string map.
         */
        init: function(iid, cid, strs) {
            instanceid = iid;
            courseid = cid;
            strings = strs;

            modal = document.getElementById('ph-story-modal');
            contentEl = document.getElementById('ph-story-content');
            choicesEl = document.getElementById('ph-story-choices');
            titleEl = document.getElementById('ph-story-title');

            document.body.addEventListener('click', function(e) {
                var opener = e.target.closest('[data-action="open-chapter"]');
                if (opener) {
                    e.preventDefault();
                    var cid = parseInt(opener.getAttribute('data-chapterid'), 10);
                    var title = opener.getAttribute('data-title') || '';
                    updateTitle(title);
                    openModal();
                    loadScene(cid);
                    return;
                }

                var recap = e.target.closest('[data-action="read-recap"]');
                if (recap) {
                    e.preventDefault();
                    e.stopPropagation();
                    var rcid = parseInt(recap.getAttribute('data-chapterid'), 10);
                    var rtitle = recap.getAttribute('data-title') || '';
                    if (rtitle) {
                        updateTitle(rtitle);
                    }
                    openModal();
                    loadRecap(rcid);
                    return;
                }
            });

            if (modal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    bsModal = null;
                });
            }
        },
    };
});
