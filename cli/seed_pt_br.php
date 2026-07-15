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
 * Seed script for manual testing of block_playerhud (PT-BR).
 *
 * Creates a demo course in Brazilian Portuguese with users, items, drops, quests, classes, story and trades.
 * Run with --reset to wipe and recreate everything.
 *
 * Usage:
 *   php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev
 *   php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev --reset
 *   php blocks/playerhud/cli/seed_pt_br.php --password=SuaSenhaDev --force
 *
 * O parâmetro --password é obrigatório e define a senha de login de todos os
 * usuários demo criados pelo script (ex.: seed_teacher, seed_student1, …).
 * Use-a para entrar no Moodle como um desses usuários de teste.
 * O script recusa execução em sites que não sejam de desenvolvimento, a menos
 * que --force seja informado (use em domínios de dev personalizados).
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
    ['reset' => false, 'help' => false, 'password' => '', 'force' => false],
    ['h' => 'help', 'r' => 'reset', 'p' => 'password', 'f' => 'force']
);

if ($options['help']) {
    cli_writeln("Seed script for block_playerhud manual testing.\n");
    cli_writeln("Options:");
    cli_writeln("  --password=<pass>  Required. Password for all seed users.");
    cli_writeln("  --reset            Wipe the demo course and recreate everything from scratch.");
    cli_writeln("  --force            Skip the development-site guard (use on custom dev domains).");
    cli_writeln("  --help             Show this message.");
    exit(0);
}

// Refuse to run without an explicit password to avoid known-credential accounts in production.
if (empty($options['password'])) {
    cli_error("ERROR: --password=<pass> is required. Aborting to prevent known-credential accounts.");
}

// Guard: refuse to run if this looks like a production environment.
// Use --force to bypass when running on a custom development domain.
if (
    !$options['force'] && !empty($CFG->wwwroot)
    && !preg_match('/localhost|127\.0\.0\.1|\.local(:|\/|$)|\.test(:|\/|$)/i', $CFG->wwwroot)
) {
    cli_error(
        "ERROR: This script must not be run on a non-development site ({$CFG->wwwroot}).\n" .
        "If this is intentional, re-run with --force."
    );
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'playerhud-demo-ptbr';

/** @var string Password for all seed users, supplied via --password flag. */
define('SEED_PASSWORD', $options['password']);

cli_writeln("=== block_playerhud seed ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removendo curso demo existente (id={$existing->id})...");
        delete_course($existing, false);
        cli_writeln("Curso removido.\n");
    }
    // Remove seed users.
    $seedusers = $DB->get_records_sql(
        "SELECT id FROM {user} WHERE username LIKE 'seed_%' AND deleted = 0"
    );
    foreach ($seedusers as $u) {
        delete_user($DB->get_record('user', ['id' => $u->id]));
    }
    cli_writeln("Usuários seed removidos.\n");
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Curso demo já existe (id={$course->id}). Use --reset para recriar.\n");
} else {
    $coursedata = (object) [
        'fullname'  => 'PlayerHUD Demo (PT-BR)',
        'shortname' => SEED_COURSE_SHORTNAME,
        'summary'   => 'Curso criado automaticamente pelo seed do PlayerHUD para testes manuais.',
        'format'    => 'topics',
        'numsections' => 3,
        'visible'   => 1,
        'category'  => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Curso criado: id={$course->id}");
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
        'auth'        => 'manual',
        'confirmed'   => 1,
        'policyagreed' => 1,
        'deleted'     => 0,
        'mnethostid'  => $CFG->mnet_localhost_id,
        'username'    => $username,
        'password'    => hash_internal_user_password($password),
        'firstname'   => $firstname,
        'lastname'    => $lastname,
        'email'       => $username . '@playerhud.test',
        'lang'        => 'pt_br',
        'timezone'    => '99',
        'picture'     => 0,
        'timecreated' => time(),
        'timemodified' => time(),
    ];

    $user->id = $DB->insert_record('user', $user);
    return $user;
}

/**
 * Generates a simple colour+initial PNG avatar and sets it as the user's profile picture,
 * via the same process_new_icon() API Moodle's own profile picture upload uses.
 *
 * Purely cosmetic: without this, every seed account renders as a plain grey initials circle
 * in screenshots, which is harder to tell apart at a glance in the training material.
 *
 * @param int $userid User ID.
 * @param string $initial Single letter to draw (only the first character is used).
 * @param string $hexcolor Background colour, e.g. '#e57373'.
 * @return void
 */
