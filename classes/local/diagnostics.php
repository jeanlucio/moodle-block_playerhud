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
 * Diagnostics counters for the anonymous usage report.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Lightweight in-config counters for diagnosing plugin failures in the wild.
 *
 * Counters accumulate between usage-report sends and are only cleared once a report has been
 * sent successfully (read-then-clear, not clear-before-send), so a failed POST never silently
 * drops counts that would otherwise never make it into a report.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class diagnostics {
    /** @var string Config key storing the JSON-encoded counters. */
    const CONFIG_KEY = 'usagereportcounters';

    /**
     * Increment a named counter by one.
     *
     * Failures here are swallowed on purpose: counter bookkeeping must never be able to turn
     * an already-failing code path into a second, unrelated failure.
     *
     * @param string $counter The counter key, e.g. 'ai_all_providers_failed'.
     * @return void
     */
    public static function increment(string $counter): void {
        try {
            $counters = self::get_all();
            $counters[$counter] = ($counters[$counter] ?? 0) + 1;
            set_config(self::CONFIG_KEY, json_encode($counters), 'block_playerhud');
        } catch (\Throwable $e) {
            debugging('block_playerhud diagnostics::increment failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Get all current counters.
     *
     * @return array<string,int>
     */
    public static function get_all(): array {
        $raw = get_config('block_playerhud', self::CONFIG_KEY);
        if (!$raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Clear all counters. Called by the usage reporter after a confirmed successful send.
     *
     * @return void
     */
    public static function clear(): void {
        set_config(self::CONFIG_KEY, json_encode([]), 'block_playerhud');
    }
}
