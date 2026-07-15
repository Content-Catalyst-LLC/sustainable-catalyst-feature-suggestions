<?php
if (!defined('ABSPATH')) { exit; }

final class SCFS_Platform_Governance {
    const VERSION = '4.3.0';
    const OPTION_KEY = 'scfs_platform_governance';
    const AUDIT_KEY = 'scfs_platform_audit';
    const NONCE = 'scfs_platform_governance';
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_scfs_save_platform_governance', array($this, 'save_settings'));
        add_action('admin_post_scfs_export_platform_snapshot', array($this, 'export_snapshot'));
        add_action('admin_post_scfs_run_retention', array($this, 'run_retention'));
        add_action('rest_api_init', array($this, 'rest_routes'));
        add_action('save_post_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, array($this, 'enforce_public_decision_rationale'), 30, 2);
    }

    private function defaults() {
        return array(
            'suggestion_retention_days' => 0,
            'response_retention_days' => 730,
            'contact_anonymization_days' => 365,
            'audit_retention_entries' => 250,
            'require_public_decision_rationale' => '1',
            'enable_retention_actions' => '',
        );
    }

    private function settings() {
        return wp_parse_args((array) get_option(self::OPTION_KEY, array()), $this->defaults());
    }

    public function admin_menu() {
        $parent = 'edit.php?post_type=' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE;
        add_submenu_page($parent, __('Platform Command Center','sustainable-catalyst-feature-suggestions'), __('Platform Command Center','sustainable-catalyst-feature-suggestions'), 'edit_posts', 'scfs-platform-command-center', array($this,'render_dashboard'));
        add_submenu_page($parent, __('Governance and Retention','sustainable-catalyst-feature-suggestions'), __('Governance & Retention','sustainable-catalyst-feature-suggestions'), 'manage_options', 'scfs-platform-governance', array($this,'render_settings'));
    }

    private function count_posts($type, $statuses = array('publish','pending','draft','private')) {
        $q = new WP_Query(array('post_type'=>$type,'post_status'=>$statuses,'posts_per_page'=>1,'fields'=>'ids','no_found_rows'=>false));
        return (int) $q->found_posts;
    }

