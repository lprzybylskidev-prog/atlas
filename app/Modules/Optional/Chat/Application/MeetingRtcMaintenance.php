<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Application;

use App\Modules\Optional\Chat\Application\Contracts\ChatTransaction;
use App\Modules\Optional\Chat\Application\Contracts\MeetingStore;
use App\Modules\Optional\Chat\Application\Contracts\RtcGateway;
use DateTimeImmutable;

final readonly class MeetingRtcMaintenance
{
    public function __construct(private MeetingStore $meetings, private ChatTransaction $transaction, private RtcGateway $gateway) {}

    public function endExpiredEmptySessions(DateTimeImmutable $now): int
    {
        $rooms = $this->transaction->run(fn (): array => $this->meetings->endExpiredEmptyRtcSessions($now));
        foreach ($rooms as $room) {
            $this->gateway->endRoom($room);
        }

        return count($rooms);
    }
}
