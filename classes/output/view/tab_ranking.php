<?php
namespace block_playerhud\output\view;

use renderable;
use templatable;
use renderer_base;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class tab_ranking implements renderable, templatable {

    protected $config;
    protected $player;
    protected $instanceid;
    protected $courseid;
    protected $isteacher;

    public function __construct($config, $player, $instanceid, $courseid, $isteacher) {
        $this->config = $config;
        $this->player = $player;
        $this->instanceid = $instanceid;
        $this->courseid = $courseid;
        $this->isteacher = $isteacher;
    }

    /**
     * Método display chamado pelo view.php
     * (Mantendo compatibilidade com o switch case do view.php original)
     */
    public function display() {
        global $OUTPUT;
        return $OUTPUT->render_from_template('block_playerhud/view_ranking', $this->export_for_template($OUTPUT));
    }

public function export_for_template(renderer_base $output) {
        // 1. Verificações de Configuração Global
        if (empty($this->config->enable_ranking)) {
            return ['is_disabled' => true, 'str_disabled' => get_string('ranking_disabled', 'block_playerhud')];
        }

        // 2. Estado de Visibilidade do Usuário (Aluno)
        $is_visible = ($this->player->ranking_visibility == 1);
        
        // URL para o aluno alternar privacidade
        $url_toggle_privacy = new moodle_url('/blocks/playerhud/view.php', [
            'id' => $this->courseid, 
            'instanceid' => $this->instanceid, 
            'tab' => 'toggle_ranking_pref',
            'sesskey' => sesskey()
        ]);

        // 3. Configuração do Botão de Privacidade (Aluno)
        if ($is_visible) {
            $btn_label = get_string('ranking_disable', 'block_playerhud'); 
            $btn_icon = 'fa-eye-slash';
            $btn_class = 'btn-outline-danger';
        } else {
            $btn_label = get_string('enable_ranking', 'block_playerhud');
            $btn_icon = 'fa-eye';
            $btn_class = 'btn-success text-white shadow-sm';
        }

        // 4. Buscar Dados (APENAS se visível ou se for professor)
        $individual = [];
        $groups = [];
        $has_groups = false;
        $has_players = false;
        $show_content = false;
        
        // [NOVO] Lógica de Filtro do Professor
        $teacher_filter_active = false;
        $url_teacher_filter = '';
        $str_teacher_filter = '';
        $btn_teacher_class = '';

        if ($this->isteacher) {
            // Verifica se o professor pediu para esconder os ocultos
            $hide_ghosts = optional_param('hide_ghosts', 0, PARAM_INT);
            $teacher_filter_active = true;

            $url_teacher_filter = new moodle_url('/blocks/playerhud/view.php', [
                'id' => $this->courseid, 
                'instanceid' => $this->instanceid, 
                'tab' => 'ranking',
                'hide_ghosts' => $hide_ghosts ? 0 : 1
            ]);

            if ($hide_ghosts) {
                // [CORREÇÃO] Usando get_string
                $str_teacher_filter = get_string('ranking_filter_show', 'block_playerhud');
                $btn_teacher_class = 'btn-outline-secondary';
            } else {
                // [CORREÇÃO] Usando get_string
                $str_teacher_filter = get_string('ranking_filter_hide', 'block_playerhud');
                $btn_teacher_class = 'btn-outline-primary';
            }
        }

        if ($is_visible || $this->isteacher) {
            $show_content = true;
            $data = \block_playerhud\game::get_leaderboard(
                $this->instanceid, 
                $this->courseid, 
                $this->player->userid, 
                $this->isteacher
            );
            
            $individual = $data['individual'];
            $groups = $data['groups'];

            // [NOVO] Aplicar o filtro na lista se o professor solicitou
            if ($this->isteacher && !empty($hide_ghosts)) {
                $individual = array_filter($individual, function($user) {
                    // Mantém apenas se tiver rank (não for '-')
                    return $user->rank !== '-';
                });
                // Re-indexar array para mustache
                $individual = array_values($individual);
            }

            $has_groups = !empty($groups);
            $has_players = !empty($individual);
        }

        // 5. Retorno
        return [
            'is_disabled' => false,
            'privacy_visible' => $is_visible,
            'show_content' => $show_content,
            'is_teacher' => $this->isteacher, // Para exibir controles extras
            
            // Controle do Aluno
            'url_toggle_privacy' => $url_toggle_privacy->out(false),
            'str_btn_toggle' => $btn_label,
            'btn_toggle_icon' => $btn_icon,
            'btn_toggle_class' => $btn_class,

            // [NOVO] Controle do Professor
            'teacher_filter_active' => $teacher_filter_active,
            'url_teacher_filter' => $url_teacher_filter ? $url_teacher_filter->out(false) : '',
            'str_teacher_filter' => $str_teacher_filter,
            'btn_teacher_class' => $btn_teacher_class,

            'str_privacy_title' => get_string('my_visibility', 'block_playerhud'),
            'str_visible' => get_string('visible', 'block_playerhud'),
            'str_hidden' => get_string('hidden', 'block_playerhud'),
            'str_visible_desc' => get_string('visible_desc', 'block_playerhud'),
            'str_hidden_help' => get_string('ranking_hidden_help', 'block_playerhud', $btn_label),
            'str_hidden_desc' => get_string('hidden_desc', 'block_playerhud'),
            
            'individual' => $individual,
            'groups' => $groups,
            'has_groups' => $has_groups,
            'has_players' => $has_players,
            'no_ranking_data' => get_string('no_ranking_data', 'block_playerhud'),
            
            'str_tab_individual' => get_string('rank_individual', 'block_playerhud'),
            'str_tab_groups' => get_string('rank_groups', 'block_playerhud'),
            'str_col_rank' => '#',
            'str_col_player' => get_string('student', 'block_playerhud'),
            'str_col_level' => get_string('level', 'block_playerhud'),
            'str_col_xp' => get_string('xp', 'block_playerhud'),
            'str_col_group' => get_string('group', 'group'),
            'str_col_members' => get_string('members', 'block_playerhud'),
            'str_col_avg' => get_string('average', 'block_playerhud')
        ];
    }
}
