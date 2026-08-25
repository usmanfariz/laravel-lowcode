<?php

namespace App\Exceptions;

use RuntimeException;

class DataSourceException extends RuntimeException
{
    public static function notWhitelisted(string $table): self
    {
        return new self("Tabel '{$table}' tidak terdaftar di data_sources.");
    }

    public static function notReadable(string $table): self
    {
        return new self("Tabel '{$table}' tidak diizinkan dibaca.");
    }

    public static function notWritable(string $table): self
    {
        return new self("Tabel '{$table}' tidak diizinkan ditulis.");
    }

    public static function blockedColumn(string $table, string $column): self
    {
        return new self("Kolom '{$column}' pada tabel '{$table}' diblokir.");
    }

    public static function unknownColumn(string $table, string $column): self
    {
        return new self("Kolom '{$column}' tidak ada pada tabel '{$table}'.");
    }
}
