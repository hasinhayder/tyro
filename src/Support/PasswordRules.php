<?php

namespace HasinHayder\Tyro\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules {
    /**
     * Get the validation rules for password.
     *
     * @param  array  $userData  Optional user data (name, email) for checking against password
     */
    public static function get(array $userData = []): array {
        $rules = [];

        $passwordRule = Password::min(config('tyro.password.min_length', 8));

        // Complexity: Numbers
        if (config('tyro.password.complexity.require_numbers', false)) {
            $passwordRule->numbers();
        }

        // Complexity: Symbols
        if (config('tyro.password.complexity.require_special_chars', false)) {
            $passwordRule->symbols();
        }

        // Complexity: Case
        $requireUppercase = config('tyro.password.complexity.require_uppercase', false);
        $requireLowercase = config('tyro.password.complexity.require_lowercase', false);

        if ($requireUppercase && $requireLowercase) {
            $passwordRule->mixedCase();
        } else {
            if ($requireUppercase) {
                $rules[] = 'regex:/[A-Z]/';
            }
            if ($requireLowercase) {
                $rules[] = 'regex:/[a-z]/';
            }
        }

        // Common Passwords
        if (config('tyro.password.check_common_passwords', false)) {
            $passwordRule->uncompromised();
        }

        $rules[] = $passwordRule;

        // Max length
        if ($maxLength = config('tyro.password.max_length')) {
            $rules[] = 'max:'.$maxLength;
        }

        // Confirmation
        if (config('tyro.password.require_confirmation', false)) {
            $rules[] = 'confirmed';
        }

        // User info check
        if (config('tyro.password.disallow_user_info', false) && ! empty($userData)) {
            $rules[] = function ($attribute, $value, $fail) use ($userData) {
                $name = $userData['name'] ?? '';
                $email = $userData['email'] ?? '';

                if ($email && str_contains(strtolower($value), strtolower($email))) {
                    $fail('The password cannot contain your email address.');
                }

                if ($name) {
                    // split name into parts
                    $parts = array_filter(explode(' ', strtolower($name)));
                    foreach ($parts as $part) {
                        if (strlen($part) > 2 && str_contains(strtolower($value), $part)) {
                            $fail('The password cannot contain parts of your name.');
                        }
                    }
                }
            };
        }

        return $rules;
    }
}
