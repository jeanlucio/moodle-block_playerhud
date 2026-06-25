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
 * Tests for the classes controller.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\controller;

use advanced_testcase;
use context_block;
use stdClass;

/**
 * Tests for the classes controller persistence logic.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \block_playerhud\controller\classes
 */
final class classes_test extends advanced_testcase {
    /** @var int Block instance ID shared across test methods. */
    protected int $instanceid;

    /** @var context_block Block context for the file API. */
    protected context_block $context;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->instanceid = $this->make_block_instance();
        $this->context = context_block::instance($this->instanceid);
    }

    /**
     * Creates a course with a PlayerHUD block instance and returns its ID.
     *
     * @return int The new block instance ID.
     */
    protected function make_block_instance(): int {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        return (int) $DB->insert_record('block_instances', (object) [
            'blockname'         => 'playerhud',
            'parentcontextid'   => $coursecontext->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
    }

    /**
     * Builds submitted form data with empty draft areas for the portrait fields.
     *
     * @param int $classid Class ID (0 when creating).
     * @param string $name Class name.
     * @param string $description Class description.
     * @param string[] $emojis Emoji per tier (index 0 = tier 1).
     * @return stdClass
     */
    protected function make_data(int $classid, string $name, string $description, array $emojis): stdClass {
        $data = (object) [
            'classid'     => $classid,
            'instanceid'  => $this->instanceid,
            'name'        => $name,
            'description' => $description,
        ];
        for ($tier = 1; $tier <= 5; $tier++) {
            $data->{'emoji_tier' . $tier} = $emojis[$tier - 1] ?? '';
            $data->{'image_tier' . $tier} = file_get_unused_draft_itemid();
        }
        return $data;
    }

    /**
     * Inserts a class directly for the block instance.
     *
     * @param string $name Class name.
     * @param int $basehp Base HP to store.
     * @return stdClass The inserted record.
     */
    protected function seed_class(string $name, int $basehp): stdClass {
        global $DB;

        $id = $DB->insert_record('block_playerhud_classes', (object) [
            'blockinstanceid' => $this->instanceid,
            'name'            => $name,
            'description'     => 'seed',
            'base_hp'         => $basehp,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        return $DB->get_record('block_playerhud_classes', ['id' => $id], '*', MUST_EXIST);
    }

    /**
     * A new class is inserted with base HP, instance binding and emoji tiers.
     *
     * @covers ::save_class
     */
    public function test_save_class_inserts_new_record(): void {
        global $DB;

        $data = $this->make_data(0, 'Warrior', 'Tanky', ['S', 'A', '', '', '']);

        $id = (new classes())->save_class($data, $this->context, null);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('block_playerhud_classes', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame($this->instanceid, (int) $record->blockinstanceid);
        $this->assertSame('Warrior', $record->name);
        $this->assertSame('Tanky', $record->description);
        $this->assertSame(100, (int) $record->base_hp);
        $this->assertSame('S', $record->emoji_tier1);
        $this->assertSame('A', $record->emoji_tier2);
        $this->assertSame('', $record->emoji_tier3);
    }

    /**
     * Saving with a class ID updates that record without touching its base HP.
     *
     * @covers ::save_class
     */
    public function test_save_class_updates_existing_record(): void {
        global $DB;

        $record = $this->seed_class('Old name', 150);
        $data = $this->make_data((int) $record->id, 'Mage', 'Updated', ['M', '', '', '', '']);

        $returned = (new classes())->save_class($data, $this->context, $record);

        $this->assertSame((int) $record->id, $returned);
        $updated = $DB->get_record('block_playerhud_classes', ['id' => $record->id], '*', MUST_EXIST);
        $this->assertSame('Mage', $updated->name);
        $this->assertSame('Updated', $updated->description);
        $this->assertSame('M', $updated->emoji_tier1);
        // The update path must not reset the base HP.
        $this->assertSame(150, (int) $updated->base_hp);
    }

    /**
     * Emoji tiers are trimmed before being stored.
     *
     * @covers ::save_class
     */
    public function test_save_class_trims_emoji_tiers(): void {
        global $DB;

        $data = $this->make_data(0, 'Rogue', 'Sneaky', ['  X  ', '', '', '', '']);

        $id = (new classes())->save_class($data, $this->context, null);

        $record = $DB->get_record('block_playerhud_classes', ['id' => $id], '*', MUST_EXIST);
        $this->assertSame('X', $record->emoji_tier1);
    }

    /**
     * A class cannot be updated under a different block instance.
     *
     * @covers ::save_class
     */
    public function test_save_class_rejects_foreign_instance(): void {
        $record = $this->seed_class('Owned by A', 100);
        $instanceb = $this->make_block_instance();

        $data = $this->make_data((int) $record->id, 'Hijack', 'x', ['', '', '', '', '']);
        $data->instanceid = $instanceb;

        $this->expectException(\dml_missing_record_exception::class);
        (new classes())->save_class($data, $this->context, $record);
    }

    /**
     * Deleting a class removes its record and every tier portrait file.
     *
     * @covers ::delete_class
     */
    public function test_delete_class_removes_record_and_portraits(): void {
        global $DB;

        $record = $this->seed_class('Doomed', 100);
        $fs = get_file_storage();
        $filerecord = [
            'contextid' => $this->context->id,
            'component' => 'block_playerhud',
            'filearea'  => 'class_image_1',
            'itemid'    => $record->id,
            'filepath'  => '/',
            'filename'  => 'portrait.png',
        ];
        $fs->create_file_from_string($filerecord, 'image-bytes');
        $this->assertTrue($fs->file_exists(
            $this->context->id,
            'block_playerhud',
            'class_image_1',
            $record->id,
            '/',
            'portrait.png'
        ));

        (new classes())->delete_class((int) $record->id, $this->instanceid, $this->context);

        $this->assertFalse($DB->record_exists('block_playerhud_classes', ['id' => $record->id]));
        $this->assertFalse($fs->file_exists(
            $this->context->id,
            'block_playerhud',
            'class_image_1',
            $record->id,
            '/',
            'portrait.png'
        ));
    }

    /**
     * A class owned by another instance cannot be deleted.
     *
     * @covers ::delete_class
     */
    public function test_delete_class_rejects_foreign_instance(): void {
        global $DB;

        $record = $this->seed_class('Owned by A', 100);
        $instanceb = $this->make_block_instance();

        try {
            (new classes())->delete_class((int) $record->id, $instanceb, $this->context);
            $this->fail('Expected a dml_missing_record_exception.');
        } catch (\dml_missing_record_exception $e) {
            $this->assertTrue($DB->record_exists('block_playerhud_classes', ['id' => $record->id]));
        }
    }

    /**
     * Deleting a class leaves sibling classes of the same instance untouched.
     *
     * @covers ::delete_class
     */
    public function test_delete_class_keeps_sibling_classes(): void {
        global $DB;

        $target = $this->seed_class('Target', 100);
        $sibling = $this->seed_class('Sibling', 100);

        (new classes())->delete_class((int) $target->id, $this->instanceid, $this->context);

        $this->assertFalse($DB->record_exists('block_playerhud_classes', ['id' => $target->id]));
        $this->assertTrue($DB->record_exists('block_playerhud_classes', ['id' => $sibling->id]));
    }
}
