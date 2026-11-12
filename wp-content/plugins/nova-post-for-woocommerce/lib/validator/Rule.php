<?php
/**
 * Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

use NovaPostForWoocommerce\Rakit\Validation\MissingRequiredParameterException;

/**
 * Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
abstract class Rule {

    /**
     * The rule key
     *
     * @var string
     */
    protected $key;

    /**
     * The validation attribute
     *
     * @var \Rakit\Validation\Attribute|null
     */
    protected $attribute;

    /**
     * The validation instance
     *
     * @var \Rakit\Validation\Validation|null
     */
    protected $validation;

    /**
     * Whether the rule is implicit
     *
     * @var bool
     */
    protected $implicit = false;

    /**
     * The rule parameters
     *
     * @var array
     */
    protected $params = array();

    /**
     * The parameter texts
     *
     * @var array
     */
    protected $paramsTexts = array();

    /**
     * The fillable parameters
     *
     * @var array
     */
    protected $fillableParams = array();

    /**
     * The error message
     *
     * @var string
     */
    protected $message = 'The :attribute is invalid';

    /**
     * Check the value
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    abstract public function check( $value ): bool;

    /**
     * Set Validation class instance
     *
     * @param \Rakit\Validation\Validation $validation The validation instance.
     * @return void
     */
    public function setValidation( Validation $validation ) {
        $this->validation = $validation;
    }

    /**
     * Set key
     *
     * @param string $key The key.
     * @return void
     */
    public function setKey( string $key ) {
        $this->key = $key;
    }

    /**
     * Get key
     *
     * @return string
     */
    public function getKey() {
        return $this->key ? $this->key : get_class( $this );
    }

    /**
     * Set attribute
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @return void
     */
    public function setAttribute( Attribute $attribute ) {
        $this->attribute = $attribute;
    }

    /**
     * Get attribute
     *
     * @return \Rakit\Validation\Attribute|null
     */
    public function getAttribute() {
        return $this->attribute;
    }

    /**
     * Get parameters
     *
     * @return array
     */
    public function getParameters(): array {
        return $this->params;
    }

    /**
     * Set params
     *
     * @param array $params The parameters.
     * @return \Rakit\Validation\Rule
     */
    public function setParameters( array $params ): Rule {
        $this->params = array_merge( $this->params, $params );
        return $this;
    }

    /**
     * Set parameters
     *
     * @param string $key   The key.
     * @param mixed  $value The value.
     * @return \Rakit\Validation\Rule
     */
    public function setParameter( string $key, $value ): Rule {
        $this->params[ $key ] = $value;
        return $this;
    }

    /**
     * Fill $params to $this->params
     *
     * @param array $params The parameters.
     * @return \Rakit\Validation\Rule
     */
    public function fillParameters( array $params ): Rule {
        foreach ( $this->fillableParams as $key ) {
            if ( empty( $params ) ) {
                break;
            }
            $this->params[ $key ] = array_shift( $params );
        }
        return $this;
    }

    /**
     * Get parameter from given $key, return null if it not exists
     *
     * @param string $key The key.
     * @return mixed
     */
    public function parameter( string $key ) {
        return isset( $this->params[ $key ] ) ? $this->params[ $key ] : null;
    }

    /**
     * Set parameter text that can be displayed in error message using ':param_key'
     *
     * @param string $key  The key.
     * @param string $text The text.
     * @return void
     */
    public function setParameterText( string $key, string $text ) {
        $this->paramsTexts[ $key ] = $text;
    }

    /**
     * Get $paramsTexts
     *
     * @return array
     */
    public function getParametersTexts(): array {
        return $this->paramsTexts;
    }

    /**
     * Check whether this rule is implicit
     *
     * @return boolean
     */
    public function isImplicit(): bool {
        return $this->implicit;
    }

    /**
     * Set message
     *
     * @param string $message The message.
     * @return \Rakit\Validation\Rule
     */
    public function setMessage( string $message ): Rule {
        $this->message = $message;
        return $this;
    }

    /**
     * Get message
     *
     * @return string
     */
    public function getMessage(): string {
        if ( method_exists( $this, 'message' ) ) {
            return $this->message();
        }
        return $this->message;
    }

    /**
     * Check given $params must be exists
     *
     * @param array $params The parameters.
     * @return void
     * @throws \Rakit\Validation\MissingRequiredParameterException Missing parameter exception.
     */
    protected function requireParameters( array $params ) {
        foreach ( $params as $param ) {
            if ( ! isset( $this->params[ $param ] ) ) {
                $rule = $this->getKey();
                throw new MissingRequiredParameterException( "Missing required parameter '{$param}' on rule '{$rule}'" ); // phpcs:ignore
            }
        }
    }
}
