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
        private const OPTION_CUSTOM_CATEGORIES = 'ordered_sitemap_custom_categories';

        public function __construct()
        {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
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

            register_setting(
                'ordered_sitemap_settings',
                self::OPTION_CUSTOM_CATEGORIES,
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_custom_categories'],
                    'default' => [],
                ]
            );

            add_settings_field(
                self::OPTION_CUSTOM_CATEGORIES,
                __('Kategori başlıkları ve sırası', 'ordered-sitemap'),
                [$this, 'render_custom_categories_field'],
                'ordered_sitemap_settings',
                'ordered_sitemap_section'
            );
        }

        public function enqueue_admin_assets(string $hook): void
        {
            if ($hook !== 'settings_page_ordered-sitemap') {
                return;
            }

            wp_enqueue_style('dashicons');
            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script('ordered-sitemap-admin', '', ['jquery', 'jquery-ui-sortable'], false, true);

            $options = [
                'categories_html' => $this->get_categories_options_html(),
                'delete_label' => __('Sil', 'ordered-sitemap'),
                'placeholder' => __('Kategori adı', 'ordered-sitemap'),
            ];

            wp_localize_script('ordered-sitemap-admin', 'OrderedSitemapData', $options);

            $inline_script = <<<'JS'
jQuery(function ($) {
    const list = $("#ordered-sitemap-categories-list");
    const input = $("#ordered-sitemap-categories-input");
    const addButton = $("#ordered-sitemap-add-category");

    function serializeList() {
        const data = [];
        list.find("li").each(function () {
            const label = $(this).find(".ordered-sitemap-cat-label").val();
            const term = $(this).find(".ordered-sitemap-cat-term").val();
            if (label && label.trim().length > 0) {
                data.push({ label: label.trim(), term_id: parseInt(term, 10) || 0 });
            }
        });
        input.val(JSON.stringify(data));
    }

    function addItem(item = { label: "", term_id: 0 }) {
        const row = $(
            '<li class="ordered-sitemap-row">' +
            '<span class="ordered-sitemap-handle dashicons dashicons-move" aria-hidden="true"></span>' +
            '<input type="text" class="regular-text ordered-sitemap-cat-label" placeholder="' + OrderedSitemapData.placeholder + '" />' +
            '<select class="ordered-sitemap-cat-term">' + OrderedSitemapData.categories_html + '</select>' +
            '<button type="button" class="button-link-delete ordered-sitemap-remove">' + OrderedSitemapData.delete_label + '</button>' +
            '</li>'
        );
        row.find(".ordered-sitemap-cat-label").val(item.label || "");
        row.find(".ordered-sitemap-cat-term").val(String(item.term_id || 0));
        list.append(row);
    }

    const initial = input.val();
    if (initial) {
        try {
            const parsed = JSON.parse(initial);
            parsed.forEach((item) => addItem(item));
        } catch (e) {
            addItem();
        }
    } else {
        addItem();
    }

    addButton.on("click", function (e) {
        e.preventDefault();
        addItem();
    });

    list.on("click", ".ordered-sitemap-remove", function () {
        $(this).closest("li").remove();
        serializeList();
    });

    list.on("change input", "input, select", serializeList);

    list.sortable({
        handle: ".ordered-sitemap-handle",
        update: serializeList,
    });

    serializeList();
});
JS;

            wp_add_inline_script('ordered-sitemap-admin', $inline_script);

            $inline_style = '.ordered-sitemap-categories-wrapper { margin-top: 12px; }
                #ordered-sitemap-categories-list { margin: 0; padding: 0; }
                #ordered-sitemap-categories-list li { list-style: none; margin: 8px 0; display: flex; gap: 8px; align-items: center; }
                .ordered-sitemap-handle { cursor: move; }
                .ordered-sitemap-cat-label { flex: 1 1 auto; }
                .ordered-sitemap-cat-term { min-width: 160px; }
                .ordered-sitemap-remove { color: #b32d2e; }';

            wp_register_style('ordered-sitemap-admin-style', false);
            wp_enqueue_style('ordered-sitemap-admin-style');
            wp_add_inline_style('ordered-sitemap-admin-style', $inline_style);
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
            $custom_categories = $this->sanitize_custom_categories(get_option(self::OPTION_CUSTOM_CATEGORIES, []));
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__('Ordered Sitemap', 'ordered-sitemap'); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('ordered_sitemap_settings');
                    do_settings_sections('ordered_sitemap_settings');
                    submit_button(__('Ayarları Kaydet', 'ordered-sitemap'));
                    ?>
                    <input type="hidden" id="ordered-sitemap-categories-input" name="<?php echo esc_attr(self::OPTION_CUSTOM_CATEGORIES); ?>" value='<?php echo esc_attr(wp_json_encode($custom_categories)); ?>' data-placeholder="<?php echo esc_attr__('Kategori adı', 'ordered-sitemap'); ?>" />
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
            $custom_categories = $this->sanitize_custom_categories(get_option(self::OPTION_CUSTOM_CATEGORIES, []));

            $categorized_posts = [];
            $collected_ids = [];

            foreach ($custom_categories as $category) {
                if (empty($category['term_id'])) {
                    continue;
                }

                $items = $this->fetch_items('post', $post_order, [
                    'tax_query' => [
                        [
                            'taxonomy' => 'category',
                            'field' => 'term_id',
                            'terms' => [(int) $category['term_id']],
                        ],
                    ],
                ]);

                $categorized_posts[] = [
                    'label' => $category['label'],
                    'posts' => $items,
                ];

                foreach ($items as $item) {
                    $collected_ids[] = $item->ID;
                }
            }

            $remaining_posts = $this->fetch_items('post', $post_order, [
                'post__not_in' => array_unique($collected_ids),
            ]);

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
                <?php if (!empty($categorized_posts) || !empty($remaining_posts)) : ?>
                    <div class="ordered-sitemap__section ordered-sitemap__posts">
                        <h2><?php echo esc_html__('Blog Yazıları', 'ordered-sitemap'); ?></h2>

                        <?php foreach ($categorized_posts as $group) : ?>
                            <?php if (empty($group['posts'])) {
                                continue;
                            } ?>
                            <h3><?php echo esc_html($group['label']); ?></h3>
                            <ul>
                                <?php foreach ($group['posts'] as $post) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_permalink($post)); ?>">
                                            <?php echo esc_html(get_the_title($post)); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endforeach; ?>

                        <?php if (!empty($remaining_posts)) : ?>
                            <h3><?php echo esc_html__('Diğer Yazılar', 'ordered-sitemap'); ?></h3>
                            <ul>
                                <?php foreach ($remaining_posts as $post) : ?>
                                    <li>
                                        <a href="<?php echo esc_url(get_permalink($post)); ?>">
                                            <?php echo esc_html(get_the_title($post)); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (empty($pages) && empty($categorized_posts) && empty($remaining_posts)) : ?>
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
        private function fetch_items(string $post_type, string $order_key, array $extra_args = []): array
        {
            $order_args = $this->map_order_option($order_key, $post_type);

            $query = new WP_Query(
                array_merge(
                    [
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'orderby' => $order_args['orderby'],
                        'order' => $order_args['order'],
                        'no_found_rows' => true,
                        'update_post_term_cache' => false,
                        'update_post_meta_cache' => false,
                    ],
                    $extra_args
                )
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

        /**
         * @param mixed $value
         * @return array<int, array{label: string, term_id: int}>
         */
        public function sanitize_custom_categories($value): array
        {
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $value = $decoded;
                }
            }

            if (!is_array($value)) {
                return [];
            }

            $sanitized = [];

            foreach ($value as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
                $term_id = isset($item['term_id']) ? absint($item['term_id']) : 0;

                if ($label === '') {
                    continue;
                }

                $sanitized[] = [
                    'label' => $label,
                    'term_id' => $term_id,
                ];
            }

            return $sanitized;
        }

        public function render_custom_categories_field(): void
        {
            echo '<p>' . esc_html__('Kendi başlıklarınızı oluşturup kategorileri sürükle-bırak ile sıralayın.', 'ordered-sitemap') . '</p>';
            echo '<div class="ordered-sitemap-categories-wrapper">';
            echo '<ul id="ordered-sitemap-categories-list">';
            echo '</ul>';
            echo '<button type="button" class="button" id="ordered-sitemap-add-category">' . esc_html__('Kategori ekle', 'ordered-sitemap') . '</button>';
            echo '</div>';
        }

        private function get_categories_options_html(): string
        {
            $terms = get_terms(
                [
                    'taxonomy' => 'category',
                    'hide_empty' => false,
                ]
            );

            $options_html = '';
            if (!is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $options_html .= sprintf(
                        '<option value="%1$d">%2$s</option>',
                        (int) $term->term_id,
                        esc_html($term->name)
                    );
                }
            }

            return $options_html;
        }
    }
}

new Ordered_Sitemap_Plugin();
