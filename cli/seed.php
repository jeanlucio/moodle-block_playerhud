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
 * Seed script for manual testing of block_playerhud.
 *
 * Creates a demo course in English with users, items, drops, quests, classes, story and trades.
 * Run with --reset to wipe and recreate everything.
 *
 * Usage:
 *   php blocks/playerhud/cli/seed.php
 *   php blocks/playerhud/cli/seed.php --reset
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/user/lib.php');

// Suppress email sending in dev/test environments (no sendmail in Docker).
$CFG->noemailever = true;

[$options, $unrecognised] = cli_get_params(
    ['reset' => false, 'help' => false],
    ['h' => 'help', 'r' => 'reset']
);

if ($options['help']) {
    cli_writeln("Seed script for block_playerhud manual testing.\n");
    cli_writeln("Options:");
    cli_writeln("  --reset   Wipe the demo course and recreate everything from scratch.");
    cli_writeln("  --help    Show this message.");
    exit(0);
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'playerhud-demo';

/** @var string Default password for all seed users. */
const SEED_PASSWORD = 'Demo1234!';

cli_writeln("=== block_playerhud seed ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removing existing demo course (id={$existing->id})...");
        delete_course($existing, false);
        cli_writeln("Course removed.\n");
    }
    // Remove seed users.
    $seedusers = $DB->get_records_sql(
        "SELECT id FROM {user} WHERE username LIKE 'seed_%' AND deleted = 0"
    );
    foreach ($seedusers as $u) {
        delete_user($DB->get_record('user', ['id' => $u->id]));
    }
    cli_writeln("Seed users removed.\n");
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Demo course already exists (id={$course->id}). Use --reset to recreate.\n");
} else {
    $coursedata = (object) [
        'fullname'    => 'PlayerHUD Demo',
        'shortname'   => SEED_COURSE_SHORTNAME,
        'summary'     => 'Course created automatically by the PlayerHUD seed script for manual testing.',
        'format'      => 'topics',
        'numsections' => 3,
        'visible'     => 1,
        'category'    => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Course created: id={$course->id}");
}

$coursecontext = context_course::instance($course->id);

// 3. Users.

/**
 * Creates a user if it does not already exist.
 *
 * @param string $username Username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @param string $password Plaintext password.
 * @return stdClass User record.
 */
function seed_create_user(string $username, string $firstname, string $lastname, string $password): stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }

    $user = (object) [
        'auth'         => 'manual',
        'confirmed'    => 1,
        'policyagreed' => 1,
        'deleted'      => 0,
        'mnethostid'   => $CFG->mnet_localhost_id,
        'username'     => $username,
        'password'     => hash_internal_user_password($password),
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $username . '@playerhud.test',
        'lang'         => 'en',
        'timezone'     => '99',
        'timecreated'  => time(),
        'timemodified' => time(),
    ];

    $user->id = $DB->insert_record('user', $user);
    return $user;
}

$teacher = seed_create_user('seed_teacher', 'Dungeon', 'Master', SEED_PASSWORD);
$students = [];
$studentnames = [
    ['seed_alice', 'Alice', 'Sword'],
    ['seed_bob', 'Bob', 'Bow'],
    ['seed_carol', 'Carol', 'Staff'],
    ['seed_dave', 'Dave', 'Shield'],
    ['seed_eve', 'Eve', 'Dagger'],
];
foreach ($studentnames as [$uname, $fname, $lname]) {
    $students[] = seed_create_user($uname, $fname, $lname, SEED_PASSWORD);
}
cli_writeln("Users created/found: 1 teacher + " . count($students) . " students.");

// 4. Enrolment.
$enrol = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$instance) {
    $instanceid = $enrol->add_default_instance($course);
    $instance = $DB->get_record('enrol', ['id' => $instanceid]);
}

$teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

$enrol->enrol_user($instance, $teacher->id, $teacherrole->id);
foreach ($students as $student) {
    $enrol->enrol_user($instance, $student->id, $studentrole->id);
}
cli_writeln("Enrolments completed.");

