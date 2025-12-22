<?php

namespace fabian\booked\helpers;

use fabian\booked\elements\Reservation;
use DateTime;
use DateTimeZone;

/**
 * ICS Helper
 */
class IcsHelper
{
    /**
     * Generate ICS content for a reservation
     */
    public static function generate(Reservation $reservation): string
    {
        $startTime = new DateTime($reservation->bookingDate . ' ' . $reservation->startTime, new DateTimeZone('Europe/Zurich'));
        $endTime = new DateTime($reservation->bookingDate . ' ' . $reservation->endTime, new DateTimeZone('Europe/Zurich'));
        
        // Convert to UTC for ICS
        $startTime->setTimezone(new DateTimeZone('UTC'));
        $endTime->setTimezone(new DateTimeZone('UTC'));

        $created = new DateTime('now', new DateTimeZone('UTC'));
        
        $uid = $reservation->uid ?: bin2hex(random_bytes(16));
        $summary = $reservation->getService() ? $reservation->getService()->title : 'Buchung';
        $description = "Buchungs-ID: #{$reservation->id}";
        
        if ($reservation->virtualMeetingUrl) {
            $description .= "\\nMeeting-Link: {$reservation->virtualMeetingUrl}";
        }

        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PROID:-//Booked Plugin//DE',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'DTSTART:' . $startTime->format('Ymd\THis\Z'),
            'DTEND:' . $endTime->format('Ymd\THis\Z'),
            'DTSTAMP:' . $created->format('Ymd\THis\Z'),
            'UID:' . $uid,
            'SUMMARY:' . self::escape($summary),
            'DESCRIPTION:' . self::escape($description),
            'STATUS:CONFIRMED',
            'SEQUENCE:0',
            'TRANSP:OPAQUE',
            'END:VEVENT',
            'END:VCALENDAR'
        ];

        return implode("\r\n", $ics);
    }

    /**
     * Escape characters for ICS
     */
    private static function escape(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace("\n", '\\n', $text);
        return $text;
    }
}

