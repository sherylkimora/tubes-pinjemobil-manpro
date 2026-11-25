<?php
declare(strict_types=1);

namespace Pinjemobil;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function required(string ...$fields): self
    {
        foreach ($fields as $f) {
            $v = $this->data[$f] ?? null;
            if ($v === null || (is_string($v) && trim($v) === '')) {
                $this->errors[$f] = "$f wajib diisi.";
            }
        }
        return $this;
    }

    public function username(string $f = 'username'): self
    {
        $v = (string) ($this->data[$f] ?? '');
        if ($v !== '' && !preg_match('/^[a-z0-9]{3,20}$/', $v)) {
            $this->errors[$f] ??= 'Username 3-20 karakter, hanya huruf kecil dan angka.';
        }
        return $this;
    }

    public function password(string $f = 'password'): self
    {
        $v = (string) ($this->data[$f] ?? '');
        if ($v !== '' && !self::strongPassword($v)) {
            $this->errors[$f] ??= 'Password minimal 8 karakter, wajib 1 huruf besar, 1 angka, dan 1 simbol.';
        }
        return $this;
    }

    public function email(string $f = 'email'): self
    {
        $v = (string) ($this->data[$f] ?? '');
        if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$f] ??= 'Format email tidak valid.';
        }
        return $this;
    }

    public function phone(string $f = 'nomor_telepon'): self
    {
        $v = (string) ($this->data[$f] ?? '');
        if ($v !== '' && !preg_match('/^[0-9]{10,15}$/', $v)) {
            $this->errors[$f] ??= 'Nomor telepon harus angka 10-15 digit.';
        }
        return $this;
    }

    public function in(string $f, array $allowed): self
    {
        $v = $this->data[$f] ?? null;
        if ($v !== null && !in_array($v, $allowed, true)) {
            $this->errors[$f] ??= "$f harus salah satu dari: " . implode(', ', $allowed) . '.';
        }
        return $this;
    }

    public function integer(string ...$fields): self
    {
        foreach ($fields as $f) {
            $v = $this->data[$f] ?? null;
            if ($v !== null && filter_var($v, FILTER_VALIDATE_INT) === false) {
                $this->errors[$f] ??= "$f harus berupa angka bulat.";
            }
        }
        return $this;
    }

    public function validate(): void
    {
        if ($this->errors) {
            throw new HttpException(422, 'Data tidak valid.', $this->errors);
        }
    }

    public static function strongPassword(string $p): bool
    {
        return (bool) preg_match('/^(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/', $p);
    }
}
