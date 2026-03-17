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
 * Version metadata for the repository_pluginname plugin.
 *
 * @package   repository_pluginname
 * @copyright 2026, author_fullname <author_link>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * Testes automatizados para a classe game.
 */
class game_test extends advanced_testcase {

    /**
     * Testa se o cálculo de níveis e progressão de XP está correto.
     */
    public function test_get_game_stats_level_calculation() {
        // Zera o banco de dados do Moodle fantasma após esse teste
        $this->resetAfterTest();

        // 1. PREPARAÇÃO (Cenário)
        // Simulamos a configuração do professor no bloco
        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels = 20;

        // 2. AÇÃO (O que queremos testar)
        // Simulamos um aluno com 250 XP (O ID do bloco será 0 pois não precisamos do banco agora)
        $currentxp = 250;
        $stats = \block_playerhud\game::get_game_stats($config, 0, $currentxp);

        // 3. VALIDAÇÃO (As regras)
        // Se cada nível custa 100 XP, com 250 XP ele TEM que estar no nível 3
        $this->assertEquals(3, $stats['level']);

        // Se ele está no nível 3 (limite 300 XP) e tem 250 XP, tem que faltar 50 XP para o próximo nível
        $this->assertEquals(50, $stats['xp_next']);

        // O nível máximo do jogo é 20, então ele NÃO pode estar no nível máximo ainda
        $this->assertFalse($stats['is_max']);
    }

    /**
     * Testa a trava do Nível Máximo (Level Cap).
     */
    public function test_get_game_stats_max_level_cap() {
        $this->resetAfterTest();

        $config = new \stdClass();
        $config->xp_per_level = 100;
        $config->max_levels = 10; // Limite baixo para testar

        // Aluno com 5000 XP (passou muito do limite)
        $stats = \block_playerhud\game::get_game_stats($config, 0, 5000);

        // Mesmo com XP infinito, a trava do sistema tem que segurá-lo no nível 10
        $this->assertEquals(10, $stats['level']);
        $this->assertTrue($stats['is_max']);
    }
}
