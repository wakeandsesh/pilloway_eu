<?php
/**
 * Box packing service for Nova Poshta shipments.
 *
 * @package NovaPostForWoocommerce\Services
 */

namespace NovaPostForWoocommerce\Services;

use DVDoug\BoxPacker\Packer;
use DVDoug\BoxPacker\PackedItemList;
use NovaPostForWoocommerce\Adapters\ProductAdapter;
use NovaPostForWoocommerce\Models\NovaPostSettings;
use NovaPostForWoocommerce\Services\Cache\CachedExchangeRatesService;
use NovaPostForWoocommerce\Services\Packaging\NPBox;
use NovaPostForWoocommerce\Services\Packaging\NPItem;
use DVDoug\BoxPacker\NoBoxesAvailableException;

/**
 * Box packing service for Nova Poshta shipments.
 *
 * Uses dvdoug/boxpacker library to calculate optimal parcel packing.
 */
class BoxPackerService {

    /**
     * Usable volume ratio (100%). Leaves space for box walls and padding materials.
     */
    private const BOX_PADDING_RATIO = 1;

    /**
     * Maximum box weight in grams.
     */
    private const MAX_BOX_WEIGHT = 1000000;

    /**
     * Default divisor used to calculate volumetric weight.
     * Ignore for now. In future will be implemented, value 4000.
     */
    private const DEFAULT_VOLUMETRIC_DIVISOR = 0;

    /**
     * Standard Nova Poshta box sizes (outer dimensions in mm).
     */
    private const BOX_SIZES = array(
        array(
            'width'  => 170,
            'length' => 120,
            'height' => 90,
        ),
        array(
            'width'  => 150,
            'length' => 215,
            'height' => 30,
        ),
        array(
            'width'  => 180,
            'length' => 265,
            'height' => 30,
        ),
        array(
            'width'  => 220,
            'length' => 265,
            'height' => 30,
        ),
        array(
            'width'  => 240,
            'length' => 170,
            'height' => 90,
        ),
        array(
            'width'  => 340,
            'length' => 240,
            'height' => 40,
        ),
        array(
            'width'  => 340,
            'length' => 240,
            'height' => 90,
        ),
        array(
            'width'  => 240,
            'length' => 200,
            'height' => 160,
        ),
        array(
            'width'  => 240,
            'length' => 240,
            'height' => 200,
        ),
        array(
            'width'  => 340,
            'length' => 240,
            'height' => 150,
        ),
        array(
            'width'  => 400,
            'length' => 240,
            'height' => 200,
        ),
        array(
            'width'  => 400,
            'length' => 350,
            'height' => 285,
        ),
        array(
            'width'  => 600,
            'length' => 350,
            'height' => 280,
        ),
        array(
            'width'  => 470,
            'length' => 400,
            'height' => 420,
        ),
        array(
            'width'  => 700,
            'length' => 400,
            'height' => 420,
        ),
        array(
            'width'  => 500,
            'length' => 500,
            'height' => 520,
        ),
    );

    /**
     * Loaded box definitions.
     *
     * @var NPBox[]
     */
    private array $box_sizes = array();

    /**
     * Packaging strategy type.
     *
     * @var string
     */
    private string $packaging_type = '';

    /**
     * List of legacy box statuses.
     *
     * @var array
     */
    private array $old_box_statuses = array( 'order', 'product' );

    /**
     * Default packaging type for new settings.
     *
     * @var string
     */
    private string $default_packaging_type = 'novapost_boxes';

    /**
     * Default packaging type for legacy order-based settings.
     *
     * @var string
     */
    private string $default_old_order_packaging_type = 'one_box';

    /**
     * Volumetric weight divisor.
     *
     * @var float
     */
    private float $volumetric_divisor;

    /**
     * Plugin settings.
     *
     * @var NovaPostSettings
     */
    private NovaPostSettings $settings;