// 5. Block instance.
$blockinstance = $DB->get_record('block_instances', [
    'blockname'       => 'playerhud',
    'parentcontextid' => $coursecontext->id,
]);

if (!$blockinstance) {
    $config = (object) [
        'xp_per_level'   => 100,
        'max_levels'     => 10,
        'enable_rpg'     => 1,
        'enable_ranking' => 1,
        'help_content'   => ['text' => '', 'format' => FORMAT_HTML],
    ];

    $bi = (object) [
        'blockname'         => 'playerhud',
        'parentcontextid'   => $coursecontext->id,
        'showinsubcontexts' => 0,
        'pagetypepattern'   => 'course-view-*',
        'subpagepattern'    => null,
        'defaultregion'     => 'side-pre',
        'defaultweight'     => 0,
        'configdata'        => base64_encode(serialize($config)),
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];

    $bi->id = $DB->insert_record('block_instances', $bi);
    context_block::instance($bi->id);
    $blockinstance = $bi;
    cli_writeln("PlayerHUD block created: id={$bi->id}");
} else {
    cli_writeln("PlayerHUD block already exists: id={$blockinstance->id}");
}

$instanceid = $blockinstance->id;

// 6. RPG Classes.
$now = time();

/**
 * Returns existing class or creates a new one.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Class name.
 * @param string $description Class description.
 * @param int $basehp Base HP value.
 * @return stdClass Class record.
 */
function seed_upsert_class(int $instanceid, string $name, string $description, int $basehp): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_classes', [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
        'description'     => $description,
        'base_hp'         => $basehp,
        'timecreated'     => $now,
        'timemodified'    => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_classes', $record);
    return $record;
}

$classwarrior = seed_upsert_class($instanceid, 'Warrior', 'A specialist in close-range combat.', 150);
$classmage    = seed_upsert_class($instanceid, 'Mage', 'Master of the arcane arts.', 80);
$classrogue   = seed_upsert_class($instanceid, 'Rogue', 'Swift and stealthy.', 100);
cli_writeln("RPG classes ready: Warrior, Mage, Rogue.");

// 7. Items.

/**
 * Returns existing item or creates a new one.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Item name.
 * @param int $xp XP value.
 * @param string $description Item description.
 * @param int $secret Whether item is secret.
 * @param string $image Emoji or image reference.
 * @return stdClass Item record.
 */
function seed_upsert_item(
    int $instanceid,
    string $name,
    int $xp,
    string $description = '',
    int $secret = 0,
    string $image = ''
): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_items', [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid'   => $instanceid,
        'name'              => $name,
        'description'       => $description,
        'image'             => $image,
        'xp'                => $xp,
        'enabled'           => 1,
        'required_class_id' => '0',
        'secret'            => $secret,
        'tradable'          => 1,
        'timecreated'       => $now,
        'timemodified'      => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_items', $record);
    return $record;
}

$itemsword     = seed_upsert_item($instanceid, 'Wooden Sword', 30, 'A simple sword for beginners.', 0, '⚔️');
$itempotionhp  = seed_upsert_item($instanceid, 'Health Potion', 20, 'Restores vitality.', 0, '🧪');
$itemscroll    = seed_upsert_item($instanceid, 'Arcane Scroll', 50, 'Increases magical power.', 0, '📜');
$itemkey       = seed_upsert_item($instanceid, 'Bronze Key', 10, 'Opens common chests.', 0, '🗝️');
$itemgem       = seed_upsert_item($instanceid, 'Secret Gem', 100, 'A very rare item.', 1, '💎');
$itemnames = implode(', ', ['Wooden Sword', 'Health Potion', 'Arcane Scroll', 'Bronze Key', 'Secret Gem']);
cli_writeln("Items ready: " . $itemnames);

// 8. Drops.

