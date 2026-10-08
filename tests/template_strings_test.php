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
 * Tests that visible and announced texts in the templates come from language strings.
 *
 * @package    block_playerhud
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud;

use advanced_testcase;

/**
 * A text written straight into a template cannot be translated or adjusted in the language
 * customisation screen. Each test customises the string through the local language pack and
 * checks the rendered output follows it, which a literal in the template cannot do.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class template_strings_test extends advanced_testcase {
    /**
     * Overrides plugin strings through the local language pack, the way an administrator does.
     *
     * @param array $strings Replacement texts keyed by string identifier.
     */
    private function customise_strings(array $strings): void {
        global $CFG;

        $dir = $CFG->dataroot . '/lang/en_local';
        make_writable_directory($dir);
        $php = "<?php\ndefined('MOODLE_INTERNAL') || die();\n";
        foreach ($strings as $key => $value) {
            $php .= '$string[' . var_export($key, true) . '] = ' . var_export($value, true) . ";\n";
        }
        file_put_contents($dir . '/block_playerhud.php', $php);
        get_string_manager()->reset_caches();
    }

    /**
     * Renders a template with the example context written in its own header.
     *
     * @param string $template Template name, without the component.
     * @param callable|null $adjust Optional change to the decoded context before rendering.
     * @return string The rendered HTML.
     */
    private function render_with_example_context(string $template, ?callable $adjust = null): string {
        global $CFG, $OUTPUT;

        $source = file_get_contents($CFG->dirroot . '/blocks/playerhud/templates/' . $template . '.mustache');
        $this->assertSame(1, preg_match('/Example context \(json\):\s*(\{.*?\n    \})\s*\n\}\}/s', $source, $matches));
        $context = json_decode($matches[1], true);
        // A "str" key in an example context would shadow the {{#str}} helper being tested.
        unset($context['str']);
        if ($adjust !== null) {
            $context = $adjust($context);
        }

        return $OUTPUT->render_from_template('block_playerhud/' . $template, $context);
    }

    /**
     * The medals of the individual and group rankings are announced by screen readers through an
     * aria-label, which used to be the English word "Rank" in the template.
     */
    public function test_ranking_medal_label_is_a_language_string(): void {
        $this->resetAfterTest();
        $this->customise_strings(['rank_position' => 'Posição {$a}']);

        $html = $this->render_with_example_context('view_ranking', static function (array $context): array {
            // The example only gives the individual ranking a medal; give the group ranking one too.
            $context['groups'][0]['medal_emoji'] = '🥇';
            return $context;
        });

        $this->assertSame(2, substr_count($html, 'aria-label="Posição 1"'));
        $this->assertStringNotContainsString('aria-label="Rank', $html);
    }

    /**
     * The emoji field label of the shortcode generator is a language string.
     */
    public function test_generator_emoji_label_is_a_language_string(): void {
        $this->resetAfterTest();
        $this->customise_strings(['gen_emoji_label' => 'Símbolo']);

        $html = $this->render_with_example_context('modal_generator');

        $this->assertMatchesRegularExpression('/<label for="customBtnEmoji"[^>]*>Símbolo<\/label>/', $html);
    }

    /**
     * The minutes unit next to the respawn field of the AI item modal is a language string.
     */
    public function test_ai_modal_respawn_unit_is_a_language_string(): void {
        $this->resetAfterTest();
        $this->customise_strings(['time_min' => 'minutos']);

        $html = $this->render_with_example_context('modal_ai');

        $this->assertMatchesRegularExpression('/<label for="ai-respawn"[^>]*>[^<]*\(minutos\)<\/label>/', $html);
        $this->assertStringNotContainsString('(min)', $html);
    }
}
