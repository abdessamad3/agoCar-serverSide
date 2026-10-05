<?php

namespace App\Enum;

enum PaymentTypeEnum: string
{
    case INITIAL     = 'initial';
    case INSTALLMENT = 'installment';
    case ADJUSTMENT  = 'adjustment';
}
