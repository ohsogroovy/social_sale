<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    <x-nav-link :href="route('current-live-streams')" :active="request()->routeIs('current-live-streams')">
                        {{ __('Live Streams') }}
                    </x-nav-link>

                    <x-nav-link :href="route('past-streams')" :active="request()->routeIs('past-streams')">
                        {{ __('Past Streams') }}
                    </x-nav-link>
                     <x-nav-link :href="route('generated-tags')" :active="request()->routeIs('generated-tags')">
                        {{ __('Generated Tags') }}

                    </x-nav-link>
                </div>
            </div>


            <div class="flex items-center gap-3">

{{-- @if (str_contains(request()->url(), 'current-live-stream')) --}}
<div>
    <input
        type="text"
        id="searchQuery"
        placeholder="Search product by SKU..."
        class="p-2 border rounded placeholder:text-sm bg-transparent text-gray-200"
    />
</div>
{{-- @endif --}}
            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        @if (!auth()->user()->facebook_user_id)
                            <x-dropdown-link :href="'/auth/facebook'" target="_blank">
                                Facebook Authorization
                            </x-dropdown-link>
                        @endif

                        <div class="px-4 py-2">
                            <div class="flex items-center justify-between gap-2">
                                <label for="autoTagToggleMain" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Auto Tag</label>
                                <input
                                    type="checkbox"
                                    id="autoTagToggleMain"
                                    class="toggle-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    onchange="dispatchAutoTagToggleEvent(this.checked)"
                                />
                            </div>
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('current-live-streams')" :active="request()->routeIs('current-live-streams')">
                {{ __('Live Streams') }}
            </x-responsive-nav-link>

            <x-responsive-nav-link :href="route('past-streams')" :active="request()->routeIs('past-streams')">
                {{ __('Past Streams') }}
            </x-responsive-nav-link>
             <x-nav-link :href="route('generated-tags')" :active="request()->routeIs('generated-tags')">
                        {{ __('Generated Tags') }}
                    </x-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
             <div class="px-4 py-2 border-b">
                            <div class="flex items-center justify-between gap-2">
                                <label for="autoTagToggleResponsive" class="text-sm text-gray-700 dark:text-gray-300 cursor-pointer">Auto Tag</label>
                                <input
                                    type="checkbox"
                                    id="autoTagToggleResponsive"
                                    class="toggle-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    onchange="dispatchAutoTagToggleEvent(this.checked)"
                                />
                            </div>
                        </div>
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>


<script>
    function dispatchAutoTagToggleEvent(isChecked) {
        console.log("dispatching autoTagToggle", isChecked);
        const toggleEvent = new CustomEvent('autoTagToggle', { detail: { enabled: isChecked } });
        window.dispatchEvent(toggleEvent);
    }

    document.getElementById('searchQuery').addEventListener('input', function(event) {
        setTimeout(() => {
            const searchQuery = document.getElementById('searchQuery').value;
            console.log("dispatching searchQuery", searchQuery);
            const searchEvent = new CustomEvent('searchProduct', { detail: { query: searchQuery } });
            window.dispatchEvent(searchEvent);
        }, 50);
    });

    window.addEventListener('resetSearchField', function() {
        document.getElementById('searchQuery').value = '';
    });

    async function fetchUserSettings() {
        try {
            const response = await fetch('/user');
            const data = await response.json();
            const autoTriggerCheckboxes = [
                document.getElementById('autoTagToggleMain'),
                document.getElementById('autoTagToggleResponsive')
            ];
            autoTriggerCheckboxes.forEach(checkbox => {
                if (checkbox) {
                    checkbox.checked = data.user.auto_trigger;
                }
            });
        } catch (error) {
            console.error('Error fetching user settings:', error);
        }
    }

    document.addEventListener('DOMContentLoaded', fetchUserSettings);
</script>
