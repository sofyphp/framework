<?php

declare(strict_types=1);

namespace Sofy\Auth;

use Sofy\Support\Url;

/**
 * Add to any model that requires email verification.
 *
 *   class User extends Model implements MustVerifyEmail
 *   {
 *       use IsVerifiable;
 *   }
 */
interface MustVerifyEmail
{
    public function hasVerifiedEmail(): bool;
    public function markEmailAsVerified(): void;
    public function sendEmailVerificationNotification(): void;
    public function getEmailForVerification(): string;
}

/**
 * Default implementation of MustVerifyEmail.
 * Requires the model to have an `email` and `email_verified_at` column.
 *
 * @mixin \Sofy\Database\Model
 */
trait IsVerifiable
{
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function markEmailAsVerified(): void
    {
        $this->setAttribute('email_verified_at', date('Y-m-d H:i:s'));
        $this->save();
    }

    public function getEmailForVerification(): string
    {
        return (string) $this->getAttribute('email');
    }

    public function sendEmailVerificationNotification(): void
    {
        $id  = $this->getAttribute('id');
        $url = Url::temporarySignedRoute("/email/verify/$id", [], 60);

        // Applications should override this to send actual email.
        // Default: log the URL.
        if (function_exists('logger')) {
            logger("Email verification URL for user #$id: $url");
        }
    }

    public function verificationUrl(): string
    {
        $id = $this->getAttribute('id');
        return Url::temporarySignedRoute("/email/verify/$id", [], 60);
    }
}
