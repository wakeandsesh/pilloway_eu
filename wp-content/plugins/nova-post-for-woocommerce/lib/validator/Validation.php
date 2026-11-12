<?php
/**
 * Validation File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

use Closure;
use NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces\BeforeValidate;
use NovaPostForWoocommerce\Rakit\Validation\Rules\Interfaces\ModifyValue;
use NovaPostForWoocommerce\Rakit\Validation\Rules\Required;

/**
 * Validation Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class Validation {

    use Traits\TranslationsTrait;
    use Traits\MessagesTrait;

    /**
     * The validator
     *
     * @var mixed
     */
    protected $validator;

    /**
     * The inputs
     *
     * @var array
     */
    protected $inputs = array();

    /**
     * The attributes
     *
     * @var array
     */
    protected $attributes = array();

    /**
     * The aliases
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * The message separator
     *
     * @var string
     */
    protected $messageSeparator = '.';

    /**
     * The valid data
     *
     * @var array
     */
    protected $validData = array();

    /**
     * The invalid data
     *
     * @var array
     */
    protected $invalidData = array();

    /**
     * The errors
     *
     * @var ErrorBag
     */
    public $errors;

    /**
     * Constructor
     *
     * @param \Rakit\Validation\Validator $validator The validator.
     * @param array                       $inputs    The inputs.
     * @param array                       $rules     The rules.
     * @param array                       $messages  The messages.
     * @return void
     */
    public function __construct(
        Validator $validator,
        array $inputs,
        array $rules,
        array $messages = array()
    ) {
        $this->validator = $validator;
        $this->inputs    = $this->resolveInputAttributes( $inputs );
        $this->messages  = $messages;
        $this->errors    = new ErrorBag();
        foreach ( $rules as $attributeKey => $rules ) {
            $this->addAttribute( $attributeKey, $rules );
        }
    }

    /**
     * Add attribute rules
     *
     * @param string       $attributeKey The attribute key.
     * @param string|array $rules        The rules.
     * @return void
     */
    public function addAttribute( string $attributeKey, $rules ) {
        $resolvedRules                     = $this->resolveRules( $rules );
        $attribute                         = new Attribute( $this, $attributeKey, $this->getAlias( $attributeKey ), $resolvedRules );
        $this->attributes[ $attributeKey ] = $attribute;
    }

    /**
     * Get attribute by key
     *
     * @param string $attributeKey The attribute key.
     * @return null|\Rakit\Validation\Attribute
     */
    public function getAttribute( string $attributeKey ) {
        return isset( $this->attributes[ $attributeKey ] ) ? $this->attributes[ $attributeKey ] : null;
    }

    /**
     * Run validation
     *
     * @param array $inputs The inputs.
     * @return void
     */
    public function validate( array $inputs = array() ) {
        $this->errors = new ErrorBag(); // reset error bag.
        $this->inputs = array_merge( $this->inputs, $this->resolveInputAttributes( $inputs ) );

        // Before validation hooks.
        foreach ( $this->attributes as $attributeKey => $attribute ) {
            foreach ( $attribute->getRules() as $rule ) {
                if ( $rule instanceof BeforeValidate ) {
                    $rule->beforeValidate();
                }
            }
        }

        foreach ( $this->attributes as $attributeKey => $attribute ) {
            $this->validateAttribute( $attribute );
        }
    }

    /**
     * Get ErrorBag instance
     *
     * @return \Rakit\Validation\ErrorBag
     */
    public function errors(): ErrorBag {
        return $this->errors;
    }

    /**
     * Validate attribute
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @return void
     */
    protected function validateAttribute( Attribute $attribute ) {
        if ( $this->isArrayAttribute( $attribute ) ) {
            $attributes = $this->parseArrayAttribute( $attribute );
            foreach ( $attributes as $i => $attr ) {
                $this->validateAttribute( $attr );
            }
            return;
        }

        $attributeKey = $attribute->getKey();
        $rules        = $attribute->getRules();

        $value        = $this->getValue( $attributeKey );
        $isEmptyValue = $this->isEmptyValue( $value );

        if ( $attribute->hasRule( 'nullable' ) && $isEmptyValue ) {
            $rules = array();
        }

        $isValid = true;
        foreach ( $rules as $ruleValidator ) {
            $ruleValidator->setAttribute( $attribute );

            if ( $ruleValidator instanceof ModifyValue ) {
                $value        = $ruleValidator->modifyValue( $value );
                $isEmptyValue = $this->isEmptyValue( $value );
            }

            $valid = $ruleValidator->check( $value );

            if ( $isEmptyValue && $this->ruleIsOptional( $attribute, $ruleValidator ) ) {
                continue;
            }

            if ( ! $valid ) {
                $isValid = false;
                $this->addError( $attribute, $value, $ruleValidator );
                if ( $ruleValidator->isImplicit() ) {
                    break;
                }
            }
        }

        if ( $isValid ) {
            $this->setValidData( $attribute, $value );
        } else {
            $this->setInvalidData( $attribute, $value );
        }
    }

    /**
     * Check whether given $attribute is array attribute
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @return bool
     */
    protected function isArrayAttribute( Attribute $attribute ): bool {
        $key = $attribute->getKey();
        return strpos( $key, '*' ) !== false;
    }

    /**
     * Parse array attribute into it's child attributes
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @return array
     */
    protected function parseArrayAttribute( Attribute $attribute ): array {
        $attributeKey = $attribute->getKey();
        $data         = Helper::arrayDot( $this->initializeAttributeOnData( $attributeKey ) );

        $pattern = str_replace( '\*', '([^\.]+)', preg_quote( $attributeKey, '/' ) );

        $data = array_merge(
            $data,
            $this->extractValuesForWildcards(
                $data,
                $attributeKey
            )
        );

        $attributes = array();

        foreach ( $data as $key => $value ) {
            if ( (bool) preg_match( '/^' . $pattern . '\z/', $key, $match ) ) {
                $attr = new Attribute( $this, $key, null, $attribute->getRules() );
                $attr->setPrimaryAttribute( $attribute );
                $attr->setKeyIndexes( array_slice( $match, 1 ) );
                $attributes[] = $attr;
            }
        }

        // set other attributes to each attributes.
        foreach ( $attributes as $i => $attr ) {
            $otherAttributes = $attributes;
            unset( $otherAttributes[ $i ] );
            $attr->setOtherAttributes( $otherAttributes );
        }

        return $attributes;
    }

    /**
     * Gather a copy of the attribute data filled with any missing attributes.
     * Adapted from: https://github.com/illuminate/validation/blob/v5.3.23/Validator.php#L334
     *
     * @param  string $attributeKey The attribute key.
     * @return array
     */
    protected function initializeAttributeOnData( string $attributeKey ): array {
        $explicitPath = $this->getLeadingExplicitAttributePath( $attributeKey );

        $data = $this->extractDataFromPath( $explicitPath );

        $asteriskPos = strpos( $attributeKey, '*' );

        if ( false === $asteriskPos || ( MB::strlen( $attributeKey, 'UTF-8' ) - 1 ) === $asteriskPos ) {
            return $data;
        }

        return Helper::arraySet( $data, $attributeKey, null, true );
    }

    /**
     * Get all of the exact attribute values for a given wildcard attribute.
     * Adapted from: https://github.com/illuminate/validation/blob/v5.3.23/Validator.php#L354
     *
     * @param  array  $data         The data.
     * @param  string $attributeKey The attribute key.
     * @return array
     */
    public function extractValuesForWildcards( array $data, string $attributeKey ): array {
        $keys = array();

        $pattern = str_replace( '\*', '[^\.]+', preg_quote( $attributeKey, '/' ) );

        foreach ( $data as $key => $value ) {
            if ( (bool) preg_match( '/^' . $pattern . '/', $key, $matches ) ) {
                $keys[] = $matches[0];
            }
        }

        $keys = array_unique( $keys );

        $data = array();

        foreach ( $keys as $key ) {
            $data[ $key ] = Helper::arrayGet( $this->inputs, $key );
        }

        return $data;
    }

    /**
     * Get the explicit part of the attribute name.
     * Adapted from: https://github.com/illuminate/validation/blob/v5.3.23/Validator.php#L2817
     *
     * E.g. 'foo.bar.*.baz' -> 'foo.bar'
     *
     * Allows us to not spin through all of the flattened data for some operations.
     *
     * @param  string $attributeKey The attribute key.
     * @return string|null null when root wildcard
     */
    protected function getLeadingExplicitAttributePath( string $attributeKey ) {
        return rtrim( explode( '*', $attributeKey )[0], '.' ) ? rtrim( explode( '*', $attributeKey )[0], '.' ) : null;
    }

    /**
     * Extract data based on the given dot-notated path.
     * Adapted from: https://github.com/illuminate/validation/blob/v5.3.23/Validator.php#L2830
     *
     * Used to extract a sub-section of the data for faster iteration.
     *
     * @param  string|null $attributeKey The attribute key.
     * @return array
     */
    protected function extractDataFromPath( $attributeKey ): array {
        $results = array();

        $value = Helper::arrayGet( $this->inputs, $attributeKey, '__missing__' );

        if ( '__missing__' !== $value ) {
            Helper::arraySet( $results, $attributeKey, $value );
        }

        return $results;
    }

    /**
     * Add error to the $this->errors
     *
     * @param \Rakit\Validation\Attribute $attribute     The attribute.
     * @param mixed                       $value         The value.
     * @param \Rakit\Validation\Rule      $ruleValidator The rule validator.
     * @return void
     */
    protected function addError( Attribute $attribute, $value, Rule $ruleValidator ) {
        $ruleName = $ruleValidator->getKey();
        $message  = $this->resolveMessage( $attribute, $value, $ruleValidator );

        $this->errors->add( $attribute->getKey(), $ruleName, $message );
    }

    /**
     * Check $value is empty value
     *
     * @param mixed $value The value.
     * @return boolean
     */
    protected function isEmptyValue( $value ): bool {
        $requiredValidator = new Required();
        return false === $requiredValidator->check( $value, array() );
    }

    /**
     * Check the rule is optional
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @param \Rakit\Validation\Rule      $rule      The rule.
     * @return bool
     */
    protected function ruleIsOptional( Attribute $attribute, Rule $rule ): bool {
        return false === $attribute->isRequired() &&
            false === $rule->isImplicit() &&
            false === $rule instanceof Required;
    }

    /**
     * Resolve attribute name
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @return string
     */
    protected function resolveAttributeName( Attribute $attribute ): string {
        $primaryAttribute = $attribute->getPrimaryAttribute();
        if ( isset( $this->aliases[ $attribute->getKey() ] ) ) {
            return $this->aliases[ $attribute->getKey() ];
        } elseif ( $primaryAttribute && isset( $this->aliases[ $primaryAttribute->getKey() ] ) ) {
            return $this->aliases[ $primaryAttribute->getKey() ];
        } elseif ( $this->validator->isUsingHumanizedKey() ) {
            return $attribute->getHumanizedKey();
        } else {
            return $attribute->getKey();
        }
    }

    /**
     * Resolve message
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @param mixed                       $value     The value.
     * @param \Rakit\Validation\Rule      $validator The validator.
     * @return mixed
     */
    protected function resolveMessage( Attribute $attribute, $value, Rule $validator ): string {
        $primaryAttribute = $attribute->getPrimaryAttribute();
        $params           = array_merge( $validator->getParameters(), $validator->getParametersTexts() );
        $attributeKey     = $attribute->getKey();
        $ruleKey          = $validator->getKey();
        $alias            = $attribute->getAlias() ? $attribute->getAlias() : $this->resolveAttributeName( $attribute );
        $message          = $validator->getMessage(); // default rule message.
        $messageKeys      = array(
            $attributeKey . $this->messageSeparator . $ruleKey,
            $attributeKey,
            $ruleKey,
        );

        if ( $primaryAttribute ) {
            // insert primaryAttribute keys
            // $messageKeys = [
            // $attributeKey.$this->messageSeparator.$ruleKey,
            // >> here [1] <<.
            // $attributeKey,
            // >> and here [3] <<.
            // $ruleKey.
            // ];.
            $primaryAttributeKey = $primaryAttribute->getKey();
            array_splice( $messageKeys, 1, 0, $primaryAttributeKey . $this->messageSeparator . $ruleKey );
            array_splice( $messageKeys, 3, 0, $primaryAttributeKey );
        }

        foreach ( $messageKeys as $key ) {
            if ( isset( $this->messages[ $key ] ) ) {
                $message = $this->messages[ $key ];
                break;
            }
        }

        // Replace message params.
        $vars = array_merge(
            $params,
            array(
                'attribute' => $alias,
                'value'     => $value,
            )
        );

        foreach ( $vars as $key => $value ) {
            $value   = $this->stringify( $value );
            $message = str_replace( ':' . $key, $value, $message );
        }

        // Replace key indexes.
        $keyIndexes = $attribute->getKeyIndexes();
        foreach ( $keyIndexes as $pathIndex => $index ) {
            $replacers = array(
                "[{$pathIndex}]" => $index,
            );

            if ( is_numeric( $index ) ) {
                $replacers[ "{{$pathIndex}}" ] = $index + 1;
            }

            $message = str_replace( array_keys( $replacers ), array_values( $replacers ), $message );
        }

        return $message;
    }

    /**
     * Stringify $value
     *
     * @param mixed $value The value.
     * @return string
     */
    protected function stringify( $value ): string {
        if ( is_string( $value ) || is_numeric( $value ) ) {
            return $value;
        } elseif ( is_array( $value ) || is_object( $value ) ) {
            return json_encode( $value ); // phpcs:ignore
        } else {
            return '';
        }
    }

    /**
     * Resolve $rules
     *
     * @param mixed $rules The rules.
     * @return array
     * @throws \Exception Rule exception.
     */
    protected function resolveRules( $rules ): array {
        if ( is_string( $rules ) ) {
            $rules = explode( '|', $rules );
        }

        $resolvedRules    = array();
        $validatorFactory = $this->getValidator();

        foreach ( $rules as $i => $rule ) {
            if ( empty( $rule ) ) {
                continue;
            }
            $params = array();

            if ( is_string( $rule ) ) {
                list($rulename, $params) = $this->parseRule( $rule );
                $validator               = call_user_func_array( $validatorFactory, array_merge( array( $rulename ), $params ) );
            } elseif ( $rule instanceof Rule ) {
                $validator = $rule;
            } elseif ( $rule instanceof Closure ) {
                $validator = call_user_func_array( $validatorFactory, array( 'callback', $rule ) );
            } else {
                $ruleName = is_object( $rule ) ? get_class( $rule ) : gettype( $rule );
                $message  = "Rule must be a string, Closure or '" . Rule::class . "' instance. " . $ruleName . ' given';
                throw new \Exception();
            }

            $resolvedRules[] = $validator;
        }

        return $resolvedRules;
    }

    /**
     * Parse $rule
     *
     * @param string $rule The rule.
     * @return array
     */
    protected function parseRule( string $rule ): array {
        $exp      = explode( ':', $rule, 2 );
        $rulename = $exp[0];
        if ( 'regex' !== $rulename ) {
            $params = isset( $exp[1] ) ? explode( ',', $exp[1] ) : array();
        } else {
            $params = array( $exp[1] );
        }

        return array( $rulename, $params );
    }

    /**
     * Given $attributeKey and $alias then assign alias
     *
     * @param string $attributeKey The attribute key.
     * @param string $alias        The alias.
     * @return void
     */
    public function setAlias( string $attributeKey, string $alias ) {
        $this->aliases[ $attributeKey ] = $alias;
    }

    /**
     * Get attribute alias from given key
     *
     * @param string $attributeKey The attribute key.
     * @return string|null
     */
    public function getAlias( string $attributeKey ) {
        return isset( $this->aliases[ $attributeKey ] ) ? $this->aliases[ $attributeKey ] : null;
    }

    /**
     * Set attributes aliases
     *
     * @param array $aliases The aliases.
     * @return void
     */
    public function setAliases( array $aliases ) {
        $this->aliases = array_merge( $this->aliases, $aliases );
    }

    /**
     * Check validations are passed
     *
     * @return bool
     */
    public function passes(): bool {
        return 0 === $this->errors->count();
    }

    /**
     * Check validations are failed
     *
     * @return bool
     */
    public function fails(): bool {
        return ! $this->passes();
    }

    /**
     * Given $key and get value
     *
     * @param string $key The key.
     * @return mixed
     */
    public function getValue( string $key ) {
        return Helper::arrayGet( $this->inputs, $key );
    }

    /**
     * Set input value
     *
     * @param string $key   The key.
     * @param mixed  $value The value.
     * @return void
     */
    public function setValue( string $key, $value ) {
        Helper::arraySet( $this->inputs, $key, $value );
    }

    /**
     * Given $key and check value is exsited
     *
     * @param string $key The key.
     * @return boolean
     */
    public function hasValue( string $key ): bool {
        return Helper::arrayHas( $this->inputs, $key );
    }

    /**
     * Get Validator class instance
     *
     * @return \Rakit\Validation\Validator
     */
    public function getValidator(): Validator {
        return $this->validator;
    }

    /**
     * Given $inputs and resolve input attributes
     *
     * @param array $inputs The inputs.
     * @return array
     */
    protected function resolveInputAttributes( array $inputs ): array {
        $resolvedInputs = array();
        foreach ( $inputs as $key => $rules ) {
            $exp = explode( ':', $key );

            if ( count( $exp ) > 1 ) {
                // set attribute alias.
                $this->aliases[ $exp[0] ] = $exp[1];
            }

            $resolvedInputs[ $exp[0] ] = $rules;
        }

        return $resolvedInputs;
    }

    /**
     * Get validated data
     *
     * @return array
     */
    public function getValidatedData(): array {
        return array_merge( $this->validData, $this->invalidData );
    }

    /**
     * Set valid data
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @param mixed                       $value     The value.
     * @return void
     */
    protected function setValidData( Attribute $attribute, $value ) {
        $key = $attribute->getKey();
        if ( $attribute->isArrayAttribute() || $attribute->isUsingDotNotation() ) {
            Helper::arraySet( $this->validData, $key, $value );
            Helper::arrayUnset( $this->invalidData, $key );
        } else {
            $this->validData[ $key ] = $value;
        }
    }

    /**
     * Get valid data
     *
     * @return array
     */
    public function getValidData(): array {
        return $this->validData;
    }

    /**
     * Set invalid data
     *
     * @param \Rakit\Validation\Attribute $attribute The attribute.
     * @param mixed                       $value     The value.
     * @return void
     */
    protected function setInvalidData( Attribute $attribute, $value ) {
        $key = $attribute->getKey();
        if ( $attribute->isArrayAttribute() || $attribute->isUsingDotNotation() ) {
            Helper::arraySet( $this->invalidData, $key, $value );
            Helper::arrayUnset( $this->validData, $key );
        } else {
            $this->invalidData[ $key ] = $value;
        }
    }

    /**
     * Get invalid data
     *
     * @return array
     */
    public function getInvalidData(): array {
        return $this->invalidData;
    }
}