    /**
     * BoxPackerService constructor.
     *
     * Initializes settings and loads available box definitions.
     *
     * @param NovaPostSettings|null $settings           Plugin settings instance.
     * @param int|null              $volumetric_divisor Volumetric weight divisor.
     */
    public function __construct( ?NovaPostSettings $settings = null, ?int $volumetric_divisor = null ) {
        $this->settings           = $settings ? $settings : NovaPostSettings::get_instance();
        $this->volumetric_divisor = (float) ( $volumetric_divisor ?? self::DEFAULT_VOLUMETRIC_DIVISOR );

        $this->init_packaging_strategy();
    }

    /**
     * Main packing method.
     *
     * Determines the strategy (one box or smart algorithm) and starts the process.
     *
     * @param array $products Products to pack.
     * @return array Resulting boxes.
     */
    public function pack( array $products ): array {
        $processed_products = array_map( array( $this, 'apply_product_dimensions_fallback' ), $products );

        $total_order_weight = 0;
        foreach ( $processed_products as $product ) {
            $qty                 = (int) ( $product['qty'] ?? 1 );
            $total_order_weight += ( (int) $product['weight'] * $qty );
        }

        $this->load_box_sizes( $total_order_weight );

        $options = $this->get_options();

        if ( ( $options['type'] ?? '' ) === $this->default_old_order_packaging_type ) {
            return $this->pack_using_one_box( $processed_products, $options );
        }

        return $this->pack_with_smart_algorithm( $processed_products, $options['boxes'] ?? array() );
    }

    /**
     * Maps internal box structures to Nova Post API parcel format.
     *
     * @param array $boxes                 List of boxes from pack() method.
     * @param bool  $include_currency_code Include currency code in parcel payload.
     * @return array API-ready parcel array.
     */
    public static function map_boxes_to_api_parcels( array $boxes, bool $include_currency_code = false ): array {
        $parcels = array();
        $row     = 1;

        foreach ( $boxes as $box ) {
            $cargo_data = self::extract_cargo_data( $box );

            // Sort dimensions (Width > length > Height).
            $dims        = $box['dimensions'] ?? array();
            $sorted_dims = array( (int) ( $dims['length'] ?? 0 ), (int) ( $dims['width'] ?? 0 ), (int) ( $dims['height'] ?? 0 ) );
            rsort( $sorted_dims );

            $parcel = array(
                'cargoCategory'     => $cargo_data['category'],
                'pricingType'       => 'parcel',
                'parcelDescription' => self::generate_parcel_description( $box ),
                'insuranceCost'     => max( $cargo_data['insurance'], 1.0 ),
                'rowNumber'         => $row++,
                'length'            => max( $sorted_dims[0], 10 ),
                'width'             => max( $sorted_dims[1], 10 ),
                'height'            => max( $sorted_dims[2], 10 ),
                'actualWeight'      => max( (int) $box['weight'], 100 ),
            );

            if ( $include_currency_code ) {
                $parcel['insuranceCurrencyCode'] = get_woocommerce_currency();
            }

            $parcels[] = $parcel;
        }

        return $parcels;
    }

    /**
     * Packs items directly from WooCommerce package contents.
     *
     * @param array $package_contents The 'contents' key from the WC package array.
     * @return array Resulting packed boxes.
     */
    public function pack_from_woocommerce_items( array $package_contents ): array {
        $products_for_packing = array();

        foreach ( $package_contents as $item ) {
            $wc_product = $item['data'] ?? null;

            if ( ! $wc_product || ! $wc_product->needs_shipping() ) {
                continue;
            }

            $adapter      = new ProductAdapter( $wc_product );
            $packing_item = $adapter->to_packing_item();

            $unit_price    = (float) $wc_product->get_price();
            $exchange_data = $this->get_exchange_data( $unit_price, get_woocommerce_currency() );

            $products_for_packing[] = array(
                'width'          => (int) $packing_item['width'],
                'length'         => (int) $packing_item['length'],
                'height'         => (int) $packing_item['height'],
                'weight'         => (int) $packing_item['weight'],
                'qty'            => (int) $item['quantity'],
                'no_rotate'      => false,
                'price'          => round( $exchange_data['originalPrice'] ?? $unit_price, 2 ),
                'cargo_category' => 'parcel',
                'name'           => $wc_product->get_name(),
            );
        }

        return $this->pack( $products_for_packing );
    }

