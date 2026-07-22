<?php
/**
 * Public release console shortcode.
 *
 * Projects the governed canonical product registry into a sleek, accessible
 * terminal-style release surface without exposing private plugin paths or
 * repository metadata. Installed WordPress versions and manual product records
 * are rendered through the same canonical registry contract.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Release_Board {
    const VERSION = '7.3.3';
    const SCHEMA = 'scfs-release-board/1.2';
    const SHORTCODE = 'sc_release_board';
    const STYLE_HANDLE = 'scfs-release-board';
    const SCRIPT_HANDLE = 'scfs-release-console';
    const CACHE_EPOCH_OPTION = 'scfs_release_board_cache_epoch';
    const DEFAULT_CACHE_TTL = 300;

    private static $instance = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_shortcode(self::SHORTCODE, array($this, 'render_shortcode'));
        add_action('scfs_product_registry_updated', array($this, 'invalidate_cache'));
        add_action('scfs_installed_plugin_discovery_completed', array($this, 'invalidate_cache'));
    }

    public static function activate() {
        if (!get_option(self::CACHE_EPOCH_OPTION)) {
            add_option(self::CACHE_EPOCH_OPTION, '1', '', false);
        }
    }

    public function register_assets() {
        $relative = 'assets/release-board-v7.3.3.css';
        $path = plugin_dir_path(dirname(__FILE__)) . $relative;
        $version = is_file($path) ? (string) filemtime($path) : self::VERSION;
        wp_register_style(
            self::STYLE_HANDLE,
            plugins_url($relative, dirname(__FILE__)),
            array(),
            $version
        );
        $script_relative = 'assets/release-console-v7.3.3.js';
        $script_path = plugin_dir_path(dirname(__FILE__)) . $script_relative;
        $script_version = is_file($script_path) ? (string) filemtime($script_path) : self::VERSION;
        wp_register_script(
            self::SCRIPT_HANDLE,
            plugins_url($script_relative, dirname(__FILE__)),
            array(),
            $script_version,
            true
        );
    }

    public function invalidate_cache() {
        $epoch = absint(get_option(self::CACHE_EPOCH_OPTION, 1));
        update_option(self::CACHE_EPOCH_OPTION, (string) ($epoch + 1), false);
    }

    public function schema_record() {
        return array(
            'schema' => self::SCHEMA,
            'version' => self::VERSION,
            'shortcode' => self::SHORTCODE,
            'public_title' => 'Release Console',
            'layouts' => array('terminal', 'blackboard', 'compact', 'directory'),
            'default_layout' => 'terminal',
            'contexts' => array('homepage', 'directory', 'generic'),
            'group_aliases' => array_keys($this->group_aliases()),
            'attributes' => array(
                'layout',
                'context',
                'groups',
                'products',
                'limit',
                'show_status',
                'show_updated',
                'show_links',
                'show_header',
                'show_footer',
                'show_source',
                'dense',
                'inactive',
                'title',
                'heading_level',
                'interval',
                'rotate',
            ),
            'canonical_registry_source' => true,
            'installed_and_manual_versions_combined' => true,
            'homepage_visibility_governed' => true,
            'knowledge_library_homepage_required' => true,
            'analytics_r_public_label' => 'Analytics R',
            'terminal_command_header' => true,
            'registry_source_counts' => true,
            'rotating_screens' => array('foundation', 'research-intelligence', 'data-analysis', 'creation-systems', 'commercial'),
            'default_interval_seconds' => 7,
            'previous_pause_next_controls' => true,
            'pause_on_hover_and_focus' => true,
            'reduced_motion_respected' => true,
            'product_labels_navigating' => false,
            'footer_links_only' => true,
            'private_plugin_paths_exposed' => false,
            'private_repository_metadata_exposed' => false,
            'semantic_list_output' => true,
            'color_only_status' => false,
            'cache_safe_asset_versioning' => true,
            'cache_safe_markup_ids' => true,
            'stable_screen_height' => true,
            'controls_hidden_without_javascript' => true,
            'all_screens_visible_without_javascript' => true,
            'multiple_instances_supported' => true,
            'duplicate_initialization_guard' => true,
            'dynamic_dom_initialization' => true,
            'keyboard_navigation' => array('ArrowLeft', 'ArrowRight', 'Home', 'End', 'Space'),
            'screen_reader_announcements_manual_only' => true,
            'astra_theme_scoped' => true,
            'minification_safe' => true,
            'cache_ttl_seconds' => self::DEFAULT_CACHE_TTL,
        );
    }

    private function defaults() {
        return array(
            'layout' => 'terminal',
            'context' => 'homepage',
            'groups' => '',
            'products' => '',
            'limit' => '0',
            'show_status' => 'yes',
            'show_updated' => 'yes',
            'show_links' => 'yes',
            'show_header' => 'yes',
            'show_footer' => 'yes',
            'show_source' => 'yes',
            'dense' => 'yes',
            'inactive' => 'hide',
            'title' => __('Release Console', 'sustainable-catalyst-feature-suggestions'),
            'heading_level' => '2',
            'interval' => '7',
            'rotate' => 'yes',
        );
    }

    private function yes($value) {
        return in_array(strtolower(trim((string) $value)), array('1', 'yes', 'true', 'on'), true);
    }

    private function csv_keys($value) {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[\s,]+/', (string) $value);
        }
        return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $parts))));
    }

    private function group_aliases() {
        return array(
            'foundation' => 'foundation',
            'research' => 'research-intelligence',
            'intelligence' => 'research-intelligence',
            'research-intelligence' => 'research-intelligence',
            'data' => 'data-analysis',
            'analysis' => 'data-analysis',
            'data-analysis' => 'data-analysis',
            'systems' => 'creation-systems',
            'creation' => 'creation-systems',
            'creation-systems' => 'creation-systems',
            'commercial' => 'commercial',
        );
    }

    private function normalize_groups($value) {
        $aliases = $this->group_aliases();
        $groups = array();
        foreach ($this->csv_keys($value) as $key) {
            if (isset($aliases[$key])) {
                $groups[] = $aliases[$key];
            }
        }
        return array_values(array_unique($groups));
    }

    private function normalize_atts($atts) {
        $atts = shortcode_atts($this->defaults(), (array) $atts, self::SHORTCODE);
        $atts = apply_filters('scfs_release_board_shortcode_atts', $atts);
        $layout = sanitize_key($atts['layout']);
        $context = sanitize_key($atts['context']);
        $layouts = array('terminal', 'blackboard', 'compact', 'directory');
        $atts['layout'] = in_array($layout, $layouts, true) ? $layout : 'terminal';
        $atts['context'] = in_array($context, array('homepage', 'directory', 'generic'), true) ? $context : 'homepage';
        $atts['groups'] = $this->normalize_groups($atts['groups']);
        $atts['products'] = $this->csv_keys($atts['products']);
        $atts['limit'] = max(0, absint($atts['limit']));
        $atts['show_status'] = $this->yes($atts['show_status']);
        $atts['show_updated'] = $this->yes($atts['show_updated']);
        $atts['show_links'] = $this->yes($atts['show_links']);
        $atts['show_header'] = $this->yes($atts['show_header']);
        $atts['show_footer'] = $this->yes($atts['show_footer']);
        $atts['show_source'] = $this->yes($atts['show_source']);
        $atts['dense'] = $this->yes($atts['dense']);
        $atts['inactive'] = sanitize_key($atts['inactive']) === 'show' ? 'show' : 'hide';
        $atts['title'] = sanitize_text_field($atts['title']);
        $atts['heading_level'] = min(6, max(2, absint($atts['heading_level'])));
        $atts['interval'] = min(60, max(3, absint($atts['interval'])));
        $atts['rotate'] = $this->yes($atts['rotate']);
        return $atts;
    }

    private function cache_key($atts) {
        $epoch = (string) get_option(self::CACHE_EPOCH_OPTION, '1');
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
        return 'scfs_rb_' . md5(wp_json_encode(array($epoch, $locale, $blog_id, $atts)));
    }

    private function filter_products($products, $atts) {
        $wanted_products = array_flip($atts['products']);
        $wanted_groups = array_flip($atts['groups']);
        $filtered = array();
        foreach ((array) $products as $product) {
            $id = sanitize_key($product['canonical_id'] ?? '');
            $family = sanitize_key($product['family'] ?? '');
            $status = sanitize_key($product['status'] ?? 'unverified');
            if ($id === '') {
                continue;
            }
            if ($wanted_products && !isset($wanted_products[$id])) {
                continue;
            }
            if ($wanted_groups && !isset($wanted_groups[$family])) {
                continue;
            }
            if ($atts['inactive'] === 'hide' && $status === 'inactive') {
                continue;
            }
            $filtered[] = $product;
        }
        usort($filtered, function ($left, $right) {
            $order = absint($left['display_order'] ?? 999) <=> absint($right['display_order'] ?? 999);
            if ($order !== 0) {
                return $order;
            }
            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });
        if ($atts['limit'] > 0) {
            $filtered = array_slice($filtered, 0, $atts['limit']);
        }
        return apply_filters('scfs_release_board_products', $filtered, $atts);
    }

    private function group_products($products) {
        $groups = array();
        foreach ((array) $products as $product) {
            $family = sanitize_key($product['family'] ?? 'foundation');
            if (!isset($groups[$family])) {
                $groups[$family] = array();
            }
            $groups[$family][] = $product;
        }
        return $groups;
    }

    private function family_labels() {
        $labels = class_exists('SCFS_Canonical_Product_Registry')
            ? SCFS_Canonical_Product_Registry::instance()->families()
            : array();
        return apply_filters('scfs_release_board_family_labels', $labels);
    }

    private function status_labels() {
        return class_exists('SCFS_Canonical_Product_Registry')
            ? SCFS_Canonical_Product_Registry::instance()->statuses()
            : array();
    }

    private function public_url($url) {
        $url = trim((string) $url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '/') === 0 && function_exists('home_url')) {
            return home_url($url);
        }
        return esc_url_raw($url);
    }

    private function version_label($version) {
        $version = trim((string) $version);
        if ($version === '') {
            return __('pending', 'sustainable-catalyst-feature-suggestions');
        }
        return preg_match('/^v/i', $version) ? $version : 'v' . $version;
    }

    private function source_label($source) {
        $source = sanitize_key($source);
        if ($source === 'wordpress_plugin') {
            return __('plugin', 'sustainable-catalyst-feature-suggestions');
        }
        if ($source === 'manual') {
            return __('manual', 'sustainable-catalyst-feature-suggestions');
        }
        return str_replace('_', '-', $source ?: 'registry');
    }

    private function last_updated_timestamp($products) {
        $timestamps = array();
        foreach ((array) $products as $product) {
            $candidate = trim((string) ($product['last_verified_at'] ?? ''));
            if ($candidate !== '') {
                $time = strtotime($candidate);
                if ($time) {
                    $timestamps[] = $time;
                }
            }
        }
        if (class_exists('SCFS_Installed_Plugin_Discovery')) {
            $snapshot = SCFS_Installed_Plugin_Discovery::instance()->snapshot();
            $time = strtotime((string) ($snapshot['scanned_at'] ?? ''));
            if ($time) {
                $timestamps[] = $time;
            }
        }
        return $timestamps ? max($timestamps) : 0;
    }

    private function last_updated($products) {
        $latest = $this->last_updated_timestamp($products);
        if (!$latest) {
            return '';
        }
        return function_exists('wp_date') ? wp_date(get_option('date_format') . ' · ' . get_option('time_format'), $latest) : date_i18n(get_option('date_format') . ' · ' . get_option('time_format'), $latest);
    }

    private function telemetry_counts($products) {
        $counts = array(
            'total' => 0,
            'plugin' => 0,
            'manual' => 0,
            'other' => 0,
            'attention' => 0,
        );
        foreach ((array) $products as $product) {
            $counts['total']++;
            $source = sanitize_key($product['version_source'] ?? 'manual');
            if ($source === 'wordpress_plugin') {
                $counts['plugin']++;
            } elseif ($source === 'manual') {
                $counts['manual']++;
            } else {
                $counts['other']++;
            }
            $status = sanitize_key($product['status'] ?? 'unverified');
            if (!in_array($status, array('current', 'stable'), true)) {
                $counts['attention']++;
            }
        }
        return $counts;
    }

    private function discovery_state() {
        $state = array(
            'label' => __('registry online', 'sustainable-catalyst-feature-suggestions'),
            'status' => 'online',
            'matched' => 0,
            'pending' => 0,
        );
        if (!class_exists('SCFS_Installed_Plugin_Discovery')) {
            return $state;
        }
        $summary = SCFS_Installed_Plugin_Discovery::instance()->summary_record();
        $state['matched'] = absint($summary['matched_product_count'] ?? 0);
        $state['pending'] = absint($summary['pending_candidate_count'] ?? 0);
        if (!empty($summary['diagnostic_errors'])) {
            $state['label'] = __('registry attention', 'sustainable-catalyst-feature-suggestions');
            $state['status'] = 'attention';
        } elseif (empty($summary['scanned_at'])) {
            $state['label'] = __('registry ready', 'sustainable-catalyst-feature-suggestions');
            $state['status'] = 'ready';
        }
        return $state;
    }

    private function render_product($product, $atts, $status_labels) {
        $id = sanitize_key($product['canonical_id'] ?? 'product');
        $name = (string) (($product['short_name'] ?? '') ?: ($product['name'] ?? $id));
        $status = sanitize_key($product['status'] ?? 'unverified');
        $status_text = isset($status_labels[$status]) ? $status_labels[$status] : ucwords(str_replace('_', ' ', $status));
        $source = sanitize_key($product['version_source'] ?? 'manual');
        $version = $this->version_label($product['version'] ?? '');

        ob_start();
        ?>
        <li class="scfs-release-board__product scfs-release-board__product--<?php echo esc_attr($status); ?>" data-product="<?php echo esc_attr($id); ?>" data-source="<?php echo esc_attr($source); ?>">
            <span class="scfs-release-board__prompt" aria-hidden="true">&gt;</span>
            <span class="scfs-release-board__name-wrap">
                <span class="scfs-release-board__name" data-console-label="true"><?php echo esc_html($name); ?></span>
                <span class="scfs-release-board__leader" aria-hidden="true"></span>
            </span>
            <span class="scfs-release-board__version"><span class="screen-reader-text"><?php esc_html_e('Version', 'sustainable-catalyst-feature-suggestions'); ?> </span><?php echo esc_html($version); ?></span>
            <?php if ($atts['show_status']) : ?>
                <span class="scfs-release-board__status" data-status="<?php echo esc_attr($status); ?>"><span class="scfs-release-board__status-dot" aria-hidden="true"></span><?php echo esc_html($status_text); ?></span>
            <?php endif; ?>
            <?php if ($atts['show_source']) : ?>
                <span class="scfs-release-board__source"><span class="screen-reader-text"><?php esc_html_e('Version source:', 'sustainable-catalyst-feature-suggestions'); ?> </span><?php echo esc_html($this->source_label($source)); ?></span>
            <?php endif; ?>
        </li>
        <?php
        return trim(ob_get_clean());
    }

    private function render_terminal_header($atts, $instance_id, $counts, $updated, $discovery) {
        $heading_tag = 'h' . $atts['heading_level'];
        ob_start();
        ?>
        <header class="scfs-release-board__header scfs-release-board__header--terminal">
            <div class="scfs-release-board__command-line">
                <span class="scfs-release-board__command-prompt" aria-hidden="true">$</span>
                <span class="scfs-release-board__command">sc releases --scope=<?php echo esc_html($atts['context']); ?> --source=registry</span>
                <span class="scfs-release-board__registry-state" data-state="<?php echo esc_attr($discovery['status']); ?>"><span aria-hidden="true"></span><?php echo esc_html($discovery['label']); ?></span>
            </div>
            <<?php echo tag_escape($heading_tag); ?> id="<?php echo esc_attr($instance_id); ?>" class="scfs-release-board__title"><?php echo esc_html($atts['title']); ?></<?php echo tag_escape($heading_tag); ?>>
            <p class="scfs-release-board__intro"><?php esc_html_e('Five governed release screens rotating across the Sustainable Catalyst platform.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            <div class="scfs-release-board__meta" role="list" aria-label="<?php esc_attr_e('Release console summary', 'sustainable-catalyst-feature-suggestions'); ?>">
                <span role="listitem"><b><?php esc_html_e('systems', 'sustainable-catalyst-feature-suggestions'); ?></b><?php echo esc_html($counts['total']); ?></span>
                <?php if ($atts['show_source']) : ?>
                    <span role="listitem"><b><?php esc_html_e('plugin', 'sustainable-catalyst-feature-suggestions'); ?></b><?php echo esc_html($counts['plugin']); ?></span>
                    <span role="listitem"><b><?php esc_html_e('manual', 'sustainable-catalyst-feature-suggestions'); ?></b><?php echo esc_html($counts['manual']); ?></span>
                <?php endif; ?>
                <?php if ($discovery['matched'] > 0) : ?><span role="listitem"><b><?php esc_html_e('matched', 'sustainable-catalyst-feature-suggestions'); ?></b><?php echo esc_html($discovery['matched']); ?></span><?php endif; ?>
                <?php if ($updated !== '') : ?><span role="listitem"><b><?php esc_html_e('last sync', 'sustainable-catalyst-feature-suggestions'); ?></b><?php echo esc_html($updated); ?></span><?php endif; ?>
            </div>
        </header>
        <?php
        return trim(ob_get_clean());
    }

    private function render_standard_header($atts, $instance_id) {
        $heading_tag = 'h' . $atts['heading_level'];
        ob_start();
        ?>
        <header class="scfs-release-board__header">
            <<?php echo tag_escape($heading_tag); ?> id="<?php echo esc_attr($instance_id); ?>" class="scfs-release-board__title"><?php echo esc_html($atts['title']); ?></<?php echo tag_escape($heading_tag); ?>>
            <p class="scfs-release-board__intro"><?php esc_html_e('Current releases across the Sustainable Catalyst platform.', 'sustainable-catalyst-feature-suggestions'); ?></p>
        </header>
        <?php
        return trim(ob_get_clean());
    }

    private function render_footer($atts, $counts, $updated, $release_archive, $support_url) {
        if (!$atts['show_footer']) {
            return '';
        }
        ob_start();
        ?>
        <footer class="scfs-release-board__footer">
            <p class="scfs-release-board__diagnostics">
                <span><?php echo esc_html(sprintf(_n('%d system', '%d systems', $counts['total'], 'sustainable-catalyst-feature-suggestions'), $counts['total'])); ?></span>
                <span><?php echo esc_html(sprintf(__('%d plugin-sourced', 'sustainable-catalyst-feature-suggestions'), $counts['plugin'])); ?></span>
                <span><?php echo esc_html(sprintf(__('%d manually governed', 'sustainable-catalyst-feature-suggestions'), $counts['manual'])); ?></span>
                <?php if ($counts['attention'] > 0) : ?><span><?php echo esc_html(sprintf(__('%d non-stable', 'sustainable-catalyst-feature-suggestions'), $counts['attention'])); ?></span><?php endif; ?>
            </p>
            <?php if ($atts['show_updated'] && $updated !== '') : ?>
                <p class="scfs-release-board__updated"><?php echo esc_html(sprintf(__('Last verified: %s', 'sustainable-catalyst-feature-suggestions'), $updated)); ?></p>
            <?php endif; ?>
            <?php if ($release_archive !== '' || $support_url !== '') : ?>
                <nav class="scfs-release-board__links" aria-label="<?php esc_attr_e('Release Console links', 'sustainable-catalyst-feature-suggestions'); ?>">
                    <?php if ($release_archive !== '') : ?><a href="<?php echo esc_url($release_archive); ?>">./<?php esc_html_e('releases', 'sustainable-catalyst-feature-suggestions'); ?></a><?php endif; ?>
                    <?php if ($support_url !== '') : ?><a href="<?php echo esc_url($support_url); ?>">./<?php esc_html_e('support', 'sustainable-catalyst-feature-suggestions'); ?></a><?php endif; ?>
                </nav>
            <?php endif; ?>
        </footer>
        <?php
        return trim(ob_get_clean());
    }

    private function render_board($products, $atts, $instance_id) {
        $groups = $this->group_products($products);
        $families = $this->family_labels();
        $status_labels = $this->status_labels();
        $group_heading_tag = 'h' . min(6, $atts['heading_level'] + 1);
        $updated = $atts['show_updated'] ? $this->last_updated($products) : '';
        $counts = $this->telemetry_counts($products);
        $discovery = $this->discovery_state();
        $release_archive = $atts['show_links'] ? apply_filters('scfs_release_board_release_archive_url', home_url('/support/releases/')) : '';
        $support_url = $atts['show_links'] ? apply_filters('scfs_release_board_support_url', home_url('/support/')) : '';
        $rotating = $atts['layout'] === 'terminal' && $atts['rotate'];
        $screens_id = $instance_id . '-screens';
        $announcer_id = $instance_id . '-announcer';
        $classes = array(
            'scfs-release-board',
            'scfs-release-board--' . $atts['layout'],
            'scfs-release-board--' . $atts['context'],
        );
        if ($atts['dense']) {
            $classes[] = 'scfs-release-board--dense';
        }
        if ($rotating) {
            $classes[] = 'scfs-release-board--rotating';
        }
        $screen_total = 0;
        foreach ($families as $family => $label) {
            if (!empty($groups[$family])) {
                $screen_total++;
            }
        }

        ob_start();
        ?>
        <section class="<?php echo esc_attr(implode(' ', $classes)); ?>" aria-labelledby="<?php echo esc_attr($instance_id); ?>" data-schema="<?php echo esc_attr(self::SCHEMA); ?>" data-version="<?php echo esc_attr(self::VERSION); ?>" data-release-console="<?php echo $rotating ? 'true' : 'false'; ?>" data-interval="<?php echo esc_attr($atts['interval'] * 1000); ?>">
            <?php if ($atts['show_header']) : ?>
                <?php echo $atts['layout'] === 'terminal' ? $this->render_terminal_header($atts, $instance_id, $counts, $updated, $discovery) : $this->render_standard_header($atts, $instance_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php else : ?>
                <span id="<?php echo esc_attr($instance_id); ?>" class="screen-reader-text"><?php echo esc_html($atts['title']); ?></span>
            <?php endif; ?>
            <?php if ($rotating) : ?>
                <div class="scfs-release-board__console-controls" aria-label="<?php esc_attr_e('Release Console controls', 'sustainable-catalyst-feature-suggestions'); ?>">
                    <button type="button" class="scfs-release-board__control" data-console-action="previous" aria-controls="<?php echo esc_attr($screens_id); ?>" aria-label="<?php esc_attr_e('Previous release screen', 'sustainable-catalyst-feature-suggestions'); ?>"><span data-console-icon aria-hidden="true">&#8592;</span><span><?php esc_html_e('Previous', 'sustainable-catalyst-feature-suggestions'); ?></span></button>
                    <button type="button" class="scfs-release-board__control" data-console-action="toggle" aria-controls="<?php echo esc_attr($screens_id); ?>" aria-pressed="false" aria-label="<?php esc_attr_e('Pause release screens', 'sustainable-catalyst-feature-suggestions'); ?>"><span data-console-icon aria-hidden="true">&#10074;&#10074;</span><span data-console-label><?php esc_html_e('Pause', 'sustainable-catalyst-feature-suggestions'); ?></span></button>
                    <span class="scfs-release-board__screen-status" aria-hidden="true"><span data-console-current>1</span>/<span><?php echo esc_html($screen_total); ?></span><span class="scfs-release-board__screen-status-label" data-console-current-label></span></span>
                    <button type="button" class="scfs-release-board__control" data-console-action="next" aria-controls="<?php echo esc_attr($screens_id); ?>" aria-label="<?php esc_attr_e('Next release screen', 'sustainable-catalyst-feature-suggestions'); ?>"><span><?php esc_html_e('Next', 'sustainable-catalyst-feature-suggestions'); ?></span><span data-console-icon aria-hidden="true">&#8594;</span></button>
                </div>
                <span id="<?php echo esc_attr($announcer_id); ?>" class="screen-reader-text" data-console-announcer aria-live="polite" aria-atomic="true"></span>
                <noscript><p class="scfs-release-board__noscript"><?php esc_html_e('Rotation controls require JavaScript. All release groups are shown.', 'sustainable-catalyst-feature-suggestions'); ?></p></noscript>
            <?php endif; ?>
            <div class="scfs-release-board__groups"<?php if ($rotating) : ?> id="<?php echo esc_attr($screens_id); ?>" data-console-screens role="region" aria-describedby="<?php echo esc_attr($announcer_id); ?>" aria-label="<?php esc_attr_e('Release Console screens. Use Left and Right Arrow keys to navigate, Home for the first screen, End for the last screen, and Space to pause or play.', 'sustainable-catalyst-feature-suggestions'); ?>"<?php endif; ?>>
                <?php $screen_index = 0; ?>
                <?php foreach ($families as $family => $label) : ?>
                    <?php if (empty($groups[$family])) { continue; } ?>
                    <?php $group_id = $instance_id . '-' . sanitize_key($family); ?>
                    <section class="scfs-release-board__group scfs-release-board__group--<?php echo esc_attr($family); ?>" aria-labelledby="<?php echo esc_attr($group_id); ?>"<?php if ($rotating) : ?> data-console-screen data-console-title="<?php echo esc_attr($label); ?>" data-screen-index="<?php echo esc_attr($screen_index); ?>" role="group" aria-roledescription="<?php esc_attr_e('release screen', 'sustainable-catalyst-feature-suggestions'); ?>" aria-label="<?php echo esc_attr(sprintf(__('%1$s, screen %2$d of %3$d', 'sustainable-catalyst-feature-suggestions'), $label, $screen_index + 1, $screen_total)); ?>"<?php endif; ?>>
                        <<?php echo tag_escape($group_heading_tag); ?> id="<?php echo esc_attr($group_id); ?>" class="scfs-release-board__group-title"><span aria-hidden="true">#</span> <?php echo esc_html($label); ?></<?php echo tag_escape($group_heading_tag); ?>>
                        <div class="scfs-release-board__column-labels" aria-hidden="true"><span>system</span><span>version</span><span>state</span><?php if ($atts['show_source']) : ?><span>source</span><?php endif; ?></div>
                        <ul class="scfs-release-board__products" role="list">
                            <?php foreach ($groups[$family] as $product) : ?>
                                <?php echo $this->render_product($product, $atts, $status_labels); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                    <?php $screen_index++; ?>
                <?php endforeach; ?>
            </div>
            <?php echo $this->render_footer($atts, $counts, $updated, $release_archive, $support_url); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </section>
        <?php
        return trim(ob_get_clean());
    }

    public function render_shortcode($atts = array()) {
        if (!class_exists('SCFS_Canonical_Product_Registry')) {
            return '';
        }
        $atts = $this->normalize_atts($atts);
        $cache_key = $this->cache_key($atts);
        $cached = get_transient($cache_key);
        if (is_string($cached) && $cached !== '') {
            wp_enqueue_style(self::STYLE_HANDLE);
            if ($atts['layout'] === 'terminal' && $atts['rotate']) { wp_enqueue_script(self::SCRIPT_HANDLE); }
            return str_replace('__SCFS_RELEASE_BOARD_INSTANCE__', wp_unique_id('scfs-release-board-'), $cached);
        }
        $homepage_only = $atts['context'] === 'homepage';
        $products = SCFS_Canonical_Product_Registry::instance()->public_products($homepage_only);
        $products = $this->filter_products($products, $atts);
        if (!$products) {
            return '';
        }
        wp_enqueue_style(self::STYLE_HANDLE);
        if ($atts['layout'] === 'terminal' && $atts['rotate']) { wp_enqueue_script(self::SCRIPT_HANDLE); }
        $cacheable_output = $this->render_board($products, $atts, '__SCFS_RELEASE_BOARD_INSTANCE__');
        $cacheable_output = apply_filters('scfs_release_board_output', $cacheable_output, $products, $atts);
        $ttl = max(0, absint(apply_filters('scfs_release_board_cache_ttl', self::DEFAULT_CACHE_TTL, $atts)));
        if ($ttl > 0) {
            set_transient($cache_key, $cacheable_output, $ttl);
        }
        return str_replace('__SCFS_RELEASE_BOARD_INSTANCE__', wp_unique_id('scfs-release-board-'), $cacheable_output);
    }
}
