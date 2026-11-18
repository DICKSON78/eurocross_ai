<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thank you | EuroCross</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
@fonts @vite(['resources/css/app.css','resources/js/marketing.js'])
</head>
<body class="min-h-screen bg-cream text-plum-900">
<div class="flex min-h-screen items-center justify-center px-6 py-12">
<div class="w-full max-w-lg text-center page-enter">
    <span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-ec-primary text-white"><i class="fa-solid fa-check text-2xl" aria-hidden="true"></i></span>
    <h1 class="mt-6 text-3xl font-bold">Thank you for your purchase!</h1>
    <p class="mt-3 text-plum-900/60">Your plan will activate in about a minute, once Gumroad confirms the payment. We'll refresh this page automatically.</p>

    <div id="ec-billing-status" class="mt-8 rounded-2xl border border-plum-900/6 bg-white p-6 text-left">
        <p class="section-label">Current status</p>
        <p id="ec-billing-status-text" class="mt-2 text-sm font-semibold">Checking…</p>
    </div>

    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
        <a href="{{ route('dashboard') }}" class="ec-action">Go to dashboard</a>
        <a href="{{ route('home') }}" class="ec-action-light">Back to website</a>
    </div>
    <p class="mt-6 text-xs text-plum-900/40">Payment processed securely by Gumroad.</p>
</div>
</div>
<script>
(function () {
    const text = document.getElementById('ec-billing-status-text');
    let attempts = 0;

    async function check() {
        attempts += 1;
        try {
            const response = await fetch('{{ route('billing.status') }}', {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json();
                text.textContent = 'Plan: ' + data.plan + ' — ' + data.status;

                if (data.status === 'active' || data.access?.allowed) {
                    window.setTimeout(() => window.location.reload(), 1200);
                    return;
                }
            }
        } catch (error) {
            text.textContent = 'Still waiting for confirmation…';
        }

        if (attempts < 20) {
            window.setTimeout(check, 5000);
        } else {
            text.textContent = 'Still pending. Please check your dashboard in a moment.';
        }
    }

    check();
})();
</script>
</body>
</html>