    /**
     * Helper to pack items from various WooCommerce sources (Order or Cart items).
     *
     * @param array  $items    Array of WC_Order_Item or Cart items.
     * @param string $order_currency  Order currency.
     * @param bool   $is_order True if processing an Order, false for Cart.
     * @return array Ready-to-use API parcels.
     */
    public function pack_and_map_from_shipment_items( array $items, string $order_currency, bool $is_order = false ): array {
        $products_for_packing = array();

        foreach ( $items as $item ) {
            // Get product object depending on source.
            $product = $is_order ? $item->get_product() : ( $item['data'] ?? null );

            // Determine quantity and unit price.
            $qty        = $is_order ? $item->get_quantity() : $item['quantity'];
            $unit_price = $is_order ? ( (float) $item->get_total() / $qty ) : (float) $product->get_price();

            if ( ! $product || ! $product->needs_shipping() ) {
                continue;
            }

            $adapter      = new ProductAdapter( $product );
            $packing_item = $adapter->to_packing_item();

            $products_for_packing[] = array(
                'name'           => $product->get_name(),
                'width'          => (int) $packing_item['width'],
                'length'         => (int) $packing_item['length'],
                'height'         => (int) $packing_item['height'],
                'weight'         => (int) $packing_item['weight'],
                'qty'            => (int) $qty,
                'price'          => round( $unit_price, 2 ),
                'cargo_category' => 'parcel',
                'no_rotate'      => $packing_item['no_rotate'] ?? false,
            );
        }

        // 1. Pack items into boxes.
        $packed_boxes = $this->pack( $products_for_packing );

        // 2. Map boxes to Nova Post API format and include currency code for shipment creation.
        return self::map_boxes_to_api_parcels( $packed_boxes, true );
    }


    /**
     * Retrieve exchange rates and converted prices for a specific unit price.
     *
     * This method acts as a wrapper for the CachedExchangeRatesService to get
     * converted amounts (USD, EUR) based on the sender's country settings.
     *
     * @param float  $unit_price     The price of a single unit to convert.
     * @param string $order_currency The currency code of the order (e.g., 'UAH').
     *
     * @return array {
     * @type float  $originalPrice The price in the original currency.
     * @type string $originalCode  The original currency code.
     * @type float  $usdPrice      The converted price in USD.
     * @type float  $eurPrice      The converted price in EUR.
     * }
     */
    private function get_exchange_data( $unit_price, $order_currency ) {
        $exchange_data = CachedExchangeRatesService::get_instance()->get_exchange_rates(
            $unit_price,
            $this->settings->sender_country,
            $order_currency
        );

        return $exchange_data;
    }

    /**
     * Define the strategy of packing based on new and old settings
     */
    private function init_packaging_strategy(): void {
        $new_type = $this->settings->packaging_type ?? '';
        $old_type = $this->settings->default_box_for ?? '';

        // If new type is chosen (not empty) - use it.
        if ( ! empty( $new_type ) ) {
            $this->packaging_type = $new_type;
            return;
        }

        // If new type is empty then check if we have old settings.
        if ( $this->check_if_old_package_type( $old_type ) ) {
            $this->packaging_type = ( 'order' === $old_type ) ? $this->default_old_order_packaging_type : $this->default_packaging_type;
        } else {
            // For new users or when old type is not 'order'/'product'.
            $this->packaging_type = $this->default_packaging_type;
        }
    }

