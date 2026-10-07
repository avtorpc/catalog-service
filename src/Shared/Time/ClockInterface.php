<?php

namespace App\Shared\Time;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;

    public function nowFormatted(): string;
}
