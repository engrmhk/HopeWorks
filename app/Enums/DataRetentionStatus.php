<?php

namespace App\Enums;

enum DataRetentionStatus: string
{
    case Active = 'active';
    case RetainedLocked = 'retained_locked';
    case PurgeScheduled = 'purge_scheduled';
}
