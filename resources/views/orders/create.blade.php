@extends('layouts.app')

@section('header', 'Novo Pedido')

@section('content')
<div class="max-w-6xl bg-white p-8 rounded-xl shadow-sm border border-gray-200 mx-auto">
    <form action="{{ route('orders.store') }}" method="POST" class="space-y-8">
        @csrf
        <input type="hidden" name="customer_mode" id="customer_mode" value="{{ old('customer_mode', $selectedCustomer ? 'existing' : 'new') }}">

        @if($errors->any())
            <div class="p-4 mb-6 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
                <div class="flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-4a1 1 0 00-1 1v1a1 1 0 102 0v-1a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                    <span class="font-bold">Erro ao salvar pedido:</span>
                </div>
                <ul class="list-disc list-inside text-sm opacity-90">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[1.3fr_1fr] gap-6">
            <section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 space-y-5">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-wide text-slate-500">Atendimento manual</div>
                        <h2 class="text-xl font-bold text-slate-900">Quem esta fazendo o pedido?</h2>
                    </div>
                    <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1">
                        <button type="button" id="existing-customer-button" onclick="setCustomerMode('existing')" class="rounded-lg px-4 py-2 text-sm font-semibold transition">Cliente ja cadastrado</button>
                        <button type="button" id="new-customer-button" onclick="setCustomerMode('new')" class="rounded-lg px-4 py-2 text-sm font-semibold transition">Cadastrar na hora</button>
                    </div>
                </div>

                <div id="existing-customer-panel" class="space-y-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Selecionar Cliente</label>
                        <select name="customer_id" id="customer_id" onchange="handleCustomerChange()" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="">Selecione um cliente...</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer->id }}"
                                    {{ (string) old('customer_id', $selectedCustomer?->id) === (string) $customer->id ? 'selected' : '' }}>
                                    {{ $customer->name }} ({{ $customer->phone }})
                                </option>
                            @endforeach
                        </select>
                        <div class="text-xs text-slate-500">Use para clientes que ja tem cadastro e historico no sistema.</div>
                    </div>

                    <div id="customer-summary" class="hidden rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-xs font-bold uppercase tracking-wide text-emerald-700">Cliente selecionado</div>
                                <div class="mt-1 text-lg font-bold text-emerald-950" id="customer-summary-name">-</div>
                                <div class="text-sm text-emerald-800" id="customer-summary-phone">-</div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm md:min-w-[280px]">
                                <div class="rounded-lg bg-white/80 px-3 py-2 border border-emerald-100">
                                    <div class="text-xs uppercase tracking-wide text-emerald-600">Enderecos</div>
                                    <div class="font-bold text-emerald-950" id="customer-summary-addresses">0</div>
                                </div>
                                <div class="rounded-lg bg-white/80 px-3 py-2 border border-emerald-100">
                                    <div class="text-xs uppercase tracking-wide text-emerald-600">Taxa padrao</div>
                                    <div class="font-bold text-emerald-950" id="customer-summary-fee">R$ 0,00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="new-customer-panel" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2 flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Nome do Cliente</label>
                            <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Nome de quem esta pedindo">
                        </div>
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Telefone</label>
                            <input type="text" name="customer_phone" id="customer_phone" value="{{ old('customer_phone', request('phone')) }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="(00) 00000-0000">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">E-mail</label>
                            <input type="email" name="customer_email" value="{{ old('customer_email') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Opcional">
                        </div>
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            Cadastre na hora quando o cliente ligar ou chegar no balcao. Se for retirada ou mesa, voce pode finalizar sem endereco.
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Tipo de Pedido</label>
                        <select name="type" id="order_type" onchange="toggleAddress()" required class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="delivery" {{ old('type', 'delivery') === 'delivery' ? 'selected' : '' }}>Delivery (Entrega)</option>
                            <option value="counter" {{ old('type') === 'counter' ? 'selected' : '' }}>Balcao (Retirada)</option>
                            <option value="table" {{ old('type') === 'table' ? 'selected' : '' }}>Mesa (Consumo no local)</option>
                        </select>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Status Inicial</label>
                        <select name="status" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                            <option value="pending" {{ old('status', 'pending') === 'pending' ? 'selected' : '' }}>Pendente</option>
                            <option value="preparing" {{ old('status') === 'preparing' ? 'selected' : '' }}>Em Preparo</option>
                        </select>
                    </div>
                </div>

                <div id="address-section" class="hidden space-y-4 rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-bold uppercase tracking-wider text-blue-800">Entrega</h3>
                            <div class="text-xs text-blue-700">Preencha o minimo necessario para o entregador sair sem duvida.</div>
                        </div>
                        <div class="rounded-lg bg-white/80 px-3 py-2 text-xs font-semibold text-blue-800 border border-blue-100">
                            Fluxo otimizado para atendimento manual
                        </div>
                    </div>

                    <div id="existing-address-panel" class="space-y-4">
                        <div class="flex flex-col gap-2">
                            <label class="text-sm font-semibold text-gray-700">Endereco do Cliente</label>
                            <select name="address_id" id="address_id" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white">
                                <option value="">Selecione o endereco...</option>
                            </select>
                            <div id="address-helper" class="text-xs text-blue-700">Escolha um cliente cadastrado para carregar os enderecos.</div>
                        </div>
                    </div>

                    <div id="new-address-panel" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Rua</label>
                                <input type="text" name="new_address[street]" id="new_address_street" value="{{ old('new_address.street') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Rua / Avenida">
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col gap-2">
                                    <label class="text-sm font-semibold text-gray-700">Numero</label>
                                    <input type="text" name="new_address[number]" id="new_address_number" value="{{ old('new_address.number') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="123">
                                </div>
                                <div class="flex flex-col gap-2">
                                    <label class="text-sm font-semibold text-gray-700">Complemento</label>
                                    <input type="text" name="new_address[complement]" value="{{ old('new_address.complement') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Casa, apto, bloco">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="md:col-span-2 flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Bairro</label>
                                <input type="text" name="new_address[neighborhood]" id="new_address_neighborhood" value="{{ old('new_address.neighborhood') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Bairro">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Cidade</label>
                                <input type="text" name="new_address[city]" id="new_address_city" value="{{ old('new_address.city') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Cidade">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">UF</label>
                                <input type="text" name="new_address[state]" id="new_address_state" value="{{ old('new_address.state') }}" maxlength="2" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white uppercase" placeholder="AM">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">CEP</label>
                                <input type="text" name="new_address[zip_code]" id="new_address_zip_code" value="{{ old('new_address.zip_code') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="00000-000">
                            </div>
                            <div class="flex flex-col gap-2">
                                <label class="text-sm font-semibold text-gray-700">Referencia</label>
                                <input type="text" name="new_address[reference]" value="{{ old('new_address.reference') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Perto de algum ponto conhecido">
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Taxa de Entrega</label>
                        <input type="number" name="delivery_fee" id="delivery_fee" min="0" step="0.01" value="{{ old('delivery_fee', 0) }}" onchange="calculateTotal()" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Ex: 5,00">
                        <div class="text-xs text-gray-500">A taxa e sugerida automaticamente para clientes cadastrados, mas o operador pode ajustar em qualquer atendimento.</div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Forma de Pagamento</label>
                        <select name="payment_method" id="payment_method" onchange="toggleChangeField()" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" required>
                            <option value="pix" {{ old('payment_method', 'pix') === 'pix' ? 'selected' : '' }}>Pix</option>
                            <option value="debit" {{ old('payment_method') === 'debit' ? 'selected' : '' }}>Debito</option>
                            <option value="credit" {{ old('payment_method') === 'credit' ? 'selected' : '' }}>Credito</option>
                            <option value="cash" {{ old('payment_method') === 'cash' ? 'selected' : '' }}>Dinheiro</option>
                        </select>
                    </div>

                    <div id="change-for-section" class="hidden flex flex-col gap-2">
                        <label class="text-sm font-semibold text-gray-700">Troco Para</label>
                        <input type="number" name="change_for" id="change_for" min="0" step="0.01" value="{{ old('change_for') }}" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none bg-white" placeholder="Ex: 50,00">
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <label class="text-sm font-semibold text-gray-700">Observacoes</label>
                    <textarea name="observations" rows="3" class="p-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-blue-500 outline-none" placeholder="Ex: retirar cebola, enviar para mesa 3, ponto da carne...">{{ old('observations') }}</textarea>
                </div>
            </section>
        </div>

        <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Itens do Pedido</h3>
                    <div class="text-sm text-slate-500">Monte o pedido sem sair da tela. O total atualiza automaticamente.</div>
                </div>
                <button type="button" onclick="addItem()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm font-bold transition">+ Adicionar Item</button>
            </div>

            <div id="order-items" class="space-y-3"></div>

            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end pt-4 border-t border-gray-100">
                <div class="rounded-xl bg-slate-50 border border-slate-200 px-4 py-3 text-sm text-slate-600">
                    Use este fluxo para atendimento de telefone, balcao e mesa. Se o cliente for novo, ja cadastre aqui e finalize em uma unica passada.
                </div>
                <div class="text-right md:text-left">
                    <span class="text-gray-500 text-sm">Total do Pedido:</span>
                    <div class="text-3xl font-bold text-gray-800" id="display-total">R$ 0,00</div>
                    <div class="text-sm text-gray-600 mt-2">Taxa de entrega: <span id="display-delivery-fee">R$ 0,00</span></div>
                    <input type="hidden" name="total_amount" id="total_amount" value="0">
                </div>
            </div>
        </section>

        <div class="flex justify-end gap-4 pt-2">
            <a href="{{ route('orders.index') }}" class="px-6 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 transition">Cancelar</a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg text-sm font-bold transition shadow-lg">Finalizar Pedido</button>
        </div>
    </form>
