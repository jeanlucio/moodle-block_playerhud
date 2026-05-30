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
 * Game Master AI Assistant tab renderable.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\output\manage;

use moodle_url;
use renderable;
use templatable;

/**
 * Prepares template data for the Game Master AI Assistant chat tab.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_assistant implements renderable, templatable {
    /** @var int Block instance ID. */
    protected int $instanceid;

    /** @var int Course ID. */
    protected int $courseid;

    /**
     * Constructor.
     *
     * @param int $instanceid Block instance ID.
     * @param int $courseid Course ID.
     * @param string $sort Unused — kept for signature compatibility with tab loader.
     * @param string $dir Unused — kept for signature compatibility with tab loader.
     */
    public function __construct(int $instanceid, int $courseid, string $sort = '', string $dir = '') {
        $this->instanceid = $instanceid;
        $this->courseid   = $courseid;
    }

    /**
     * Export data for the Mustache template.
     *
     * @param \renderer_base $output Active renderer.
     * @return array Template context array.
     */
    public function export_for_template($output): array {
        global $PAGE;

        $haskey = $this->has_any_key();

        $PAGE->requires->js_call_amd('block_playerhud/assistant', 'init', [[
            'instanceid' => $this->instanceid,
            'courseid'   => $this->courseid,
            'haskey'     => $haskey,
            'openLabel'  => get_string('assistant_open_link', 'block_playerhud'),
        ]]);

        $configurl = (new moodle_url('/blocks/playerhud/manage.php', [
            'id'         => $this->courseid,
            'instanceid' => $this->instanceid,
            'tab'        => 'config',
        ]))->out(false);

        return [
            'instanceid'            => $this->instanceid,
            'courseid'              => $this->courseid,
            'has_key'               => $haskey,
            'no_key_msg'            => get_string('assistant_no_key', 'block_playerhud'),
            'no_key_link_label'     => get_string('assistant_no_key_link', 'block_playerhud'),
            'no_key_config_url'     => $configurl,
            'heading'               => get_string('assistant_heading', 'block_playerhud'),
            'intro'                 => get_string('assistant_intro', 'block_playerhud'),
            'placeholder'           => get_string('assistant_placeholder', 'block_playerhud'),
            'send_label'            => get_string('assistant_send', 'block_playerhud'),
            'clear_label'           => get_string('assistant_clear', 'block_playerhud'),
            'confirm_label'         => get_string('assistant_confirm', 'block_playerhud'),
            'cancel_label'          => get_string('assistant_cancel', 'block_playerhud'),
            'thinking_label'        => get_string('assistant_thinking', 'block_playerhud'),
            'error_label'           => get_string('assistant_error', 'block_playerhud'),
            'action_prompt_label'   => get_string('assistant_action_prompt', 'block_playerhud'),
            'disclaimer'            => get_string('assistant_disclaimer', 'block_playerhud'),
        ];
    }

    /**
     * Returns true when at least one AI provider key is configured.
     *
     * Checks user preferences (teacher's personal keys) first, then falls back
     * to the global plugin config — the same priority order as the generator.
     *
     * @return bool
     */
    private function has_any_key(): bool {
        // Priority 0: Moodle core_ai subsystem.
        if (
            class_exists(\core_ai\manager::class)
            && class_exists(\core_ai\aiactions\generate_text::class)
        ) {
            try {
                global $DB;
                $actionclass = \core_ai\aiactions\generate_text::class;
                $reflection = new \ReflectionMethod(\core_ai\manager::class, 'get_providers_for_actions');
                if ($reflection->isStatic()) {
                    $providers = \core_ai\manager::get_providers_for_actions([$actionclass], true);
                } else {
                    $providers = (new \core_ai\manager($DB))->get_providers_for_actions([$actionclass], true);
                }
                if (!empty($providers[$actionclass])) {
                    return true;
                }
            } catch (\Throwable $e) {
                debugging('core_ai check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        // PlayerHUD user preferences and site config.
        $prefs = [
            'block_playerhud_gemini_key',
            'block_playerhud_groq_key',
            'block_playerhud_openai_key',
        ];
        foreach ($prefs as $pref) {
            if (get_user_preferences($pref, '') !== '') {
                return true;
            }
        }

        $configs = ['apikey_gemini', 'apikey_groq', 'apikey_openai'];
        foreach ($configs as $cfg) {
            if (get_config('block_playerhud', $cfg) !== false && get_config('block_playerhud', $cfg) !== '') {
                return true;
            }
        }

        // Local_playergames keys (personal + site config only — core_ai already checked above).
        if (class_exists(\local_playergames\api_key_helper::class)) {
            $pgproviders = [
                \local_playergames\api_key_helper::PROVIDER_GEMINI,
                \local_playergames\api_key_helper::PROVIDER_GROQ,
                \local_playergames\api_key_helper::PROVIDER_OPENAI,
            ];
            foreach ($pgproviders as $provider) {
                if (\local_playergames\api_key_helper::get_key($provider) !== '') {
                    return true;
                }
            }
        }

        return false;
    }
}
