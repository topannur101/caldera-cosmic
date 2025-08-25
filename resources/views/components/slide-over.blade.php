@props([
    'name',
    'show' => false,
])
<div
    x-data="{
        show: @js($show),
        focusables() {
            // All focusable element types...
            let selector = 'input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                // All non-disabled elements...
                .filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="$watch('show', value => {
        if (value) {
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
        }
    })"
    x-on:open-slide-over.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-slide-over.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close-slide-over.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    class="relative w-auto h-auto">
   
    <template x-teleport="body">
        <div
            x-show="show"
            class="relative z-50"
            style="display: {{ $show ? 'block' : 'none' }};">
            <!-- Backdrop with click handler -->
            <div 
                x-show="show"                 
                class="fixed {{ session('mblur') ? 'backdrop-blur' : ''}} inset-0 transform transition-all"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
            >
                <div class="absolute inset-0 bg-neutral-500 dark:bg-neutral-900 opacity-75"></div>
            </div>
            <div class="fixed inset-0 overflow-hidden">
                <div @click="show = false" class="absolute inset-0 overflow-hidden">
                    <div class="fixed inset-y-0 right-0 flex max-w-full pl-10">
                        <div
                            x-show="show"
                            x-transition:enter="transform transition ease-out duration-300"
                            x-transition:enter-start="translate-x-full"
                            x-transition:enter-end="translate-x-0"
                            x-transition:leave="transform transition ease-in duration-200"
                            x-transition:leave-start="translate-x-0"
                            x-transition:leave-end="translate-x-full"
                            class="w-screen max-w-md">
                            <!-- Removed @click.away and kept only @click.stop -->
                            <div @click.stop class="bg-white dark:bg-neutral-800 flex flex-col h-full text-neutral-900 dark:text-neutral-100">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>