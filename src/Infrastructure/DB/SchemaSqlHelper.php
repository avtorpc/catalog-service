<?php

namespace App\Infrastructure\DB;

final class SchemaSqlHelper
{
    public function __construct(
        private string $schema
    ) {}

    public function table(string $table): string
    {
        $this->assertValidIdentifier($this->schema);
        $this->assertValidIdentifier($table);

        return $this->schema . '.' . $table;
    }

    private function assertValidIdentifier(string $name): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException("Invalid SQL identifier: {$name}");
        }
    }
}
