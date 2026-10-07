<?php

namespace App\Infrastructure\EventPublisher;

use App\Domain\Event\EmailVerificationEvent;

interface EmailEventPublisherInterface
{
    public function publish(EmailVerificationEvent $event): void;
}
