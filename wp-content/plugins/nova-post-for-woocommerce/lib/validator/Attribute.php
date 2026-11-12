<?php
/**
 * Attribute File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

/**
 * Attribute Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class Attribute {

    /**
     * Rules for the attribute
     *
     * @var array
     */
    protected $rules = array();

    /**
     * The attribute key
     *
     * @var string
     */
    protected $key;

    /**
     * The attribute alias
     *
     * @var string|null
     */
    protected $alias;

    /**
     * The validation instance
     *
     * @var \Rakit\Validation\Validation
     */
    protected $validation;

    /**
     * Whether the attribute is required
     *
     * @var bool
     */
    protected $required = false;

    /**
     * The primary attribute
     *
     * @var \Rakit\Validation\Attribute|null
     */
    protected $primaryAttribute = null;

    /**
     * Other attributes
     *
     * @var array
     */
    protected $otherAttributes = array();

    /**
     * Key indexes
     *
     * @var array
     */
    protected $keyIndexes = array();

    /**
     * Constructor
     *
     * @param \Rakit\Validation\Validation $validation The validation instance.
     * @param string                       $key        The attribute key.
     * @param string|null                  $alias      The attribute alias.
     * @param array                        $rules      The rules for the attribute.
     * @return void
     */
    public function __construct(
        Validation $validation,
        string $key,
        $alias = null,
        array $rules = array()
    ) {
        $this->validation = $validation;
        $this->alias      = $alias;
        $this->key        = $key;
        foreach ( $rules as $rule ) {
            $this->addRule( $rule );
        }
    }

    /**
     * Set the primary attribute
     *
     * @param \Rakit\Validation\Attribute $primaryAttribute The primary attribute.
     * @return void
     */
    public function setPrimaryAttribute( Attribute $primaryAttribute ) {
        $this->primaryAttribute = $primaryAttribute;
    }

    /**
     * Set key indexes
     *
     * @param array $keyIndexes The key indexes.
     * @return void
     */
    public function setKeyIndexes( array $keyIndexes ) {
        $this->keyIndexes = $keyIndexes;
    }

    /**
     * Get primary attributes
     *
     * @return \Rakit\Validation\Attribute|null
     */
    public function getPrimaryAttribute() {
        return $this->primaryAttribute;
    }

    /**
     * Set other attributes
     *
     * @param array $otherAttributes Other attributes.
     * @return void
     */
    public function setOtherAttributes( array $otherAttributes ) {
        $this->otherAttributes = array();
        foreach ( $otherAttributes as $otherAttribute ) {
            $this->addOtherAttribute( $otherAttribute );
        }
    }

    /**
     * Add other attributes
     *
     * @param \Rakit\Validation\Attribute $otherAttribute The other attribute.
     * @return void
     */
    public function addOtherAttribute( Attribute $otherAttribute ) {
        $this->otherAttributes[] = $otherAttribute;
    }

    /**
     * Get other attributes
     *
     * @return array
     */
    public function getOtherAttributes(): array {
        return $this->otherAttributes;
    }

    /**
     * Add rule
     *
     * @param \Rakit\Validation\Rule $rule The rule to add.
     * @return void
     */
    public function addRule( Rule $rule ) {
        $rule->setAttribute( $this );
        $rule->setValidation( $this->validation );
        $this->rules[ $rule->getKey() ] = $rule;
    }

    /**
     * Get rule
     *
     * @param string $ruleKey The rule key.
     * @return \Rakit\Validation\Rule|null
     */
    public function getRule( string $ruleKey ) {
        return $this->hasRule( $ruleKey ) ? $this->rules[ $ruleKey ] : null;
    }

    /**
     * Get rules
     *
     * @return array
     */
    public function getRules(): array {
        return $this->rules;
    }

    /**
     * Check the $ruleKey has in the rule
     *
     * @param string $ruleKey The rule key.
     * @return bool
     */
    public function hasRule( string $ruleKey ): bool {
        return isset( $this->rules[ $ruleKey ] );
    }

    /**
     * Set required
     *
     * @param boolean $required Whether the attribute is required.
     * @return void
     */
    public function setRequired( bool $required ) {
        $this->required = $required;
    }

    /**
     * Set rule is required
     *
     * @return boolean
     */
    public function isRequired(): bool {
        return $this->required;
    }

    /**
     * Get key
     *
     * @return string
     */
    public function getKey(): string {
        return $this->key;
    }

    /**
     * Get key indexes
     *
     * @return array
     */
    public function getKeyIndexes(): array {
        return $this->keyIndexes;
    }

    /**
     * Get value
     *
     * @param string|null $key The key.
     * @return mixed
     */
    public function getValue( string $key = null ) {
        if ( $key && $this->isArrayAttribute() ) {
            $key = $this->resolveSiblingKey( $key );
        }

        if ( ! $key ) {
            $key = $this->getKey();
        }

        return $this->validation->getValue( $key );
    }

    /**
     * Get that is array attribute
     *
     * @return boolean
     */
    public function isArrayAttribute(): bool {
        return count( $this->getKeyIndexes() ) > 0;
    }

    /**
     * Check this attribute is using dot notation
     *
     * @return boolean
     */
    public function isUsingDotNotation(): bool {
        return strpos( $this->getKey(), '.' ) !== false;
    }

    /**
     * Resolve sibling key
     *
     * @param string $key The key.
     * @return string
     */
    public function resolveSiblingKey( string $key ): string {
        $indexes        = $this->getKeyIndexes();
        $keys           = explode( '*', $key );
        $countAsterisks = count( $keys ) - 1;
        if ( count( $indexes ) < $countAsterisks ) {
            $indexes = array_merge( $indexes, array_fill( 0, $countAsterisks - count( $indexes ), '*' ) );
        }
        $args = array_merge( array( str_replace( '*', '%s', $key ) ), $indexes );
        return call_user_func_array( 'sprintf', $args );
    }

    /**
     * Get humanize key
     *
     * @return string
     */
    public function getHumanizedKey() {
        $primaryAttribute = $this->getPrimaryAttribute();
        $key              = str_replace( '_', ' ', $this->key );

        // Resolve key from array validation.
        if ( $primaryAttribute ) {
            $split = explode( '.', $key );
            $key   = implode(
                ' ',
                array_map(
                    function ( $word ) {
                        if ( is_numeric( $word ) ) {
                            ++$word;
                        }
                        return Helper::snakeCase( $word, ' ' );
                    },
                    $split
                )
            );
        }

        return ucfirst( $key );
    }

    /**
     * Set alias
     *
     * @param string $alias The alias.
     * @return void
     */
    public function setAlias( string $alias ) {
        $this->alias = $alias;
    }

    /**
     * Get alias
     *
     * @return string|null
     */
    public function getAlias() {
        return $this->alias;
    }
}
