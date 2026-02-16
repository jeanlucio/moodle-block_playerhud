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
 * English language strings for PlayerHUD.
 *
 * @package    block_playerhud
 * @copyright  2026 Jean Lúcio <jeanlucio@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['ai_btn_conjure'] = 'Conjure!';
$string['ai_btn_create'] = 'Create Magic Item';
$string['ai_create_drop'] = 'Generate Drop location?';
$string['ai_created_count'] = '{$a} item(s) created!';
$string['ai_creating'] = 'Conjuring...';
$string['ai_drop_settings'] = 'Drop Settings';
$string['ai_error_apikey'] = 'Gemini API Key not configured in plugin settings.';
$string['ai_error_no_keys'] = 'No AI key configured (neither by Teacher nor by Institution).';
$string['ai_error_offline'] = 'AI Offline: {$a}';
$string['ai_error_parsing'] = 'The AI spell failed (Invalid JSON response). Try again.';
$string['ai_ex_desc'] = 'Factual Description...';
$string['ai_ex_loc'] = 'Location';
$string['ai_ex_name'] = 'Name';
$string['ai_info_infinite_xp'] = 'Infinite items were created with 0 XP to maintain balance.';
$string['ai_item_list_label'] = 'Item(s):';
$string['ai_json_instruction'] = 'Return ONLY valid JSON following this structure:';
$string['ai_prompt_ctx_easy'] = 'Context: The game needs common or introductory items.';
$string['ai_prompt_ctx_hard'] = 'Context: The game needs high-value (rare/complex) items.';
$string['ai_prompt_item'] = 'Create RPG item. Theme: \'{$a->theme}\'. JSON: {"name":"", "description":"", "emoji":"", "xp":{$a->xp}, "location_name":"Location Name"}. Reply in English.';
$string['ai_prompt_tech_xp'] = 'Technical Requirement: The internal XP value is {$a} (Do NOT write this number in the description, it is only for your assessment of item "value" or "rarity").';
$string['ai_prompt_theme_item'] = 'Theme (e.g. Mythology, Chemistry)';
$string['ai_reply_lang'] = 'Reply strictly in the language: {$a}.';
$string['ai_rnd_xp'] = 'Empty = Random';
$string['ai_role_item'] = 'Act as a Subject Matter Expert and Educator.';
$string['ai_rules_item'] = 'IMPORTANT: Create a factual, realistic, and educational description of the item, as if for an encyclopedia or textbook. Do NOT invent fantasy stories, do not create fictional "lore", and do NOT mention XP, levels, or game mechanics in the text.';
$string['ai_success'] = 'Item created successfully!';
$string['ai_task_multi'] = 'Create {$a->count} distinct real items related to the theme: \'{$a->theme}\'.';
$string['ai_task_single'] = 'Create ONE real item related to the theme: \'{$a}\'.';
$string['ai_theme_placeholder'] = 'e.g. Art History';
$string['ai_tip_balanced'] = 'This item helps balance the game economy.';
$string['ai_validation_theme'] = 'Please enter a theme for the story!';
$string['ai_warn_overflow'] = 'Warning: With this item, the game has {$a}% more XP than needed.';
$string['api_key_placeholder'] = 'Leave empty to use the institution\'s key';
$string['api_settings_desc'] = 'If you have your own API keys (Gemini or Groq), enter them here. The system will use your keys as a priority. If you leave it blank, the system will try to use the institution global key (if any).';
$string['api_settings_title'] = 'Your AI Keys (Optional)';
$string['average'] = 'Avg';
$string['back_to_course'] = 'Back to Course';
$string['back_to_library'] = 'Back to Library';
$string['bal_msg_easy'] = 'Too easy! There is <strong>{$a->total} XP</strong> available. Students will max out too fast.';
$string['bal_msg_empty'] = 'The game is empty. Create items to start.';
$string['bal_msg_hard'] = 'Hard! There is only <strong>{$a->total} XP</strong> available, but student needs <strong>{$a->req} XP</strong> to max out.';
$string['bal_msg_perfect'] = 'Balanced! The game economy is healthy ({$a->ratio}% coverage).';
$string['cancel'] = 'Cancel';
$string['changessaved'] = 'Changes saved successfully.';
$string['choice_text'] = 'Button Text';
$string['click_to_hide'] = 'Click to hide';
$string['click_to_show'] = 'Click to show';
$string['close'] = 'Close';
$string['collected'] = 'Collected';
$string['collected_msg'] = 'You collected: {$a->name}{$a->xp}!';
$string['completed'] = 'Completed';
$string['confirm_bulk_delete'] = 'Are you sure you want to delete the selected items?';
$string['confirm_delete'] = 'Are you sure you want to delete this?';
$string['confirm_disable'] = 'Are you sure? Your HUD will disappear until you reactivate.';
$string['connector_and'] = ' and ';
$string['default_drop_name'] = 'Generated Drop';
$string['delete'] = 'Delete';
$string['delete_n_items'] = 'Delete %d items';
$string['delete_selected'] = 'Delete selected';
$string['deleted'] = 'Item deleted.';
$string['deleted_bulk'] = 'Deleted {$a} items successfully.';
$string['description'] = 'Description';
$string['description_help'] = 'A short text describing the item (lore, functionality, or flavor text). This will appear when the student hovers over the item in their Backpack.';
$string['details'] = 'Details';
$string['disable_exit'] = 'Disable and Exit';
$string['drop_config_header'] = 'Configure Drop for: {$a}';
$string['drop_configured_msg'] = 'Drop configured!';
$string['drop_interval'] = 'Cooldown Interval';
$string['drop_max_qty'] = 'Max Quantity';
$string['drop_name_default'] = 'Ex: inside the castle';
$string['drop_name_label'] = 'Location / Name';
$string['drop_new_title'] = 'New Location';
$string['drop_rules_header'] = 'Collection Rules';
$string['drop_save_btn'] = 'Save Location';
$string['drop_supplies_label'] = 'Supplies';
$string['drop_unlimited_label'] = 'Unlimited';
$string['drop_unlimited_xp_warning'] = '<strong>Note:</strong> Infinite drops do not grant XP. Even if this item has an XP value, <strong>this specific drop will award 0 XP</strong>.';
$string['dropcode'] = 'Shortcode';
$string['dropcode_help'] = 'Copy this code and paste it anywhere in your course (Labels, Pages, Forum posts, Assignments descriptions, etc). When the student sees this code, the "Collect" button will appear. Tip: If you enter the activity name in the location field, the system will automatically generate a link so you can access it quickly.';
$string['drops'] = 'Drops';
$string['drops_btn_new'] = 'New Drop Location';
$string['drops_col_actions'] = 'Actions';
$string['drops_col_code'] = 'Code';
$string['drops_col_id'] = 'ID';
$string['drops_col_name'] = 'Location (Name)';
$string['drops_col_qty'] = 'Qty';
$string['drops_col_time'] = 'Time';
$string['drops_confirm_delete'] = 'Delete this drop location?';
$string['drops_empty'] = 'No drops configured.';
$string['drops_empty_desc'] = 'Create a "Drop" to generate a code and hide this item in your course.';
$string['drops_header_managedrops'] = 'Managing Drops for:';
$string['drops_immediate'] = 'Immediate';
$string['drops_summary'] = 'You have {$a} drops scattered for this item.';
$string['edit'] = 'Edit';
$string['empty'] = '- Empty -';
$string['enable_ranking'] = 'Enable Leaderboard';
$string['enable_ranking_help'] = 'If enabled, a "Leaderboard" tab will be available. Students can see their position individually or by group. Students can also opt-out if they prefer privacy.';
$string['enabled'] = 'Enabled?';
$string['enabled_help'] = 'If unchecked, this item will not appear in the game, cannot be collected, and will disappear from students\' inventories if they already have it (until enabled again).';
$string['error_connection'] = 'Connection error.';
$string['error_msg'] = 'Error: {$a}';
$string['error_quest_already_claimed'] = 'Reward already claimed.';
$string['error_quest_invalid'] = 'Invalid quest.';
$string['error_quest_requirements'] = 'Requirements not met.';
$string['error_service_code'] = '{$a->service} error {$a->code}';
$string['error_unknown_mode'] = 'Unknown generation mode.';
$string['game_balance'] = 'Game Economy Balance';
$string['gemini_apikey'] = 'Google Gemini API Key';
$string['gemini_apikey_desc'] = 'Enter your API Key to enable automatic item creation functionality using AI.';
$string['gen_btn'] = '🪄 Generate Code';
$string['gen_code_label'] = 'Final Code';
$string['gen_copied'] = 'Copied!';
$string['gen_copy'] = 'Copy';
$string['gen_copy_short'] = 'Copy';
$string['gen_customize'] = 'Customize';
$string['gen_link_help'] = 'Enter what the student should read.';
$string['gen_link_label'] = 'Link Text';
$string['gen_link_placeholder'] = 'Click here to collect';
$string['gen_preview'] = 'Preview';
$string['gen_style'] = 'Display Style';
$string['gen_style_card'] = 'Full Card';
$string['gen_style_card_desc'] = 'Icon + Name + Button (Default)';
$string['gen_style_image'] = 'Image Only';
$string['gen_style_image_desc'] = 'Floating clickable icon.';
$string['gen_style_text'] = 'Text Only';
$string['gen_style_text_desc'] = 'A simple text link.';
$string['gen_title'] = 'Code Generator';
$string['gen_yours'] = 'Owned: 0';
$string['great'] = 'Great!';
$string['groq_apikey'] = 'Groq API Key';
$string['groq_apikey_desc'] = 'Enter your Groq Cloud key for free redundancy.';
$string['group'] = 'Group';
$string['hidden'] = 'Hidden';
$string['hidden_desc'] = 'Only the teacher sees you.';
$string['infinite'] = 'Infinite';
$string['infinite_item_title'] = 'Infinite Item';
$string['item'] = 'Item';
$string['item_archived'] = 'Archived';
$string['item_desc'] = 'Description';
$string['item_details'] = 'Item Details';
$string['item_image'] = 'Icon / Emoji';
$string['item_name'] = 'Item Name';
$string['item_new'] = 'New Item';
$string['item_xp'] = 'XP Value';
$string['itemimage_emoji'] = 'Emoji or Image URL';
$string['itemimage_emoji_help'] = 'Use this field if you don\'t want to upload a file.<br>You can paste an Emoji (e.g. 🛡️, 🧪) or a direct link to an image on the web.<br><b>Note:</b> If you upload a file below, this field will be ignored.';
$string['itemname'] = 'Item Name';
$string['itemnotfound'] = 'Item not found or inactive.';
$string['items'] = 'Items:';
$string['items_none'] = 'No items created';
$string['last_collected'] = 'Last collected:';
$string['latest_items'] = 'Latest items';
$string['leaderboard_desc'] = 'See who are the masters of the course.';
$string['leaderboard_title'] = 'Leaderboard';
$string['level'] = 'Level';
$string['level_settings'] = 'Level Settings';
$string['limitreached'] = '🏆 Congratulations! You have completed this item collection!';
$string['loading'] = 'Loading...';
$string['manage_drops_title'] = 'Manage Drops for: {$a}';
$string['master_panel'] = 'Game Master Panel';
$string['max_levels'] = 'Max Level';
$string['max_levels_help'] = 'The maximum level a student can reach (Cap). Level colors will be distributed proportionally up to this number.';
$string['maxusage'] = 'Collection Limit';
$string['maxusage_help'] = 'Define how many times the student can take this item. If you check "Unlimited", the student earns points (bonus) every time they click, but it does not count towards the total course goal.';
$string['members'] = 'members';
$string['modulename'] = 'PlayerHUD';
$string['modulename_help'] = 'Gamification and Inventory for your students.';
$string['modulenameplural'] = 'PlayerHUDs';
$string['my_visibility'] = 'My Visibility:';
$string['new_item_badge'] = 'NEW!';
$string['next_collection_in'] = 'Next collection in:';
$string['no'] = 'No';
$string['no_description'] = '- No description -';
$string['no_groups_data'] = 'This course has no groups defined for competition.';
$string['no_ranking_data'] = 'No ranking data yet.';
$string['openbackpack'] = 'Open Backpack';
$string['optin_hello'] = 'Hello, {$a}!';
$string['optin_message'] = 'This course features a gamification system with items, levels, and achievements. Would you like to join this journey?';
$string['optin_no'] = 'No, thanks. Return to course.';
$string['optin_yes'] = 'Yes, I want to join!';
$string['playerhud:addinstance'] = 'Add a new PlayerHUD';
$string['playerhud:manage'] = 'Manage Game Content';
$string['playerhud:view'] = 'View PlayerHUD';
$string['pluginadministration'] = 'PlayerHUD Administration';
$string['pluginname'] = 'PlayerHUD';
$string['privacy:metadata:ai_logs'] = 'Logs of interactions with AI for content generation.';
$string['privacy:metadata:ai_logs:action'] = 'The type of action performed by the AI (e.g., create item).';
$string['privacy:metadata:inventory'] = 'The inventory of items collected by the user.';
$string['privacy:metadata:inventory:itemid'] = 'The ID of the item that was collected.';
$string['privacy:metadata:playerhud_user'] = 'Stores basic player profile information and game progress.';
$string['privacy:metadata:playerhud_user:currentxp'] = 'The current amount of Experience Points (XP) of the user.';
$string['privacy:metadata:playerhud_user:ranking_visibility'] = 'User preference regarding visibility on the public leaderboard.';
$string['privacy:metadata:quest_log'] = 'Log of quests completed by the user.';
$string['privacy:metadata:quest_log:questid'] = 'The ID of the completed quest.';
$string['privacy:metadata:rpg'] = 'RPG story progress data and user choices.';
$string['privacy:metadata:rpg:chapters'] = 'List of completed chapters.';
$string['privacy:metadata:rpg:classid'] = 'The chosen character class.';
$string['privacy:metadata:rpg:karma'] = 'The players current Karma (morality) value.';
$string['privacy:metadata:rpg:nodes'] = 'History of visited scenes and choices made.';
$string['privacy:metadata:timecreated'] = 'The time when the record was created.';
$string['privacy:metadata:trade_log'] = 'History of trades and purchases made in the shop.';
$string['privacy:metadata:trade_log:tradeid'] = 'The ID of the trade transaction.';
$string['privacy_updated'] = 'Privacy preference updated.';
$string['qty'] = 'Quantity';
$string['quest_status_completed'] = 'Completed';
$string['quest_status_pending'] = 'Pending';
$string['quest_status_removed'] = 'Activity removed';
$string['rank_groups'] = 'Groups (Avg XP)';
$string['rank_individual'] = 'Individual';
$string['ranking_disable'] = 'Disable Ranking';
$string['ranking_filter_hide'] = 'Hide Inactive (Student View)';
$string['ranking_filter_show'] = 'Show Hidden/Paused';
$string['ranking_hdr'] = 'Ranking & Competition';
$string['ranking_hidden_help'] = 'Click on <strong>{$a}</strong> above to join the competition again.';
$string['ready'] = 'Ready!';
$string['respawntime'] = 'Cooldown Time';
$string['respawntime_help'] = 'This setting defines how long the student must wait to collect the item again at this specific location.<br><br>Set to <b>0</b> if you want the collection to be one-time only (the student takes it once and it never appears again), or if it is an infinite item with no wait time.';
$string['save_keys'] = 'Save My Keys';
$string['savechanges'] = 'Save changes';
$string['secret'] = 'Secret Item';
$string['secret_desc'] = 'A mysterious item. Collect it and find out!';
$string['secret_help'] = 'If checked, this item will appear as "???" (Mystery Item) in the list of available items until the student collects it for the first time.';
$string['secret_name'] = '???';
$string['secretdesc'] = 'Hide from list until student finds it.';
$string['select'] = 'Select';
$string['selectall'] = 'Select all';
$string['sort_acquired'] = 'Owned First';
$string['sort_by'] = 'Sort by...';
$string['sort_count_asc'] = 'Lowest Quantity';
$string['sort_count_desc'] = 'Highest Quantity';
$string['sort_missing'] = 'Missing First';
$string['sort_name_asc'] = 'Name (A-Z)';
$string['sort_name_desc'] = 'Name (Z-A)';
$string['sort_recent'] = 'Most Recent';
$string['sort_xp_asc'] = 'Lowest XP';
$string['sort_xp_desc'] = 'Highest XP';
$string['status_active'] = 'You are participating in Gamification.';
$string['status_off'] = 'Off';
$string['status_paused'] = 'Gamification paused.';
$string['status_paused_title'] = 'Gamification Disabled';
$string['str_col_date'] = 'Last Score';
$string['student'] = 'Student';
$string['success'] = 'Success!';
$string['summary_stats'] = 'You have {$a->items} items created and {$a->drops} drops available.';
$string['tab_collection'] = 'Collection';
$string['tab_config'] = 'Settings';
$string['tab_items'] = 'Item Library';
$string['tab_maintenance'] = 'The "{$a}" tab is currently under maintenance or construction.';
$string['tab_ranking'] = 'Leaderboard';
$string['take'] = 'Take';
$string['total_items_xp'] = 'Total XP in Items';
$string['tradable'] = 'Tradable?';
$string['tradable_help'] = 'Defines if this item can be traded.<br><br><b>Yes:</b> The student can sell this item in the shop or trade it with other students.<br><b>No:</b> The item is bound to the student (ideal for unique items, quest keys, or non-transferable assets).';
$string['unlimited'] = 'Unlimited';
$string['uploadfile'] = 'Upload File';
$string['view_ranking'] = 'View Leaderboard';
$string['visible'] = 'Visible';
$string['visible_desc'] = 'You appear to your colleagues.';
$string['visual_content'] = 'Visual content';
$string['visualrules'] = 'Visualization & Rules';
$string['waitmore'] = 'You already collected this! Wait {$a} minutes for the next one.';
$string['widget_code_desc'] = 'PlayerHUD works natively as a Side Block. Use this shortcode <strong>only</strong> if you want to pin the dashboard within the course content (e.g., Topic Zero) or to improve the <strong>Mobile App</strong> experience.';
$string['widget_code_tip'] = '<strong>Pro Tip:</strong> In the Moodle Mobile App to ensure students see their stats immediately upon entry, paste this code into a <strong>Label</strong> at the top of the course.';
$string['widget_code_title'] = 'Embedded Widget & Mobile';
$string['xp'] = 'XP';
$string['xp_help'] = 'How many Experience Points (XP) the student earns when collecting this item.';
$string['xp_per_level'] = 'XP per Level';
$string['xp_per_level_help'] = 'How many XP points the student needs to accumulate to gain 1 level. (Default: 100)';
$string['xp_required_max'] = 'XP for Max Level';
$string['xp_warning_msg'] = 'Tradable items cannot grant XP to prevent fraud. The value will be set to 0.';
$string['yes'] = 'Yes';
$string['yours'] = 'Owned: {$a}';
// ... Strings existentes ...

