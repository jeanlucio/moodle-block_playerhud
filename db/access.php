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
 * Capability definitions for the PlayerHUD block.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    // Ability to add a new PlayerHUD block to a page.
    'block/playerhud:addinstance' => [
        'riskbitmask' => RISK_XSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],

    // Ability to add a PlayerHUD block specifically to the My Moodle (Dashboard) page.
    'block/playerhud:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],

    // Ability to view the PlayerHUD content only (render the block and its read-only tabs).
    'block/playerhud:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],

    // Ability to write gamification state: collect drops, consume/equip items, make story
    // choices and process trades. Declared separately from block/playerhud:view (which stays
    // read-only) so an admin can grant read access without also granting write access, and so
    // the contextlocking/guest-role protections the Moodle core applies to 'write' capabilities
    // actually cover these actions. clonepermissionsfrom copies every existing role/context
    // grant of block/playerhud:view onto this capability during upgrade of a site that already
    // has the plugin installed (see lib/accesslib.php::update_capabilities()) — do not add an
    // assign_capability() call for this in db/upgrade.php, the capability does not exist yet at
    // the point db/upgrade.php runs and that call would fatal on every upgrading site.
    // archetypes is also required here, mirroring block/playerhud:view's own list: on a brand
    // new install both capabilities are registered in the same batch, so
    // block/playerhud:view is not yet in update_capabilities()'s pre-batch snapshot and the
    // clone is silently skipped for that case — archetypes is what grants :interact its
    // defaults there. Same dual-declaration pattern as mod/quiz:reviewattempt cloning from
    // mod/quiz:attempt in mod/quiz/db/access.php.
    'block/playerhud:interact' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'student' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/playerhud:view',
    ],

    // Ability to manage game content (items, quests, chapters) via the management panel.
    'block/playerhud:manage' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS | RISK_PERSONAL | RISK_DATALOSS,
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
];