function seed_set_avatar(int $userid, string $initial, string $hexcolor): void {
    global $DB, $CFG;

    require_once($CFG->libdir . '/gdlib.php');

    $size = 256;
    $image = imagecreatetruecolor($size, $size);

    [$red, $green, $blue] = sscanf($hexcolor, '#%02x%02x%02x');
    $bg = imagecolorallocate($image, $red, $green, $blue);
    imagefill($image, 0, 0, $bg);

    $white = imagecolorallocate($image, 255, 255, 255);
    $font = $CFG->libdir . '/default.ttf';
    $fontsize = 120;
    $letter = strtoupper(substr($initial, 0, 1));

    $bbox = imagettfbbox($fontsize, 0, $font, $letter);
    $textwidth = abs($bbox[4] - $bbox[0]);
    $textheight = abs($bbox[5] - $bbox[1]);
    $x = (int) (($size - $textwidth) / 2);
    $y = (int) (($size + $textheight) / 2);
    imagettftext($image, $fontsize, 0, $x, $y, $white, $font, $letter);

    $tmpfile = $CFG->tempdir . '/seed_avatar_' . $userid . '.png';
    imagepng($image, $tmpfile);
    imagedestroy($image);

    $newpicture = (int) process_new_icon(context_user::instance($userid), 'user', 'icon', 0, $tmpfile);
    $DB->set_field('user', 'picture', $newpicture, ['id' => $userid]);

    unlink($tmpfile);
}

$teacher = seed_create_user('seed_teacher', 'Mestre', 'Dungeon', SEED_PASSWORD);
$students = [];
$studentnames = [
    ['seed_alice', 'Alice', 'Espada', '#e57373'],
    ['seed_bob', 'Bob', 'Arco', '#64b5f6'],
    ['seed_carol', 'Carol', 'Cajado', '#ba68c8'],
    ['seed_dave', 'Dave', 'Escudo', '#81c784'],
    ['seed_eve', 'Eve', 'Adaga', '#ffb74d'],
];
foreach ($studentnames as [$uname, $fname, $lname, $avatarcolor]) {
    $student = seed_create_user($uname, $fname, $lname, SEED_PASSWORD);
    if ((int) $student->picture === 0) {
        seed_set_avatar($student->id, $fname, $avatarcolor);
    }
    $students[] = $student;
}
if ((int) $teacher->picture === 0) {
    seed_set_avatar($teacher->id, $teacher->firstname, '#546e7a');
}
cli_writeln("Usuários criados/encontrados: 1 professor + " . count($students) . " alunos.");

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
cli_writeln("Matrículas concluídas.");

// 5. Block instance.
$blockinstance = $DB->get_record('block_instances', [
    'blockname' => 'playerhud',
    'parentcontextid' => $coursecontext->id,
]);

