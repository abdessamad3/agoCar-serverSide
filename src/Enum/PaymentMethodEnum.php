<?php

namespace App\Enum;

enum PaymentMethodEnum: string
{
    case CASH          = 'cash';
    case BANK_TRANSFER = 'bank_transfer';
    case CHECK         = 'check';
    case CREDIT_CARD   = 'credit_card';
    case MOBILE        = 'mobile';
}
