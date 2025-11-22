<?php

namespace HasinHayder\Tyro\Console\Commands;

class QuickTokenCommand extends BaseTyroCommand {
    protected $signature = 'tyro:quick-token {user? : User ID or email} {--name=Tyro Quick Token : Token name}';

    protected $description = 'Mint a Sanctum token for a user without prompting for credentials';

    public function handle(): int {
        $identifier = $this->argument('user') ?? $this->ask('User ID or email');
        $tokenName = $this->option('name') ?: 'Tyro Quick Token';

        if (!$identifier) {
            $this->error('A user identifier is required.');

            return self::FAILURE;
        }

        $user = $this->findUser($identifier);

        if (!$user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $isSuspended = method_exists($user, 'isSuspended')
            ? $user->isSuspended()
            : (bool) ($user->suspended_at ?? false);

        if ($isSuspended) {
            $reason = method_exists($user, 'getSuspensionReason')
                ? $user->getSuspensionReason()
                : ($user->suspension_reason ?? null);

            $message = 'User is suspended.';
            if ($reason) {
                $message .= ' Reason: ' . $reason;
            }

            $this->error($message);

            return self::FAILURE;
        }

        if (config('tyro.delete_previous_access_tokens_on_login', false)) {
            $user->tokens()->delete();
        }

        $token = $user->createToken($tokenName, $this->abilitiesForUser($user))->plainTextToken;

        $this->info('Token: ' . $token);
        $this->line(sprintf('User #%s (%s) now has a new token named "%s".', $user->id, $user->email, $tokenName));

        return self::SUCCESS;
    }
}
