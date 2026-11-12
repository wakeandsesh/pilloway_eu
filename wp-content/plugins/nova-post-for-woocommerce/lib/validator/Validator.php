<?php
/**
 * Validator File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */

namespace NovaPostForWoocommerce\Rakit\Validation;

/**
 * Validator Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation
 */
class Validator {

    use Traits\TranslationsTrait;
    use Traits\MessagesTrait;

    /**
     * The translations
     *
     * @var array
     */
    protected $translations = array();

    /**
     * The validators
     *
     * @var array
     */
    protected $validators = array();

    /**
     * Allow rule override
     *
     * @var bool
     */
    protected $allowRuleOverride = false;

    /**
     * Use humanized keys
     *
     * @var bool
     */
    protected $useHumanizedKeys = true;

    /**
     * Constructor
     *
     * @param array $messages The messages.
     * @return void
     */
    public function __construct( array $messages = array() ) {
        $this->messages = $messages;
        $this->registerBaseValidators();
    }

    /**
     * Register or override existing validator
     *
     * @param string                        $key  The key.
     * @param \Rakit\Validation\Rule|string $rule The rule.
     * @return void
     */
    public function setValidator( string $key, $rule ) {
        $this->validators[ $key ] = $rule;
        if ( $rule instanceof Rule ) {
            $rule->setKey( $key );
        }
    }

    /**
     * Get validator object from given $key
     *
     * @param mixed $key The key.
     * @return mixed
     * @throws \Exception Validator exception.
     */
    public function getValidator( $key ) {
        if ( ! isset( $this->validators[ $key ] ) ) {
            return null;
        }

        $validator = $this->validators[ $key ];

        if ( $validator instanceof Rule ) {
            return $validator;
        }

        if ( is_string( $validator ) ) {
            $validator = new $validator();
        } elseif ( $validator instanceof \Closure ) {
            $validator = $validator( $this );
        }

        if ( ! $validator instanceof Rule ) {
            throw new \Exception( "Validator '{$key}' must be an instance of " . Rule::class ); // phpcs:ignore
        }

        $validator->setKey( $key );
        $this->validators[ $key ] = $validator;

        return $validator;
    }

    /**
     * Validate $inputs
     *
     * @param array $inputs   The inputs.
     * @param array $rules    The rules.
     * @param array $messages The messages.
     * @return Validation
     */
    public function validate( array $inputs, array $rules, array $messages = array() ): Validation {
        $validation = $this->make( $inputs, $rules, $messages );
        $validation->validate();
        return $validation;
    }

    /**
     * Given $inputs, $rules and $messages to make the Validation class instance
     *
     * @param array $inputs   The inputs.
     * @param array $rules    The rules.
     * @param array $messages The messages.
     * @return Validation
     */
    public function make( array $inputs, array $rules, array $messages = array() ): Validation {
        $messages   = array_merge( $this->messages, $messages );
        $validation = new Validation( $this, $inputs, $rules, $messages );
        $validation->setTranslations( $this->getTranslations() );

        return $validation;
    }

    /**
     * Magic invoke method to make Rule instance
     *
     * @param string $rule The rule.
     * @return Rule
     * @throws RuleNotFoundException Rule not found exception.
     */
    public function __invoke( string $rule ): Rule {
        $args      = func_get_args();
        $rule      = array_shift( $args );
        $params    = $args;
        $validator = $this->getValidator( $rule );
        if ( ! $validator ) {
            throw new RuleNotFoundException( "Validator '{$rule}' is not registered", 1 ); // phpcs:ignore
        }

        $clonedValidator = clone $validator;
        $clonedValidator->fillParameters( $params );

        return $clonedValidator;
    }

