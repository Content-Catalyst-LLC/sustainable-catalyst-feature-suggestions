<?php
if (!defined('ABSPATH')) { exit; }

final class SCFS_Forms_Foundation {
    const FORM_TYPE = 'scfs_form';
    const RESPONSE_TYPE = 'scfs_response';
    const SCHEMA_VERSION = '2.0';
    const SHORTCODE = 'sc_feedback_form';
    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_types'));
        add_action('init', array($this, 'handle_submission'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_action('add_meta_boxes', array($this, 'meta_boxes'));
        add_action('save_post_' . self::FORM_TYPE, array($this, 'save_form'), 10, 2);
        add_filter('manage_' . self::FORM_TYPE . '_posts_columns', array($this, 'form_columns'));
        add_action('manage_' . self::FORM_TYPE . '_posts_custom_column', array($this, 'form_column'), 10, 2);
        add_filter('manage_' . self::RESPONSE_TYPE . '_posts_columns', array($this, 'response_columns'));
        add_action('manage_' . self::RESPONSE_TYPE . '_posts_custom_column', array($this, 'response_column'), 10, 2);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_post_scfs_export_form_responses', array($this, 'export_responses'));
        add_action('admin_enqueue_scripts', array($this, 'admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('rest_api_init', array($this, 'rest_routes'));
    }

    public function register_types() {
        register_post_type(self::FORM_TYPE, array(
            'labels' => array('name'=>'Forms & Surveys','singular_name'=>'Form or Survey','add_new_item'=>'Create Form or Survey','edit_item'=>'Edit Form or Survey','menu_name'=>'Forms & Surveys'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=sc_feature_suggest','supports'=>array('title','editor'),'capability_type'=>'post','map_meta_cap'=>true,'show_in_rest'=>false,
        ));
        register_post_type(self::RESPONSE_TYPE, array(
            'labels' => array('name'=>'Form Responses','singular_name'=>'Form Response','menu_name'=>'Responses','edit_item'=>'View Form Response'),
            'public'=>false,'show_ui'=>true,'show_in_menu'=>'edit.php?post_type=sc_feature_suggest','supports'=>array('title'),'capability_type'=>'post','map_meta_cap'=>true,'show_in_rest'=>false,
        ));
    }

    public function register_assets() {
        wp_register_style('scfs-forms', plugins_url('../assets/forms.css', __FILE__), array(), '2.5.0');
        wp_register_script('scfs-forms', plugins_url('../assets/forms.js', __FILE__), array(), '2.5.0', true);
    }

    public function admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::FORM_TYPE) { return; }
        wp_enqueue_style('scfs-forms-admin', plugins_url('../assets/forms-admin.css', __FILE__), array(), '2.5.0');
        wp_enqueue_script('scfs-forms-admin', plugins_url('../assets/forms-admin.js', __FILE__), array(), '2.5.0', true);
    }

    public function meta_boxes() {
        add_meta_box('scfs-form-builder','Form and Survey Builder',array($this,'builder_box'),self::FORM_TYPE,'normal','high');
        add_meta_box('scfs-form-publishing','Publishing and Response Settings',array($this,'settings_box'),self::FORM_TYPE,'side','default');
        add_meta_box('scfs-response-details','Response Details',array($this,'response_box'),self::RESPONSE_TYPE,'normal','high');
    }

    private function field_types() {
        return array('text'=>'Short text','textarea'=>'Long text','email'=>'Email','number'=>'Number','url'=>'URL','date'=>'Date','select'=>'Dropdown','radio'=>'Radio buttons','checkbox'=>'Checkboxes','rating'=>'Rating scale','likert'=>'Likert scale','consent'=>'Consent','hidden'=>'Hidden context');
    }

    public function builder_box($post) {
        wp_nonce_field('scfs_save_form','scfs_form_nonce');
        $schema = get_post_meta($post->ID,'_scfs_form_schema',true);
        $fields = is_array($schema) && !empty($schema['fields']) ? $schema['fields'] : array();
        ?>
        <p>Create ordered fields below. Choice options use one line per option. Field keys become stable export and API identifiers.</p>
        <div id="scfs-builder" data-next="<?php echo esc_attr(count($fields)); ?>">
          <div class="scfs-builder-actions"><button type="button" class="button button-primary" id="scfs-add-field">Add field</button></div>
          <div id="scfs-field-list">
          <?php foreach ($fields as $i=>$field) { $this->field_row($i,$field); } ?>
          </div>
        </div>
        <script type="text/template" id="scfs-field-template"><?php $this->field_row('__INDEX__',array()); ?></script>
        <?php
    }

    private function field_row($i,$field) {
        $f=wp_parse_args($field,array('key'=>'','label'=>'','type'=>'text','required'=>false,'help'=>'','placeholder'=>'','options'=>array(),'validation'=>array(),'page'=>1,'condition_field'=>'','condition_operator'=>'equals','condition_value'=>'','randomize_options'=>false));
        $options=is_array($f['options'])?implode("\n",$f['options']):(string)$f['options'];
        ?>
        <section class="scfs-field-row" data-index="<?php echo esc_attr($i); ?>">
          <header><strong class="scfs-field-title"><?php echo esc_html($f['label'] ?: 'New field'); ?></strong><div><button type="button" class="button scfs-move-up" aria-label="Move field up">↑</button> <button type="button" class="button scfs-move-down" aria-label="Move field down">↓</button> <button type="button" class="button-link-delete scfs-remove-field">Remove</button></div></header>
          <div class="scfs-field-grid">
            <label>Label<input type="text" name="scfs_fields[<?php echo esc_attr($i); ?>][label]" value="<?php echo esc_attr($f['label']); ?>" required></label>
            <label>Stable key<input type="text" class="scfs-field-key" name="scfs_fields[<?php echo esc_attr($i); ?>][key]" value="<?php echo esc_attr($f['key']); ?>" pattern="[a-z0-9_\-]+"></label>
            <label>Field type<select name="scfs_fields[<?php echo esc_attr($i); ?>][type]" class="scfs-field-type"><?php foreach($this->field_types() as $k=>$label): ?><option value="<?php echo esc_attr($k); ?>" <?php selected($f['type'],$k); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
            <label class="scfs-check"><input type="checkbox" name="scfs_fields[<?php echo esc_attr($i); ?>][required]" value="1" <?php checked(!empty($f['required'])); ?>> Required</label>
            <label>Help text<textarea name="scfs_fields[<?php echo esc_attr($i); ?>][help]" rows="2"><?php echo esc_textarea($f['help']); ?></textarea></label>
            <label>Placeholder<input type="text" name="scfs_fields[<?php echo esc_attr($i); ?>][placeholder]" value="<?php echo esc_attr($f['placeholder']); ?>"></label>
            <label class="scfs-options">Options, one per line<textarea name="scfs_fields[<?php echo esc_attr($i); ?>][options]" rows="4"><?php echo esc_textarea($options); ?></textarea></label>
            <label>Page number<input type="number" min="1" name="scfs_fields[<?php echo esc_attr($i); ?>][page]" value="<?php echo esc_attr($f['page']); ?>"></label>
            <label>Show when field key<input type="text" name="scfs_fields[<?php echo esc_attr($i); ?>][condition_field]" value="<?php echo esc_attr($f['condition_field']); ?>"></label>
            <label>Condition<select name="scfs_fields[<?php echo esc_attr($i); ?>][condition_operator]"><option value="equals" <?php selected($f['condition_operator'],'equals'); ?>>equals</option><option value="not_equals" <?php selected($f['condition_operator'],'not_equals'); ?>>does not equal</option><option value="contains" <?php selected($f['condition_operator'],'contains'); ?>>contains</option><option value="answered" <?php selected($f['condition_operator'],'answered'); ?>>is answered</option></select></label>
            <label>Condition value<input type="text" name="scfs_fields[<?php echo esc_attr($i); ?>][condition_value]" value="<?php echo esc_attr($f['condition_value']); ?>"></label>
            <label class="scfs-check"><input type="checkbox" name="scfs_fields[<?php echo esc_attr($i); ?>][randomize_options]" value="1" <?php checked(!empty($f['randomize_options'])); ?>> Randomize options</label>
          </div>
        </section>
        <?php
    }

    public function settings_box($post) {
        $mode=get_post_meta($post->ID,'_scfs_form_mode',true) ?: 'form';
        $confirmation=get_post_meta($post->ID,'_scfs_form_confirmation',true) ?: 'Thank you. Your response has been received.';
        $anonymous=get_post_meta($post->ID,'_scfs_form_anonymous',true);
        $open=get_post_meta($post->ID,'_scfs_form_open',true); if($open==='')$open='1';
        echo '<p><label for="scfs_form_mode"><strong>Instrument type</strong></label><select class="widefat" id="scfs_form_mode" name="scfs_form_mode"><option value="form"'.selected($mode,'form',false).'>Form</option><option value="survey"'.selected($mode,'survey',false).'>Survey</option></select></p>';
        echo '<p><label><input type="checkbox" name="scfs_form_open" value="1" '.checked($open,'1',false).'> Accept responses</label></p>';
        echo '<p><label><input type="checkbox" name="scfs_form_anonymous" value="1" '.checked($anonymous,'1',false).'> Anonymous response mode</label></p>';
        echo '<p><label for="scfs_form_confirmation"><strong>Confirmation message</strong></label><textarea class="widefat" id="scfs_form_confirmation" name="scfs_form_confirmation" rows="4">'.esc_textarea($confirmation).'</textarea></p>';
        $advanced=get_post_meta($post->ID,'_scfs_form_advanced',true); if(!is_array($advanced))$advanced=array();
        $advanced=wp_parse_args($advanced,array('multi_page'=>false,'randomize_questions'=>false,'save_resume'=>false,'quota'=>0,'close_message'=>'This survey has reached its response limit.','version_lock'=>true));
        echo '<hr><p><strong>Advanced survey behavior</strong></p>';
        echo '<p><label><input type="checkbox" name="scfs_advanced[multi_page]" value="1" '.checked(!empty($advanced['multi_page']),true,false).'> Multi-step pages</label></p>';
        echo '<p><label><input type="checkbox" name="scfs_advanced[randomize_questions]" value="1" '.checked(!empty($advanced['randomize_questions']),true,false).'> Randomize questions within pages</label></p>';
        echo '<p><label><input type="checkbox" name="scfs_advanced[save_resume]" value="1" '.checked(!empty($advanced['save_resume']),true,false).'> Browser save and resume</label></p>';
        echo '<p><label>Response quota<input class="widefat" type="number" min="0" name="scfs_advanced[quota]" value="'.esc_attr($advanced['quota']).'"></label></p>';
        echo '<p><label>Quota message<textarea class="widefat" name="scfs_advanced[close_message]" rows="3">'.esc_textarea($advanced['close_message']).'</textarea></label></p>';
        echo '<p><label><input type="checkbox" name="scfs_advanced[version_lock]" value="1" '.checked(!empty($advanced['version_lock']),true,false).'> Lock schema version for responses</label></p>';
        echo '<p><strong>Shortcode</strong><br><code>[sc_feedback_form id="'.esc_html($post->post_name ?: 'publish-first').'" ]</code></p>';
    }

    public function save_form($post_id,$post) {
        if (!isset($_POST['scfs_form_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_form_nonce'])),'scfs_save_form') || !current_user_can('edit_post',$post_id) || wp_is_post_revision($post_id)) return;
        $raw=isset($_POST['scfs_fields']) && is_array($_POST['scfs_fields']) ? wp_unslash($_POST['scfs_fields']) : array();
        $fields=array(); $keys=array();
        foreach($raw as $row){
            $label=sanitize_text_field($row['label']??''); if($label==='')continue;
            $key=sanitize_key($row['key']??''); if($key==='')$key=sanitize_key($label); if(isset($keys[$key]))$key.='_'.(count($keys)+1); $keys[$key]=true;
            $type=sanitize_key($row['type']??'text'); if(!isset($this->field_types()[$type]))$type='text';
            $opts=array_values(array_filter(array_map('sanitize_text_field',preg_split('/\r\n|\r|\n/',(string)($row['options']??'')))));
            $fields[]=array('key'=>$key,'label'=>$label,'type'=>$type,'required'=>!empty($row['required']),'help'=>sanitize_textarea_field($row['help']??''),'placeholder'=>sanitize_text_field($row['placeholder']??''),'options'=>$opts,'page'=>max(1,absint($row['page']??1)),'condition_field'=>sanitize_key($row['condition_field']??''),'condition_operator'=>in_array(($row['condition_operator']??'equals'),array('equals','not_equals','contains','answered'),true)?sanitize_key($row['condition_operator']):'equals','condition_value'=>sanitize_text_field($row['condition_value']??''),'randomize_options'=>!empty($row['randomize_options']));
        }
        update_post_meta($post_id,'_scfs_form_schema',array('schema_version'=>self::SCHEMA_VERSION,'fields'=>$fields));
        update_post_meta($post_id,'_scfs_form_mode',in_array($_POST['scfs_form_mode']??'',array('form','survey'),true)?sanitize_key($_POST['scfs_form_mode']):'form');
        update_post_meta($post_id,'_scfs_form_open',!empty($_POST['scfs_form_open'])?'1':'0');
        update_post_meta($post_id,'_scfs_form_anonymous',!empty($_POST['scfs_form_anonymous'])?'1':'0');
        update_post_meta($post_id,'_scfs_form_confirmation',sanitize_textarea_field(wp_unslash($_POST['scfs_form_confirmation']??'')));
        $a=isset($_POST['scfs_advanced'])&&is_array($_POST['scfs_advanced'])?wp_unslash($_POST['scfs_advanced']):array();
        update_post_meta($post_id,'_scfs_form_advanced',array('multi_page'=>!empty($a['multi_page']),'randomize_questions'=>!empty($a['randomize_questions']),'save_resume'=>!empty($a['save_resume']),'quota'=>absint($a['quota']??0),'close_message'=>sanitize_textarea_field($a['close_message']??''),'version_lock'=>!empty($a['version_lock'])));
        $rev=max(1,absint(get_post_meta($post_id,'_scfs_schema_revision',true))); update_post_meta($post_id,'_scfs_schema_revision',$rev+1);
    }

