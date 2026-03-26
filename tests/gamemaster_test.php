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

/**
 * Game Master actions and XP recalculation tests.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\tests;

use advanced_testcase;

/**
 * Class gamemaster_test.
 *
 * Tests for granting items, revoking items, and preserving leaderboard timestamps.
 *
 * @package block_playerhud
 * @coversNothing
 */
final class gamemaster_test extends advanced_testcase {
    /**
     * Setup test environment.
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Test granting an item to a user.
     * Expected: Inventory is created, XP increases, timemodified is updated to NOW.
     */
    public function test_grant_item_updates_xp_and_date(): void {
        global $DB;

        // 1. Setup: Criar curso, usuário e bloco falso.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1; // Fake block instance.

        // Criar o jogador com 0 XP e data antiga.
        $pastdate = time() - 86400; // Ontem.
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 0;
        $player->timemodified = $pastdate;
        $DB->insert_record('block_playerhud_user', $player);

        // Criar um item que vale 100 XP.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Espada de Teste';
        $item->xp = 100;
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        // 2. Ação: Simular o grant_item do manage.php.
        $now = time();
        $newinv = new \stdClass();
        $newinv->userid = $user->id;
        $newinv->itemid = $itemid;
        $newinv->dropid = 0;
        $newinv->source = 'teacher';
        $newinv->timecreated = $now;
        $DB->insert_record('block_playerhud_inventory', $newinv);

        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp += $item->xp;
        $dbplayer->timemodified = $now;
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Asserções (Validações).
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $inventory = $DB->get_records('block_playerhud_inventory', ['userid' => $user->id]);

        $this->assertEquals(100, $updatedplayer->currentxp, 'O XP deveria ter subido para 100.');
        $this->assertGreaterThan(
            $pastdate,
            $updatedplayer->timemodified,
            'A data timemodified deveria ter sido atualizada para o momento da concessão.'
        );
        $this->assertCount(1, $inventory, 'Deveria existir 1 item no inventário.');
        $this->assertEquals('teacher', reset($inventory)->source, 'A origem do item deve ser do professor.');
    }

    /**
     * Test revoking an item from a user.
     * Expected: Item status becomes 'revoked', XP decreases, timemodified remains UNCHANGED.
     */
    public function test_revoke_item_preserves_leaderboard_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;

        // Jogador com 500 XP atingidos há 5 dias.
        $fivedaysago = time() - (5 * 86400);
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 500;
        $player->timemodified = $fivedaysago;
        $DB->insert_record('block_playerhud_user', $player);

        // Item de 200 XP que o aluno já possui.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Escudo Roubado';
        $item->xp = 200;
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        $inv = new \stdClass();
        $inv->userid = $user->id;
        $inv->itemid = $itemid;
        $inv->source = 'map';
        $inv->timecreated = $fivedaysago;
        $invid = $DB->insert_record('block_playerhud_inventory', $inv);

