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
 * Cached figures of the administrator's diagnostics page.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local\diagnostics;

/**
 * Gathers usage and integrity figures, cached for a few minutes.
 *
 * The windowed counts scan whole tables (none of them is indexed by date), so the page keeps
 * the result for MAXAGE seconds and offers a refresh instead of recounting on every visit.
 *
 * @package    block_playerhud
 */
class report {
    /** @var int Seconds a computed report is reused before being recounted. */
    public const MAXAGE = 600;

    /**
     * Returns the report, recounting it when stale, missing or explicitly refreshed.
     *
     * @param bool $refresh Whether to ignore the cached copy.
     * @return array Keys: time, since, adoption, engagement, orphans and loose.
     */
    public static function get(bool $refresh = false): array {
        $cache = \cache::make('block_playerhud', 'diagnostics');
        $data = $refresh ? false : $cache->get('report');
        if (!is_array($data) || ($data['time'] ?? 0) < time() - self::MAXAGE) {
            $data = self::compute();
            $cache->set('report', $data);
        }
        return $data;
    }

    /**
     * Counts everything now, without the cache.
     *
     * @return array Same shape as get().
     */
    public static function compute(): array {
        $now = time();
        $since = $now - usage::WINDOWDAYS * DAYSECS;
        return [
            'time' => $now,
            'since' => $since,
            'adoption' => usage::get_adoption(),
            'engagement' => usage::get_engagement($since),
            'orphans' => integrity::get_orphan_instances(),
            'loose' => integrity::count_loose_rows(),
        ];
    }

    /**
     * Drops the cached report, so the next visit recounts it.
     *
     * @return void
     */
    public static function invalidate(): void {
        \cache::make('block_playerhud', 'diagnostics')->delete('report');
    }
}
