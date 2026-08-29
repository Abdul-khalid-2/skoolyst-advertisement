<?php

namespace Tests\Core;

use Core\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Section 13.c — one unit test per validator rule in core/Validator.php.
 * Pure logic, no database — a real unit test, not an integration one.
 */
class ValidatorTest extends TestCase
{
    public function testRequiredRejectsEmptyAndWhitespaceStrings(): void
    {
        $this->assertFalse(Validator::required(''));
        $this->assertFalse(Validator::required('   '));
        $this->assertFalse(Validator::required(null));
        $this->assertFalse(Validator::required([]));
    }

    public function testRequiredAcceptsNonEmptyValues(): void
    {
        $this->assertTrue(Validator::required('a'));
        $this->assertTrue(Validator::required('0'));
        $this->assertTrue(Validator::required(0));
        $this->assertTrue(Validator::required(['x']));
    }

    public function testMaxLengthUsesMultibyteLength(): void
    {
        $this->assertTrue(Validator::maxLength('hello', 5));
        $this->assertFalse(Validator::maxLength('hello!', 5));
        // Multi-byte string — mb_strlen must count characters, not bytes.
        $this->assertTrue(Validator::maxLength('café', 4));
    }

    public function testUrlAcceptsOnlyValidUrls(): void
    {
        $this->assertTrue(Validator::url('https://example.com/land'));
        $this->assertFalse(Validator::url('not a url'));
        $this->assertFalse(Validator::url(''));
    }

    public function testDateAcceptsOnlyExactYmdFormat(): void
    {
        $this->assertTrue(Validator::date('2026-08-29'));
        // Real calendar validity, not just the right shape.
        $this->assertFalse(Validator::date('2026-02-30'));
        $this->assertFalse(Validator::date('29-08-2026'));
        $this->assertFalse(Validator::date('not-a-date'));
    }
}