    private function module_status() {
        $settings = get_option(Sustainable_Catalyst_Feature_Suggestions::OPTION_KEY, array());
        return array(
            'product_support_platform' => array('label'=>'Unified support platform','ready'=>class_exists('SCFS_Product_Support_Platform') && shortcode_exists(SCFS_Product_Support_Platform::SHORTCODE) && post_type_exists(SCFS_Product_Support_Platform::RELEASE_POST_TYPE),'detail'=>'Unified public support center, release intelligence, participation, and private handoff boundary'),
            'feature_intake' => array('label'=>'Feature intake','ready'=>shortcode_exists(Sustainable_Catalyst_Feature_Suggestions::SHORTCODE),'detail'=>'Structured public suggestions and review workflow'),
            'ai_triage' => array('label'=>'AI triage','ready'=>!empty($settings['ai_backend_url']),'detail'=>!empty($settings['ai_backend_url'])?'Backend URL configured':'Deterministic/manual workflow remains available'),
            'forms_surveys' => array('label'=>'Forms and surveys','ready'=>post_type_exists('scfs_form'),'detail'=>'Builder, conditional logic, quotas, and response storage'),
            'survey_intelligence' => array('label'=>'Survey intelligence','ready'=>class_exists('SCFS_Survey_Intelligence'),'detail'=>'Descriptive analysis and research-intelligence exports'),
            'librarian_feedback' => array('label'=>'Research Librarian bridge','ready'=>shortcode_exists('sc_librarian_feedback'),'detail'=>'Contextual route, source, topic, and tool feedback'),
            'public_participation' => array('label'=>'Public participation','ready'=>shortcode_exists('sc_public_ideas'),'detail'=>'Moderated ideas, advisory support voting, and official responses'),
            'roadmap_workflow' => array('label'=>'Roadmap workflow','ready'=>class_exists('SCFS_Opportunity_Workflow'),'detail'=>'Evidence-weighted scoring and human-controlled states'),
            'product_taxonomy' => array('label'=>'Product taxonomy','ready'=>class_exists('SCFS_Product_Integration') && taxonomy_exists('scfs_product'),'detail'=>'Shared products, versions, components, issue types, releases, and migration'),
            'knowledge_base' => array('label'=>'Support Knowledge Base','ready'=>class_exists('SCFS_Knowledge_Base_Foundation') && post_type_exists(SCFS_Knowledge_Base_Foundation::ARTICLE_POST_TYPE) && shortcode_exists(SCFS_Knowledge_Base_Foundation::SHORTCODE),'detail'=>'Support Articles, collections, templates, public search, and REST records'),
            'documentation_intelligence' => array('label'=>'Documentation intelligence','ready'=>class_exists('SCFS_Documentation_Feature_Intelligence') && post_type_exists(SCFS_Documentation_Feature_Intelligence::GAP_POST_TYPE),'detail'=>'Article feedback, failed-search gaps, case relationships, and support-demand signals'),
            'editorial_governance' => array('label'=>'Editorial governance','ready'=>class_exists('SCFS_Editorial_Governance') && post_type_exists(SCFS_Editorial_Governance::NOTE_POST_TYPE),'detail'=>'Assigned review, approval gates, documentation standards, scheduling, expiration, reminders, and audit history'),
            'guided_resolution' => array('label'=>'Guided resolution','ready'=>class_exists('SCFS_Guided_Resolution') && shortcode_exists(SCFS_Guided_Resolution::SHORTCODE),'detail'=>'Product-aware search, error matching, known-issue prioritization, and unresolved support handoff'),
            'known_issues' => array('label'=>'Known issues','ready'=>class_exists('SCFS_Knowledge_Base_Foundation') && post_type_exists(SCFS_Knowledge_Base_Foundation::ISSUE_POST_TYPE),'detail'=>'Public issue status, severity, symptoms, workarounds, resolutions, and releases'),
            'contact_handoff' => array('label'=>'Contact and Engagement handoff','ready'=>class_exists('SCFS_Product_Integration'),'detail'=>'Typed private support handoff contract; automatic case creation remains disabled'),
            'shared_events' => array('label'=>'Shared events','ready'=>!empty($settings['enable_shared_events']),'detail'=>!empty($settings['enable_shared_events'])?'Platform events enabled':'Enable in Feature Suggestions settings'),
            'webhooks' => array('label'=>'Signed webhooks','ready'=>!empty($settings['enable_webhooks']) && !empty($settings['webhook_url']),'detail'=>!empty($settings['enable_webhooks'])?'Configured for external delivery':'Optional; local hooks remain active'),
        );
    }