/**
 * Creates a drop if none exists for the given item and location name.
 *
 * @param int $instanceid Block instance ID.
 * @param int $itemid Item ID.
 * @param string $location Location name.
 * @param int $maxusage Max collections (0 = infinite).
 * @param int $respawntime Cooldown in seconds.
 * @return stdClass Drop record.
 */
function seed_upsert_drop(
    int $instanceid,
    int $itemid,
    string $location,
    int $maxusage = 1,
    int $respawntime = 0
): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_drops', [
        'blockinstanceid' => $instanceid,
        'itemid'          => $itemid,
        'name'            => $location,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid' => $instanceid,
        'itemid'          => $itemid,
        'name'            => $location,
        'maxusage'        => $maxusage,
        'respawntime'     => $respawntime,
        'code'            => strtoupper(substr(md5(uniqid($location, true)), 0, 8)),
        'timecreated'     => $now,
        'timemodified'    => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_drops', $record);
    return $record;
}

$dropforest  = seed_upsert_drop($instanceid, $itemsword->id, 'Dark Forest', 5, 0);
$droptavern  = seed_upsert_drop($instanceid, $itempotionhp->id, 'Harbor Tavern', 0, 3600);
$droplibrary = seed_upsert_drop($instanceid, $itemscroll->id, 'Royal Library', 3, 0);
$dropcave    = seed_upsert_drop($instanceid, $itemkey->id, 'Dwarf Cave', 10, 0);
$dropsecret  = seed_upsert_drop($instanceid, $itemgem->id, "Dragon's Vault", 1, 0);
cli_writeln("Drops ready: Dark Forest, Harbor Tavern, Royal Library, Dwarf Cave, Dragon's Vault.");

// 9. Quests.

/**
 * Creates a quest if one with the same name does not exist.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Quest name.
 * @param string $description Quest description.
 * @param int $type Quest type (1=level, 3=unique items, 4=specific item).
 * @param string $requirement Requirement value.
 * @param int $reqitemid Required item ID.
 * @param int $rewardxp XP reward.
 * @param int $rewarditemid Item reward ID.
 * @return stdClass Quest record.
 */
function seed_upsert_quest(
    int $instanceid,
    string $name,
    string $description,
    int $type,
    string $requirement,
    int $reqitemid,
    int $rewardxp,
    int $rewarditemid = 0
): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_quests', [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid'   => $instanceid,
        'name'              => $name,
        'description'       => $description,
        'type'              => $type,
        'requirement'       => $requirement,
        'req_itemid'        => $reqitemid,
        'reward_xp'         => $rewardxp,
        'reward_itemid'     => $rewarditemid,
        'required_class_id' => '0',
        'image_todo'        => '📋',
        'image_done'        => '🏅',
        'enabled'           => 1,
        'timecreated'       => $now,
        'timemodified'      => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_quests', $record);
    return $record;
}

// TYPE_UNIQUE_ITEMS=3: collected at least 1 distinct item.
seed_upsert_quest($instanceid, 'First Step', 'Collect any item for the first time.', 3, '1', 0, 25);
// TYPE_SPECIFIC_ITEM=4: collected 3 Health Potions.
seed_upsert_quest($instanceid, 'Potion Hunter', 'Collect 3 Health Potions.', 4, '3', $itempotionhp->id, 50);
// TYPE_SPECIFIC_ITEM=4: collected 1 Arcane Scroll; reward=Bronze Key.
seed_upsert_quest($instanceid, 'Scholar', 'Collect the Arcane Scroll.', 4, '1', $itemscroll->id, 75, $itemkey->id);
// TYPE_LEVEL=1: reached level 3.
seed_upsert_quest($instanceid, 'Level 3', 'Reach level 3.', 1, '3', 0, 30);
// TYPE_LEVEL=1: reached level 5.
seed_upsert_quest($instanceid, 'Level 5', 'Reach level 5.', 1, '5', 0, 60);
// TYPE_LEVEL=1: reached level 7.
seed_upsert_quest($instanceid, 'Level 7', 'Reach level 7.', 1, '7', 0, 100);
// TYPE_LEVEL=1: reached maximum level.
seed_upsert_quest($instanceid, 'Level 10', 'Reach the maximum level.', 1, '10', 0, 160);
cli_writeln("Quests ready: 7 quests created.");

