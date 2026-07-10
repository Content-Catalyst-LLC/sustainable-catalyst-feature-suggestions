<?php
/**
 * Opportunity scoring and roadmap workflow for Feature Suggestions v2.9.0.
 */
if (!defined('ABSPATH')) { exit; }

final class SCFS_Opportunity_Workflow {
    const OPTION_KEY = 'scfs_opportunity_settings';
    const NONCE = 'scfs_opportunity_workflow';
    const VERSION = '1.0';
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('add_meta_boxes', array($this, 'meta_boxes'));
        add_action('save_post_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, array($this, 'save_meta'), 30, 2);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_scfs_opportunity_recalculate', array($this, 'recalculate_action'));
        add_action('admin_post_scfs_opportunity_transition', array($this, 'transition_action'));
        add_action('admin_post_scfs_opportunity_save_settings', array($this, 'save_settings_action'));
        add_action('admin_post_scfs_opportunity_export', array($this, 'export_action'));
        add_action('rest_api_init', array($this, 'rest_routes'));
        add_filter('manage_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '_posts_columns', array($this, 'columns'));
        add_action('manage_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '_posts_custom_column', array($this, 'column_value'), 20, 2);
    }

    private function defaults() {
        return array(
            'weight_demand' => 15,
            'weight_impact' => 20,
            'weight_alignment' => 20,
            'weight_evidence' => 15,
            'weight_public_interest' => 10,
            'weight_readiness' => 10,
            'weight_effort' => 10,
            'minimum_evidence' => 2,
            'candidate_threshold' => 55,
            'github_repository' => 'Content-Catalyst-LLC/sustainable-catalyst-feature-suggestions',
            'decision_studio_url' => '',
        );
    }

    private function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->defaults());
    }

    public function states() {
        return array(
            'unscored' => __('Unscored', 'sustainable-catalyst-feature-suggestions'),
            'candidate' => __('Roadmap candidate', 'sustainable-catalyst-feature-suggestions'),
            'under_review' => __('Under review', 'sustainable-catalyst-feature-suggestions'),
            'approved' => __('Approved', 'sustainable-catalyst-feature-suggestions'),
            'planned' => __('Planned', 'sustainable-catalyst-feature-suggestions'),
            'in_progress' => __('In progress', 'sustainable-catalyst-feature-suggestions'),
            'released' => __('Released', 'sustainable-catalyst-feature-suggestions'),
            'parked' => __('Parked', 'sustainable-catalyst-feature-suggestions'),
            'declined' => __('Declined', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    private function clamp($value, $min = 0, $max = 5) { return max($min, min($max, (float) $value)); }
    private function ai_score($analysis, $key, $default = 0) {
        $value = isset($analysis['scores'][$key]) ? (float) $analysis['scores'][$key] : $default;
        return $value <= 1 ? $value * 5 : $this->clamp($value);
    }

    public function calculate($post_id) {
        $settings = $this->settings();
        $analysis = get_post_meta($post_id, '_scfs_ai_analysis', true);
        if (!is_array($analysis)) $analysis = array();
        $votes = absint(get_post_meta($post_id, '_scfs_support_votes', true));
        $duplicate_count = max(0, absint(get_post_meta($post_id, '_scfs_duplicate_count', true)));
        $reviewer_impact = $this->clamp(get_post_meta($post_id, '_scfs_impact_score', true));
        $reviewer_effort = $this->clamp(get_post_meta($post_id, '_scfs_effort_score', true));
        $confidence = $this->clamp(((float) ($analysis['confidence'] ?? 0)) * 5);
        $ai_impact = $this->ai_score($analysis, 'impact', $reviewer_impact);
        $alignment = $this->ai_score($analysis, 'strategic_alignment', 0);
        $ai_effort = $this->ai_score($analysis, 'effort', $reviewer_effort);
        $impact = $reviewer_impact > 0 ? $reviewer_impact : $ai_impact;
        $effort = $reviewer_effort > 0 ? $reviewer_effort : $ai_effort;
        $demand = $this->clamp(log(1 + $votes + ($duplicate_count * 2), 2));
        $evidence_count = 0;
        if ($votes > 0) $evidence_count++;
        if ($duplicate_count > 0) $evidence_count++;
        if (!empty($analysis)) $evidence_count++;
        if (get_post_meta($post_id, '_scfs_librarian_feedback_type', true)) $evidence_count++;
        if (get_post_meta($post_id, '_scfs_relevant_url', true)) $evidence_count++;
        if (get_post_meta($post_id, '_scfs_success_criteria', true)) $evidence_count++;
        $evidence = $this->clamp(($evidence_count / 6) * 5);
        $public_interest = $this->clamp(get_post_meta($post_id, '_scfs_public_interest_score', true));
        if (!$public_interest) $public_interest = $this->clamp(($impact + $alignment) / 2);
        $readiness_count = 0;
        if (get_post_meta($post_id, '_scfs_roadmap_area', true)) $readiness_count++;
        if (get_post_meta($post_id, '_scfs_success_criteria', true)) $readiness_count++;
        if (get_post_meta($post_id, '_scfs_implementation_notes', true)) $readiness_count++;
        if (get_post_meta($post_id, '_scfs_github_issue_url', true)) $readiness_count++;
        $readiness = $this->clamp(($readiness_count / 4) * 5);

        $dimensions = compact('demand','impact','alignment','evidence','public_interest','readiness','effort');
        $weights = array(
            'demand' => absint($settings['weight_demand']),
            'impact' => absint($settings['weight_impact']),
            'alignment' => absint($settings['weight_alignment']),
            'evidence' => absint($settings['weight_evidence']),
            'public_interest' => absint($settings['weight_public_interest']),
            'readiness' => absint($settings['weight_readiness']),
            'effort' => absint($settings['weight_effort']),
        );
        $weight_total = max(1, array_sum($weights));
        $positive = 0;
        foreach (array('demand','impact','alignment','evidence','public_interest','readiness') as $key) $positive += ($dimensions[$key] / 5) * $weights[$key];
        $effort_penalty = ($effort / 5) * $weights['effort'];
        $score = max(0, min(100, (($positive - $effort_penalty) / $weight_total) * 100));
        $minimum_met = $evidence_count >= absint($settings['minimum_evidence']);
        $result = array(
            'version' => self::VERSION,
            'score' => round($score, 1),
            'dimensions' => array_map(function($v){ return round($v, 2); }, $dimensions),
            'weights' => $weights,
            'evidence_count' => $evidence_count,
            'minimum_evidence_met' => $minimum_met,
            'confidence' => round($confidence / 5, 2),
            'calculated_at' => gmdate('c'),
            'explanation' => $minimum_met ? 'Score combines demand, impact, strategic alignment, evidence, public-interest value, implementation readiness, and an effort penalty.' : 'Score is provisional because the configured minimum evidence threshold has not been met.',
        );
        update_post_meta($post_id, '_scfs_opportunity_score', $result);
        $state = get_post_meta($post_id, '_scfs_opportunity_state', true) ?: 'unscored';
        if ($state === 'unscored' && $minimum_met && $score >= (float) $settings['candidate_threshold']) {
            update_post_meta($post_id, '_scfs_opportunity_state', 'candidate');
            $this->audit($post_id, 'automatic_candidate_recommendation', array('score' => $result['score']));
        }
        do_action('scfs_event', $this->event($post_id, 'opportunity.scored', array('score'=>$result['score'],'minimum_evidence_met'=>$minimum_met)));
        do_action('sc_platform_event', $this->event($post_id, 'opportunity.scored', array('score'=>$result['score'],'minimum_evidence_met'=>$minimum_met)));
        return $result;
    }

    private function event($post_id, $type, $extra = array()) {
        return array_merge(array(
            'schema_version' => Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,
            'event_type' => $type,
            'source' => 'feature_suggestions',
            'submission_uuid' => get_post_meta($post_id, '_scfs_submission_uuid', true),
            'opportunity_state' => get_post_meta($post_id, '_scfs_opportunity_state', true) ?: 'unscored',
            'timestamp' => gmdate('c'),
        ), $extra);
    }

    private function audit($post_id, $action, $details = array()) {
        $log = get_post_meta($post_id, '_scfs_opportunity_audit', true);
        if (!is_array($log)) $log = array();
        $user = wp_get_current_user();
        $log[] = array('timestamp'=>gmdate('c'),'action'=>sanitize_key($action),'user_id'=>get_current_user_id(),'user'=>$user->exists()?$user->user_login:'system','details'=>$details);
        if (count($log) > 100) $log = array_slice($log, -100);
        update_post_meta($post_id, '_scfs_opportunity_audit', $log);
    }

    public function meta_boxes() {
        add_meta_box('scfs-opportunity-workflow', __('Opportunity scoring and roadmap', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_meta'), Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, 'side', 'high');
        add_meta_box('scfs-opportunity-audit', __('Roadmap audit history', 'sustainable-catalyst-feature-suggestions'), array($this, 'render_audit'), Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, 'normal', 'default');
    }

    public function render_meta($post) {
        wp_nonce_field(self::NONCE, 'scfs_opportunity_nonce');
        $score = get_post_meta($post->ID, '_scfs_opportunity_score', true);
        $state = get_post_meta($post->ID, '_scfs_opportunity_state', true) ?: 'unscored';
        $owner = get_post_meta($post->ID, '_scfs_roadmap_owner', true);
        $target = get_post_meta($post->ID, '_scfs_target_release', true);
        $decision = get_post_meta($post->ID, '_scfs_roadmap_decision_reason', true);
        $public_interest = get_post_meta($post->ID, '_scfs_public_interest_score', true);
        echo '<p><strong>'.esc_html__('Opportunity score:', 'sustainable-catalyst-feature-suggestions').'</strong> '.esc_html(is_array($score)?$score['score'].' / 100':'Not calculated').'</p>';
        if (is_array($score)) echo '<p class="description">'.esc_html($score['explanation']).'</p><details><summary>'.esc_html__('Score dimensions','sustainable-catalyst-feature-suggestions').'</summary><pre>'.esc_html(wp_json_encode($score['dimensions'], JSON_PRETTY_PRINT)).'</pre></details>';
        echo '<p><label><strong>'.esc_html__('Roadmap state','sustainable-catalyst-feature-suggestions').'</strong><br><select class="widefat" name="scfs_opportunity_state">';
        foreach($this->states() as $key=>$label) echo '<option value="'.esc_attr($key).'" '.selected($state,$key,false).'>'.esc_html($label).'</option>';
        echo '</select></label></p>';
        echo '<p><label><strong>'.esc_html__('Public-interest value (0–5)','sustainable-catalyst-feature-suggestions').'</strong><br><select class="widefat" name="scfs_public_interest_score">'; for($i=0;$i<=5;$i++) echo '<option value="'.$i.'" '.selected((string)$public_interest,(string)$i,false).'>'.$i.'</option>'; echo '</select></label></p>';
        echo '<p><label><strong>'.esc_html__('Owner','sustainable-catalyst-feature-suggestions').'</strong><br><input class="widefat" name="scfs_roadmap_owner" value="'.esc_attr($owner).'"></label></p>';
        echo '<p><label><strong>'.esc_html__('Target release','sustainable-catalyst-feature-suggestions').'</strong><br><input class="widefat" name="scfs_target_release" value="'.esc_attr($target).'" placeholder="v3.0.0"></label></p>';
        echo '<p><label><strong>'.esc_html__('Decision rationale','sustainable-catalyst-feature-suggestions').'</strong><br><textarea class="widefat" rows="4" name="scfs_roadmap_decision_reason">'.esc_textarea($decision).'</textarea></label></p>';
        $recalc = wp_nonce_url(admin_url('admin-post.php?action=scfs_opportunity_recalculate&post_id='.$post->ID), self::NONCE.'_'.$post->ID);
        $export = wp_nonce_url(admin_url('admin-post.php?action=scfs_opportunity_export&post_id='.$post->ID), self::NONCE.'_export_'.$post->ID);
        echo '<p><a class="button button-primary" href="'.esc_url($recalc).'">'.esc_html__('Recalculate score','sustainable-catalyst-feature-suggestions').'</a> <a class="button" href="'.esc_url($export).'">'.esc_html__('Export handoff','sustainable-catalyst-feature-suggestions').'</a></p>';
        echo '<p class="description">'.esc_html__('Scores are advisory. State changes require an authorized human reviewer.','sustainable-catalyst-feature-suggestions').'</p>';
    }

    public function render_audit($post) {
        $log = get_post_meta($post->ID, '_scfs_opportunity_audit', true);
        if (!is_array($log) || !$log) { echo '<p>'.esc_html__('No roadmap actions recorded yet.','sustainable-catalyst-feature-suggestions').'</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Date</th><th>Action</th><th>Reviewer</th><th>Details</th></tr></thead><tbody>';
        foreach(array_reverse($log) as $item) echo '<tr><td>'.esc_html($item['timestamp']??'').'</td><td>'.esc_html(str_replace('_',' ',$item['action']??'')).'</td><td>'.esc_html($item['user']??'system').'</td><td><code>'.esc_html(wp_json_encode($item['details']??array())).'</code></td></tr>';
        echo '</tbody></table>';
    }

    public function save_meta($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!isset($_POST['scfs_opportunity_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_opportunity_nonce'])), self::NONCE)) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $old = get_post_meta($post_id, '_scfs_opportunity_state', true) ?: 'unscored';
        $state = sanitize_key($_POST['scfs_opportunity_state'] ?? 'unscored'); if (!isset($this->states()[$state])) $state='unscored';
        update_post_meta($post_id, '_scfs_opportunity_state', $state);
        update_post_meta($post_id, '_scfs_public_interest_score', min(5,absint($_POST['scfs_public_interest_score']??0)));
        update_post_meta($post_id, '_scfs_roadmap_owner', sanitize_text_field(wp_unslash($_POST['scfs_roadmap_owner']??'')));
        update_post_meta($post_id, '_scfs_target_release', sanitize_text_field(wp_unslash($_POST['scfs_target_release']??'')));
        update_post_meta($post_id, '_scfs_roadmap_decision_reason', sanitize_textarea_field(wp_unslash($_POST['scfs_roadmap_decision_reason']??'')));
        $this->calculate($post_id);
        if ($old !== $state) {
            $this->audit($post_id, 'state_changed', array('from'=>$old,'to'=>$state));
            $event=$this->event($post_id,'opportunity.status_changed',array('previous_state'=>$old,'state'=>$state)); do_action('scfs_event',$event); do_action('sc_platform_event',$event); do_action('scfs_dispatch_event',$event);
        }
    }

    public function admin_menu() {
        add_submenu_page('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, __('Opportunity Roadmap','sustainable-catalyst-feature-suggestions'), __('Opportunity Roadmap','sustainable-catalyst-feature-suggestions'), 'edit_posts', 'scfs-opportunity-roadmap', array($this,'render_page'));
        add_submenu_page('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, __('Scoring Settings','sustainable-catalyst-feature-suggestions'), __('Scoring Settings','sustainable-catalyst-feature-suggestions'), 'manage_options', 'scfs-opportunity-settings', array($this,'render_settings'));
    }

    public function roadmap_items() {
        $ids=get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','orderby'=>'date','order'=>'DESC'));
        $items=array(); foreach($ids as $id){$score=get_post_meta($id,'_scfs_opportunity_score',true); if(!is_array($score))$score=$this->calculate($id); $items[]=array('id'=>$id,'title'=>get_the_title($id),'score'=>(float)$score['score'],'state'=>get_post_meta($id,'_scfs_opportunity_state',true)?:'unscored','owner'=>get_post_meta($id,'_scfs_roadmap_owner',true),'target'=>get_post_meta($id,'_scfs_target_release',true),'evidence'=>(int)$score['evidence_count'],'edit'=>get_edit_post_link($id,'raw'));} usort($items,function($a,$b){return $b['score']<=>$a['score'];}); return $items;
    }

    public function render_page() {
        if(!current_user_can('edit_posts'))wp_die(__('Permission denied.','sustainable-catalyst-feature-suggestions'));
        $items=$this->roadmap_items(); echo '<div class="wrap"><h1>'.esc_html__('Opportunity Scoring and Roadmap Workflow','sustainable-catalyst-feature-suggestions').'</h1><p>'.esc_html__('Prioritize evidence-linked opportunities while retaining explicit human approval and an auditable decision history.','sustainable-catalyst-feature-suggestions').'</p><table class="widefat striped"><thead><tr><th>Opportunity</th><th>Score</th><th>Evidence</th><th>State</th><th>Owner</th><th>Target</th></tr></thead><tbody>';
        foreach($items as $i) echo '<tr><td><a href="'.esc_url($i['edit']).'"><strong>'.esc_html($i['title']).'</strong></a></td><td>'.esc_html((string)$i['score']).'</td><td>'.esc_html((string)$i['evidence']).'</td><td>'.esc_html($this->states()[$i['state']]??$i['state']).'</td><td>'.esc_html($i['owner']).'</td><td>'.esc_html($i['target']).'</td></tr>';
        echo '</tbody></table><p class="description">'.esc_html__('Support counts are only one demand signal. Scores also include impact, alignment, evidence, public-interest value, readiness, and effort.','sustainable-catalyst-feature-suggestions').'</p></div>';
    }

    public function render_settings() {
        if(!current_user_can('manage_options'))wp_die(__('Permission denied.','sustainable-catalyst-feature-suggestions')); $s=$this->settings();
        echo '<div class="wrap"><h1>'.esc_html__('Opportunity Scoring Settings','sustainable-catalyst-feature-suggestions').'</h1><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="scfs_opportunity_save_settings">'; wp_nonce_field(self::NONCE.'_settings');
        echo '<table class="form-table"><tbody>'; foreach(array('weight_demand'=>'Demand weight','weight_impact'=>'Impact weight','weight_alignment'=>'Strategic alignment weight','weight_evidence'=>'Evidence weight','weight_public_interest'=>'Public-interest weight','weight_readiness'=>'Readiness weight','weight_effort'=>'Effort penalty weight','minimum_evidence'=>'Minimum evidence signals','candidate_threshold'=>'Candidate threshold (0–100)') as $key=>$label) echo '<tr><th><label for="'.$key.'">'.esc_html($label).'</label></th><td><input type="number" min="0" max="100" id="'.$key.'" name="'.$key.'" value="'.esc_attr($s[$key]).'"></td></tr>';
        echo '<tr><th>GitHub repository</th><td><input class="regular-text" name="github_repository" value="'.esc_attr($s['github_repository']).'" placeholder="Owner/repository"></td></tr><tr><th>Decision Studio URL</th><td><input class="regular-text" type="url" name="decision_studio_url" value="'.esc_attr($s['decision_studio_url']).'"></td></tr></tbody></table>'; submit_button(__('Save scoring settings','sustainable-catalyst-feature-suggestions')); echo '</form><p class="description">'.esc_html__('Changing weights does not silently change roadmap decisions. Recalculate opportunities and review the results.','sustainable-catalyst-feature-suggestions').'</p></div>';
    }

    public function save_settings_action(){if(!current_user_can('manage_options'))wp_die('Permission denied.');check_admin_referer(self::NONCE.'_settings');$d=$this->defaults();foreach(array_keys($d) as $k){if(in_array($k,array('github_repository','decision_studio_url'),true))continue;$d[$k]=min(100,max(0,absint($_POST[$k]??$d[$k])));} $d['github_repository']=sanitize_text_field(wp_unslash($_POST['github_repository']??''));$d['decision_studio_url']=esc_url_raw(wp_unslash($_POST['decision_studio_url']??''));update_option(self::OPTION_KEY,$d,false);wp_safe_redirect(admin_url('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE.'&page=scfs-opportunity-settings&updated=1'));exit;}
    public function recalculate_action(){ $id=absint($_GET['post_id']??0);check_admin_referer(self::NONCE.'_'.$id);if(!current_user_can('edit_post',$id))wp_die('Permission denied.');$r=$this->calculate($id);$this->audit($id,'score_recalculated',array('score'=>$r['score']));wp_safe_redirect(get_edit_post_link($id,'url').'&scfs_score_updated=1');exit; }
    public function transition_action(){ $id=absint($_POST['post_id']??0);check_admin_referer(self::NONCE.'_transition_'.$id);if(!current_user_can('edit_post',$id))wp_die('Permission denied.');$from=get_post_meta($id,'_scfs_opportunity_state',true)?:'unscored';$to=sanitize_key($_POST['state']??'under_review');if(!isset($this->states()[$to]))$to='under_review';update_post_meta($id,'_scfs_opportunity_state',$to);$this->audit($id,'state_changed',array('from'=>$from,'to'=>$to));wp_safe_redirect(get_edit_post_link($id,'url'));exit; }

    private function handoff($id) {
        $post=get_post($id); $score=get_post_meta($id,'_scfs_opportunity_score',true); if(!is_array($score))$score=$this->calculate($id); $analysis=get_post_meta($id,'_scfs_ai_analysis',true);
        return array('schema_version'=>'1.0','type'=>'scfs.opportunity_handoff','submission_uuid'=>get_post_meta($id,'_scfs_submission_uuid',true),'title'=>$post?$post->post_title:'','problem'=>get_post_meta($id,'_scfs_problem',true),'proposal'=>get_post_meta($id,'_scfs_suggestion',true),'success_criteria'=>get_post_meta($id,'_scfs_success_criteria',true),'roadmap_area'=>get_post_meta($id,'_scfs_roadmap_area',true),'opportunity_state'=>get_post_meta($id,'_scfs_opportunity_state',true)?:'unscored','opportunity_score'=>$score,'ai_analysis'=>is_array($analysis)?$analysis:array(),'public_supports'=>absint(get_post_meta($id,'_scfs_support_votes',true)),'owner'=>get_post_meta($id,'_scfs_roadmap_owner',true),'target_release'=>get_post_meta($id,'_scfs_target_release',true),'decision_rationale'=>get_post_meta($id,'_scfs_roadmap_decision_reason',true),'github_issue_url'=>get_post_meta($id,'_scfs_github_issue_url',true),'generated_at'=>gmdate('c'));
    }
    public function export_action(){ $id=absint($_GET['post_id']??0);check_admin_referer(self::NONCE.'_export_'.$id);if(!current_user_can('edit_post',$id))wp_die('Permission denied.');$payload=$this->handoff($id);$this->audit($id,'handoff_exported',array('format'=>'json'));header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename=scfs-opportunity-'.$id.'.json');echo wp_json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit; }

    public function rest_routes(){register_rest_route('scfs/v1','/opportunities',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_list'),'permission_callback'=>function(){return current_user_can('edit_posts');}));register_rest_route('scfs/v1','/opportunities/(?P<id>\d+)/handoff',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_handoff'),'permission_callback'=>function($r){return current_user_can('edit_post',absint($r['id']));}));register_rest_route('scfs/v1','/opportunities/(?P<id>\d+)/recalculate',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'rest_recalculate'),'permission_callback'=>function($r){return current_user_can('edit_post',absint($r['id']));}));}
    public function rest_list(){return rest_ensure_response(array('items'=>$this->roadmap_items(),'methodology'=>'Advisory weighted score with effort penalty and minimum-evidence gate. Human approval is required for roadmap state changes.'));}
    public function rest_handoff($r){return rest_ensure_response($this->handoff(absint($r['id'])));}
    public function rest_recalculate($r){$id=absint($r['id']);$result=$this->calculate($id);$this->audit($id,'score_recalculated_via_rest',array('score'=>$result['score']));return rest_ensure_response($result);}
    public function columns($columns){$columns['scfs_opportunity']='Opportunity';return $columns;}
    public function column_value($column,$id){if('scfs_opportunity'!==$column)return;$s=get_post_meta($id,'_scfs_opportunity_score',true);$state=get_post_meta($id,'_scfs_opportunity_state',true)?:'unscored';echo '<strong>'.esc_html(is_array($s)?$s['score'].' / 100':'—').'</strong><br>'.esc_html($this->states()[$state]??$state);}
}
