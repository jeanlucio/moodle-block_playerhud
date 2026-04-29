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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Rules and Help tab output generator.
 *
 * This class handles the display logic for the rules tab, implementing a fallback
 * mechanism: if no custom content is provided by the teacher, it renders a system
 * default template instead of using a monolithic language string.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_rules implements renderable, templatable {
    /** @var stdClass Block instance configuration. */
    protected $config;
    /** @var int Block instance ID. */
    protected int $instanceid;

    /**
     * Constructor.
     *
     * @param stdClass $config Block configuration object.
     * @param int $instanceid Block instance ID.
     */
    public function __construct($config, int $instanceid = 0) {
        $this->config     = $config;
        $this->instanceid = $instanceid;
    }

    /**
     * Renders the HTML for the rules tab.
     *
     * @return string The rendered HTML content.
     */
    public function display(): string {
        global $OUTPUT;

        $data = $this->export_for_template($OUTPUT);

        // If the default fallback flag is set, render the system-wide help template.
        if (!empty($data['use_default'])) {
            return $OUTPUT->render_from_template('block_playerhud/help_default', []);
        }

        // Otherwise, render the custom content provided via block settings.
        return $OUTPUT->render_from_template('block_playerhud/tab_rules', $data);
    }

    /**
     * Prepares data for the Mustache template.
     *
     * @param renderer_base $output The renderer engine.
     * @return array Context data for the template.
     */
    public function export_for_template(renderer_base $output): array {
        $rawcontent = '';

        // Safely retrieve help_content from block configuration.
        // It can be a simple string or an array if using the editor field.
        if (isset($this->config->help_content)) {
            if (is_array($this->config->help_content)) {
                $rawcontent = $this->config->help_content['text'] ?? '';
            } else {
                $rawcontent = (string)$this->config->help_content;
            }
        }

        // Check if content is truly empty (ignoring white spaces).
        // If empty, signal the display method to use the default help_default template.
        if (empty(trim($rawcontent))) {
            return [
                'use_default' => true,
                'help_content' => '',
            ];
        }

        $context = $this->instanceid > 0
            ? \context_block::instance($this->instanceid)
            : \context_system::instance();

        // Process Moodle filters (multilang, media players, etc.) before outputting.
        $content = format_text($rawcontent, FORMAT_HTML, ['context' => $context]);

        return [
            'use_default' => false,
            'help_content' => $content,
        ];
    }
}
