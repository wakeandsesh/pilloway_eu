<?php
/**
 * Mimes Rule File
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */

namespace NovaPostForWoocommerce\Rakit\Validation\Rules;

use NovaPostForWoocommerce\Rakit\Validation\Helper;
use NovaPostForWoocommerce\Rakit\Validation\MimeTypeGuesser;
use NovaPostForWoocommerce\Rakit\Validation\Rule;

/**
 * Mimes Rule Class
 *
 * @package NovaPostForWoocommerce\Rakit\Validation\Rules
 */
class Mimes extends Rule {

    use Traits\FileTrait;

    /**
     * The rule error message
     *
     * @var string
     */
    protected $message = 'The :attribute file type must be :values';

    /**
     * The max size
     *
     * @var string|int
     */
    protected $maxSize = null;

    /**
     * The min size
     *
     * @var string|int
     */
    protected $minSize = null;

    /**
     * The allowed types
     *
     * @var array
     */
    protected $allowedTypes = array();

    /**
     * Given $params and assign $this->params
     *
     * @param array $params The parameters.
     * @return self
     */
    public function fillParameters( array $params ): Rule {
        $this->allowTypes( $params );
        return $this;
    }

    /**
     * Given $types and assign $this->params
     *
     * @param mixed $types The allowed types.
     * @return self
     */
    public function allowTypes( $types ): Rule {
        if ( is_string( $types ) ) {
            $types = explode( '|', $types );
        }

        $this->params['values'] = $types;

        return $this;
    }

    /**
     * Check the $value is valid
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    public function check( $value ): bool {
        $allowedTypes = $this->parameter( 'values' );

        if ( $allowedTypes ) {
            $or = $this->validation ? $this->validation->getTranslation( 'or' ) : 'or';
            $this->setParameterText( 'values', Helper::join( Helper::wraps( $allowedTypes, "'" ), ', ', ", {$or} " ) );
        }

        // below is Required rule job.
        if ( ! $this->isValueFromUploadedFiles( $value ) || UPLOAD_ERR_NO_FILE === $value['error'] ) {
            return true;
        }

        if ( ! $this->isUploadedFile( $value ) ) {
            return false;
        }

        // just make sure there is no error.
        if ( $value['error'] ) {
            return false;
        }

        if ( ! empty( $allowedTypes ) ) {
            $guesser = new MimeTypeGuesser();
            $ext     = $guesser->getExtension( $value['type'] );
            unset( $guesser );

            if ( ! in_array( $ext, $allowedTypes, true ) ) {
                return false;
            }
        }

        return true;
    }
}
