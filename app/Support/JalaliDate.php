<?php

namespace App\Support;

use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Afghan Solar Hijri (Shamsi) calendar support - Hamal through Hoot.
 *
 * Pure PHP, no external dependencies (deliberately not Carbon-based, so
 * the conversion math can be unit-verified standalone with nothing but
 * the PHP CLI - see the verification script used when this class was
 * introduced). Conversion uses the astronomical "jalaali" algorithm (the
 * same one behind the widely-used jalaali-js/moment-jalaali libraries),
 * driven by a table of leap-year "breaks" rather than a fixed 33-year
 * cycle, which is what keeps it accurate centuries out in both
 * directions.
 *
 * Every date column touched by the Shamsi conversion stores a plain
 * "YYYY-MM-DD" Jalali string (zero-padded, so it still sorts and
 * compares correctly as text, including with PHP's own < / > operators -
 * see isPast()). There is no Carbon/DateTime object living inside the
 * app for these fields day to day. This class is the only place a
 * Gregorian date is ever touched, for: reading the server's real
 * "today" (today()), the one-time historical-data migration
 * (fromGregorian()), and the rare need to add/subtract calendar days
 * (addDays()).
 */
class JalaliDate
{
    /**
     * Break points of the 2820-year great cycle used by the accurate
     * jalaali algorithm. Do not "simplify" this to a fixed 33-year rule -
     * that approximation drifts by a day every few centuries.
     */
    private const BREAKS = [
        -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210,
        1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178,
    ];

    public const MONTH_NAMES = [
        1 => 'حمل', 2 => 'ثور', 3 => 'جوزا', 4 => 'سرطان', 5 => 'اسد', 6 => 'سنبله',
        7 => 'میزان', 8 => 'عقرب', 9 => 'قوس', 10 => 'جدی', 11 => 'دلو', 12 => 'حوت',
    ];

    // ---- Public API -------------------------------------------------

    /**
     * Today, as a Jalali "YYYY-MM-DD" string, based on the server's
     * configured timezone (config/app.php).
     */
    public static function today(): string
    {
        return self::fromGregorian(new DateTime());
    }

    /**
     * Any Gregorian date (a DateTimeInterface, or a "YYYY-MM-DD" /
     * otherwise DateTime-parsable string) to a Jalali "YYYY-MM-DD"
     * string. Returns null for null/''.
     */
    public static function fromGregorian(DateTimeInterface|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $dt = is_string($date) ? new DateTime($date) : $date;

        [$jy, $jm, $jd] = self::toJalaliParts(
            (int) $dt->format('Y'),
            (int) $dt->format('n'),
            (int) $dt->format('j')
        );

        return self::format($jy, $jm, $jd);
    }

    /**
     * A Jalali "YYYY-MM-DD" string back to a Gregorian DateTime (midnight).
     * Only needed for the historical-data migration and for addDays()'s
     * day arithmetic - nothing else in the app should need this.
     */
    public static function toGregorian(?string $jalali): ?DateTime
    {
        if (! $jalali) {
            return null;
        }

        [$jy, $jm, $jd] = self::parts($jalali);
        [$gy, $gm, $gd] = self::toGregorianParts($jy, $jm, $jd);

        return (new DateTime())->setDate($gy, $gm, $gd)->setTime(0, 0);
    }

    /**
     * Combines three dropdown values (as submitted by <x-jalali-date>)
     * into a "YYYY-MM-DD" string, or null if any part is missing or the
     * combination isn't a real Jalali date. Form requests should call
     * isValidDate() themselves first to surface a friendly validation
     * error instead of silently getting null back from here.
     */
    public static function fromParts(?int $year, ?int $month, ?int $day): ?string
    {
        if (! $year || ! $month || ! $day || ! self::isValidDate($year, $month, $day)) {
            return null;
        }

        return self::format($year, $month, $day);
    }

    /**
     * Same as fromParts(), but reads straight from a <x-jalali-date>
     * array (e.g. $request->input('date')) - ['year' => ..., 'month' =>
     * ..., 'day' => ...]. For GET-filter controllers reading a query
     * param directly (not a validated FormRequest, which instead uses
     * App\Http\Requests\Concerns\ValidatesJalaliDates::jalaliDateFromInput()).
     */
    public static function fromRequestArray(?array $parts): ?string
    {
        if (! $parts) {
            return null;
        }

        return self::fromParts(
            isset($parts['year']) ? (int) $parts['year'] : null,
            isset($parts['month']) ? (int) $parts['month'] : null,
            isset($parts['day']) ? (int) $parts['day'] : null,
        );
    }

    /**
     * The first day of the given Jalali year/month, as "YYYY-MM-DD".
     */
    public static function startOfMonth(int $year, int $month): string
    {
        return self::format($year, $month, 1);
    }

    /**
     * The last day of the given Jalali year/month, as "YYYY-MM-DD" (29,
     * 30 or 31 depending on the month and, for Hoot, whether $year is a
     * leap year - see daysInMonth()).
     */
    public static function endOfMonth(int $year, int $month): string
    {
        return self::format($year, $month, self::daysInMonth($year, $month));
    }

    /**
     * A "YYYY-MM-DD" Jalali string to [year, month, day] integers.
     */
    public static function parts(string $jalali): array
    {
        [$y, $m, $d] = array_map('intval', explode('-', $jalali));

        return [$y, $m, $d];
    }