// 10. Story (chapters, nodes, choices).

/**
 * Creates or returns an existing chapter.
 *
 * @param int $instanceid Block instance ID.
 * @param string $title Chapter title.
 * @param string $intro Intro text.
 * @param int $sortorder Sort order.
 * @return stdClass Chapter record.
 */
function seed_upsert_chapter(int $instanceid, string $title, string $intro, int $sortorder): stdClass {
    global $DB;

    $existing = $DB->get_record('block_playerhud_chapters', [
        'blockinstanceid' => $instanceid,
        'title'           => $title,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid' => $instanceid,
        'title'           => $title,
        'intro_text'      => $intro,
        'unlock_date'     => 0,
        'required_level'  => 0,
        'sortorder'       => $sortorder,
    ];
    $record->id = $DB->insert_record('block_playerhud_chapters', $record);
    return $record;
}

/**
 * Creates a story node.
 *
 * @param int $chapterid Chapter ID.
 * @param string $content Node content (HTML).
 * @param int $isstart Whether this is the starting node.
 * @return stdClass Node record.
 */
function seed_create_node(int $chapterid, string $content, int $isstart = 0): stdClass {
    global $DB;

    $record = (object) [
        'chapterid' => $chapterid,
        'content'   => $content,
        'is_start'  => $isstart,
    ];
    $record->id = $DB->insert_record('block_playerhud_story_nodes', $record);
    return $record;
}

/**
 * Creates a choice between nodes.
 *
 * @param int $nodeid Source node ID.
 * @param string $text Choice label.
 * @param int $nextnodeid Target node ID (0 = end).
 * @param int $karmadelta Karma change when selected.
 * @return stdClass Choice record.
 */
function seed_create_choice(int $nodeid, string $text, int $nextnodeid, int $karmadelta = 0): stdClass {
    global $DB;

    $record = (object) [
        'nodeid'        => $nodeid,
        'text'          => $text,
        'next_nodeid'   => $nextnodeid,
        'req_class_id'  => 0,
        'req_karma_min' => 0,
        'karma_delta'   => $karmadelta,
        'set_class_id'  => 0,
        'cost_itemid'   => 0,
        'cost_item_qty' => 1,
    ];
    $record->id = $DB->insert_record('block_playerhud_choices', $record);
    return $record;
}

$chapterexists = $DB->record_exists('block_playerhud_chapters', [
    'blockinstanceid' => $instanceid,
    'title'           => 'The Arrival',
]);

if (!$chapterexists) {
    // Chapter 1.
    $ch1 = seed_upsert_chapter($instanceid, 'The Arrival', 'It all began on a stormy night.', 1);
    $n1a = seed_create_node($ch1->id, '<p>You wake up in an unfamiliar inn. Rain hammers against the window.</p>', 1);
    $n1b = seed_create_node($ch1->id, '<p>The innkeeper smiles as he sees you awake. "Good morning, adventurer!"</p>');
    $n1c = seed_create_node($ch1->id, '<p>You slip out quietly without settling your bill.</p>');
    seed_create_choice($n1a->id, 'Greet the innkeeper', $n1b->id, 5);
    seed_create_choice($n1a->id, 'Leave without paying', $n1c->id, -10);
    seed_create_choice($n1b->id, 'Head out on an adventure', 0, 0);
    seed_create_choice($n1c->id, 'Vanish into the shadows', 0, -5);

    // Chapter 2.
    $ch2 = seed_upsert_chapter($instanceid, 'The Dark Forest', 'A forest full of dangers and secrets.', 2);
    $n2a = seed_create_node($ch2->id, '<p>You enter the forest. The silence is unsettling.</p>', 1);
    $n2b = seed_create_node($ch2->id, '<p>You find a wounded wolf lying on the path.</p>');
    $n2c = seed_create_node($ch2->id, '<p>The wolf barks gratefully and disappears into the darkness.</p>');
    $n2d = seed_create_node($ch2->id, '<p>You ignore the wolf and press on.</p>');
    seed_create_choice($n2a->id, 'Investigate the noise', $n2b->id, 0);
    seed_create_choice($n2a->id, 'Ignore it and keep moving', $n2d->id, 0);
    seed_create_choice($n2b->id, 'Help the wolf', $n2c->id, 15);
    seed_create_choice($n2b->id, 'Step over it', $n2d->id, -15);
    seed_create_choice($n2c->id, 'Continue your journey', 0, 0);
    seed_create_choice($n2d->id, 'Continue your journey', 0, 0);

    cli_writeln("Story created: 2 chapters with nodes and choices.");
} else {
    cli_writeln("Story already exists, skipping.");
}

// 11. Trades.

/**
 * Creates a trade offer if it does not exist.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Trade name.
 * @param array $reqs Array of ['itemid' => int, 'qty' => int].
 * @param array $rewards Array of ['itemid' => int, 'qty' => int].
 * @return stdClass Trade record.
 */
function seed_upsert_trade(int $instanceid, string $name, array $reqs, array $rewards): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_trades', [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $trade = (object) [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
        'groupid'         => 0,
        'centralized'     => 1,
        'onetime'         => 0,
        'timecreated'     => $now,
    ];
    $trade->id = $DB->insert_record('block_playerhud_trades', $trade);

    foreach ($reqs as $req) {
        $DB->insert_record('block_playerhud_trade_reqs', (object) [
            'tradeid' => $trade->id,
            'itemid'  => $req['itemid'],
            'qty'     => $req['qty'],
        ]);
    }

    foreach ($rewards as $reward) {
        $DB->insert_record('block_playerhud_trade_rewards', (object) [
            'tradeid' => $trade->id,
            'itemid'  => $reward['itemid'],
            'qty'     => $reward['qty'],
        ]);
    }

    return $trade;
}