// --- Help / Rules Section ---
$string['tab_rules'] = 'Help & Rules';
$string['help_title'] = 'Game Guide';
$string['help_btn'] = 'Help';
$string['help_content_label'] = 'Custom Help Content';
$string['help_content_desc'] = 'Customize the instructions students see in the Help tab. Clear this field or check the box below to restore the system default.';
$string['help_reset_checkbox'] = 'Reset help content to system default on save';
$string['help_pagedefault'] = '
<div class="alert alert-info shadow-sm mb-4">
    <div class="d-flex align-items-center">
        <div class="me-3"><i class="fa fa-info-circle fa-2x" aria-hidden="true"></i></div>
        <div>
            <h5 class="alert-heading fw-bold m-0">Welcome, Adventurer!</h5>
            <p class="mb-0">This course uses a gamification system to track your progress.</p>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-cube fa-3x text-primary mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Items & Drops</h5>
                <p class="small text-muted">Find secret codes hidden in activities descriptions or complete tasks to collect items and fill your Backpack.</p>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-3">
        <div class="card h-100 border shadow-sm">
            <div class="card-body text-center">
                <i class="fa fa-trophy fa-3x text-warning mb-3" aria-hidden="true"></i>
                <h5 class="fw-bold">Leaderboard</h5>
                <p class="small text-muted">Earn XP to level up. Check the Leaderboard to see how you rank against your peers or other groups.</p>
            </div>
        </div>
    </div>
</div>';
