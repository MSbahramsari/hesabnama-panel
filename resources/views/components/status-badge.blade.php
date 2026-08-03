@props(['status'])
<span @class([
    'status-badge',
    'status-slate' => $status->color() === 'slate',
    'status-amber' => $status->color() === 'amber',
    'status-blue' => $status->color() === 'blue',
    'status-rose' => $status->color() === 'rose',
    'status-emerald' => $status->color() === 'emerald',
])>
    <span class="size-1.5 rounded-full bg-current"></span>{{ $status->label() }}
</span>
