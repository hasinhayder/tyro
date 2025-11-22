<?php

namespace HasinHayder\Tyro\Console\Commands;

use Illuminate\Support\Str;

class PrepareUserModelCommand extends BaseTyroCommand {
    protected $signature = 'tyro:prepare-user-model {--path= : Override the location of the User model file}';

    protected $description = 'Add HasApiTokens and HasTyroRoles traits to the default User model';

    public function handle(): int {
        $path = $this->option('path') ?: app_path('Models/User.php');

        if (!file_exists($path)) {
            $this->error(sprintf('User model not found at %s.', $path));

            return self::FAILURE;
        }

        $original = file_get_contents($path);
        $updated = $original;

        $updated = $this->ensureImports($updated);
        $updated = $this->ensureTraitUsage($updated);

        if ($updated === $original) {
            $this->info('User model already prepared.');

            return self::SUCCESS;
        }

        file_put_contents($path, $updated);

        $this->info(sprintf('Updated User model at %s.', $path));

        return self::SUCCESS;
    }

    protected function ensureImports(string $contents): string {
        $imports = [
            'Laravel\\Sanctum\\HasApiTokens',
            'HasinHayder\\Tyro\\Concerns\\HasTyroRoles',
        ];

        $missing = array_filter($imports, fn($import) => !Str::contains($contents, "use {$import};"));

        if (empty($missing)) {
            return $contents;
        }

        if (!preg_match('/namespace\s+[^;]+;\s*/', $contents, $namespaceMatch, PREG_OFFSET_CAPTURE)) {
            return $contents;
        }

        $namespaceEnd = $namespaceMatch[0][1] + strlen($namespaceMatch[0][0]);
        $classPosition = strpos($contents, 'class ');
        $insertionPoint = $namespaceEnd;

        if (preg_match_all('/\nuse\s+[^;]+;/', $contents, $useMatches, PREG_OFFSET_CAPTURE)) {
            foreach ($useMatches[0] as $match) {
                $position = $match[1];
                if ($position > $namespaceEnd && ($classPosition === false || $position < $classPosition)) {
                    $insertionPoint = $position + strlen($match[0]);
                }
            }
        }

        $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $insert = '';

        foreach ($missing as $import) {
            $insert .= 'use ' . $import . ';' . $lineEnding;
        }

        if ($insertionPoint === $namespaceEnd) {
            $insert = $lineEnding . $lineEnding . $insert;
        } elseif (!str_ends_with(substr($contents, 0, $insertionPoint), $lineEnding)) {
            $insert = $lineEnding . $insert;
        }

        return substr_replace($contents, $insert . $lineEnding, $insertionPoint, 0);
    }

    protected function ensureTraitUsage(string $contents): string {
        if (!preg_match('/class\s+User[^\{]*\{/', $contents, $classMatch, PREG_OFFSET_CAPTURE)) {
            return $contents;
        }

        $classStart = $classMatch[0][1] + strlen($classMatch[0][0]);
        $classBody = substr($contents, $classStart);

        if (Str::contains($classBody, 'HasApiTokens') && Str::contains($classBody, 'HasTyroRoles')) {
            if (preg_match('/use\s+[^;]*HasApiTokens[^;]*HasTyroRoles[^;]*;/', $classBody)) {
                return $contents;
            }
        }

        if (preg_match('/use\s+[^;]*HasApiTokens[^;]*;/', $classBody, $match, PREG_OFFSET_CAPTURE)) {
            $line = $match[0][0];

            if (!Str::contains($line, 'HasTyroRoles')) {
                $replacement = rtrim(substr($line, 0, -1)) . ', HasTyroRoles;';

                return substr_replace($contents, $replacement, $classStart + $match[0][1], strlen($line));
            }

            return $contents;
        }

        if (preg_match('/use\s+[^;]*HasTyroRoles[^;]*;/', $classBody, $match, PREG_OFFSET_CAPTURE)) {
            $line = $match[0][0];

            if (!Str::contains($line, 'HasApiTokens')) {
                $replacement = preg_replace('/use\s+/', 'use HasApiTokens, ', rtrim(substr($line, 0, -1)), 1) . ';';

                return substr_replace($contents, $replacement, $classStart + $match[0][1], strlen($line));
            }

            return $contents;
        }

        $lineEnding = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $insertion = $lineEnding . '    use HasApiTokens, HasTyroRoles;' . $lineEnding . $lineEnding;

        return substr_replace($contents, $insertion, $classStart, 0);
    }
}
