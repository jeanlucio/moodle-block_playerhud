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
 * Tests for the AI content generator's DB-writing helpers and its anti-SSRF URL guard
 * (no call to an actual AI provider involved).
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\ai;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for generator::save_item() and generator::is_safe_url(), both reached via reflection
 * since they are protected/private and neither one calls the AI itself — save_item() only
 * persists an already-parsed data array, and is_safe_url() only resolves/inspects a host.
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
     * Regression test for the security-audit finding: an AI-generated description must go
     * through the same sanitize_rich_description() the professor-authored path applies
     * (tab_items.php) — a modal skeleton embedded in the response must not survive intact, since
     * the plugin's own format_text() rendering keeps structural HTML (div/class) alive.
     */
    public function test_save_item_sanitizes_a_malicious_description(): void {
        global $DB;

        $generator = new generator($this->instanceid);
        $poisoned = '<script>alert(document.cookie)</script>'
            . '<div class="modal show d-block"><div class="modal-content">A real potion.</div></div>';

        $this->call_save_item($generator, [
            'name' => 'Poison',
            'description' => $poisoned,
            'emoji' => '🧪',
        ]);

        $item = $DB->get_record('block_playerhud_items', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertStringNotContainsString('<script>', $item->description);
        $this->assertStringNotContainsString('<div', $item->description);
        $this->assertStringNotContainsString('modal', $item->description);
        $this->assertStringContainsString('A real potion.', $item->description);
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

    /**
     * Builds a generator whose AI call is replaced by a canned response, so the parsing that
     * follows the call can be exercised without any provider.
     *
     * @param string $json The raw text the "AI" answers with.
     * @return generator
     */
    private function make_generator_answering(string $json): generator {
        return new class ($this->instanceid, $json) extends generator {
            /** @var string Canned AI answer. */
            private string $canned;

            /**
             * Stores the canned answer.
             *
             * @param int $instanceid Block instance ID.
             * @param string $canned The raw text the "AI" answers with.
             */
            public function __construct(int $instanceid, string $canned) {
                parent::__construct($instanceid);
                $this->canned = $canned;
            }

            #[\Override]
            protected function call_with_fallback(array $parts, string $description = ''): array {
                return ['success' => true, 'data' => $this->canned, 'provider' => 'Test'];
            }
        };
    }

    /**
     * Some models wrap the class in an array or in a "classes" list, which the code documents
     * and means to accept. The check for a top-level name used to run first and reject both.
     *
     * @dataProvider wrapped_class_response_provider
     * @param string $json Raw AI answer.
     */
    public function test_generate_class_accepts_a_wrapped_response(string $json): void {
        global $DB;

        $result = $this->make_generator_answering($json)->generate_class('wizards');

        $this->assertTrue($result['success']);
        $class = $DB->get_record('block_playerhud_classes', ['blockinstanceid' => $this->instanceid], '*', MUST_EXIST);
        $this->assertSame('Mago', $class->name);
        $this->assertSame(80, (int) $class->base_hp);
    }

    /**
     * Wrapped shapes a model may answer with.
     *
     * @return array
     */
    public static function wrapped_class_response_provider(): array {
        $class = '{"name":"Mago","description":"Arcano","hp":80,"emoji":"🧙"}';
        return [
            'array of classes' => ['[' . $class . ']'],
            'classes key' => ['{"classes":[' . $class . ']}'],
            'plain object' => [$class],
        ];
    }

    /**
     * A response with no usable class at all is still rejected as a parsing error.
     */
    public function test_generate_class_rejects_a_response_without_a_name(): void {
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('ai_error_parsing', 'block_playerhud'));

        $this->make_generator_answering('{"classes":[{"description":"no name"}]}')->generate_class('wizards');
    }

    /**
     * Calls the private is_safe_url() method via reflection.
     *
     * @param string $url The URL to check.
     * @return bool
     */
    private function call_is_safe_url(string $url): bool {
        $generator = new generator($this->instanceid);
        $method = new \ReflectionMethod($generator, 'is_safe_url');
        $method->setAccessible(true);

        return $method->invoke($generator, $url);
    }

    /**
     * Regression test for the security-audit SSRF finding: a host that does not resolve to any
     * A/AAAA record must be rejected, not silently treated as safe. Uses a .invalid TLD
     * (RFC 2606 — guaranteed to never resolve), the same failure shape a typo, a stale DNS
     * record or a transient resolver failure would produce.
     */
    public function test_is_safe_url_rejects_a_host_that_does_not_resolve(): void {
        $this->assertFalse($this->call_is_safe_url('https://nonexistent-host-xyz.invalid/v1'));
    }

    /**
     * Regression test for the security-audit SSRF finding: '127.000.000.1' is rejected by
     * filter_var(FILTER_VALIDATE_IP) for its leading zeros (PHP avoids octal ambiguity), so it
     * falls into the hostname branch — where it also fails to resolve via DNS, since it isn't a
     * real hostname either. curl's own resolver still connects it to 127.0.0.1, so the fail-open
     * fallback previously let this bypass the loopback/private-range check entirely.
     */
    public function test_is_safe_url_rejects_a_non_canonical_ip_literal(): void {
        $this->assertFalse($this->call_is_safe_url('https://127.000.000.1/v1'));
    }
}
