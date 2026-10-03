<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Contracts\TranscriptionProvider;
use App\Modules\Optional\Chat\Infrastructure\Persistence\TableNames\ChatDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;

final readonly class ChatOperationsSummary
{
    public function __construct(
        private ConnectionInterface $database,
        private ChatRetention $retention,
        private MeetingRecordingRetention $recordingRetention,
        private TranscriptionProvider $transcription,
    ) {}

    /** @return array<string, mixed> */
    public function get(): array
    {
        return [
            'counts' => [
                'conversations' => $this->database->table(ChatDatabaseTable::CONVERSATIONS)->count(),
                'messages' => $this->database->table(ChatDatabaseTable::MESSAGES)->count(),
                'activeCalls' => $this->database->table(ChatDatabaseTable::CALLS)->whereIn('status', ['ringing', 'active'])->count(),
                'activeMeetings' => $this->database->table(ChatDatabaseTable::MEETING_OCCURRENCES)->where('rtc_status', 'active')->count(),
                'failedJobs' => $this->database->table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
                    ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
                    ->whereNull('acknowledgements.failed_job_uuid')->count(),
            ],
            'storage' => [
                'attachmentBytes' => (int) $this->database->table(ChatDatabaseTable::MESSAGE_ATTACHMENTS)->whereNull('discarded_at')->sum('size_bytes'),
                'recordingsReady' => $this->database->table(ChatDatabaseTable::MEETING_RECORDINGS)->where('status', 'ready')->count(),
            ],
            'health' => [
                'reverb' => Config::string('broadcasting.default') === 'reverb' ? 'configured' : 'degraded',
                'rtc' => Config::boolean('livekit.rtc_enabled', false) ? 'configured' : 'disabled',
                'egress' => Config::boolean('livekit.egress_enabled', false) ? 'configured' : 'disabled',
                'search' => Config::string('scout.driver') === 'meilisearch' ? 'configured' : 'degraded',
            ],
            'transcription' => [
                'provider' => $this->transcription->key(),
                'available' => $this->transcription->available(),
                'queued' => $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->whereIn('status', ['queued', 'submitted', 'processing'])->count(),
                'failed' => $this->database->table(ChatDatabaseTable::MEETING_TRANSCRIPTIONS)->where('status', 'failed')->count(),
            ],
            'retention' => $this->retention->summary(),
            'recordingRetention' => $this->recordingRetention->summary(),
        ];
    }
}
