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

namespace block_playerhud\output\view;

use advanced_testcase;

/**
 * Tests for the rules/help tab renderer (default fallback vs custom teacher content).
 *
 * Had no test coverage of any kind before ITEM 3's type-hint sweep.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\output\view\tab_rules
 */
final class tab_rules_test extends advanced_testcase {
    /**
     * Reset the environment for each test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * A config with no help_content at all falls back to the system default template,
     * carrying the enabled-feature flags for the default help cards.
     */
    public function test_export_for_template_falls_back_to_default(): void {
        $config = (object) [
            'enable_items'   => 1,
            'enable_quests'  => 0,
            'enable_rpg'     => 1,
            'enable_ranking' => 0,
        ];

        $tab = new tab_rules($config, 0);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertTrue($data['use_default']);
        $this->assertTrue($data['show_items']);
        $this->assertFalse($data['show_quests']);
        $this->assertTrue($data['show_rpg']);
        $this->assertFalse($data['show_ranking']);
    }

    /**
     * Custom help_content with use_default_help explicitly disabled renders the
     * teacher's own content instead of the default template.
     */
    public function test_export_for_template_uses_custom_content(): void {
        $config = (object) [
            'help_content'      => '<p>Welcome to the adventure!</p>',
            'use_default_help'  => 0,
        ];

        $tab = new tab_rules($config, 0);
        $data = $tab->export_for_template($this->createMock(\renderer_base::class));

        $this->assertFalse($data['use_default']);
        $this->assertStringContainsString('Welcome to the adventure!', $data['help_content']);
    }
}
