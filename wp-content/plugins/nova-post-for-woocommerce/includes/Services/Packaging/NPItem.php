<?php
/**
 * Item class for BoxPacker
 *
 * @package NovaPostForWoocommerce\Services\Packaging
 */

namespace NovaPostForWoocommerce\Services\Packaging;

use DVDoug\BoxPacker\Item;

/**
 * Product item class for the 3D packer.
 */
class NPItem implements Item {

    /**
     * Class constructor.
     *
     * @param string $name               Product name.
     * @param int    $width              Width in mm.
     * @param int    $length             Length in mm.
     * @param int    $depth              Height/Depth in mm.
     * @param float  $price              Declared value/Price.
     * @param int    $weight             Weight in grams.
     * @param bool   $no_rotate          Whether rotation is prohibited.
     * @param string $cargo_category     Nova Post cargo category.
     * @param float  $volumetric_divisor Divisor for volumetric weight calculation.
     */
    public function __construct(
        private string $name,
        private int $width,
        private int $length,
        private int $depth,
        private float $price,
        private int $weight,
        private bool $no_rotate = false,
        private string $cargo_category = 'parcel',
        private float $volumetric_divisor = 4000.0
    ) {
    }

    /**
     * Get item description (name).
     *
     * @return string
     */
    public function getDescription(): string {
        return $this->name;
    }

    /**
     * Get item width.
     *
     * @return int
     */
    public function getWidth(): int {
        return $this->width;
    }

    /**
     * Get item length.
     *
     * @return int
     */
    public function getLength(): int {
        return $this->length;
    }

    /**
     * Get item depth (height).
     *
     * @return int
     */
    public function getDepth(): int {
        return $this->depth;
    }

    /**
     * Get item weight.
     *
     * @return int
     */
    public function getWeight(): int {
        return $this->weight;
    }

    /**
     * Get item volume.
     *
     * @return int
     */
    public function getVolume(): int {
        return $this->width * $this->length * $this->depth;
    }

    /**
     * Calculate volumetric weight.
     *
     * @return int
     */
    public function getVolumetricWeight(): int {
        return (int) ceil( $this->getVolume() / $this->volumetric_divisor );
    }

    /**
     * Check if rotation is prohibited.
     *
     * @return bool
     */
    public function noRotate(): bool {
        return $this->no_rotate;
    }

    /**
     * Get price for API data.
     *
     * @return float
     */
    public function getPrice(): float {
        return $this->price;
    }

    /**
     * Get cargo category for API data.
     *
     * @return string
     */
    public function getCargoCategory(): string {
        return $this->cargo_category;
    }

    /**
     * Required method by DVDoug\BoxPacker\Item interface.
     * Determines if the item must be kept flat (no vertical rotation).
     *
     * @return bool
     */
    public function getKeepFlat(): bool {
        return $this->no_rotate;
    }
}
