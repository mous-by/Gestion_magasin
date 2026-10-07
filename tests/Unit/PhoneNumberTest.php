<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberTest extends TestCase
{
    #[DataProvider('accepted')]
    public function test_common_writings_normalize_to_e164(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::normalize($input));
    }

    public static function accepted(): array
    {
        return [
            'local 8 chiffres' => ['70000001', '+22370000001'],
            'avec espaces' => ['70 00 00 01', '+22370000001'],
            'avec points' => ['70.00.00.01', '+22370000001'],
            'avec tirets' => ['70-00-00-01', '+22370000001'],
            'international +' => ['+223 70 00 00 01', '+22370000001'],
            'international 00' => ['00223 70 00 00 01', '+22370000001'],
            'indicatif sans plus' => ['223 70 00 00 01', '+22370000001'],
            'parenthèses' => ['(+223) 70 00 00 01', '+22370000001'],
            'autre pays' => ['+33 6 12 34 56 78', '+33612345678'],
            'espaces de bord' => ['  70 00 00 01  ', '+22370000001'],
        ];
    }

    #[DataProvider('refused')]
    public function test_unusable_input_is_refused(?string $input): void
    {
        $this->assertNull(PhoneNumber::normalize($input));
        $this->assertFalse(PhoneNumber::isValid($input));
    }

    public static function refused(): array
    {
        return [
            'null' => [null], 'vide' => [''], 'espaces' => ['   '], 'lettres' => ['abcdefgh'], 'trop court' => ['7000'],
            '7 chiffres' => ['7000000'], '9 chiffres locaux' => ['700000012'], 'plus seul' => ['+'], 'mélange' => ['70 00 ab 01'],
            'indicatif 0' => ['+0123456789'],
        ];
    }

    public function test_the_default_country_code_is_configurable(): void
    {
        config(['authorization.default_country_code' => '225']);

        $this->assertSame('+22507070707', PhoneNumber::normalize('07070707'));
    }
}
