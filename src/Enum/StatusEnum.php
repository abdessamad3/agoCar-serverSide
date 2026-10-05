<?php

namespace App\Enum;

enum StatusEnum: string
{
    case ACTIVE     = 'active';
    case INACTIVE   = 'inactive';
    case PENDING    = 'pending';
    case PAYEE      = 'payee';
    case NON_PAYEE  = 'non_payee';
    case IMPAYE     = 'impaye';
    case PARTIEL    = 'partiel';
    case EN_ATTENTE = 'en_attente';
    case ANNULEE    = 'annulee';
}
