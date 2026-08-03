@props(['title', 'description', 'action' => null, 'actionLabel' => null])
<div class="grid min-h-64 place-items-center p-8 text-center">
    <div>
        <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-slate-100 text-slate-400"><x-icon name="invoice" class="size-8" /></div>
        <h3 class="mt-5 text-lg font-extrabold text-slate-900">{{ $title }}</h3>
        <p class="mx-auto mt-2 max-w-md text-sm leading-7 text-slate-500">{{ $description }}</p>
        @if($action)
            <a href="{{ $action }}" class="btn-primary mt-5"><x-icon name="plus" class="size-4" />{{ $actionLabel }}</a>
        @endif
    </div>
</div>