if (!$blockinstance) {
    $config = (object) [
        'xp_per_level'          => 100,
        'max_levels'            => 10,
        'enable_rpg'            => 1,
        'enable_ranking'        => 1,
        'enable_items'          => 1,
        'enable_quests'         => 1,
        'enable_group_ranking'  => 1,
        'help_content'          => ['text' => '', 'format' => FORMAT_HTML],
    ];

    $bi = (object) [
        'blockname'        => 'playerhud',
        'parentcontextid'  => $coursecontext->id,
        'showinsubcontexts' => 0,
        'pagetypepattern'  => 'course-view-*',
        'subpagepattern'   => null,
        'defaultregion'    => 'side-pre',
        'defaultweight'    => 0,
        'configdata'       => base64_encode(serialize($config)),
        'timecreated'      => time(),
        'timemodified'     => time(),
    ];

    $bi->id = $DB->insert_record('block_instances', $bi);
    context_block::instance($bi->id);
    $blockinstance = $bi;
    cli_writeln("Bloco PlayHUD criado: id={$bi->id}");
} else {
    cli_writeln("Bloco PlayHUD já existe: id={$blockinstance->id}");
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
 * @param string[] $tiers Five evolution-stage emoji, from Tier 1 (no chapters completed) to
 *        Tier 5 (highest reputation evolution).
 * @return stdClass Class record.
 */
function seed_upsert_class(int $instanceid, string $name, string $description, int $basehp, array $tiers): stdClass {
    global $DB, $now;

    $existing = $DB->get_record('block_playerhud_classes', [
        'blockinstanceid' => $instanceid,
        'name' => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid' => $instanceid,
        'name'            => $name,
        'description'     => $description,
        'base_hp'         => $basehp,
        'emoji_tier1'     => $tiers[0],
        'emoji_tier2'     => $tiers[1],
        'emoji_tier3'     => $tiers[2],
        'emoji_tier4'     => $tiers[3],
        'emoji_tier5'     => $tiers[4],
        'timecreated'     => $now,
        'timemodified'    => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_classes', $record);
    return $record;
}

$classwarrior = seed_upsert_class(
    $instanceid,
    'Guerreiro',
    'Especialista em combate corpo a corpo.',
    150,
    ['🥾', '⚔️', '🛡️', '🐉', '👑']
);
$classmage = seed_upsert_class(
    $instanceid,
    'Mago',
    'Mestre das artes arcanas.',
    80,
    ['✨', '📖', '🔮', '🌟', '🧙']
);
$classrogue = seed_upsert_class(
    $instanceid,
    'Ladino',
    'Veloz e sorrateiro.',
    100,
    ['🗝️', '🏹', '🥷', '🌙', '🃏']
);
cli_writeln("Classes RPG prontas: Guerreiro, Mago, Ladino (com retratos evolutivos).");

// 7. Items.

/**
 * Returns existing item or creates a new one.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Item name.
 * @param int $xp XP value.
 * @param string $description Item description.
 * @param int $secret Whether item is secret.
 * @param string $image Image filename for the item.
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
        'name' => $name,
    ]);
    if ($existing) {
        return $existing;
    }

    $record = (object) [
        'blockinstanceid'  => $instanceid,
        'name'             => $name,
        'description'      => $description,
        'image'            => $image,
        'xp'               => $xp,
        'enabled'          => 1,
        'required_class_id' => '0',
        'secret'           => $secret,
        'tradable'         => 1,
        'timecreated'      => $now,
        'timemodified'     => $now,
    ];
    $record->id = $DB->insert_record('block_playerhud_items', $record);
    return $record;
}

$itemsword = seed_upsert_item($instanceid, 'Espada de Madeira', 30, 'Uma espada simples para iniciantes.', 0, '⚔️');
$itempotionhp = seed_upsert_item($instanceid, 'Poção de Vida', 20, 'Restaura vitalidade.', 0, '🧪');
$itemscroll = seed_upsert_item($instanceid, 'Pergaminho Arcano', 50, 'Aumenta o poder mágico.', 0, '📜');
$itemkey = seed_upsert_item($instanceid, 'Chave de Bronze', 10, 'Abre cofres comuns.', 0, '🗝️');
$itemgem = seed_upsert_item($instanceid, 'Gema Secreta', 100, 'Um item muito raro.', 1, '💎');
$itemnames = implode(', ', ['Espada de Madeira', 'Poção de Vida', 'Pergaminho Arcano', 'Chave de Bronze', 'Gema Secreta']);
cli_writeln("Itens prontos: " . $itemnames);

// 8. Drops.

/**
 * Creates a drop if none exists for the given item name.
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

$dropforest  = seed_upsert_drop($instanceid, $itemsword->id, 'Floresta Sombria', 5, 900);      // 15 min.
$droptavern  = seed_upsert_drop($instanceid, $itempotionhp->id, 'Taverna do Porto', 0, 3600);  // 1 h.
$droplibrary = seed_upsert_drop($instanceid, $itemscroll->id, 'Biblioteca Real', 3, 1800);     // 30 min.
$dropcave    = seed_upsert_drop($instanceid, $itemkey->id, 'Caverna dos Anões', 10, 600);      // 10 min.
$dropsecret  = seed_upsert_drop($instanceid, $itemgem->id, 'Cofre do Dragão', 1, 0);           // Coleta única.
cli_writeln("Drops prontos: Floresta, Taverna, Biblioteca, Caverna, Cofre.");

// 9. Quests.

/**
 * Creates a quest if one with the same name does not exist.
 *
 * @param int $instanceid Block instance ID.
 * @param string $name Quest name.
 * @param string $description Quest description.
 * @param int $type Quest type (1=item collect, 2=level).
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

// TYPE_UNIQUE_ITEMS=3: coletou pelo menos 1 item distinto.
seed_upsert_quest($instanceid, 'Primeiro Passo', 'Colete qualquer item pela primeira vez.', 3, '1', 0, 25);
// TYPE_SPECIFIC_ITEM=4: coletou 3 Poções de Vida.
seed_upsert_quest($instanceid, 'Caçador de Poções', 'Colete 3 Poções de Vida.', 4, '3', $itempotionhp->id, 50);
// TYPE_SPECIFIC_ITEM=4: coletou 1 Pergaminho Arcano; recompensa=Chave.
seed_upsert_quest($instanceid, 'Estudioso', 'Colete o Pergaminho Arcano.', 4, '1', $itemscroll->id, 75, $itemkey->id);
// TYPE_LEVEL=1: alcançou o nível 3.
seed_upsert_quest($instanceid, 'Nível 3', 'Alcance o nível 3.', 1, '3', 0, 30);
// TYPE_LEVEL=1: alcançou o nível 5.
seed_upsert_quest($instanceid, 'Nível 5', 'Alcance o nível 5.', 1, '5', 0, 60);
// TYPE_LEVEL=1: alcançou o nível 7.
seed_upsert_quest($instanceid, 'Nível 7', 'Alcance o nível 7.', 1, '7', 0, 100);
// TYPE_LEVEL=1: alcançou o nível máximo. Reward reduzido de 160 para 80 para abrir espaço
// no orçamento de XP para as 2 quests novas abaixo, mantendo a Saúde da Economia em 100%.
seed_upsert_quest($instanceid, 'Nível 10', 'Alcance o nível máximo.', 1, '10', 0, 80);
// TYPE_XP_TOTAL=2: acumulou 150 XP no total.
seed_upsert_quest($instanceid, 'Colecionador de XP', 'Acumule 150 XP no total.', 2, '150', 0, 40);
// TYPE_TRADES=7: completou pelo menos 1 troca na Loja NPC.
seed_upsert_quest($instanceid, 'Negociante', 'Complete pelo menos 1 troca na Loja NPC.', 7, '1', 0, 40);
cli_writeln("Quests prontas: 9 quests criadas.");

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
    'title'           => 'A Chegada',
]);

if (!$chapterexists) {
    // Chapter 1.
    $ch1 = seed_upsert_chapter($instanceid, 'A Chegada', 'Tudo começou numa noite de tempestade.', 1);
    $n1a = seed_create_node($ch1->id, '<p>Você acorda numa estalagem desconhecida. A chuva bate forte na janela.</p>', 1);
    $n1b = seed_create_node($ch1->id, '<p>O estalajadeiro sorri ao vê-lo acordado. "Bom dia, aventureiro!"</p>');
    $n1c = seed_create_node($ch1->id, '<p>Você sai sorrateiramente sem pagar a conta.</p>');
    seed_create_choice($n1a->id, 'Cumprimentar o estalajadeiro', $n1b->id, 5);
    seed_create_choice($n1a->id, 'Sair sem pagar', $n1c->id, -10);
    seed_create_choice($n1b->id, 'Partir em aventura', 0, 0);
    seed_create_choice($n1c->id, 'Fugir para as sombras', 0, -5);

    // Chapter 2.
    $ch2 = seed_upsert_chapter($instanceid, 'A Floresta Sombria', 'Uma floresta cheia de perigos e segredos.', 2);
    $n2a = seed_create_node($ch2->id, '<p>Você entra na floresta. O silêncio é perturbador.</p>', 1);
    $n2b = seed_create_node($ch2->id, '<p>Você encontra um lobo ferido no caminho.</p>');
    $n2c = seed_create_node($ch2->id, '<p>O lobo late de gratidão e some na escuridão.</p>');
    $n2d = seed_create_node($ch2->id, '<p>Você ignora o lobo e segue em frente.</p>');
    seed_create_choice($n2a->id, 'Investigar o barulho', $n2b->id, 0);
    seed_create_choice($n2a->id, 'Ignorar e continuar', $n2d->id, 0);
    seed_create_choice($n2b->id, 'Ajudar o lobo', $n2c->id, 15);
    seed_create_choice($n2b->id, 'Passar por cima', $n2d->id, -15);
    seed_create_choice($n2c->id, 'Seguir viagem', 0, 0);
    seed_create_choice($n2d->id, 'Seguir viagem', 0, 0);

    cli_writeln("História criada: 2 capítulos com nós e escolhas.");
} else {
    cli_writeln("História já existe, pulando criação.");
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
    'Troca: Chaves por Pergaminho',
    [['itemid' => $itemkey->id, 'qty' => 2]],
    [['itemid' => $itemscroll->id, 'qty' => 1]]
);
seed_upsert_trade(
    $instanceid,
    'Troca: Poções por Espada',
    [['itemid' => $itempotionhp->id, 'qty' => 3]],
    [['itemid' => $itemsword->id, 'qty' => 1]]
);
cli_writeln("Trades prontos: 2 trocas criadas.");

// 12. User game records.

/**
 * Creates or updates a user XP record.
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
        'blockinstanceid'    => $instanceid,
        'userid'             => $userid,
        'currentxp'          => $xp,
        'enable_gamification' => 1,
        'ranking_visibility' => 1,
        'last_inventory_view' => 0,
        'last_shop_view'     => 0,
        'timecreated'        => $now,
        'timemodified'       => $now,
    ]);
}

/**
 * Creates or updates RPG progress for a user.
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
cli_writeln("Registros base de usuários criados.");

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

    // Recompensa em item de missão nunca paga o XP do próprio item (só reward_xp paga); um
    // drop no mapa paga, exceto quando o drop é infinito (maxusage = 0).
    $xpawarded = 0;
    if ($source === 'map') {
        $item = $DB->get_record('block_playerhud_items', ['id' => $itemid], 'xp');
        $drop = $dropid > 0 ? $DB->get_record('block_playerhud_drops', ['id' => $dropid], 'maxusage') : null;
        if ($item && (!$drop || (int) $drop->maxusage > 0)) {
            $xpawarded = (int) $item->xp;
        }
    }

    // Define timecreated 2 horas no passado para nenhum item pré-inserido iniciar em cooldown.
    $DB->insert_record('block_playerhud_inventory', (object) [
        'userid'      => $userid,
        'itemid'      => $itemid,
        'dropid'      => $dropid,
        'source'      => $source,
        'timecreated' => $now - 7200,
        'xpawarded'   => $xpawarded,
    ]);
}

// Alice: espada (30 XP, finito) + poção (0 XP, drop infinito) + chave (10 XP, finito).
seed_give_item($students[0]->id, $itemsword->id, $dropforest->id);
seed_give_item($students[0]->id, $itempotionhp->id, $droptavern->id);
seed_give_item($students[0]->id, $itemkey->id, $dropcave->id);

// Bob: pergaminho (50 XP, finito).
seed_give_item($students[1]->id, $itemscroll->id, $droplibrary->id);

// Carol: espada (30 XP) + gema secreta (100 XP), ambos finitos.
seed_give_item($students[2]->id, $itemsword->id, $dropforest->id);
seed_give_item($students[2]->id, $itemgem->id, $dropsecret->id);

// Dave: poção (0 XP, drop infinito) + chave (10 XP, finito).
seed_give_item($students[3]->id, $itempotionhp->id, $droptavern->id);
seed_give_item($students[3]->id, $itemkey->id, $dropcave->id);

// Eve: chave (10 XP, finito).
seed_give_item($students[4]->id, $itemkey->id, $dropcave->id);

cli_writeln("Inventários criados para todos os alunos.");

// 13b. Trade execution (demo for the TYPE_TRADES quest).

/**
 * Grants an extra copy of an item, bypassing seed_give_item()'s per-item dedupe guard.
 * Needed to stock up multiple copies of the same item (e.g. 2 keys for a trade cost).
 *
 * @param int $userid User ID.
 * @param int $itemid Item ID.
 * @param int $dropid Drop source ID.
 * @return void
 */
function seed_give_extra_item(int $userid, int $itemid, int $dropid): void {
    global $DB, $now;

    $xp = (int) $DB->get_field('block_playerhud_items', 'xp', ['id' => $itemid]);
    $DB->insert_record('block_playerhud_inventory', (object) [
        'userid'      => $userid,
        'itemid'      => $itemid,
        'dropid'      => $dropid,
        'source'      => 'map',
        'timecreated' => $now - 7200,
        'xpawarded'   => $xp,
    ]);
}

/**
 * Replicates trade_manager::execute_trade() (classes/trade_manager.php) without going through
 * its locking/transaction machinery, which is unnecessary for a single-threaded seed script.
 * Consumes the required items FIFO, grants the reward items (dropid=0, source='shop', no XP —
 * trade rewards never pay XP, mirroring the real implementation) and logs the transaction.
 *
 * @param int $userid User ID.
 * @param stdClass $trade Trade record.
 * @param array $reqs Array of ['itemid' => int, 'qty' => int], same shape passed to
 *        seed_upsert_trade().
 * @param array $rewards Array of ['itemid' => int, 'qty' => int].
 * @return void
 */
function seed_execute_trade(int $userid, stdClass $trade, array $reqs, array $rewards): void {
    global $DB, $now;

    if ($DB->record_exists('block_playerhud_trade_log', ['tradeid' => $trade->id, 'userid' => $userid])) {
        return;
    }

    foreach ($reqs as $req) {
        $owned = $DB->get_records(
            'block_playerhud_inventory',
            ['userid' => $userid, 'itemid' => $req['itemid']],
            'timecreated ASC',
            'id',
            0,
            $req['qty']
        );
        $ids = array_keys($owned);
        [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'tc');
        $DB->set_field_select('block_playerhud_inventory', 'source', 'consumed', "id $insql", $inparams);
    }

    foreach ($rewards as $reward) {
        for ($i = 0; $i < $reward['qty']; $i++) {
            $DB->insert_record('block_playerhud_inventory', (object) [
                'userid'      => $userid,
                'itemid'      => $reward['itemid'],
                'dropid'      => 0,
                'source'      => 'shop',
                'timecreated' => $now,
                'xpawarded'   => 0,
            ]);
        }
    }

    $DB->insert_record('block_playerhud_trade_log', (object) [
        'tradeid'     => $trade->id,
        'userid'      => $userid,
        'timecreated' => $now,
    ]);
}

// Dave precisa de uma 2ª chave para pagar a troca "Chaves por Pergaminho" (custo: 2 chaves).
seed_give_extra_item($students[3]->id, $itemkey->id, $dropcave->id);
$tradekeys = $DB->get_record('block_playerhud_trades', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Troca: Chaves por Pergaminho',
]);
if ($tradekeys) {
    seed_execute_trade(
        $students[3]->id,
        $tradekeys,
        [['itemid' => $itemkey->id, 'qty' => 2]],
        [['itemid' => $itemscroll->id, 'qty' => 1]]
    );
    cli_writeln("Dave realizou a troca 'Chaves por Pergaminho'.");
}

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

$questfirst = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Primeiro Passo',
]);
$queststudioso = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Estudioso',
]);
$questxptotal = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Colecionador de XP',
]);
$questtrades = $DB->get_record('block_playerhud_quests', [
    'blockinstanceid' => $instanceid,
    'name'            => 'Negociante',
]);

