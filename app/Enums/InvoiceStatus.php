<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case PARTIALLY_PAID = 'partially_paid';
    case VOID = 'void';
    case REFUNDED = 'refunded';
}
