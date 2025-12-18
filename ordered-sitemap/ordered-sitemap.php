<?php
/**
 * Plugin Name: Ordered Sitemap
 * Description: Sayfa ve yazıları ayrı başlıklarla listeleyen ve sıralama seçenekleri sunan site haritası eklentisi.
 * Version: 1.0.0
 * Author: Sitemap Toolkit
 * Text Domain: ordered-sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Ordered_Sitemap_Plugin')) {
    class Ordered_Sitemap_Plugin
    {
        private const OPTION_PAGE_ORDER = 'ordered_sitemap_page_order';
        private const OPTION_POST_ORDER = 'ordered_sitemap_post_order';

        public function __construct()
        {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_shortcode('ordered_sitemap', [$this, 'render_shortcode']);
        }

        public function add_settings_page(): void
        {
            add_options_page(
                __('Ordered Sitemap', 'ordered-sitemap'),
                __('Ordered Sitemap', 'ordered-sitemap'),
                'manage_options',
                'ordered-sitemap',
                [$this, 'render_settings_page']
            );
        }

        public function register_settings(): void
        {
            register_setting(
                'ordered_sitemap_settings',
                self::OPTION_PAGE_ORDER,
                [
                    'type' => 'string',
                    'sanitize_callback' => [$this, 'sanitize_order_option'],
                    'default' => 'menu_asc',
                ]
            );

            register_setting(
                'ordered_sitemap_settings',
                self::OPTION_POST_ORDER,
                [
                    'type' => 'string',
                    'sanitize_callback' => [$this, 'sanitize_order_option'],
                    'default' => 'date_desc',
                ]
            );

            add_settings_section(
                'ordered_sitemap_section',
                __('Sıralama Ayarları', 'ordered-sitemap'),
                [$this, 'render_settings_description'],
                'ordered_sitemap_settings'
            );

            add_settings_field(
                self::OPTION_PAGE_ORDER,
                __('Sayfa sıralaması', 'ordered-sitemap'),
                [$this, 'render_page_order_field'],
                'ordered_sitemap_settings',
                'ordered_sitemap_section'
            );

            add_settings_field(
                self::OPTION_POST_ORDER,
                __('Yazı sıralaması', 'ordered-sitemap'),
                [$this, 'render_post_order_field'],
                'ordered_sitemap_settings',
                'ordered_sitemap_section'
            );
        }

        public function render_settings_description(): void
        {
            echo '<p>' . esc_html__(
                'Site haritasındaki listelerin nasıl sıralanacağını seçin. Değerler kısa kod ile de değiştirilebilir.',
                'ordered-sitemap'
            ) . '</p>';
        }

        public function render_page_order_field(): void
        {
            $this->render_order_field(self::OPTION_PAGE_ORDER);
        }

        public function render_post_order_field(): void
        {
            $this->render_order_field(self::OPTION_POST_ORDER);
        }

        private function render_order_field(string $option_name): void
        {
            $current = get_option($option_name, 'title_asc');
            ?>
            <select name="<?php echo esc_attr($option_name); ?>">
                <?php foreach ($this->order_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php
        }

        public function render_settings_page(): void
        {
            if (!current_user_can('manage_options')) {
                return;
            }
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('Ordered Sitemap', 'ordered-sitemap'); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('ordered_sitemap_settings');
                    do_settings_sections('ordered_sitemap_settings');
                    submit_button(__('Ayarları Kaydet', 'ordered-sitemap'));
                    ?>
                </form>
                <hr />
                <h2><?php echo esc_html__('Kullanım', 'ordered-sitemap'); ?></h2>
                <p>
                    <?php echo esc_html__(
                        'Site haritasını göstermek için [ordered_sitemap] kısa kodunu kullanın. İsterseniz page_order ve post_order parametreleriyle ayarları geçersiz kılabilirsiniz.',
                        'ordered-sitemap'
                    ); ?>
                </p>
                <code>[ordered_sitemap page_order="title_asc" post_order="date_desc"]</code>
            </div>
            <?php
        }

        public function render_shortcode($atts): string
        {
            $atts = shortcode_atts(
                [
                    'page_order' => get_option(self::OPTION_PAGE_ORDER, 'menu_asc'),
                    'post_order' => get_option(self::OPTION_POST_ORDER, 'date_desc'),
                ],
                $atts,
                'ordered_sitemap'
            );

            $page_order = $this->sanitize_order_option($atts['page_order']);
            $post_order = $this->sanitize_order_option($atts['post_order']);

            $pages = $this->fetch_items('page', $page_order);
            $posts = $this->fetch_items('post', $post_order);

            ob_start();
            ?>
            <div class="ordered-sitemap">
                <?php if (!empty($pages)) : ?>
                    <div class="ordered-sitemap__section ordered-sitemap__pages">
                        <h2><?php echo esc_html__('Sayfalar', 'ordered-sitemap'); ?></h2>
                        <ul>
                            <?php foreach ($pages as $page) : ?>
                                <li>
                                    <a href="<?php echo esc_url(get_permalink($page)); ?>">
                                        <?php echo esc_html(get_the_title($page)); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (!empty($posts)) : ?>
                    <div class="ordered-sitemap__section ordered-sitemap__posts">
                        <h2><?php echo esc_html__('Blog Yazıları', 'ordered-sitemap'); ?></h2>
                        <ul>
                            <?php foreach ($posts as $post) : ?>
                                <li>
                                    <a href="<?php echo esc_url(get_permalink($post)); ?>">
                                        <?php echo esc_html(get_the_title($post)); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <?php if (empty($pages) && empty($posts)) : ?>
                    <p><?php echo esc_html__('Gösterilecek içerik bulunamadı.', 'ordered-sitemap'); ?></p>
                <?php endif; ?>
            </div>
            <?php

            return (string) ob_get_clean();
        }

        private function sanitize_order_option(string $value): string
        {
            $allowed = array_keys($this->order_options());

            if (in_array($value, $allowed, true)) {
                return $value;
            }

            return 'title_asc';
        }

        /**
        * @return WP_Post[]
        */
        private function fetch_items(string $post_type, string $order_key): array
        {
            $order_args = $this->map_order_option($order_key, $post_type);

            $query = new WP_Query(
                [
                    'post_type' => $post_type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'orderby' => $order_args['orderby'],
                    'order' => $order_args['order'],
                    'no_found_rows' => true,
                    'update_post_term_cache' => false,
                    'update_post_meta_cache' => false,
                ]
            );

            return $query->posts;
        }

        private function map_order_option(string $order_key, string $post_type): array
        {
            switch ($order_key) {
                case 'menu_asc':
                    return [
                        'orderby' => $post_type === 'page' ? 'menu_order title' : 'title',
                        'order' => 'ASC',
                    ];
                case 'title_desc':
                    return ['orderby' => 'title', 'order' => 'DESC'];
                case 'date_asc':
                    return ['orderby' => 'date', 'order' => 'ASC'];
                case 'date_desc':
                    return ['orderby' => 'date', 'order' => 'DESC'];
                case 'title_asc':
                default:
                    return ['orderby' => 'title', 'order' => 'ASC'];
            }
        }

        /**
         * @return array<string, string>
         */
        private function order_options(): array
        {
            return [
                'menu_asc' => __('Menü sırasına göre (A → Z)', 'ordered-sitemap'),
                'title_asc' => __('Başlığa göre (A → Z)', 'ordered-sitemap'),
                'title_desc' => __('Başlığa göre (Z → A)', 'ordered-sitemap'),
                'date_desc' => __('Yayın tarihine göre (Yeni → Eski)', 'ordered-sitemap'),
                'date_asc' => __('Yayın tarihine göre (Eski → Yeni)', 'ordered-sitemap'),
            ];
        }
    }
}

new Ordered_Sitemap_Plugin();
