<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

use advanced_testcase;
use block_playerhud\game;

/**
 * Tests for the game logic class.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_playerhud\game
 */
final class game_test extends advanced_testcase {
    /**
     * Test get_player method to ensure it creates or retrieves the correct user data.
     *
     * @covers ::get_player
     */
    public function test_get_player(): void {
        global $DB;

        // Essential: Rollback database changes after test.
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $blockinstanceid = 999;

        // 1. Test creation of a new player.
        $player = game::get_player($blockinstanceid, $user->id);
        $this->assertEquals(0, $player->currentxp);
        $this->assertEquals(1, $player->enable_gamification);
        $this->assertNotEmpty($player->timecreated);

        // 2. Test retrieving an existing player.
        $DB->set_field('block_playerhud_user', 'currentxp', 150, ['id' => $player->id]);

        $existingplayer = game::get_player($blockinstanceid, $user->id);
        $this->assertEquals(150, $existingplayer->currentxp);
    }

    /**
     * Test toggle_gamification method.
     *
     * @covers ::toggle_gamification
     */
    public function test_toggle_gamification(): void {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $blockinstanceid = 999;

        // Toggle OFF.
        game::toggle_gamification($blockinstanceid, $user->id, false);
        $player = game::get_player($blockinstanceid, $user->id);
        $this->assertEquals(0, $player->enable_gamification);

        // Toggle ON.
        game::toggle_gamification($blockinstanceid, $user->id, true);
        $player = game::get_player($blockinstanceid, $user->id);
        $this->assertEquals(1, $player->enable_gamification);
    }
}
