<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurante do Dimas</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-stone-100 text-slate-900">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_#fff_0%,_#f5f5f4_45%,_#e7e5e4_100%)]">
        <div class="mx-auto flex min-h-screen max-w-6xl items-center px-6 py-10">
            <div class="grid w-full items-center gap-10 lg:grid-cols-2">
                <div class="order-2 lg:order-1">
                    <div class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-4 py-1 text-xs font-bold uppercase tracking-[0.2em] text-orange-700">
                        Sistema do Restaurante
                    </div>

                    <h1 class="mt-6 text-4xl font-black leading-tight text-slate-900 md:text-6xl">
                        Restaurante do Dimas
                    </h1>

                    <p class="mt-4 max-w-xl text-lg leading-8 text-slate-600">
                        Painel operacional para pedidos, clientes, impressao de comandas e atendimento pelo WhatsApp.
                    </p>

                    <div class="mt-8 flex flex-wrap items-center gap-4">
                        <a href="{{ route('login') }}" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-bold text-white transition hover:bg-slate-800">
                            Entrar no sistema
                        </a>
                    </div>

                    <div class="mt-10 grid gap-4 sm:grid-cols-3">
                        <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                            <div class="text-sm font-black uppercase text-slate-900">Pedidos</div>
                            <div class="mt-2 text-sm text-slate-600">Controle de mesa, retirada no balcao e delivery.</div>
                        </div>
                        <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                            <div class="text-sm font-black uppercase text-slate-900">WhatsApp</div>
                            <div class="mt-2 text-sm text-slate-600">Atendimento automatizado com acompanhamento da equipe.</div>
                        </div>
                        <div class="rounded-2xl border border-stone-200 bg-white/90 p-4 shadow-sm">
                            <div class="text-sm font-black uppercase text-slate-900">Impressao</div>
                            <div class="mt-2 text-sm text-slate-600">Comandas e cupons enviados direto para a impressora termica.</div>
                        </div>
                    </div>
                </div>

                <div class="order-1 flex justify-center lg:order-2">
                    <div class="w-full max-w-md rounded-[2rem] border border-stone-200 bg-white p-8 shadow-2xl shadow-stone-300/40">
                        <img
                            src="{{ asset('images/restaurante-do-dimas.png') }}"
                            alt="Logo Restaurante do Dimas"
                            class="mx-auto w-full max-w-sm"
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
