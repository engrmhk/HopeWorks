<?php

namespace App\Services\Communications\Contracts;

interface MessageProvider
{
    public function send(string $to, string $subject, string $body, array $context = []): void;
}
