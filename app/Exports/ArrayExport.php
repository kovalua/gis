<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class ArrayExport implements FromCollection
{
    public function __construct(
        protected array $rows
    ) {
    }

    public function collection(): Collection
    {
        return collect($this->rows);
    }
}