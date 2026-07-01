<?php
/**
 * Plugin Name: Sustainable Catalyst Feature Suggestions
 * Description: Adds a structured feature suggestion form, private suggestion records, email notifications, and CSV export for Sustainable Catalyst.
 * Version: 1.0.0
 * Author: Content Catalyst LLC
 * License: GPL-2.0-or-later
 * Text Domain: sustainable-catalyst-feature-suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Sustainable_Catalyst_Feature_Suggestions {
    const VERSION = '1.0.0';
    const POST_TYPE = 'sc_feature_suggestion';
    const NONCE_ACTION = 'scfs_submit_suggestion';
    const NONCE_NAME = 'scfs_nonce';
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
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('admin_menu', array($this, 'add_export_page'));
        add_action('admin_post_scfs_export_csv', array($this, 'export_csv'));
    }

    public function register_post_type() {
        $labels = array(
            'name' => __('Feature Suggestions', 'sustainable-catalyst-feature-suggestions'),
            'singular_name' => __('Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'menu_name' => __('Feature Suggestions', 'sustainable-catalyst-feature-suggestions'),
            'add_new_item' => __('Add Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
            'edit_item' => __('View Feature Suggestion', 'sustainable-catalyst-feature-suggestions'),
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
            'supports' => array('title'),
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
        if ($screen && $screen->post_type === self::POST_TYPE) {
            wp_enqueue_style(
                'scfs-admin',
                plugins_url('assets/feature-suggestions-admin.css', __FILE__),
                array(),
                self::VERSION
            );
        }
    }

    private function categories() {
        return array(
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
        );
    }

    private function priorities() {
        return array('Low', 'Medium', 'High');
    }

    public function render_shortcode($atts = array()) {
        wp_enqueue_style('scfs-feature-suggestions');

        $message = '';
        $message_type = '';
        $values = $this->empty_values();

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
                <p class="scfs-eyebrow">Platform Feedback</p>
                <h3>Suggest a Sustainable Catalyst Feature</h3>
                <p>
                    Use this form to suggest new platform modules, online demo improvements, repository features,
                    data/export workflows, article map ideas, accessibility fixes, documentation improvements, or Research Librarian capabilities.
                </p>
            </div>

            <?php if (!empty($message)) : ?>
                <div class="scfs-alert scfs-alert-<?php echo esc_attr($message_type); ?>" role="status">
                    <?php echo wp_kses_post($message); ?>
                </div>
            <?php endif; ?>

            <form class="scfs-form" method="post" action="#feature-suggestion-form" novalidate>
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="scfs_form_submitted" value="1">

                <div class="scfs-honeypot" aria-hidden="true">
                    <label for="scfs_website">Website</label>
                    <input id="scfs_website" type="text" name="scfs_website" value="" tabindex="-1" autocomplete="off">
                </div>

                <div class="scfs-grid scfs-grid-two">
                    <p class="scfs-field">
                        <label for="scfs_title">Suggestion title <span aria-hidden="true">*</span></label>
                        <input id="scfs_title" name="scfs_title" type="text" required maxlength="160" value="<?php echo esc_attr($values['title']); ?>" placeholder="Example: Add CSV export to Catalyst Data demo">
                    </p>

                    <p class="scfs-field">
                        <label for="scfs_category">Suggestion category <span aria-hidden="true">*</span></label>
                        <select id="scfs_category" name="scfs_category" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($this->categories() as $category) : ?>
                                <option value="<?php echo esc_attr($category); ?>" <?php selected($values['category'], $category); ?>><?php echo esc_html($category); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <p class="scfs-field">
                    <label for="scfs_problem">What problem would this solve? <span aria-hidden="true">*</span></label>
                    <textarea id="scfs_problem" name="scfs_problem" required rows="4" maxlength="2000" placeholder="Describe the friction, missing capability, or user need."><?php echo esc_textarea($values['problem']); ?></textarea>
                </p>

                <p class="scfs-field">
                    <label for="scfs_beneficiaries">Who would benefit?</label>
                    <textarea id="scfs_beneficiaries" name="scfs_beneficiaries" rows="3" maxlength="1200" placeholder="Examples: researchers, students, NGOs, sustainability teams, content teams, public-interest builders."><?php echo esc_textarea($values['beneficiaries']); ?></textarea>
                </p>

                <p class="scfs-field">
                    <label for="scfs_suggestion">Suggested feature or improvement <span aria-hidden="true">*</span></label>
                    <textarea id="scfs_suggestion" name="scfs_suggestion" required rows="5" maxlength="3000" placeholder="Describe what you would like the platform, demo, repository, or Research Librarian to do."><?php echo esc_textarea($values['suggestion']); ?></textarea>
                </p>

                <div class="scfs-grid scfs-grid-two">
                    <p class="scfs-field">
                        <label for="scfs_relevant_url">Relevant page, demo, or repository</label>
                        <input id="scfs_relevant_url" name="scfs_relevant_url" type="text" maxlength="300" value="<?php echo esc_attr($values['relevant_url']); ?>" placeholder="Paste a URL or page name if relevant">
                    </p>

                    <p class="scfs-field">
                        <label for="scfs_priority">Priority</label>
                        <select id="scfs_priority" name="scfs_priority">
                            <?php foreach ($this->priorities() as $priority) : ?>
                                <option value="<?php echo esc_attr($priority); ?>" <?php selected($values['priority'], $priority); ?>><?php echo esc_html($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </p>
                </div>

                <div class="scfs-grid scfs-grid-two">
                    <p class="scfs-field">
                        <label for="scfs_name">Name</label>
                        <input id="scfs_name" name="scfs_name" type="text" maxlength="120" value="<?php echo esc_attr($values['name']); ?>" placeholder="Optional">
                    </p>

                    <p class="scfs-field">
                        <label for="scfs_email">Email</label>
                        <input id="scfs_email" name="scfs_email" type="email" maxlength="160" value="<?php echo esc_attr($values['email']); ?>" placeholder="Optional, for follow-up only">
                    </p>
                </div>

                <div class="scfs-checks">
                    <label class="scfs-check">
                        <input type="checkbox" name="scfs_follow_up" value="1" <?php checked($values['follow_up'], '1'); ?>>
                        <span>You may follow up with me about this suggestion.</span>
                    </label>

                    <label class="scfs-check">
                        <input type="checkbox" name="scfs_consent" value="1" required <?php checked($values['consent'], '1'); ?>>
                        <span>I understand this form is for non-confidential suggestions and does not create an obligation to implement, attribute, compensate, or respond. <strong>Do not submit confidential, proprietary, sensitive personal, medical, legal, financial, or regulated information.</strong></span>
                    </label>
                </div>

                <button class="scfs-submit" type="submit">Submit Feature Suggestion</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function empty_values() {
        return array(
            'title' => '',
            'category' => '',
            'problem' => '',
            'beneficiaries' => '',
            'suggestion' => '',
            'relevant_url' => '',
            'priority' => 'Medium',
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
        $values['relevant_url'] = isset($_POST['scfs_relevant_url']) ? sanitize_text_field(wp_unslash($_POST['scfs_relevant_url'])) : '';
        $values['priority'] = isset($_POST['scfs_priority']) ? sanitize_text_field(wp_unslash($_POST['scfs_priority'])) : 'Medium';
        $values['name'] = isset($_POST['scfs_name']) ? sanitize_text_field(wp_unslash($_POST['scfs_name'])) : '';
        $values['email'] = isset($_POST['scfs_email']) ? sanitize_email(wp_unslash($_POST['scfs_email'])) : '';
        $values['follow_up'] = isset($_POST['scfs_follow_up']) ? '1' : '';
        $values['consent'] = isset($_POST['scfs_consent']) ? '1' : '';
        return $values;
    }

    private function handle_submission() {
        $values = $this->collect_values();

        if (!empty($_POST['scfs_website'])) {
            return array(
                'success' => true,
                'message' => __('Thank you. Your suggestion has been received.', 'sustainable-catalyst-feature-suggestions'),
            );
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])), self::NONCE_ACTION)) {
            return array(
                'success' => false,
                'message' => __('Security check failed. Please refresh the page and try again.', 'sustainable-catalyst-feature-suggestions'),
                'values' => $values,
            );
        }

        $errors = array();
        if ($values['title'] === '') {
            $errors[] = __('Suggestion title is required.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['category'] === '' || !in_array($values['category'], $this->categories(), true)) {
            $errors[] = __('Please choose a valid suggestion category.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['problem'] === '') {
            $errors[] = __('Please describe the problem this would solve.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['suggestion'] === '') {
            $errors[] = __('Please describe the suggested feature or improvement.', 'sustainable-catalyst-feature-suggestions');
        }
        if (!in_array($values['priority'], $this->priorities(), true)) {
            $values['priority'] = 'Medium';
        }
        if ($values['email'] !== '' && !is_email($values['email'])) {
            $errors[] = __('Please enter a valid email address or leave it blank.', 'sustainable-catalyst-feature-suggestions');
        }
        if ($values['consent'] !== '1') {
            $errors[] = __('Please confirm the non-confidential submission notice.', 'sustainable-catalyst-feature-suggestions');
        }

        if (!empty($errors)) {
            return array(
                'success' => false,
                'message' => '<strong>Please fix the following:</strong><br>' . esc_html(implode(' ', $errors)),
                'values' => $values,
            );
        }

        $post_id = wp_insert_post(array(
            'post_type' => self::POST_TYPE,
            'post_status' => 'private',
            'post_title' => $values['title'],
            'post_content' => $values['suggestion'],
        ), true);

        if (is_wp_error($post_id)) {
            return array(
                'success' => false,
                'message' => __('Could not save the suggestion. Please try again later.', 'sustainable-catalyst-feature-suggestions'),
                'values' => $values,
            );
        }

        foreach ($values as $key => $value) {
            update_post_meta($post_id, '_scfs_' . $key, $value);
        }
        update_post_meta($post_id, '_scfs_ip_hash', $this->ip_hash());
        update_post_meta($post_id, '_scfs_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '');

        $this->send_notification($post_id, $values);

        return array(
            'success' => true,
            'message' => __('Thank you. Your feature suggestion has been received.', 'sustainable-catalyst-feature-suggestions'),
        );
    }

    private function ip_hash() {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        if ($ip === '') {
            return '';
        }
        return hash('sha256', $ip . wp_salt('nonce'));
    }

    private function send_notification($post_id, $values) {
        $to = get_option('admin_email');
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
        $body .= "Who benefits:\n" . $values['beneficiaries'] . "\n\n";
        $body .= "Suggested feature:\n" . $values['suggestion'] . "\n\n";
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
        $new['scfs_contact'] = __('Contact', 'sustainable-catalyst-feature-suggestions');
        $new['date'] = __('Date', 'sustainable-catalyst-feature-suggestions');
        return $new;
    }

    public function admin_column_content($column, $post_id) {
        if ($column === 'scfs_category') {
            echo esc_html(get_post_meta($post_id, '_scfs_category', true));
        } elseif ($column === 'scfs_priority') {
            echo esc_html(get_post_meta($post_id, '_scfs_priority', true));
        } elseif ($column === 'scfs_contact') {
            $name = get_post_meta($post_id, '_scfs_name', true);
            $email = get_post_meta($post_id, '_scfs_email', true);
            $follow = get_post_meta($post_id, '_scfs_follow_up', true) === '1' ? __('Follow-up OK', 'sustainable-catalyst-feature-suggestions') : __('No follow-up', 'sustainable-catalyst-feature-suggestions');
            echo esc_html(trim($name . ' ' . $email));
            echo '<br><small>' . esc_html($follow) . '</small>';
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
    }

    public function render_details_metabox($post) {
        $fields = array(
            'category' => 'Category',
            'priority' => 'Priority',
            'problem' => 'Problem it solves',
            'beneficiaries' => 'Who benefits',
            'suggestion' => 'Suggested feature',
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

    public function add_export_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Export Suggestions', 'sustainable-catalyst-feature-suggestions'),
            __('Export CSV', 'sustainable-catalyst-feature-suggestions'),
            'edit_posts',
            'scfs-export',
            array($this, 'render_export_page')
        );
    }

    public function render_export_page() {
        $url = wp_nonce_url(admin_url('admin-post.php?action=scfs_export_csv'), 'scfs_export_csv');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Export Feature Suggestions', 'sustainable-catalyst-feature-suggestions'); ?></h1>
            <p><?php esc_html_e('Download all feature suggestions as a CSV file for review, backlog planning, or archival.', 'sustainable-catalyst-feature-suggestions'); ?></p>
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
        fputcsv($out, array('ID', 'Date', 'Title', 'Category', 'Priority', 'Problem', 'Beneficiaries', 'Suggestion', 'Relevant URL', 'Name', 'Email', 'Follow Up Allowed'));

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
                $post->post_title,
                get_post_meta($post->ID, '_scfs_category', true),
                get_post_meta($post->ID, '_scfs_priority', true),
                get_post_meta($post->ID, '_scfs_problem', true),
                get_post_meta($post->ID, '_scfs_beneficiaries', true),
                get_post_meta($post->ID, '_scfs_suggestion', true),
                get_post_meta($post->ID, '_scfs_relevant_url', true),
                get_post_meta($post->ID, '_scfs_name', true),
                get_post_meta($post->ID, '_scfs_email', true),
                get_post_meta($post->ID, '_scfs_follow_up', true) === '1' ? 'Yes' : 'No',
            ));
        }
        fclose($out);
        exit;
    }
}

Sustainable_Catalyst_Feature_Suggestions::instance();
