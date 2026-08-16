<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case SCHEDULED = 'scheduled';
    case ACTIVE = 'active';
    case FROZEN = 'frozen';
    case PAST_DUE = 'past_due';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case UPGRADED = 'upgraded';
    case RENEWED = 'renewed';
}
