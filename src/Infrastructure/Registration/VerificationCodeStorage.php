<?php
namespace App\Infrastructure\Registration;

use App\Domain\Registration\SignupRequestStorageInterface;
use App\Infrastructure\DB\SchemaSqlHelper;
use Doctrine\DBAL\Connection;

class VerificationCodeStorage implements SignupRequestStorageInterface
{
    private const TABLE = 'signup_requests';

    public function __construct(
        private Connection $connection,
        private readonly SchemaSqlHelper $schemaSqlHelper
    ) {}

    public function getCodeByRequestId(string $requestId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT verification_code, verification_attempts, is_verified
             FROM ' . $this->schemaSqlHelper->table(self::TABLE) . '
             WHERE request_id = :requestId',
            ['requestId' => $requestId]
        );

        return $row ?: null;
    }

    public function markVerified(string $requestId): void
    {
        $this->connection->executeStatement(
            'UPDATE ' . $this->schemaSqlHelper->table(self::TABLE) . '
             SET is_verified = true
             WHERE request_id = :requestId',
            ['requestId' => $requestId]
        );
    }

    /**
     * Имя стоража (аналог getName() у словарей)
     */
    public function getName(): string
    {
        return 'verification-code-db';
    }
}
