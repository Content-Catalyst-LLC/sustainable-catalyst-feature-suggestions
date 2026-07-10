<?php
if (!defined('ABSPATH')) { exit; }

final class SCFS_Survey_Intelligence {
    const VERSION='2.6.0';
    private static $instance=null;
    public static function instance(){if(self::$instance===null)self::$instance=new self();return self::$instance;}
    private function __construct(){
        add_action('admin_menu',array($this,'admin_menu'));
        add_action('admin_post_scfs_run_survey_analysis',array($this,'run_analysis'));
        add_action('admin_post_scfs_export_survey_analysis',array($this,'export_analysis'));
        add_action('rest_api_init',array($this,'rest_routes'));
    }
    public function admin_menu(){add_submenu_page('edit.php?post_type=sc_feature_suggest','Survey Intelligence','Survey Intelligence','edit_posts','scfs-survey-intelligence',array($this,'page'));}
    private function forms(){return get_posts(array('post_type'=>SCFS_Forms_Foundation::FORM_TYPE,'post_status'=>array('publish','draft','private'),'numberposts'=>-1,'orderby'=>'title','order'=>'ASC'));}
    private function selected_form(){return isset($_GET['form_id'])?absint($_GET['form_id']):0;}
    private function settings(){return wp_parse_args(get_option('scfs_settings',array()),array('ai_backend_url'=>'','ai_backend_api_key'=>'','ai_timeout'=>30));}
    private function payload($form_id){
        $form=get_post($form_id); if(!$form||$form->post_type!==SCFS_Forms_Foundation::FORM_TYPE)return new WP_Error('scfs_invalid_form','Invalid form or survey.');
        $schema=get_post_meta($form_id,'_scfs_form_schema',true); $fields=array();
        foreach(($schema['fields']??array()) as $f){$fields[]=array('key'=>$f['key'],'label'=>$f['label'],'type'=>$f['type'],'options'=>array_values((array)($f['options']??array())),'scale_group'=>isset($f['scale_group'])?$f['scale_group']:null);}
        $q=new WP_Query(array('post_type'=>SCFS_Forms_Foundation::RESPONSE_TYPE,'post_status'=>'private','posts_per_page'=>-1,'meta_key'=>'_scfs_form_id','meta_value'=>$form_id,'orderby'=>'date','order'=>'ASC'));
        $responses=array(); foreach($q->posts as $r){$responses[]=array('response_id'=>get_post_meta($r->ID,'_scfs_response_uuid',true),'submitted_at'=>get_post_meta($r->ID,'_scfs_response_submitted_at',true),'answers'=>(array)get_post_meta($r->ID,'_scfs_response_answers',true));}
        return array('instrument_id'=>(string)$form_id,'instrument_title'=>$form->post_title,'schema_revision'=>max(1,absint(get_post_meta($form_id,'_scfs_schema_revision',true))),'fields'=>$fields,'responses'=>$responses,'segment_by'=>isset($_POST['segment_by'])?sanitize_key(wp_unslash($_POST['segment_by'])):null);
    }
    private function request($form_id){
        $settings=$this->settings(); $base=untrailingslashit((string)$settings['ai_backend_url']); if($base==='')return new WP_Error('scfs_backend_missing','Configure the Python backend URL in Feature Suggestions settings.');
        $payload=$this->payload($form_id); if(is_wp_error($payload))return $payload; $headers=array('Content-Type'=>'application/json'); if(!empty($settings['ai_backend_api_key']))$headers['X-SCFS-AI-Key']=$settings['ai_backend_api_key'];
        $r=wp_remote_post($base.'/v1/surveys/analyze',array('timeout'=>max(10,min(120,absint($settings['ai_timeout']))),'headers'=>$headers,'body'=>wp_json_encode($payload),'data_format'=>'body'));
        if(is_wp_error($r))return $r; $code=wp_remote_retrieve_response_code($r); $data=json_decode(wp_remote_retrieve_body($r),true); if($code<200||$code>=300||!is_array($data))return new WP_Error('scfs_analysis_failed','Survey backend returned HTTP '.$code.'.');
        update_post_meta($form_id,'_scfs_survey_analysis',$data); update_post_meta($form_id,'_scfs_survey_analysis_at',gmdate('c')); update_post_meta($form_id,'_scfs_survey_analysis_version',sanitize_text_field($data['analysis_version']??''));
        do_action('scfs_event',array('event_id'=>wp_generate_uuid4(),'event_type'=>'survey.analysis_completed','schema_version'=>'1.0','source'=>'feature_suggestions','source_version'=>self::VERSION,'occurred_at'=>gmdate('c'),'data'=>array('form_id'=>$form_id,'response_count'=>absint($data['response_count']??0),'analysis_version'=>$data['analysis_version']??'')));
        do_action('sc_platform_event',array('event_type'=>'survey.analysis_completed','source'=>'feature_suggestions','form_id'=>$form_id,'response_count'=>absint($data['response_count']??0)));
        return $data;
    }
    public function run_analysis(){if(!current_user_can('edit_posts'))wp_die('Not permitted.'); check_admin_referer('scfs_run_survey_analysis'); $id=absint($_POST['form_id']??0); $result=$this->request($id); $url=admin_url('edit.php?post_type=sc_feature_suggest&page=scfs-survey-intelligence&form_id='.$id); if(is_wp_error($result))$url=add_query_arg('scfs_error',rawurlencode($result->get_error_message()),$url); else $url=add_query_arg('scfs_updated','1',$url); wp_safe_redirect($url);exit;}
    public function page(){if(!current_user_can('edit_posts'))return;$forms=$this->forms();$id=$this->selected_form();$analysis=$id?get_post_meta($id,'_scfs_survey_analysis',true):array();$schema=$id?get_post_meta($id,'_scfs_form_schema',true):array(); ?>
      <div class="wrap scfs-survey-intelligence"><h1>Survey Analysis and Research Intelligence</h1><p>Descriptive statistics are computed by the Python service. Machine-assisted themes are advisory and require human review.</p>
      <?php if(isset($_GET['scfs_error'])):?><div class="notice notice-error"><p><?php echo esc_html(wp_unslash($_GET['scfs_error']));?></p></div><?php endif;?>
      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php'));?>"><input type="hidden" name="action" value="scfs_run_survey_analysis"><?php wp_nonce_field('scfs_run_survey_analysis');?>
      <label><strong>Form or survey</strong> <select name="form_id" required><option value="">Select</option><?php foreach($forms as$f):?><option value="<?php echo esc_attr($f->ID);?>" <?php selected($id,$f->ID);?>><?php echo esc_html($f->post_title);?></option><?php endforeach;?></select></label>
      <?php if($id):?><label> Segment by <select name="segment_by"><option value="">None</option><?php foreach(($schema['fields']??array())as$field):if(!in_array($field['type'],array('select','radio','checkbox','rating','likert','consent'),true))continue;?><option value="<?php echo esc_attr($field['key']);?>"><?php echo esc_html($field['label']);?></option><?php endforeach;?></select></label><?php endif;?> <button class="button button-primary">Run analysis</button></form>
      <?php if($id&&!empty($analysis)):$this->render($analysis,$id);endif;?></div><?php }
    private function render($a,$id){echo '<hr><h2>'.esc_html($a['instrument_title']??'Analysis').'</h2><p><strong>'.absint($a['response_count']??0).'</strong> responses · <strong>'.absint($a['field_count']??0).'</strong> fields · generated '.esc_html($a['generated_at']??'').'</p>';
      echo '<p><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=scfs_export_survey_analysis&form_id='.$id),'scfs_export_survey_analysis')).'">Download analysis JSON</a></p>';
      foreach((array)($a['warnings']??array())as$w)echo '<div class="notice notice-warning inline"><p>'.esc_html($w).'</p></div>';
      echo '<h2>Executive summary</h2><ul>';foreach((array)($a['narrative_summary']??array())as$x)echo '<li>'.esc_html($x).'</li>';echo '</ul>';
      $c=$a['completion']??array();echo '<h2>Completion</h2><table class="widefat striped"><tbody><tr><th>Average completion</th><td>'.esc_html($c['average_completion_rate']??0).'%</td></tr><tr><th>Complete responses</th><td>'.absint($c['complete_response_count']??0).'</td></tr><tr><th>Partial responses</th><td>'.absint($c['partial_response_count']??0).'</td></tr></tbody></table>';
      echo '<h2>Question summaries</h2><table class="widefat striped"><thead><tr><th>Question</th><th>Answered</th><th>Rate</th><th>Summary</th></tr></thead><tbody>';foreach((array)($a['questions']??array())as$q){$summary='';if(isset($q['mean']))$summary='Mean '.$q['mean'].'; SD '.$q['standard_deviation'];elseif(!empty($q['distribution']))$summary=($q['distribution'][0]['value']??'').' ('.absint($q['distribution'][0]['count']??0).')';elseif(isset($q['average_length_words']))$summary='Average '.esc_html($q['average_length_words']).' words';echo '<tr><td>'.esc_html($q['label']??$q['key']).'</td><td>'.absint($q['answered']??0).'</td><td>'.esc_html($q['answer_rate']??0).'%</td><td>'.esc_html($summary).'</td></tr>';}echo '</tbody></table>';
      if(!empty($a['reliability'])){echo '<h2>Scale reliability</h2><table class="widefat striped"><thead><tr><th>Scale</th><th>Items</th><th>Complete cases</th><th>Cronbach alpha</th></tr></thead><tbody>';foreach($a['reliability']as$r)echo '<tr><td>'.esc_html($r['scale_group']).'</td><td>'.esc_html(implode(', ',$r['items'])).'</td><td>'.absint($r['complete_cases']).'</td><td>'.esc_html($r['cronbach_alpha']===null?'Unavailable':$r['cronbach_alpha']).'</td></tr>';echo '</tbody></table>';}
      if(!empty($a['text_intelligence'])){echo '<h2>Open-text themes</h2>';foreach($a['text_intelligence']as$t){echo '<h3>'.esc_html($t['label']).'</h3><p><em>'.esc_html($t['method']).'</em></p><p>';foreach($t['themes'] as$theme)echo '<span style="display:inline-block;margin:0 8px 8px 0;padding:4px 8px;border:1px solid #ccd0d4">'.esc_html($theme['term']).' · '.absint($theme['mentions']).'</span>';echo '</p>';}}
      echo '<p><strong>Boundary:</strong> Results are descriptive. They do not establish statistical significance, causality, representativeness, or professional research conclusions.</p>';
    }
    public function export_analysis(){if(!current_user_can('edit_posts'))wp_die('Not permitted.');check_admin_referer('scfs_export_survey_analysis');$id=absint($_GET['form_id']??0);$a=get_post_meta($id,'_scfs_survey_analysis',true);if(!$a)wp_die('No analysis available.');header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename=scfs-survey-analysis-'.$id.'-'.gmdate('Y-m-d').'.json');echo wp_json_encode($a,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);exit;}
    public function rest_routes(){register_rest_route('scfs/v1','/forms/(?P<id>\d+)/analysis',array(array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_get'),'permission_callback'=>function(){return current_user_can('edit_posts');}),array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'rest_run'),'permission_callback'=>function(){return current_user_can('edit_posts');})));}
    public function rest_get($r){$id=absint($r['id']);return rest_ensure_response(array('analysis'=>get_post_meta($id,'_scfs_survey_analysis',true),'analyzed_at'=>get_post_meta($id,'_scfs_survey_analysis_at',true)));}
    public function rest_run($r){$result=$this->request(absint($r['id']));return is_wp_error($result)?$result:rest_ensure_response($result);}
}
