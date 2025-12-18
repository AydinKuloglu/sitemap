<?php
/**
 * Plugin Name: Structured Sitemap Organizer
 * Description: Displays a sitemap that separates pages and blog posts with custom ordering controls.
 * Version: 1.0.0
 * Author: Example Author
 * Text Domain: structured-sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

class Structured_Sitemap_Organizer {
    const OPTION_KEY = 'structured_sitemap_order';

    public function __construct() {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_shortcode('structured_sitemap', [$this, 'render_shortcode']);
    }

    public function register_menu(): void {
        add_options_page(
            __('Structured Sitemap', 'structured-sitemap'),
            __('Structured Sitemap', 'structured-sitemap'),
            'manage_options',
            'structured-sitemap',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings(): void {
        register_setting(
            'structured_sitemap',
            self::OPTION_KEY,
            [
                'type' => 'array',
                'default' => [
                    'pages' => [],
                    'posts' => [],
                ],
                'sanitize_callback' => [$this, 'sanitize_order'],
            ]
        );
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'settings_page_structured-sitemap') {
            return;
        }

        wp_enqueue_style(
            'structured-sitemap-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'structured-sitemap-admin',
            plugin_dir_url(__FILE__) . 'assets/admin.js',
            ['jquery', 'jquery-ui-sortable'],
            '1.0.0',
            true
        );
    }

    public function sanitize_order($value): array {
        $order = [
            'pages' => [],
            'posts' => [],
        ];

        if (isset($value['pages'])) {
            $order['pages'] = $this->clean_id_list($value['pages']);
        }

        if (isset($value['posts'])) {
            $order['posts'] = $this->clean_id_list($value['posts']);
        }

        return $order;
    }

    public function render_shortcode(): string {
        $order = $this->get_current_order();
        $pages = $this->sort_items($this->get_pages(), $order['pages']);
        $posts = $this->sort_items($this->get_posts(), $order['posts']);

        ob_start();
        ?>
        <div class="structured-sitemap">
            <section class="structured-sitemap-section structured-sitemap-pages">
                <h2><?php esc_html_e('Pages', 'structured-sitemap'); ?></h2>
                <ul>
                    <?php foreach ($pages as $page) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($page)); ?>">
                                <?php echo esc_html(get_the_title($page)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <section class="structured-sitemap-section structured-sitemap-posts">
                <h2><?php esc_html_e('Blog Posts', 'structured-sitemap'); ?></h2>
                <ul>
                    <?php foreach ($posts as $post) : ?>
                        <li>
                            <a href="<?php echo esc_url(get_permalink($post)); ?>">
                                <?php echo esc_html(get_the_title($post)); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>
        <?php

        return (string) ob_get_clean();
    }

    public function render_settings_page(): void {
        $order = $this->get_current_order();
        $sorted_pages = $this->sort_items($this->get_pages(), $order['pages']);
        $sorted_posts = $this->sort_items($this->get_posts(), $order['posts']);
        ?>
        <div class="wrap structured-sitemap-admin">
            <h1><?php esc_html_e('Structured Sitemap', 'structured-sitemap'); ?></h1>
            <p><?php esc_html_e('Arrange the order of your pages and blog posts as they should appear in the sitemap.', 'structured-sitemap'); ?></p>
            <p><?php esc_html_e('Use the shortcode [structured_sitemap] to display the sitemap on any page or post.', 'structured-sitemap'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('structured_sitemap');
                do_settings_sections('structured_sitemap');
                ?>
                <div class="structured-sitemap-columns">
                    <div class="structured-sitemap-column">
                        <h2><?php esc_html_e('Pages', 'structured-sitemap'); ?></h2>
                        <p class="description"><?php esc_html_e('Drag to change the order pages appear in the sitemap.', 'structured-sitemap'); ?></p>
                        <ul id="structured-sitemap-pages" class="structured-sitemap-sortable">
                            <?php foreach ($sorted_pages as $page) : ?>
                                <li class="structured-sitemap-item" data-id="<?php echo esc_attr($page->ID); ?>">
                                    <?php echo esc_html(get_the_title($page)); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <input
                            type="hidden"
                            id="structured-sitemap-pages-order"
                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[pages]"
                            value="<?php echo esc_attr(implode(',', $order['pages'])); ?>"
                        />
                    </div>
                    <div class="structured-sitemap-column">
                        <h2><?php esc_html_e('Blog Posts', 'structured-sitemap'); ?></h2>
                        <p class="description"><?php esc_html_e('Drag to change the order blog posts appear in the sitemap.', 'structured-sitemap'); ?></p>
                        <ul id="structured-sitemap-posts" class="structured-sitemap-sortable">
                            <?php foreach ($sorted_posts as $post) : ?>
                                <li class="structured-sitemap-item" data-id="<?php echo esc_attr($post->ID); ?>">
                                    <?php echo esc_html(get_the_title($post)); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <input
                            type="hidden"
                            id="structured-sitemap-posts-order"
                            name="<?php echo esc_attr(self::OPTION_KEY); ?>[posts]"
                            value="<?php echo esc_attr(implode(',', $order['posts'])); ?>"
                        />
                    </div>
                </div>

                <?php submit_button(__('Save Order', 'structured-sitemap')); ?>
            </form>
        </div>
        <?php
    }

    private function clean_id_list($value): array {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_map('absint', $ids);
        $ids = array_filter($ids, static fn($id) => $id > 0);

        return array_values(array_unique($ids));
    }

    private function get_current_order(): array {
        $order = get_option(
            self::OPTION_KEY,
            [
                'pages' => [],
                'posts' => [],
            ]
        );

        if (!is_array($order)) {
            $order = [
                'pages' => [],
                'posts' => [],
            ];
        }

        return [
            'pages' => $order['pages'] ?? [],
            'posts' => $order['posts'] ?? [],
        ];
    }

    /**
     * @param array<int, WP_Post> $items
     * @param array<int>          $order_ids
     * @return array<int, WP_Post>
     */
    private function sort_items(array $items, array $order_ids): array {
        $position = array_flip($order_ids);
        $defaults = [];

        foreach ($items as $index => $item) {
            $defaults[$item->ID] = $index;
        }

        usort(
            $items,
            static function ($a, $b) use ($position, $defaults) {
                $a_order = $position[$a->ID] ?? PHP_INT_MAX;
                $b_order = $position[$b->ID] ?? PHP_INT_MAX;

                if ($a_order === $b_order) {
                    $a_default = $defaults[$a->ID] ?? PHP_INT_MAX;
                    $b_default = $defaults[$b->ID] ?? PHP_INT_MAX;

                    if ($a_default === $b_default) {
                        return strcasecmp($a->post_title, $b->post_title);
                    }

                    return $a_default <=> $b_default;
                }

                return $a_order <=> $b_order;
            }
        );

        return $items;
    }

    /**
     * @return array<int, WP_Post>
     */
    private function get_pages(): array {
        return get_pages(
            [
                'sort_column' => 'menu_order,post_title',
                'post_status' => 'publish',
            ]
        );
    }

    /**
     * @return array<int, WP_Post>
     */
    private function get_posts(): array {
        return get_posts(
            [
                'numberposts' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => 'publish',
            ]
        );
    }
}

new Structured_Sitemap_Organizer();