    /**
     * Loads boxes into the internal array of NPBox objects.
     *
     * Passes global settings (padding, divisor) to each box instance.
     *
     * @param int $max_weight Max weight in grams (total order weight).
     * @return void
     */
    private function load_box_sizes( int $max_weight ): void {
        if ( $this->default_old_order_packaging_type === $this->packaging_type ) {
            return;
        }

        $boxes_data = $this->get_boxes_data_by_type( $this->packaging_type );

        if ( empty( $boxes_data ) ) {
            $logger = wc_get_logger();
            $logger->error(
                "BoxPackerService: No boxes found for packaging type '{$this->packaging_type}'. Packing aborted.",
                array( 'source' => 'nova-post-packer' )
            );

            return;
        }

        foreach ( $boxes_data as $i => $b ) {
            $this->box_sizes[] = new NPBox(
                'BOX_' . $i,
                (int) $b['width'],
                (int) $b['length'],
                (int) $b['height'],
                0,
                $max_weight,
                self::BOX_PADDING_RATIO,
                $this->volumetric_divisor
            );
        }
    }

    /**
     * Simplified packing: everything is placed in a single virtual box.
     *
     * @param array $products Processed products.
     * @param array $options  Packaging options.
     * @return array Resulting parcel array.
     */
    private function pack_using_one_box( array $products, array $options ): array {
        $box_w = (int) ( $options['width'] ?? 0 );
        $box_l = (int) ( $options['length'] ?? 0 );
        $box_h = (int) ( $options['height'] ?? 0 );

        $dims = $this->normalize_dimensions( $box_w, $box_l, $box_h );

        $total_weight = 0;
        foreach ( $products as $product ) {
            $item_weight   = ! empty( $product['weight'] ) ? $product['weight'] : $options['default_weight'];
            $weight_grams  = (float) $item_weight;
            $qty           = $product['qty'] ?? 1;
            $total_weight += $weight_grams * $qty;
        }

        $final_weight = $this->normalize_weight( $total_weight );

        return array(
            array(
                'box_reference'     => 'ONE_BOX',
                'dimensions'        => $dims,
                'items'             => $products,
                'weight'            => $final_weight,
                'volumetric_weight' => $this->get_volumetric_weight( $dims ),
            ),
        );
    }

    /**
     * Universal smart packing method.
     *
     * Handles both standard NP boxes and custom boxes using BoxPacker.
     *
     * @param array $products         Products list.
     * @param array $available_boxes  Box dimension array.
     * @return array                  Resulting parcel list.
     */
    private function pack_with_smart_algorithm( array $products, array $available_boxes ): array {
        if ( empty( $available_boxes ) ) {
            return array();
        }

        // Prepare items for the Packer.
        $packable_items = $this->prepare_packable_items( $products );

        // Attempt to pack items into available boxes.
        $packing_result = $this->execute_packing_loop( $packable_items, $available_boxes );

        // Process boxes and any items that couldn't fit.
        $result = $this->format_packed_result( $packing_result['packed'] );
        $result = array_merge( $result, $this->handle_unpacked_items( $packing_result['unpacked'], $products ) );

        return $result;
    }

    /**
     * Transforms raw product data into NPItem objects.
     *
     * @param array $products Raw product data to convert into packable items.
     * @return array          List of NPItem objects ready for packing.
     */
    private function prepare_packable_items( array $products ): array {
        $items = array();
        foreach ( $products as $product ) {
            $qty = (int) ( $product['qty'] ?? 1 );
            for ( $i = 0; $i < $qty; $i++ ) {
                $items[] = new NPItem(
                    $product['name'], // unique_id used for mapping.
                    (int) $product['width'],
                    (int) $product['length'],
                    (int) $product['height'],
                    (float) ( $product['price'] ?? 0 ),
                    (int) $product['weight'],
                    false,
                    $product['cargo_category'] ?? 'parcel',
                    $this->volumetric_divisor
                );
            }
        }
        return $items;
    }