// Alice: Primeiro Passo (+25 XP). Items(40) + quest(25) = 65 XP.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[0]->id);
}

// Bob: Primeiro Passo (+25) + Estudioso (+75, reward=chave). scroll(50)+q1(25)+q2(75) = 150 XP.
// Já atinge o alvo de "Colecionador de XP" (150) mas fica sem reivindicar de propósito, para
// mostrar o estado "pronto para resgatar" na tela de missões.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[1]->id);
}
if ($queststudioso) {
    seed_claim_quest($instanceid, $queststudioso, $students[1]->id);
}

// Carol: Primeiro Passo (+25) + Colecionador de XP (+40, já tinha 155 XP acumulado).
// espada(30)+gema(100)+quest(25)+quest(40) = 195 XP.
if ($questfirst) {
    seed_claim_quest($instanceid, $questfirst, $students[2]->id);
}
if ($questxptotal) {
    seed_claim_quest($instanceid, $questxptotal, $students[2]->id);
}

// Dave: Negociante (+40, após a troca de chaves por pergaminho). chave×2(20)+quest(40) = 60 XP.
if ($questtrades) {
    seed_claim_quest($instanceid, $questtrades, $students[3]->id);
}

// Eve: sem quests. Eve=10 XP.

cli_writeln("Missões concluídas registradas.");

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
cli_writeln("XP recalculado a partir dos eventos reais.");

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

