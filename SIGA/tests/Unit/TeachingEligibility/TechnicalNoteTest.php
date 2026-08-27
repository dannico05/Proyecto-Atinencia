<?php

declare(strict_types=1);

namespace Tests\Unit\TeachingEligibility;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\TeachingEligibility\Verification\Domain\Entities\TechnicalNote;

final class TechnicalNoteTest extends TestCase
{
    #[Test]
    public function a_pending_note_is_automatically_effective_as_expired_after_its_deadline(): void
    {
        $note = new TechnicalNote(1, 'technical-notes/note.pdf', new DateTimeImmutable('2026-08-20'), 'pending');

        self::assertSame('expired', $note->effectiveStatus(new DateTimeImmutable('2026-08-21')));
    }

    #[Test]
    public function a_resolved_note_never_changes_to_expired(): void
    {
        $note = new TechnicalNote(1, 'technical-notes/note.pdf', new DateTimeImmutable('2026-08-20'), 'ratified');

        self::assertSame('ratified', $note->effectiveStatus(new DateTimeImmutable('2026-08-21')));
    }
}
