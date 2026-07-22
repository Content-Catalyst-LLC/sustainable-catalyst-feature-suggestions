<?php
/**
 * Public release blackboard shortcode.
 *
 * Projects the governed canonical product registry into a compact, accessible
 * public release surface without exposing private plugin paths or repository
 * metadata. Installed WordPress versions and manual product records are
 * rendered through the same canonical registry contract.
 *
 * @package Sustainable_Catalyst_Feature_Suggestions
 */

if (!defined('ABSPATH')) {
    exit;
}

final class SCFS_Release_Board {
    const VERSION = '7.3.0';
    const SCHEMA = 'scfs-release-board/1.0';
    const SHORTCODE = 'sc_release_board';
    const STYLE_HANDLE = 'scfs-release-board';
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
        $relative = 'assets/release-board-v7.3.0.css';
        $path = plugin_dir_path(dirname(__FILE__)) . $relative;
        $version = is_file($path) ? (string) filemtime($path) : self::VERSION;
        wp_register_style(
            self::STYLE_HANDLE,
            plugins_url($relative, dirname(__FILE__)),
            array(),
            $version
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
            'layouts' => array('blackboard', 'compact', 'directory'),
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
                'inactive',
                'title',
                'heading_level',
            ),
            'canonical_registry_source' => true,
            'installed_and_manual_versions_combined' => true,
            'homepage_visibility_governed' => true,
            'private_plugin_paths_exposed' => false,
            'private_repository_metadata_exposed' => false,
            'semantic_list_output' => true,
            'color_only_status' => false,
            'cache_safe_asset_versioning' => true,
            'cache_safe_markup_ids' => true,
            'cache_ttl_seconds' => self::DEFAULT_CACHE_TTL,
        );
    }

    private function defaults() {
        return array(
            'layout' => 'blackboard',
            'context' => 'homepage',
            'groups' => '',
            'products' => '',
            'limit' => '0',
            'show_status' => 'yes',
            'show_updated' => 'yes',
            'show_links' => 'yes',
            'inactive' => 'hide',
            'title' => __('Catalyst Release Board', 'sustainable-catalyst-feature-suggestions'),
            'heading_level' => '2',
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
        $atts['layout'] = in_array($layout, array('blackboard', 'compact', 'directory'), true) ? $layout : 'blackboard';
        $atts['context'] = in_array($context, array('homepage', 'directory', 'generic'), true) ? $context : 'homepage';
        $atts['groups'] = $this->normalize_groups($atts['groups']);
        $atts['products'] = $this->csv_keys($atts['products']);
        $atts['limit'] = max(0, absint($atts['limit']));
        $atts['show_status'] = $this->yes($atts['show_status']);
        $atts['show_updated'] = $this->yes($atts['show_updated']);
        $atts['show_links'] = $this->yes($atts['show_links']);
        $atts['inactive'] = sanitize_key($atts['inactive']) === 'show' ? 'show' : 'hide';
        $atts['title'] = sanitize_text_field($atts['title']);
        $atts['heading_level'] = min(6, max(2, absint($atts['heading_level'])));
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
            return __('Version pending', 'sustainable-catalyst-feature-suggestions');
        }
        return preg_match('/^v/i', $version) ? $version : 'v' . $version;
    }

    private function last_updated($products) {
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
        if (!$timestamps) {
            return '';
        }
        $latest = max($timestamps);
        return function_exists('wp_date') ? wp_date(get_option('date_format'), $latest) : date_i18n(get_option('date_format'), $latest);
    }

    private function render_product($product, $atts, $status_labels) {
        $id = sanitize_key($product['canonical_id'] ?? 'product');
        $name = (string) (($product['short_name'] ?? '') ?: ($product['name'] ?? $id));
        $status = sanitize_key($product['status'] ?? 'unverified');
        $status_text = isset($status_labels[$status]) ? $status_labels[$status] : ucwords(str_replace('_', ' ', $status));
        $product_url = $atts['show_links'] ? $this->public_url($product['product_url'] ?? '') : '';
        $release_url = $atts['show_links'] ? $this->public_url($product['release_notes_url'] ?? '') : '';
        $version = $this->version_label($product['version'] ?? '');

        ob_start();
        ?>
        <li class="scfs-release-board__product scfs-release-board__product--<?php echo esc_attr($status); ?>" data-product="<?php echo esc_attr($id); ?>">
            <span class="scfs-release-board__name-wrap">
                <?php if ($product_url !== '') : ?>
                    <a class="scfs-release-board__name" href="<?php echo esc_url($product_url); ?>"><?php echo esc_html($name); ?></a>
                <?php else : ?>
                    <span class="scfs-release-board__name"><?php echo esc_html($name); ?></span>
                <?php endif; ?>
                <span class="scfs-release-board__leader" aria-hidden="true"></span>
            </span>
            <?php if ($release_url !== '') : ?>
                <a class="scfs-release-board__version" href="<?php echo esc_url($release_url); ?>">
                    <span class="screen-reader-text"><?php esc_html_e('Version', 'sustainable-catalyst-feature-suggestions'); ?> </span><?php echo esc_html($version); ?>
                </a>
            <?php else : ?>
                <span class="scfs-release-board__version"><span class="screen-reader-text"><?php esc_html_e('Version', 'sustainable-catalyst-feature-suggestions'); ?> </span><?php echo esc_html($version); ?></span>
            <?php endif; ?>
            <?php if ($atts['show_status']) : ?>
                <span class="scfs-release-board__status" data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html($status_text); ?></span>
            <?php endif; ?>
        </li>
        <?php
        return trim(ob_get_clean());
    }

    private function render_board($products, $atts, $instance_id) {
        $groups = $this->group_products($products);
        $families = $this->family_labels();
        $status_labels = $this->status_labels();
        $heading_tag = 'h' . $atts['heading_level'];
        $group_heading_tag = 'h' . min(6, $atts['heading_level'] + 1);
        $updated = $atts['show_updated'] ? $this->last_updated($products) : '';
        $release_archive = $atts['show_links'] ? apply_filters('scfs_release_board_release_archive_url', home_url('/support/releases/')) : '';
        $support_url = $atts['show_links'] ? apply_filters('scfs_release_board_support_url', home_url('/support/')) : '';

        ob_start();
        ?>
        <section class="scfs-release-board scfs-release-board--<?php echo esc_attr($atts['layout']); ?> scfs-release-board--<?php echo esc_attr($atts['context']); ?>" aria-labelledby="<?php echo esc_attr($instance_id); ?>" data-schema="<?php echo esc_attr(self::SCHEMA); ?>" data-version="<?php echo esc_attr(self::VERSION); ?>">
            <header class="scfs-release-board__header">
                <<?php echo tag_escape($heading_tag); ?> id="<?php echo esc_attr($instance_id); ?>" class="scfs-release-board__title"><?php echo esc_html($atts['title']); ?></<?php echo tag_escape($heading_tag); ?>>
                <p class="scfs-release-board__intro"><?php esc_html_e('Current releases across the Sustainable Catalyst platform.', 'sustainable-catalyst-feature-suggestions'); ?></p>
            </header>
            <div class="scfs-release-board__groups">
                <?php foreach ($families as $family => $label) : ?>
                    <?php if (empty($groups[$family])) { continue; } ?>
                    <?php $group_id = $instance_id . '-' . sanitize_key($family); ?>
                    <section class="scfs-release-board__group scfs-release-board__group--<?php echo esc_attr($family); ?>" aria-labelledby="<?php echo esc_attr($group_id); ?>">
                        <<?php echo tag_escape($group_heading_tag); ?> id="<?php echo esc_attr($group_id); ?>" class="scfs-release-board__group-title"><?php echo esc_html($label); ?></<?php echo tag_escape($group_heading_tag); ?>>
                        <ul class="scfs-release-board__products" role="list">
                            <?php foreach ($groups[$family] as $product) : ?>
                                <?php echo $this->render_product($product, $atts, $status_labels); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
            </div>
            <?php if ($updated !== '' || $release_archive !== '' || $support_url !== '') : ?>
                <footer class="scfs-release-board__footer">
                    <?php if ($updated !== '') : ?>
                        <p class="scfs-release-board__updated"><?php echo esc_html(sprintf(__('Last verified: %s', 'sustainable-catalyst-feature-suggestions'), $updated)); ?></p>
                    <?php endif; ?>
                    <?php if ($release_archive !== '' || $support_url !== '') : ?>
                        <nav class="scfs-release-board__links" aria-label="<?php esc_attr_e('Release board links', 'sustainable-catalyst-feature-suggestions'); ?>">
                            <?php if ($release_archive !== '') : ?><a href="<?php echo esc_url($release_archive); ?>"><?php esc_html_e('View releases', 'sustainable-catalyst-feature-suggestions'); ?></a><?php endif; ?>
                            <?php if ($support_url !== '') : ?><a href="<?php echo esc_url($support_url); ?>"><?php esc_html_e('Visit Support', 'sustainable-catalyst-feature-suggestions'); ?></a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                </footer>
            <?php endif; ?>
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
            return str_replace('__SCFS_RELEASE_BOARD_INSTANCE__', wp_unique_id('scfs-release-board-'), $cached);
        }
        $homepage_only = $atts['context'] === 'homepage';
        $products = SCFS_Canonical_Product_Registry::instance()->public_products($homepage_only);
        $products = $this->filter_products($products, $atts);
        if (!$products) {
            return '';
        }
        wp_enqueue_style(self::STYLE_HANDLE);
        $cacheable_output = $this->render_board($products, $atts, '__SCFS_RELEASE_BOARD_INSTANCE__');
        $cacheable_output = apply_filters('scfs_release_board_output', $cacheable_output, $products, $atts);
        $ttl = max(0, absint(apply_filters('scfs_release_board_cache_ttl', self::DEFAULT_CACHE_TTL, $atts)));
        if ($ttl > 0) {
            set_transient($cache_key, $cacheable_output, $ttl);
        }
        return str_replace('__SCFS_RELEASE_BOARD_INSTANCE__', wp_unique_id('scfs-release-board-'), $cacheable_output);
    }
}
