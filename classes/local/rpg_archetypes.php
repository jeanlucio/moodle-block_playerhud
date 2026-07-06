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
 * Fixed content for the wizard's RPG Classes module.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_playerhud\local;

/**
 * Provides the pre-defined, tone-specific character archetypes and opening story chapter
 * used by the wizard's RPG Classes module.
 *
 * A class in this plugin can only ever be assigned to a student through a story choice
 * (`block_playerhud_choices.set_class_id`), so classes and Chapter 1 are always generated
 * together. Chapter 1 is a fixed narrative skeleton, never AI-generated: the class-selection
 * moment is too critical to depend on AI output quality. Later chapters (not covered by this
 * class) reuse `ai\generator::generate_story()` freely by theme.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rpg_archetypes {
    /** @var int Base HP for the front-line archetype. */
    private const HP_FRONT = 150;

    /** @var int Base HP for the insight archetype. */
    private const HP_INSIGHT = 80;

    /** @var int Base HP for the precision archetype. */
    private const HP_PRECISION = 100;

    /**
     * Returns the tone-specific archetype pack: 3 classes and a 6-node opening chapter.
     *
     * @param string $tonekey One of 'fantasy', 'scifi', 'mystery', 'academic'.
     * @return array{
     *     chapter_title: string,
     *     classes: array<int, array{role: string, name: string, description: string,
     *         emoji: string, base_hp: int}>,
     *     nodes: array<int, array{content: string, is_start: bool,
     *         choices: array<int, array{text: string, target: int, karma_delta?: int,
     *             class_role?: string}>}>
     * }
     */
    public static function get_pack(string $tonekey): array {
        $builders = [
            'fantasy' => [self::class, 'fantasy_pack'],
            'scifi' => [self::class, 'scifi_pack'],
            'mystery' => [self::class, 'mystery_pack'],
            'academic' => [self::class, 'academic_pack'],
        ];

        $builder = $builders[$tonekey] ?? $builders['fantasy'];

        return call_user_func($builder);
    }

    /**
     * Builds the classes array shared by all tone packs, driven by lang strings.
     *
     * @param string $tonekey The tone key used as the lang string prefix.
     * @return array<int, array{role: string, name: string, description: string,
     *     emoji: string, base_hp: int}>
     */
    private static function build_classes(string $tonekey): array {
        return [
            [
                'role' => 'front',
                'name' => get_string("rpg_{$tonekey}_class_front_name", 'block_playerhud'),
                'description' => get_string("rpg_{$tonekey}_class_front_desc", 'block_playerhud'),
                'emoji' => self::front_emoji($tonekey),
                'base_hp' => self::HP_FRONT,
            ],
            [
                'role' => 'insight',
                'name' => get_string("rpg_{$tonekey}_class_insight_name", 'block_playerhud'),
                'description' => get_string("rpg_{$tonekey}_class_insight_desc", 'block_playerhud'),
                'emoji' => self::insight_emoji($tonekey),
                'base_hp' => self::HP_INSIGHT,
            ],
            [
                'role' => 'precision',
                'name' => get_string("rpg_{$tonekey}_class_precision_name", 'block_playerhud'),
                'description' => get_string("rpg_{$tonekey}_class_precision_desc", 'block_playerhud'),
                'emoji' => self::precision_emoji($tonekey),
                'base_hp' => self::HP_PRECISION,
            ],
        ];
    }

    /**
     * Returns the front-line archetype emoji for a tone.
     *
     * @param string $tonekey The tone key.
     * @return string The emoji character.
     */
    private static function front_emoji(string $tonekey): string {
        $map = [
            'fantasy' => "\u{1F93A}",
            'scifi' => "\u{1F469}\u{200D}\u{1F680}",
            'mystery' => "\u{1F575}\u{FE0F}\u{200D}\u{2642}\u{FE0F}",
            'academic' => "\u{1F469}\u{200D}\u{1F3EB}",
        ];

        return $map[$tonekey] ?? $map['fantasy'];
    }

    /**
     * Returns the insight archetype emoji for a tone.
     *
     * @param string $tonekey The tone key.
     * @return string The emoji character.
     */
    private static function insight_emoji(string $tonekey): string {
        $map = [
            'fantasy' => "\u{1F9D9}\u{200D}\u{2640}\u{FE0F}",
            'scifi' => "\u{1F468}\u{200D}\u{1F4BB}",
            'mystery' => "\u{1F9D0}",
            'academic' => "\u{1F468}\u{200D}\u{1F52C}",
        ];

        return $map[$tonekey] ?? $map['fantasy'];
    }

    /**
     * Returns the precision archetype emoji for a tone.
     *
     * @param string $tonekey The tone key.
     * @return string The emoji character.
     */
    private static function precision_emoji(string $tonekey): string {
        $map = [
            'fantasy' => "\u{1F3F9}",
            'scifi' => "\u{1F3AF}",
            'mystery' => "\u{1F469}\u{200D}\u{1F52C}",
            'academic' => "\u{1F9D1}\u{200D}\u{1F4BB}",
        ];

        return $map[$tonekey] ?? $map['fantasy'];
    }

    /**
     * Builds the 6-node opening chapter shared by all tone packs, driven by lang strings.
     *
     * @param string $tonekey The tone key used as the lang string prefix.
     * @return array<int, array{content: string, is_start: bool,
     *     choices: array<int, array{text: string, target: int, karma_delta?: int,
     *         class_role?: string}>}>
     */
    private static function build_nodes(string $tonekey): array {
        return [
            [
                'content' => get_string("rpg_{$tonekey}_node0", 'block_playerhud'),
                'is_start' => true,
                'choices' => [
                    ['text' => get_string('wizard_rpg_continue', 'block_playerhud'), 'target' => 1],
                ],
            ],
            [
                'content' => get_string("rpg_{$tonekey}_node1", 'block_playerhud'),
                'is_start' => false,
                'choices' => [
                    [
                        'text' => get_string("rpg_{$tonekey}_choice_help", 'block_playerhud'),
                        'target' => 2,
                        'karma_delta' => 10,
                    ],
                    [
                        'text' => get_string("rpg_{$tonekey}_choice_direct", 'block_playerhud'),
                        'target' => 2,
                        'karma_delta' => 0,
                    ],
                ],
            ],
            [
                'content' => get_string("rpg_{$tonekey}_node2", 'block_playerhud'),
                'is_start' => false,
                'choices' => [
                    [
                        'text' => get_string("rpg_{$tonekey}_class_front_name", 'block_playerhud'),
                        'target' => 3,
                        'class_role' => 'front',
                    ],
                    [
                        'text' => get_string("rpg_{$tonekey}_class_insight_name", 'block_playerhud'),
                        'target' => 4,
                        'class_role' => 'insight',
                    ],
                    [
                        'text' => get_string("rpg_{$tonekey}_class_precision_name", 'block_playerhud'),
                        'target' => 5,
                        'class_role' => 'precision',
                    ],
                ],
            ],
            [
                'content' => get_string("rpg_{$tonekey}_node3", 'block_playerhud'),
                'is_start' => false,
                'choices' => [],
            ],
            [
                'content' => get_string("rpg_{$tonekey}_node4", 'block_playerhud'),
                'is_start' => false,
                'choices' => [],
            ],
            [
                'content' => get_string("rpg_{$tonekey}_node5", 'block_playerhud'),
                'is_start' => false,
                'choices' => [],
            ],
        ];
    }

    /**
     * Builds the Fantasy tone pack.
     *
     * @return array{chapter_title: string, classes: array, nodes: array}
     */
    private static function fantasy_pack(): array {
        return [
            'chapter_title' => get_string('rpg_fantasy_chapter_title', 'block_playerhud'),
            'classes' => self::build_classes('fantasy'),
            'nodes' => self::build_nodes('fantasy'),
        ];
    }

    /**
     * Builds the Sci-Fi tone pack.
     *
     * @return array{chapter_title: string, classes: array, nodes: array}
     */
    private static function scifi_pack(): array {
        return [
            'chapter_title' => get_string('rpg_scifi_chapter_title', 'block_playerhud'),
            'classes' => self::build_classes('scifi'),
            'nodes' => self::build_nodes('scifi'),
        ];
    }

    /**
     * Builds the Mystery/Investigation tone pack.
     *
     * @return array{chapter_title: string, classes: array, nodes: array}
     */
    private static function mystery_pack(): array {
        return [
            'chapter_title' => get_string('rpg_mystery_chapter_title', 'block_playerhud'),
            'classes' => self::build_classes('mystery'),
            'nodes' => self::build_nodes('mystery'),
        ];
    }

    /**
     * Builds the Academic tone pack.
     *
     * @return array{chapter_title: string, classes: array, nodes: array}
     */
    private static function academic_pack(): array {
        return [
            'chapter_title' => get_string('rpg_academic_chapter_title', 'block_playerhud'),
            'classes' => self::build_classes('academic'),
            'nodes' => self::build_nodes('academic'),
        ];
    }
}
