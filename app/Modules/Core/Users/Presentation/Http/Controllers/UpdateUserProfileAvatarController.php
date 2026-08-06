<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Presentation\Http\Controllers;

use App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle;
use App\Modules\Core\Files\Application\Public\Contracts\FileScanner;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserProfileAvatarController
{
    public function __construct(
        private FileStorage $files,
        private FileLifecycle $fileLifecycle,
        private FileScanner $fileScanner,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'avatar_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar_image' => ['boolean'],
        ]);

        $user = $request->user();
        $userId = $this->intValue(data_get($user, 'id'));
        $userPublicId = $this->stringValue(data_get($user, 'public_id'));

        $updates = [
            'avatar_color' => $request->filled('avatar_color') ? $request->string('avatar_color')->toString() : null,
        ];

        if ($request->boolean('remove_avatar_image')) {
            $this->deleteExistingAvatar($userId, data_get($user, 'avatar_image_file_public_id'), 'User removed profile avatar image.');
            $updates['avatar_image_file_public_id'] = null;
        } elseif ($request->hasFile('avatar_image')) {
            $file = $request->file('avatar_image');
            $stored = $file === null ? null : $this->files->storeUpload($file, $userId, null, [
                'owner_type' => 'user_profile_avatar',
                'owner_public_id' => $userPublicId,
            ]);

            if ($stored !== null && $this->fileScanner->scanNow($stored->publicId)->blocksUse()) {
                throw ValidationException::withMessages([
                    'avatar_image' => __('validation.upload_security_scan_failed'),
                ]);
            }

            $this->deleteExistingAvatar($userId, data_get($user, 'avatar_image_file_public_id'), 'User replaced profile avatar image.');
            $updates['avatar_image_file_public_id'] = $stored?->publicId;
        }

        DB::table(IdentityDatabaseTable::USERS)
            ->where('id', $userId)
            ->update(array_merge($updates, ['updated_at' => now()]));

        return back()->with('flash.messages', [
            FlashMessage::success('flash.user_profile.avatar_updated'),
        ]);
    }

    private function deleteExistingAvatar(int $userId, mixed $filePublicId, string $reason): void
    {
        if (is_string($filePublicId) && $filePublicId !== '') {
            $this->fileLifecycle->delete($filePublicId, $userId, null, $reason);
        }
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : 0);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