    /**
     * Initialize base validators array
     *
     * @return void
     */
    protected function registerBaseValidators() {
        $baseValidator = array(
            'required'             => Rules\Required::class,
            'required_if'          => Rules\RequiredIf::class,
            'required_unless'      => Rules\RequiredUnless::class,
            'required_with'        => Rules\RequiredWith::class,
            'required_without'     => Rules\RequiredWithout::class,
            'required_with_all'    => Rules\RequiredWithAll::class,
            'required_without_all' => Rules\RequiredWithoutAll::class,
            'email'                => Rules\Email::class,
            'alpha'                => Rules\Alpha::class,
            'numeric'              => Rules\Numeric::class,
            'alpha_num'            => Rules\AlphaNum::class,
            'alpha_dash'           => Rules\AlphaDash::class,
            'alpha_spaces'         => Rules\AlphaSpaces::class,
            'in'                   => Rules\In::class,
            'not_in'               => Rules\NotIn::class,
            'min'                  => Rules\Min::class,
            'max'                  => Rules\Max::class,
            'between'              => Rules\Between::class,
            'url'                  => Rules\Url::class,
            'integer'              => Rules\Integer::class,
            'boolean'              => Rules\Boolean::class,
            'ip'                   => Rules\Ip::class,
            'ipv4'                 => Rules\Ipv4::class,
            'ipv6'                 => Rules\Ipv6::class,
            'extension'            => Rules\Extension::class,
            'array'                => Rules\TypeArray::class,
            'string'               => Rules\TypeString::class,
            'same'                 => Rules\Same::class,
            'regex'                => Rules\Regex::class,
            'date'                 => Rules\Date::class,
            'accepted'             => Rules\Accepted::class,
            'present'              => Rules\Present::class,
            'filled'               => Rules\Filled::class,
            'required_if_filled'   => Rules\RequiredIfFilled::class,
            'different'            => Rules\Different::class,
            'uploaded_file'        => Rules\UploadedFile::class,
            'mimes'                => Rules\Mimes::class,
            'callback'             => Rules\Callback::class,
            'before'               => Rules\Before::class,
            'after'                => Rules\After::class,
            'after_or_equal'       => Rules\AfterOrEqual::class,
            'lowercase'            => Rules\Lowercase::class,
            'uppercase'            => Rules\Uppercase::class,
            'size'                 => Rules\Size::class,
            'json'                 => Rules\Json::class,
            'digits'               => Rules\Digits::class,
            'digits_between'       => Rules\DigitsBetween::class,
            'defaults'             => Rules\Defaults::class,
            'default'              => Rules\Defaults::class, // alias of defaults.
            'nullable'             => Rules\Nullable::class,
        );

        foreach ( $baseValidator as $key => $validator ) {
            $this->setValidator( $key, $validator );
        }
    }

    /**
     * Given $ruleName and $rule to add new validator
     *
     * @param string                        $ruleName The rule name.
     * @param \Rakit\Validation\Rule|string $rule     The rule.
     * @return void
     * @throws RuleQuashException Rule quash exception.
     */
    public function addValidator( string $ruleName, $rule ) {
        if ( ! $this->allowRuleOverride && array_key_exists( $ruleName, $this->validators ) ) {
            throw new RuleQuashException(
                'You cannot override a built in rule. You have to rename your rule'
            );
        }

        $this->setValidator( $ruleName, $rule );
    }

    /**
     * Set rule can allow to be overrided
     *
     * @param boolean $status The status.
     * @return void
     */
    public function allowRuleOverride( bool $status = false ) {
        $this->allowRuleOverride = $status;
    }

    /**
     * Set this can use humanize keys
     *
     * @param boolean $useHumanizedKeys The use humanized keys.
     * @return void
     */
    public function setUseHumanizedKeys( bool $useHumanizedKeys = true ) {
        $this->useHumanizedKeys = $useHumanizedKeys;
    }

    /**
     * Get $this->useHumanizedKeys value
     *
     * @return bool
     */
    public function isUsingHumanizedKey(): bool {
        return $this->useHumanizedKeys;
    }
}
