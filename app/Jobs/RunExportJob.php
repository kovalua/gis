<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\Gis\Export\ExportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunExportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $exportJobId
    ) {
    }

    public function handle(ExportService $exportService): void
    {
        $job = ExportJob::query()->findOrFail($this->exportJobId);
        $exportService->runJob($job);
    }
}