    private function resolve_form($id) {
        if (is_numeric($id)) return get_post(absint($id));
        $posts=get_posts(array('post_type'=>self::FORM_TYPE,'name'=>sanitize_title($id),'post_status'=>'publish','numberposts'=>1));
        return $posts ? $posts[0] : null;
    }

    public function render_shortcode($atts) {
        $atts=shortcode_atts(array('id'=>'','class'=>''),$atts,self::SHORTCODE); $form=$this->resolve_form($atts['id']);
        if(!$form || $form->post_type!==self::FORM_TYPE) return '<p class="scfs-form-notice">Form not found.</p>';
        wp_enqueue_style('scfs-forms'); wp_enqueue_script('scfs-forms');
        $schema=get_post_meta($form->ID,'_scfs_form_schema',true); $fields=$schema['fields']??array(); $advanced=get_post_meta($form->ID,'_scfs_form_advanced',true); if(!is_array($advanced))$advanced=array(); $advanced=wp_parse_args($advanced,array('multi_page'=>false,'randomize_questions'=>false,'save_resume'=>false,'quota'=>0,'close_message'=>'This survey has reached its response limit.')); if(!empty($advanced['quota']) && $this->response_count($form->ID)>=(int)$advanced['quota']) return '<p class="scfs-form-notice">'.esc_html($advanced['close_message']).'</p>';
        if(get_post_meta($form->ID,'_scfs_form_open',true)!=='1') return '<p class="scfs-form-notice">This form is not currently accepting responses.</p>';
        $status=isset($_GET['scfs_form_status'])?sanitize_key(wp_unslash($_GET['scfs_form_status'])):'';
        ob_start(); ?>
        <section class="scfs-dynamic-form <?php echo esc_attr($atts['class']); ?>" aria-labelledby="scfs-form-title-<?php echo esc_attr($form->ID); ?>">
          <h2 id="scfs-form-title-<?php echo esc_attr($form->ID); ?>"><?php echo esc_html($form->post_title); ?></h2>
          <?php if($form->post_content): ?><div class="scfs-form-intro"><?php echo wp_kses_post(wpautop($form->post_content)); ?></div><?php endif; ?>
          <?php if($status==='success'): ?><div class="scfs-form-message success" role="status"><?php echo esc_html(get_post_meta($form->ID,'_scfs_form_confirmation',true)); ?></div><?php endif; ?>
          <?php if($status==='error'): ?><div class="scfs-form-message error" role="alert">Please review the required fields and submit again.</div><?php endif; ?>
          <form method="post" class="scfs-form" novalidate data-form-id="<?php echo esc_attr($form->ID); ?>" data-multi-page="<?php echo !empty($advanced['multi_page'])?'1':'0'; ?>" data-save-resume="<?php echo !empty($advanced['save_resume'])?'1':'0'; ?>" data-randomize="<?php echo !empty($advanced['randomize_questions'])?'1':'0'; ?>">
            <input type="hidden" name="scfs_form_action" value="submit"><input type="hidden" name="scfs_form_id" value="<?php echo esc_attr($form->ID); ?>">
            <?php wp_nonce_field('scfs_submit_form_'.$form->ID,'scfs_form_submit_nonce'); ?>
            <div class="scfs-hp" aria-hidden="true"><label>Leave this field empty<input type="text" name="scfs_website" tabindex="-1" autocomplete="off"></label></div>
            <div class="scfs-progress" aria-live="polite"></div><?php foreach($fields as $field) $this->render_field($field); ?><div class="scfs-page-actions"><button type="button" class="scfs-prev">Previous</button><button type="button" class="scfs-next">Next</button></div>
            <button type="submit" class="scfs-form-submit">Submit response</button>
          </form>
        </section><?php return ob_get_clean();
    }

