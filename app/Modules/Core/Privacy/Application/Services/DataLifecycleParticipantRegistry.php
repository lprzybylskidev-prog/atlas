<?php

declare(strict_types=1);

namespace App\Modules\Core\Privacy\Application\Services;

use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;

final readonly class DataLifecycleParticipantRegistry
{
    /**
     * @param  iterable<object>  $participants
     */
    public function __construct(
        private iterable $participants,
    ) {}

    /**
     * @return list<DataLifecycleParticipant>
     */
    public function all(): array
    {
        $participants = [];

        foreach ($this->participants as $participant) {
            if ($participant instanceof DataLifecycleParticipant) {
                $participants[] = $participant;
            }
        }

        return $participants;
    }

    public function count(): int
    {
        return count($this->all());
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(
            static fn (DataLifecycleParticipant $participant): string => $participant->key(),
            $this->all(),
        );
    }
}
