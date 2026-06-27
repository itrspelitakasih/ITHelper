<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} | {{ $appName }}</title>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js -->
    {{-- <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script> --}}

    <!-- Theme Store -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('theme', {
                init() {
                    const savedTheme = localStorage.getItem('theme');
                    const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                        'light';
                    this.theme = savedTheme || systemTheme;
                    this.updateTheme();
                },
                theme: 'light',
                toggle() {
                    this.theme = this.theme === 'light' ? 'dark' : 'light';
                    localStorage.setItem('theme', this.theme);
                    this.updateTheme();
                },
                updateTheme() {
                    const html = document.documentElement;
                    const body = document.body;
                    if (this.theme === 'dark') {
                        html.classList.add('dark');
                        body.classList.add('dark', 'bg-gray-900');
                    } else {
                        html.classList.remove('dark');
                        body.classList.remove('dark', 'bg-gray-900');
                    }
                }
            });

            Alpine.store('sidebar', {
                // Initialize based on screen size
                isExpanded: window.innerWidth >= 1280, // true for desktop, false for mobile
                isMobileOpen: false,
                isHovered: false,
                surpriseOpen: false,

                toggleExpanded() {
                    this.isExpanded = !this.isExpanded;
                    // When toggling desktop sidebar, ensure mobile menu is closed
                    this.isMobileOpen = false;
                },

                toggleMobileOpen() {
                    this.isMobileOpen = !this.isMobileOpen;
                    // Don't modify isExpanded when toggling mobile menu
                },

                setMobileOpen(val) {
                    this.isMobileOpen = val;
                },

                setHovered(val) {
                    // Only allow hover effects on desktop when sidebar is collapsed
                    if (window.innerWidth >= 1280 && !this.isExpanded) {
                        this.isHovered = val;
                    }
                }
            });
        });
    </script>

    <!-- Apply dark mode immediately to prevent flash -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme');
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            const theme = savedTheme || systemTheme;
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
                document.body.classList.add('dark', 'bg-gray-900');
            } else {
                document.documentElement.classList.remove('dark');
                document.body.classList.remove('dark', 'bg-gray-900');
            }
        })();
    </script>
    
</head>

<body
    x-data="{ 'loaded': true}"
    x-init="$store.sidebar.isExpanded = window.innerWidth >= 1280;
    const checkMobile = () => {
        if (window.innerWidth < 1280) {
            $store.sidebar.setMobileOpen(false);
            $store.sidebar.isExpanded = false;
        } else {
            $store.sidebar.isMobileOpen = false;
            $store.sidebar.isExpanded = true;
        }
    };
    window.addEventListener('resize', checkMobile);">

    {{-- preloader --}}
    <x-common.preloader/>
    {{-- preloader end --}}

    <div class="min-h-screen xl:flex">
        @include('layouts.backdrop')
        @include('layouts.sidebar')

        <div class="min-w-0 flex-1 transition-all duration-300 ease-in-out"
            :class="{
                'xl:ml-[290px]': $store.sidebar.isExpanded || $store.sidebar.isHovered,
                'xl:ml-[90px]': !$store.sidebar.isExpanded && !$store.sidebar.isHovered,
                'ml-0': $store.sidebar.isMobileOpen
            }">
            <!-- app header start -->
            @include('layouts.app-header')
            <!-- app header end -->
            <div class="w-full min-w-0 p-4 md:p-6">
                @yield('content')
            </div>
        </div>

    </div>

    @include('layouts.donation-modal')
</body>

@stack('scripts')

<script>
/**
 * Global Date Filter Converter
 * Converts all input[type="date"] with name="from" or name="to"
 * from browser-locale MM/DD/YYYY display to Indonesian DD/MM/YYYY format.
 * Server always receives YYYY-MM-DD via a hidden input.
 */
document.addEventListener('DOMContentLoaded', function () {
    function toDisplay(ymd) {
        if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return '';
        const [y, m, d] = ymd.split('-');
        return d + '/' + m + '/' + y;
    }

    function toYMD(dmy) {
        if (!dmy) return '';
        const parts = dmy.split('/');
        if (parts.length === 3 && parts[2].length === 4) {
            return parts[2] + '-' + parts[1].padStart(2, '0') + '-' + parts[0].padStart(2, '0');
        }
        return '';
    }

    const selectors = [
        'input[type="date"][name="from"]',
        'input[type="date"][name="to"]',
        'input[type="date"][name="tanggal"][form]',
    ];

    document.querySelectorAll(selectors.join(', ')).forEach(function (dateInput) {
        const originalName  = dateInput.name;
        const originalValue = dateInput.value;       // YYYY-MM-DD
        const originalClass = dateInput.getAttribute('class') || '';

        // Text input for display (DD/MM/YYYY)
        const textInput = document.createElement('input');
        textInput.type        = 'text';
        textInput.value       = toDisplay(originalValue);
        textInput.placeholder = 'TT/BB/TTTT';
        textInput.setAttribute('class', originalClass);
        textInput.setAttribute('autocomplete', 'off');
        // Copy any data-* or id attributes
        if (dateInput.id) textInput.id = dateInput.id;

        // Hidden input carries the real YYYY-MM-DD value
        const hiddenInput = document.createElement('input');
        hiddenInput.type  = 'hidden';
        hiddenInput.name  = originalName;
        hiddenInput.value = originalValue;

        // Sync text → hidden on every keystroke
        textInput.addEventListener('input', function () {
            hiddenInput.value = toYMD(this.value) || this.value;
        });

        // Auto-format after blur: normalize partial entries
        textInput.addEventListener('blur', function () {
            const ymd = toYMD(this.value);
            if (ymd) {
                this.value = toDisplay(ymd);
                hiddenInput.value = ymd;
            }
        });

        // Remove name from original so it doesn't submit twice
        dateInput.removeAttribute('name');
        dateInput.style.display = 'none';

        dateInput.parentNode.insertBefore(textInput, dateInput);
        dateInput.parentNode.insertBefore(hiddenInput, dateInput);
    });
});
</script>

</html>
