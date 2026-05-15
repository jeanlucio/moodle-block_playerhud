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

/**
 * Game Master AI Assistant AMD module for PlayerHUD.
 *
 * Manages the chat UI, conversation history, and web service calls for the
 * assistant tab on the management page.
 *
 * @module     block_playerhud/assistant
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax'], function(Ajax) {
    'use strict';

    /**
     * Initialise the Game Master Assistant chat panel.
     *
     * @param {Object} config Configuration object passed from PHP.
     * @param {number} config.instanceid Block instance ID.
     * @param {number} config.courseid Course ID.
     * @param {boolean} config.haskey Whether at least one AI provider key exists.
     */
    const init = (config) => {
        const {instanceid, courseid, haskey, openLabel} = config;

        if (!haskey) {
            return;
        }

        const messagesEl = document.getElementById('ph-assistant-messages');
        const inputEl = document.getElementById('ph-assistant-input');
        const sendBtn = document.getElementById('ph-assistant-send');
        const clearBtn = document.getElementById('ph-assistant-clear');
        const actionCard = document.getElementById('ph-assistant-action-card');
        const actionLabelEl = document.getElementById('ph-assistant-action-label');
        const confirmBtn = document.getElementById('ph-assistant-action-confirm');
        const cancelBtn = document.getElementById('ph-assistant-action-cancel');

        if (!messagesEl || !inputEl || !sendBtn) {
            return;
        }

        // In-memory conversation history — [{role, content}].
        let history = [];
        // The action proposed by the AI, waiting for teacher confirmation.
        let pendingAction = null;

        // ------------------------------------------------------------------ //
        // DOM helpers                                                         //
        // ------------------------------------------------------------------ //

        /**
         * Converts a plain-text AI reply that may contain basic Markdown into
         * safe HTML. The text is HTML-escaped first, then only **bold**,
         * *italic*, and newline-to-<br> substitutions are applied.
         *
         * @param {string} text Raw text from the AI.
         * @returns {string} Safe HTML string.
         */
        const renderMarkdown = (text) => {
            // Escape HTML to prevent XSS.
            const tmp = document.createElement('div');
            tmp.textContent = text;
            let safe = tmp.innerHTML;

            // Apply basic Markdown patterns.
            safe = safe.replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>');
            safe = safe.replace(/\*(.+?)\*/gs, '<em>$1</em>');
            safe = safe.replace(/\n/g, '<br>');

            return safe;
        };

        /**
         * Appends a chat message bubble to the messages container.
         *
         * @param {string} role  'user' | 'assistant' | 'system' | 'error'
         * @param {string} text  Plain text (user/system) or Markdown (assistant).
         * @param {string} [badge]  Optional badge text (e.g. provider name).
         */
        const appendMessage = (role, text, badge = '') => {
            const wrapper = document.createElement('div');
            wrapper.className = `ph-msg ph-msg-${role} mb-2`;

            if (role === 'user') {
                wrapper.classList.add('d-flex', 'justify-content-end');
            } else if (role === 'system' || role === 'error') {
                wrapper.classList.add('d-flex', 'justify-content-center');
            } else {
                wrapper.classList.add('d-flex', 'justify-content-start');
            }

            const bubble = document.createElement('div');
            bubble.className = 'ph-msg-bubble rounded p-2';

            if (role === 'system') {
                bubble.classList.add('alert', 'alert-success', 'py-1', 'px-3', 'mb-0', 'small');
            } else if (role === 'error') {
                bubble.classList.add('alert', 'alert-danger', 'py-1', 'px-3', 'mb-0', 'small');
            }

            if (badge) {
                const badgeEl = document.createElement('small');
                badgeEl.className = 'ph-provider-badge text-muted d-block mb-1';
                badgeEl.textContent = badge;
                bubble.appendChild(badgeEl);
            }

            const content = document.createElement('p');
            content.className = 'mb-0';
            if (role === 'assistant') {
                content.innerHTML = renderMarkdown(text);
            } else {
                content.textContent = text;
            }
            bubble.appendChild(content);

            wrapper.appendChild(bubble);
            messagesEl.appendChild(wrapper);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        };

        /**
         * Inserts an animated "thinking" bubble and returns the element.
         * Must be removed via removeThinking() when the response arrives.
         *
         * @returns {HTMLElement}
         */
        const showThinking = () => {
            const wrapper = document.createElement('div');
            wrapper.id = 'ph-assistant-thinking';
            wrapper.className = 'ph-msg ph-msg-assistant d-flex mb-2';

            const bubble = document.createElement('div');
            bubble.className = 'ph-msg-bubble ph-thinking-bubble rounded p-2';

            for (let i = 0; i < 3; i++) {
                const dot = document.createElement('span');
                dot.className = 'ph-dot';
                bubble.appendChild(dot);
            }

            wrapper.appendChild(bubble);
            messagesEl.appendChild(wrapper);
            messagesEl.scrollTop = messagesEl.scrollHeight;
            return wrapper;
        };

        /**
         * Removes the thinking bubble if still present.
         *
         * @param {HTMLElement} el The element returned by showThinking().
         */
        const removeThinking = (el) => {
            if (el && el.parentNode) {
                el.remove();
            }
        };

        // ------------------------------------------------------------------ //
        // Action card                                                         //
        // ------------------------------------------------------------------ //

        const showActionCard = (label) => {
            actionLabelEl.textContent = label;
            actionCard.classList.remove('d-none');
        };

        const hideActionCard = () => {
            actionCard.classList.add('d-none');
            actionLabelEl.textContent = '';
        };

        // ------------------------------------------------------------------ //
        // Sending state (button only)                                        //
        // ------------------------------------------------------------------ //

        const setSending = (sending) => {
            sendBtn.disabled = sending;
            inputEl.disabled = sending;
            sendBtn.querySelector('.ph-send-label').classList.toggle('d-none', sending);
            sendBtn.querySelector('.ph-thinking-label').classList.toggle('d-none', !sending);
        };

        // ------------------------------------------------------------------ //
        // Send a message                                                      //
        // ------------------------------------------------------------------ //

        const sendMessage = async() => {
            const text = inputEl.value.trim();
            if (!text) {
                return;
            }

            inputEl.value = '';
            appendMessage('user', text);
            history.push({role: 'user', content: text});

            setSending(true);
            hideActionCard();
            pendingAction = null;

            const thinkingEl = showThinking();

            try {
                const response = await Ajax.call([{
                    methodname: 'block_playerhud_chat_message',
                    args: {
                        instanceid,
                        courseid,
                        history,
                    },
                }])[0];

                removeThinking(thinkingEl);

                const reply = response.reply || '';
                if (reply) {
                    appendMessage('assistant', reply, response.provider || '');
                    history.push({role: 'assistant', content: reply});
                }

                if (response.action) {
                    try {
                        pendingAction = JSON.parse(response.action);
                        showActionCard(pendingAction.label || pendingAction.type);
                    } catch (_e) {
                        // Malformed action JSON from AI — silently ignore.
                    }
                }
            } catch (e) {
                removeThinking(thinkingEl);
                const msg = (e && e.message) ? e.message : String(e);
                appendMessage('error', msg);
            } finally {
                setSending(false);
            }
        };

        // ------------------------------------------------------------------ //
        // Execute confirmed action                                            //
        // ------------------------------------------------------------------ //

        const executeAction = async() => {
            if (!pendingAction) {
                return;
            }

            const action = pendingAction;
            pendingAction = null;
            hideActionCard();

            const thinkingEl = showThinking();

            try {
                const result = await Ajax.call([{
                    methodname: 'block_playerhud_execute_chat_action',
                    args: {
                        instanceid,
                        courseid,
                        actiontype: action.type,
                        actionparams: JSON.stringify(action.params || {}),
                    },
                }])[0];

                removeThinking(thinkingEl);
                appendMessage('system', result.message);

                if (result.redirect_url) {
                    const lastMsg = messagesEl.lastElementChild;
                    const bubble = lastMsg && lastMsg.querySelector('.ph-msg-bubble');
                    if (bubble) {
                        const link = document.createElement('a');
                        link.href = result.redirect_url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';
                        link.className = 'btn btn-sm btn-outline-success mt-2';
                        link.innerHTML =
                            `<i class="fa fa-external-link me-1" aria-hidden="true"></i>${openLabel}`;
                        bubble.appendChild(link);
                    }
                }
            } catch (e) {
                removeThinking(thinkingEl);
                const msg = (e && e.message) ? e.message : String(e);
                appendMessage('error', msg);
            }
        };

        // ------------------------------------------------------------------ //
        // Event listeners                                                     //
        // ------------------------------------------------------------------ //

        sendBtn.addEventListener('click', () => sendMessage());

        inputEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        clearBtn.addEventListener('click', () => {
            history = [];
            pendingAction = null;
            messagesEl.innerHTML = '';
            hideActionCard();
        });

        confirmBtn.addEventListener('click', () => executeAction());

        cancelBtn.addEventListener('click', () => {
            pendingAction = null;
            hideActionCard();
        });
    };

    return {init};
});
