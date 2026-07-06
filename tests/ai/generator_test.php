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
 * Tests for the AI content generator's DB-writing helpers (no network involved).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\ai;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for generator::save_item(), reached via reflection since it is protected and never
 * calls the AI itself — it only persists an already-parsed data array.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\ai\generator
 */
final class generator_test extends external_base_testcase {
    /**
     * Calls the protected save_item() method via reflection.
     *
     * @param generator $generator The generator instance.
     * @param array $data Item data, as if parsed from the AI's JSON response.
     * @return array The method's return value.
     */
    private function call_save_item(generator $generator, array $data): array {
        $method = new \ReflectionMethod($generator, 'save_item');
        $method->setAccessible(true);

        return $method->invoke($generator, $data, 10, false, 'Test');
    }

    /**
     * A name longer than the block_playerhud_items.name column (char(255)) must be clamped
     * instead of failing the insert — AI output is untrusted input, per the project's own rule.
     */
    public function test_save_item_clamps_an_overlong_ai_name(): void {
        global $DB;

        $generator = new generator($this->instanceid);
        $overlongname = str_repeat('A', 300);

        $this->call_save_item($generator, [
            'name' => $overlongname,
            'description' => 'A short description.',
            'emoji' => '🪙',
        ]);

        $item = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertSame(255, strlen($item->name));
        $this->assertSame(str_repeat('A', 255), $item->name);
    }

    /**
     * A malformed AI response (non-string name/description) must not crash the insert — coerced
     * to string defensively instead of trusting the JSON's declared shape.
     */
    public function test_save_item_coerces_non_string_fields(): void {
        global $DB;

        $generator = new generator($this->instanceid);

        $this->call_save_item($generator, [
            'name' => 12345,
            'description' => null,
            'emoji' => '🪙',
        ]);

        $item = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertSame('12345', $item->name);
        $this->assertSame('', $item->description);
    }
}
