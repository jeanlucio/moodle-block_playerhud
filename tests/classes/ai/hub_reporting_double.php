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
 * Test double for generator that skips the network and hub-usage reporting tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\tests\ai;

use block_playerhud\ai\generator;

/**
 * Replaces load_api_keys() and the provider callers with canned values, so
 * call_with_fallback() can be exercised without real keys or network access.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hub_reporting_double extends generator {
    /** @var array Canned load_api_keys() return tuple. */
    private array $keys;

    /** @var array Queue of canned provider results, consumed in call order. */
    private array $results;

    /**
     * Constructor.
     *
     * @param int $instanceid Block instance ID.
     * @param array $keys Canned load_api_keys() return tuple: [gemini, groq, openai,
     *              openaiurl, openaimodel, keysource].
     * @param array $results Queue of results returned by successive provider calls.
     */
    public function __construct(int $instanceid, array $keys, array $results) {
        parent::__construct($instanceid);
        $this->keys = $keys;
        $this->results = $results;
    }

    #[\Override]
    protected function load_api_keys(): array {
        return $this->keys;
    }

    #[\Override]
    protected function call_gemini(array $parts, string $key): array {
        return array_shift($this->results);
    }

    #[\Override]
    protected function call_groq(array $parts, string $key): array {
        return array_shift($this->results);
    }

    #[\Override]
    protected function call_openai_compatible(array $parts, string $key, string $baseurl, string $model): array {
        return array_shift($this->results);
    }

    /**
     * Exposes the protected call_with_fallback() to the test.
     *
     * @param array $parts Prompt parts with 'system' and 'user' keys.
     * @param string $description Short label reported to the hub's usage log.
     * @return array Result array from call_with_fallback().
     */
    public function call_with_fallback_public(array $parts, string $description = ''): array {
        return $this->call_with_fallback($parts, $description);
    }
}
