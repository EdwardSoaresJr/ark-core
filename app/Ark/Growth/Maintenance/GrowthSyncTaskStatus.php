<?php

namespace App\Ark\Growth\Maintenance;

enum GrowthSyncTaskStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Running = 'running';
}
