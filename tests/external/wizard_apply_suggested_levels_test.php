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
 * Tests for the wizard_apply_suggested_levels web service.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\external;

use block_playerhud\tests\external\external_base_testcase;

/**
 * Tests for the wizard_apply_suggested_levels web service.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\external\wizard_apply_suggested_levels
 */
final class wizard_apply_suggested_levels_test extends external_base_testcase {
    /**
     * An instance still at the edit form's defaults (100 XP per level, 20 levels) gets the
     * suggested max_levels for the given journey size, at the fixed 100 XP per level.
     */
    public function test_applies_suggestion_when_at_defaults(): void {
        $result = wizard_apply_suggested_levels::execute($this->instanceid, 'medium');

        $this->assertTrue($result['applied']);
        $this->assertSame(100, $result['xp_per_level']);
        $this->assertSame(15, $result['max_levels']);

        $blockinstance = \block_instance_by_id($this->instanceid);
        $this->assertSame(100, (int) $blockinstance->config->xp_per_level);
        $this->assertSame(15, (int) $blockinstance->config->max_levels);
    }

    /**
     * An instance whose level settings were already customised away from the defaults still gets
     * overwritten: the wizard's "auto-adjust levels" checkbox is the consent gate, not this
     * server-side call — a teacher only reaches it by explicitly opting in.
     */
    public function test_applies_even_when_already_customised(): void {
        $instanceid = $this->create_block_instance(['xp_per_level' => 100, 'max_levels' => 30]);

        $result = wizard_apply_suggested_levels::execute($instanceid, 'short');

        $this->assertTrue($result['applied']);
        $this->assertSame(10, $result['max_levels']);

        $blockinstance = \block_instance_by_id($instanceid);
        $this->assertSame(10, (int) $blockinstance->config->max_levels);
    }

    /**
     * Applying the suggestion preserves every other setting already stored in configdata
     * (instance_config_save() replaces the whole object, so the caller must merge, not
     * overwrite) — proven here with the ranking toggle, unrelated to level settings.
     */
    public function test_preserves_other_config_fields(): void {
        $instanceid = $this->create_block_instance(['enable_ranking' => 0]);

        $result = wizard_apply_suggested_levels::execute($instanceid, 'long');

        $this->assertTrue($result['applied']);

        $blockinstance = \block_instance_by_id($instanceid);
        $this->assertSame(0, (int) $blockinstance->config->enable_ranking);
        $this->assertSame(20, (int) $blockinstance->config->max_levels);
    }
}
