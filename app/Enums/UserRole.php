<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case COLLABORATOR = 'collaborator';
    case CUSTOMER = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrátor',
            self::MANAGER => 'Manažer',
            self::COLLABORATOR => 'Spolupracovník',
            self::CUSTOMER => 'Zákazník',
        };
    }
}