</div>

@php
    $orderCustomersPayload = $customers->map(function ($customer) {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'addresses' => $customer->addresses->map(function ($address) {
                return [
                    'id' => $address->id,
                    'label' => trim($address->street . ', ' . $address->number . ' - ' . $address->neighborhood, ' -'),
                    'is_primary' => (bool) $address->is_primary,
                    'last_delivery_fee' => $address->last_delivery_fee !== null ? (float) $address->last_delivery_fee : null,
                ];
            })->values()->all(),
        ];
    })->values()->all();
@endphp

<script>
    const products = @json($products);
    const customers = @json($orderCustomersPayload);

    let itemCount = 0;
    let currentCustomerMode = @json(old('customer_mode', $selectedCustomer ? 'existing' : 'new'));

    function selectedCustomer() {
        const customerId = Number(document.getElementById('customer_id').value || 0);
        return customers.find(customer => customer.id === customerId) || null;
    }

    function formatMoney(value) {
        return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setDeliveryFee(value) {
        const feeInput = document.getElementById('delivery_fee');
        feeInput.value = Number(value || 0).toFixed(2);
        calculateTotal();
    }

    function updateCustomerSummary(customer) {
        const summary = document.getElementById('customer-summary');

        if (!customer) {
            summary.classList.add('hidden');
            return;
        }

        const fees = customer.addresses
            .map(address => address.last_delivery_fee)
            .filter(fee => fee !== null);

        document.getElementById('customer-summary-name').innerText = customer.name;
        document.getElementById('customer-summary-phone').innerText = customer.phone;
        document.getElementById('customer-summary-addresses').innerText = customer.addresses.length;
        document.getElementById('customer-summary-fee').innerText = fees.length ? formatMoney(fees[0]) : 'R$ 0,00';
        summary.classList.remove('hidden');
    }

    function applyAddressDefaults() {
        const addressSelect = document.getElementById('address_id');
        const selectedOption = addressSelect.options[addressSelect.selectedIndex];
        const suggestedFee = selectedOption?.dataset.deliveryFee;

        if (suggestedFee !== undefined && suggestedFee !== '') {
            setDeliveryFee(suggestedFee);
            return;
        }

        if (document.getElementById('order_type').value === 'delivery') {
            setDeliveryFee(document.getElementById('delivery_fee').value || 0);
        }
    }

    function populateAddresses() {
        const customer = selectedCustomer();
        const addressSelect = document.getElementById('address_id');
        const helper = document.getElementById('address-helper');
        const oldAddressId = @json(old('address_id'));

        addressSelect.innerHTML = '<option value="">Selecione o endereco...</option>';

        if (!customer) {
            helper.innerText = 'Escolha um cliente cadastrado para carregar os enderecos.';
            setDeliveryFee(0);
            return;
        }

        if (customer.addresses.length === 0) {
            helper.innerText = 'Esse cliente ainda nao possui endereco cadastrado.';
            setDeliveryFee(0);
            return;
        }

        customer.addresses.forEach(address => {
            const option = document.createElement('option');
            option.value = address.id;
            option.textContent = `${address.label}${address.is_primary ? ' (principal)' : ''}`;
            option.dataset.deliveryFee = address.last_delivery_fee ?? '';
            addressSelect.appendChild(option);
        });

        const preferredAddress = customer.addresses.find(address => String(address.id) === oldAddressId)
            || customer.addresses.find(address => address.is_primary)
            || customer.addresses[0];

        addressSelect.value = String(preferredAddress.id);
        helper.innerText = `${customer.addresses.length} endereco(s) carregado(s) para ${customer.name}.`;
        applyAddressDefaults();
    }

    function handleCustomerChange() {
        if (currentCustomerMode !== 'existing') {
            return;
        }

        const customer = selectedCustomer();
        updateCustomerSummary(customer);
        populateAddresses();
        toggleAddress();
    }

    function setCustomerMode(mode) {
        currentCustomerMode = mode;
        document.getElementById('customer_mode').value = mode;

        const existingPanel = document.getElementById('existing-customer-panel');
        const newPanel = document.getElementById('new-customer-panel');
        const existingButton = document.getElementById('existing-customer-button');
        const newButton = document.getElementById('new-customer-button');
        const customerId = document.getElementById('customer_id');
        const customerName = document.getElementById('customer_name');
        const customerPhone = document.getElementById('customer_phone');

        existingPanel.classList.toggle('hidden', mode !== 'existing');
        newPanel.classList.toggle('hidden', mode !== 'new');

        existingButton.className = mode === 'existing'
            ? 'rounded-lg px-4 py-2 text-sm font-semibold transition bg-slate-900 text-white'
            : 'rounded-lg px-4 py-2 text-sm font-semibold transition text-slate-700';
        newButton.className = mode === 'new'
            ? 'rounded-lg px-4 py-2 text-sm font-semibold transition bg-slate-900 text-white'
            : 'rounded-lg px-4 py-2 text-sm font-semibold transition text-slate-700';

        customerId.required = mode === 'existing';
        customerName.required = mode === 'new';
        customerPhone.required = mode === 'new';

        if (mode === 'existing') {
            updateCustomerSummary(selectedCustomer());
            populateAddresses();
        } else {
            document.getElementById('customer-summary').classList.add('hidden');
            document.getElementById('address_id').innerHTML = '<option value="">Selecione o endereco...</option>';
            document.getElementById('address-helper').innerText = 'Preencha o endereco logo abaixo para cadastrar junto com o pedido.';
        }

        toggleAddress();
    }

    function toggleAddress() {
        const type = document.getElementById('order_type').value;
        const isDelivery = type === 'delivery';
        const section = document.getElementById('address-section');
        const addressSelect = document.getElementById('address_id');
        const deliveryFeeInput = document.getElementById('delivery_fee');
        const existingAddressPanel = document.getElementById('existing-address-panel');
        const newAddressPanel = document.getElementById('new-address-panel');
        const newAddressFields = [
            'new_address_street',
            'new_address_number',
            'new_address_neighborhood',
            'new_address_city',
            'new_address_state',
            'new_address_zip_code',
        ].map(id => document.getElementById(id));

        section.classList.toggle('hidden', !isDelivery);
        addressSelect.required = isDelivery && currentCustomerMode === 'existing';
        deliveryFeeInput.required = isDelivery;

        existingAddressPanel.classList.toggle('hidden', currentCustomerMode !== 'existing');
        newAddressPanel.classList.toggle('hidden', currentCustomerMode !== 'new');

        newAddressFields.forEach(field => {
            field.required = isDelivery && currentCustomerMode === 'new';
        });

        if (!isDelivery) {
            addressSelect.required = false;
            deliveryFeeInput.required = false;
            setDeliveryFee(0);
            return;
        }

        if (currentCustomerMode === 'existing') {
            if (!addressSelect.value) {
                populateAddresses();
            }
            applyAddressDefaults();
        } else {
            calculateTotal();
        }
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

    function addItem() {
        itemCount++;
        const container = document.getElementById('order-items');
        const div = document.createElement('div');
        div.className = 'grid grid-cols-1 md:grid-cols-[1fr_100px_140px_auto] gap-4 items-center p-4 bg-gray-50 rounded-lg border border-gray-200';
        div.innerHTML = `
            <div class="flex-1">
                <select name="items[${itemCount}][product_id]" onchange="updatePrice(${itemCount})" required class="w-full p-3 rounded border border-gray-300 outline-none">
                    <option value="">Selecione o prato...</option>
                    ${products.map(p => `<option value="${p.id}" data-price="${p.price}">${p.name} (R$ ${p.price})</option>`).join('')}
                </select>
            </div>
            <div>
                <input type="number" name="items[${itemCount}][quantity]" value="1" min="1" onchange="updatePrice(${itemCount})" required class="w-full p-3 rounded border border-gray-300 outline-none">
            </div>
            <div class="text-right font-semibold text-gray-700 item-subtotal" id="subtotal-${itemCount}">R$ 0,00</div>
            <button type="button" onclick="this.parentElement.remove(); calculateTotal();" class="text-red-500 hover:text-red-700 text-sm font-semibold">
                Remover
            </button>
        `;
        container.appendChild(div);
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

        const finalTotal = total + deliveryFee;
        document.getElementById('display-delivery-fee').innerText = formatMoney(deliveryFee);
        document.getElementById('display-total').innerText = formatMoney(finalTotal);
        document.getElementById('total_amount').value = finalTotal;
    }

    window.onload = () => {
        addItem();
        document.getElementById('address_id').addEventListener('change', applyAddressDefaults);
        setCustomerMode(currentCustomerMode);
        toggleAddress();
        toggleChangeField();
    };
</script>
@endsection
