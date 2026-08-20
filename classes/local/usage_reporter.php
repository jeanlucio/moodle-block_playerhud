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
 * Anonymous usage reporter.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Builds and sends the anonymous usage report.
 *
 * Mirrors block_xp's own usage_reporter/usage_report_maker split conceptually (a real,
 * published Marketplace plugin whose pattern is already accepted by the Directory review
 * process), collapsed into one class here since block_playerhud has no DI container to split
 * maker/sender across two collaborators the way block_xp does.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class usage_reporter {
    /** @var string Config key for the timestamp of the last successful send. */
    const CONFIG_LAST_SENT = 'usagereportlast';

    /** @var int Minimum seconds between sends, matching block_xp's own cadence. */
    const REPORT_INTERVAL = 21 * DAYSECS;

    /** @var string The remote endpoint that receives the report. */
    const REPORT_URL = 'https://plugintelemetry.duckdns.org/report.php';

    /**
     * Whether a report is due right now.
     *
     * @return bool
     */
    public static function is_due(): bool {
        if (!get_config('block_playerhud', 'usagereport')) {
            return false;
        }
        $last = (int) get_config('block_playerhud', self::CONFIG_LAST_SENT);
        return (time() - $last) > self::REPORT_INTERVAL;
    }

    /**
     * Build the report payload.
     *
     * @return array
     */
    public static function build_payload(): array {
        global $CFG, $DB;

        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('block_playerhud');
        $totalusers = $DB->count_records_select('user', 'deleted = 0 AND id != ?', [$CFG->siteguest]);

        // Active users of THIS plugin: touched their gamification record in the last
        // 90 days (~4 report cycles) — timemodified changes on XP/milestone updates,
        // a reasonable proxy for "engaged with the plugin recently".
        $activewindow = time() - (90 * DAYSECS);
        $activeusers = $DB->count_records_select(
            'block_playerhud_user',
            'timemodified > ?',
            [$activewindow],
            'COUNT(DISTINCT userid)'
        );

        return [
            'plugin' => 'block_playerhud',
            'siteidentifier' => get_site_identifier(),
            'url' => $CFG->wwwroot,
            'moodle_version' => $CFG->version,
            'moodle_release' => $CFG->release,
            'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION . '.' . PHP_RELEASE_VERSION,
            'plugin_version' => $plugininfo ? $plugininfo->versiondisk : null,
            'plugin_release' => $plugininfo ? $plugininfo->release : null,
            'country' => $CFG->country,
            'lang' => $CFG->lang,
            'active_users' => $activeusers,
            'site_size_bucket' => self::size_bucket((int) $totalusers),
            'error_counters' => diagnostics::get_all(),
        ];
    }

    /**
     * Send the report, if due.
     *
     * Clears error counters only after a confirmed successful send (2xx received), so a
     * failed POST never silently loses counts that were meant to be reported.
     *
     * @return bool Whether a report was sent successfully.
     */
    public static function send(): bool {
        if (!self::is_due()) {
            return false;
        }

        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $payload = self::build_payload();

        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json']);
        $curl->setopt(['CURLOPT_TIMEOUT' => 5, 'CURLOPT_CONNECTTIMEOUT' => 5]);
        $curl->post(self::REPORT_URL, json_encode($payload));

        if ($curl->get_errno()) {
            return false;
        }

        $info = $curl->get_info();
        $code = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        if ($code !== 200) {
            return false;
        }

        set_config(self::CONFIG_LAST_SENT, time(), 'block_playerhud');
        diagnostics::clear();

        return true;
    }

    /**
     * Bucket the site's total user count.
     *
     * @param int $totalusers
     * @return string
     */
    protected static function size_bucket(int $totalusers): string {
        if ($totalusers < 50) {
            return '<50';
        } else if ($totalusers < 500) {
            return '50-500';
        } else if ($totalusers < 5000) {
            return '500-5000';
        }
        return '>5000';
    }
}
