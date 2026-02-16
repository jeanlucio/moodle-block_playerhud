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

namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;

/**
 * Rules/Help tab output renderer.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tab_rules implements renderable, templatable {
    /** @var \stdClass Block configuration. */
    protected $config;

    /**
     * Constructor.
     *
     * @param \stdClass $config Block configuration.
     */
    public function __construct($config) {
        $this->config = $config;
    }

    /**
     * Display method.
     *
     * @return string HTML content.
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/tab_rules', $this->export_for_template($OUTPUT));
    }

    /**
     * Export data for template.
     *
     * @param renderer_base $output The renderer.
     * @return array Template data.
     */
    public function export_for_template(renderer_base $output) {
        // Retrieve content. Handle legacy or array format from editor.
        $rawcontent = '';
        if (isset($this->config->help_content)) {
            if (is_array($this->config->help_content)) {
                $rawcontent = $this->config->help_content['text'];
            } else {
                $rawcontent = $this->config->help_content;
            }
        }

        // Fallback to default if somehow empty.
        if (empty($rawcontent)) {
            $rawcontent = get_string('help_pagedefault', 'block_playerhud');
        }

        // Process filters (essential for media/links).
        $content = format_text($rawcontent, FORMAT_HTML, ['noclean' => true]);

        return [
            'help_content' => $content,
        ];
    }
}
