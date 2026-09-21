<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitation unavailable</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-neutral-50 text-neutral-800 antialiased dark:bg-neutral-950 dark:text-neutral-100">
    <div class="mx-auto flex min-h-full max-w-md flex-col justify-center px-6 py-12">
        <div class="rounded-xl border border-neutral-200 bg-white p-8 text-center shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
            <h1 class="text-lg font-semibold">This invitation is no longer valid</h1>
            <p class="mt-2 text-sm text-neutral-500">
                It may have expired or already been used. Ask your administrator at
                {{ $invitation->tenant->name }} to send a new one.
            </p>
        </div>
    </div>
</body>
</html>
