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
 * Tests for the event fired when an AI provider request fails.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\event;

/**
 * Tests for the event fired when an AI provider request fails.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\event\ai_attempt_failed
 */
final class ai_attempt_failed_test extends \advanced_testcase {
    /**
     * The event can be triggered with its payload, has a name and describes the failure.
     */
    public function test_event_carries_the_failure_and_describes_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $sink = $this->redirectEvents();

        ai_attempt_failed::create([
            'context' => \context_system::instance(),
            'other' => [
                'provider' => 'Gemini',
                'tier' => 'own_personal',
                'seconds' => 90.0,
                'description' => 'story',
                'message' => 'Connection error.',
            ],
        ])->trigger();

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf(ai_attempt_failed::class, $event);
        $this->assertSame('Gemini', $event->other['provider']);
        $this->assertNotEmpty(ai_attempt_failed::get_name());
        $this->assertStringContainsString('Gemini', $event->get_description());
        $this->assertStringContainsString('90', $event->get_description());
    }

    /**
     * A payload without one of its values is a programming error, caught when the event is created.
     */
    public function test_event_requires_every_payload_value(): void {
        $this->resetAfterTest();

        $this->expectException(\coding_exception::class);
        ai_attempt_failed::create([
            'context' => \context_system::instance(),
            'other' => ['provider' => 'Groq'],
        ]);
    }
}
