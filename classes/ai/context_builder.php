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

namespace block_playerhud\ai;

use block_playerhud\quest;

/**
 * Builds the system prompt for the Game Master AI chat assistant.
 *
 * Gathers the current game state (items, quests, configuration) from the
 * database and combines it with role, command specification, and response
 * format instructions into a single system prompt string.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class context_builder {
    /** @var int Maximum number of items to include in the game-state snapshot. */
    const MAX_ITEMS_SNAPSHOT = 20;

    /** @var int Maximum number of quests to include in the game-state snapshot. */
    const MAX_QUESTS_SNAPSHOT = 15;

    /** @var int The block instance ID. */
    private int $instanceid;

    /** @var int The course ID. */
    private int $courseid;

    /** @var \stdClass Block configuration object. */
    private \stdClass $config;

    /**
     * Constructor.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     */
    public function __construct(int $instanceid, int $courseid) {
        global $DB;
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
        $bi = $DB->get_record('block_instances', ['id' => $instanceid], '*', MUST_EXIST);
        $cfg = unserialize_object(base64_decode($bi->configdata));
        $this->config = $cfg ?: new \stdClass();
    }

    /**
     * Returns the complete system prompt string.
     *
     * @return string
     */
    public function build(): string {
        $sections = [
            $this->role_section(),
            $this->game_state_section(),
            $this->commands_section(),
            $this->format_section(),
        ];
        return implode("\n\n", array_filter($sections));
    }

    /**
     * Returns the role and persona definition section.
     *
     * @return string
     */
    private function role_section(): string {
        return "You are the Game Master AI assistant for PlayerHUD, a gamification plugin for Moodle.\n"
            . "You help teachers (course instructors) design, manage, and balance their gamified courses.\n"
            . "You can answer questions about how the plugin works, suggest game design improvements,\n"
            . "and propose concrete actions such as creating items or quests — which the teacher can\n"
            . "review and confirm before anything is actually saved.\n"
            . "Always reply in the same language the teacher uses to talk to you.\n"
            . "Be concise, practical, and friendly.";
    }

    /**
     * Returns the current game state section (config + items snapshot + quests snapshot).
     *
     * @return string
     */
    private function game_state_section(): string {
        global $DB;

        $xpperlevel = isset($this->config->xp_per_level) ? (int)$this->config->xp_per_level : 100;
        $maxlevels = isset($this->config->max_levels) ? (int)$this->config->max_levels : 20;
        $rpgenabled = !empty($this->config->enable_rpg)   || !isset($this->config->enable_rpg);
        $itemsen    = !empty($this->config->enable_items)  || !isset($this->config->enable_items);
        $questsen   = !empty($this->config->enable_quests) || !isset($this->config->enable_quests);

        $course = $DB->get_record('course', ['id' => $this->courseid], 'fullname', IGNORE_MISSING);
        $coursename = $course ? format_string($course->fullname) : '';

        $lines = ["## Current Game State"];

        if ($coursename !== '') {
            $lines[] = "- Course: " . $coursename;
        }
        $lines[] = "- XP per level: {$xpperlevel}  |  Max levels: {$maxlevels}";
        $lines[] = "- Features enabled: items=" . ($itemsen ? 'yes' : 'no')
            . ", quests=" . ($questsen ? 'yes' : 'no')
            . ", RPG mode=" . ($rpgenabled ? 'yes' : 'no');

        // Items snapshot.
        if ($itemsen) {
            $items = $DB->get_records(
                'block_playerhud_items',
                ['blockinstanceid' => $this->instanceid],
                'timecreated DESC',
                'id, name, xp, enabled',
                0,
                self::MAX_ITEMS_SNAPSHOT
            );
            if ($items) {
                $lines[] = "\n### Items (most recent " . count($items) . ")";
                foreach ($items as $it) {
                    $status = $it->enabled ? '' : ' [disabled]';
                    $lines[] = "- " . s($it->name) . " ({$it->xp} XP){$status}";
                }
            } else {
                $lines[] = "\n### Items: none created yet.";
            }
        }

        // Quests snapshot.
        if ($questsen) {
            $quests = $DB->get_records(
                'block_playerhud_quests',
                ['blockinstanceid' => $this->instanceid],
                'timecreated DESC',
                'id, name, type, requirement, reward_xp, enabled',
                0,
                self::MAX_QUESTS_SNAPSHOT
            );
            if ($quests) {
                $typenames = [
                    quest::TYPE_LEVEL        => 'reach level',
                    quest::TYPE_XP_TOTAL     => 'total XP',
                    quest::TYPE_UNIQUE_ITEMS => 'collect unique items',
                    quest::TYPE_TRADES       => 'complete trades',
                ];
                $lines[] = "\n### Quests (most recent " . count($quests) . ")";
                foreach ($quests as $q) {
                    $tname = $typenames[$q->type] ?? "type {$q->type}";
                    $status = $q->enabled ? '' : ' [disabled]';
                    $lines[] = "- " . s($q->name) . " ({$tname} = {$q->requirement}"
                        . ", reward: {$q->reward_xp} XP){$status}";
                }
            } else {
                $lines[] = "\n### Quests: none created yet.";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Returns the available commands section describing what the AI may propose.
     *
     * @return string
     */
    private function commands_section(): string {
        $itemsen  = !empty($this->config->enable_items)  || !isset($this->config->enable_items);
        $questsen = !empty($this->config->enable_quests) || !isset($this->config->enable_quests);

        $lines = ["## Available Actions You Can Propose"];
        $lines[] = "When you want to suggest a concrete game action, include an \"action\" key in your JSON reply.";
        $lines[] = "The teacher will see a confirmation card before anything is saved.";
        $lines[] = "Only propose an action when the teacher clearly requests one.\n";

        if ($itemsen) {
            $lines[] = "### create_item";
            $lines[] = "Create a new collectible item.";
            $lines[] = "\x60\x60\x60";
            $lines[] = '{"type":"create_item","label":"Create item: <name> (<xp> XP)",'
                . '"params":{"theme":"<theme>","xp":<int>,"create_drop":<bool>}}';
            $lines[] = "\x60\x60\x60";
            $lines[] = "- theme: short English description of the item concept";
            $lines[] = "- xp: integer, must be > 0";
            $lines[] = "- create_drop: true if the item should also have a drop location\n";
        }

        if ($questsen) {
            $lines[] = "### create_quest";
            $lines[] = "Create a new quest milestone.";
            $lines[] = "\x60\x60\x60";
            $lines[] = '{"type":"create_quest","label":"Create quest: <title>",'
                . '"params":{"title":"<title>","description":"<desc>",'
                . '"type":<1|2|3|7>,"target_value":<int>,"reward_xp":<int>}}';
            $lines[] = "\x60\x60\x60";
            $lines[] = "- type: 1=reach level, 2=accumulate XP, 3=collect unique items, 7=complete trades";
            $lines[] = "- target_value and reward_xp must be positive integers\n";
        }

        $lines[] = "### create_chapter";
        $lines[] = "Generate a full interactive story chapter with branching nodes and choices.";
        $lines[] = "Use this when the teacher wants to create narrative content for the course.";
        $lines[] = "\x60\x60\x60";
        $lines[] = '{"type":"create_chapter","label":"Create chapter: <title>",'
            . '"params":{"theme":"<theme>","karma_gain":<int>,"karma_loss":<int>,"item_qty":<int>}}';
        $lines[] = "\x60\x60\x60";
        $lines[] = "- theme: narrative description in the course language (can be long and detailed)";
        $lines[] = "- karma_gain: total positive karma to distribute across choices (0 = none)";
        $lines[] = "- karma_loss: total negative karma to distribute across choices (0 = none)";
        $lines[] = "- item_qty: total item cost to distribute across key choices (0 = no item cost)\n";

        $lines[] = "### open_tab";
        $lines[] = "Navigate the teacher to a management tab.";
        $lines[] = "\x60\x60\x60";
        $lines[] = '{"type":"open_tab","label":"Go to <tab> tab","params":{"tab":"<tab>"}}';
        $lines[] = "\x60\x60\x60";
        $lines[] = "- tab: one of: items, quests, classes, chapters, reports, config";

        return implode("\n", $lines);
    }

    /**
     * Returns the mandatory response format section.
     *
     * The AI must ALWAYS reply with JSON so the frontend can extract the reply
     * text and any optional action card.
     *
     * @return string
     */
    private function format_section(): string {
        return "## Response Format — MANDATORY\n"
            . "You MUST always reply with a valid JSON object and nothing else.\n"
            . "No markdown fences, no extra text outside the JSON.\n\n"
            . "Without action:\n"
            . '{"reply":"Your answer here."}'
            . "\n\n"
            . "With action:\n"
            . '{"reply":"Here is what I suggest...","action":{"type":"...","label":"...","params":{...}}}';
    }
}
