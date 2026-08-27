<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Verification\Domain\Exceptions;

use DomainException;

final class AssignmentCannotBeReverifiedException extends DomainException
{
    public function __construct()
    {
        parent::__construct('This assignment cannot be verified again because a manual or Technical Note decision has already started.');
    }
}
