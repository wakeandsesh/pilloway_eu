<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Storage;

interface JwtTokenStorageInterface
{
    public function save(string $token): void;

    public function get(): ?string;

    public function delete(): void;
}
