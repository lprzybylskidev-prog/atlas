<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Presentation\Jobs;

use App\Modules\Optional\Chat\Application\ChatSearch;
use App\Modules\Optional\Chat\Application\Contracts\ChatSearchProjectionStore;
use App\Modules\Optional\Search\Application\Public\Contracts\SearchProjectionWriter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RefreshChatSearchProjection implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $sourceType, public string $sourcePublicId) {}

    public function handle(ChatSearchProjectionStore $projection, SearchProjectionWriter $writer): void
    {
        foreach ($projection->documentsForSource($this->sourceType, $this->sourcePublicId) as $document) {
            $writer->upsert($document);
        }
        $writer->delete(ChatSearch::INDEX_KEY, $projection->deletedDocumentIdsForSource($this->sourceType, $this->sourcePublicId));
    }
}
