<?php
/**
 * Template Name: Beauty Balance Landing
 * Template Post Type: page
 *
 * Full-width landing content framed by the active WordPress theme header and footer.
 */
$beauty_balance_asset_dir = WP_CONTENT_DIR . '/uploads/beauty-balance/';
$beauty_balance_asset_url = content_url('/uploads/beauty-balance/');
$beauty_balance_asset_version = static function (string $filename) use ($beauty_balance_asset_dir): string {
    $asset_path = $beauty_balance_asset_dir . $filename;
    if (!is_readable($asset_path)) {
        return '1.0.0';
    }
    $fingerprint = hash_file('sha256', $asset_path);
    return is_string($fingerprint) ? substr($fingerprint, 0, 16) : (string) filemtime($asset_path);
};
$beauty_balance_js_version = $beauty_balance_asset_version('beauty-balance.js');
wp_enqueue_script(
    'beauty-balance-landing',
    $beauty_balance_asset_url . 'beauty-balance.js',
    [],
    $beauty_balance_js_version,
    true
);
$beauty_balance_tailwind_version = $beauty_balance_asset_version('tailwind.css');
$beauty_balance_css_version = $beauty_balance_asset_version('beauty-balance.css');
if (have_posts()) {
    the_post();
}
ob_start();
block_template_part('header');
$beauty_balance_header_markup = ob_get_clean();
ob_start();
block_template_part('footer');
$beauty_balance_footer_markup = ob_get_clean();
$beauty_balance_page_id = get_the_ID();
$beauty_balance_meta_title = trim((string) get_post_meta($beauty_balance_page_id, '_pilloway_seo_title', true)) ?: wp_get_document_title();
$beauty_balance_meta_description = trim((string) get_post_meta($beauty_balance_page_id, '_pilloway_seo_description', true));
if ($beauty_balance_meta_description === '') {
    $beauty_balance_meta_description = preg_replace('/\s+/u', ' ', trim(wp_strip_all_tags(get_the_excerpt($beauty_balance_page_id))));
}
$beauty_balance_canonical = function_exists('wp_get_canonical_url') ? wp_get_canonical_url($beauty_balance_page_id) : get_permalink($beauty_balance_page_id);
$beauty_balance_og_image = get_the_post_thumbnail_url($beauty_balance_page_id, 'full');
$beauty_balance_og_image_alt = '';
if (!$beauty_balance_og_image) {
    $beauty_balance_source_content = (string) get_post_field('post_content', $beauty_balance_page_id);
    if (preg_match('~<img\b[^>]*>~is', $beauty_balance_source_content, $beauty_balance_image_match)) {
        $beauty_balance_image_tag = $beauty_balance_image_match[0];
        if (preg_match('~\bsrc=["\']([^"\']+)["\']~i', $beauty_balance_image_tag, $beauty_balance_src_match)) {
            $beauty_balance_image_src = html_entity_decode($beauty_balance_src_match[1], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
            $beauty_balance_og_image = preg_match('~^https?://~i', $beauty_balance_image_src)
                ? $beauty_balance_image_src
                : home_url($beauty_balance_image_src);
        }
        if (preg_match('~\balt=["\']([^"\']*)["\']~i', $beauty_balance_image_tag, $beauty_balance_alt_match)) {
            $beauty_balance_og_image_alt = html_entity_decode($beauty_balance_alt_match[1], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset'));
        }
    }
}
$beauty_balance_locale = function_exists('pll_current_language') ? (string) pll_current_language('locale') : determine_locale();
$beauty_balance_og_locale = str_replace('-', '_', $beauty_balance_locale);
if ($beauty_balance_og_locale === 'uk') {
    $beauty_balance_og_locale = 'uk_UA';
}
$beauty_balance_translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($beauty_balance_page_id) : [];
$beauty_balance_default_translation = (int) ($beauty_balance_translations['uk'] ?? $beauty_balance_translations['ua'] ?? $beauty_balance_page_id);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($beauty_balance_meta_title); ?></title>
<?php if ($beauty_balance_meta_description !== '') : ?>
<meta name="description" content="<?php echo esc_attr($beauty_balance_meta_description); ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?php echo esc_attr($beauty_balance_meta_title); ?>">
<?php if ($beauty_balance_meta_description !== '') : ?>
<meta property="og:description" content="<?php echo esc_attr($beauty_balance_meta_description); ?>">
<?php endif; ?>
<meta property="og:url" content="<?php echo esc_url($beauty_balance_canonical); ?>">
<meta property="og:locale" content="<?php echo esc_attr($beauty_balance_og_locale); ?>">
<?php foreach ($beauty_balance_translations as $beauty_balance_alt_page_id) :
    $beauty_balance_alt_locale = function_exists('pll_get_post_language') ? (string) pll_get_post_language((int) $beauty_balance_alt_page_id, 'locale') : (string) $beauty_balance_alt_language;
?>
<?php if ((int) $beauty_balance_alt_page_id !== $beauty_balance_page_id) : ?>
<meta property="og:locale:alternate" content="<?php echo esc_attr($beauty_balance_alt_locale === 'uk' ? 'uk_UA' : str_replace('-', '_', $beauty_balance_alt_locale)); ?>">
<?php endif; endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?php echo esc_url(get_permalink($beauty_balance_default_translation)); ?>">
<?php if ($beauty_balance_og_image) : ?>
<meta property="og:image" content="<?php echo esc_url($beauty_balance_og_image); ?>">
<meta property="og:image:alt" content="<?php echo esc_attr($beauty_balance_og_image_alt !== '' ? $beauty_balance_og_image_alt : $beauty_balance_meta_title); ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?php echo esc_url($beauty_balance_og_image); ?>">
<?php else : ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?php echo esc_attr($beauty_balance_meta_title); ?>">
<?php if ($beauty_balance_meta_description !== '') : ?>
<meta name="twitter:description" content="<?php echo esc_attr($beauty_balance_meta_description); ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo esc_url(add_query_arg('ver', $beauty_balance_tailwind_version, $beauty_balance_asset_url . 'tailwind.css')); ?>">
<link rel="stylesheet" href="<?php echo esc_url(add_query_arg('ver', $beauty_balance_css_version, $beauty_balance_asset_url . 'beauty-balance.css')); ?>">
<?php wp_head(); ?>
<style>
body.bb-landing .bb-landing__main {
    font-family: "Montserrat", sans-serif;
}

body.bb-landing .bb-hero-cta {
    position: relative;
    z-index: 10;
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    justify-content: center;
    min-height: 48px;
    min-width: 210px;
    max-width: 100%;
    width: fit-content;
    margin-top: 16px;
    padding: 11px 22px;
    border: 1px solid rgba(255, 255, 255, .75);
    border-radius: 999px;
    background: #d64b70;
    box-shadow: 0 8px 24px rgba(84, 22, 40, .2);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.2;
    text-decoration: none;
    text-shadow: none;
    transition: background-color .2s ease, transform .2s ease, box-shadow .2s ease;
}

body.bb-landing .bb-hero-cta:hover {
    background: #b9375a;
    box-shadow: 0 10px 28px rgba(84, 22, 40, .27);
    transform: translateY(-2px);
}

body.bb-landing .bb-product-selector {
    scroll-margin-top: 24px;
    margin-top: clamp(20px, 3vw, 44px);
    margin-bottom: clamp(28px, 4vw, 56px);
    padding: clamp(16px, 3.5vw, 40px);
    border: 1px solid #eee8e8;
    border-radius: 24px;
    background: linear-gradient(180deg, #fff 0%, #fffafb 100%);
    box-shadow: 0 18px 55px rgba(55, 35, 40, .07);
}

body.bb-landing .bb-product-selector > div:first-child {
    padding-top: 0;
}

body.bb-landing .bb-product-selector > div:first-child h2 {
    margin-bottom: 8px;
}

body.bb-landing .bb-product-selector__title {
    max-width: 920px;
    margin: 0 auto 8px;
    color: #181618;
    font-size: clamp(24px, 3.4vw, 40px);
    font-weight: 700;
    letter-spacing: -.025em;
    line-height: 1.15;
}

body.bb-landing .bb-product-selector__lead {
    max-width: 780px;
    margin: 0 auto 16px;
    color: #555;
    font-size: clamp(15px, 1.4vw, 18px);
    line-height: 1.55;
}

body.bb-landing .bb-product-selector__intro {
    margin: 0 auto 18px;
    max-width: 720px;
    color: #666;
    font-size: 15px;
    line-height: 1.6;
    text-align: center;
}

body.bb-landing .bb-product-selector .bb-product-variant {
    margin-top: 16px;
    padding: clamp(14px, 2.5vw, 28px);
    border: 1px solid #f0e8e9;
    border-radius: 20px;
    background: rgba(255, 255, 255, .94);
}

body.bb-landing .bb-product-selector .bb-product-color-grid {
    display: grid !important;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 8px !important;
    margin-bottom: 18px !important;
}

body.bb-landing .bb-product-selector .bb-product-color-card {
    min-width: 0;
    align-items: stretch;
    padding: 10px 8px 12px;
    overflow: hidden;
    border: 1px solid #f3eeee;
    border-radius: 14px;
    background: #fff;
}

body.bb-landing .bb-product-selector .bb-product-color-link {
    color: inherit;
    text-decoration: none;
    transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

body.bb-landing .bb-product-selector .bb-product-color-link:hover {
    border-color: #d64b70;
    box-shadow: 0 8px 22px rgba(105, 41, 59, .12);
    transform: translateY(-2px);
}

body.bb-landing .bb-product-selector .bb-product-color-link:focus-visible {
    outline: 3px solid #d64b70;
    outline-offset: 3px;
}

body.bb-landing .bb-product-selector .bb-product-color-image {
    max-width: 132px;
    margin: 0 auto 4px;
}

body.bb-landing .bb-product-selector .bb-product-color-label {
    display: flex;
    min-height: 2.3em;
    align-items: center;
    justify-content: center;
    overflow-wrap: anywhere;
    color: #555;
    font-size: 12px;
    line-height: 1.15;
    text-align: center;
}

body.bb-landing .bb-product-selector .bb-product-card-meta {
    display: flex;
    width: 100%;
    min-height: 78px;
    margin-top: 10px;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

body.bb-landing .bb-product-selector .bb-product-card-price {
    display: block;
    color: #201d1e;
    font-size: clamp(13px, 1.2vw, 16px);
    font-weight: 700;
    line-height: 1.3;
    text-align: center;
}

body.bb-landing .bb-product-selector .bb-product-card-price .price {
    display: inline-flex;
    flex-wrap: wrap;
    align-items: baseline;
    justify-content: center;
    gap: 3px 6px;
}

body.bb-landing .bb-product-selector .bb-product-card-price del {
    color: #8b8586;
    font-size: .84em;
    font-weight: 400;
}

body.bb-landing .bb-product-selector .bb-product-card-price ins {
    color: #201d1e;
    text-decoration: none;
}

body.bb-landing .bb-product-selector .bb-product-card-action {
    display: flex;
    width: 100%;
    min-height: 34px;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    border-radius: 999px;
    background: #d64b70;
    color: #fff;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.2;
    text-align: center;
    transition: background-color .2s ease, transform .2s ease;
}

body.bb-landing .bb-product-selector .bb-product-color-link:hover .bb-product-card-action {
    background: #b9375a;
    transform: translateY(-1px);
}

@media (min-width: 768px) {
    body.bb-landing .bb-product-selector .bb-product-color-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px !important;
    }

    body.bb-landing .bb-product-selector .bb-product-color-card {
        padding: 12px 10px 14px;
    }

    body.bb-landing .bb-product-selector .bb-product-color-label {
        font-size: 14px;
    }
}

@media (min-width: 1200px) {
    body.bb-landing .bb-product-selector .bb-product-color-grid {
        grid-template-columns: repeat(6, minmax(0, 1fr));
    }
}

@media (min-width: 768px) {
    body.bb-landing .bb-landing__main > section:first-child > div.relative {
        height: clamp(460px, 42vw, 620px);
    }
}

@media (max-width: 767px) {
    body.bb-landing .bb-landing__main > section:first-child > div.relative {
        height: auto !important;
        min-height: 400px;
    }

    body.bb-landing .bb-hero__image {
        position: static !important;
        width: 100%;
        height: auto !important;
        max-height: none;
        flex: 0 0 auto;
        object-fit: contain;
        object-position: center;
    }
}

@media (min-width: 768px) {
    body.bb-landing .bb-hero__image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        max-width: none;
        max-height: none;
        flex: 0 0 auto;
        object-fit: cover;
        object-position: 50% 10%;
    }
}
</style>
</head>
<body <?php body_class('bb-landing'); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#wp--skip-link--target"><?php esc_html_e('Перейти до вмісту', 'twentytwentyfive'); ?></a>
<div class="wp-site-blocks">
<header class="wp-block-template-part">
<?php echo $beauty_balance_header_markup; ?>
</header>
<main id="wp--skip-link--target" class="bb-landing__main">
<?php
$beauty_balance_content = apply_filters('the_content', get_the_content());
$beauty_balance_content = preg_replace(
    '~<a\b(?=[^>]*\bbb-product-buy-btn\b)[^>]*>.*?</a>~is',
    '',
    $beauty_balance_content
);

if (function_exists('wc_get_product')) {
    $beauty_balance_content_with_prices = preg_replace_callback(
        '~<a\b(?=[^>]*\bbb-product-color-card\b)(?=[^>]*\bdata-product-id=["\'](\d+)["\'])[^>]*>.*?</a>~is',
        static function (array $matches): string {
            $product = wc_get_product((int) $matches[1]);
            if (!$product) {
                return $matches[0];
            }

            $price_html = $product->get_price_html();
            if ($price_html === '') {
                return $matches[0];
            }

            $card_meta = sprintf(
                '<span class="bb-product-card-meta"><span class="bb-product-card-price">%1$s</span><span class="bb-product-card-action">%2$s</span></span>',
                wp_kses_post($price_html),
                esc_html__('View product', 'woocommerce')
            );
            $card_markup = preg_replace('~</a>\s*$~i', $card_meta . '</a>', $matches[0], 1);

            return is_string($card_markup) ? $card_markup : $matches[0];
        },
        $beauty_balance_content
    );

    if (is_string($beauty_balance_content_with_prices)) {
        $beauty_balance_content = $beauty_balance_content_with_prices;
    }
}

echo $beauty_balance_content;
$beauty_balance_lash_modal_path = __DIR__ . '/inc/beauty-balance-lash-modal.php';
if (is_readable($beauty_balance_lash_modal_path)) {
    require $beauty_balance_lash_modal_path;
}
?>
</main>
<footer class="wp-block-template-part">
<?php echo $beauty_balance_footer_markup; ?>
</footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
