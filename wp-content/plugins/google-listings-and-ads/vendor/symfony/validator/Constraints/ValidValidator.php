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
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class ValidValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof Valid) {
            throw new UnexpectedTypeException($constraint, Valid::class);
        }

        if (null === $value) {
            return;
        }

        $this->context
            ->getValidator()
            ->inContext($this->context)
            ->validate($value, null, $this->context->getGroup());
    }
}