    private function counts() {
        $op_states = array();
        $q = new WP_Query(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','no_found_rows'=>true));
        foreach ($q->posts as $id) {
            $state = get_post_meta($id, '_scfs_opportunity_state', true) ?: 'unscored';
            $op_states[$state] = isset($op_states[$state]) ? $op_states[$state] + 1 : 1;
        }
        return array(
            'suggestions'=>$this->count_posts(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE),
            'forms'=>post_type_exists('scfs_form')?$this->count_posts('scfs_form'):0,
            'responses'=>post_type_exists('scfs_form_response')?$this->count_posts('scfs_form_response'):0,
            'public_ideas'=>(int) count(get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','meta_key'=>'_scfs_public_visibility','meta_value'=>'1'))),
            'support_articles'=>post_type_exists('sc_support_article')?$this->count_posts('sc_support_article'):0,
            'known_issues'=>post_type_exists('sc_known_issue')?$this->count_posts('sc_known_issue'):0,
            'release_records'=>post_type_exists('sc_release_record')?$this->count_posts('sc_release_record'):0,
            'resolution_searches'=>class_exists('SCFS_Guided_Resolution')?(int)(SCFS_Guided_Resolution::instance()->analytics_summary()['total_searches']??0):0,
            'documentation_gaps'=>post_type_exists('sc_doc_gap')?$this->count_posts('sc_doc_gap'):0,
            'article_feedback'=>class_exists('SCFS_Documentation_Feature_Intelligence')?(int)(SCFS_Documentation_Feature_Intelligence::instance()->analytics_summary()['feedback_total']??0):0,
            'case_relationships'=>class_exists('SCFS_Documentation_Feature_Intelligence')?(int)(SCFS_Documentation_Feature_Intelligence::instance()->analytics_summary()['case_relationships']??0):0,
            'roadmap_states'=>$op_states,
        );
    }

    private function readiness() {
        $modules=$this->module_status(); $passed=0;
        foreach($modules as $m) if($m['ready']) $passed++;
        $tests=array(
            array('label'=>'Unified support center registered','pass'=>class_exists('SCFS_Product_Support_Platform') && shortcode_exists('scfs_product_support_center')),
            array('label'=>'Release intelligence registered','pass'=>post_type_exists('sc_release_record')),
            array('label'=>'Core feature intake registered','pass'=>post_type_exists(Sustainable_Catalyst_Feature_Suggestions::POST_TYPE)),
            array('label'=>'Forms module registered','pass'=>post_type_exists('scfs_form')),
            array('label'=>'Public ideas shortcode registered','pass'=>shortcode_exists('sc_public_ideas')),
            array('label'=>'Research Librarian feedback shortcode registered','pass'=>shortcode_exists('sc_librarian_feedback')),
            array('label'=>'Opportunity workflow loaded','pass'=>class_exists('SCFS_Opportunity_Workflow')),
            array('label'=>'Shared event schema available','pass'=>defined('Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION')),
            array('label'=>'Product taxonomy schema available','pass'=>class_exists('SCFS_Product_Integration') && taxonomy_exists('scfs_product')),
            array('label'=>'Support Knowledge Base registered','pass'=>class_exists('SCFS_Knowledge_Base_Foundation') && post_type_exists('sc_support_article')),
            array('label'=>'Known Issues registered','pass'=>class_exists('SCFS_Knowledge_Base_Foundation') && post_type_exists('sc_known_issue')),
            array('label'=>'Knowledge Base shortcode registered','pass'=>shortcode_exists('scfs_support_knowledge_base')),
            array('label'=>'Documentation intelligence registered','pass'=>class_exists('SCFS_Documentation_Feature_Intelligence') && post_type_exists('sc_doc_gap')),
            array('label'=>'Support relationship contract available','pass'=>class_exists('SCFS_Documentation_Feature_Intelligence')),
            array('label'=>'Contact and Engagement contract available','pass'=>class_exists('SCFS_Product_Integration')),
        );
        foreach($tests as $t) if($t['pass']) $passed++;
        return array('passed'=>$passed,'total'=>count($modules)+count($tests),'modules'=>$modules,'tests'=>$tests);
    }

    public function render_dashboard() {
        if (!current_user_can('edit_posts')) wp_die(__('You do not have permission to view this page.','sustainable-catalyst-feature-suggestions'));
        $c=$this->counts(); $r=$this->readiness();
        $snapshot=wp_nonce_url(admin_url('admin-post.php?action=scfs_export_platform_snapshot'), self::NONCE.'_snapshot');
        echo '<div class="wrap scfs-platform-center"><h1>'.esc_html__('Sustainable Catalyst Product Support and Feedback Platform','sustainable-catalyst-feature-suggestions').'</h1>';
        echo '<p>'.esc_html__('Unified operational view across support documentation, known issues, feature intake, forms, surveys, research intelligence, public participation, and roadmap governance.','sustainable-catalyst-feature-suggestions').'</p>';
        echo '<p><a class="button button-primary" href="'.esc_url($snapshot).'">'.esc_html__('Export platform snapshot','sustainable-catalyst-feature-suggestions').'</a> <a class="button" href="'.esc_url(admin_url('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE.'&page=scfs-platform-governance')).'">'.esc_html__('Governance settings','sustainable-catalyst-feature-suggestions').'</a></p>';
        echo '<div class="scfs-intel-cards">';
        foreach(array('suggestions'=>'Suggestions','support_articles'=>'Support articles','known_issues'=>'Known issues','documentation_gaps'=>'Documentation gaps','article_feedback'=>'Article feedback','case_relationships'=>'Case relationships','release_records'=>'Release records','forms'=>'Forms & surveys','responses'=>'Responses','public_ideas'=>'Public ideas') as $k=>$label) echo '<div class="scfs-intel-card"><span>'.esc_html($label).'</span><strong>'.esc_html((string)$c[$k]).'</strong></div>';
        echo '<div class="scfs-intel-card"><span>'.esc_html__('Release readiness','sustainable-catalyst-feature-suggestions').'</span><strong>'.esc_html($r['passed'].' / '.$r['total']).'</strong></div></div>';
        echo '<h2>'.esc_html__('Module health','sustainable-catalyst-feature-suggestions').'</h2><table class="widefat striped"><thead><tr><th>Module</th><th>Status</th><th>Operational note</th></tr></thead><tbody>';
        foreach($r['modules'] as $m) echo '<tr><td><strong>'.esc_html($m['label']).'</strong></td><td>'.($m['ready']?'<span class="scfs-status scfs-status--ok">Ready</span>':'<span class="scfs-status">Optional / needs configuration</span>').'</td><td>'.esc_html($m['detail']).'</td></tr>';
        echo '</tbody></table><h2>'.esc_html__('Release-readiness checks','sustainable-catalyst-feature-suggestions').'</h2><ul class="scfs-readiness-list">';
        foreach($r['tests'] as $t) echo '<li>'.($t['pass']?'✓':'✕').' '.esc_html($t['label']).'</li>';
        echo '</ul><h2>'.esc_html__('Roadmap distribution','sustainable-catalyst-feature-suggestions').'</h2>';
        if(!$c['roadmap_states']) echo '<p>No roadmap records yet.</p>'; else { echo '<table class="widefat striped"><thead><tr><th>State</th><th>Count</th></tr></thead><tbody>'; foreach($c['roadmap_states'] as $state=>$count) echo '<tr><td>'.esc_html(ucwords(str_replace('_',' ',$state))).'</td><td>'.esc_html((string)$count).'</td></tr>'; echo '</tbody></table>'; }
        echo '<p class="description">'.esc_html__('This dashboard reports configuration and record availability. External service availability should also be verified from the Integration and AI status screens.','sustainable-catalyst-feature-suggestions').'</p></div>';
    }

    public function render_settings() {
        if (!current_user_can('manage_options')) wp_die(__('You do not have permission to manage governance settings.','sustainable-catalyst-feature-suggestions'));
        $s=$this->settings(); $dry=wp_nonce_url(admin_url('admin-post.php?action=scfs_run_retention&mode=dry-run'),self::NONCE.'_retention'); $run=wp_nonce_url(admin_url('admin-post.php?action=scfs_run_retention&mode=execute'),self::NONCE.'_retention');
        echo '<div class="wrap"><h1>'.esc_html__('Governance and Retention','sustainable-catalyst-feature-suggestions').'</h1><p>'.esc_html__('Configure privacy-oriented retention controls. Zero means retain indefinitely. Destructive retention actions are disabled unless explicitly enabled.','sustainable-catalyst-feature-suggestions').'</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="scfs_save_platform_governance">'; wp_nonce_field(self::NONCE,'scfs_platform_nonce');
        echo '<table class="form-table"><tr><th>Suggestion retention</th><td><input type="number" min="0" name="suggestion_retention_days" value="'.esc_attr($s['suggestion_retention_days']).'"> days <p class="description">Applies only to suggestions already in released, declined, or parked opportunity states.</p></td></tr>';
        echo '<tr><th>Form response retention</th><td><input type="number" min="0" name="response_retention_days" value="'.esc_attr($s['response_retention_days']).'"> days</td></tr>';
        echo '<tr><th>Contact anonymization</th><td><input type="number" min="0" name="contact_anonymization_days" value="'.esc_attr($s['contact_anonymization_days']).'"> days <p class="description">Clears stored names and email addresses while retaining non-identifying feedback.</p></td></tr>';
        echo '<tr><th>Audit log limit</th><td><input type="number" min="25" max="2000" name="audit_retention_entries" value="'.esc_attr($s['audit_retention_entries']).'"></td></tr>';
        echo '<tr><th>Public decisions</th><td><label><input type="checkbox" name="require_public_decision_rationale" value="1" '.checked($s['require_public_decision_rationale'],'1',false).'> Require a rationale before public ideas are marked released or not planned</label></td></tr>';
        echo '<tr><th>Destructive retention</th><td><label><input type="checkbox" name="enable_retention_actions" value="1" '.checked($s['enable_retention_actions'],'1',false).'> Enable execution after reviewing a dry run</label></td></tr></table><p><button class="button button-primary">Save governance settings</button></p></form>';
        echo '<hr><h2>Retention review</h2><p><a class="button" href="'.esc_url($dry).'">Run dry-run report</a> <a class="button" href="'.esc_url($run).'">Execute configured retention</a></p><p class="description">Execution is blocked unless destructive retention is enabled. Actions are recorded in the platform audit log.</p>';
        $audit=array_reverse((array)get_option(self::AUDIT_KEY,array())); echo '<h2>Platform audit log</h2><table class="widefat striped"><thead><tr><th>Date</th><th>Action</th><th>User</th><th>Details</th></tr></thead><tbody>'; if(!$audit) echo '<tr><td colspan="4">No platform governance actions recorded.</td></tr>'; foreach(array_slice($audit,0,50) as $item) echo '<tr><td>'.esc_html($item['timestamp']??'').'</td><td>'.esc_html($item['action']??'').'</td><td>'.esc_html($item['user']??'system').'</td><td><code>'.esc_html(wp_json_encode($item['details']??array())).'</code></td></tr>'; echo '</tbody></table></div>';
    }

    public function enforce_public_decision_rationale($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        $settings=$this->settings(); if(empty($settings['require_public_decision_rationale'])) return;
        if ('1' !== get_post_meta($post_id,'_scfs_public_visibility',true)) return;
        $state=get_post_meta($post_id,'_scfs_public_state',true);
        if (!in_array($state,array('released','not_planned'),true)) return;
        $response=trim((string)get_post_meta($post_id,'_scfs_official_response',true));
        if ($response !== '') return;
        update_post_meta($post_id,'_scfs_public_state','under_review');
        $this->audit('public_decision_rationale_required',array('post_id'=>$post_id,'attempted_state'=>$state));
        add_filter('redirect_post_location', function($location){ return add_query_arg('scfs_rationale_required','1',$location); });
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer(self::NONCE,'scfs_platform_nonce');
        $new=array(
            'suggestion_retention_days'=>absint($_POST['suggestion_retention_days']??0),
            'response_retention_days'=>absint($_POST['response_retention_days']??730),
            'contact_anonymization_days'=>absint($_POST['contact_anonymization_days']??365),
            'audit_retention_entries'=>min(2000,max(25,absint($_POST['audit_retention_entries']??250))),
            'require_public_decision_rationale'=>isset($_POST['require_public_decision_rationale'])?'1':'',
            'enable_retention_actions'=>isset($_POST['enable_retention_actions'])?'1':'',
        );
        update_option(self::OPTION_KEY,$new,false); $this->audit('governance_settings_updated',$new);
        wp_safe_redirect(admin_url('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE.'&page=scfs-platform-governance&updated=1')); exit;
    }

    private function retention_report($execute=false) {
        $s=$this->settings(); $now=time(); $report=array('mode'=>$execute?'execute':'dry-run','suggestions_deleted'=>0,'responses_deleted'=>0,'contacts_anonymized'=>0,'blocked'=>false);
        if($execute && empty($s['enable_retention_actions'])) { $report['blocked']=true; return $report; }
        if(absint($s['contact_anonymization_days'])>0) {
            $before=gmdate('Y-m-d H:i:s',$now-DAY_IN_SECONDS*absint($s['contact_anonymization_days']));
            $ids=get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','date_query'=>array(array('before'=>$before))));
            foreach($ids as $id) { if(get_post_meta($id,'_scfs_name',true)||get_post_meta($id,'_scfs_email',true)) { $report['contacts_anonymized']++; if($execute){ delete_post_meta($id,'_scfs_name'); delete_post_meta($id,'_scfs_email'); } } }
        }
        if(absint($s['response_retention_days'])>0 && post_type_exists('scfs_form_response')) {
            $before=gmdate('Y-m-d H:i:s',$now-DAY_IN_SECONDS*absint($s['response_retention_days']));
            $ids=get_posts(array('post_type'=>'scfs_form_response','post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','date_query'=>array(array('before'=>$before)))); $report['responses_deleted']=count($ids); if($execute) foreach($ids as $id) wp_delete_post($id,true);
        }
        if(absint($s['suggestion_retention_days'])>0) {
            $before=gmdate('Y-m-d H:i:s',$now-DAY_IN_SECONDS*absint($s['suggestion_retention_days']));
            $ids=get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','pending','draft','private'),'posts_per_page'=>-1,'fields'=>'ids','date_query'=>array(array('before'=>$before))));
            foreach($ids as $id){ $state=get_post_meta($id,'_scfs_opportunity_state',true); if(in_array($state,array('released','declined','parked'),true)){ $report['suggestions_deleted']++; if($execute) wp_delete_post($id,true); } }
        }
        return $report;
    }

    public function run_retention() {
        if (!current_user_can('manage_options')) wp_die('Forbidden'); check_admin_referer(self::NONCE.'_retention');
        $execute=($_GET['mode']??'dry-run')==='execute'; $report=$this->retention_report($execute); $this->audit($execute?'retention_executed':'retention_dry_run',$report);
        wp_safe_redirect(admin_url('edit.php?post_type='.Sustainable_Catalyst_Feature_Suggestions::POST_TYPE.'&page=scfs-platform-governance&retention='.rawurlencode(wp_json_encode($report)))); exit;
    }

    private function audit($action,$details=array()) {
        $log=(array)get_option(self::AUDIT_KEY,array()); $u=wp_get_current_user(); $log[]=array('timestamp'=>gmdate('c'),'action'=>sanitize_key($action),'user'=>$u->exists()?$u->user_login:'system','details'=>$details);
        $limit=absint($this->settings()['audit_retention_entries']); if(count($log)>$limit) $log=array_slice($log,-$limit); update_option(self::AUDIT_KEY,$log,false);
    }

    private function snapshot() {
        return array('ok'=>true,'platform'=>'Sustainable Catalyst Product Support and Feedback Platform','version'=>self::VERSION,'generated_at'=>gmdate('c'),'counts'=>$this->counts(),'readiness'=>$this->readiness(),'governance'=>array_diff_key($this->settings(),array('enable_retention_actions'=>true)),'event_schema_version'=>Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,'product_taxonomy'=>class_exists('SCFS_Product_Integration')?SCFS_Product_Integration::instance()->taxonomy_schema():array(),'product_coverage'=>class_exists('SCFS_Product_Integration')?SCFS_Product_Integration::instance()->coverage():array(),'knowledge_base'=>class_exists('SCFS_Knowledge_Base_Foundation')?SCFS_Knowledge_Base_Foundation::instance()->schema_record():array(),'guided_resolution'=>class_exists('SCFS_Guided_Resolution')?SCFS_Guided_Resolution::instance()->schema_record():array(),'guided_resolution_handoff'=>class_exists('SCFS_Guided_Resolution')?SCFS_Guided_Resolution::instance()->handoff_schema():array(),'contact_engagement_handoff'=>class_exists('SCFS_Product_Integration')?SCFS_Product_Integration::instance()->handoff_schema():array(),'product_support_platform'=>class_exists('SCFS_Product_Support_Platform')?SCFS_Product_Support_Platform::instance()->schema_record():array(),'release_intelligence'=>class_exists('SCFS_Product_Support_Platform')?SCFS_Product_Support_Platform::instance()->overview_record(''):array(),'editorial_governance'=>class_exists('SCFS_Editorial_Governance')?SCFS_Editorial_Governance::instance()->schema_record():array(),'editorial_governance_summary'=>class_exists('SCFS_Editorial_Governance')?SCFS_Editorial_Governance::instance()->governance_summary():array(),'human_review_required'=>true);
    }

    public function export_snapshot() {
        if (!current_user_can('edit_posts')) wp_die('Forbidden'); check_admin_referer(self::NONCE.'_snapshot'); $this->audit('platform_snapshot_exported');
        header('Content-Type: application/json; charset=utf-8'); header('Content-Disposition: attachment; filename=scfs-platform-snapshot-'.gmdate('Y-m-d').'.json'); echo wp_json_encode($this->snapshot(),JSON_PRETTY_PRINT); exit;
    }

    public function rest_routes() {
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE,'/platform/status',array('methods'=>'GET','callback'=>array($this,'rest_status'),'permission_callback'=>function(){return current_user_can('edit_posts');}));
        register_rest_route(Sustainable_Catalyst_Feature_Suggestions::REST_NAMESPACE,'/platform/snapshot',array('methods'=>'GET','callback'=>array($this,'rest_snapshot'),'permission_callback'=>function(){return current_user_can('edit_posts');}));
    }
    public function rest_status(){ $r=$this->readiness(); return rest_ensure_response(array('ok'=>true,'version'=>self::VERSION,'readiness'=>array('passed'=>$r['passed'],'total'=>$r['total']),'modules'=>$r['modules'])); }
    public function rest_snapshot(){ return rest_ensure_response($this->snapshot()); }
}
