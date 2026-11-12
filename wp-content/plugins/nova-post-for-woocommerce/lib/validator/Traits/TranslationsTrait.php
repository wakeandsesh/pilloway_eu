<?php
/**
 * TranslationsTrait File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Traits
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Traits;

/**
 * TranslationsTrait
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Traits
 */
trait TranslationsTrait {

    /**
     * The translations
     *
     * @var array
     */
    protected $translations = array();

    /**
     * Given $key and $translation to set translation
     *
     * @param string $key         The key.
     * @param string $translation The translation.
     * @return void
     */
    public function setTranslation( string $key, string $translation ) {
        $this->translations[ $key ] = $translation;
    }

    /**
     * Given $translations and set multiple translations
     *
     * @param array $translations The translations.
     * @return void
     */
    public function setTranslations( array $translations ) {
        $this->translations = array_merge( $this->translations, $translations );
    }

    /**
     * Given translation from given $key
     *
     * @param string $key The key.
     * @return string
     */
    public function getTranslation( string $key ): string {
        return array_key_exists( $key, $this->translations ) ? $this->translations[ $key ] : $key;
    }

    /**
     * Get all $translations
     *
     * @return array
     */
    public function getTranslations(): array {
        return $this->translations;
    }
}
