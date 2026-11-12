<?php
/**
 * Admin Settings object.
 *
 * Provides admin settings used for calculations..
 *
 * @package NovaPostForWoocommerce\Http
 */

namespace NovaPostForWoocommerce\Http\RequestBuilder\Calculation;

use NovaPostForWoocommerce\Models\NovaPostSettings;

/**
 * Settings class to access Nova Post plugin settings.
 */
class Settings {
    /**
     * Instance of NovaPostSettings model.
     *
     * @var NovaPostSettings
     */
    private $settings;

    /**
     * Constructor to set settings instance.
     */
    public function __construct() {
        $this->settings = NovaPostSettings::get_instance();
    }

    /**
     * Get the sender's country.
     *
     * @return string
     */
    public function get_sender_country(): string {
        return (string) $this->settings->sender_country;
    }

    /**
     * Get the company TIN.
     *
     * @return string
     */
    public function get_company_tin(): string {
        return (string) $this->settings->company_tin;
    }

    /**
     * Get the company name.
     *
     * @return string
     */
    public function get_company_name(): string {
        return (string) $this->settings->company_name;
    }

    /**
     * Get the domestic counterparty number (CN).
     *
     * @return string
     */
    public function get_domestic_cn(): string {
        return (string) $this->settings->domestic_cn;
    }

    /**
     * Get the international counterparty number (CN).
     *
     * @return string
     */
    public function get_international_cn(): string {
        return (string) $this->settings->international_cn;
    }

    /**
     * Get the address type.
     *
     * @return string
     */
    public function get_address_type(): string {
        return (string) $this->settings->address_type;
    }

    /**
     * Get the sender's city.
     *
     * @return string
     */
    public function get_sender_city(): string {
        return (string) $this->settings->sender_city;
    }

    /**
     * Get the sender's street.
     *
     * @return string
     */
    public function get_sender_street(): string {
        return (string) $this->settings->sender_street;
    }

    /**
     * Get the sender's building.
     *
     * @return string
     */
    public function get_sender_building(): string {
        return (string) $this->settings->sender_building;
    }

    /**
     * Get the sender's postal code.
     *
     * @return string
     */
    public function get_sender_postal_code(): string {
        return (string) $this->settings->sender_postal_code;
    }

    /**
     * Get the sender's division ID.
     *
     * @return int
     */
    public function get_sender_division_id(): int {
        return (int) $this->settings->sender_division_id;
    }

    /**
     * Get the default box for.
     *
     * @return string
     */
    public function get_default_box_for(): string {
        return (string) $this->settings->default_box_for;
    }

    /**
     * Get the default width in millimeters.
     *
     * @return int
     */
    public function get_default_width_in_mm(): int {
        return (int) $this->settings->get_default_width_in_mm();
    }

    /**
     * Get the default height in millimeters.
     *
     * @return int
     */
    public function get_default_height_in_mm(): int {
        return (int) $this->settings->get_default_height_in_mm();
    }

    /**
     * Get the default product length in millimeters.
     *
     * @return int
     */
    public function get_default_length_in_mm(): int {
        return (int) $this->settings->get_default_length_in_mm();
    }

    /**
     * Get the default product width in millimeters
     *
     * @return int
     */
    public function get_default_product_width_in_mm(): int {
        return (int) $this->settings->get_default_product_width_in_mm();
    }

    /**
     * Get the default product length in millimeters.
     *
     * @return int
     */
    public function get_default_product_length_in_mm(): int {
        return (int) $this->settings->get_default_product_length_in_mm();
    }

    /**
     * Get the default product height in millimeters.
     *
     * @return int
     */
    public function get_default_product_height_in_mm(): int {
        return (int) $this->settings->get_default_product_height_in_mm();
    }
}
