<?php

namespace App\Ark\Operations\Evidence;

enum EvidenceSource: string
{
    case Camera = 'camera';
    case Upload = 'upload';
    case Migration = 'migration';
    case System = 'system';
}
