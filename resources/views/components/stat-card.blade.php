@props(['label', 'value', 'icon' => 'bi-bar-chart', 'color' => 'primary'])

<div class="col-sm-6 col-lg-3">
    <div class="sms-stat-card p-3 h-100 d-flex align-items-center gap-3">
        <div class="sms-stat-icon bg-{{ $color }}-subtle text-{{ $color }}">
            <i class="bi {{ $icon }}"></i>
        </div>
        <div>
            <div class="fs-4 fw-bold lh-1">{{ $value }}</div>
            <div class="text-muted small">{{ $label }}</div>
        </div>
    </div>
</div>
