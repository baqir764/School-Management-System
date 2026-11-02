<?php

namespace App\Http\Requests\Concerns;

use App\Support\JalaliDate;
use Illuminate\Validation\Validator;

/**
 * Shared plumbing for every FormRequest that has one or more Shamsi date
 * fields submitted by the <x-jalali-date> component (an array of
 * day/month/year, e.g. "date_of_birth[day]"). Three steps, mirrored in
 * every request that uses this trait:
 *
 *   1. rules(): merge in jalaliDateRules('date_of_birth', required: false)
 *      - validates the three parts are present (if required) and each is
 *        an integer in a sane range, but NOT that they form a real date
 *        (Jun 31st, or Hoot 30th in a non-leap year, needs the actual
 *        Jalali calendar to check - a plain 'between' rule can't do it).
 *   2. withValidator(): call validateJalaliDate($validator, 'date_of_birth', false)
 *      inside a ->after() closure - this is the real-date check, and
 *      adds a friendly error instead of a confusing downstream failure.
 *   3. In the controller (not here - this class has no access to
 *      $request->validated() shaping): call
 *      JalaliDate::fromParts($request->integer('date_of_birth.year'), ...)
 *      or the convenience jalaliDateFromInput('date_of_birth') below to
 *      turn the three parts into the single "YYYY-MM-DD" string that
 *      actually gets stored.
 */
trait ValidatesJalaliDates
{
    protected function jalaliDateRules(string $field, bool $required = true): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            $field => [$presence, 'array'],
            "{$field}.day" => [$presence, 'integer', 'min:1', 'max:31'],
            "{$field}.month" => [$presence, 'integer', 'between:1,12'],
            "{$field}.year" => [$presence, 'integer', 'between:1300,1500'],
        ];
    }

    /**
     * Call from withValidator()'s ->after() closure. Skips silently when
     * every part is empty and the field isn't required (the 'nullable'
     * rules above already allow that); otherwise requires all three parts
     * and that they form a real Jalali calendar date.
     */
    protected function validateJalaliDate(Validator $validator, string $field, bool $required = true): void
    {
        $day = $this->input("{$field}.day");
        $month = $this->input("{$field}.month");
        $year = $this->input("{$field}.year");

        if (! $required && ! $day && ! $month && ! $year) {
            return;
        }

        if (! $day || ! $month || ! $year || ! JalaliDate::isValidDate((int) $year, (int) $month, (int) $day)) {
            $validator->errors()->add("{$field}.day", __('app.error_invalid_jalali_date'));
        }
    }

    /**
     * The combined "YYYY-MM-DD" Jalali string for a field validated with
     * the two helpers above - null if any part is missing/invalid (which
     * validateJalaliDate() would already have rejected when the field is
     * required, so by the time a controller calls this the request has
     * already passed validation). Public: controllers call this directly
     * to get the string to actually store, since $request->validated()
     * still holds the raw day/month/year array for these fields.
     */
    public function jalaliDateFromInput(string $field): ?string
    {
        $day = $this->input("{$field}.day");
        $month = $this->input("{$field}.month");
        $year = $this->input("{$field}.year");

        return JalaliDate::fromParts(
            $year ? (int) $year : null,
            $month ? (int) $month : null,
            $day ? (int) $day : null,
        );
    }
}
