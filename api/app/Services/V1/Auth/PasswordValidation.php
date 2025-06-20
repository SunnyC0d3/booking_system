<?php

namespace App\Services\V1\Auth;

use App\Constants\PasswordRequirements;
use App\Models\User;
use App\Models\PasswordHistory;
use Illuminate\Support\Facades\Hash;

class PasswordValidation
{
    protected array $errors = [];

    public function validate(string $password, ?User $user = null): bool
    {
        $this->errors = [];

        $this->validateLength($password);
        $this->validateCharacterRequirements($password);
        $this->validateCommonPasswords($password);
        $this->validateSequentialPatterns($password);
        $this->validateRepeatedCharacters($password);

        if ($user) {
            $this->validateUserSpecificRules($password, $user);
            $this->validatePasswordHistory($password, $user);
        }

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorsAsString(): string
    {
        return implode(' ', $this->errors);
    }

    protected function validateLength(string $password): void
    {
        $length = strlen($password);

        if ($length < PasswordRequirements::MIN_LENGTH) {
            $this->errors[] = "Password must be at least " . PasswordRequirements::MIN_LENGTH . " characters long.";
        }

        if ($length > PasswordRequirements::MAX_LENGTH) {
            $this->errors[] = "Password cannot exceed " . PasswordRequirements::MAX_LENGTH . " characters.";
        }
    }

    protected function validateCharacterRequirements(string $password): void
    {
        if (PasswordRequirements::REQUIRE_UPPERCASE) {
            $uppercaseCount = preg_match_all('/[A-Z]/', $password);
            if ($uppercaseCount < PasswordRequirements::MIN_UPPERCASE) {
                $this->errors[] = "Password must contain at least " . PasswordRequirements::MIN_UPPERCASE . " uppercase letter(s).";
            }
        }

        if (PasswordRequirements::REQUIRE_LOWERCASE) {
            $lowercaseCount = preg_match_all('/[a-z]/', $password);
            if ($lowercaseCount < PasswordRequirements::MIN_LOWERCASE) {
                $this->errors[] = "Password must contain at least " . PasswordRequirements::MIN_LOWERCASE . " lowercase letter(s).";
            }
        }

        if (PasswordRequirements::REQUIRE_NUMBERS) {
            $numberCount = preg_match_all('/[0-9]/', $password);
            if ($numberCount < PasswordRequirements::MIN_NUMBERS) {
                $this->errors[] = "Password must contain at least " . PasswordRequirements::MIN_NUMBERS . " number(s).";
            }
        }

        if (PasswordRequirements::REQUIRE_SYMBOLS) {
            $symbolCount = preg_match_all('/[^A-Za-z0-9]/', $password);
            if ($symbolCount < PasswordRequirements::MIN_SYMBOLS) {
                $this->errors[] = "Password must contain at least " . PasswordRequirements::MIN_SYMBOLS . " special character(s).";
            }
        }
    }

    protected function validateCommonPasswords(string $password): void
    {
        $lowerPassword = strtolower($password);

        foreach (PasswordRequirements::COMMON_PASSWORDS as $commonPassword) {
            if ($lowerPassword === strtolower($commonPassword) ||
                str_contains($lowerPassword, strtolower($commonPassword))) {
                $this->errors[] = "Password contains common words or patterns that are not secure.";
                break;
            }
        }
    }

    protected function validateSequentialPatterns(string $password): void
    {
        $lowerPassword = strtolower($password);

        foreach (PasswordRequirements::SEQUENTIAL_PATTERNS as $pattern) {
            if (str_contains($lowerPassword, $pattern)) {
                $this->errors[] = "Password cannot contain sequential patterns like '" . $pattern . "'.";
                break;
            }
        }
    }

    protected function validateRepeatedCharacters(string $password): void
    {
        $maxRepeated = PasswordRequirements::REPEATED_CHARS_MAX;

        for ($i = 0; $i <= strlen($password) - $maxRepeated; $i++) {
            $char = $password[$i];
            $count = 1;

            for ($j = $i + 1; $j < strlen($password) && $password[$j] === $char; $j++) {
                $count++;
            }

            if ($count > $maxRepeated) {
                $this->errors[] = "Password cannot contain more than {$maxRepeated} consecutive identical characters.";
                break;
            }
        }
    }

    protected function validateUserSpecificRules(string $password, User $user): void
    {
        $lowerPassword = strtolower($password);
        $userName = strtolower($user->name ?? '');
        $userEmail = strtolower($user->email ?? '');

        if ($userName && str_contains($lowerPassword, $userName)) {
            $this->errors[] = "Password cannot contain your name.";
        }

        if ($userEmail) {
            $emailParts = explode('@', $userEmail);
            if (isset($emailParts[0]) && str_contains($lowerPassword, $emailParts[0])) {
                $this->errors[] = "Password cannot contain parts of your email address.";
            }
        }

        $currentYear = date('Y');
        $lastYear = $currentYear - 1;

        if (str_contains($password, (string)$currentYear) || str_contains($password, (string)$lastYear)) {
            $this->errors[] = "Password should not contain current or recent years.";
        }
    }

    protected function validatePasswordHistory(string $password, User $user): void
    {
        $historyCount = PasswordRequirements::PASSWORD_HISTORY_COUNT;

        $passwordHistories = PasswordHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit($historyCount)
            ->get();

        foreach ($passwordHistories as $history) {
            if (Hash::check($password, $history->password_hash)) {
                $this->errors[] = "Password cannot be the same as your last {$historyCount} passwords.";
                break;
            }
        }
    }

    public function calculatePasswordStrength(string $password): array
    {
        $score = 0;
        $feedback = [];

        $length = strlen($password);
        if ($length >= 12) $score += 25;
        elseif ($length >= 8) $score += 15;
        else $feedback[] = 'Use at least 12 characters';

        if (preg_match('/[a-z]/', $password)) $score += 10;
        else $feedback[] = 'Add lowercase letters';

        if (preg_match('/[A-Z]/', $password)) $score += 10;
        else $feedback[] = 'Add uppercase letters';

        if (preg_match('/[0-9]/', $password)) $score += 10;
        else $feedback[] = 'Add numbers';

        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 15;
        else $feedback[] = 'Add special characters';

        $uniqueChars = count(array_unique(str_split($password)));
        if ($uniqueChars >= 8) $score += 15;
        elseif ($uniqueChars >= 6) $score += 10;
        else $feedback[] = 'Use more unique characters';

        if ($length >= 16) $score += 15;

        $strength = 'weak';
        if ($score >= 80) $strength = 'very strong';
        elseif ($score >= 60) $strength = 'strong';
        elseif ($score >= 40) $strength = 'medium';

        return [
            'score' => $score,
            'strength' => $strength,
            'feedback' => $feedback
        ];
    }

    public function savePasswordToHistory(User $user, string $passwordHash): void
    {
        PasswordHistory::create([
            'user_id' => $user->id,
            'password_hash' => $passwordHash,
            'created_at' => now(),
        ]);

        $historyCount = PasswordRequirements::PASSWORD_HISTORY_COUNT;

        $oldPasswords = PasswordHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->skip($historyCount)
            ->pluck('id');

        if ($oldPasswords->isNotEmpty()) {
            PasswordHistory::whereIn('id', $oldPasswords)->delete();
        }
    }
}
