@extends('layouts.app')

@section('header', 'Editar Pedido #' . $order->id)

@section('content')
@php
    $oldItems = old('items');
    $itemsPayload = $oldItems
        ? collect($oldItems)->values()->map(fn ($item) => [
            'product_id' => $item['product_id'] ?? '',
            'quantity' => $item['quantity'] ?? 1,
        ])->values()
        : $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity,
        ])->values();
@endphp

<div class="max-w-6xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('orders.update', $order) }}" method="POST" class="space-y-8">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="p-4 mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
                <div class="font-bold mb-2">Erro ao atualizar pedido:</div>
                <ul class="list-disc list-inside text-sm opacity-90">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <a href="{{ route('orders.show', $order) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                Voltar para detalhes
            </a>
            <div class="flex justify-end gap-3">
                <a href="{{ route('orders.index') }}" class="px-5 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg text-sm font-bold transition shadow-lg">
                    Salvar Alteracoes
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_1fr] gap-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-5">
                <div>
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Cliente</div>
                    <h2 class="text-xl font-bold text-slate-900">Dados do atendimento</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2 flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Nome do Cliente</label>
                        <input type="text" name="customer_name" value="{{ old('customer_name', $order->customer->name) }}" required class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Telefone</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone', $order->customer->phone) }}" required class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">E-mail</label>
                        <input type="email" name="customer_email" value="{{ old('customer_email', $order->customer->email) }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Opcional">
                    </div>
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Status</label>
                        <select name="status" required class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="awaiting_acceptance" {{ old('status', $order->status) === 'awaiting_acceptance' ? 'selected' : '' }}>Aguardando Aceite</option>
                            <option value="pending" {{ old('status', $order->status) === 'pending' ? 'selected' : '' }}>Pendente</option>
                            <option value="preparing" {{ old('status', $order->status) === 'preparing' ? 'selected' : '' }}>Em Preparo</option>
                            <option value="shipped" {{ old('status', $order->status) === 'shipped' ? 'selected' : '' }}>Saindo para Entrega</option>
                            <option value="delivered" {{ old('status', $order->status) === 'delivered' ? 'selected' : '' }}>Entregue</option>
                            <option value="cancelled" {{ old('status', $order->status) === 'cancelled' ? 'selected' : '' }}>Cancelado</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Tipo de Pedido</label>
                        <select name="type" id="order_type" onchange="toggleAddress()" required class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="delivery" {{ old('type', $order->type) === 'delivery' ? 'selected' : '' }}>Delivery (Entrega)</option>
                            <option value="counter" {{ old('type', $order->type) === 'counter' ? 'selected' : '' }}>Balcao (Retirada)</option>
                            <option value="table" {{ old('type', $order->type) === 'table' ? 'selected' : '' }}>Mesa (Consumo no local)</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Taxa de Entrega</label>
                        <input type="number" name="delivery_fee" id="delivery_fee" min="0" step="0.01" value="{{ old('delivery_fee', $order->delivery_fee ?? 0) }}" onchange="calculateTotal()" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    </div>
                </div>

                <div id="address-section" class="hidden space-y-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Rua</label>
                            <input type="text" name="address[street]" id="address_street" value="{{ old('address.street', $order->address->street ?? '') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Numero</label>
                                <input type="text" name="address[number]" id="address_number" value="{{ old('address.number', $order->address->number ?? '') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Complemento</label>
                                <input type="text" name="address[complement]" value="{{ old('address.complement', $order->address->complement ?? '') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Bairro</label>
                            <input type="text" name="address[neighborhood]" id="address_neighborhood" value="{{ old('address.neighborhood', $order->address->neighborhood ?? '') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Referencia</label>
                            <input type="text" name="address[reference]" value="{{ old('address.reference', $order->address->reference ?? '') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Opcional">
                        </div>
                    </div>

                    <div class="rounded-xl border border-blue-100 bg-white/80 px-4 py-3 text-sm text-blue-800">
                        Cidade e UF serao salvos automaticamente como Manaus/AM. CEP e referencia sao opcionais.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Forma de Pagamento</label>
                        <select name="payment_method" id="payment_method" onchange="toggleChangeField()" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" required>
                            <option value="pix" {{ old('payment_method', $order->payment_method) === 'pix' ? 'selected' : '' }}>Pix</option>
                            <option value="debit" {{ old('payment_method', $order->payment_method) === 'debit' ? 'selected' : '' }}>Debito</option>
                            <option value="credit" {{ old('payment_method', $order->payment_method) === 'credit' ? 'selected' : '' }}>Credito</option>
                            <option value="cash" {{ old('payment_method', $order->payment_method) === 'cash' ? 'selected' : '' }}>Dinheiro</option>
                        </select>
                    </div>

                    <div id="change-for-section" class="hidden flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Troco Para</label>
                        <input type="number" name="change_for" id="change_for" min="0" step="0.01" value="{{ old('change_for', $order->change_for) }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Observacoes</label>
                    <textarea name="observations" rows="3" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none">{{ old('observations', $order->observations) }}</textarea>
                </div>
            </section>
        </div>

        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Itens do Pedido</h3>
                    <div class="text-sm text-slate-500">Altere produtos e quantidades. O total atualiza automaticamente.</div>
                </div>
                <button type="button" onclick="addItem()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-bold transition">+ Adicionar Item</button>
            </div>

            <div id="order-items" class="space-y-3"></div>

            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end pt-4 border-t border-gray-100">
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">
                    Ao salvar, os itens do pedido serao recalculados com o preco atual do produto.
                </div>
                <div class="text-right md:text-left">
                    <span class="text-gray-500 text-sm">Total do Pedido:</span>
                    <div class="text-3xl font-bold text-gray-800" id="display-total">R$ 0,00</div>
                    <div class="text-sm text-gray-600 mt-2">Taxa de entrega: <span id="display-delivery-fee">R$ 0,00</span></div>
                </div>
            </div>
        </section>
    </form>