seed_upsert_trade(
    $instanceid,
    'Trade: Keys for Scroll',
    [['itemid' => $itemkey->id, 'qty' => 2]],
    [['itemid' => $itemscroll->id, 'qty' => 1]]
);
seed_upsert_trade(
    $instanceid,
    'Trade: Potions for Sword',
    [['itemid' => $itempotionhp->id, 'qty' => 3]],
    [['itemid' => $itemsword->id, 'qty' => 1]]
);
cli_writeln("Trades ready: 2 trades created.");

// 12. User game records.

/**
 * Creates a user XP record if one does not exist.
 *
 * @param int $instanceid Block instance ID.
 * @param int $userid User ID.
 * @param int $xp Current XP.
 * @return void
 */
function seed_upsert_user_xp(int $instanceid, int $userid, int $xp): void {
    global $DB, $now;

    if ($DB->record_exists('block_playerhud_user', ['blockinstanceid' => $instanceid, 'userid' => $userid])) {
        return;
    }

    $DB->insert_record('block_playerhud_user', (object) [
        'blockinstanceid'     => $instanceid,
        'userid'              => $userid,
        'currentxp'           => $xp,
        'enable_gamification' => 1,
        'ranking_visibility'  => 1,
        'last_inventory_view' => 0,
        'last_shop_view'      => 0,
        'timecreated'         => $now,
        'timemodified'        => $now,
    ]);
}

/**
 * Creates RPG progress for a user if one does not exist.
 *
 * @param int $instanceid Block instance ID.
 * @param int $userid User ID.
 * @param int $classid RPG class ID.
 * @param int $karma Karma value.
 * @return void
 */
