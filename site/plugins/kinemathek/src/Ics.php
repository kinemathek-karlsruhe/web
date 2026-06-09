<?php

namespace Kinemathek;

/**
 * RFC 5545 (iCalendar) generation helper.
 *
 * Centralises the fiddly bits — escaping, 75-octet line folding, local
 * DATE-TIME formatting and the Europe/Berlin VTIMEZONE — so the showing and
 * event templates stay trivial and the rules are identical for both.
 *
 * Verified rules (RFC 5545):
 *  - Content lines are delimited by CRLF.
 *  - Lines SHOULD NOT exceed 75 OCTETS (not characters); fold with
 *    CRLF + single SPACE, never splitting a multibyte UTF-8 sequence.
 *  - In TEXT values: backslash, semicolon and comma MUST be escaped; a line
 *    break becomes the literal two chars \n; a colon is NOT escaped.
 *  - Any TZID used MUST have a matching VTIMEZONE in the object.
 */
class Ics
{
    public const CRLF = "\r\n";

    /**
     * Set the calendar download headers (Content-Type + RFC 5987
     * Content-Disposition) on the response and return the body to echo. Shared
     * by the showing and event .ics representations so the header logic lives
     * in one place.
     */
    public static function respond(\Kirby\Cms\App $kirby, string $ics, string $filename): string
    {
        $kirby->response()->type('text/calendar'); // Kirby appends charset=UTF-8
        $kirby->response()->header(
            'Content-Disposition',
            'attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename)
        );
        return $ics;
    }

    /** Escape a value for an iCalendar TEXT property (SUMMARY, DESCRIPTION …). */
    public static function escapeText(string $value): string
    {
        $value = str_replace('\\', '\\\\', $value); // backslash first
        $value = str_replace(';', '\\;', $value);
        $value = str_replace(',', '\\,', $value);
        return preg_replace("/\r\n|\r|\n/", '\\n', $value);
    }

    /**
     * Fold a single assembled content line to <= 75 octets, inserting
     * CRLF + SPACE at fold points. Octet-aware: never splits a multibyte
     * UTF-8 character across a fold.
     */
    public static function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $out     = '';
        $current = '';
        $limit   = 75; // octets on the first line
        $len     = strlen($line);
        $i       = 0;

        while ($i < $len) {
            $byte = ord($line[$i]);
            if ($byte < 0x80)     { $charLen = 1; }
            elseif ($byte < 0xE0) { $charLen = 2; }
            elseif ($byte < 0xF0) { $charLen = 3; }
            else                  { $charLen = 4; }

            $char = substr($line, $i, $charLen);

            if (strlen($current) + $charLen > $limit) {
                $out    .= $current . self::CRLF . ' ';
                $current = '';
                $limit   = 74; // continuation lines start with one SPACE
            }

            $current .= $char;
            $i += $charLen;
        }

        return $out . $current;
    }

    /** Local DATE-TIME value (no Z); the property must carry ;TZID=…. */
    public static function localDateTime(\DateTime $dt): string
    {
        return $dt->format('Ymd\\THis');
    }

    /** UTC DATE-TIME value (with Z) — used for DTSTAMP. */
    public static function utcDateTime(\DateTime $dt): string
    {
        return (clone $dt)->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
    }

    /**
     * Static Europe/Berlin VTIMEZONE block (EU CET/CEST rules).
     *
     * @return string[] unfolded content lines
     */
    public static function berlinVtimezone(): array
    {
        return [
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Berlin',
            'X-LIC-LOCATION:Europe/Berlin',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];
    }

    /**
     * Build a complete single-event VCALENDAR string.
     *
     * @param array $data uid(string,req), start(DateTime,req), end(DateTime,req),
     *                    summary, description, location, url, prodid, tzid
     */
    public static function build(array $data): string
    {
        $tzid   = $data['tzid'] ?? 'Europe/Berlin';
        $now    = new \DateTime('now', new \DateTimeZone('UTC'));
        $prodid = $data['prodid'] ?? '-//Kinemathek Karlsruhe//Website//DE';

        $lines   = [];
        $lines[] = 'BEGIN:VCALENDAR';
        $lines[] = 'VERSION:2.0';
        $lines[] = 'PRODID:' . self::escapeText($prodid);
        $lines[] = 'CALSCALE:GREGORIAN';
        $lines[] = 'METHOD:PUBLISH';

        foreach (self::berlinVtimezone() as $tzLine) {
            $lines[] = $tzLine;
        }

        $lines[] = 'BEGIN:VEVENT';
        $lines[] = 'UID:' . $data['uid'];
        $lines[] = 'DTSTAMP:' . self::utcDateTime($now);
        $lines[] = 'DTSTART;TZID=' . $tzid . ':' . self::localDateTime($data['start']);
        $lines[] = 'DTEND;TZID='   . $tzid . ':' . self::localDateTime($data['end']);

        if (!empty($data['summary'])) {
            $lines[] = 'SUMMARY:' . self::escapeText($data['summary']);
        }
        if (!empty($data['description'])) {
            $lines[] = 'DESCRIPTION:' . self::escapeText($data['description']);
        }
        if (!empty($data['location'])) {
            $lines[] = 'LOCATION:' . self::escapeText($data['location']);
        }
        if (!empty($data['url'])) {
            // URL is a URI value, not TEXT — do not escape commas/semicolons.
            $lines[] = 'URL:' . $data['url'];
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        $folded = array_map([self::class, 'foldLine'], $lines);
        return implode(self::CRLF, $folded) . self::CRLF;
    }
}
