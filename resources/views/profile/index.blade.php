<x-layouts.app title="Profile">
    <div class="mx-auto max-w-4xl space-y-6">
        @include('profile.partials.form', ['user' => $user])
    </div>
</x-layouts.app>
