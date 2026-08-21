<?php

declare(strict_types=1);

namespace App\Service;

enum UserState: string
{
    case Idle     = 'idle';
    case Waiting  = 'waiting';
    case Chatting = 'chatting';
}
