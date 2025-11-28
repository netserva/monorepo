<?php

namespace NetServa\Core\Enums;

/**
 * String constants for NetServa Platform
 */
enum NetServaStrings: string
{
    /**
     * Default Admin User
     */
    case ADMIN_USER = 'sysadm';
    case ADMIN_NAME = 'System Administrator';

    /**
     * Default Database
     */
    case DEFAULT_DB_TYPE = 'mysql';
    case DEFAULT_DB_HOST = 'localhost';

    /**
     * Default Timezone
     */
    case DEFAULT_TIMEZONE_AREA = 'Australia';
    case DEFAULT_TIMEZONE_CITY = 'Sydney';

    /**
     * Default Server IP
     */
    case DEFAULT_SERVER_IP = '127.0.0.1';

    /**
     * Character sets for password generation
     */
    case ALPHANUMERIC_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    case LOWERCASE_CHARS = 'abcdefghijklmnopqrstuvwxyz';
}