function seed_upsert_rpg_progress(int $instanceid, int $userid, int $classid, int $karma): void {
    global $DB;

    if ($DB->record_exists('block_playerhud_rpg_progress', ['blockinstanceid' => $instanceid, 'userid' => $userid])) {
        return;
    }

    $DB->insert_record('block_playerhud_rpg_progress', (object) [
        'blockinstanceid'    => $instanceid,
        'userid'             => $userid,
        'classid'            => $classid,
        'karma'              => $karma,
        'current_nodes'      => null,
        'completed_chapters' => null,
    ]);
}

$classids = [
    $classwarrior->id,
    $classmage->id,
    $classrogue->id,
    $classwarrior->id,
    $classmage->id,
];
$karmas = [10, -5, 20, 0, -20];

foreach ($students as $idx => $student) {
    // XP starts at 0 and is recalculated from real events after inventory/quests.
    seed_upsert_user_xp($instanceid, $student->id, 0);
    seed_upsert_rpg_progress($instanceid, $student->id, $classids[$idx], $karmas[$idx]);
}
cli_writeln("Base user records created.");

// 13. Inventory.

/**
 * Adds an item to a user's inventory if not already present.
 *
 * @param int $userid User ID.
 * @param int $itemid Item ID.
 * @param int $dropid Drop source ID (0 for quest rewards).
 * @param string $source Source type: 'map' or 'quest'.
 * @return void
 */
function seed_give_item(int $userid, int $itemid, int $dropid, string $source = 'map'): void {
    global $DB, $now;

    if ($DB->record_exists('block_playerhud_inventory', ['userid' => $userid, 'itemid' => $itemid])) {
        return;
    }

    $DB->insert_record('block_playerhud_inventory', (object) [
        'userid'      => $userid,
        'itemid'      => $itemid,
        'dropid'      => $dropid,
        'source'      => $source,
        'timecreated' => $now,
    ]);
}

// Alice: sword (30 XP, finite) + potion (0 XP, infinite drop) + key (10 XP, finite).
seed_give_item($students[0]->id, $itemsword->id, $dropforest->id);
seed_give_item($students[0]->id, $itempotionhp->id, $droptavern->id);
seed_give_item($students[0]->id, $itemkey->id, $dropcave->id);

// Bob: scroll (50 XP, finite).
seed_give_item($students[1]->id, $itemscroll->id, $droplibrary->id);

// Carol: sword (30 XP) + secret gem (100 XP), both finite.
seed_give_item($students[2]->id, $itemsword->id, $dropforest->id);
seed_give_item($students[2]->id, $itemgem->id, $dropsecret->id);

// Dave: potion (0 XP, infinite drop) + key (10 XP, finite).
seed_give_item($students[3]->id, $itempotionhp->id, $droptavern->id);
seed_give_item($students[3]->id, $itemkey->id, $dropcave->id);

// Eve: key (10 XP, finite).
seed_give_item($students[4]->id, $itemkey->id, $dropcave->id);

cli_writeln("Inventories created for all students.");

// 14. Quest completions.

/**
 * Logs a quest as claimed and adds the reward item to inventory if applicable.
 * Does not update currentxp directly — seed_recalc_xp handles that in bulk.
 *
 * @param int $instanceid Block instance ID.
 * @param stdClass $quest Quest record.
 * @param int $userid User ID.
 * @return void
 */
function seed_claim_quest(int $instanceid, stdClass $quest, int $userid): void {
    global $DB, $now;

    if ($DB->record_exists('block_playerhud_quest_log', ['questid' => $quest->id, 'userid' => $userid])) {
        return;
    }

    $DB->insert_record('block_playerhud_quest_log', (object) [
        'questid'     => $quest->id,
        'userid'      => $userid,
        'timecreated' => $now,
    ]);

    if ((int) $quest->reward_itemid > 0) {
        seed_give_item($userid, (int) $quest->reward_itemid, 0, 'quest');
    }
}

$questfirst   = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'First Step',
]);
$questscholar = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Scholar',
]);

// Alice: First Step (+25 XP). Items(40) + quest(25) = 65 XP.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[0]->id);
}

