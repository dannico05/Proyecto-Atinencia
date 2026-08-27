<?php

declare(strict_types=1);

namespace Src\TeachingEligibility\Teacher\Domain\Exceptions;

use DomainException;

final class TeacherHasAssignmentsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Teacher has teaching assignments and cannot be deleted.');
    }
}