    /**
     * Recursively packs items, removing problematic ones that don't fit any box.
     *
     * @param array $items    Packable items to attempt to fit into boxes.
     * @param array $box_data Available box definitions and dimensions.
     * @return array          Resulting packed parcels and any unpacked items.
     */
    private function execute_packing_loop( array $items, array $box_data ): array {
        $unpacked     = array();
        $remaining    = $items;
        $packed_boxes = array();

        while ( ! empty( $remaining ) ) {
            $packer = new Packer();
            $this->add_boxes_to_packer( $packer, $box_data, $this->calculate_total_weight( $remaining ) );

            foreach ( $remaining as $item ) {
                $packer->addItem( $item );
            }

            try {
                $packed_boxes = $packer->pack();
                break;
            } catch ( NoBoxesAvailableException $e ) {
                $problem_id = $this->extract_problematic_item_id( $e->getMessage() );
                $extraction = $this->remove_item_by_id( $remaining, $problem_id );

                $unpacked[] = $extraction['removed'];
                $remaining  = $extraction['remaining'];

                if ( ! $extraction['removed'] ) {
                    $unpacked  = array_merge( $unpacked, $remaining );
                    $remaining = array();
                }
            }
        }

        return array(
            'packed'   => $packed_boxes,
            'unpacked' => array_filter( $unpacked ),
        );
    }

    /**
     * Extracts the item ID from the exception message.
     *
     * @param string $message Exception message that may contain an item identifier.
     * @return string|null    Extracted item ID, or null if not found.
     */
    private function extract_problematic_item_id( string $message ): ?string {
        return preg_match( "/item '([^']+)'/", $message, $matches ) ? $matches[1] : null;
    }

    /**
     * Configures the Packer with available box dimensions.
     *
     * @param Packer $packer     Packer instance to which boxes will be added (passed by reference).
     * @param array  $box_data   List of available box definitions and dimensions.
     * @param int    $max_weight Maximum allowed weight per box.
     * @return void
     */
    private function add_boxes_to_packer( Packer &$packer, array $box_data, int $max_weight ): void {
        foreach ( $box_data as $b ) {
            $packer->addBox(
                new NPBox(
                    $b['width'] . 'x' . $b['length'] . 'x' . $b['height'],
                    (int) $b['width'],
                    (int) $b['length'],
                    (int) $b['height'],
                    0,
                    $max_weight,
                    self::BOX_PADDING_RATIO,
                    $this->volumetric_divisor
                )
            );
        }
    }

    /**
     * Formats successful packing objects into standard result arrays.
     *
     * @param array $packed_boxes List of packed box objects from the Packer.
     * @return array              Formatted array of packed box details.
     */
    private function format_packed_result( $packed_boxes ): array {
        $result = array();
        foreach ( $packed_boxes as $packed_box ) {
            $box      = $packed_box->getBox();
            $result[] = array(
                'box_reference'     => $box->getReference(),
                'dimensions'        => array(
                    'width'  => $box->getOuterWidth(),
                    'length' => $box->getOuterLength(),
                    'height' => $box->getOuterDepth(),
                ),
                'items'             => $packed_box->getItems(),
                'weight'            => $packed_box->getWeight(),
                'volumetric_weight' => 0,
            );
        }
        return $result;
    }

    /**
     * Handles items that physically couldn't fit in any box.
     *
     * @param array $unpacked_items    List of items that could not be packed.
     * @param array $original_products Original product data for reference or logging.
     * @return array                   Processed list including any adjustments for unpacked items.
     */
    private function handle_unpacked_items( array $unpacked_items, array $original_products ): array {
        $result      = array();
        $product_map = array();
        foreach ( $original_products as $p ) {
            $product_map[ $p['name'] ] = $p;
        }

        foreach ( $unpacked_items as $item ) {
            $name = $item->getDescription();
            $meta = $product_map[ $name ] ?? array();

            $result[] = array(
                'box_reference'           => 'FALLBACK_' . $name . '_' . uniqid(),
                'dimensions'              => array(
                    'width'  => $item->getWidth(),
                    'length' => $item->getLength(),
                    'height' => $item->getDepth(),
                ),
                'items'                   => null,
                'weight'                  => $item->getWeight(),
                'volumetric_weight'       => 0,
                'fallback_price'          => (float) ( $meta['price'] ?? 0 ),
                'fallback_cargo_category' => $meta['cargo_category'] ?? 'parcel',
            );
        }
        return $result;
    }

