<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SenderProfileService
{
    /**
     * Get all active, interactive sender profiles that the given user is authorized to use.
     * Automated system profiles (is_system = true) are strictly excluded.
     *
     * @return Collection<int, EmailSenderProfile>
     */
    public function getAuthorizedProfiles(User $user): Collection
    {
        $profiles = EmailSenderProfile::query()
            ->active()
            ->interactive()
            ->get();

        return $profiles->filter(fn (EmailSenderProfile $profile) => Gate::forUser($user)->allows('use', $profile))->values();
    }

    /**
     * Get the default sender profile authorized for the user, falling back to their first authorized profile.
     */
    public function getDefaultAuthorizedProfile(User $user): ?EmailSenderProfile
    {
        $authorized = $this->getAuthorizedProfiles($user);

        return $authorized->firstWhere('is_default', true) ?? $authorized->first();
    }

    /**
     * Resolve and authorize a sender profile for email creation.
     *
     * - If profileId is given: ensures it exists, is active, is NOT system, and user can use it.
     * - If profileId is null: resolves user's default authorized profile.
     *
     * @throws ValidationException
     */
    public function resolveAuthorizedProfile(User $user, ?int $profileId): EmailSenderProfile
    {
        if ($profileId !== null) {
            $profile = EmailSenderProfile::find($profileId);

            if (! $profile) {
                throw ValidationException::withMessages([
                    'sender_profile_id' => ['The selected sender profile does not exist.'],
                ]);
            }

            if ($profile->is_system) {
                throw ValidationException::withMessages([
                    'sender_profile_id' => ['The system sender identity is reserved for automated system emails.'],
                ]);
            }

            if (! Gate::forUser($user)->allows('use', $profile)) {
                throw ValidationException::withMessages([
                    'sender_profile_id' => ['You are not authorized to send emails using this sender identity.'],
                ]);
            }

            return $profile;
        }

        $defaultProfile = $this->getDefaultAuthorizedProfile($user);

        if (! $defaultProfile) {
            throw ValidationException::withMessages([
                'sender_profile_id' => ['You are not authorized to send emails from any sender profile.'],
            ]);
        }

        return $defaultProfile;
    }
}
