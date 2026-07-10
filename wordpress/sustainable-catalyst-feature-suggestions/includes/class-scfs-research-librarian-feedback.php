<?php
/**
 * Research Librarian contextual feedback bridge.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) { exit; }

final class SCFS_Research_Librarian_Feedback {
    const SHORTCODE = 'sc_librarian_feedback';
    const SCHEMA_VERSION = '1.0';
    const RECEIPT_TTL = 15552000; // 180 days.
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) { self::$instance = new self(); }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode(self::SHORTCODE, array($this, 'shortcode'));
        add_action('rest_api_init', array($this, 'rest_routes'));
        add_action('admin_post_nopriv_scfs_librarian_feedback', array($this, 'handle_form'));
        add_action('admin_post_scfs_librarian_feedback', array($this, 'handle_form'));
        add_action('scfs_research_librarian_feedback', array($this, 'receive_action'), 10, 1);
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_filter('manage_sc_feature_suggest_posts_columns', array($this, 'column'));
        add_action('manage_sc_feature_suggest_posts_custom_column', array($this, 'column_value'), 10, 2);
        add_action('wp_enqueue_scripts', array($this, 'assets'));
    }

    public function assets() {
        wp_register_style('scfs-librarian-feedback', plugins_url('../assets/librarian-feedback.css', __FILE__), array(), Sustainable_Catalyst_Feature_Suggestions::VERSION);
    }

    private function feedback_types() {
        return array(
            'route_helpful' => __('Route was helpful', 'sustainable-catalyst-feature-suggestions'),
            'route_incorrect' => __('Route was incorrect', 'sustainable-catalyst-feature-suggestions'),
            'source_relevant' => __('Source card was relevant', 'sustainable-catalyst-feature-suggestions'),
            'source_irrelevant' => __('Source card was not relevant', 'sustainable-catalyst-feature-suggestions'),
            'answer_grounding' => __('Answer or grounding issue', 'sustainable-catalyst-feature-suggestions'),
            'missing_source' => __('Missing source', 'sustainable-catalyst-feature-suggestions'),
            'missing_topic' => __('Missing topic or research path', 'sustainable-catalyst-feature-suggestions'),
            'missing_tool' => __('Missing calculator or tool', 'sustainable-catalyst-feature-suggestions'),
            'other' => __('Other Research Librarian feedback', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    private function safe_context($input) {
        $allowed = array('route_id','route_label','query_topic','source_id','source_title','source_url','page_url','article_map','destination','session_ref','answer_ref','prompt_ref');
        $context = array();
        foreach ($allowed as $key) {
            if (!isset($input[$key])) { continue; }
            $value = is_scalar($input[$key]) ? (string) $input[$key] : '';
            $context[$key] = in_array($key, array('source_url','page_url'), true) ? esc_url_raw($value) : sanitize_text_field($value);
        }
        return array_filter($context, static function($value){ return '' !== $value; });
    }

    private function normalize($input) {
        $types = $this->feedback_types();
        $type = sanitize_key(isset($input['feedback_type']) ? $input['feedback_type'] : 'other');
        if (!isset($types[$type])) { $type = 'other'; }
        $rating = isset($input['rating']) ? absint($input['rating']) : 0;
        if ($rating < 1 || $rating > 5) { $rating = 0; }
        return array(
            'feedback_type' => $type,
            'rating' => $rating,
            'details' => sanitize_textarea_field(isset($input['details']) ? $input['details'] : ''),
            'expected_result' => sanitize_textarea_field(isset($input['expected_result']) ? $input['expected_result'] : ''),
            'consent' => !empty($input['consent']),
            'context' => $this->safe_context(isset($input['context']) && is_array($input['context']) ? $input['context'] : $input),
        );
    }

    private function validate($data) {
        if (!$data['consent']) { return new WP_Error('scfs_consent_required', __('Consent is required.', 'sustainable-catalyst-feature-suggestions'), array('status'=>422)); }
        if (in_array($data['feedback_type'], array('route_incorrect','source_irrelevant','answer_grounding','missing_source','missing_topic','missing_tool','other'), true) && '' === $data['details']) {
            return new WP_Error('scfs_details_required', __('Please provide a short explanation.', 'sustainable-catalyst-feature-suggestions'), array('status'=>422));
        }
        return true;
    }

    private function fingerprint($data) {
        return hash('sha256', wp_json_encode(array($data['feedback_type'], $data['details'], $data['context']['route_id'] ?? '', $data['context']['source_id'] ?? '', $data['context']['query_topic'] ?? '')));
    }

    private function create_record($data) {
        $validation = $this->validate($data);
        if (is_wp_error($validation)) { return $validation; }
        $fingerprint = $this->fingerprint($data);
        $recent = get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>'any','posts_per_page'=>1,'fields'=>'ids','meta_key'=>'_scfs_librarian_fingerprint','meta_value'=>$fingerprint,'date_query'=>array(array('after'=>'24 hours ago'))));
        if ($recent) { return new WP_Error('scfs_duplicate_feedback', __('This feedback appears to have already been received.', 'sustainable-catalyst-feature-suggestions'), array('status'=>409)); }

        $types = $this->feedback_types();
        $topic = $data['context']['query_topic'] ?? ($data['context']['route_label'] ?? 'Research Librarian');
        $title = sprintf('%s: %s', $types[$data['feedback_type']], $topic);
        $post_id = wp_insert_post(array(
            'post_type' => Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,
            'post_status' => 'pending',
            'post_title' => wp_trim_words($title, 16, ''),
            'post_content' => $data['details'],
        ), true);
        if (is_wp_error($post_id)) { return $post_id; }

        $uuid = wp_generate_uuid4();
        $receipt = wp_generate_password(32, false, false);
        $category = 'Research Librarian Feedback';
        $meta = array(
            '_scfs_submission_uuid'=>$uuid,
            '_scfs_source'=>'research_librarian',
            '_scfs_schema_version'=>self::SCHEMA_VERSION,
            '_scfs_category'=>$category,
            '_scfs_priority'=>'Medium',
            '_scfs_review_status'=>'new',
            '_scfs_problem'=>$data['details'],
            '_scfs_suggestion'=>$data['expected_result'],
            '_scfs_consent'=>'1',
            '_scfs_librarian_feedback_type'=>$data['feedback_type'],
            '_scfs_librarian_rating'=>$data['rating'],
            '_scfs_librarian_context'=>$data['context'],
            '_scfs_librarian_fingerprint'=>$fingerprint,
            '_scfs_librarian_receipt_hash'=>wp_hash_password($receipt),
            '_scfs_librarian_receipt_created'=>time(),
        );
        foreach ($meta as $key=>$value) { update_post_meta($post_id, $key, $value); }

        $event = array(
            'schema_version'=>self::SCHEMA_VERSION,
            'event_type'=>'librarian.feedback_submitted',
            'source'=>'feature_suggestions',
            'submission_uuid'=>$uuid,
            'feedback_type'=>$data['feedback_type'],
            'rating'=>$data['rating'],
            'route_id'=>$data['context']['route_id'] ?? '',
            'source_id'=>$data['context']['source_id'] ?? '',
            'query_topic'=>$data['context']['query_topic'] ?? '',
            'timestamp'=>gmdate('c'),
        );
        do_action('scfs_event', $event);
        do_action('sc_platform_event', $event);
        do_action('scfs_dispatch_event', $event);
        do_action('scfs_librarian_feedback_created', $post_id, $event);
        return array('post_id'=>$post_id,'submission_uuid'=>$uuid,'receipt_token'=>$receipt,'status'=>'new');
    }

    public function receive_action($payload) {
        if (is_array($payload)) { $this->create_record($this->normalize($payload)); }
    }

    public function rest_routes() {
        register_rest_route('scfs/v1','/librarian/feedback/schema',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_schema'),'permission_callback'=>'__return_true'));
        register_rest_route('scfs/v1','/librarian/feedback',array('methods'=>WP_REST_Server::CREATABLE,'callback'=>array($this,'rest_submit'),'permission_callback'=>array($this,'public_permission')));
        register_rest_route('scfs/v1','/librarian/feedback/(?P<uuid>[a-f0-9-]{36})/status',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_status'),'permission_callback'=>'__return_true'));
        register_rest_route('scfs/v1','/librarian/handoff',array('methods'=>WP_REST_Server::READABLE,'callback'=>array($this,'rest_handoff'),'permission_callback'=>'__return_true'));
    }

    public function public_permission($request) {
        $settings = get_option(Sustainable_Catalyst_Feature_Suggestions::OPTION_KEY, array());
        if (!empty($settings['rest_require_api_key'])) {
            $expected = (string)($settings['rest_api_key'] ?? '');
            $provided = (string)$request->get_header('x-scfs-api-key');
            if (!$expected || !$provided || !hash_equals($expected,$provided)) { return new WP_Error('scfs_invalid_api_key',__('A valid API key is required.','sustainable-catalyst-feature-suggestions'),array('status'=>401)); }
        }
        return true;
    }

    public function rest_schema() {
        return rest_ensure_response(array('schema_version'=>self::SCHEMA_VERSION,'feedback_types'=>$this->feedback_types(),'ratings'=>array('minimum'=>1,'maximum'=>5),'context_fields'=>array('route_id','route_label','query_topic','source_id','source_title','source_url','page_url','article_map','destination','session_ref','answer_ref','prompt_ref'),'privacy'=>'Do not send raw conversations, names, email addresses, IP addresses, or secrets.'));
    }

    public function rest_submit($request) {
        $payload = $request->get_json_params();
        if (!is_array($payload)) { $payload = $request->get_params(); }
        $result = $this->create_record($this->normalize($payload));
        if (is_wp_error($result)) { return $result; }
        return new WP_REST_Response(array('ok'=>true)+$result, 201);
    }

    public function rest_status($request) {
        $uuid = sanitize_text_field($request['uuid']);
        $token = sanitize_text_field($request->get_param('receipt_token'));
        $posts = get_posts(array('post_type'=>Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'post_status'=>'any','posts_per_page'=>1,'meta_key'=>'_scfs_submission_uuid','meta_value'=>$uuid));
        if (!$posts) { return new WP_Error('scfs_not_found',__('Feedback record not found.','sustainable-catalyst-feature-suggestions'),array('status'=>404)); }
        $post = $posts[0];
        $hash = get_post_meta($post->ID,'_scfs_librarian_receipt_hash',true);
        $created = absint(get_post_meta($post->ID,'_scfs_librarian_receipt_created',true));
        if (!$token || !$hash || !wp_check_password($token,$hash) || ($created && time()-$created>self::RECEIPT_TTL)) { return new WP_Error('scfs_invalid_receipt',__('A valid feedback receipt is required.','sustainable-catalyst-feature-suggestions'),array('status'=>403)); }
        return rest_ensure_response(array('submission_uuid'=>$uuid,'status'=>get_post_meta($post->ID,'_scfs_review_status',true) ?: 'new','updated_at'=>get_post_modified_time('c',true,$post)));
    }

    public function rest_handoff($request) {
        $args = $this->safe_context($request->get_params());
        $args['feedback_type'] = sanitize_key($request->get_param('feedback_type') ?: 'other');
        return rest_ensure_response(array('shortcode'=>'['.self::SHORTCODE.' '.implode(' ',array_map(static function($k,$v){return sprintf('%s="%s"',$k,esc_attr($v));},array_keys($args),$args)).']','page_url'=>add_query_arg(array_merge(array('scfs_context'=>'librarian'),$args),home_url('/platform/feature-suggestions/')),'context'=>$args));
    }

    public function handle_form() {
        check_admin_referer('scfs_librarian_feedback','scfs_librarian_nonce');
        $result = $this->create_record($this->normalize(wp_unslash($_POST)));
        $redirect = wp_get_referer() ?: home_url('/platform/feature-suggestions/');
        if (is_wp_error($result)) { $redirect = add_query_arg(array('scfs_librarian_error'=>$result->get_error_code()),$redirect); }
        else { $redirect = add_query_arg(array('scfs_librarian_submitted'=>'1','scfs_receipt'=>$result['submission_uuid']),$redirect); }
        wp_safe_redirect($redirect); exit;
    }

    public function shortcode($atts) {
        $atts = shortcode_atts(array('feedback_type'=>'other','route_id'=>'','route_label'=>'','query_topic'=>'','source_id'=>'','source_title'=>'','source_url'=>'','page_url'=>'','article_map'=>'','destination'=>'','session_ref'=>'','answer_ref'=>'','prompt_ref'=>'','title'=>'Research Librarian feedback'),$atts,self::SHORTCODE);
        wp_enqueue_style('scfs-librarian-feedback');
        $types = $this->feedback_types();
        ob_start(); ?>
        <section class="scfs-rl-feedback" data-scfs-product="research-librarian-feedback">
            <h3><?php echo esc_html($atts['title']); ?></h3>
            <p><?php esc_html_e('Report route quality, source relevance, missing research coverage, or a tool you expected to find. Context is limited to the fields shown below; raw conversations are not collected.','sustainable-catalyst-feature-suggestions'); ?></p>
            <?php if (isset($_GET['scfs_librarian_submitted'])): ?><div class="scfs-rl-notice" role="status"><?php esc_html_e('Thank you. Your contextual feedback was submitted for human review.','sustainable-catalyst-feature-suggestions'); ?></div><?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="scfs_librarian_feedback">
                <?php wp_nonce_field('scfs_librarian_feedback','scfs_librarian_nonce'); ?>
                <?php foreach ($this->safe_context($atts) as $key=>$value): ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>"><?php endforeach; ?>
                <label><?php esc_html_e('Feedback type','sustainable-catalyst-feature-suggestions'); ?><select name="feedback_type" required><?php foreach($types as $value=>$label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($atts['feedback_type'],$value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <fieldset><legend><?php esc_html_e('Rating (optional)','sustainable-catalyst-feature-suggestions'); ?></legend><div class="scfs-rl-rating"><?php for($i=1;$i<=5;$i++): ?><label><input type="radio" name="rating" value="<?php echo esc_attr($i); ?>"> <?php echo esc_html($i); ?></label><?php endfor; ?></div></fieldset>
                <label><?php esc_html_e('What happened or what is missing?','sustainable-catalyst-feature-suggestions'); ?><textarea name="details" rows="5"></textarea></label>
                <label><?php esc_html_e('What result did you expect?','sustainable-catalyst-feature-suggestions'); ?><textarea name="expected_result" rows="3"></textarea></label>
                <label class="scfs-rl-consent"><input type="checkbox" name="consent" value="1" required> <?php esc_html_e('I consent to storing this feedback and the limited contextual metadata supplied with it.','sustainable-catalyst-feature-suggestions'); ?></label>
                <button type="submit"><?php esc_html_e('Submit contextual feedback','sustainable-catalyst-feature-suggestions'); ?></button>
            </form>
        </section><?php return ob_get_clean();
    }

    public function add_meta_box() { add_meta_box('scfs-librarian-context',__('Research Librarian Context','sustainable-catalyst-feature-suggestions'),array($this,'meta_box'),Sustainable_Catalyst_Feature_Suggestions::POST_TYPE,'side','default'); }
    public function meta_box($post) {
        if ('research_librarian' !== get_post_meta($post->ID,'_scfs_source',true)) { echo '<p>'.esc_html__('No Research Librarian context is attached.','sustainable-catalyst-feature-suggestions').'</p>'; return; }
        echo '<p><strong>'.esc_html(get_post_meta($post->ID,'_scfs_librarian_feedback_type',true)).'</strong></p>';
        $rating = absint(get_post_meta($post->ID,'_scfs_librarian_rating',true)); if($rating){echo '<p>'.esc_html(sprintf(__('Rating: %d/5','sustainable-catalyst-feature-suggestions'),$rating)).'</p>';}
        $context = get_post_meta($post->ID,'_scfs_librarian_context',true); if(is_array($context)){echo '<dl>';foreach($context as $key=>$value){echo '<dt><strong>'.esc_html($key).'</strong></dt><dd>'.esc_html($value).'</dd>';}echo '</dl>';}
    }
    public function column($columns) { $columns['scfs_librarian'] = __('Librarian context','sustainable-catalyst-feature-suggestions'); return $columns; }
    public function column_value($column,$post_id) { if('scfs_librarian'===$column && 'research_librarian'===get_post_meta($post_id,'_scfs_source',true)){echo esc_html(get_post_meta($post_id,'_scfs_librarian_feedback_type',true)); $rating=absint(get_post_meta($post_id,'_scfs_librarian_rating',true)); if($rating){echo '<br>'.esc_html($rating.'/5');}} }
}
