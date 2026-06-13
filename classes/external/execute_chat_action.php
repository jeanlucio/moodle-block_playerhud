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
 * Web service to execute a game action proposed by the AI assistant.
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
use context_block;

/**
 * External API to execute a game action proposed by the AI after teacher confirmation.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class execute_chat_action extends external_api {
    /**
     * Parameters for execute_chat_action.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'instanceid'   => new external_value(PARAM_INT, 'Block instance ID'),
            'courseid'     => new external_value(PARAM_INT, 'Course ID'),
            'actiontype'   => new external_value(PARAM_ALPHANUMEXT, 'Action type identifier'),
            'actionparams' => new external_value(PARAM_RAW, 'JSON-encoded action parameters'),
        ]);
    }

    /**
     * Executes a game action proposed by the AI after teacher confirmation.
     *
     * Supported action types: create_item, create_quest, open_tab.
     * Each type is validated against an explicit allow-list before execution.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $actiontype Action type identifier.
     * @param string $actionparams JSON-encoded parameters for the action.
     * @return array {success, message, redirect_url}
     */
    public static function execute(
        int $instanceid,
        int $courseid,
        string $actiontype,
        string $actionparams
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'instanceid'   => $instanceid,
            'courseid'     => $courseid,
            'actiontype'   => $actiontype,
            'actionparams' => $actionparams,
        ]);

        $context = context_block::instance($params['instanceid']);
        self::validate_context($context);
        require_capability('block/playerhud:manage', $context);

        // Validate action type against explicit allow-list.
        $allowedtypes = ['create_item', 'create_quest', 'create_chapter', 'open_tab'];
        if (!in_array($params['actiontype'], $allowedtypes, true)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_unknown_action', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $aparams = json_decode($params['actionparams'], true);
        if (!is_array($aparams)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        try {
            if ($params['actiontype'] === 'create_item') {
                return self::action_create_item(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'create_quest') {
                return self::action_create_quest(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'create_chapter') {
                return self::action_create_chapter(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }

            if ($params['actiontype'] === 'open_tab') {
                return self::action_open_tab(
                    $params['instanceid'],
                    $params['courseid'],
                    $aparams
                );
            }
        } catch (\Exception $e) {
            return [
                'success'      => false,
                'message'      => $e->getMessage(),
                'redirect_url' => '',
            ];
        }

        return [
            'success'      => false,
            'message'      => get_string('assistant_error_unknown_action', 'block_playerhud'),
            'redirect_url' => '',
        ];
    }

    /**
     * Executes the create_item action.
     *
     * Reuses the existing generator to create an item with an optional drop.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: theme, xp, create_drop.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_item(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $theme      = isset($p['theme']) ? clean_param($p['theme'], PARAM_TEXT) : '';
        $xp         = isset($p['xp']) ? max(1, (int)$p['xp']) : 10;
        $createdrop = !empty($p['create_drop']);

        if ($theme === '') {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate('item', $theme, $xp, $createdrop);

        $itemname = $result['item_name'] ?? '';

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'item';
        $log->object_name     = substr($itemname, 0, 255);
        $log->ai_provider     = substr($result['provider'] ?? '', 0, 50);
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_item_created', 'block_playerhud', $itemname);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'items',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the create_quest action.
     *
     * Validates and inserts a quest record with the AI-provided parameters.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: name, description, type, target_value, reward_xp.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_quest(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $allowedtypes = [
            \block_playerhud\quest::TYPE_LEVEL,
            \block_playerhud\quest::TYPE_XP_TOTAL,
            \block_playerhud\quest::TYPE_UNIQUE_ITEMS,
            \block_playerhud\quest::TYPE_TRADES,
        ];

        $name        = isset($p['name']) ? clean_param($p['name'], PARAM_TEXT) : '';
        $description = isset($p['description']) ? clean_param($p['description'], PARAM_TEXT) : '';
        $type        = isset($p['type']) ? (int)$p['type'] : 0;
        $targetvalue = isset($p['target_value']) ? max(1, (int)$p['target_value']) : 1;
        $rewardxp    = isset($p['reward_xp']) ? max(0, (int)$p['reward_xp']) : 0;

        if ($name === '' || !in_array($type, $allowedtypes, true)) {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $now = time();
        $quest = new \stdClass();
        $quest->blockinstanceid  = $instanceid;
        $quest->name             = $name;
        $quest->description      = $description;
        $quest->type             = $type;
        $quest->requirement      = (string)$targetvalue;
        $quest->req_itemid       = 0;
        $quest->reward_xp        = $rewardxp;
        $quest->reward_itemid    = 0;
        $quest->required_class_id = '0';
        $quest->image_todo       = '';
        $quest->image_done       = '';
        $quest->enabled          = 1;
        $quest->timecreated      = $now;
        $quest->timemodified     = $now;
        $DB->insert_record('block_playerhud_quests', $quest);

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'quest';
        $log->object_name     = substr($name, 0, 255);
        $log->ai_provider     = 'assistant';
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_quest_created', 'block_playerhud', $name);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'quests',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the create_chapter action.
     *
     * Delegates to the existing generate_story generator, which creates the
     * chapter record, all story nodes, and branching choices in a single
     * DB transaction.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: theme, karma_gain, karma_loss, item_qty.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_create_chapter(int $instanceid, int $courseid, array $p): array {
        global $DB, $USER;

        $theme = isset($p['theme']) ? clean_param($p['theme'], PARAM_TEXT) : '';

        if ($theme === '') {
            return [
                'success'      => false,
                'message'      => get_string('assistant_error_bad_params', 'block_playerhud'),
                'redirect_url' => '',
            ];
        }

        $options = [
            'karma_gain' => max(0, (int)($p['karma_gain'] ?? 0)),
            'karma_loss' => max(0, (int)($p['karma_loss'] ?? 0)),
            'item_qty'   => max(0, (int)($p['item_qty'] ?? 0)),
        ];

        $generator = new \block_playerhud\ai\generator($instanceid);
        $result = $generator->generate_story($theme, $options);

        $chaptertitle = $result['chapter_title'] ?? '';

        $log = new \stdClass();
        $log->blockinstanceid = $instanceid;
        $log->userid          = (int) $USER->id;
        $log->action_type     = 'chapter';
        $log->object_name     = substr($chaptertitle, 0, 255);
        $log->ai_provider     = substr($result['provider'] ?? '', 0, 50);
        $log->timecreated     = time();
        $DB->insert_record('block_playerhud_ai_logs', $log, false);

        $msg = get_string('assistant_action_chapter_created', 'block_playerhud', $chaptertitle);

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => 'chapters',
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => $msg,
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Executes the open_tab action.
     *
     * Returns a redirect URL to the requested management tab.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param array $p Action parameters: tab.
     * @return array Result with success, message, redirect_url.
     */
    private static function action_open_tab(int $instanceid, int $courseid, array $p): array {
        $allowedtabs = ['items', 'quests', 'classes', 'chapters', 'reports', 'config'];
        $tab = isset($p['tab']) ? clean_param($p['tab'], PARAM_ALPHA) : '';

        if (!in_array($tab, $allowedtabs, true)) {
            $tab = 'items';
        }

        $redirecturl = (new \moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $courseid,
            'instanceid' => $instanceid,
            'tab'        => $tab,
        ]))->out(false);

        return [
            'success'      => true,
            'message'      => get_string('assistant_action_opening_tab', 'block_playerhud', $tab),
            'redirect_url' => $redirecturl,
        ];
    }

    /**
     * Return structure for execute_chat_action.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'      => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
            'message'      => new external_value(PARAM_RAW, 'Result or error message', VALUE_DEFAULT, ''),
            'redirect_url' => new external_value(PARAM_URL, 'URL to redirect to', VALUE_DEFAULT, ''),
        ]);
    }
}
