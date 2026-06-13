<?php
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
 * Web service to send a chat message to the Game Master AI.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use context_block;

/**
 * External API to send a chat message to the Game Master AI and return its reply.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class chat_message extends external_api {
    /**
     * Parameters for chat_message.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid' => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'   => new external_value(PARAM_INT, 'Course ID'),
            'history'    => new external_multiple_structure(
                new external_single_structure([
                    'role'    => new external_value(PARAM_ALPHA, 'Message role: user or assistant'),
                    'content' => new external_value(PARAM_TEXT, 'Message content'),
                ]),
                'Conversation history',
                VALUE_DEFAULT,
                []
            ),
        ]);
    }

    /**
     * Sends a chat message to the Game Master AI and returns its reply.
     *
     * The full conversation history is received from the client (session-based,
     * never stored in the DB) so the AI has multi-turn context.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $history Conversation history [{role, content}].
     * @return array {reply, action, provider}
     */
    public static function execute(int $instanceid, int $courseid, array $history): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid' => $instanceid,
            'courseid'   => $courseid,
            'history'    => $history,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // Sanitise history: only allow known roles, strip excessive messages.
        $cleanhistory = [];
        foreach ($params['history'] as $msg) {
            if (!in_array($msg['role'], ['user', 'assistant'], true)) {
                continue;
            }
            $cleanhistory[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }

        // Limit history depth to avoid very large prompts.
        if (count($cleanhistory) > 30) {
            $cleanhistory = array_slice($cleanhistory, -30);
        }

        $builder      = new \block_playerhud\ai\context_builder(
            $params['instanceid'],
            $params['courseid']
        );
        $systemprompt = $builder->build();

        $chat   = new \block_playerhud\ai\chat($params['instanceid']);
        $result = $chat->send($systemprompt, $cleanhistory);

        $actionjson = '';
        if (!empty($result['action']) && is_array($result['action'])) {
            $actionjson = json_encode($result['action']);
        }

        return [
            'reply'    => $result['reply'],
            'action'   => $actionjson,
            'provider' => $result['provider'],
            'message'  => '',
        ];
    }

    /**
     * Return structure for chat_message.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'reply'    => new external_value(PARAM_RAW, 'AI reply text', VALUE_DEFAULT, ''),
            'action'   => new external_value(PARAM_RAW, 'JSON-encoded action object or empty', VALUE_DEFAULT, ''),
            'provider' => new external_value(PARAM_TEXT, 'AI provider used', VALUE_DEFAULT, ''),
            'message'  => new external_value(PARAM_RAW, 'Error message if any', VALUE_DEFAULT, ''),
        ]);
    }
}