// Seção 1 — Card (padrão): cartão interativo completo com botão.
$cmpage = seed_create_module($course, 'page', [
    'name'          => 'Leitura: A História do Reino',
    'intro'         => 'Colete sua recompensa por ler esta página.',
    'content'       => '<p>Era uma vez um reino onde aventureiros ganhavam XP explorando o mundo.</p>'
        . '[PLAYERHUD_DROP code=' . $droplibrary->code . ']',
    'contentformat' => FORMAT_HTML,
    'section'       => 1,
    'display'       => 5,
]);

// Seção 1 — Image: ícone flutuante clicável.
$cmpageforest = seed_create_module($course, 'page', [
    'name'          => 'Exploração: A Floresta Sombria',
    'intro'         => 'Uma recompensa oculta aguarda dentro da floresta.',
    'content'       => '<p>A floresta esconde segredos ancestrais.'
        . ' Os corajosos que entrarem podem encontrar algo útil.</p>'
        . '[PLAYERHUD_DROP code=' . $dropforest->code . ' mode=image]',
    'contentformat' => FORMAT_HTML,
    'section'       => 1,
    'display'       => 5,
]);

// Seção 2 — Text: link de texto simples.
$cmpagetavern = seed_create_module($course, 'page', [
    'name'          => 'Descanso: A Taverna do Porto',
    'intro'         => 'Descanse e recupere suas forças — algo te aguarda aqui.',
    'content'       => '<p>Uma lareira quente, uma bebida gelada e um estalajadeiro simpático.'
        . ' Descanse aqui para recuperar suas forças.</p>'
        . '[PLAYERHUD_DROP code=' . $droptavern->code . ' mode=text]',
    'contentformat' => FORMAT_HTML,
    'section'       => 2,
    'display'       => 5,
]);

