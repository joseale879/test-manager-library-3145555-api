<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\Ports\Outbound\Clock;
use App\Application\Ports\Outbound\LoanRepository;
use App\Application\Services\Results\OverdueItem;

final readonly class OverdueService
{
    public function __construct(
        private LoanRepository $loans,
        private Clock $clock,
    ) {}

    /** @return list<OverdueItem> */
    public function execute(): array
    {
        $now = $this->clock->now();
        $items = [];

        foreach ($this->loans->findOverdueItems($now) as $record) {
            if ($record->dueAt >= $now) {
                continue;
            }

            $items[] = new OverdueItem(
                $record->loanId,
                $record->itemId,
                $record->bookTitle,
                $record->memberName,
                $record->dueAt,
                $record->dueAt->diff($now)->days,
            );
        }

        usort($items, static fn (OverdueItem $left, OverdueItem $right): int => $right->daysLate <=> $left->daysLate);

        return $items;
    }
}
