<?php
/**
 * Plugin Name: Sustainable Catalyst Feature Suggestions
 * Description: Advanced feature suggestion intake, triage, settings, workflow metadata, spam controls, notifications, and CSV export for Sustainable Catalyst.
 * Version: 2.0.0
 * Author: Content Catalyst LLC
 * License: GPL-2.0-or-later
 * Text Domain: sustainable-catalyst-feature-suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Sustainable_Catalyst_Feature_Suggestions {
    const VERSION = '2.0.0';
    const POST_TYPE = 'sc_feature_suggestion';
    const NONCE_ACTION = 'scfs_submit_suggestion';
    const NONCE_NAME = 'scfs_nonce';
    const ADMIN_NONCE_ACTION = 'scfs_save_review';
    const ADMIN_NONCE_NAME = 'scfs_review_nonce';
    const SETTINGS_NONCE_ACTION = 'scfs_save_settings';
    const OPTION_KEY = 'scfs_settings';
    const SHORTCODE = 'sustainable_catalyst_feature_suggestions';

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_admin_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', array($this, 'admin_columns'));
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', array($this, 'admin_column_content'), 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', array($this, 'sortable_columns'));
        add_action('restrict_manage_posts', array($this, 'render_admin_filters'));
        add_action('pre_get_posts', array($this, 'apply_admin_filters'));

        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post_' . self::POST_TYPE, array($this, 'save_admin_review'), 10, 2);

        add_action('admin_menu', array($this, 'add_admin_pages'));
        add_action('admin_post_scfs_export_csv', array($this, 'export_csv'));
        add_action('admin_post_scfs_save_settings', array($this, 'save_settings'));
    }

    public static function activate() {
        $instance = self::instance();
        $instance->register_post_type();
        flush_rewrite_rules();
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, $instance->default_settings(), '', false);
        }
    }

    public static function deactivate() {
        flush_rewrite_rules();
    }

    public function register_post_type() {
        $labels = array(
            'name' => __('Feature Suggestions', 'sustainable-catalyst-feature-suggestions'),
            'singular_name' => __('Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'menu_name' => __('Feature Suggestions', 'sustainable-catalyst-feature-suggestions'),
            'add_new_item' => __('Add Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'edit_item' => __('Review Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'new_item' => __('New Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'view_item' => __('View Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'search_items' => __('Search Feature Suggestions', 'sustainable-catalyst-feature-suggestions'),
            'not_found' => __('No feature suggestions found.', 'sustainable-catalyst-feature-suggestions'),
            'not_found_in_trash' => __('No feature suggestions found in Trash.', 'sustainable-catalyst-feature-suggestions'),
        );

        register_post_type(self::POST_TYPE, array(
            'labels' => $labels,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'menu_icon' => 'dashicons-lightbulb',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'supports' => array('title', 'editor'),
            'has_archive' => false,
            'rewrite' => false,
            'show_in_rest' => false,
        ));
    }

    public function register_assets() {
        wp_register_style(
            'scfs-feature-suggestions',
            plugins_url('assets/feature-suggestions.css', __FILE__),
            array(),
            self::VERSION
        );
    }

    public function register_admin_assets($hook) {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && ($screen->post_type === self::POST_TYPE || strpos((string) $hook, 'scfs-') !== false)) {
            wp_enqueue_style(
                'scfs-admin',
                plugins_url('assets/feature-suggestions-admin.css', __FILE__),
                array(),
                self::VERSION
            );
        }
    }

    private function default_settings() {
        return array(
            'form_title' => 'Suggest a Sustainable Catalyst Feature',
            'form_intro' => 'Use this form to suggest platform modules, online demo improvements, repository features, data/export workflows, article map ideas, accessibility fixes, documentation improvements, or Research Librarian capabilities.',
            'submit_button_label' => 'Submit Feature Suggestion',
            'success_message' => 'Thank you. Your feature suggestion has been received.',
            'error_message' => 'Could not save the suggestion. Please check the form and try again.',
            'consent_text' => 'I understand this form is for non-confidential suggestions and does not create an obligation to implement, attribute, compensate, or respond. Do not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information.',
            'notification_email' => get_option('admin_email'),
            'enable_email_notifications' => '1',
            'submission_status' => 'pending',
            'require_nonce' => '',
            'show_priority' => '1',
            'show_beneficiaries' => '1',
            'show_success_criteria' => '1',
            'show_implementation_notes' => '1',
            'show_relevant_url' => '1',
            'show_contact' => '1',
            'show_follow_up' => '1',
            'collect_user_agent' => '',
            'collect_referrer' => '1',
            'max_submissions_per_hour' => '5',
            'max_submissions_per_day' => '20',
            'min_seconds_before_submit' => '2',
            'duplicate_window_hours' => '24',
            'max_links' => '4',
            'min_problem_length' => '12',
            'min_suggestion_length' => '12',
            'categories' => implode("\n", array(
                'New platform module',
                'Online demo improvement',
                'Research Librarian feature',
                'GitHub repository improvement',
                'Data or export feature',
                'Article map or library suggestion',
                'Accessibility or usability issue',
                'Bug report',
                'Documentation improvement',
                'Consulting or workflow idea',
                'Other',
            )),
            'priorities' => "Low\nMedium\nHigh\nUrgent",
            'blocked_terms' => '',
        );
    }

    private function settings() {
        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            $saved = array();
        }
        return wp_parse_args($saved, $this->default_settings());
    }

    private function setting($key) {
        $settings = $this->settings();
        return isset($settings[$key]) ? $settings[$key] : '';
    }

    private function line_list($key) {
        $settings = $this->settings();
        $raw = isset($settings[$key]) ? (string) $settings[$key] : '';
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out = array();
        foreach ($lines as $line) {
            $line = trim(wp_strip_all_tags($line));
            if ($line !== '' && !in_array($line, $out, true)) {
                $out[] = $line;
            }
        }
        return $out;
    }

    private function categories() {
        $categories = $this->line_list('categories');
        return !empty($categories) ? $categories : $this->line_list_from_default('categories');
    }

    private function priorities() {
        $priorities = $this->line_list('priorities');
        return !empty($priorities) ? $priorities : array('Low', 'Medium', 'High');
    }

    private function line_list_from_default($key) {
        $defaults = $this->default_settings();
        $raw = isset($defaults[$key]) ? (string) $defaults[$key] : '';
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        return array_values(array_filter(array_map('trim', $lines)));
    }

    private function review_statuses() {
        return array(
            'new' => __('New', 'sustainable-catalyst-feature-suggestions'),
            'reviewed' => __('Reviewed', 'sustainable-catalyst-feature-suggestions'),
            'planned' => __('Planned', 'sustainable-catalyst-feature-suggestions'),
            'in_progress' => __('In progress', 'sustainable-catalyst-feature-suggestions'),
            'implemented' => __('Implemented', 'sustainable-catalyst-feature-suggestions'),
            'declined' => __('Declined', 'sustainable-catalyst-feature-suggestions'),
            'duplicate' => __('Duplicate', 'sustainable-catalyst-feature-suggestions'),
            'archived' => __('Archived', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    public function render_shortcode($atts = array()) {
        wp_enqueue_style('scfs-feature-suggestions');

        $settings = $this->settings();
        $atts = shortcode_atts(array(
            'title' => $settings['form_title'],
            'intro' => $settings['form_intro'],
            'category' => '',
        ), $atts, self::SHORTCODE);

        $message = '';
        $message_type = '';
        $values = $this->empty_values();
        if ($atts['category'] !== '' && in_array($atts['category'], $this->categories(), true)) {
            $values['category'] = $atts['category'];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scfs_form_submitted'])) {
            $result = $this->handle_submission();
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
            if (!$result['success'] && isset($result['values'])) {
                $values = $result['values'];
            }
        }

        ob_start();
        ?>
        <div class="scfs-wrap" id="feature-suggestion-form">
            <div class="scfs-intro">
                <p class="scfs-eyebrow"><?php esc_html_e('Platform Feedback', 'sustainable-catalyst-feature-suggestions'); ?></p>
                <h3><?php echo esc_html($atts['title']); ?></h3>
                <?php if (!empty($atts['intro'])) : ?>
                    <p><?php echo esc_html($atts['intro']); ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($message)) : ?>
                <div class="scfs-alert scfs-alert-<?php echo esc_attr($message_type); ?>" role="status">
                    <?php echo wp_kses_post($message); ?>
                </div>
            <?php endif; ?>

            <form class="scfs-form" method="post" action="#feature-suggestion-form" novalidate>
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="scfs_form_submitted" value="1">
                <input type="hidden" name="scfs_started_at" value="<?php echo esc_attr((string) time()); ?>">

                <div class="scfs-honeypot" aria-hidden="true">
                    <label for="scfs_website">Website</label>
                    <input id="scfs_website" type="text" name="scfs_website" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="scfs-grid scfs-grid-two">
                    <p class="scfs-field">
                        <label for="scfs_title"><?php esc_html_e('Suggestion title', 'sustainable-catalyst-feature-suggestions'); ?> <span aria-hidden="true">*</span></label>
                        <input id="scfs_title" name="scfs_title" type="text" required maxlength="160" value="<?php echo esc_attr($values['title']); ?>" placeholder="<?php esc_attr_e('Example: Add CSV export to Catalyst Data demo', 'sustainable-catalyst-feature-suggestions'); ?>">
                    </p>

                    <p class="scfs-field">
                        <label for="scfs_category"><?php esc_html_e('Suggestion category', 'sustainable-catalyst-feature-suggestions'); ?> <span aria-hidden="true">*</span></label>
                        <select id="scfs_category" name="scfs_category" required>
                            <option value=""><?php esc_html_e('Choose a category', 'sustainable-catalyst-feature-suggestions'); ?></option>
                            <?php foreach ($this->categories() as $category) : ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($values['category'], $category); ?>><?php echo esc_html($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <p class="scfs-field">
                    <label for="scfs_problem"><?php esc_html_e('What problem would this solve?', 'sustainable-catalyst-feature-suggestions'); ?> <span aria-hidden="true">*</span></label>
                    <textarea id="scfs_problem" name="scfs_problem" required rows="4" maxlength="2400" placeholder="<?php esc_attr_e('Describe the friction, missing capability, or user need.', 'sustainable-catalyst-feature-suggestions'); ?>"><?php echo esc_textarea($values['problem']); ?></textarea>
                </p>

                <p class="scfs-field">
                    <label for="scfs_suggestion"><?php esc_html_e('Suggested feature or improvement', 'sustainable-catalyst-feature-suggestions'); ?> <span aria-hidden="true">*</span></label>
                    <textarea id="scfs_suggestion" name="scfs_suggestion" required rows="5" maxlength="3600" placeholder="<?php esc_attr_e('Describe what you would like the platform, demo, repository, or Research Librarian to do.', 'sustainable-catalyst-feature-suggestions'); ?>"><?php echo esc_textarea($values['suggestion']); ?></textarea>
                </p>

                <?php if ($settings['show_success_criteria'] === '1') : ?>
                    <p class="scfs-field">
                        <label for="scfs_success_criteria"><?php esc_html_e('What would make this successful?', 'sustainable-catalyst-feature-suggestions'); ?></label>
                        <textarea id="scfs_success_criteria" name="scfs_success_criteria" rows="3" maxlength="1600" placeholder="<?php esc_attr_e('Optional: describe the output, workflow, or result that would make the feature useful.', 'sustainable-catalyst-feature-suggestions'); ?>"><?php echo esc_textarea($values['success_criteria']); ?></textarea>
                    </p>
                <?php endif; ?>

                <?php if ($settings['show_beneficiaries'] === '1') : ?>
                    <p class="scfs-field">
                        <label for="scfs_beneficiaries"><?php esc_html_e('Who would benefit?', 'sustainable-catalyst-feature-suggestions'); ?></label>
                        <textarea id="scfs_beneficiaries" name="scfs_beneficiaries" rows="3" maxlength="1600" placeholder="<?php esc_attr_e('Examples: researchers, students, NGOs, sustainability teams, content teams, public-interest builders.', 'sustainable-catalyst-feature-suggestions'); ?>"><?php echo esc_textarea($values['beneficiaries']); ?></textarea>
                    </p>
                <?php endif; ?>

                <?php if ($settings['show_implementation_notes'] === '1') : ?>
                    <p class="scfs-field">
                        <label for="scfs_implementation_notes"><?php esc_html_e('Implementation notes or examples', 'sustainable-catalyst-feature-suggestions'); ?></label>
                        <textarea id="scfs_implementation_notes" name="scfs_implementation_notes" rows="3" maxlength="2000" placeholder="<?php esc_attr_e('Optional: add examples, references, similar tools, or technical details.', 'sustainable-catalyst-feature-suggestions'); ?>"><?php echo esc_textarea($values['implementation_notes']); ?></textarea>
                    </p>
                <?php endif; ?>

                <div class="scfs-grid scfs-grid-two">
                    <?php if ($settings['show_relevant_url'] === '1') : ?>
                        <p class="scfs-field">
                            <label for="scfs_relevant_url"><?php esc_html_e('Relevant page, demo, or repository', 'sustainable-catalyst-feature-suggestions'); ?></label>
                            <input id="scfs_relevant_url" name="scfs_relevant_url" type="text" maxlength="400" value="<?php echo esc_attr($values['relevant_url']); ?>" placeholder="<?php esc_attr_e('Paste a URL or page name if relevant', 'sustainable-catalyst-feature-suggestions'); ?>">
                        </p>
                    <?php endif; ?>

                    <?php if ($settings['show_priority'] === '1') : ?>
                        <p class="scfs-field">
                            <label for="scfs_priority"><?php esc_html_e('Priority', 'sustainable-catalyst-feature-suggestions'); ?></label>
                            <select id="scfs_priority" name="scfs_priority">
                                <?php foreach ($this->priorities() as $priority) : ?>
                                    <option value="<?php echo esc_attr($priority); ?>" <?php selected($values['priority'], $priority); ?>><?php echo esc_html($priority); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if ($settings['show_contact'] === '1') : ?>
                    <div class="scfs-grid scfs-grid-two">
                        <p class="scfs-field">
                            <label for="scfs_name"><?php esc_html_e('Name', 'sustainable-catalyst-feature-suggestions'); ?></label>
                            <input id="scfs_name" name="scfs_name" type="text" maxlength="120" value="<?php echo esc_attr($values['name']); ?>" placeholder="<?php esc_attr_e('Optional', 'sustainable-catalyst-feature-suggestions'); ?>">
                        </p>

                        <p class="scfs-field">
                            <label for="scfs_email"><?php esc_html_e('Email', 'sustainable-catalyst-feature-suggestions'); ?></label>
                            <input id="scfs_email" name="scfs_email" type="email" maxlength="160" value="<?php echo esc_attr($values['email']); ?>" placeholder="<?php esc_attr_e('Optional, for follow-up only', 'sustainable-catalyst-feature-suggestions'); ?>">
                        </p>
                    </div>
                <?php endif; ?>

                <div class="scfs-checks">
                    <?php if ($settings['show_follow_up'] === '1') : ?>
                        <label class="scfs-check">
                            <input type="checkbox" name="scfs_follow_up" value="1" <?php checked($values['follow_up'], '1'); ?>>
                            <span><?php esc_html_e('You may follow up with me about this suggestion.', 'sustainable-catalyst-feature-suggestions'); ?></span>
                        </label>
                    <?php endif; ?>

                    <label class="scfs-check">
                        <input type="checkbox" name="scfs_consent" value="1" required <?php checked($values['consent'], '1'); ?>>
                        <span><?php echo wp_kses_post($settings['consent_text']); ?></span>
                    </label>
                </div>

                <button class="scfs-submit" type="submit"><?php echo esc_html($settings['submit_button_label']); ?></button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function empty_values() {
        $priorities = $this->priorities();
        return array(
            'title' => '',
            'category' => '',
            'problem' => '',
            'beneficiaries' => '',
            'suggestion' => '',
            'success_criteria' => '',
            'implementation_notes' => '',
            'relevant_url' => '',
            'priority' => isset($priorities[1]) ? $priorities[1] : (isset($priorities[0]) ? $priorities[0] : 'Medium'),
            'name' => '',
            'email' => '',
            'follow_up' => '',
            'consent' => '',
        );
    }

    private function collect_values() {
        $values = $this->empty_values();
        $values['title'] = isset($_POST['scfs_title']) ? sanitize_text_field(wp_unslash($_POST['scfs_title'])) : '';
        $values['category'] = isset($_POST['scfs_category']) ? sanitize_text_field(wp_unslash($_POST['scfs_category'])) : '';
        $values['problem'] = isset($_POST['scfs_problem']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_problem'])) : '';
        $values['beneficiaries'] = isset($_POST['scfs_beneficiaries']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_beneficiaries'])) : '';
        $values['suggestion'] = isset($_POST['scfs_suggestion']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_suggestion'])) : '';
        $values['success_criteria'] = isset($_POST['scfs_success_criteria']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_success_criteria'])) : '';
        $values['implementation_notes'] = isset($_POST['scfs_implementation_notes']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_implementation_notes'])) : '';
        $values['relevant_url'] = isset($_POST['scfs_relevant_url']) ? sanitize_text_field(wp_unslash($_POST['scfs_relevant_url'])) : '';
        $values['priority'] = isset($_POST['scfs_priority']) ? sanitize_text_field(wp_unslash($_POST['scfs_priority'])) : $values['priority'];
        $values['name'] = isset($_POST['scfs_name']) ? sanitize_text_field(wp_unslash($_POST['scfs_name'])) : '';
        $values['email'] = isset($_POST['scfs_email']) ? sanitize_email(wp_unslash($_POST['scfs_email'])) : '';
        $values['follow_up'] = isset($_POST['scfs_follow_up']) ? '1' : '';
        $values['consent'] = isset($_POST['scfs_consent']) ? '1' : '';
        return $values;
    }

    private function handle_submission() {
        $settings = $this->settings();
        $values = $this->collect_values();

        if (!empty($_POST['scfs_website'])) {
            return array(
                'success' => true,
                'message' => esc_html($settings['success_message']),
            );
        }

        if ($settings['require_nonce'] === '1') {
            if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
                return array(
                    'success' => false,
                    'message' => __('Security check failed. Please refresh the page and try again. If this continues, disable strict nonce validation in the plugin settings when using page caching.', 'sustainable-catalyst-feature-suggestions'),
                    'values' => $values,
                );
            }
        }

        $spam_result = $this->passes_spam_controls($settings, $values);
        if (is_wp_error($spam_result)) {
            return array(
                'success' => false,
                'message' => esc_html($spam_result->get_error_message()),
                'values' => $values,
            );
        }

        $errors = $this->validate_values($values, $settings);
        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => '<strong>' . esc_html__('Please fix the following:', 'sustainable-catalyst-feature-suggestions') . '</strong><br>' . esc_html(implode(' ', $errors)),
                'values' => $values,
            );
        }
        if (!in_array($values['priority'], $this->priorities(), true)) {
            $priorities = $this->priorities();
            $values['priority'] = isset($priorities[0]) ? $priorities[0] : 'Medium';
        }

        $fingerprint = $this->submission_fingerprint($values);
        $duplicate_key = 'scfs_dup_' . substr($fingerprint, 0, 32);
        if ($this->int_setting($settings, 'duplicate_window_hours', 24, 0, 168) > 0 && get_transient($duplicate_key)) {
            return array(
                'success' => false,
                'message' => __('This looks like a duplicate submission. Please edit the suggestion if you meant to add new information.', 'sustainable-catalyst-feature-suggestions'),
                'values' => $values,
            );
        }

        $post_content = $this->build_post_content($values);
        $post_status = $this->allowed_post_status($settings['submission_status']);

        $post_id = wp_insert_post(wp_slash(array(
            'post_type' => self::POST_TYPE,
            'post_status' => $post_status,
            'post_title' => $values['title'],
            'post_content' => $post_content,
            'post_author' => $this->default_author_id(),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        )), true);

        if (is_wp_error($post_id) || !absint($post_id)) {
            $debug_message = is_wp_error($post_id) ? $post_id->get_error_message() : __('WordPress returned an empty post ID.', 'sustainable-catalyst-feature-suggestions');
            $message = $settings['error_message'];
            if (current_user_can('manage_options')) {
                $message .= ' ' . sprintf(__('Debug: %s', 'sustainable-catalyst-feature-suggestions'), $debug_message);
            }
            return array(
                'success' => false,
                'message' => esc_html($message),
                'values' => $values,
            );
        }

        foreach ($values as $key => $value) {
            update_post_meta($post_id, '_scfs_' . $key, $value);
        }
        update_post_meta($post_id, '_scfs_fingerprint', $fingerprint);
        update_post_meta($post_id, '_scfs_review_status', 'new');
        update_post_meta($post_id, '_scfs_impact_score', '0');
        update_post_meta($post_id, '_scfs_effort_score', '0');
        update_post_meta($post_id, '_scfs_ip_hash', $this->ip_hash());

        if ($settings['collect_user_agent'] === '1') {
            update_post_meta($post_id, '_scfs_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '');
        }
        if ($settings['collect_referrer'] === '1') {
            update_post_meta($post_id, '_scfs_referrer', isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '');
        }

        if ($this->int_setting($settings, 'duplicate_window_hours', 24, 0, 168) > 0) {
            set_transient($duplicate_key, '1', HOUR_IN_SECONDS * $this->int_setting($settings, 'duplicate_window_hours', 24, 0, 168));
        }
        $this->increment_rate_limits($settings);
        $this->send_notification($post_id, $values, $settings);

        return array(
            'success' => true,
            'message' => esc_html($settings['success_message']),
        );
    }

    private function validate_values($values, $settings) {
        $errors = array();
        if ($values['title'] === '') {
            $errors[] = __('Suggestion title is required.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['category'] === '' || !in_array($values['category'], $this->categories(), true)) {
            $errors[] = __('Please choose a valid suggestion category.', 'sustainable-catalyst-feature-suggestions');
        }
        if (strlen($values['problem']) < $this->int_setting($settings, 'min_problem_length', 12, 0, 1000)) {
            $errors[] = __('Please describe the problem this would solve in a little more detail.', 'sustainable-catalyst-feature-suggestions');
        }
        if (strlen($values['suggestion']) < $this->int_setting($settings, 'min_suggestion_length', 12, 0, 1000)) {
            $errors[] = __('Please describe the suggested feature or improvement in a little more detail.', 'sustainable-catalyst-feature-suggestions');
        }
        if (!in_array($values['priority'], $this->priorities(), true)) {
            $values['priority'] = isset($this->priorities()[0]) ? $this->priorities()[0] : 'Medium';
        }
        if ($values['email'] !== '' && !is_email($values['email'])) {
            $errors[] = __('Please enter a valid email address or leave it blank.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['consent'] !== '1') {
            $errors[] = __('Please confirm the non-confidential submission notice.', 'sustainable-catalyst-feature-suggestions');
        }
        return $errors;
    }

    private function passes_spam_controls($settings, $values) {
        $started = isset($_POST['scfs_started_at']) ? absint($_POST['scfs_started_at']) : 0;
        $min_seconds = $this->int_setting($settings, 'min_seconds_before_submit', 2, 0, 30);
        if ($min_seconds > 0 && $started > 0 && (time() - $started) < $min_seconds) {
            return new WP_Error('scfs_too_fast', __('Please take a moment to complete the form before submitting.', 'sustainable-catalyst-feature-suggestions'));
        }

        $hour_limit = $this->int_setting($settings, 'max_submissions_per_hour', 5, 0, 1000);
        $day_limit = $this->int_setting($settings, 'max_submissions_per_day', 20, 0, 5000);
        $hash = $this->ip_hash();
        if ($hash !== '') {
            if ($hour_limit > 0 && absint(get_transient('scfs_hour_' . $hash)) >= $hour_limit) {
                return new WP_Error('scfs_rate_hour', __('Too many feature suggestions were submitted recently. Please try again later.', 'sustainable-catalyst-feature-suggestions'));
            }
            if ($day_limit > 0 && absint(get_transient('scfs_day_' . $hash)) >= $day_limit) {
                return new WP_Error('scfs_rate_day', __('Too many feature suggestions were submitted today. Please try again later.', 'sustainable-catalyst-feature-suggestions'));
            }
        }

        $combined = strtolower(implode(' ', $values));
        $blocked_terms = $this->line_list('blocked_terms');
        foreach ($blocked_terms as $term) {
            if ($term !== '' && strpos($combined, strtolower($term)) !== false) {
                return new WP_Error('scfs_blocked_term', __('This suggestion could not be accepted. Please remove prohibited or spam-like content and try again.', 'sustainable-catalyst-feature-suggestions'));
            }
        }

        $max_links = $this->int_setting($settings, 'max_links', 4, 0, 100);
        if ($max_links > 0) {
            preg_match_all('/https?:\/\/|www\./i', $combined, $matches);
            if (count($matches[0]) > $max_links) {
                return new WP_Error('scfs_too_many_links', __('Please reduce the number of links in the suggestion.', 'sustainable-catalyst-feature-suggestions'));
            }
        }

        return true;
    }

    private function increment_rate_limits($settings) {
        $hash = $this->ip_hash();
        if ($hash === '') {
            return;
        }
        $hour_limit = $this->int_setting($settings, 'max_submissions_per_hour', 5, 0, 1000);
        $day_limit = $this->int_setting($settings, 'max_submissions_per_day', 20, 0, 5000);
        if ($hour_limit > 0) {
            $key = 'scfs_hour_' . $hash;
            $count = absint(get_transient($key));
            set_transient($key, $count + 1, HOUR_IN_SECONDS);
        }
        if ($day_limit > 0) {
            $key = 'scfs_day_' . $hash;
            $count = absint(get_transient($key));
            set_transient($key, $count + 1, DAY_IN_SECONDS);
        }
    }

    private function submission_fingerprint($values) {
        $basis = strtolower(trim($values['title'] . '|' . $values['email'] . '|' . $values['suggestion']));
        return hash('sha256', $basis . '|' . wp_salt('auth'));
    }

    private function build_post_content($values) {
        $sections = array(
            'Problem' => $values['problem'],
            'Suggested feature' => $values['suggestion'],
            'Success criteria' => $values['success_criteria'],
            'Who benefits' => $values['beneficiaries'],
            'Implementation notes' => $values['implementation_notes'],
            'Relevant page/demo/repository' => $values['relevant_url'],
        );
        $content = '';
        foreach ($sections as $label => $value) {
            if ($value !== '') {
                $content .= $label . ":\n" . $value . "\n\n";
            }
        }
        return trim($content);
    }

    private function default_author_id() {
        $current = get_current_user_id();
        if ($current) {
            return $current;
        }
        $admin_email = get_option('admin_email');
        if ($admin_email) {
            $user = get_user_by('email', $admin_email);
            if ($user && !empty($user->ID)) {
                return absint($user->ID);
            }
        }
        $admins = get_users(array('role' => 'administrator', 'number' => 1, 'fields' => 'ids'));
        if (!empty($admins[0])) {
            return absint($admins[0]);
        }
        return 0;
    }

    private function ip_hash() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if ($ip === '') {
            return '';
        }
        return hash('sha256', $ip . wp_salt('nonce'));
    }

    private function send_notification($post_id, $values, $settings) {
        if ($settings['enable_email_notifications'] !== '1') {
            return;
        }
        $to = sanitize_email($settings['notification_email']);
        if (!$to || !is_email($to)) {
            $to = get_option('admin_email');
        }
        if (!$to || !is_email($to)) {
            return;
        }

        $subject = sprintf('[Sustainable Catalyst] Feature suggestion: %s', $values['title']);
        $edit_link = admin_url('post.php?post=' . absint($post_id) . '&action=edit');
        $body = "A new feature suggestion was submitted.\n\n";
        $body .= "Title: " . $values['title'] . "\n";
        $body .= "Category: " . $values['category'] . "\n";
        $body .= "Priority: " . $values['priority'] . "\n";
        $body .= "Relevant page/repo: " . $values['relevant_url'] . "\n\n";
        $body .= "Problem it solves:\n" . $values['problem'] . "\n\n";
        $body .= "Suggested feature:\n" . $values['suggestion'] . "\n\n";
        if ($values['success_criteria'] !== '') {
            $body .= "Success criteria:\n" . $values['success_criteria'] . "\n\n";
        }
        if ($values['beneficiaries'] !== '') {
            $body .= "Who benefits:\n" . $values['beneficiaries'] . "\n\n";
        }
        if ($values['implementation_notes'] !== '') {
            $body .= "Implementation notes:\n" . $values['implementation_notes'] . "\n\n";
        }
        $body .= "Name: " . $values['name'] . "\n";
        $body .= "Email: " . $values['email'] . "\n";
        $body .= "Follow-up allowed: " . ($values['follow_up'] === '1' ? 'Yes' : 'No') . "\n\n";
        $body .= "Review in WordPress: " . $edit_link . "\n";

        wp_mail($to, $subject, $body);
    }

    public function admin_columns($columns) {
        $new = array();
        $new['cb'] = isset($columns['cb']) ? $columns['cb'] : '<input type="checkbox" />';
        $new['title'] = __('Suggestion', 'sustainable-catalyst-feature-suggestions');
        $new['scfs_category'] = __('Category', 'sustainable-catalyst-feature-suggestions');
        $new['scfs_priority'] = __('Priority', 'sustainable-catalyst-feature-suggestions');
        $new['scfs_workflow'] = __('Workflow', 'sustainable-catalyst-feature-suggestions');
        $new['scfs_contact'] = __('Contact', 'sustainable-catalyst-feature-suggestions');
        $new['date'] = __('Date', 'sustainable-catalyst-feature-suggestions');
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        if ($column === 'scfs_category') {
            echo esc_html(get_post_meta($post_id, '_scfs_category', true));
        } elseif ($column === 'scfs_priority') {
            echo esc_html(get_post_meta($post_id, '_scfs_priority', true));
        } elseif ($column === 'scfs_workflow') {
            $status = get_post_meta($post_id, '_scfs_review_status', true);
            $statuses = $this->review_statuses();
            $impact = get_post_meta($post_id, '_scfs_impact_score', true);
            $effort = get_post_meta($post_id, '_scfs_effort_score', true);
            echo '<strong>' . esc_html(isset($statuses[$status]) ? $statuses[$status] : __('New', 'sustainable-catalyst-feature-suggestions')) . '</strong>';
            echo '<br><small>' . esc_html(sprintf(__('Impact %s / Effort %s', 'sustainable-catalyst-feature-suggestions'), $impact === '' ? '0' : $impact, $effort === '' ? '0' : $effort)) . '</small>';
        } elseif ($column === 'scfs_contact') {
            $name = get_post_meta($post_id, '_scfs_name', true);
            $email = get_post_meta($post_id, '_scfs_email', true);
            $follow = get_post_meta($post_id, '_scfs_follow_up', true) === '1' ? __('Follow-up OK', 'sustainable-catalyst-feature-suggestions') : __('No follow-up', 'sustainable-catalyst-feature-suggestions');
            echo esc_html(trim($name . ' ' . $email));
            echo '<br><small>' . esc_html($follow) . '</small>';
        }
    }

    public function sortable_columns($columns) {
        $columns['scfs_priority'] = 'scfs_priority';
        $columns['scfs_category'] = 'scfs_category';
        $columns['scfs_workflow'] = 'scfs_workflow';
        return $columns;
    }

    public function render_admin_filters() {
        global $typenow;
        if ($typenow !== self::POST_TYPE) {
            return;
        }
        $this->render_filter_select('scfs_filter_category', __('All categories', 'sustainable-catalyst-feature-suggestions'), $this->categories(), isset($_GET['scfs_filter_category']) ? sanitize_text_field(wp_unslash($_GET['scfs_filter_category'])) : '');
        $this->render_filter_select('scfs_filter_priority', __('All priorities', 'sustainable-catalyst-feature-suggestions'), $this->priorities(), isset($_GET['scfs_filter_priority']) ? sanitize_text_field(wp_unslash($_GET['scfs_filter_priority'])) : '');
        $this->render_filter_select('scfs_filter_review_status', __('All workflow statuses', 'sustainable-catalyst-feature-suggestions'), $this->review_statuses(), isset($_GET['scfs_filter_review_status']) ? sanitize_text_field(wp_unslash($_GET['scfs_filter_review_status'])) : '');
    }

    private function render_filter_select($name, $all_label, $options, $selected) {
        echo '<select name="' . esc_attr($name) . '">';
        echo '<option value="">' . esc_html($all_label) . '</option>';
        foreach ($options as $key => $label) {
            if (is_int($key)) {
                $key = $label;
            }
            echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }

    public function apply_admin_filters($query) {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        $meta_query = array();
        $filters = array(
            'scfs_filter_category' => '_scfs_category',
            'scfs_filter_priority' => '_scfs_priority',
            'scfs_filter_review_status' => '_scfs_review_status',
        );
        foreach ($filters as $get_key => $meta_key) {
            if (!empty($_GET[$get_key])) {
                $meta_query[] = array(
                    'key' => $meta_key,
                    'value' => sanitize_text_field(wp_unslash($_GET[$get_key])),
                    'compare' => '=',
                );
            }
        }
        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'scfs_priority') {
            $query->set('meta_key', '_scfs_priority');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'scfs_category') {
            $query->set('meta_key', '_scfs_category');
            $query->set('orderby', 'meta_value');
        } elseif ($orderby === 'scfs_workflow') {
            $query->set('meta_key', '_scfs_review_status');
            $query->set('orderby', 'meta_value');
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'scfs_details',
            __('Suggestion Details', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_details_metabox'),
            self::POST_TYPE,
            'normal',
            'high'
        );
        add_meta_box(
            'scfs_workflow',
            __('Review Workflow', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_workflow_metabox'),
            self::POST_TYPE,
            'side',
            'high'
        );
        add_meta_box(
            'scfs_submission_meta',
            __('Submission Metadata', 'sustainable-catalyst-feature-suggestions'),
            array($this, 'render_submission_meta_metabox'),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_details_metabox($post) {
        $fields = array(
            'category' => 'Category',
            'priority' => 'Priority',
            'problem' => 'Problem it solves',
            'suggestion' => 'Suggested feature',
            'success_criteria' => 'Success criteria',
            'beneficiaries' => 'Who benefits',
            'implementation_notes' => 'Implementation notes',
            'relevant_url' => 'Relevant page/demo/repository',
            'name' => 'Name',
            'email' => 'Email',
            'follow_up' => 'Follow-up allowed',
            'consent' => 'Consent confirmed',
        );

        echo '<div class="scfs-admin-details">';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_scfs_' . $key, true);
            if ($key === 'follow_up' || $key === 'consent') {
                $value = $value === '1' ? 'Yes' : 'No';
            }
            echo '<div class="scfs-admin-row">';
            echo '<strong>' . esc_html($label) . '</strong>';
            echo '<p>' . nl2br(esc_html($value)) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    public function render_workflow_metabox($post) {
        wp_nonce_field(self::ADMIN_NONCE_ACTION, self::ADMIN_NONCE_NAME);
        $review_status = get_post_meta($post->ID, '_scfs_review_status', true);
        $impact = get_post_meta($post->ID, '_scfs_impact_score', true);
        $effort = get_post_meta($post->ID, '_scfs_effort_score', true);
        $roadmap_area = get_post_meta($post->ID, '_scfs_roadmap_area', true);
        $github_issue_url = get_post_meta($post->ID, '_scfs_github_issue_url', true);
        $admin_notes = get_post_meta($post->ID, '_scfs_admin_notes', true);
        ?>
        <p>
            <label for="scfs_review_status"><strong><?php esc_html_e('Workflow status', 'sustainable-catalyst-feature-suggestions'); ?></strong></label><br>
            <select id="scfs_review_status" name="scfs_review_status" class="widefat">
                <?php foreach ($this->review_statuses() as $key => $label) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($review_status ? $review_status : 'new', $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="scfs_impact_score"><strong><?php esc_html_e('Impact score', 'sustainable-catalyst-feature-suggestions'); ?></strong></label><br>
            <select id="scfs_impact_score" name="scfs_impact_score" class="widefat">
                <?php for ($i = 0; $i <= 5; $i++) : ?>
                    <option value="<?php echo esc_attr((string) $i); ?>" <?php selected((string) $impact, (string) $i); ?>><?php echo esc_html((string) $i); ?></option>
                <?php endfor; ?>
            </select>
        </p>
        <p>
            <label for="scfs_effort_score"><strong><?php esc_html_e('Effort score', 'sustainable-catalyst-feature-suggestions'); ?></strong></label><br>
            <select id="scfs_effort_score" name="scfs_effort_score" class="widefat">
                <?php for ($i = 0; $i <= 5; $i++) : ?>
                    <option value="<?php echo esc_attr((string) $i); ?>" <?php selected((string) $effort, (string) $i); ?>><?php echo esc_html((string) $i); ?></option>
                <?php endfor; ?>
            </select>
        </p>
        <p>
            <label for="scfs_roadmap_area"><strong><?php esc_html_e('Roadmap area', 'sustainable-catalyst-feature-suggestions'); ?></strong></label>
            <input id="scfs_roadmap_area" name="scfs_roadmap_area" type="text" class="widefat" value="<?php echo esc_attr($roadmap_area); ?>" placeholder="Research Library, GitHub, Demo, etc.">
        </p>
        <p>
            <label for="scfs_github_issue_url"><strong><?php esc_html_e('GitHub issue / task URL', 'sustainable-catalyst-feature-suggestions'); ?></strong></label>
            <input id="scfs_github_issue_url" name="scfs_github_issue_url" type="url" class="widefat" value="<?php echo esc_attr($github_issue_url); ?>">
        </p>
        <p>
            <label for="scfs_admin_notes"><strong><?php esc_html_e('Internal notes', 'sustainable-catalyst-feature-suggestions'); ?></strong></label>
            <textarea id="scfs_admin_notes" name="scfs_admin_notes" rows="5" class="widefat"><?php echo esc_textarea($admin_notes); ?></textarea>
        </p>
        <?php
    }

    public function render_submission_meta_metabox($post) {
        $fields = array(
            'ip_hash' => __('IP hash', 'sustainable-catalyst-feature-suggestions'),
            'referrer' => __('Referrer', 'sustainable-catalyst-feature-suggestions'),
            'user_agent' => __('User agent', 'sustainable-catalyst-feature-suggestions'),
            'fingerprint' => __('Duplicate fingerprint', 'sustainable-catalyst-feature-suggestions'),
        );
        echo '<div class="scfs-admin-meta">';
        foreach ($fields as $key => $label) {
            $value = get_post_meta($post->ID, '_scfs_' . $key, true);
            if ($value === '') {
                continue;
            }
            echo '<p><strong>' . esc_html($label) . '</strong><br><code>' . esc_html($value) . '</code></p>';
        }
        echo '</div>';
    }

    public function save_admin_review($post_id, $post) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST[self::ADMIN_NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::ADMIN_NONCE_NAME])), self::ADMIN_NONCE_ACTION)) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        $statuses = $this->review_statuses();
        $review_status = isset($_POST['scfs_review_status']) ? sanitize_text_field(wp_unslash($_POST['scfs_review_status'])) : 'new';
        if (!isset($statuses[$review_status])) {
            $review_status = 'new';
        }
        update_post_meta($post_id, '_scfs_review_status', $review_status);
        update_post_meta($post_id, '_scfs_impact_score', isset($_POST['scfs_impact_score']) ? min(5, max(0, absint($_POST['scfs_impact_score']))) : 0);
        update_post_meta($post_id, '_scfs_effort_score', isset($_POST['scfs_effort_score']) ? min(5, max(0, absint($_POST['scfs_effort_score']))) : 0);
        update_post_meta($post_id, '_scfs_roadmap_area', isset($_POST['scfs_roadmap_area']) ? sanitize_text_field(wp_unslash($_POST['scfs_roadmap_area'])) : '');
        update_post_meta($post_id, '_scfs_github_issue_url', isset($_POST['scfs_github_issue_url']) ? esc_url_raw(wp_unslash($_POST['scfs_github_issue_url'])) : '');
        update_post_meta($post_id, '_scfs_admin_notes', isset($_POST['scfs_admin_notes']) ? sanitize_textarea_field(wp_unslash($_POST['scfs_admin_notes'])) : '');
    }

    public function add_admin_pages() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Feature Suggestion Settings', 'sustainable-catalyst-feature-suggestions'),
            __('Settings', 'sustainable-catalyst-feature-suggestions'),
            'manage_options',
            'scfs-settings',
            array($this, 'render_settings_page')
        );
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Export Suggestions', 'sustainable-catalyst-feature-suggestions'),
            __('Export CSV', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-export',
            array($this, 'render_export_page')
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to manage these settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        $settings = $this->settings();
        ?>
        <div class="wrap scfs-settings-page">
            <h1><?php esc_html_e('Feature Suggestion Settings', 'sustainable-catalyst-feature-suggestions'); ?></h1>
            <?php if (!empty($_GET['settings-updated'])) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'sustainable-catalyst-feature-suggestions'); ?></p></div>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="scfs_save_settings">
                <?php wp_nonce_field(self::SETTINGS_NONCE_ACTION, 'scfs_settings_nonce'); ?>

                <div class="scfs-settings-grid">
                    <section class="scfs-settings-card">
                        <h2><?php esc_html_e('Form Content', 'sustainable-catalyst-feature-suggestions'); ?></h2>
                        <?php $this->text_input('form_title', __('Form title', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->textarea_input('form_intro', __('Intro text', 'sustainable-catalyst-feature-suggestions'), $settings, 3); ?>
                        <?php $this->text_input('submit_button_label', __('Submit button label', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->textarea_input('success_message', __('Success message', 'sustainable-catalyst-feature-suggestions'), $settings, 2); ?>
                        <?php $this->textarea_input('error_message', __('Generic save error message', 'sustainable-catalyst-feature-suggestions'), $settings, 2); ?>
                        <?php $this->textarea_input('consent_text', __('Consent text', 'sustainable-catalyst-feature-suggestions'), $settings, 4); ?>
                    </section>

                    <section class="scfs-settings-card">
                        <h2><?php esc_html_e('Fields and Taxonomy', 'sustainable-catalyst-feature-suggestions'); ?></h2>
                        <?php $this->checkbox_input('show_priority', __('Show priority field', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_beneficiaries', __('Show beneficiaries field', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_success_criteria', __('Show success criteria field', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_implementation_notes', __('Show implementation notes field', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_relevant_url', __('Show relevant URL field', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_contact', __('Show name and email fields', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('show_follow_up', __('Show follow-up permission checkbox', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->textarea_input('categories', __('Categories, one per line', 'sustainable-catalyst-feature-suggestions'), $settings, 8); ?>
                        <?php $this->textarea_input('priorities', __('Priorities, one per line', 'sustainable-catalyst-feature-suggestions'), $settings, 4); ?>
                    </section>

                    <section class="scfs-settings-card">
                        <h2><?php esc_html_e('Submission Handling', 'sustainable-catalyst-feature-suggestions'); ?></h2>
                        <p class="scfs-setting-field">
                            <label for="scfs_submission_status"><strong><?php esc_html_e('Default saved status', 'sustainable-catalyst-feature-suggestions'); ?></strong></label>
                            <select id="scfs_submission_status" name="scfs_settings[submission_status]">
                                <?php foreach (array('pending' => 'Pending Review', 'private' => 'Private', 'draft' => 'Draft', 'publish' => 'Published internally') as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($settings['submission_status'], $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <?php $this->checkbox_input('enable_email_notifications', __('Send email notifications', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->text_input('notification_email', __('Notification email', 'sustainable-catalyst-feature-suggestions'), $settings, 'email'); ?>
                        <?php $this->checkbox_input('collect_referrer', __('Store referrer URL', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->checkbox_input('collect_user_agent', __('Store user agent', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                    </section>

                    <section class="scfs-settings-card">
                        <h2><?php esc_html_e('Spam and Abuse Controls', 'sustainable-catalyst-feature-suggestions'); ?></h2>
                        <p class="description"><?php esc_html_e('Strict nonce validation is off by default because cached WordPress pages often break public form nonces. Honeypot, duplicate detection, link limits, blocked terms, and rate limiting remain active.', 'sustainable-catalyst-feature-suggestions'); ?></p>
                        <?php $this->checkbox_input('require_nonce', __('Require strict WordPress nonce validation', 'sustainable-catalyst-feature-suggestions'), $settings); ?>
                        <?php $this->number_input('max_submissions_per_hour', __('Max submissions per IP hash per hour', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 1000); ?>
                        <?php $this->number_input('max_submissions_per_day', __('Max submissions per IP hash per day', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 5000); ?>
                        <?php $this->number_input('min_seconds_before_submit', __('Minimum seconds before submit', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 30); ?>
                        <?php $this->number_input('duplicate_window_hours', __('Duplicate detection window, hours', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 168); ?>
                        <?php $this->number_input('max_links', __('Maximum links per suggestion', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 100); ?>
                        <?php $this->number_input('min_problem_length', __('Minimum problem length', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 1000); ?>
                        <?php $this->number_input('min_suggestion_length', __('Minimum suggestion length', 'sustainable-catalyst-feature-suggestions'), $settings, 0, 1000); ?>
                        <?php $this->textarea_input('blocked_terms', __('Blocked terms, one per line', 'sustainable-catalyst-feature-suggestions'), $settings, 6); ?>
                    </section>
                </div>

                <?php submit_button(__('Save Feature Suggestion Settings', 'sustainable-catalyst-feature-suggestions')); ?>
            </form>
        </div>
        <?php
    }

    private function text_input($key, $label, $settings, $type = 'text') {
        echo '<p class="scfs-setting-field"><label for="scfs_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input id="scfs_' . esc_attr($key) . '" type="' . esc_attr($type) . '" name="scfs_settings[' . esc_attr($key) . ']" value="' . esc_attr($settings[$key]) . '" class="regular-text"></p>';
    }

    private function textarea_input($key, $label, $settings, $rows = 4) {
        echo '<p class="scfs-setting-field"><label for="scfs_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<textarea id="scfs_' . esc_attr($key) . '" name="scfs_settings[' . esc_attr($key) . ']" rows="' . esc_attr((string) $rows) . '" class="large-text">' . esc_textarea($settings[$key]) . '</textarea></p>';
    }

    private function checkbox_input($key, $label, $settings) {
        echo '<p class="scfs-setting-field scfs-setting-check"><label><input type="checkbox" name="scfs_settings[' . esc_attr($key) . ']" value="1" ' . checked(isset($settings[$key]) ? $settings[$key] : '', '1', false) . '> ' . esc_html($label) . '</label></p>';
    }

    private function number_input($key, $label, $settings, $min, $max) {
        echo '<p class="scfs-setting-field"><label for="scfs_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        echo '<input id="scfs_' . esc_attr($key) . '" type="number" min="' . esc_attr((string) $min) . '" max="' . esc_attr((string) $max) . '" name="scfs_settings[' . esc_attr($key) . ']" value="' . esc_attr($settings[$key]) . '" class="small-text"></p>';
    }

    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to manage these settings.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer(self::SETTINGS_NONCE_ACTION, 'scfs_settings_nonce');
        $defaults = $this->default_settings();
        $incoming = isset($_POST['scfs_settings']) && is_array($_POST['scfs_settings']) ? wp_unslash($_POST['scfs_settings']) : array();
        $settings = array();

        foreach ($defaults as $key => $default) {
            if (in_array($key, array('show_priority', 'show_beneficiaries', 'show_success_criteria', 'show_implementation_notes', 'show_relevant_url', 'show_contact', 'show_follow_up', 'enable_email_notifications', 'collect_user_agent', 'collect_referrer', 'require_nonce'), true)) {
                $settings[$key] = isset($incoming[$key]) ? '1' : '';
            } elseif (in_array($key, array('max_submissions_per_hour', 'max_submissions_per_day', 'min_seconds_before_submit', 'duplicate_window_hours', 'max_links', 'min_problem_length', 'min_suggestion_length'), true)) {
                $settings[$key] = (string) max(0, absint(isset($incoming[$key]) ? $incoming[$key] : $default));
            } elseif ($key === 'notification_email') {
                $email = sanitize_email(isset($incoming[$key]) ? $incoming[$key] : $default);
                $settings[$key] = is_email($email) ? $email : get_option('admin_email');
            } elseif ($key === 'submission_status') {
                $settings[$key] = $this->allowed_post_status(isset($incoming[$key]) ? sanitize_text_field($incoming[$key]) : $default);
            } elseif (in_array($key, array('form_intro', 'success_message', 'error_message', 'consent_text', 'categories', 'priorities', 'blocked_terms'), true)) {
                $settings[$key] = sanitize_textarea_field(isset($incoming[$key]) ? $incoming[$key] : $default);
            } else {
                $settings[$key] = sanitize_text_field(isset($incoming[$key]) ? $incoming[$key] : $default);
            }
        }

        update_option(self::OPTION_KEY, $settings, false);
        wp_safe_redirect(add_query_arg(array('post_type' => self::POST_TYPE, 'page' => 'scfs-settings', 'settings-updated' => '1'), admin_url('edit.php')));
        exit;
    }

    private function allowed_post_status($status) {
        $allowed = array('pending', 'private', 'draft', 'publish');
        return in_array($status, $allowed, true) ? $status : 'pending';
    }

    private function int_setting($settings, $key, $default, $min = 0, $max = PHP_INT_MAX) {
        $value = isset($settings[$key]) ? absint($settings[$key]) : absint($default);
        return min($max, max($min, $value));
    }

    public function render_export_page() {
        $url = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_csv'), 'scfs_export_csv');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Export Feature Suggestions', 'sustainable-catalyst-feature-suggestions'); ?></h1>
            <p><?php esc_html_e('Download all feature suggestions as a CSV file for review, backlog planning, GitHub issue creation, or archival.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <p><a class="button button-primary" href="<?php echo esc_url($url); ?>"><?php esc_html_e('Download CSV', 'sustainable-catalyst-feature-suggestions'); ?></a></p>
        </div>
        <?php
    }

    public function export_csv() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to export suggestions.', 'sustainable-catalyst-feature-suggestions'));
        }
        check_admin_referer('scfs_export_csv');

        $filename = 'sustainable-catalyst-feature-suggestions-' . gmdate('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fputcsv($out, array(
            'ID', 'Date', 'Post Status', 'Title', 'Category', 'Priority', 'Review Status', 'Impact Score', 'Effort Score', 'Roadmap Area', 'GitHub Issue URL', 'Problem', 'Suggestion', 'Success Criteria', 'Beneficiaries', 'Implementation Notes', 'Relevant URL', 'Name', 'Email', 'Follow Up Allowed', 'Consent Confirmed', 'Referrer', 'IP Hash', 'Admin Notes'
        ));

        $query = new WP_Query(array(
            'post_type' => self::POST_TYPE,
            'post_status' => array('private', 'publish', 'draft', 'pending'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'no_found_rows' => true,
        ));

        foreach ($query->posts as $post) {
            fputcsv($out, array(
                $post->ID,
                $post->post_date,
                $post->post_status,
                $post->post_title,
                get_post_meta($post->ID, '_scfs_category', true),
                get_post_meta($post->ID, '_scfs_priority', true),
                get_post_meta($post->ID, '_scfs_review_status', true),
                get_post_meta($post->ID, '_scfs_impact_score', true),
                get_post_meta($post->ID, '_scfs_effort_score', true),
                get_post_meta($post->ID, '_scfs_roadmap_area', true),
                get_post_meta($post->ID, '_scfs_github_issue_url', true),
                get_post_meta($post->ID, '_scfs_problem', true),
                get_post_meta($post->ID, '_scfs_suggestion', true),
                get_post_meta($post->ID, '_scfs_success_criteria', true),
                get_post_meta($post->ID, '_scfs_beneficiaries', true),
                get_post_meta($post->ID, '_scfs_implementation_notes', true),
                get_post_meta($post->ID, '_scfs_relevant_url', true),
                get_post_meta($post->ID, '_scfs_name', true),
                get_post_meta($post->ID, '_scfs_email', true),
                get_post_meta($post->ID, '_scfs_follow_up', true) === '1' ? 'Yes' : 'No',
                get_post_meta($post->ID, '_scfs_consent', true) === '1' ? 'Yes' : 'No',
                get_post_meta($post->ID, '_scfs_referrer', true),
                get_post_meta($post->ID, '_scfs_ip_hash', true),
                get_post_meta($post->ID, '_scfs_admin_notes', true),
            ));
        }
        fclose($out);
        exit;
    }
}

Sustainable_Catalyst_Feature_Suggestions::instance();
register_activation_hook(__FILE__, array('Sustainable_Catalyst_Feature_Suggestions', 'activate'));
register_deactivation_hook(__FILE__, array('Sustainable_Catalyst_Feature_Suggestions', 'deactivate'));
