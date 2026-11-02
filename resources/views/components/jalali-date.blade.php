{{--
    Reusable Shamsi (Jalali) date input: three <select> dropdowns
    (day/month/year, month shown as its Dari name - Hamal..Hoot) that
    submit as an array under one field name, e.g. name="date_of_birth"
    submits date_of_birth[day]/[month]/[year].

    Usage:
        <label class="form-label">{{ __('app.field_date_of_birth') }}</label>
        <x-jalali-date name="date_of_birth" :value="$student->date_of_birth" />

    $value is the field's CURRENT value, already a Jalali "YYYY-MM-DD"
    string (that's how every converted date column is stored - see
    App\Support\JalaliDate) - pass null for a create form / no existing
    value. old() input always takes precedence over $value, matching
    every other input in this app.

    On the request side, pair this with the ValidatesJalaliDates trait
    (App\Http\Requests\Concerns\ValidatesJalaliDates) rather than
    hand-rolling the three field rules/parsing on every form - see that
    trait's own doc block for the exact rules()/withValidator()/
    controller-side pattern.

    minYear/maxYear default to a 100-years-back/1-year-forward window
    centered on today's Jalali year, which suits a date of birth. Pass
    tighter bounds for anything else (joining dates, transaction dates)
    e.g. :min-year="..." :max-year="...".
--}}
@props([
    'name',
    'value' => null,
    'required' => false,
    'minYear' => null,
    'maxYear' => null,
])

@php
    $currentJalaliYear = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
    $minYear ??= $currentJalaliYear - 100;
    $maxYear ??= $currentJalaliYear + 1;

    [$existingYear, $existingMonth, $existingDay] = $value
        ? \App\Support\JalaliDate::parts($value)
        : [null, null, null];

    $oldDay = old("{$name}.day", $existingDay);
    $oldMonth = old("{$name}.month", $existingMonth);
    $oldYear = old("{$name}.year", $existingYear);

    // A stored/old value can fall outside the caller's chosen range (an
    // old record from before a tighter range was picked, or a
    // validation redisplay) - widen the bounds rather than silently
    // rendering the year dropdown with nothing selected.
    if ($oldYear) {
        $minYear = min($minYear, (int) $oldYear);
        $maxYear = max($maxYear, (int) $oldYear);
    }
@endphp

<div {{ $attributes->class(['row g-2']) }}>
    <div class="col-3">
        <select name="{{ $name }}[day]" class="form-select @error($name.'.day') is-invalid @enderror" @if ($required) required @endif>
            <option value="">{{ __('app.jalali_day') }}</option>
            @for ($d = 1; $d <= 31; $d++)
                <option value="{{ $d }}" @selected((int) $oldDay === $d)>{{ $d }}</option>
            @endfor
        </select>
    </div>
    <div class="col-5">
        <select name="{{ $name }}[month]" class="form-select @error($name.'.month') is-invalid @enderror" @if ($required) required @endif>
            <option value="">{{ __('app.jalali_month') }}</option>
            @foreach (\App\Support\JalaliDate::MONTH_NAMES as $num => $label)
                <option value="{{ $num }}" @selected((int) $oldMonth === $num)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    {{-- A Jalali year is always 4 digits (e.g. "1405"); col-4 (not the
         col-3 day/month used to get, despite day/month needing fewer
         characters) is what actually keeps all 4 digits plus the <select>
         arrow visible instead of clipped. --}}
    <div class="col-4">
        <select name="{{ $name }}[year]" class="form-select @error($name.'.year') is-invalid @enderror" @if ($required) required @endif>
            <option value="">{{ __('app.jalali_year') }}</option>
            @for ($y = $maxYear; $y >= $minYear; $y--)
                <option value="{{ $y }}" @selected((int) $oldYear === $y)>{{ $y }}</option>
            @endfor
        </select>
    </div>

    @error($name.'.day')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error($name.'.month')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
    @error($name.'.year')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>