    private function render_field($f) {
        $key=$f['key']; $id='scfs-field-'.$key.'-'.wp_rand(100,999); $required=!empty($f['required']); $req=$required?' required aria-required="true"':''; $help=$f['help']??''; $described=$help?' aria-describedby="'.$id.'-help"':'';
        if(!empty($f['randomize_options'])&&!empty($f['options']))shuffle($f['options']); echo '<div class="scfs-form-field scfs-type-'.esc_attr($f['type']).'" data-page="'.esc_attr(max(1,absint($f['page']??1))).'" data-condition-field="'.esc_attr($f['condition_field']??'').'" data-condition-operator="'.esc_attr($f['condition_operator']??'equals').'" data-condition-value="'.esc_attr($f['condition_value']??'').'"><label for="'.esc_attr($id).'">'.esc_html($f['label']).($required?' <span aria-hidden="true">*</span>':'').'</label>';
        if($help)echo '<p class="scfs-field-help" id="'.esc_attr($id).'-help">'.esc_html($help).'</p>';
        $name='scfs_response['.$key.']'; $ph=!empty($f['placeholder'])?' placeholder="'.esc_attr($f['placeholder']).'"':'';
        switch($f['type']){
            case 'textarea': echo '<textarea id="'.esc_attr($id).'" name="'.esc_attr($name).'" rows="5"'.$req.$described.$ph.'></textarea>'; break;
            case 'select': echo '<select id="'.esc_attr($id).'" name="'.esc_attr($name).'"'.$req.$described.'><option value="">Select an option</option>'; foreach($f['options'] as $o)echo '<option value="'.esc_attr($o).'">'.esc_html($o).'</option>'; echo '</select>'; break;
            case 'radio': case 'likert': echo '<fieldset><legend class="screen-reader-text">'.esc_html($f['label']).'</legend>'; foreach($f['options'] as $n=>$o)echo '<label class="scfs-choice"><input type="radio" name="'.esc_attr($name).'" value="'.esc_attr($o).'"'.($required&&$n===0?' required':'').'> '.esc_html($o).'</label>'; echo '</fieldset>'; break;
            case 'checkbox': echo '<fieldset><legend class="screen-reader-text">'.esc_html($f['label']).'</legend>'; foreach($f['options'] as $o)echo '<label class="scfs-choice"><input type="checkbox" name="'.esc_attr($name).'[]" value="'.esc_attr($o).'"> '.esc_html($o).'</label>'; echo '</fieldset>'; break;
            case 'rating': $opts=$f['options']?:array('1','2','3','4','5'); echo '<fieldset class="scfs-rating"><legend class="screen-reader-text">'.esc_html($f['label']).'</legend>'; foreach($opts as $n=>$o)echo '<label><input type="radio" name="'.esc_attr($name).'" value="'.esc_attr($o).'"'.($required&&$n===0?' required':'').'><span>'.esc_html($o).'</span></label>'; echo '</fieldset>'; break;
            case 'consent': echo '<label class="scfs-consent"><input id="'.esc_attr($id).'" type="checkbox" name="'.esc_attr($name).'" value="1"'.$req.'> '.esc_html($f['placeholder']?:'I agree.').'</label>'; break;
            case 'hidden': echo '<input type="hidden" name="'.esc_attr($name).'" value="'.esc_attr($f['placeholder']).'">'; break;
            default: $type=in_array($f['type'],array('email','number','url','date'),true)?$f['type']:'text'; echo '<input id="'.esc_attr($id).'" type="'.esc_attr($type).'" name="'.esc_attr($name).'"'.$req.$described.$ph.'>'; break;
        } echo '</div>';
    }

