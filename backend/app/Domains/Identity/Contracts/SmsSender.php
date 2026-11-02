<?php

namespace App\Domains\Identity\Contracts;

interface SmsSender
{
    public function send(string $phone, string $message): void;
}
