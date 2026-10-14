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
 * Controller for the teacher's AI credential storage.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use block_playerhud\ai\generator;

/**
 * Persists the AI provider keys as user preferences.
 *
 * The keys are read back by {@see \block_playerhud\ai\generator}. They live in
 * user preferences (not block config) so they are never exposed in course
 * backups.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aikeys {
    /**
     * Maps submitted field names to their user-preference names.
     *
     * @var string[]
     */
    private const PREFERENCES = [
        'gemini_key'   => 'block_playerhud_gemini_key',
        'groq_key'     => 'block_playerhud_groq_key',
        'openai_key'   => 'block_playerhud_openai_key',
        'openai_url'   => 'block_playerhud_openai_url',
        'openai_model' => 'block_playerhud_openai_model',
    ];

    /**
     * Saves the submitted AI keys for a user and strips any legacy keys from
     * the block configuration.
     *
     * Each value is trimmed before being stored. Legacy keys that may have been
     * saved into the block config in older versions are removed so the
     * credentials only ever live in user preferences.
     *
     * @param array $values Submitted values keyed by field name (see PREFERENCES).
     * @param \stdClass $blockinstance The block_instances record to clean.
     * @param int $userid The owning user ID.
     * @return void
     */
    public static function save(array $values, \stdClass $blockinstance, int $userid): void {
        foreach (self::PREFERENCES as $field => $pref) {
            set_user_preference($pref, trim((string) ($values[$field] ?? '')), $userid);
        }

        $block = \block_instance_by_id((int) $blockinstance->id);
        $config = $block->config ?: new \stdClass();
        if (isset($config->apikey_gemini) || isset($config->apikey_groq)) {
            // The block's own save keeps its configuration cache and the config-changed handling in step.
            unset($config->apikey_gemini, $config->apikey_groq);
            $block->instance_config_save($config);
        }
    }

    /**
     * Explains why a typed personal endpoint cannot be saved, or returns '' when it can.
     *
     * The field is cleaned as a URL, and what the cleaner cannot read (an IP address in brackets, for
     * one) comes back empty, which the page used to take for "forget the endpoint": the saved one was
     * erased and the page said the keys were saved. The typed value is checked before it is cleaned.
     *
     * @param string $typed What the teacher typed in the endpoint field.
     * @param int $instanceid The block instance, for the address check.
     * @return string The lang string key that explains the refusal; empty when the value is fine.
     */
    public static function endpoint_problem(string $typed, int $instanceid): string {
        $typed = trim($typed);
        if ($typed === '') {
            return '';
        }
        $url = clean_param($typed, PARAM_URL);
        if ($url === '') {
            return 'ai_keys_endpoint_unreadable';
        }

        return (new generator($instanceid))->is_endpoint_allowed($url) ? '' : 'ai_keys_endpoint_refused';
    }
}
