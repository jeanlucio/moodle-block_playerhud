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
 * Lock factory for tests that records which lock keys were requested.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\tests;

use core\lock\file_lock_factory;

/**
 * A file lock factory that remembers every key it was asked for.
 *
 * The database lock factory used on PostgreSQL is reentrant inside one session, so a test can
 * never tell whether the code under test took a lock at all. Selecting this factory through
 * $CFG->lock_factory lets a test assert that the expected key was requested.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recording_lock_factory extends file_lock_factory {
    /** @var string[] Keys requested since the last call to start(). */
    private static array $requested = [];

    /**
     * Selects this factory for the rest of the test and clears the record.
     *
     * @return void
     */
    public static function start(): void {
        global $CFG;
        $CFG->lock_factory = self::class;
        self::$requested = [];
    }

    /**
     * Tells whether a key was requested since start().
     *
     * @param string $key The lock key.
     * @return bool
     */
    public static function was_requested(string $key): bool {
        return in_array($key, self::$requested, true);
    }

    #[\Override]
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        self::$requested[] = (string) $resource;
        return parent::get_lock($resource, $timeout, $maxlifetime);
    }
}
