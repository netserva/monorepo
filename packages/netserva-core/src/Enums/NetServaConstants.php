<?php

namespace NetServa\Core\Enums;

/**
 * NetServa Platform Constants
 *
 * Central constants for UIDs, ports, and other platform-specific values
 */
enum NetServaConstants: int
{
    /**
     * User ID Constants
     */
    case ADMIN_UID = 1000;
    case MAX_USER_UID = 9999;

    /**
     * Default Ports
     */
    case MYSQL_PORT = 3306;
    case SSH_PORT = 22;
    case HTTP_PORT = 80;
    case HTTPS_PORT = 443;
    case SMTP_PORT = 25;
    case IMAP_PORT = 143;
    case IMAPS_PORT = 993;
    case SUBMISSION_PORT = 587;

    /**
     * Password Generation
     */
    case SECURE_PASSWORD_LENGTH = 12;
    case WORDPRESS_USER_LENGTH = 6;

    /**
     * Get username for UID
     */
    public static function getUsernameForUid(int $uid): string
    {
        return $uid === self::ADMIN_UID->value ? 'sysadm' : "u{$uid}";
    }

    /**
     * Check if UID is admin
     */
    public static function isAdminUid(int $uid): bool
    {
        return $uid === self::ADMIN_UID->value;
    }

    /**
     * Check if UID is valid user range (> ADMIN_UID)
     */
    public static function isValidUserUid(int $uid): bool
    {
        return $uid > self::ADMIN_UID->value && $uid <= self::MAX_USER_UID->value;
    }

    /**
     * Get default shell for UID
     */
    public static function getShellForUid(int $uid): string
    {
        return self::isAdminUid($uid) ? '/bin/bash' : '/bin/sh';
    }
}