// Bob: First Step (+25) + Scholar (+75, reward=key). scroll(50)+q1(25)+q2(75) = 150 XP.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[1]->id);
}
if ($questscholar) {
    seed_claim_quest($instanceid, $questscholar, $students[1]->id);
}

// Carol: First Step (+25). sword(30)+gem(100)+quest(25) = 155 XP.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[2]->id);
}

// Dave and Eve: no quests. Dave=10 XP, Eve=10 XP.

cli_writeln("Completed quests recorded.");

// 15. Recalculate XP from real events.

/**
 * Calculates a user's total XP from finite-drop inventory plus claimed quest rewards.
 * Mirrors the rules in game::process_collection and quest::claim_reward.
 *
 * @param int $instanceid Block instance ID.
 * @param int $userid User ID.
 * @return int Calculated total XP.
 */
function seed_recalc_xp(int $instanceid, int $userid): int {
    global $DB;

    $itemxp = (int) $DB->get_field_sql(
        "SELECT COALESCE(SUM(i.xp), 0)
           FROM {block_playerhud_inventory} inv
           JOIN {block_playerhud_items} i ON i.id = inv.itemid
           JOIN {block_playerhud_drops} d ON d.id = inv.dropid
          WHERE inv.userid = :userid
            AND i.blockinstanceid = :instanceid
            AND d.maxusage > 0",
        ['userid' => $userid, 'instanceid' => $instanceid]
    );

    $questxp = (int) $DB->get_field_sql(
        "SELECT COALESCE(SUM(q.reward_xp), 0)
           FROM {block_playerhud_quest_log} ql
           JOIN {block_playerhud_quests} q ON q.id = ql.questid
          WHERE ql.userid = :userid
            AND q.blockinstanceid = :instanceid",
        ['userid' => $userid, 'instanceid' => $instanceid]
    );

    return $itemxp + $questxp;
}

$finalxp = [];
foreach ($students as $student) {
    $xp = seed_recalc_xp($instanceid, $student->id);
    $finalxp[$student->id] = $xp;
    $DB->set_field('block_playerhud_user', 'currentxp', $xp, [
        'blockinstanceid' => $instanceid,
        'userid'          => $student->id,
    ]);
}

// Spread timemodified so the ranking shows a realistic history.
// Lower timemodified = higher rank on XP tie (ORDER BY currentxp DESC, timemodified ASC).
$timeoffsets = [
    $students[0]->id => 3 * DAYSECS, // Alice: 3 days ago.
    $students[1]->id => 4 * DAYSECS, // Bob: 4 days ago.
    $students[2]->id => 5 * DAYSECS, // Carol: 5 days ago.
    $students[3]->id => 2 * DAYSECS, // Dave: 2 days ago (ranks above Eve on tie).
    $students[4]->id => 1 * DAYSECS, // Eve: 1 day ago.
];
foreach ($timeoffsets as $userid => $offset) {
    $DB->set_field('block_playerhud_user', 'timemodified', time() - $offset, [
        'blockinstanceid' => $instanceid,
        'userid'          => $userid,
    ]);
}
cli_writeln("XP recalculated from real events.");

// 16. Activities (page + assignment with simulated completion).

require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/completionlib.php');

$DB->set_field('course', 'enablecompletion', 1, ['id' => $course->id]);
$course->enablecompletion = 1;

// The add_moduleinfo API requires a logged-in user context.
$origuser = $USER;
\core\session\manager::set_user(get_admin());

/**
 * Creates a course module if one with the same name does not already exist.
 *
 * @param stdClass $course Course record.
 * @param string $modulename Module type (e.g., 'page', 'assign').
 * @param array $extra Fields merged into the moduleinfo object.
 * @return stdClass|null Course module record, or null if unavailable.
 */
