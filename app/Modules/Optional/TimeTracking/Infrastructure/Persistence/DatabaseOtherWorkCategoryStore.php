<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Optional\TimeTracking\Application\Contracts\OtherWorkCategoryStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\OtherWorkCategory;
use App\Modules\Optional\TimeTracking\Application\Enums\OtherWorkCategoryScope;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DatabaseOtherWorkCategoryStore implements OtherWorkCategoryStore
{
    public function __construct(private ConnectionInterface $database) {}

    public function activeForTeam(int $teamId): array
    {
        $rows = $this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('is_active', true)
            ->where('scope_type', OtherWorkCategoryScope::Team->value)
            ->where('scope_id', $teamId)
            ->orderBy('category_key')
            ->get([
                'public_id',
                'scope_type',
                'scope_id',
                'category_key',
                'label_pl',
                'label_en',
                'description_pl',
                'description_en',
                'requires_comment',
                'auto_approval_enabled',
                'is_active',
            ]);

        $categories = [];

        foreach ($rows as $row) {
            $categories[] = $this->categoryFromRow($row);
        }

        return $categories;
    }

    public function upsertTeam(
        int $teamId,
        string $categoryKey,
        string $labelPl,
        string $labelEn,
        ?string $descriptionPl,
        ?string $descriptionEn,
        bool $requiresComment,
        bool $autoApprovalEnabled = false,
    ): void {
        if ($teamId < 1) {
            throw new InvalidArgumentException('Other work team category scope must reference a valid team.');
        }

        $this->upsert(OtherWorkCategoryScope::Team, $teamId, $categoryKey, $labelPl, $labelEn, $descriptionPl, $descriptionEn, $requiresComment, $autoApprovalEnabled);
    }

    public function deactivateTeam(int $teamId, string $categoryKey): void
    {
        $this->deactivate(OtherWorkCategoryScope::Team, $teamId, $categoryKey);
    }

    private function upsert(
        OtherWorkCategoryScope $scope,
        int $scopeId,
        string $categoryKey,
        string $labelPl,
        string $labelEn,
        ?string $descriptionPl,
        ?string $descriptionEn,
        bool $requiresComment,
        bool $autoApprovalEnabled,
    ): void {
        $this->assertCategoryKey($categoryKey);
        $this->assertLabel($labelPl, 'Polish');
        $this->assertLabel($labelEn, 'English');

        $now = now();

        $this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)->upsert([
            [
                'public_id' => (string) Str::ulid(),
                'scope_type' => $scope->value,
                'scope_id' => $scopeId,
                'category_key' => $categoryKey,
                'label_pl' => $labelPl,
                'label_en' => $labelEn,
                'description_pl' => $descriptionPl,
                'description_en' => $descriptionEn,
                'requires_comment' => $requiresComment,
                'auto_approval_enabled' => $autoApprovalEnabled,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['scope_type', 'scope_id', 'category_key'], [
            'label_pl',
            'label_en',
            'description_pl',
            'description_en',
            'requires_comment',
            'auto_approval_enabled',
            'is_active',
            'updated_at',
        ]);
    }

    private function deactivate(OtherWorkCategoryScope $scope, int $scopeId, string $categoryKey): void
    {
        $this->assertCategoryKey($categoryKey);

        $this->database->table(DatabaseTable::TIME_TRACKING_OTHER_WORK_CATEGORIES)
            ->where('scope_type', $scope->value)
            ->where('scope_id', $scopeId)
            ->where('category_key', $categoryKey)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    private function categoryFromRow(object $row): OtherWorkCategory
    {
        return new OtherWorkCategory(
            publicId: $this->stringValue($row->public_id ?? null),
            scope: $this->stringValue($row->scope_type ?? null),
            scopeId: $this->intValue($row->scope_id ?? null),
            categoryKey: $this->stringValue($row->category_key ?? null),
            labelPl: $this->stringValue($row->label_pl ?? null),
            labelEn: $this->stringValue($row->label_en ?? null),
            descriptionPl: $this->nullableString($row->description_pl ?? null),
            descriptionEn: $this->nullableString($row->description_en ?? null),
            requiresComment: (bool) ($row->requires_comment ?? false),
            autoApprovalEnabled: (bool) ($row->auto_approval_enabled ?? false),
            isActive: (bool) ($row->is_active ?? false),
        );
    }

    private function assertCategoryKey(string $categoryKey): void
    {
        if (preg_match('/\A[a-z][a-z0-9_]{1,63}\z/', $categoryKey) !== 1) {
            throw new InvalidArgumentException('Other work category key must be stable lowercase snake_case.');
        }
    }

    private function assertLabel(string $label, string $language): void
    {
        if (trim($label) === '') {
            throw new InvalidArgumentException(sprintf('Other work %s label cannot be empty.', $language));
        }
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $string = (string) $value;

        return $string === '' ? null : $string;
    }
}
