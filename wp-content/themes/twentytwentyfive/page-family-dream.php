<?php
/**
 * Template Name: Family Dream Landing
 * Template Post Type: page
 *
 * Localized Family Dream landing page with current WooCommerce products.
 */
$fd_page_id = get_queried_object_id();
if (have_posts()) {
    the_post();
    $fd_page_id = get_the_ID();
}
$fd_locales = require __DIR__ . '/family-dream-data.php';
$fd_language = function_exists('pll_get_post_language') ? (string) pll_get_post_language($fd_page_id, 'slug') : 'uk';
if (!isset($fd_locales['locales'][$fd_language])) {
    $fd_language = 'uk';
}
$fd_copy = $fd_locales['locales'][$fd_language];
$fd_meta_title = trim((string) get_post_meta($fd_page_id, '_pilloway_seo_title', true)) ?: $fd_copy['meta_title'];
$fd_meta_description = trim((string) get_post_meta($fd_page_id, '_pilloway_seo_description', true)) ?: $fd_copy['meta_description'];
$fd_asset_dir = WP_CONTENT_DIR . '/uploads/family-dream/';
$fd_asset_url = content_url('/uploads/family-dream/');
$fd_asset_version = static function (string $filename) use ($fd_asset_dir): string {
    $path = $fd_asset_dir . $filename;
    if (!is_readable($path)) {
        return '1.0.0';
    }
    $hash = hash_file('sha256', $path);
    return is_string($hash) ? substr($hash, 0, 16) : (string) filemtime($path);
};
$fd_css_version = $fd_asset_version('family-dream.css');
$fd_image = static function (string $filename, string $alt, int $width, int $height, bool $priority = false) use ($fd_asset_url, $fd_asset_version): string {
    $url = add_query_arg('ver', $fd_asset_version($filename), $fd_asset_url . $filename);
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" width="' . esc_attr((string) $width) . '" height="' . esc_attr((string) $height) . '" ' . ($priority ? 'fetchpriority="high"' : 'loading="lazy"') . ' decoding="async">';
};

