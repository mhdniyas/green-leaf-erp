<?php

declare(strict_types=1);

namespace App\Repositories\Finance;

use App\Models\JournalEntry;
use App\Repositories\BaseRepository;

class JournalEntryRepository extends BaseRepository
{
    protected function getModel(): string
    {
        return JournalEntry::class;
    }
}