    public static function isValidDate(int $year, int $month, int $day): bool
    {
        if ($month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        return $day <= self::daysInMonth($year, $month);
    }

    /**
     * 31 for Hamal-Shahrivar (1-6), 30 for Mehr-Bahman (7-11), and 29 or
     * 30 for Hoot (12) depending on whether $year is a Jalali leap year.
     */
    public static function daysInMonth(int $year, int $month): int
    {
        if ($month <= 6) {
            return 31;
        }

        if ($month <= 11) {
            return 30;
        }

        return self::isLeapYear($year) ? 30 : 29;
    }

    public static function isLeapYear(int $year): bool
    {
        return self::jalCal($year)['leap'] === 0;
    }

    public static function monthName(int $month): string
    {
        return self::MONTH_NAMES[$month] ?? '';
    }

    /**
     * "۱۳ سنبله ۱۴۰۵"-style display (Latin digits kept, to stay easy to
     * scan/copy in a mixed-script UI) for a full date field.
     */
    public static function display(?string $jalali): string
    {
        if (! $jalali) {
            return '—';
        }

        [$y, $m, $d] = self::parts($jalali);

        return "{$d} ".self::monthName($m)." {$y}";
    }

    /**
     * "سنبله 1405" - for a month-only field (payroll's salary_month is
     * stored as "YYYY-MM", with no day component - a Jalali month simply
     * doesn't line up with a Gregorian one, so pretending it starts on
     * "the 1st" the way the old Gregorian column did would be meaningless).
     */
    public static function monthYearDisplay(?string $jalaliYearMonth): string
    {
        if (! $jalaliYearMonth) {
            return '—';
        }

        [$y, $m] = array_map('intval', explode('-', $jalaliYearMonth));

        return self::monthName($m)." {$y}";
    }

    /**
     * Adds (or, for a negative $days, subtracts) whole calendar days to a
     * Jalali date, staying in Jalali representation. Round-trips through
     * Gregorian internally - that's the only reliable way to do
     * calendar-day arithmetic - but callers never see the Gregorian side.
     */
    public static function addDays(string $jalali, int $days): string
    {
        $gregorian = self::toGregorian($jalali);
        $gregorian->modify(($days >= 0 ? '+' : '').$days.' days');

        return self::fromGregorian($gregorian);
    }

    /**
     * String comparison is safe here only because every stored/derived
     * Jalali date is zero-padded "YYYY-MM-DD" - the same reasoning that
     * makes ISO Gregorian strings sortable.
     */
    public static function isPast(string $jalali): bool
    {
        return $jalali < self::today();
    }

    // ---- Conversion core (jalaali astronomical algorithm) ------------

    private static function format(int $y, int $m, int $d): string
    {
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private static function toJalaliParts(int $gy, int $gm, int $gd): array
    {
        return self::d2jFromJdn(self::g2d($gy, $gm, $gd));
    }

    private static function toGregorianParts(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    /**
     * For a given Jalali year, returns whether it's a leap year, the
     * corresponding Gregorian year its Farvardin/Hamal 1st falls in, and
     * which March day (Gregorian) that 1st falls on.
     */
    private static function jalCal(int $jy): array
    {
        $breaks = self::BREAKS;
        $bl = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];

        if ($jy < $jp || $jy >= $breaks[$bl - 1]) {
            throw new InvalidArgumentException("Jalali year {$jy} is out of the supported range.");
        }

        $jump = 0;
        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + intdiv($jump, 33) * 8 + intdiv($jump % 33, 4);
            $jp = $jm;
        }

        $n = $jy - $jp;

        $leapJ = $leapJ + intdiv($n, 33) * 8 + intdiv(($n % 33) + 3, 4);
        if ($jump % 33 === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }

        $leapG = intdiv($gy, 4) - intdiv((intdiv($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + intdiv($jump + 4, 33) * 33;
        }

        $leap = (($n + 1) % 33 - 1) % 4;
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jalCal($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - intdiv($jm, 7) * ($jm - 7) + $jd - 1;
    }

    private static function d2jFromJdn(int $jdn): array
    {
        [$gy] = self::d2g($jdn);
        $jy = $gy - 621;
        $r = self::jalCal($jy);
        $jdn1f = self::g2d($r['gy'], 3, $r['march']);

        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + intdiv($k, 31);
                $jd = ($k % 31) + 1;

                return [$jy, $jm, $jd];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        $jm = 7 + intdiv($k, 30);
        $jd = ($k % 30) + 1;

        return [$jy, $jm, $jd];
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = intdiv(($gy + intdiv($gm - 8, 6) + 100100) * 1461, 4)
            + intdiv(153 * (($gm + 9) % 12) + 2, 5)
            + $gd - 34840408;

        return $d - intdiv(intdiv($gy + 100100 + intdiv($gm - 8, 6), 100) * 3, 4) + 752;
    }

    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + intdiv(intdiv(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = intdiv($j % 1461, 4) * 5 + 308;
        $gd = intdiv($i % 153, 5) + 1;
        $gm = intdiv($i, 153) % 12 + 1;
        $gy = intdiv($j, 1461) - 100100 + intdiv(8 - $gm, 6);

        return [$gy, $gm, $gd];
    }
}