// Find current published UK Family Dream pillows from the live catalogue; no product IDs or prices are embedded in the landing page.
global $wpdb;
$fd_product_ids = $wpdb->get_col(
    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' AND post_title LIKE '%Family Dream%' ORDER BY ID DESC LIMIT 250"
);
$fd_products_by_size = [];
$fd_color_keys = [
    'olive' => ['темна олива', 'темная олива', 'dark olive'],
    'cappuccino' => ['капучино', 'cappuccino'],
    'mint' => ['м’ята', 'мята', 'mięta', 'mint'],
    'terracotta' => ['теракотов', 'терракотов', 'terrakot', 'terracotta'],
    'milk' => ['молочн', 'mleczny', 'milk'],
    'white' => ['білий', 'белый', 'biały', 'white'],
    'black' => ['чорний', 'черный', 'czarny', 'black'],
];
foreach ($fd_product_ids as $fd_id) {
    $fd_id = (int) $fd_id;
    $fd_title = html_entity_decode(wp_strip_all_tags(get_the_title($fd_id)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (stripos($fd_title, 'Family Dream') === false || preg_match('/наволоч|poszewk|pillowcase/iu', $fd_title)) {
        continue;
    }
    if (function_exists('pll_get_post_language') && pll_get_post_language($fd_id, 'slug') !== 'uk') {
        continue;
    }
    if (!preg_match('/\b(XXS|XS|XL|S|M|L)\b/i', $fd_title, $fd_size_match)) {
        continue;
    }
    $fd_size = strtoupper($fd_size_match[1]);
    $fd_product = function_exists('wc_get_product') ? wc_get_product($fd_id) : false;
    if (!$fd_product || !$fd_product->is_in_stock()) {
        continue;
    }
    $fd_title_lc = function_exists('mb_strtolower') ? mb_strtolower($fd_title, 'UTF-8') : strtolower($fd_title);
    $fd_color = 'other';
    foreach ($fd_color_keys as $fd_color_key => $fd_markers) {
        foreach ($fd_markers as $fd_marker) {
            $fd_marker_lc = function_exists('mb_strtolower') ? mb_strtolower($fd_marker, 'UTF-8') : strtolower($fd_marker);
            if (strpos($fd_title_lc, $fd_marker_lc) !== false) {
                $fd_color = $fd_color_key;
                break 2;
            }
        }
    }
    if (!isset($fd_products_by_size[$fd_size][$fd_color])) {
        $fd_products_by_size[$fd_size][$fd_color] = ['id' => $fd_id, 'product' => $fd_product, 'color' => $fd_color];
    }
}
$fd_size_order = ['XXS', 'XS', 'S', 'M', 'L', 'XL'];
$fd_color_order = ['olive', 'cappuccino', 'mint', 'terracotta', 'milk', 'white', 'black', 'other'];
$fd_cards = [];
foreach ($fd_size_order as $fd_size) {
    if (empty($fd_products_by_size[$fd_size])) {
        continue;
    }
    $fd_variants = [];
    foreach ($fd_color_order as $fd_color) {
        if (empty($fd_products_by_size[$fd_size][$fd_color])) {
            continue;
        }
        $fd_variant = $fd_products_by_size[$fd_size][$fd_color];
        $fd_target_id = $fd_variant['id'];
        if ($fd_language !== 'uk' && function_exists('pll_get_post')) {
            $fd_translation_id = (int) pll_get_post($fd_target_id, $fd_language);
            if ($fd_translation_id > 0) {
                $fd_target_id = $fd_translation_id;
            }
        }
        $fd_target_product = function_exists('wc_get_product') ? wc_get_product($fd_target_id) : false;
        if (!$fd_target_product) {
            $fd_target_id = $fd_variant['id'];
            $fd_target_product = $fd_variant['product'];
        }
        $fd_image_id = (int) $fd_target_product->get_image_id();
        $fd_image_html = $fd_image_id ? wp_get_attachment_image($fd_image_id, 'medium_large', false, [
            'class' => 'fd-product-card__image',
            'alt' => $fd_copy['h1'] . ' — ' . $fd_size,
            'loading' => 'lazy',
            'decoding' => 'async',
        ]) : '';
        $fd_variants[] = [
            'id' => $fd_target_id,
            'color' => $fd_color,
            'url' => get_permalink($fd_target_id),
            'name' => (string) ($fd_copy['color_names'][$fd_color] ?? $fd_copy['color_names']['other']),
            'price_html' => $fd_target_product->get_price_html(),
            'image_html' => $fd_image_html,
            'product' => $fd_target_product,
        ];
    }
    if ($fd_variants) {
        $fd_cards[] = ['size' => $fd_size, 'variants' => $fd_variants];
    }
}
$fd_schema_items = [];
foreach ($fd_cards as $fd_card) {
    $fd_variant = reset($fd_card['variants']);
    $fd_schema_items[] = [
        '@type' => 'ListItem',
        'position' => count($fd_schema_items) + 1,
        'name' => 'Family Dream ' . $fd_card['size'],
        'url' => $fd_variant['url'],
    ];
}
$fd_json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $fd_copy['h1'],
    'numberOfItems' => count($fd_schema_items),
    'itemListElement' => $fd_schema_items,
];
$fd_canonical = function_exists('wp_get_canonical_url') ? wp_get_canonical_url($fd_page_id) : get_permalink($fd_page_id);
$fd_og_locale = ['uk' => 'uk_UA', 'ru' => 'ru_UA', 'en' => 'en_US', 'pl' => 'pl_PL', 'fr' => 'fr_FR'][$fd_language] ?? 'uk_UA';
$fd_translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($fd_page_id) : [];
$fd_default_translation = (int) ($fd_translations['uk'] ?? $fd_translations['ua'] ?? $fd_page_id);
remove_action('wp_head', '_wp_render_title_tag', 1);
remove_action('wp_head', 'rel_canonical');
ob_start();
block_template_part('header');
$fd_header_markup = ob_get_clean();
ob_start();
block_template_part('footer');
$fd_footer_markup = ob_get_clean();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($fd_meta_title); ?></title>
<meta name="description" content="<?php echo esc_attr($fd_meta_description); ?>">
<link rel="canonical" href="<?php echo esc_url($fd_canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($fd_meta_title); ?>">
<meta property="og:description" content="<?php echo esc_attr($fd_meta_description); ?>">
<meta property="og:url" content="<?php echo esc_url($fd_canonical); ?>">
<meta property="og:locale" content="<?php echo esc_attr($fd_og_locale); ?>">
<meta property="og:image" content="<?php echo esc_url($fd_asset_url . 'FD_037.jpg'); ?>">
<meta property="og:image:alt" content="<?php echo esc_attr($fd_copy['alt_hero']); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr($fd_meta_title); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($fd_meta_description); ?>">
<meta name="twitter:image" content="<?php echo esc_url($fd_asset_url . 'FD_037.jpg'); ?>">
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(get_permalink($fd_default_translation)); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<script type="application/ld+json"><?php echo esc_html(wp_json_encode($fd_json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?></script>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url(add_query_arg('ver', $fd_css_version, $fd_asset_url . 'family-dream.css')); ?>">
</head>
<body <?php body_class('fd-landing fd-lang-' . $fd_language); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#fd-main"><?php echo esc_html($fd_copy['skip']); ?></a>
<div class="wp-site-blocks">
<header class="wp-block-template-part"><?php echo $fd_header_markup; ?></header>
<main id="fd-main" class="fd-landing__main">
    <section class="fd-hero" aria-labelledby="fd-title">
        <div class="fd-container fd-hero__grid">
            <div class="fd-hero__copy">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['eyebrow']); ?></p>
                <h1 id="fd-title"><?php echo esc_html($fd_copy['h1']); ?></h1>
                <p class="fd-hero__lead"><?php echo esc_html($fd_copy['hero_text']); ?></p>
                <ul class="fd-hero__benefits">
                    <?php foreach ($fd_copy['benefits'] as $fd_benefit) : ?><li><?php echo esc_html($fd_benefit); ?></li><?php endforeach; ?>
                </ul>
                <div class="fd-hero__actions">
                    <a class="fd-button fd-button--primary" href="#fd-products"><?php echo esc_html($fd_copy['choose']); ?></a>
                    <a class="fd-button fd-button--secondary" href="#fd-zones"><?php echo esc_html($fd_copy['hero_secondary']); ?></a>
                </div>
            </div>
            <figure class="fd-hero__visual">
                <?php echo $fd_image('FD_037.jpg', $fd_copy['alt_hero'], 1500, 1500, true); ?>
            </figure>
        </div>
    </section>

    <section id="fd-products" class="fd-products" aria-labelledby="fd-products-title">
        <div class="fd-container">
            <header class="fd-section-heading">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['products_eyebrow']); ?></p>
                <h2 id="fd-products-title"><?php echo esc_html($fd_copy['products_heading']); ?></h2>
                <p><?php echo esc_html($fd_copy['products_lead']); ?></p>
            </header>
            <?php if ($fd_cards) : ?>
            <div class="fd-product-grid">
                <?php foreach ($fd_cards as $fd_card) : $fd_default_variant = reset($fd_card['variants']); ?>
                <article class="fd-product-card">
                    <a class="fd-product-card__visual" href="<?php echo esc_url($fd_default_variant['url']); ?>" aria-label="<?php echo esc_attr($fd_copy['h1'] . ' — ' . $fd_card['size']); ?>">
                        <?php echo $fd_default_variant['image_html']; ?>
                    </a>
                    <div class="fd-product-card__body">
                        <div class="fd-product-card__heading">
                            <h3>Family Dream <span><?php echo esc_html($fd_card['size']); ?></span></h3>
                            <p><?php echo esc_html($fd_copy['size_guidance'][$fd_card['size']] ?? ''); ?></p>
                        </div>
                        <div class="fd-product-card__price">
                            <span><?php echo esc_html($fd_copy['price_label']); ?></span>
                            <?php echo wp_kses_post($fd_default_variant['price_html']); ?>
                        </div>
                        <p class="fd-product-card__color-label"><?php echo esc_html($fd_copy['colors_label']); ?></p>
                        <div class="fd-color-options">
                            <?php foreach ($fd_card['variants'] as $fd_variant) : ?>
                            <a class="fd-color-option" href="<?php echo esc_url($fd_variant['url']); ?>" aria-label="<?php echo esc_attr($fd_copy['h1'] . ' ' . $fd_card['size'] . ' — ' . $fd_variant['name']); ?>">
                                <span class="fd-color-option__swatch fd-color-option__swatch--<?php echo esc_attr($fd_variant['color']); ?>" aria-hidden="true"></span>
                                <span class="fd-color-option__name"><?php echo esc_html($fd_variant['name']); ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
            <?php else : ?>
            <p class="fd-empty-state"><?php echo esc_html($fd_copy['products_lead']); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section id="fd-zones" class="fd-zones" aria-labelledby="fd-zones-title">
        <div class="fd-container fd-zones__grid">
            <div class="fd-zones__visual"><?php echo $fd_image('FD_002.jpg', $fd_copy['alt_zones'], 1795, 1795); ?></div>
            <div class="fd-zones__copy">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['zones_eyebrow']); ?></p>
                <h2 id="fd-zones-title"><?php echo esc_html($fd_copy['zones_heading']); ?></h2>
                <p class="fd-section-lead"><?php echo esc_html($fd_copy['zones_lead']); ?></p>
                <ol class="fd-zone-list">
                    <?php foreach ($fd_copy['zones'] as $fd_index => $fd_zone) : ?>
                    <li><span><?php echo esc_html((string) ($fd_index + 1)); ?></span><?php echo esc_html($fd_zone); ?></li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </div>
    </section>

    <section class="fd-sleep" aria-labelledby="fd-sleep-title">
        <div class="fd-container">
            <header class="fd-section-heading">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['sleep_eyebrow']); ?></p>
                <h2 id="fd-sleep-title"><?php echo esc_html($fd_copy['sleep_heading']); ?></h2>
            </header>
            <div class="fd-sleep__grid">
                <figure><div class="fd-sleep__image"><?php echo $fd_image('IMG_4634.jpg', $fd_copy['alt_back'], 2362, 1575); ?></div><figcaption><?php echo esc_html($fd_copy['sleep_back']); ?></figcaption></figure>
                <figure><div class="fd-sleep__image"><?php echo $fd_image('IMG_4645.jpg', $fd_copy['alt_side'], 5300, 3534); ?></div><figcaption><?php echo esc_html($fd_copy['sleep_side']); ?></figcaption></figure>
            </div>
        </div>
    </section>

    <section class="fd-foam" aria-labelledby="fd-foam-title">
        <div class="fd-container fd-foam__grid">
            <figure class="fd-foam__visual"><?php echo $fd_image('memory-foam-w.jpg', $fd_copy['foam_heading'], 429, 242); ?></figure>
            <div class="fd-foam__copy">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['foam_eyebrow']); ?></p>
                <h2 id="fd-foam-title"><?php echo esc_html($fd_copy['foam_heading']); ?></h2>
                <p><?php echo esc_html($fd_copy['foam_text']); ?></p>
            </div>
        </div>
    </section>

    <section class="fd-covers" aria-labelledby="fd-covers-title">
        <div class="fd-container fd-covers__grid">
            <div class="fd-covers__copy">
                <p class="fd-eyebrow"><?php echo esc_html($fd_copy['covers_eyebrow']); ?></p>
                <h2 id="fd-covers-title"><?php echo esc_html($fd_copy['covers_heading']); ?></h2>
                <ul>
                    <li><?php echo esc_html($fd_copy['inner_cover']); ?></li>
                    <li><?php echo esc_html($fd_copy['outer_cover']); ?></li>
                </ul>
                <h3><?php echo esc_html($fd_copy['interlock_heading']); ?></h3>
                <p><?php echo esc_html($fd_copy['interlock_text']); ?></p>
            </div>
            <div class="fd-covers__visual" role="group" aria-label="<?php echo esc_attr($fd_copy['alt_covers']); ?>">
                <?php echo $fd_image('FD_L01.png', $fd_copy['alt_covers'], 1226, 936); ?>
                <?php echo $fd_image('FD_L02.png', $fd_copy['alt_covers'], 1196, 940); ?>
            </div>
        </div>
    </section>
</main>
<footer class="wp-block-template-part"><?php echo $fd_footer_markup; ?></footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
