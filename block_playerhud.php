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
 * PlayerHUD Block main class.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * block_playerhud class.
 */
class block_playerhud extends block_base {

    /**
     * Initialize block title and properties.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_playerhud');
    }

    /**
     * Get block content for display.
     *
     * @return stdClass|string
     */
public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }

        global $USER, $COURSE, $OUTPUT;

        $this->content = new \stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        try {
            $context = \context_block::instance($this->instance->id);
            $player = \block_playerhud\game::get_player($this->instance->id, $USER->id);
            
            $config = unserialize(base64_decode($this->instance->configdata));
            if (!$config) $config = new \stdClass();

            $stats = \block_playerhud\game::get_game_stats($config, $this->instance->id, $player->currentxp);

            // [Lógica de Itens Recentes Mantida igual ao original...]
            $recentitems = [];
            $seen_items = [];
            $rawinventory = \block_playerhud\game::get_inventory($USER->id, $this->instance->id);
            $limit = 6; 
            $count = 0;
            
            foreach ($rawinventory as $invitem) {
                if ($count >= $limit) break;
                if (in_array($invitem->id, $seen_items)) continue;
                $seen_items[] = $invitem->id;

                $media = \block_playerhud\utils::get_item_display_data($invitem, $context);
                
                $recentitems[] = [
                    'name' => format_string($invitem->name),
                    'xp' => '+'.$invitem->xp.' XP',
                    'image' => $media['is_image'] ? $media['url'] : strip_tags($media['content']),
                    'isimage' => $media['is_image'],
                    'description' => !empty($invitem->description) ? format_text($invitem->description, FORMAT_HTML) : '',
                    'date' => userdate($invitem->collecteddate, get_string('strftimedatefullshort', 'langconfig'))
                ];
                $count++;
            }

            $isteacher = has_capability('block/playerhud:manage', $context);
            $manageurl = '';
            if ($isteacher) {
                $url = new \moodle_url('/blocks/playerhud/manage.php', ['id' => $COURSE->id, 'instanceid' => $this->instance->id]);
                $manageurl = $url->out();
            }

            $xp_total_game = isset($stats['total_game_xp']) ? $stats['total_game_xp'] : 0;
            $xp_display = $player->currentxp . ' / ' . $xp_total_game . ' XP';
            if ($player->currentxp >= $xp_total_game && $xp_total_game > 0) {
                $xp_display .= ' 🏆';
            }

            $renderdata = [
                'username'    => fullname($USER),
                'userpicture' => $OUTPUT->user_picture($USER, ['size' => 100]), 
                'xp'          => $xp_display,
                'level'       => $stats['level'] . ' / ' . $stats['max_levels'],
                'level_class' => $stats['level_class'],
                'progress'    => $stats['progress'],
                'viewurl'     => (new \moodle_url('/blocks/playerhud/view.php', ['id' => $COURSE->id, 'instanceid' => $this->instance->id]))->out(),
                'isteacher'   => $isteacher,
                'manageurl'   => $manageurl,
                'has_items'   => !empty($recentitems),
                'items'       => $recentitems
            ];

            // Renderiza o Sidebar
            $this->content->text = $OUTPUT->render_from_template('block_playerhud/sidebar_view', $renderdata);

            // JS Call
            $jsvars = [
                'strings' => [
                    'confirm_title' => get_string('confirmation', 'admin'),
                    'yes' => get_string('yes'),
                    'cancel' => get_string('cancel'),
                    'no_desc' => get_string('no_description', 'block_playerhud')
                ]
            ];
            $this->page->requires->js_call_amd('block_playerhud/view', 'init', [$jsvars]);
            
            // Injeta o Modal via Template (Limpo!)
            $this->content->text .= $OUTPUT->render_from_template('block_playerhud/modal_item', []);

        } catch (\Exception $e) {
            if (debugging()) {
                $this->content->text = 'Error: ' . $e->getMessage();
            }
        }

        return $this->content;
    }

    /**
     * Allow multiple instances of the block in the same course.
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Define where this block can be added.
     */
    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'my' => true
        ];
    }
    
    /**
     * Enable block configuration.
     */
    public function has_config() {
        return true;
    }
}
