<?php
if (!defined('ABSPATH')) { exit; }

final class SCFS_Public_Ideas {
    const SHORTCODE = 'sc_public_ideas';
    const VOTE_COOKIE = 'scfs_supported_ideas';
    private static $instance = null;
    public static function instance() { if (null === self::$instance) { self::$instance = new self(); } return self::$instance; }
    private function __construct() {
        add_action('init', array($this,'register_assets'));
        add_shortcode(self::SHORTCODE, array($this,'shortcode'));
        add_action('add_meta_boxes', array($this,'add_meta_box'));
        add_action('save_post_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, array($this,'save'), 20, 2);
        add_action('rest_api_init', array($this,'routes'));
        add_filter('manage_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '_posts_columns', array($this,'columns'));
        add_action('manage_' . Sustainable_Catalyst_Feature_Suggestions::POST_TYPE . '_posts_custom_column', array($this,'column_value'), 10, 2);
    }
    public function register_assets() {
        wp_register_style('scfs-public-ideas', plugins_url('../assets/public-ideas.css', __FILE__), array(), Sustainable_Catalyst_Feature_Suggestions::VERSION);
        wp_register_script('scfs-public-ideas', plugins_url('../assets/public-ideas.js', __FILE__), array(), Sustainable_Catalyst_Feature_Suggestions::VERSION, true);
    }
    public function states() {
        return array('under_review'=>'Under review','planned'=>'Planned','in_progress'=>'In progress','released'=>'Released','not_planned'=>'Not planned');
    }
    public function add_meta_box() {
        add_meta_box('scfs-public-participation', __('Public participation','sustainable-catalyst-feature-suggestions'), array($this,'meta_box'), Sustainable_Catalyst_Feature_Suggestions::POST_TYPE, 'side', 'high');
    }
    public function meta_box($post) {
        wp_nonce_field('scfs_public_participation','scfs_public_participation_nonce');
        $visible = get_post_meta($post->ID,'_scfs_public_visibility',true);
        $state = get_post_meta($post->ID,'_scfs_public_state',true) ?: 'under_review';
        $response = get_post_meta($post->ID,'_scfs_official_response',true);
        $release = get_post_meta($post->ID,'_scfs_release_url',true);
        $canonical = absint(get_post_meta($post->ID,'_scfs_canonical_idea_id',true));
        echo '<p><label><input type="checkbox" name="scfs_public_visibility" value="1" '.checked($visible,'1',false).'> '.esc_html__('Publish in public ideas directory','sustainable-catalyst-feature-suggestions').'</label></p>';
        echo '<p><label>'.esc_html__('Public state','sustainable-catalyst-feature-suggestions').'<br><select name="scfs_public_state">';
        foreach($this->states() as $value=>$label){echo '<option value="'.esc_attr($value).'" '.selected($state,$value,false).'>'.esc_html($label).'</option>';}
        echo '</select></label></p>';
        echo '<p><label>'.esc_html__('Official response','sustainable-catalyst-feature-suggestions').'<br><textarea name="scfs_official_response" rows="5" style="width:100%">'.esc_textarea($response).'</textarea></label></p>';
        echo '<p><label>'.esc_html__('Release or roadmap URL','sustainable-catalyst-feature-suggestions').'<br><input type="url" name="scfs_release_url" value="'.esc_attr($release).'" style="width:100%"></label></p>';
        echo '<p><label>'.esc_html__('Canonical idea ID (merge duplicate)','sustainable-catalyst-feature-suggestions').'<br><input type="number" min="0" name="scfs_canonical_idea_id" value="'.esc_attr($canonical).'" style="width:100%"></label></p>';
        echo '<p><strong>'.esc_html__('Support votes:','sustainable-catalyst-feature-suggestions').'</strong> '.esc_html(absint(get_post_meta($post->ID,'_scfs_support_votes',true))).'</p>';
        echo '<p class="description">'.esc_html__('Votes are advisory. Public visibility, state changes, merging, and responses always require human review.','sustainable-catalyst-feature-suggestions').'</p>';
    }
    public function save($post_id,$post) {
        if (!isset($_POST['scfs_public_participation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scfs_public_participation_nonce'])),'scfs_public_participation')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post',$post_id)) return;
        update_post_meta($post_id,'_scfs_public_visibility',isset($_POST['scfs_public_visibility'])?'1':'0');
        $state = sanitize_key($_POST['scfs_public_state'] ?? 'under_review');
        if (!isset($this->states()[$state])) $state='under_review';
        update_post_meta($post_id,'_scfs_public_state',$state);
        update_post_meta($post_id,'_scfs_official_response',sanitize_textarea_field(wp_unslash($_POST['scfs_official_response'] ?? '')));
        update_post_meta($post_id,'_scfs_release_url',esc_url_raw(wp_unslash($_POST['scfs_release_url'] ?? '')));
        $canonical=absint($_POST['scfs_canonical_idea_id'] ?? 0); if($canonical===$post_id)$canonical=0;
        update_post_meta($post_id,'_scfs_canonical_idea_id',$canonical);
    }
    private function public_query($args=array()) {
        return new WP_Query(array_merge(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>array('publish','private','pending'),'posts_per_page'=>20,'meta_query'=>array(array('key'=>'_scfs_public_visibility','value'=>'1')),'orderby'=>'date','order'=>'DESC'),$args));
    }
    public function shortcode($atts) {
        $atts=shortcode_atts(array('limit'=>'20','state'=>'','title'=>'Public Ideas'),$atts,self::SHORTCODE);
        $meta=array(array('key'=>'_scfs_public_visibility','value'=>'1'));
        $state=sanitize_key($atts['state']); if($state && isset($this->states()[$state]))$meta[]=array('key'=>'_scfs_public_state','value'=>$state);
        $q=$this->public_query(array('posts_per_page'=>min(100,max(1,absint($atts['limit']))),'meta_query'=>$meta));
        wp_enqueue_style('scfs-public-ideas'); wp_enqueue_script('scfs-public-ideas');
        wp_localize_script('scfs-public-ideas','SCFSPublicIdeas',array('endpoint'=>esc_url_raw(rest_url('scfs/v1/public-ideas')),'nonce'=>wp_create_nonce('wp_rest'),'supported'=>array_values(array_filter(array_map('absint',explode(',',sanitize_text_field($_COOKIE[self::VOTE_COOKIE] ?? '')))))));
        ob_start(); echo '<section class="scfs-public-ideas"><header><p class="scfs-public-kicker">'.esc_html__('Participatory roadmap','sustainable-catalyst-feature-suggestions').'</p><h2>'.esc_html($atts['title']).'</h2><p>'.esc_html__('Review approved ideas, support priorities, and follow official roadmap decisions. Support counts inform review but do not determine implementation.','sustainable-catalyst-feature-suggestions').'</p></header><div class="scfs-public-grid">';
        if(!$q->have_posts()) echo '<p>'.esc_html__('No public ideas are available yet.','sustainable-catalyst-feature-suggestions').'</p>';
        while($q->have_posts()){ $q->the_post(); $id=get_the_ID(); $canonical=absint(get_post_meta($id,'_scfs_canonical_idea_id',true)); if($canonical){continue;} $state=get_post_meta($id,'_scfs_public_state',true)?:'under_review'; $votes=absint(get_post_meta($id,'_scfs_support_votes',true)); $response=get_post_meta($id,'_scfs_official_response',true); $release=get_post_meta($id,'_scfs_release_url',true); $category=get_post_meta($id,'_scfs_category',true);
            echo '<article class="scfs-public-card" data-idea-id="'.esc_attr($id).'"><div class="scfs-public-meta"><span>'.esc_html($category).'</span><span class="scfs-state scfs-state-'.esc_attr($state).'">'.esc_html($this->states()[$state]??$state).'</span></div><h3>'.esc_html(get_the_title()).'</h3><div class="scfs-public-summary">'.wp_kses_post(wpautop(wp_trim_words(get_the_content(),55))).'</div>';
            if($response) echo '<div class="scfs-official-response"><strong>'.esc_html__('Official response','sustainable-catalyst-feature-suggestions').'</strong><p>'.esc_html($response).'</p></div>';
            if($release) echo '<p><a href="'.esc_url($release).'" target="_blank" rel="noopener">'.esc_html__('View roadmap or release','sustainable-catalyst-feature-suggestions').'</a></p>';
            echo '<div class="scfs-vote-row"><button type="button" class="scfs-support-button" aria-pressed="false">'.esc_html__('Support this idea','sustainable-catalyst-feature-suggestions').'</button><span class="scfs-support-count" aria-live="polite">'.esc_html($votes).' '.esc_html(_n('support','supports',$votes,'sustainable-catalyst-feature-suggestions')).'</span></div></article>';
        } wp_reset_postdata(); echo '</div></section>'; return ob_get_clean();
    }
    public function routes() {
        register_rest_route('scfs/v1','/public-ideas',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_list'),'permission_callback'=>'__return_true'));
        register_rest_route('scfs/v1','/public-ideas/(?P<id>\d+)/support',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'rest_support'),'permission_callback'=>'__return_true'));
    }
    public function rest_list($request) {
        $q=$this->public_query(array('posts_per_page'=>min(100,max(1,absint($request->get_param('limit')?:20))))); $items=array();
        foreach($q->posts as $p){if(absint(get_post_meta($p->ID,'_scfs_canonical_idea_id',true)))continue;$items[]=array('id'=>$p->ID,'title'=>$p->post_title,'summary'=>wp_trim_words(wp_strip_all_tags($p->post_content),55),'category'=>get_post_meta($p->ID,'_scfs_category',true),'state'=>get_post_meta($p->ID,'_scfs_public_state',true)?:'under_review','supports'=>absint(get_post_meta($p->ID,'_scfs_support_votes',true)),'official_response'=>get_post_meta($p->ID,'_scfs_official_response',true),'release_url'=>get_post_meta($p->ID,'_scfs_release_url',true));}
        return rest_ensure_response(array('items'=>$items,'participation_note'=>'Support is advisory and does not automatically determine roadmap priority.'));
    }
    public function rest_support($request) {
        $id=absint($request['id']); $post=get_post($id);
        if(!$post || $post->post_type!==Sustainable_Catalyst_Feature_Suggestions::POST_TYPE || '1'!==get_post_meta($id,'_scfs_public_visibility',true) || absint(get_post_meta($id,'_scfs_canonical_idea_id',true))) return new WP_Error('scfs_idea_not_found',__('Public idea not found.','sustainable-catalyst-feature-suggestions'),array('status'=>404));
        $supported=array_filter(array_map('absint',explode(',',sanitize_text_field($_COOKIE[self::VOTE_COOKIE] ?? ''))));
        if(in_array($id,$supported,true)) return new WP_Error('scfs_already_supported',__('This browser has already supported the idea.','sustainable-catalyst-feature-suggestions'),array('status'=>409));
        $ip=(string)($_SERVER['REMOTE_ADDR']??''); $fingerprint=hash_hmac('sha256',$id.'|'.$ip.'|'.(string)($_SERVER['HTTP_USER_AGENT']??''),wp_salt('auth'));
        if(get_transient('scfs_vote_'.$fingerprint)) return new WP_Error('scfs_already_supported',__('This idea has already been supported from this session.','sustainable-catalyst-feature-suggestions'),array('status'=>409));
        $votes=absint(get_post_meta($id,'_scfs_support_votes',true))+1; update_post_meta($id,'_scfs_support_votes',$votes); set_transient('scfs_vote_'.$fingerprint,1,DAY_IN_SECONDS*365);
        $supported[]=$id; setcookie(self::VOTE_COOKIE,implode(',',array_unique($supported)),time()+YEAR_IN_SECONDS,COOKIEPATH?:'/',COOKIE_DOMAIN,is_ssl(),true);
        $event=array('schema_version'=>Sustainable_Catalyst_Feature_Suggestions::EVENT_SCHEMA_VERSION,'event_type'=>'idea.supported','source'=>'feature_suggestions','submission_uuid'=>get_post_meta($id,'_scfs_submission_uuid',true),'idea_id'=>$id,'support_count'=>$votes,'timestamp'=>gmdate('c'));
        do_action('scfs_event',$event); do_action('sc_platform_event',$event); do_action('scfs_dispatch_event',$event);
        return rest_ensure_response(array('ok'=>true,'supports'=>$votes,'message'=>__('Support recorded. It will be considered alongside evidence, feasibility, and strategic value.','sustainable-catalyst-feature-suggestions')));
    }
    public function columns($columns){$columns['scfs_public']='Public idea';return $columns;}
    public function column_value($column,$id){if('scfs_public'!==$column)return;if('1'!==get_post_meta($id,'_scfs_public_visibility',true)){echo '&mdash;';return;}echo esc_html($this->states()[get_post_meta($id,'_scfs_public_state',true)?:'under_review']??'Under review').'<br>'.esc_html(absint(get_post_meta($id,'_scfs_support_votes',true))).' '.esc_html__('supports','sustainable-catalyst-feature-suggestions');}
}