    /**
     * Removes the first occurrence of an item by its description (ID).
     *
     * @param array       $items List of items to search through.
     * @param string|null $id    ID of the item to remove; null if not specified.
     * @return array             Updated list of items with the first matching item removed.
     */
    private function remove_item_by_id( array $items, ?string $id ): array {
        $removed   = null;
        $remaining = array();

        foreach ( $items as $item ) {
            if ( ! $removed && $item->getDescription() === $id ) {
                $removed = $item;
                continue;
            }
            $remaining[] = $item;
        }

        return array(
            'removed'   => $removed,
            'remaining' => $remaining,
        );
    }

    /**
     * Sums up the weight of all items to determine the total box capacity dynamically.
     *
     * @param array $items List of items to calculate total weight from.
     * @return int         Total weight of all items.
     */
    private function calculate_total_weight( array $items ): int {
        return array_reduce( $items, fn( $carry, $item ) => $carry + $item->getWeight(), 0 );
    }

    /**
     * Applies default dimensions if product dimensions are missing.
     *
     * @param array $product Product data.
     * @return array Processed product data.
     */
    private function apply_product_dimensions_fallback( array $product ): array {
        $is_missing_dimensions = empty( $product['width'] ) || empty( $product['length'] ) || empty( $product['height'] );

        if ( $is_missing_dimensions ) {
            $default_product_width  = $this->settings->get_default_product_width_in_mm();
            $default_product_length = $this->settings->get_default_product_length_in_mm();
            $default_product_height = $this->settings->get_default_product_height_in_mm();

            $product['width']  = max( $default_product_width, 10 );
            $product['length'] = max( $default_product_length, 10 );
            $product['height'] = max( $default_product_height, 10 );
        }

        if ( empty( $product['weight'] ) ) {
            $default_weight    = $this->settings->get_default_weight_in_gr() ?? 100;
            $product['weight'] = $default_weight - ( $default_weight % 10 );
        }

        return $product;
    }

    /**
     * Gets box data by selected strategy type.
     *
     * @param string $type Strategy type.
     * @return array Box dimensions array.
     */
    private function get_boxes_data_by_type( string $type ): array {
        if ( 'custom_boxes' === $type ) {
            return $this->get_custom_boxes_from_admin();
        }

        return self::BOX_SIZES;
    }

    /**
     * Retrieves custom box settings from plugin admin options.
     *
     * @return array
     */
    private function get_custom_boxes_from_admin(): array {
        $custom_boxes = $this->settings->np_custom_boxes;

        if ( empty( $custom_boxes ) ) {
            return array();
        }

        return ProductAdapter::convert_box_dimensions_to_mm( $custom_boxes );
    }

    /**
     * Builds an options array for the pack() method.
     *
     * @return array
     */
    private function get_options(): array {
        $options = array( 'type' => $this->packaging_type );

        if ( $this->default_old_order_packaging_type === $this->packaging_type ) {
            $options['width']  = (float) ( $this->settings->get_default_width_in_mm() ?? 0 );
            $options['height'] = (float) ( $this->settings->get_default_height_in_mm() ?? 0 );
            $options['length'] = (float) ( $this->settings->get_default_length_in_mm() ?? 0 );
            $options['boxes']  = array();
        } else {
            $options['boxes'] = $this->get_boxes_data_by_type( $this->packaging_type );
        }

        return $options;
    }

    /**
     * Checks whether the provided package type belongs to the legacy (old) package types.
     *
     * @param string $type Package type identifier.
     * @return bool True if the type is an old package type, false otherwise.
     */
    private function check_if_old_package_type( $type ): bool {
        return in_array( $type, $this->old_box_statuses, true );
    }

