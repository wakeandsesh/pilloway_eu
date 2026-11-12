<?php
/**
 * Beauty Balance LASH product chooser.
 *
 * Products and prices are read from WooCommerce at render time. Polylang keeps
 * the list in the language currently shown to the shopper.
 */
if (!function_exists('wc_get_products')) {
    return;
}

$current_language = function_exists('pll_current_language')
    ? (string) pll_current_language('slug')
    : '';

$lash_products = wc_get_products([
    'status'  => 'publish',
    'limit'   => -1,
    'search'  => 'LASH',
    'orderby' => 'title',
    'order'   => 'ASC',
]);

$lash_groups = [];
foreach ($lash_products as $lash_product) {
    if (!$lash_product instanceof WC_Product || !$lash_product->is_visible()) {
        continue;
    }

    $lash_product_id = $lash_product->get_id();
    if ($current_language !== '' && function_exists('pll_get_post_language')) {
        $product_language = (string) pll_get_post_language($lash_product_id, 'slug');
        if ($product_language !== '' && $product_language !== $current_language) {
            continue;
        }
    }

    $product_name = trim(wp_strip_all_tags($lash_product->get_name()));
    if (stripos($product_name, 'Beauty Balance') === false || stripos($product_name, 'LASH') === false) {
        continue;
    }

    if (preg_match('/(?:комплект.{0,48}(?:трьох|трех|трёх)|set of three|3[- ]piece|ensemble de trois|zestaw trzech)/iu', $product_name)) {
        $group_key = 'set';
    } elseif (preg_match('/(?:під голову|под голову|head pillow|head support|oreiller.{0,12}tête|poduszk.{0,12}głow|kopfkissen)/iu', $product_name)) {
        $group_key = 'head';
    } elseif (preg_match('/(?:під колін|под колен|knee pillow|knee support|genou|knie|kolan)/iu', $product_name)) {
        $group_key = 'knee';
    } elseif (preg_match('/(?:під поперек|под поясниц|lumbar|lower back|lenden|lombaire|lędźwi)/iu', $product_name)) {
        $group_key = 'lumbar';
    } else {
        continue;
    }

    $group_title = preg_replace('/\s*[—–-]\s*(?:колір|цвет|colour|color|farbe|kolor)\s*:.*$/iu', '', $product_name);
    $group_title = is_string($group_title) && $group_title !== '' ? $group_title : $product_name;

    $product_color = '';
    if (preg_match('/(?:колір|цвет|colour|color|farbe|kolor)\s*:\s*(.+)$/iu', $product_name, $color_match)) {
        $product_color = trim($color_match[1]);
    }
    if ($product_color === '') {
        $product_color = $product_name;
    }

    if (!isset($lash_groups[$group_key])) {
        $lash_groups[$group_key] = [
            'title' => $group_title,
            'items' => [],
        ];
    }

    $lash_groups[$group_key]['items'][] = [
        'product' => $lash_product,
        'color'   => $product_color,
    ];
}

$group_order = ['set', 'head', 'knee', 'lumbar'];
uksort($lash_groups, static function (string $left, string $right) use ($group_order): int {
    return array_search($left, $group_order, true) <=> array_search($right, $group_order, true);
});
foreach ($lash_groups as &$lash_group) {
    usort($lash_group['items'], static function (array $left, array $right): int {
        return strnatcasecmp($left['color'], $right['color']);
    });
}
unset($lash_group);
?>
<dialog id="lashSetModal" class="bb-lash-modal" aria-labelledby="lashSetModalTitle">
    <div class="bb-lash-modal__panel">
        <button class="bb-lash-modal__close" type="button" data-close-lash-modal aria-label="<?php echo esc_attr__('Close', 'woocommerce'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>

        <header class="bb-lash-modal__header">
            <p class="bb-lash-modal__eyebrow">Beauty Balance®</p>
            <h2 id="lashSetModalTitle" class="bb-lash-modal__title">LASH</h2>
        </header>

        <?php if ($lash_groups) : ?>
            <div class="bb-lash-modal__groups">
                <?php foreach ($lash_groups as $lash_group) : ?>
                    <section class="bb-lash-modal__group">
                        <h3 class="bb-lash-modal__group-title"><?php echo esc_html($lash_group['title']); ?></h3>
                        <div class="bb-lash-modal__products">
                            <?php foreach ($lash_group['items'] as $lash_item) :
                                $product = $lash_item['product'];
                                ?>
                                <a class="bb-lash-modal__product" href="<?php echo esc_url($product->get_permalink()); ?>" aria-label="<?php echo esc_attr($product->get_name()); ?>">
                                    <span class="bb-lash-modal__product-image">
                                        <?php echo wp_kses_post($product->get_image('woocommerce_thumbnail', ['loading' => 'lazy'])); ?>
                                    </span>
                                    <span class="bb-lash-modal__product-color"><?php echo esc_html($lash_item['color']); ?></span>
                                    <span class="bb-lash-modal__product-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                                    <span class="bb-lash-modal__product-action"><?php esc_html_e('View product', 'woocommerce'); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p class="bb-lash-modal__empty"><?php esc_html_e('No products found.', 'woocommerce'); ?></p>
        <?php endif; ?>
    </div>
</dialog>