</div>

<script>
    const products = @json($products->map(fn ($product) => [
        'id' => $product->id,
        'name' => $product->name,
        'price' => (float) $product->price,
    ])->values());
    const initialItems = @json($itemsPayload);

    let itemCount = 0;

    function formatMoney(value) {
        return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function productOptions(selectedProductId) {
        return products.map(product => {
            const selected = String(product.id) === String(selectedProductId) ? 'selected' : '';
            return `<option value="${product.id}" data-price="${product.price}" ${selected}>${product.name} (R$ ${Number(product.price).toFixed(2)})</option>`;
        }).join('');
    }

    function addItem(item = null) {
        itemCount++;
        const container = document.getElementById('order-items');
        const div = document.createElement('div');
        div.className = 'grid grid-cols-1 md:grid-cols-[1fr_100px_140px_auto] gap-4 items-center p-4 bg-gray-50 rounded-lg border border-gray-200';
        div.innerHTML = `
            <div class="flex-1">
                <select name="items[${itemCount}][product_id]" onchange="updatePrice(${itemCount})" required class="w-full p-3 rounded border border-gray-300 outline-none">
                    <option value="">Selecione o prato...</option>
                    ${productOptions(item?.product_id)}
                </select>
            </div>
            <div>
                <input type="number" name="items[${itemCount}][quantity]" value="${item?.quantity || 1}" min="1" onchange="updatePrice(${itemCount})" required class="w-full p-3 rounded border border-gray-300 outline-none">
            </div>
            <div class="text-right font-semibold text-gray-700 item-subtotal" id="subtotal-${itemCount}">R$ 0,00</div>
            <button type="button" onclick="removeItem(this)" class="text-red-500 hover:text-red-700 text-sm font-semibold">
                Remover
            </button>
        `;
        container.appendChild(div);
        updatePrice(itemCount);
    }

    function removeItem(button) {
        if (document.querySelectorAll('#order-items > div').length === 1) {
            return;
        }

        button.parentElement.remove();
        calculateTotal();
    }

    function updatePrice(id) {
        const select = document.querySelector(`select[name="items[${id}][product_id]"]`);
        const price = Number(select.options[select.selectedIndex]?.getAttribute('data-price') || 0);
        const qty = Number(document.querySelector(`input[name="items[${id}][quantity]"]`).value || 0);
        const subtotal = price * qty;

        document.getElementById(`subtotal-${id}`).innerText = formatMoney(subtotal);
        calculateTotal();
    }

    function calculateTotal() {
        let total = 0;
        const type = document.getElementById('order_type').value;
        const deliveryFee = type === 'delivery'
            ? parseFloat(document.getElementById('delivery_fee').value || 0)
            : 0;

        document.querySelectorAll('.item-subtotal').forEach(el => {
            const val = parseFloat(el.innerText.replace('R$ ', '').replace('.', '').replace(',', '.'));
            total += Number.isNaN(val) ? 0 : val;
        });

        document.getElementById('display-delivery-fee').innerText = formatMoney(deliveryFee);
        document.getElementById('display-total').innerText = formatMoney(total + deliveryFee);
    }

    function toggleAddress() {
        const type = document.getElementById('order_type').value;
        const isDelivery = type === 'delivery';
        const deliveryFeeInput = document.getElementById('delivery_fee');
        const requiredAddressFields = [
            'address_street',
            'address_number',
            'address_neighborhood',
        ].map(id => document.getElementById(id));

        document.getElementById('address-section').classList.toggle('hidden', !isDelivery);
        deliveryFeeInput.required = isDelivery;
        deliveryFeeInput.disabled = !isDelivery;

        requiredAddressFields.forEach(field => {
            field.required = isDelivery;
        });

        if (!isDelivery) {
            deliveryFeeInput.value = '0.00';
        }

        calculateTotal();
    }

    function toggleChangeField() {
        const paymentMethod = document.getElementById('payment_method').value;
        const section = document.getElementById('change-for-section');
        const changeInput = document.getElementById('change_for');

        if (paymentMethod === 'cash') {
            section.classList.remove('hidden');
        } else {
            section.classList.add('hidden');
            changeInput.value = '';
        }
    }

    window.onload = () => {
        if (initialItems.length) {
            initialItems.forEach(item => addItem(item));
        } else {
            addItem();
        }

        toggleAddress();
        toggleChangeField();
    };
</script>
@endsection
