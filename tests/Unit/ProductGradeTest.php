<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Inventory\ProductGrade;
use PHPUnit\Framework\TestCase;

class ProductGradeTest extends TestCase
{
    public function test_grade_suffixes(): void
    {
        $this->assertSame('(A)', ProductGrade::GradeA->suffix());
        $this->assertSame('(B)', ProductGrade::GradeB->suffix());
        $this->assertSame('(C)', ProductGrade::GradeC->suffix());
        $this->assertSame('(Mixed)', ProductGrade::Unsorted->suffix());
        $this->assertSame('(DMG)', ProductGrade::Damage->suffix());
    }

    public function test_format_name_with_grade_appends_suffix(): void
    {
        $this->assertSame('Tomato H (A)', ProductGrade::formatNameWithGrade('Tomato H', 'A'));
        $this->assertSame('Tomato H (B)', ProductGrade::formatNameWithGrade('Tomato H', 'B'));
        $this->assertSame('Tomato H (C)', ProductGrade::formatNameWithGrade('Tomato H', 'C'));
        $this->assertSame('Tomato H (Mixed)', ProductGrade::formatNameWithGrade('Tomato H', 'MIXED'));
        $this->assertSame('Tomato H (Mixed)', ProductGrade::formatNameWithGrade('Tomato H', 'U'));
        $this->assertSame('Tomato H (Mixed)', ProductGrade::formatNameWithGrade('Tomato H', ProductGrade::Unsorted));
    }

    public function test_format_name_with_grade_prevents_duplicate_suffix(): void
    {
        $this->assertSame('Tomato H (A)', ProductGrade::formatNameWithGrade('Tomato H (A)', 'A'));
        $this->assertSame('Tomato H (B)', ProductGrade::formatNameWithGrade('Tomato H (B)', 'B'));
        $this->assertSame('Tomato H (Mixed)', ProductGrade::formatNameWithGrade('Tomato H (Mixed)', 'MIXED'));
    }

    public function test_format_name_with_grade_legacy_or_empty_does_not_invent_grade_a(): void
    {
        $this->assertSame('Tomato H', ProductGrade::formatNameWithGrade('Tomato H', null));
        $this->assertSame('Tomato H', ProductGrade::formatNameWithGrade('Tomato H', ''));
        $this->assertSame('Tomato H', ProductGrade::formatNameWithGrade('Tomato H', 'UNKNOWN'));
    }
}
