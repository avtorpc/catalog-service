<?php

namespace App\Tests\Unit\Registration\QuickSignup\Mapper;

use PHPUnit\Framework\TestCase;
use App\Application\Registration\QuickSignup\Mapper\AuthJsonMapper;
use App\Shared\Exception\BadRequestException;

final class QuickSignupJsonMapperTest extends TestCase
{
    public function test_valid_json_is_mapped_to_raw_dto(): void
    {
        $json = json_encode([
            'roleCode' => 'SELLER',
            'countryAlpha2' => 'RU',
            'email' => 'test@test.com',
            'acceptMarketingVer' => '1.0',
            'urlPageCheckout' => 'https://example.com',
            'userAgent' => 'Mozilla',
            'acceptTerms' => true,
            'acceptMarketing' => false,
        ]);

        $raw = AuthJsonMapper::fromJson($json);

        $this->assertSame('SELLER', $raw->roleCode);
        $this->assertSame('RU', $raw->countryAlpha2);
        $this->assertSame('test@test.com', $raw->email);
        $this->assertSame('1.0', $raw->acceptMarketingVer);
        $this->assertSame('https://example.com', $raw->urlPageCheckout);
        $this->assertSame('Mozilla', $raw->userAgent);

        $this->assertTrue($raw->acceptTerms);
        $this->assertFalse($raw->acceptMarketing);
    }

    public function test_invalid_json_throws_exception(): void
    {
        $this->expectException(BadRequestException::class);

        AuthJsonMapper::fromJson('INVALID_JSON');
    }

    public function test_missing_fields_are_null_in_raw_dto(): void
    {
        $json = json_encode([
            'roleCode' => 'SELLER'
        ]);

        $raw = AuthJsonMapper::fromJson($json);

        $this->assertSame('SELLER', $raw->roleCode);

        $this->assertNull($raw->countryAlpha2);
        $this->assertNull($raw->email);
        $this->assertNull($raw->acceptMarketingVer);
        $this->assertNull($raw->urlPageCheckout);
        $this->assertNull($raw->userAgent);
        $this->assertNull($raw->acceptMarketing);
        $this->assertNull($raw->acceptTerms);
    }
}