// Seção 2 — Card: cartão interativo completo com botão.
$cmassign = seed_create_module($course, 'assign', [
    'name'                                => 'Tarefa: Relato de Aventura',
    'intro'                               => '[PLAYERHUD_DROP code=' . $dropcave->code . ']',
    'section'                             => 2,
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

// Seção 3 — Card: item secreto renderiza como mistério até ser coletado.
$cmassignsecret = seed_create_module($course, 'assign', [
    'name'                                => 'Desafio: O Cofre do Dragão',
    'intro'                               => '[PLAYERHUD_DROP code=' . $dropsecret->code . ']',
    'section'                             => 3,
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
    // Alice, Bob, Carol concluíram a leitura.
    foreach ([$students[0], $students[1], $students[2]] as $s) {
        $completion->update_state($cmpage, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Atividade 'Leitura' criada — Alice, Bob, Carol marcados como concluídos.");
} else {
    cli_writeln("Módulo 'page' indisponível — atividade de leitura ignorada.");
}

if ($cmpageforest) {
    // Carol e Dave exploraram a floresta.
    foreach ([$students[2], $students[3]] as $s) {
        $completion->update_state($cmpageforest, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Atividade 'Floresta Sombria' criada — Carol e Dave marcados como concluídos.");
} else {
    cli_writeln("Módulo 'page' indisponível — atividade da floresta ignorada.");
}

if ($cmpagetavern) {
    // Alice, Dave e Eve descansaram na taverna.
    foreach ([$students[0], $students[3], $students[4]] as $s) {
        $completion->update_state($cmpagetavern, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Atividade 'Taverna do Porto' criada — Alice, Dave e Eve marcados como concluídos.");
} else {
    cli_writeln("Módulo 'page' indisponível — atividade da taverna ignorada.");
}

if ($cmassign) {
    // Bob e Carol concluíram a tarefa.
    foreach ([$students[1], $students[2]] as $s) {
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $s->id);
    }
    cli_writeln("Atividade 'Relato de Aventura' criada — Bob e Carol marcados como concluídos.");
} else {
    cli_writeln("Módulo 'assign' indisponível — atividade de tarefa ignorada.");
}

if ($cmassignsecret) {
    // Carol concluiu o desafio secreto.
    $completion->update_state($cmassignsecret, COMPLETION_COMPLETE, $students[2]->id);
    cli_writeln("Atividade 'Cofre do Dragão' criada — Carol marcada como concluída.");
} else {
    cli_writeln("Módulo 'assign' indisponível — desafio secreto ignorado.");
}

// 16b. Late Penalty integration (demo for the "Extensão de Prazo" item power).
//
// Only the "before" state is seeded here: Eve's real late submission, graded, with a real
// penalty applied by local_latepenalty's own event observer. Redeeming the item to waive the
// penalty happens live, as Eve, during the actual screenshot session — that shows a genuine
// before/after grade change instead of a pre-baked one.
if ($cmassign && class_exists('\local_latepenalty\recalculator')) {
    $eve = $students[4];
    $duedate = time() - (3 * DAYSECS);

    $DB->set_field('assign', 'duedate', $duedate, ['id' => $cmassign->instance]);

    // Upsert, never skip-if-exists: a stale rule can already sit here for this cmid, left behind
    // by an unrelated course module deleted in the past — local_latepenalty does not clean up
    // local_latepenalty_rules on course/module deletion, and course_modules.id is a site-wide
    // sequence, so an old disabled rule can silently "occupy" a freshly created cmid.
    $rulerecord = (object) [
        'cmid'               => $cmassign->id,
        'enabled'            => 1,
        'daily_penalty'      => 5.00,
        'max_penalty'        => 30.00,
        'recalc_on_deadline' => 1,
        'recalc_on_rate'     => 1,
        'last_deadline'      => $duedate,
    ];
    $existingrule = $DB->get_record('local_latepenalty_rules', ['cmid' => $cmassign->id]);
    if ($existingrule) {
        $rulerecord->id = $existingrule->id;
        $DB->update_record('local_latepenalty_rules', $rulerecord);
    } else {
        $DB->insert_record('local_latepenalty_rules', $rulerecord);
    }

    if (!$DB->record_exists('assign_submission', ['assignment' => $cmassign->instance, 'userid' => $eve->id])) {
        // Submitted 2 days after the due date.
        $DB->insert_record('assign_submission', (object) [
            'assignment'   => $cmassign->instance,
            'userid'       => $eve->id,
            'timecreated'  => $duedate + (2 * DAYSECS),
            'timemodified' => $duedate + (2 * DAYSECS),
            'status'       => 'submitted',
            'groupid'      => 0,
            'attemptnumber' => 0,
            'latest'       => 1,
        ]);
    }

    require_once($CFG->libdir . '/gradelib.php');
    // Full marks before grading — local_latepenalty's own observer applies the real percentage
    // penalty in reaction to this grade_update() call, exactly like a teacher grading the
    // submission through the normal assign UI would trigger it. Safe to call on every run
    // (idempotent): it always (re)writes the same rawgrade to the gradebook's own grade_grades,
    // which is not the same table as mod_assign's assign_grades.
    grade_update(
        'mod/assign',
        $course->id,
        'mod',
        'assign',
        $cmassign->instance,
        0,
        (object) ['userid' => $eve->id, 'rawgrade' => 100]
    );

    $itemextension = seed_upsert_item(
        $instanceid,
        'Extensão de Prazo',
        0,
        'Adia o prazo da Tarefa: Relato de Aventura e dispensa a penalidade de atraso.',
        0,
        '⏳'
    );
    $DB->set_field('block_playerhud_items', 'action_type', 'deadline_extension', ['id' => $itemextension->id]);
    $DB->set_field(
        'block_playerhud_items',
        'action_value',
        json_encode(['days' => 2, 'cmid' => $cmassign->id]),
        ['id' => $itemextension->id]
    );

    $dropextension = seed_upsert_drop($instanceid, $itemextension->id, 'Recompensa da Diretoria', 1, 0);
    seed_give_item($eve->id, $itemextension->id, $dropextension->id);

    cli_writeln("Late Penalty configurado — Eve submeteu atrasado 'Relato de Aventura' e recebeu");
    cli_writeln("o item 'Extensão de Prazo' para resgatar durante a captura de prints.");
} else if ($cmassign) {
    cli_writeln("local_latepenalty não está instalado — integração de Extensão de Prazo ignorada.");
}

// 16c. PlayerGroup integration (demo for group ranking + HUD header group badge).
if (class_exists('\mod_playergroup\api\group_info')) {
    require_once($CFG->dirroot . '/group/lib.php');

    \core\session\manager::set_user(get_admin());
    $cmplayergroup = seed_create_module($course, 'playergroup', [
        'name'          => 'Formação de Esquadrões',
        'intro'         => 'Forme seu esquadrão para aparecer no ranking de grupos.',
        'section'       => 1,
        'groupingmode'  => 'new',
        'groupingname'  => 'Esquadrões',
        'minmembers'    => 2,
        'maxmembers'    => 5,
        'canleave'      => 1,
        'deleteemptygroups' => 1,
        'deletegroups'  => 0,
        'grade'         => 0,
    ]);
    \core\session\manager::set_user($origuser);

    if ($cmplayergroup && !$DB->record_exists('groups', ['courseid' => $course->id, 'name' => 'Esquadrão Alfa'])) {
        $squadmembers = [$students[0], $students[1], $students[2]]; // Alice, Bob, Carol.

        $groupid = groups_create_group((object) [
            'courseid'    => $course->id,
            'name'        => 'Esquadrão Alfa',
            'description' => '',
        ]);

        $playergroupinstance = $DB->get_record('playergroup', ['id' => $cmplayergroup->instance], '*', MUST_EXIST);
        if (!empty($playergroupinstance->groupingid)) {
            groups_assign_grouping($playergroupinstance->groupingid, $groupid);
        }

        foreach ($squadmembers as $member) {
            groups_add_member($groupid, $member->id);
        }

        $DB->insert_record('playergroup_meta', (object) [
            'groupid'       => $groupid,
            'playergroupid' => $cmplayergroup->instance,
            'creatorid'     => $squadmembers[0]->id,
            'badge'         => '🛡️',
            'privacy'       => 0,
            'password'      => '',
            'timecreated'   => $now,
            'timemodified'  => $now,
        ]);

        cli_writeln("Esquadrão Alfa criado — Alice, Bob e Carol agrupados (Dave e Eve ficam sem grupo).");
    }
} else {
    cli_writeln("mod_playergroup não está instalado — integração de Ranking de Grupos ignorada.");
}

// 17. Summary.

$wwwroot = $CFG->wwwroot;
$courseurl = "{$wwwroot}/course/view.php?id={$course->id}";

cli_writeln("\n" . str_repeat('=', 60));
cli_writeln("SEED CONCLUÍDO");
cli_writeln(str_repeat('=', 60));
cli_writeln("Curso:    {$courseurl}");
cli_writeln("Bloco ID: {$instanceid}");
cli_writeln("");
cli_writeln("USUÁRIOS (senha: " . SEED_PASSWORD . ")");
cli_writeln(str_pad("Username", 20) . str_pad("Nome", 20) . "XP real");
cli_writeln(str_repeat('-', 55));
cli_writeln(str_pad($teacher->username, 20) . str_pad($teacher->firstname . ' ' . $teacher->lastname, 20) . "Professor");
foreach ($students as $s) {
    $xp = $finalxp[$s->id];
    cli_writeln(str_pad($s->username, 20) . str_pad($s->firstname . ' ' . $s->lastname, 20) . "{$xp} XP");
}
cli_writeln(str_repeat('=', 60));
cli_writeln("Para recriar tudo do zero: php blocks/playerhud/cli/seed_pt_br.php --reset");