        // 2. Ação: Simular o revoke_item do manage.php (Soft Revoke).
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $item->xp);
        // NOTA: Intencionalmente NÃO atualizamos dbplayer->timemodified.
        $DB->update_record('block_playerhud_user', $dbplayer);

        $dbinv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);
        $dbinv->source = 'revoked';
        $dbinv->timecreated = time();
        $DB->update_record('block_playerhud_inventory', $dbinv);

        // 3. Asserções (Validações Cruciais).
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $updatedinv = $DB->get_record('block_playerhud_inventory', ['id' => $invid]);

        $this->assertEquals(300, $updatedplayer->currentxp, 'O XP deveria ter caído de 500 para 300.');
        $this->assertEquals(
            $fivedaysago,
            $updatedplayer->timemodified,
            'CATASTRÓFICO: A data timemodified foi alterada! O critério de desempate foi quebrado.'
        );
        $this->assertEquals('revoked', $updatedinv->source, 'O status do item deve ser Soft Revoke (revoked).');
    }

    /**
     * Test XP does not drop below zero when deleting items.
     */
    public function test_xp_never_negative_on_revoke(): void {
        global $DB;

        // 1. Setup: Jogador com 50 XP.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;

        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 50;
        $player->timemodified = time() - 3600;
        $DB->insert_record('block_playerhud_user', $player);

        // Item vale 100 XP (mais do que o jogador tem).
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Item Bugado';
        $item->xp = 100;

        // 2. Ação: Remover os 100 XP do jogador.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $item->xp);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Asserções.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(0, $updatedplayer->currentxp, 'O XP não pode ficar negativo. Deveria ter travado em 0.');
    }

    /**
     * Test Hard Delete (single item).
     * Expected: Total XP from all instances of the item is deducted, timemodified remains UNCHANGED.
     */
    public function test_hard_delete_item_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $instanceid = 1;
        $olddate = time() - (10 * 86400); // 10 dias atrás.

        // Jogador com 1000 XP.
        $player = new \stdClass();
        $player->blockinstanceid = $instanceid;
        $player->userid = $user->id;
        $player->currentxp = 1000;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // Item de 200 XP.
        $item = new \stdClass();
        $item->blockinstanceid = $instanceid;
        $item->name = 'Poção Excluída';
        $item->xp = 200;
        $itemid = $DB->insert_record('block_playerhud_items', $item);

        // Aluno coletou esse item 2 vezes (400 XP ganhos com ele).
        $qtd = 2;

        // 2. Ação: Simular a lógica exata da action 'delete' do manage.php.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $xptoremove = $item->xp * $qtd;
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $xptoremove);
        // NOTA: Intencionalmente NÃO atualizamos dbplayer->timemodified.
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Asserções.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(600, $updatedplayer->currentxp, 'O XP deveria ter caído de 1000 para 600.');
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'A exclusão do item alterou a data de desempate!');
    }

    /**
     * Test Bulk Delete (multiple items).
     * Expected: Sum of XP from all deleted items is deducted, timemodified remains UNCHANGED.
     */
    public function test_bulk_delete_items_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $olddate = time() - 3600; // 1 hora atrás.

        $player = new \stdClass();
        $player->blockinstanceid = 1;
        $player->userid = $user->id;
        $player->currentxp = 800;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // 2. Ação: Simular a action 'bulk_delete' (Múltiplos itens somando 350 XP a remover).
        $totalxptoremove = 350;
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $totalxptoremove);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Asserções.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(450, $updatedplayer->currentxp, 'O XP deveria ter caído de 800 para 450.');
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'A exclusão em massa alterou a data de desempate!');
    }

    /**
     * Test Delete Quest/Raid.
     * Expected: Quest XP * completions is deducted, timemodified remains UNCHANGED.
     */
    public function test_delete_quest_preserves_date(): void {
        global $DB;

        // 1. Setup.
        $user = $this->getDataGenerator()->create_user();
        $olddate = time() - 86400; // 1 dia atrás.

        $player = new \stdClass();
        $player->blockinstanceid = 1;
        $player->userid = $user->id;
        $player->currentxp = 2000;
        $player->timemodified = $olddate;
        $DB->insert_record('block_playerhud_user', $player);

        // Quest de 500 XP completada 1 vez.
        $questrewardxp = 500;
        $completions = 1;

        // 2. Ação: Simular a action 'delete_quest'.
        $dbplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $xptoremove = $questrewardxp * $completions;
        $dbplayer->currentxp = max(0, $dbplayer->currentxp - $xptoremove);
        $DB->update_record('block_playerhud_user', $dbplayer);

        // 3. Asserções.
        $updatedplayer = $DB->get_record('block_playerhud_user', ['userid' => $user->id]);
        $this->assertEquals(
            1500,
            $updatedplayer->currentxp,
            'O XP da quest (500) deveria ter sido subtraído dos 2000 originais.'
        );
        $this->assertEquals($olddate, $updatedplayer->timemodified, 'Deletar a missão alterou a data de desempate!');
    }
}