    /**
     * Sorts dimensions in descending order (Length > Width > Height).
     *
     * @param int $width  The width of the object.
     * @param int $length The length of the object.
     * @param int $height The height of the object.
     *
     * @return int[] Dimensions sorted in descending order.
     */
    private function normalize_dimensions( int $width, int $length, int $height ): array {
        $dims = array( $width, $length, $height );
        rsort( $dims );
        return array(
            'length' => max( $dims[0], 10 ),
            'width'  => max( $dims[1], 10 ),
            'height' => max( $dims[2], 10 ),
        );
    }

    /**
     * Rounds weight down to the nearest 10 grams and ensures a minimum.
     *
     * @param float $weight_grams The weight in grams.
     *
     * @return int Normalized weight in grams (minimum 100g, rounded down to nearest 10g).
     */
    private function normalize_weight( float $weight_grams ): int {
        $weight  = (int) $weight_grams;
        $weight -= ( $weight % 10 );
        return max( $weight, 100 );
    }

    /**
     * Calculates volume from dimensions.
     *
     * @param int $width  The width of the object.
     * @param int $length The length of the object.
     * @param int $height The height of the object.
     *
     * @return int Volume in cubic units.
     */
    private function calculate_volume( int $width, int $length, int $height ): int {
        return (int) ( $width * $length * $height );
    }

    /**
     * Calculates volumetric weight based on dimensions array.
     *
     * @param array $dims Array with 'length', 'width', 'height' keys.
     *
     * @return int Volumetric weight rounded up.
     */
    private function get_volumetric_weight( array $dims ): int {
        unset( $dims ); // Need to be deleted for future volumetric weight calculation, need now to fix phpcs.
        return 0;
        // Formula below will be used here instead of it in future.
        // return (int) ceil( $this->calculate_volume( $dims['width'], $dims['length'], $dims['height'] ) / $this->volumetric_divisor ).
    }

    /**
     * Generates a comma-separated description of parcel contents.
     *
     * @param array $box Packed box data.
     * @return string Formatted description limited to 255 characters.
     */
    private static function generate_parcel_description( array $box ): string {
        $items = $box['items'] ?? null;
        $names = array();

        if ( $items instanceof PackedItemList ) {
            foreach ( $items as $packed_item ) {
                $names[] = $packed_item->getItem()->getDescription();
            }
        } elseif ( is_array( $items ) ) {
            $names = array_column( $items, 'name' );
        } elseif ( isset( $box['box_reference'] ) && str_starts_with( $box['box_reference'], 'FALLBACK_' ) ) {
            $names[] = str_replace( 'FALLBACK_', '', $box['box_reference'] );
        }

        // Filter duplicates and empty values.
        $unique_names = array_filter( array_unique( $names ) );

        // Join unique names into a comma-separated string - limited length by API.
        $desc = empty( $unique_names ) ? 'Parcel' : implode( ', ', array_unique( $names ) );

        return substr( $desc, 0, 255 );
    }

    /**
     * Extracts insurance cost and cargo category from the given box data.
     *
     * @param array $box Box data containing details like weight, dimensions, and insurance info.
     * @return array     Array with keys for insurance cost and cargo category.
     */
    private static function extract_cargo_data( array $box ): array {
        $insurance = 0.0;
        $category  = 'parcel';
        $items     = $box['items'] ?? null;

        // 1. Parsing result of BoxPacker (objects)
        if ( $items instanceof PackedItemList ) {
            foreach ( $items as $packed_item ) {
                $item       = $packed_item->getItem();
                $insurance += method_exists( $item, 'getPrice' ) ? $item->getPrice() : 0;
            }

            return array(
                'insurance' => $insurance,
                'category'  => $category,
            );
        }

        // 2. Parsing manually packing (arrays, eg ONE_BOX)
        if ( is_array( $items ) ) {
            foreach ( $items as $item ) {
                $qty        = (int) ( $item['qty'] ?? 1 );
                $insurance += ( (float) ( $item['price'] ?? 0 ) * $qty );
            }

            return array(
                'insurance' => $insurance,
                'category'  => $category,
            );
        }

        // 3. Fallback option
        return array(
            'insurance' => (float) ( $box['fallback_price'] ?? 0 ),
            'category'  => $category,
        );
    }
}
