<?php

declare(strict_types=1);

namespace NovaDigital\NovaPost\Storage;

class FileJwtTokenStorage implements JwtTokenStorageInterface
{
    private const FILE_PATH = 'novapost_api_sdk_jwt_token.json';
    private string $filePath;

    public function __construct()
    {
        $this->filePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR .  self::FILE_PATH;
    }

    public function save(string $token): void
    {
        $data = ['token' => $token];
        file_put_contents($this->filePath, json_encode($data), LOCK_EX);
    }

    public function get(): ?string
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $content = file_get_contents($this->filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data['token'])) {
            @unlink($this->filePath);
            return null;
        }

        return $data['token'];
    }

    public function delete(): void
    {
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }
}
