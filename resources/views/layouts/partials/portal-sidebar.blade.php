@php $collapsible = $collapsible ?? false; @endphp

<div class="flex h-full flex-col">
    <div class="flex items-center gap-3 p-3 md:p-5 md:pb-3" :class="collapsed && 'md:!justify-center md:!p-3'">
        <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white"><i class="fa-solid fa-language text-ec-primary" aria-hidden="true"></i></div>
        <div class="min-w-0 flex-1" :class="collapsed && 'md:!hidden'">
            <h1 class="truncate text-[15px] font-bold leading-tight text-white">EUROCROSS</h1>
            <p class="truncate text-[11px] text-rose-200/60">Translation platform</p>
        </div>
        @if ($collapsible)
            <button type="button" class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-200/70 hover:bg-white/10 hover:text-white md:inline-flex" @click="collapsed=!collapsed" :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'" aria-label="Toggle sidebar">
                <i class="fa-solid fa-chevron-left text-sm transition-transform duration-300" :class="collapsed && '!rotate-180'" aria-hidden="true"></i>
            </button>
        @endif
        <button type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-rose-200/70 hover:bg-white/10 md:hidden" @click="sidebarOpen=false" aria-label="Close menu"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
    </div>

    <nav class="sidebar-scroll flex-1 overflow-x-hidden overflow-y-auto px-3 py-2">
        @foreach ($nav as $group)
            <div class="mb-2" x-data="{ open:true }">
                <template x-if="!collapsed">
                    <button type="button" @click="open=!open" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-[11px] font-bold uppercase tracking-wider text-rose-200/70 transition hover:text-white">
                        <span>{{ $group['label'] }}</span>
                        <i class="fa-solid fa-chevron-right text-[10px] transition-transform duration-200" :style="open ? 'transform:rotate(90deg)' : ''" aria-hidden="true"></i>
                    </button>
                </template>
                <template x-if="collapsed"><div class="mx-2 my-1.5 hidden border-t border-white/10 md:block"></div></template>
                <div x-show="open" x-collapse>
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            @php $active = ($module ?? '') === $item['id']; @endphp
                            <a href="{{ $item['route'] }}"
                               @class(['group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-200', 'text-white' => $active, 'text-white/60 hover:bg-white/10 hover:text-white' => !$active])
                               :class="collapsed && 'md:!justify-center md:!px-2'"
                               @if($active) style="background:linear-gradient(135deg,#E82757,#9A1255);" @endif>
                                <i class="fa-solid {{ $item['icon'] }} w-[18px] shrink-0" aria-hidden="true"></i>
                                <span class="truncate" :class="collapsed && 'md:!hidden'">{{ $item['label'] }}</span>
                                <span x-show="collapsed" class="pointer-events-none absolute left-full ml-3 z-50 hidden whitespace-nowrap rounded-lg border border-white/10 bg-plum-950 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 md:block">{{ $item['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforeach
    </nav>

    <div class="border-t border-white/10 px-3 pb-4 pt-3">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="group relative flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-white/60 transition hover:bg-white/10 hover:text-white" :class="collapsed && 'md:!justify-center md:!px-2'">
                <i class="fa-solid fa-right-from-bracket w-[18px] shrink-0" aria-hidden="true"></i>
                <span :class="collapsed && 'md:!hidden'">Log out</span>
            </button>
        </form>
    </div>
</div>
