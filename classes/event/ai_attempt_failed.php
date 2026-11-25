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
 * Event fired when a request to an AI provider fails.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Fired for every provider attempt of an AI request that did not produce an answer.
 *
 * The 'other' payload carries the 'provider' that was asked, the 'tier' the key came from, how many
 * 'seconds' the attempt took, the 'description' of what was being generated and the provider's
 * 'message', cut to 255 characters. It never carries the prompt or the API key.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_attempt_failed extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud']     = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_ai_attempt_failed', 'block_playerhud');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $provider = $this->other['provider'] ?? '';
        $seconds = $this->other['seconds'] ?? 0;
        $tier = $this->other['tier'] ?? '';
        return "The request of the user with id '{$this->userid}' to the AI provider '{$provider}' failed after " .
            "{$seconds} seconds, using a key from the tier '{$tier}'.";
    }

    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        foreach (['provider', 'tier', 'seconds', 'description', 'message'] as $key) {
            if (!array_key_exists($key, $this->other ?? [])) {
                throw new \coding_exception("The '{$key}' value must be set in other.");
            }
        }
    }

    #[\Override]
    public static function get_other_mapping(): bool {
        return false;
    }
}
