/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

/**
 * Alpine.data(...) components used by pages loaded via wire:navigate must be
 * registered here, in the bundle loaded once in <head> — never in a
 * per-page @push('scripts') block. Livewire's wire:navigate re-inserts a
 * page's <script> tags asynchronously (a `src`-based <script> must be
 * fetched), but it initializes Alpine on the swapped-in content
 * synchronously, in the same tick. A page-local script that calls
 * Alpine.data() on its own therefore loses the race on every navigation
 * after the first real page load — "x is not defined" — even though it
 * works fine on a full page load/reload, where document order guarantees it
 * runs before Alpine starts. Registering here instead runs exactly once,
 * before Alpine ever starts, and the registration then holds for the whole
 * browser session regardless of how the user navigates.
 */
import './calls';
import './report-chart';
