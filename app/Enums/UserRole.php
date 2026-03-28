<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'ADMIN';
    case PROCUREMENT_OFFICER = 'PROC_OFFICER';
    case GSO_OFFICER = 'GSO_OFFICER';
    case OFFICE_USER = 'OFFICE_USER';
    case EXECUTIVE_OFFICER = 'EXEC_OFFICER';
    case PUBLIC = 'PUBLIC';
}
