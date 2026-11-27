<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Automattic\WooCommerce\GoogleListingsAndAds\Vendor\Symfony\Component\Validator\Constraints;

use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\Symfony\Component\Validator\Constraint;
use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\Symfony\Component\Validator\ConstraintValidator;
use Automattic\WooCommerce\GoogleListingsAndAds\Vendor\Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class BlankValidator extends ConstraintValidator
{
    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Blank) {
            throw new UnexpectedTypeException($constraint, Blank::class);
        }

        if ('' !== $value && null !== $value) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $this->formatValue($value))
                ->setCode(Blank::NOT_BLANK_ERROR)
                ->addViolation();
        }
    }
}