function seed_create_module(stdClass $course, string $modulename, array $extra): ?stdClass {
    global $DB;

    $moduleid = $DB->get_field('modules', 'id', ['name' => $modulename]);
    if (!$moduleid) {
        return null;
    }

    $existingcm = $DB->get_record_sql(
        "SELECT cm.* FROM {course_modules} cm
           JOIN {{$modulename}} m ON m.id = cm.instance
          WHERE cm.course = :course AND m.name = :name",
        ['course' => $course->id, 'name' => $extra['name']]
    );
    if ($existingcm) {
        return get_coursemodule_from_id($modulename, $existingcm->id);
    }

    $moduleinfo = (object) array_merge([
        'modulename'         => $modulename,
        'module'             => $moduleid,
        'course'             => $course->id,
        'section'            => 1,
        'visible'            => 1,
        'intro'              => '',
        'introformat'        => FORMAT_HTML,
        'completion'         => COMPLETION_TRACKING_MANUAL,
        'completionview'     => 0,
        'completionexpected' => 0,
    ], $extra);

    $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
    return get_coursemodule_from_id($modulename, $moduleinfo->coursemodule);
}

$cmpage = seed_create_module($course, 'page', [
    'name'          => 'Reading: The Kingdom\'s History',
    'content'       => '<p>Once upon a time there was a kingdom where adventurers earned XP by exploring the world.</p>',
    'contentformat' => FORMAT_HTML,
    'display'       => 5,
]);

$cmassign = seed_create_module($course, 'assign', [
    'name'                                => 'Assignment: Adventure Report',
    'assignsubmission_onlinetext_enabled' => 1,
    'assignsubmission_file_enabled'       => 0,
    'assignfeedback_comments_enabled'     => 1,
    'submissiondrafts'                    => 0,
    'requiresubmissionstatement'          => 0,
    'sendnotifications'                   => 0,
    'sendlatenotifications'               => 0,
    'duedate'                             => 0,
    'cutoffdate'                          => 0,
    'gradingduedate'                      => 0,
    'allowsubmissionsfromdate'            => 0,
    'grade'                               => 100,
    'teamsubmission'                      => 0,
    'requireallteammemberssubmit'         => 0,
    'blindmarking'                        => 0,
    'attemptreopenmethod'                 => 'none',
    'maxattempts'                         => -1,
    'markingworkflow'                     => 0,
    'markingallocation'                   => 0,
]);

\core\session\manager::set_user($origuser);

$completion = new completion_info($course);

if ($cmpage) {
    // Alice, Bob, Carol completed the reading.
    foreach ([$students[0], $students[1], $students[2]] as $s) {
        $completion->update_state($cmpage, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Activity 'Reading' created — Alice, Bob, Carol marked as complete.");
} else {
    cli_writeln("Module 'page' unavailable — reading activity skipped.");
}

if ($cmassign) {
    // Bob and Carol completed the assignment.
    foreach ([$students[1], $students[2]] as $s) {
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Activity 'Assignment' created — Bob and Carol marked as complete.");
} else {
    cli_writeln("Module 'assign' unavailable — assignment activity skipped.");
}

// 17. Summary.

$wwwroot   = $CFG->wwwroot;
$courseurl = "{$wwwroot}/course/view.php?id={$course->id}";

cli_writeln("\n" . str_repeat('=', 60));
cli_writeln("SEED COMPLETE");
cli_writeln(str_repeat('=', 60));
cli_writeln("Course:   {$courseurl}");
cli_writeln("Block ID: {$instanceid}");
cli_writeln("");
cli_writeln("USERS (password: " . SEED_PASSWORD . ")");
cli_writeln(str_pad("Username", 20) . str_pad("Name", 20) . "XP");
cli_writeln(str_repeat('-', 55));
cli_writeln(str_pad($teacher->username, 20) . str_pad($teacher->firstname . ' ' . $teacher->lastname, 20) . "Teacher");
foreach ($students as $s) {
    $xp = $finalxp[$s->id];
    cli_writeln(str_pad($s->username, 20) . str_pad($s->firstname . ' ' . $s->lastname, 20) . "{$xp} XP");
}
cli_writeln(str_repeat('=', 60));
cli_writeln("To recreate from scratch: php blocks/playerhud/cli/seed.php --reset");
