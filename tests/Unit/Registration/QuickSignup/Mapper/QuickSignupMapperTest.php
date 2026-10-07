<?php

namespace App\Tests\Application\Registration\QuickSignup\Mapper;

use App\Application\Registration\QuickSignup\DTO\AuthRequest;
use App\Application\Registration\QuickSignup\Mapper\AuthCommandMapper;
use App\Application\Registration\QuickSignup\Command\AuthCommand;
use PHPUnit\Framework\TestCase;

final class QuickSignupMapperTest extends TestCase
{
    private function request(): AuthRequest
    {
        return new AuthRequest(
            roleCode: 'seller',
            countryAlpha2: 'DE',
            email: 'test@example.com',
            acceptMarketingVer: 'v1',
            urlPageCheckout: 'https://example.com/checkout',
            userAgent: 'Mozilla',
            acceptTerms: true,
            acceptMarketing: false,
        );
    }

    public function test_map_request_to_command(): void
    {
        $request = $this->request();

        $command = AuthCommandMapper::mapRequestToCommand(
            $request,
            '127.0.0.1'
        );

        $this->assertInstanceOf(AuthCommand::class, $command);

        $this->assertSame('seller', $command->roleCode);
        $this->assertSame('DE', $command->countryAlpha2);
        $this->assertSame('test@example.com', $command->email);

        $this->assertTrue($command->acceptTerms);
        $this->assertFalse($command->acceptMarketing);

        $this->assertSame('v1', $command->acceptMarketingVer);
        $this->assertSame('https://example.com/checkout', $command->urlPageCheckout);
        $this->assertSame('Mozilla', $command->userAgent);

        $this->assertSame('127.0.0.1', $command->ipAddress);
    }
}
