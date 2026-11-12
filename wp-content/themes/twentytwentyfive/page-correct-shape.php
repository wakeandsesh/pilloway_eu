<?php
/**
 * Template Name: Correct Shape Landing
 * Template Post Type: page
 *
 * Correct Shape landing content framed by the active WordPress header and footer.
 */
$cs_config = require __DIR__ . '/correct-shape-data.php';
$cs_page_id = get_queried_object_id();
if (have_posts()) {
    the_post();
    $cs_page_id = get_the_ID();
}
$cs_language = function_exists('pll_get_post_language') ? (string) pll_get_post_language($cs_page_id, 'slug') : 'uk';
if (!isset($cs_config['translations'][$cs_language])) {
    $cs_language = 'uk';
}
$cs_copy = $cs_config['translations'][$cs_language];
$cs_meta_title = trim((string) get_post_meta($cs_page_id, '_pilloway_seo_title', true)) ?: $cs_copy['meta_title'];
$cs_meta_description = trim((string) get_post_meta($cs_page_id, '_pilloway_seo_description', true)) ?: $cs_copy['meta_description'];
$cs_asset_dir = WP_CONTENT_DIR . '/uploads/correct-shape/';
$cs_asset_url = content_url('/uploads/correct-shape/');
$cs_preview_cache_version = substr((string) (hash_file('sha256', __FILE__) ?: filemtime(__FILE__)), 0, 12);
$cs_versioned_asset_url = static function (string $filename) use ($cs_asset_url, $cs_preview_cache_version): string {
    return add_query_arg('cs_preview', $cs_preview_cache_version, $cs_asset_url . $filename);
};
$cs_version_image_html = static function (string $html) use ($cs_preview_cache_version): string {
    return (string) preg_replace_callback(
        '/\b(src|srcset)="([^"]*)"/i',
        static function (array $matches) use ($cs_preview_cache_version): string {
            $attribute = strtolower($matches[1]);
            $value = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($attribute === 'srcset') {
                $value = implode(', ', array_map(
                    static function (string $candidate) use ($cs_preview_cache_version): string {
                        $parts = preg_split('/\s+/', trim($candidate), 2);
                        $url = add_query_arg('cs_preview', $cs_preview_cache_version, $parts[0]);
                        return $url . (isset($parts[1]) ? ' ' . $parts[1] : '');
                    },
                    explode(',', $value)
                ));
                $value = esc_attr($value);
            } else {
                $value = esc_url(add_query_arg('cs_preview', $cs_preview_cache_version, $value));
            }
            return $matches[1] . '="' . $value . '"';
        },
        $html
    );
};
$cs_asset_version = static function (string $filename) use ($cs_asset_dir): string {
    $asset_path = $cs_asset_dir . $filename;
    if (!is_readable($asset_path)) {
        return '1.0.0';
    }
    $fingerprint = hash_file('sha256', $asset_path);
    return is_string($fingerprint) ? substr($fingerprint, 0, 16) : (string) filemtime($asset_path);
};
$cs_stylesheet_version = $cs_asset_version('correct-shape.css');
$cs_product_cache = [];
$cs_product_for = static function (string $key) use ($cs_config, $cs_language, $cs_copy, $cs_version_image_html, &$cs_product_cache): ?array {
    if (array_key_exists($key, $cs_product_cache)) {
        return $cs_product_cache[$key];
    }
    $ids = $cs_config['products'][$key] ?? [];
    $product_id = (int) ($ids[$cs_language] ?? 0);
    if (!$product_id && function_exists('pll_get_post') && !empty($ids['uk'])) {
        $product_id = (int) pll_get_post((int) $ids['uk'], $cs_language);
    }
    if (!$product_id) {
        $product_id = (int) ($ids['uk'] ?? $ids['ru'] ?? 0);
    }
    $post = $product_id ? get_post($product_id) : null;
    $product = ($post && $post->post_status === 'publish' && function_exists('wc_get_product')) ? wc_get_product($product_id) : false;
    if (!$product) {
        $cs_product_cache[$key] = null;
        return $cs_product_cache[$key];
    }
    $label = (string) ($cs_copy['product_names'][$key] ?? get_the_title($product_id));
    $image_alt = $label . ' | Correct Shape';
    $image_id = (int) ($cs_config['image_overrides'][$key] ?? $product->get_image_id());
    $image_html = $image_id ? $cs_version_image_html(wp_get_attachment_image(
        $image_id,
        'large',
        false,
        [
            'class' => 'cs-product-card__image',
            'alt' => $image_alt,
            'loading' => 'lazy',
            'decoding' => 'async',
        ]
    )) : '';
    $cs_product_cache[$key] = [
        'id' => $product_id,
        'name' => $label,
        'url' => wp_make_link_relative(get_permalink($product_id)),
        'absolute_url' => get_permalink($product_id),
        'price_html' => $product->get_price_html(),
        'image_id' => $image_id,
        'image_html' => $image_html,
    ];
    return $cs_product_cache[$key];
};
$cs_render_product_card = static function (string $key) use ($cs_product_for, $cs_copy): string {
    $item = $cs_product_for($key);
    if (!$item) {
        return '';
    }
    ob_start();
    ?>
    <article class="cs-product-card" data-product-id="<?php echo esc_attr((string) $item['id']); ?>">
        <a class="cs-product-card__visual" href="<?php echo esc_url($item['url']); ?>" aria-label="<?php echo esc_attr($item['name']); ?>">
            <?php if ($item['image_html']) : ?>
                <?php echo $item['image_html']; ?>
            <?php else : ?>
                <span class="cs-product-card__placeholder" aria-hidden="true">CS</span>
            <?php endif; ?>
        </a>
        <div class="cs-product-card__body">
            <h3 class="cs-product-card__title"><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['name']); ?></a></h3>
            <p class="cs-product-card__note"><?php echo esc_html($cs_copy['product_notes'][$key] ?? ''); ?></p>
            <?php if ($item['price_html']) : ?>
                <p class="cs-product-card__price"><?php echo wp_kses_post($item['price_html']); ?></p>
            <?php endif; ?>
            <a class="cs-button cs-button--product" href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($cs_copy['buy']); ?></a>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
};
$cs_render_size_group = static function (string $group_key) use ($cs_product_for, $cs_copy): string {
    $group = $cs_copy['size_groups'][$group_key] ?? null;
    if (!is_array($group) || empty($group['options'])) {
        return '';
    }
    $items = [];
    foreach ($group['options'] as $product_key => $label) {
        $item = $cs_product_for((string) $product_key);
        if ($item) {
            $items[(string) $product_key] = ['product' => $item, 'label' => (string) $label];
        }
    }
    if (!$items) {
        return '';
    }
    $first_item = reset($items);
    $cover = $first_item['product'];
    ob_start();
    ?>
    <article class="cs-product-card cs-product-card--variants cs-product-card--group-<?php echo esc_attr(sanitize_html_class(str_replace('_', '-', $group_key))); ?>">
        <a class="cs-product-card__visual" href="<?php echo esc_url($cover['url']); ?>" aria-label="<?php echo esc_attr($group['title']); ?>">
            <?php if ($cover['image_html']) : ?>
                <?php echo $cover['image_html']; ?>
            <?php else : ?>
                <span class="cs-product-card__placeholder" aria-hidden="true">CS</span>
            <?php endif; ?>
        </a>
        <div class="cs-product-card__body">
            <h3 class="cs-product-card__title"><?php echo esc_html($group['title']); ?></h3>
            <p class="cs-product-card__note"><?php echo esc_html($group['note']); ?></p>
            <p class="cs-product-card__variant-label"><?php echo esc_html($cs_copy['choose_size']); ?></p>
            <div class="cs-product-card__variants" role="group" aria-label="<?php echo esc_attr($cs_copy['choose_size']); ?>">
                <?php foreach ($items as $item) : ?>
                    <a class="cs-size-option" data-product-id="<?php echo esc_attr((string) $item['product']['id']); ?>" href="<?php echo esc_url($item['product']['url']); ?>" aria-label="<?php echo esc_attr($group['title'] . ' — ' . $item['label']); ?>">
                        <span class="cs-size-option__label"><?php echo esc_html($item['label']); ?></span>
                        <?php if ($item['product']['price_html']) : ?>
                            <span class="cs-size-option__price"><?php echo wp_kses_post($item['product']['price_html']); ?></span>
                        <?php endif; ?>
                        <span class="cs-size-option__arrow" aria-hidden="true"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </article>
    <?php
    return (string) ob_get_clean();
};
$cs_featured_product = $cs_product_for('max_comfort');
$cs_featured_image = '';
if ($cs_featured_product && $cs_featured_product['image_id']) {
    $cs_featured_image = $cs_version_image_html(wp_get_attachment_image(
        $cs_featured_product['image_id'],
        'large',
        false,
        [
            'class' => 'cs-hero__product-image',
            'alt' => (string) $cs_copy['featured_title'] . ' | Correct Shape',
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'decoding' => 'async',
        ]
    ));
}
$cs_bundle_item_labels = [];
foreach ($cs_config['bundle_groups'] as $bundle_group) {
    foreach ($bundle_group['variants'] as $variant) {
        $cs_bundle_item_labels[$variant['product']] = trim(
            $cs_copy[$bundle_group['age_key']] . ' — ' . $cs_copy['colors'][$variant['color']]
        );
    }
}
$cs_schema_keys = [
    'max_comfort', 'model_1', 'lumbar', 'school_m1_s', 'school_m1_m', 'school_m2', 'travel_l', 'travel_xl', 'good_travel',
    ...array_keys($cs_bundle_item_labels),
];
$cs_schema_items = [];
foreach ($cs_schema_keys as $key) {
    $item = $cs_product_for($key);
    if ($item) {
        $cs_schema_items[] = [
            '@type' => 'ListItem',
            'position' => count($cs_schema_items) + 1,
            'item' => [
                '@type' => 'Product',
                'name' => $cs_bundle_item_labels[$key] ?? $cs_copy['product_names'][$key],
                'url' => $item['absolute_url'],
            ],
        ];
    }
}
$cs_json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $cs_copy['h1'],
    'numberOfItems' => count($cs_schema_items),
    'itemListElement' => $cs_schema_items,
];
$cs_canonical = function_exists('wp_get_canonical_url') ? wp_get_canonical_url($cs_page_id) : get_permalink($cs_page_id);
$cs_og_locale = ['uk' => 'uk_UA', 'ru' => 'ru_UA', 'en' => 'en_US', 'pl' => 'pl_PL', 'fr' => 'fr_FR'][$cs_language] ?? 'uk_UA';
$cs_translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($cs_page_id) : [];
$cs_default_translation = (int) ($cs_translations['uk'] ?? $cs_translations['ua'] ?? $cs_page_id);
remove_action('wp_head', '_wp_render_title_tag', 1);
remove_action('wp_head', 'rel_canonical');
ob_start();
block_template_part('header');
$cs_header_markup = ob_get_clean();
ob_start();
block_template_part('footer');
$cs_footer_markup = ob_get_clean();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($cs_meta_title); ?></title>
<meta name="description" content="<?php echo esc_attr($cs_meta_description); ?>">
<link rel="canonical" href="<?php echo esc_url($cs_canonical); ?>">
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($cs_meta_title); ?>">
<meta property="og:description" content="<?php echo esc_attr($cs_meta_description); ?>">
<meta property="og:url" content="<?php echo esc_url($cs_canonical); ?>">
<meta property="og:locale" content="<?php echo esc_attr($cs_og_locale); ?>">
<?php if ($cs_featured_product && $cs_featured_product['image_id']) : ?>
<meta property="og:image" content="<?php echo esc_url(wp_get_attachment_image_url($cs_featured_product['image_id'], 'large')); ?>">
<meta property="og:image:alt" content="<?php echo esc_attr($cs_meta_title); ?>">
<?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr($cs_meta_title); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($cs_meta_description); ?>">
<?php if ($cs_featured_product && $cs_featured_product['image_id']) : ?>
<meta name="twitter:image" content="<?php echo esc_url(wp_get_attachment_image_url($cs_featured_product['image_id'], 'large')); ?>">
<?php endif; ?>
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(get_permalink($cs_default_translation)); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<script type="application/ld+json"><?php echo esc_html(wp_json_encode($cs_json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)); ?></script>
<?php wp_head(); ?>
<link rel="stylesheet" href="<?php echo esc_url(add_query_arg(['ver' => $cs_stylesheet_version, 'cs_preview' => $cs_preview_cache_version], $cs_asset_url . 'correct-shape.css')); ?>">
</head>
<body <?php body_class('cs-landing cs-lang-' . $cs_language); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#wp--skip-link--target"><?php esc_html_e('Перейти до вмісту', 'twentytwentyfive'); ?></a>
<div class="wp-site-blocks">
<header class="wp-block-template-part">
<?php echo $cs_header_markup; ?>
</header>
<main id="wp--skip-link--target" class="cs-landing__main">
    <section class="cs-hero" aria-labelledby="cs-page-title">
        <div class="cs-hero__inner">
            <div class="cs-hero__copy">
                <img class="cs-hero__logo" src="<?php echo esc_url($cs_versioned_asset_url('correct-shape-logo.webp')); ?>" width="210" height="62" alt="Correct Shape" fetchpriority="high" decoding="async">
                <p class="cs-eyebrow"><?php echo esc_html($cs_copy['hero_eyebrow']); ?></p>
                <h1 id="cs-page-title"><?php echo esc_html($cs_copy['h1']); ?></h1>
                <p class="cs-hero__lead"><?php echo esc_html($cs_copy['hero_text']); ?></p>
                <ul class="cs-hero__benefits">
                    <?php foreach ($cs_copy['hero_benefits'] as $benefit) : ?>
                        <li><?php echo esc_html($benefit); ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="cs-hero__actions">
                    <a class="cs-button cs-button--primary" href="#cs-products"><?php echo esc_html($cs_copy['hero_primary']); ?></a>
                    <a class="cs-button cs-button--quiet" href="#cs-bundles"><?php echo esc_html($cs_copy['hero_secondary']); ?></a>
                </div>
            </div>
            <article class="cs-hero__feature">
                <div class="cs-hero__feature-copy">
                    <span class="cs-hero__feature-label"><?php echo esc_html($cs_copy['featured_label']); ?></span>
                    <h2><?php echo esc_html($cs_copy['featured_title']); ?></h2>
                </div>
                <a class="cs-hero__feature-image" href="<?php echo esc_url($cs_featured_product['url'] ?? '#cs-products'); ?>" aria-label="<?php echo esc_attr($cs_copy['featured_title']); ?>">
                    <?php if ($cs_featured_image) : ?>
                        <?php echo $cs_featured_image; ?>
                    <?php endif; ?>
                </a>
                <?php if ($cs_featured_product && $cs_featured_product['price_html']) : ?>
                    <p class="cs-hero__feature-price"><?php echo wp_kses_post($cs_featured_product['price_html']); ?></p>
                <?php endif; ?>
                <a class="cs-button cs-button--primary cs-hero__feature-cta" href="<?php echo esc_url($cs_featured_product['url'] ?? '#cs-products'); ?>"><?php echo esc_html($cs_copy['featured_cta']); ?></a>
            </article>
        </div>
    </section>

    <nav class="cs-quick-nav" aria-label="<?php echo esc_attr($cs_copy['h1']); ?>">
        <a href="#cs-products"><?php echo esc_html($cs_copy['products_heading']); ?></a>
        <a href="#cs-bundles"><?php echo esc_html($cs_copy['bundles_eyebrow']); ?></a>
        <a href="#cs-more-models"><?php echo esc_html($cs_copy['more_eyebrow']); ?></a>
    </nav>

    <section class="cs-section cs-section--products" id="cs-products" aria-labelledby="cs-products-title">
        <div class="cs-section__heading">
            <p class="cs-eyebrow"><?php echo esc_html($cs_copy['products_eyebrow']); ?></p>
            <h2 id="cs-products-title"><?php echo esc_html($cs_copy['products_heading']); ?></h2>
            <p><?php echo esc_html($cs_copy['products_lead']); ?></p>
        </div>
        <div class="cs-product-grid cs-product-grid--featured">
            <?php echo $cs_render_product_card('max_comfort'); ?>
            <?php echo $cs_render_product_card('model_1'); ?>
        </div>
    </section>

    <section class="cs-section cs-section--bundles" id="cs-bundles" aria-labelledby="cs-bundles-title">
        <div class="cs-section__heading">
            <p class="cs-eyebrow"><?php echo esc_html($cs_copy['bundles_eyebrow']); ?></p>
            <h2 id="cs-bundles-title"><?php echo esc_html($cs_copy['bundles_heading']); ?></h2>
            <p><?php echo esc_html($cs_copy['bundles_lead']); ?></p>
        </div>
        <div class="cs-bundle-grid">
            <?php foreach ($cs_config['bundle_groups'] as $bundle_group) : ?>
                <?php $cs_bundle_cover = $cs_product_for($bundle_group['cover']); ?>
                <?php if (!$cs_bundle_cover) { continue; } ?>
                <article class="cs-bundle-card">
                    <div class="cs-bundle-card__top">
                        <a class="cs-bundle-card__image" href="<?php echo esc_url($cs_bundle_cover['url']); ?>" aria-label="<?php echo esc_attr($cs_copy[$bundle_group['age_key']]); ?>">
                            <?php if ($cs_bundle_cover['image_html']) : ?>
                                <?php echo $cs_bundle_cover['image_html']; ?>
                            <?php else : ?>
                                <span class="cs-product-card__placeholder" aria-hidden="true">M1 + M2</span>
                            <?php endif; ?>
                        </a>
                        <div class="cs-bundle-card__title">
                            <h3><?php echo esc_html($cs_copy[$bundle_group['age_key']]); ?></h3>
                            <p><?php echo esc_html($cs_copy[$bundle_group['detail_key']]); ?></p>
                        </div>
                    </div>
                    <div class="cs-bundle-card__options" aria-label="<?php echo esc_attr($cs_copy['colors']['emerald'] . ', ' . $cs_copy['colors']['black'] . ', ' . $cs_copy['colors']['gray']); ?>">
                        <?php foreach ($bundle_group['variants'] as $variant) : ?>
                            <?php $cs_variant = $cs_product_for($variant['product']); ?>
                            <?php if (!$cs_variant) { continue; } ?>
                            <a class="cs-bundle-option cs-bundle-option--<?php echo esc_attr($variant['color']); ?>" data-product-id="<?php echo esc_attr((string) $cs_variant['id']); ?>" href="<?php echo esc_url($cs_variant['url']); ?>" aria-label="<?php echo esc_attr(sprintf('%s — %s', $cs_copy[$bundle_group['age_key']], $cs_copy['colors'][$variant['color']])); ?>">
                                <span class="cs-bundle-option__swatch" aria-hidden="true"></span>
                                <span class="cs-bundle-option__name"><?php echo esc_html($cs_copy['colors'][$variant['color']]); ?></span>
                                <?php if ($cs_variant['price_html']) : ?>
                                    <span class="cs-bundle-option__price"><?php echo wp_kses_post($cs_variant['price_html']); ?></span>
                                <?php endif; ?>
                                <span class="cs-bundle-option__arrow" aria-hidden="true"></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="cs-section cs-section--more" id="cs-more-models" aria-labelledby="cs-more-title">
        <div class="cs-section__heading">
            <p class="cs-eyebrow"><?php echo esc_html($cs_copy['more_eyebrow']); ?></p>
            <h2 id="cs-more-title"><?php echo esc_html($cs_copy['more_heading']); ?></h2>
        </div>
        <div class="cs-product-grid cs-product-grid--more">
            <?php foreach (['lumbar', 'school_m2'] as $cs_product_key) : ?>
                <?php echo $cs_render_product_card($cs_product_key); ?>
            <?php endforeach; ?>
            <?php echo $cs_render_size_group('school_m1'); ?>
            <?php echo $cs_render_size_group('travel'); ?>
            <?php echo $cs_render_product_card('good_travel'); ?>
        </div>
    </section>

    <section class="cs-guide" aria-labelledby="cs-guide-title">
        <div class="cs-guide__inner">
            <div class="cs-section__heading cs-section__heading--left">
                <p class="cs-eyebrow"><?php echo esc_html($cs_copy['more_eyebrow']); ?></p>
                <h2 id="cs-guide-title"><?php echo esc_html($cs_copy['guide_heading']); ?></h2>
            </div>
            <div class="cs-guide__grid">
                <?php foreach ($cs_copy['guide_items'] as $index => $guide_item) : ?>
                    <article class="cs-guide-card">
                        <span class="cs-guide-card__number" aria-hidden="true">0<?php echo esc_html((string) ($index + 1)); ?></span>
                        <h3><?php echo esc_html($guide_item[0]); ?></h3>
                        <p><?php echo esc_html($guide_item[1]); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cs-story cs-story--school" aria-labelledby="cs-school-title">
        <div class="cs-story__inner">
            <div class="cs-story__image-wrap">
                <img class="cs-story__image" src="<?php echo esc_url($cs_versioned_asset_url('school-comfort-study.jpeg')); ?>" alt="<?php echo esc_attr($cs_copy['img_school_alt']); ?>" width="887" height="1328" loading="lazy" decoding="async">
            </div>
            <div class="cs-story__copy">
                <p class="cs-eyebrow"><?php echo esc_html($cs_copy['bundles_eyebrow']); ?></p>
                <h2 id="cs-school-title"><?php echo esc_html($cs_copy['kids_heading']); ?></h2>
                <p><?php echo esc_html($cs_copy['kids_text']); ?></p>
                <a class="cs-text-link" href="#cs-bundles"><?php echo esc_html($cs_copy['hero_secondary']); ?> <span class="cs-text-link__arrow" aria-hidden="true"></span></a>
            </div>
        </div>
    </section>

    <section class="cs-info-section" aria-labelledby="cs-lumbar-title">
        <div class="cs-info-section__inner">
            <div>
                <p class="cs-eyebrow"><?php echo esc_html($cs_copy['more_eyebrow']); ?></p>
                <h2 id="cs-lumbar-title"><?php echo esc_html($cs_copy['lumbar_heading']); ?></h2>
                <p><?php echo esc_html($cs_copy['lumbar_text']); ?></p>
                <?php $cs_lumbar_product = $cs_product_for('lumbar'); ?>
                <?php if ($cs_lumbar_product) : ?>
                    <a class="cs-text-link" href="<?php echo esc_url($cs_lumbar_product['url']); ?>"><?php echo esc_html($cs_copy['product_names']['lumbar']); ?> <span class="cs-text-link__arrow" aria-hidden="true"></span></a>
                <?php endif; ?>
            </div>
            <?php if ($cs_lumbar_product && $cs_lumbar_product['image_html']) : ?>
                <a class="cs-info-section__product-image" href="<?php echo esc_url($cs_lumbar_product['url']); ?>" aria-label="<?php echo esc_attr($cs_copy['product_names']['lumbar']); ?>">
                    <?php echo $cs_lumbar_product['image_html']; ?>
                </a>
            <?php endif; ?>
        </div>
    </section>

    <section class="cs-story cs-story--travel" aria-labelledby="cs-travel-title">
        <div class="cs-story__inner">
            <div class="cs-story__copy">
                <p class="cs-eyebrow"><?php echo esc_html($cs_copy['size_groups']['travel']['title']); ?></p>
                <h2 id="cs-travel-title"><?php echo esc_html($cs_copy['travel_heading']); ?></h2>
                <p><?php echo esc_html($cs_copy['travel_text']); ?></p>
                <a class="cs-text-link" href="#cs-more-models"><?php echo esc_html($cs_copy['more_eyebrow']); ?> <span class="cs-text-link__arrow" aria-hidden="true"></span></a>
            </div>
            <div class="cs-travel-photos">
                <img src="<?php echo esc_url($cs_versioned_asset_url('travel-rest-1.jpeg')); ?>" alt="<?php echo esc_attr($cs_copy['img_travel_alt']); ?>" width="887" height="592" loading="lazy" decoding="async">
                <img src="<?php echo esc_url($cs_versioned_asset_url('travel-rest-2.jpeg')); ?>" alt="<?php echo esc_attr($cs_copy['img_good_travel_alt']); ?>" width="887" height="592" loading="lazy" decoding="async">
            </div>
        </div>
    </section>

    <section class="cs-faq" aria-labelledby="cs-faq-title">
        <div class="cs-faq__inner">
            <div class="cs-section__heading cs-section__heading--left">
                <p class="cs-eyebrow">Correct Shape</p>
                <h2 id="cs-faq-title"><?php echo esc_html($cs_copy['faq_heading']); ?></h2>
            </div>
            <div class="cs-faq__items">
                <?php foreach ($cs_copy['faq_items'] as $faq_item) : ?>
                    <details class="cs-faq__item">
                        <summary><?php echo esc_html($faq_item[0]); ?></summary>
                        <p><?php echo esc_html($faq_item[1]); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

</main>
<footer class="wp-block-template-part">
<?php echo $cs_footer_markup; ?>
</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
