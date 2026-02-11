<?php

namespace App\Enums;

enum VoteType: string
{
    case Like = 'like';
    case Dislike = 'dislike';
}
