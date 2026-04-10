<div class="stat-card">
    <div class="stat-icon">{!! app_icon($icon ?? 'dashboard') !!}</div>
    <div class="stat-title">{{ $title }}</div>
    <div class="stat-value">{{ $value }}</div>
    @isset($meta)
        <div class="stat-meta">{{ $meta }}</div>
    @endisset
</div>
