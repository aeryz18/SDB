<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Contract\Database;

class FirebaseReader
{
    public function __construct(private readonly Database $database) {}

    public function read(string $path): array
    {
        $snapshot = $this->database->getReference($path)->getSnapshot();
        $value = $snapshot->getValue();

        if (is_array($value)) {
            return $value;
        }

        return $value !== null ? ['value' => $value] : [];
    }

    public function set(string $path, mixed $value): void
    {
        $this->database->getReference($path)->set($value);
    }
}