    public function handle_submission() {
        if(($_SERVER['REQUEST_METHOD']??'')!=='POST' || ($_POST['scfs_form_action']??'')!=='submit')return;
        $form_id=absint($_POST['scfs_form_id']??0); $form=get_post($form_id); if(!$form||$form->post_type!==self::FORM_TYPE)return;
        $redirect=wp_get_referer()?:home_url('/');
        if(!isset($_POST['scfs_form_submit_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_form_submit_nonce'])),'scfs_submit_form_'.$form_id)||!empty($_POST['scfs_website'])||get_post_meta($form_id,'_scfs_form_open',true)!=='1'){wp_safe_redirect(add_query_arg('scfs_form_status','error',$redirect));exit;}
        $advanced=get_post_meta($form_id,'_scfs_form_advanced',true); if(is_array($advanced)&&!empty($advanced['quota'])&&$this->response_count($form_id)>=(int)$advanced['quota']){wp_safe_redirect(add_query_arg('scfs_form_status','closed',$redirect));exit;} $schema=get_post_meta($form_id,'_scfs_form_schema',true); $input=isset($_POST['scfs_response'])&&is_array($_POST['scfs_response'])?wp_unslash($_POST['scfs_response']):array(); $answers=array(); $errors=array();
        foreach(($schema['fields']??array()) as $f){if(!$this->condition_met($f,$input))continue;$raw=$input[$f['key']]??''; $value=$this->sanitize_answer($raw,$f); if(!empty($f['required'])&&($value===''||$value===array()))$errors[]=$f['key']; $answers[$f['key']]=$value;}
        if($errors){wp_safe_redirect(add_query_arg('scfs_form_status','error',$redirect));exit;}
        $uuid=wp_generate_uuid4(); $response_id=wp_insert_post(array('post_type'=>self::RESPONSE_TYPE,'post_status'=>'private','post_title'=>$form->post_title.' response '.substr($uuid,0,8)));
        if(is_wp_error($response_id)||!$response_id){wp_safe_redirect(add_query_arg('scfs_form_status','error',$redirect));exit;}
        update_post_meta($response_id,'_scfs_response_uuid',$uuid); update_post_meta($response_id,'_scfs_form_id',$form_id); update_post_meta($response_id,'_scfs_form_schema_version',$schema['schema_version']??self::SCHEMA_VERSION); update_post_meta($response_id,'_scfs_response_answers',$answers); update_post_meta($response_id,'_scfs_response_submitted_at',gmdate('c'));
        do_action('scfs_event',array('event_id'=>wp_generate_uuid4(),'event_type'=>'form.response_submitted','schema_version'=>'1.0','source'=>'feature_suggestions','source_version'=>'2.5.0','occurred_at'=>gmdate('c'),'data'=>array('response_uuid'=>$uuid,'form_id'=>$form_id,'form_slug'=>$form->post_name,'instrument_type'=>get_post_meta($form_id,'_scfs_form_mode',true),'field_count'=>count($answers),'schema_revision'=>absint(get_post_meta($form_id,'_scfs_schema_revision',true)))));
        do_action('sc_platform_event',array('event_type'=>'form.response_submitted','source'=>'feature_suggestions','form_id'=>$form_id,'response_uuid'=>$uuid));
        wp_safe_redirect(add_query_arg('scfs_form_status','success',$redirect));exit;
    }

    private function condition_met($field,$input) {
        $key=$field['condition_field']??''; if($key==='')return true;
        $raw=$input[$key]??''; $values=is_array($raw)?array_map('sanitize_text_field',$raw):array(sanitize_text_field($raw));
        $values=array_values(array_filter($values,function($v){return $v!=='';})); $target=(string)($field['condition_value']??'');
        switch($field['condition_operator']??'equals'){
            case 'answered': return !empty($values);
            case 'not_equals': return !in_array($target,$values,true);
            case 'contains': foreach($values as$v){if(strpos($v,$target)!==false)return true;} return false;
            default: return in_array($target,$values,true);
        }
    }

    private function sanitize_answer($raw,$f){ if(is_array($raw))return array_values(array_map('sanitize_text_field',$raw)); switch($f['type']){case'email':return sanitize_email($raw);case'url':return esc_url_raw($raw);case'textarea':return sanitize_textarea_field($raw);case'number':return is_numeric($raw)?(string)(0+$raw):'';case'consent':return $raw==='1'?'1':'';default:return sanitize_text_field($raw);} }

    public function response_box($post){$form_id=absint(get_post_meta($post->ID,'_scfs_form_id',true));$answers=get_post_meta($post->ID,'_scfs_response_answers',true);$form=get_post($form_id);$advanced=get_post_meta($form_id,'_scfs_form_advanced',true); if(is_array($advanced)&&!empty($advanced['quota'])&&$this->response_count($form_id)>=(int)$advanced['quota']){wp_safe_redirect(add_query_arg('scfs_form_status','closed',$redirect));exit;} $schema=get_post_meta($form_id,'_scfs_form_schema',true);$labels=array();foreach(($schema['fields']??array())as$f)$labels[$f['key']]=$f['label'];echo '<p><strong>Form:</strong> '.esc_html($form?$form->post_title:'Unknown').'</p><p><strong>UUID:</strong> <code>'.esc_html(get_post_meta($post->ID,'_scfs_response_uuid',true)).'</code></p><table class="widefat striped"><tbody>';foreach((array)$answers as$k=>$v)echo '<tr><th>'.esc_html($labels[$k]??$k).'</th><td>'.esc_html(is_array($v)?implode(', ',$v):$v).'</td></tr>';echo '</tbody></table>';}

    public function form_columns($cols){$cols['scfs_type']='Type';$cols['scfs_fields']='Fields';$cols['scfs_responses']='Responses';$cols['scfs_shortcode']='Shortcode';return $cols;}
    public function form_column($col,$id){if($col==='scfs_type')echo esc_html(ucfirst(get_post_meta($id,'_scfs_form_mode',true)?:'form'));if($col==='scfs_fields'){$s=get_post_meta($id,'_scfs_form_schema',true);echo esc_html(count($s['fields']??array()));}if($col==='scfs_responses')echo esc_html($this->response_count($id));if($col==='scfs_shortcode')echo '<code>[sc_feedback_form id=&quot;'.esc_html(get_post_field('post_name',$id)).'&quot;]</code>';}
    public function response_columns($cols){return array('cb'=>$cols['cb'],'title'=>'Response','scfs_form'=>'Form','scfs_submitted'=>'Submitted','date'=>'Date');}
    public function response_column($col,$id){if($col==='scfs_form'){echo esc_html(get_the_title(absint(get_post_meta($id,'_scfs_form_id',true))));}if($col==='scfs_submitted')echo esc_html(get_post_meta($id,'_scfs_response_submitted_at',true));}
    private function response_count($form_id){$q=new WP_Query(array('post_type'=>self::RESPONSE_TYPE,'post_status'=>'private','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_scfs_form_id','meta_value'=>$form_id));return (int)$q->found_posts;}

    public function admin_menu(){add_submenu_page('edit.php?post_type=sc_feature_suggest','Form Response Export','Form Response Export','edit_posts','scfs-form-export',array($this,'export_page'));}
    public function export_page(){if(!current_user_can('edit_posts'))return;$forms=get_posts(array('post_type'=>self::FORM_TYPE,'post_status'=>array('publish','draft','private'),'numberposts'=>-1));echo '<div class="wrap"><h1>Export Form and Survey Responses</h1><p>Exports include response UUIDs and answer values. Anonymous mode affects collection behavior; review exports before sharing.</p><form method="get" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="scfs_export_form_responses">';wp_nonce_field('scfs_export_form_responses');echo '<select name="form_id" required><option value="">Select a form or survey</option>';foreach($forms as$f)echo '<option value="'.esc_attr($f->ID).'">'.esc_html($f->post_title).'</option>';echo '</select> <button class="button button-primary">Download CSV</button></form></div>';}
    public function export_responses(){if(!current_user_can('edit_posts'))wp_die('Not permitted.');check_admin_referer('scfs_export_form_responses');$form_id=absint($_GET['form_id']??0);$form=get_post($form_id);if(!$form||$form->post_type!==self::FORM_TYPE)wp_die('Invalid form.');$advanced=get_post_meta($form_id,'_scfs_form_advanced',true); if(is_array($advanced)&&!empty($advanced['quota'])&&$this->response_count($form_id)>=(int)$advanced['quota']){wp_safe_redirect(add_query_arg('scfs_form_status','closed',$redirect));exit;} $schema=get_post_meta($form_id,'_scfs_form_schema',true);$fields=$schema['fields']??array();$q=new WP_Query(array('post_type'=>self::RESPONSE_TYPE,'post_status'=>'private','posts_per_page'=>-1,'meta_key'=>'_scfs_form_id','meta_value'=>$form_id,'orderby'=>'date','order'=>'DESC'));header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename=scfs-'.sanitize_title($form->post_name).'-responses-'.gmdate('Y-m-d').'.csv');$out=fopen('php://output','w');$head=array('response_uuid','submitted_at');foreach($fields as$f)$head[]=$f['key'];fputcsv($out,$head);foreach($q->posts as$r){$a=get_post_meta($r->ID,'_scfs_response_answers',true);$row=array(get_post_meta($r->ID,'_scfs_response_uuid',true),get_post_meta($r->ID,'_scfs_response_submitted_at',true));foreach($fields as$f){$v=$a[$f['key']]??'';$row[]=is_array($v)?implode('|',$v):$v;}fputcsv($out,$row);}fclose($out);exit;}

    public function rest_routes(){register_rest_route('scfs/v1','/forms/(?P<id>[a-zA-Z0-9_-]+)',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_form'),'permission_callback'=>'__return_true'));register_rest_route('scfs/v1','/forms/(?P<id>[a-zA-Z0-9_-]+)/responses',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'rest_submit'),'permission_callback'=>'__return_true'));}
    public function rest_form($request){$form=$this->resolve_form($request['id']);if(!$form||$form->post_status!=='publish')return new WP_Error('scfs_form_not_found','Form not found.',array('status'=>404));return rest_ensure_response(array('id'=>$form->ID,'slug'=>$form->post_name,'title'=>$form->post_title,'description'=>wp_strip_all_tags($form->post_content),'mode'=>get_post_meta($form->ID,'_scfs_form_mode',true),'open'=>get_post_meta($form->ID,'_scfs_form_open',true)==='1','schema'=>get_post_meta($form->ID,'_scfs_form_schema',true)));}
    public function rest_submit($request){$form=$this->resolve_form($request['id']);if(!$form||$form->post_status!=='publish'||get_post_meta($form->ID,'_scfs_form_open',true)!=='1')return new WP_Error('scfs_form_closed','Form is unavailable.',array('status'=>404));$schema=get_post_meta($form->ID,'_scfs_form_schema',true);$input=(array)$request->get_param('answers');$answers=array();$errors=array();foreach(($schema['fields']??array())as$f){if(!$this->condition_met($f,$input))continue;$v=$this->sanitize_answer($input[$f['key']]??'',$f);if(!empty($f['required'])&&($v===''||$v===array()))$errors[]=$f['key'];$answers[$f['key']]=$v;}if($errors)return new WP_Error('scfs_validation_failed','Required fields are missing.',array('status'=>422,'fields'=>$errors));$uuid=wp_generate_uuid4();$rid=wp_insert_post(array('post_type'=>self::RESPONSE_TYPE,'post_status'=>'private','post_title'=>$form->post_title.' response '.substr($uuid,0,8)));update_post_meta($rid,'_scfs_response_uuid',$uuid);update_post_meta($rid,'_scfs_form_id',$form->ID);update_post_meta($rid,'_scfs_form_schema_version',$schema['schema_version']??self::SCHEMA_VERSION);update_post_meta($rid,'_scfs_response_answers',$answers);update_post_meta($rid,'_scfs_response_submitted_at',gmdate('c'));do_action('sc_platform_event',array('event_type'=>'form.response_submitted','source'=>'feature_suggestions','form_id'=>$form->ID,'response_uuid'=>$uuid));return new WP_REST_Response(array('ok'=>true,'response_uuid'=>$uuid,'message'=>get_post_meta($form->ID,'_scfs_form_confirmation',true)),201);}
}
