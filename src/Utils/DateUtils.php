<?php
// src/Utils/DateUtils.php
namespace App\Utils;

class DateUtils {
    /**
     * Set the default timezone for the application
     */
    public static function setDefaultTimezone(string $timezone = 'Asia/Tokyo'): void {
        date_default_timezone_set($timezone);
    }

    /**
     * Get an offset date based on the provided offset
     * 
     * @param int $offset Number of days to offset from today
     * @return \DateTime
     */
    public static function getOffsetDate(int $offset = 0): \DateTime {
        $targetDate = new \DateTime('now');
        $targetDate->modify("{$offset} days");
        return $targetDate;
    }

    /**
     * Format date for display
     * 
     * @param \DateTime $date
     * @param string $format
     * @return string
     */
    public static function formatDateForDisplay(\DateTime $date, string $format = 'Y-m-d'): string {
        return $date->format($format);
    }

    /**
     * Get a relative date label
     * 
     * @param int $offset
     * @return string
     */
    public static function getRelativeDateLabel(int $offset): string {
        return match (true) {
            $offset < 0 => abs($offset) . ' day(s) ago',
            $offset > 0 => $offset . ' day(s) from now',
            default => 'Today'
        };
    }
}