<?php

namespace App\Infrastructure\Application;

final class ApplicationInfo
{
    public function __construct(
        private string $name,
        private string $version,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function version(): string
    {
        return $this->version;
    }
}
