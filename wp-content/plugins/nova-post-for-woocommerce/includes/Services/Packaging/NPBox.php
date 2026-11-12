<?php
/**
 * NPBox class file.
 *
 * Represents a box definition for the Nova Post packaging service.
 *
 * @package NovaPostForWoocommerce\Services\Packaging
 */

namespace NovaPostForWoocommerce\Services\Packaging;

use DVDoug\BoxPacker\Box;

/**
 * Class representing a box used by the packer.
 */
class NPBox implements Box {

    /**
     * Constructor.
     *
     * @param string $reference           Box reference ID.
     * @param int    $outer_width         Outer width in mm.
     * @param int    $outer_length        Outer length in mm.
     * @param int    $outer_depth         Outer depth in mm.
     * @param int    $empty_weight        Empty box weight in grams.
     * @param int    $max_weight          Maximum allowed weight in grams.
     * @param float  $padding_ratio       Inner padding ratio (e.g. 0.95).
     * @param float  $volumetric_divisor  Divisor for volumetric weight calculation.
     */
    public function __construct(
        private string $reference,
        private int $outer_width,
        private int $outer_length,
        private int $outer_depth,
        private int $empty_weight,
        private int $max_weight,
        // These values are required and provided externally.
        private float $padding_ratio,
        private float $volumetric_divisor
    ) {}

    /** {@inheritdoc} */
    public function getReference(): string {
        return $this->reference;
    }

    /** {@inheritdoc} */
    public function getOuterWidth(): int {
        return $this->outer_width;
    }

    /** {@inheritdoc} */
    public function getOuterLength(): int {
        return $this->outer_length;
    }

    /** {@inheritdoc} */
    public function getOuterDepth(): int {
        return $this->outer_depth;
    }

    /**
     * Get inner width adjusted by padding ratio.
     *
     * @return int
     */
    public function getInnerWidth(): int {
        return (int) floor( $this->outer_width * $this->padding_ratio );
    }

    /**
     * Get inner length adjusted by padding ratio.
     *
     * @return int
     */
    public function getInnerLength(): int {
        return (int) floor( $this->outer_length * $this->padding_ratio );
    }

    /**
     * Get inner depth adjusted by padding ratio.
     *
     * @return int
     */
    public function getInnerDepth(): int {
        return (int) floor( $this->outer_depth * $this->padding_ratio );
    }

    /** {@inheritdoc} */
    public function getEmptyWeight(): int {
        return $this->empty_weight;
    }

    /** {@inheritdoc} */
    public function getMaxWeight(): int {
        return $this->max_weight;
    }

    /**
     * Calculate box volume.
     *
     * @return int
     */
    public function getVolume(): int {
        return $this->outer_width * $this->outer_length * $this->outer_depth;
    }

    /**
     * Calculate volumetric weight.
     *
     * @return int
     */
    public function getVolumetricWeight(): int {
        return (int) ceil( $this->getVolume() / $this->volumetric_divisor );
    }
}
