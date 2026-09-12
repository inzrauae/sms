<?php

namespace Tests\Unit;

use App\Services\Sms;
use PHPUnit\Framework\TestCase;

class SmsTest extends TestCase
{
    public function test_detects_gsm7_character_set(): void
    {
        $this->assertTrue(Sms::isGsm7('Hello World 123!'));
        $this->assertTrue(Sms::isGsm7("Special: @£\$¥èéùìòÇ\nØø\rÅå"));
        $this->assertTrue(Sms::isGsm7('Extended: ^{}\\[~]|€'));

        // Sinhala
        $this->assertFalse(Sms::isGsm7('ඔබගේ මුරපදය'));
        // Tamil
        $this->assertFalse(Sms::isGsm7('வணக்கம்'));
        // Emoji
        $this->assertFalse(Sms::isGsm7('Hello 😊'));
    }

    public function test_segment_maths_for_gsm7(): void
    {
        // 160 chars fits in 1 segment
        $single = str_repeat('a', 160);
        $res = Sms::analyse($single);
        $this->assertEquals('gsm', $res['encoding']);
        $this->assertEquals('plain', $res['type']);
        $this->assertEquals(160, $res['length']);
        $this->assertEquals(1, $res['segments']);
        $this->assertEquals(0, $res['remaining']);

        // 161 chars splits into 2 segments of 153 chars each
        $multi = str_repeat('a', 161);
        $resMulti = Sms::analyse($multi);
        $this->assertEquals(2, $resMulti['segments']);
        $this->assertEquals(306, $resMulti['capacity']); // 153 * 2
        $this->assertEquals(145, $resMulti['remaining']);
    }

    public function test_segment_maths_for_unicode(): void
    {
        // Sinhala message within 70 characters
        $sinhala = 'ඔබගේ මුරපදය 4821 වේ.';
        $res = Sms::analyse($sinhala);
        $this->assertEquals('unicode', $res['encoding']);
        $this->assertEquals('unicode', $res['type']);
        $this->assertEquals(1, $res['segments']);

        // Unicode multi-segment (over 70 characters split at 67)
        $longUnicode = str_repeat('ස', 75);
        $resLong = Sms::analyse($longUnicode);
        $this->assertEquals(75, $resLong['length']);
        $this->assertEquals(2, $resLong['segments']);
    }

    public function test_normalises_phone_numbers(): void
    {
        $this->assertEquals('94712345678', Sms::normaliseNumber('0712345678'));
        $this->assertEquals('94712345678', Sms::normaliseNumber('+94712345678'));
        $this->assertEquals('94712345678', Sms::normaliseNumber('94 71 234 5678'));
        $this->assertEquals('94712345678', Sms::normaliseNumber('0094712345678'));
        $this->assertEquals('94712345678', Sms::normaliseNumber('712345678'));

        $this->assertNull(Sms::normaliseNumber(''));
        $this->assertNull(Sms::normaliseNumber('invalid'));
        $this->assertNull(Sms::normaliseNumber('123')); // too short
    }

    public function test_parses_and_deduplicates_recipients(): void
    {
        $input = "0712345678, +94712345678\n0771234567,invalid-num,0712345678";
        $parsed = Sms::parseRecipients($input);

        $this->assertCount(2, $parsed['valid']);
        $this->assertContains('94712345678', $parsed['valid']);
        $this->assertContains('94771234567', $parsed['valid']);
        $this->assertContains('invalid-num', $parsed['invalid']);
    }

    public function test_validates_sender_mask(): void
    {
        $valid = Sms::validateSenderMask('MyBrand');
        $this->assertTrue($valid['ok']);
        $this->assertEquals('MyBrand', $valid['value']);

        $numeric = Sms::validateSenderMask('12345678');
        $this->assertTrue($numeric['ok']);

        $tooLong = Sms::validateSenderMask('ThisNameIsTooLong');
        $this->assertFalse($tooLong['ok']);

        $empty = Sms::validateSenderMask('  ');
        $this->assertFalse($empty['ok']);
    }

    public function test_units_calculation(): void
    {
        // 1 segment message x 5 recipients = 5 units
        $this->assertEquals(5, Sms::unitsFor('Hello', 5));
    }
}
