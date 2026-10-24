<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Crypto\Keys;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('campaign:keys {--show : Print new keys instead of writing .env} {--force : Replace existing keys (destroys access to encrypted data)}')]
#[Description('Generate PHONE_HASH_KEY and FIELD_ENCRYPTION_KEY')]
final class GenerateDataKeys extends Command
{
    public function handle(): int
    {
        $keys = ['PHONE_HASH_KEY' => Keys::generate(), 'FIELD_ENCRYPTION_KEY' => Keys::generate()];

        if ($this->option('show')) {
            foreach ($keys as $name => $value) {
                $this->line("{$name}={$value}");
            }

            return self::SUCCESS;
        }

        $path = base_path('.env');
        if (! is_file($path)) {
            $this->error('.env not found. Copy .env.example first.');

            return self::FAILURE;
        }

        $env = (string) file_get_contents($path);

        foreach ($keys as $name => $value) {
            $existing = preg_match("/^{$name}=([^\\s#]\\S*)/m", $env) === 1;

            if ($existing && ! $this->option('force')) {
                $this->warn("{$name} already set; left unchanged.");

                continue;
            }

            if ($existing && ! $this->confirm("Replacing {$name} makes existing encrypted data or hashes unusable. Continue?", false)) {
                continue;
            }

            $env = preg_match("/^{$name}=.*$/m", $env) === 1
                ? (string) preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env)."\n{$name}={$value}\n";

            $this->info("{$name} written.");
        }

        file_put_contents($path, $env);

        return self::SUCCESS;
    }
}
