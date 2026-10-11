<?php

namespace App\Enums;

enum ChangeType: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
