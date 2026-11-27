<?php

namespace App\Services\Businesses\Commands;

use App\Models\Business;
use App\Models\User;
use App\Services\Audit\Commands\RecordAuditLogCommand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class UpdateBusinessCommand
{
    public function __construct(public RecordAuditLogCommand $audit) {}

    /** @param array<string, mixed> $attributes */
    public function execute(int $businessId, int $userId, array $attributes): Business
    {
        $actor = User::query()->findOrFail($userId);
        abort_unless($actor->hasBusinessPermission($businessId, 'settings.update'), 403);
        if (array_diff(array_keys($attributes), [
            'name', 'phone', 'email', 'timezone', 'currency', 'description', 'address',
            'public_review_url', 'logo', 'remove_logo',
        ]) !== []) {
            throw ValidationException::withMessages(['business' => 'The update contains fields that cannot be changed.']);
        }

        $logo = $attributes['logo'] ?? null;
        if ($logo !== null && ! $logo instanceof UploadedFile) {
            throw ValidationException::withMessages(['logo' => 'Choose a valid business logo image.']);
        }
        $removeLogo = filter_var($attributes['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($removeLogo && $logo instanceof UploadedFile) {
            throw ValidationException::withMessages(['logo' => 'Choose a new logo or remove the current logo, not both.']);
        }

        $newLogoPath = $logo instanceof UploadedFile
            ? $logo->store("businesses/{$businessId}/logo", 'local')
            : null;
        if ($logo instanceof UploadedFile && ! is_string($newLogoPath)) {
            throw ValidationException::withMessages(['logo' => 'The business logo could not be stored. Try again.']);
        }

        try {
            [$business, $oldLogoPath] = DB::transaction(function () use ($businessId, $userId, $attributes, $newLogoPath, $removeLogo): array {
                $business = Business::query()->lockForUpdate()->findOrFail($businessId);
                $auditFields = [
                    'name', 'phone', 'email', 'description', 'address', 'public_review_url',
                    'timezone', 'currency', 'logo_path',
                ];
                $before = $business->only($auditFields);
                $oldLogoPath = $business->logo_path;
                $profile = array_intersect_key($attributes, array_flip([
                    'name', 'phone', 'email', 'description', 'address', 'public_review_url', 'timezone', 'currency',
                ]));
                if (is_string($newLogoPath)) {
                    $profile['logo_path'] = $newLogoPath;
                } elseif ($removeLogo) {
                    $profile['logo_path'] = null;
                }

                $business->update($profile);
                $this->audit->execute(
                    $businessId,
                    $userId,
                    'business.settings_updated',
                    Business::class,
                    $business->getKey(),
                    $before,
                    $business->only($auditFields),
                );

                return [$business->refresh(), $oldLogoPath];
            });
        } catch (Throwable $exception) {
            if (is_string($newLogoPath)) {
                Storage::disk('local')->delete($newLogoPath);
            }

            throw $exception;
        }

        if (is_string($oldLogoPath) && ($removeLogo || is_string($newLogoPath)) && $oldLogoPath !== $newLogoPath) {
            Storage::disk('local')->delete($oldLogoPath);
        }

        return $business;
    }
}
