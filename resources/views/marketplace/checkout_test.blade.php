<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rivo Marketplace — Custom Product Checkout & Live Stripe Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://js.stripe.com/v3/"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">

    <div class="max-w-5xl mx-auto space-y-8">
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-800 pb-6 gap-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-orange-500 font-bold text-white shadow-lg shadow-orange-500/30">R</span>
                    <h1 class="text-2xl font-bold tracking-tight text-white">Rivo Marketplace Live Checkout</h1>
                </div>
                <p class="text-sm text-slate-400 mt-1">Real Stripe Payment Gateway, Cart & Webhook Simulator</p>
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Stripe Live Test Mode
                </span>
            </div>
        </div>

        <!-- Customer & Config Bar -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                <p class="text-xs font-medium text-slate-400">Authenticated Customer</p>
                <p class="text-base font-bold text-white mt-1">{{ $user->name ?? 'Guest User' }}</p>
                <p class="text-xs text-slate-400 truncate">Token: {{ substr($token, 0, 15) }}...</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                <p class="text-xs font-medium text-slate-400">Selected Delivery Address</p>
                <select id="select-address" class="w-full mt-1.5 bg-slate-950 border border-slate-700 rounded-lg p-1.5 text-xs text-white focus:outline-none focus:border-orange-500">
                    <option value="">Auto-resolve Default Address</option>
                    @foreach($addresses as $addr)
                        <option value="{{ $addr->id }}" {{ $addr->is_default ? 'selected' : '' }}>
                            {{ $addr->street_address }}, {{ $addr->city }} {{ $addr->is_default ? '(Default)' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-xl p-4">
                <p class="text-xs font-medium text-slate-400">Stripe Public Key</p>
                <p class="text-xs font-mono text-orange-400 mt-2 truncate">{{ $stripeKey }}</p>
            </div>
        </div>

        <!-- Custom Product Picker Bar -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-900/60 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>🛍️</span>
                        <span>Choose Any Product from Database</span>
                    </h3>
                    <p class="text-xs text-slate-400">Select any item and quantity to dynamically rebuild your cart and test checkout.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                <!-- Product Select -->
                <div class="sm:col-span-6 space-y-1">
                    <label class="text-xs font-semibold text-slate-400">Available Products</label>
                    <select id="picker-product" onchange="onProductSelectChange()" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-orange-500">
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" 
                                    data-price="{{ $p->discount_price ?: $p->price }}" 
                                    data-store="{{ $p->store?->name }}"
                                    data-variants="{{ json_encode($p->variants) }}">
                                {{ $p->name }} — ${{ number_format($p->discount_price ?: $p->price, 2) }} ({{ $p->store?->name }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Variant Select -->
                <div class="sm:col-span-3 space-y-1">
                    <label class="text-xs font-semibold text-slate-400">Variant (Optional)</label>
                    <select id="picker-variant" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2.5 text-sm text-white focus:outline-none focus:border-orange-500">
                        <option value="">No Variant / Default</option>
                    </select>
                </div>

                <!-- Quantity -->
                <div class="sm:col-span-1 space-y-1">
                    <label class="text-xs font-semibold text-slate-400">Qty</label>
                    <input id="picker-qty" type="number" min="1" max="10" value="1" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-2 py-2.5 text-sm text-white text-center focus:outline-none focus:border-orange-500">
                </div>

                <!-- Add to Cart Button -->
                <div class="sm:col-span-2">
                    <button id="btn-add-item" onclick="addItemToCart()" class="w-full py-2.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-bold text-xs transition duration-200 flex items-center justify-center gap-1.5 shadow">
                        <span>+ Set in Cart</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Main Test Interface -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            
            <!-- Left: Order Summary & Place Order -->
            <div class="lg:col-span-5 space-y-6">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                        <h2 class="text-lg font-bold text-white flex items-center justify-between w-full">
                            <span>Active Cart</span>
                            <span id="cart-item-count-badge" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-500/20 text-orange-400 border border-orange-500/30">
                                {{ $cart && $cart->items ? $cart->items->sum('quantity') : 0 }} items
                            </span>
                        </h2>
                    </div>

                    <!-- Items List -->
                    <div id="cart-items-container" class="divide-y divide-slate-800/60 max-h-56 overflow-y-auto pr-1">
                        @if($cart && $cart->items->isNotEmpty())
                            @foreach($cart->items as $item)
                                <div class="py-3 flex items-center justify-between gap-3 first:pt-0 last:pb-0">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-lg overflow-hidden border border-slate-700">
                                            @if($item->product->images && count($item->product->images))
                                                <img src="{{ $item->product->images[0] }}" class="w-full h-full object-cover">
                                            @else
                                                📦
                                            @endif
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-white truncate max-w-[160px]">{{ $item->product->name }}</p>
                                            <p class="text-xs text-slate-400">Qty: {{ $item->quantity }} × ${{ number_format($item->product->discount_price ?: $item->product->price, 2) }}</p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-bold text-white">
                                        ${{ number_format(($item->product->discount_price ?: $item->product->price) * $item->quantity, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        @else
                            <p class="text-sm text-slate-400 py-4 text-center">Cart is empty.</p>
                        @endif
                    </div>

                    <!-- Receipt Breakdown -->
                    <div class="border-t border-slate-800 pt-4 space-y-3 text-sm">
                        <!-- Delivery Speed Selector -->
                        <div class="flex justify-between items-center text-slate-400">
                            <span>Delivery Speed</span>
                            <select id="select-delivery-type" onchange="recalculateSummary()" class="bg-slate-950 border border-slate-700 rounded-lg px-2.5 py-1 text-xs text-white focus:outline-none focus:border-orange-500 font-medium">
                                <option value="express">Express (15-20 min)</option>
                                <option value="standard">Standard (~45-60 min)</option>
                                <option value="pickup">Store Pickup (In-store)</option>
                            </select>
                        </div>

                        <!-- Pro Free Delivery Benefit Toggle -->
                        <div class="flex justify-between items-center text-slate-400 bg-slate-950/60 border border-slate-800/80 rounded-xl p-2.5">
                            <span class="text-xs font-semibold text-slate-300">Customer Plan</span>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="toggle-pro" {{ $user && $user->isPro() ? 'checked' : '' }} onchange="recalculateSummary()" class="rounded border-slate-700 text-orange-500 focus:ring-0">
                                <span class="text-xs font-bold text-emerald-400" id="label-pro-status">👑 Rivo Pro ($0 Delivery)</span>
                            </label>
                        </div>

                        <div class="flex justify-between text-slate-400 pt-1">
                            <span>Delivery Fee</span>
                            <span class="font-bold text-emerald-400" id="display-delivery-fee">$0.00 (Pro Free)</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Estimated Tax (10%)</span>
                            <span class="font-medium text-white" id="display-tax">$5.00</span>
                        </div>
                        <div class="border-t border-slate-800 pt-3 flex justify-between items-baseline">
                            <span class="text-base font-bold text-white">Total Amount</span>
                            <span class="text-2xl font-extrabold text-orange-400" id="display-total">$54.99</span>
                        </div>
                    </div>

                    <!-- Step 1 Button: Place Order & Init Payment -->
                    <button id="btn-initiate-checkout" onclick="initiateCheckout()" class="w-full py-3.5 px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm shadow-lg shadow-orange-500/25 transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer">
                        <span>1. Place Order & Create Stripe Intent</span>
                    </button>
                </div>
            </div>

            <!-- Right: Stripe Elements & Payment Form -->
            <div class="lg:col-span-7 space-y-6">
                <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <span>2. Real Stripe Payment Form</span>
                        </h2>
                        <span id="badge-step" class="text-xs px-2.5 py-1 rounded-md bg-slate-800 text-slate-400 border border-slate-700">Awaiting Order</span>
                    </div>

                    <!-- Demo Card Info Pill -->
                    <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-3.5 text-xs text-blue-300 space-y-1">
                        <p class="font-semibold flex items-center gap-1.5 text-blue-200">
                            <span>💳</span> Standard Stripe Test Cards:
                        </p>
                        <p class="font-mono text-slate-300">Number: <span class="text-white font-bold">4242 •••• •••• 4242</span> | Exp: <span class="text-white font-bold">12/34</span> | CVC: <span class="text-white font-bold">123</span> | Zip: <span class="text-white font-bold">78701</span></p>
                    </div>

                    <!-- Stripe Card Element Mount Target -->
                    <form id="payment-form" onsubmit="handlePaymentSubmit(event)" class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Card Details</label>
                            <div id="payment-element" class="bg-slate-950 border border-slate-700/80 rounded-xl p-4 min-h-[44px]">
                                <p class="text-xs text-slate-500 italic text-center py-4">Click "Place Order" above to generate a real Stripe PaymentIntent & mount the Payment Form</p>
                            </div>
                            <div id="payment-errors" class="text-xs text-rose-400 mt-2 font-medium hidden"></div>
                        </div>

                        <!-- Order Meta Details -->
                        <div id="order-meta-card" class="hidden bg-slate-950/60 border border-slate-800 rounded-xl p-3 text-xs space-y-1 font-mono text-slate-300">
                            <div>Order Number: <span id="meta-order-number" class="text-orange-400 font-bold">-</span></div>
                            <div>Intent ID: <span id="meta-intent-id" class="text-emerald-400 font-bold">-</span></div>
                        </div>

                        <!-- Pay Button -->
                        <button id="btn-pay" type="submit" disabled class="w-full py-3.5 px-4 rounded-xl bg-slate-800 text-slate-500 font-bold text-sm transition-all duration-200 cursor-not-allowed flex items-center justify-center gap-2">
                            <span>2. Confirm Payment via Stripe</span>
                        </button>
                    </form>

                    <!-- Success Confirmation Banner -->
                    <div id="success-card" class="hidden bg-emerald-500/10 border border-emerald-500/30 rounded-2xl p-6 text-center space-y-3">
                        <div class="w-12 h-12 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto text-2xl">✓</div>
                        <h3 class="text-lg font-bold text-white">Payment Confirmed Successfully!</h3>
                        <p class="text-xs text-slate-300 max-w-sm mx-auto">
                            The Stripe PaymentIntent was charged, polymorphic <code class="text-emerald-400 font-bold">Transaction</code> status updated to <span class="text-emerald-400 font-bold">success</span>, and Order status updated to <span class="text-emerald-400 font-bold">paid</span>.
                        </p>
                        <div class="pt-2">
                            <button onclick="window.location.reload()" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition">
                                Test Another Order
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Live Log Box -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Live API Activity Log</span>
                        <button onclick="clearLog()" class="text-xs text-slate-500 hover:text-slate-300">Clear</button>
                    </div>
                    <pre id="log-box" class="bg-slate-950 rounded-xl p-3 text-[11px] font-mono text-slate-400 max-h-40 overflow-y-auto whitespace-pre-wrap">Awaiting user action...</pre>
                </div>
            </div>
        </div>
    </div>

<script>
    const STRIPE_KEY = @json($stripeKey);
    const BEARER_TOKEN = @json($token);
    const BASE_URL = window.location.origin;

    let stripe = null;
    let elements = null;
    let paymentElement = null;
    let currentOrder = null;
    let clientSecret = null;
    let currentSubtotal = {{ $cart && $cart->items ? $cart->items->sum(fn($i) => ($i->product->discount_price ?: $i->product->price) * $i->quantity) : 49.99 }};

    if (STRIPE_KEY && !STRIPE_KEY.endsWith('...')) {
        stripe = Stripe(STRIPE_KEY);
        log(`✓ Stripe.js initialized with publishable key: ${STRIPE_KEY.substring(0, 16)}...`);
    } else {
        log(`⚠️ Warning: Stripe Key is placeholder (${STRIPE_KEY}). Please configure real STRIPE_KEY.`);
    }

    function log(msg) {
        const box = document.getElementById('log-box');
        const timestamp = new Date().toLocaleTimeString();
        box.textContent = `[${timestamp}] ${msg}\n` + box.textContent;
    }

    function clearLog() {
        document.getElementById('log-box').textContent = '';
    }

    function onProductSelectChange() {
        const picker = document.getElementById('picker-product');
        const opt = picker.options[picker.selectedIndex];
        const variants = JSON.parse(opt.getAttribute('data-variants') || '[]');
        const variantSelect = document.getElementById('picker-variant');
        
        variantSelect.innerHTML = '<option value="">No Variant / Default</option>';
        if (variants && variants.length > 0) {
            variants.forEach(v => {
                const optEl = document.createElement('option');
                optEl.value = v.id;
                optEl.textContent = `${v.flavor || ''} ${v.size_lbs ? v.size_lbs + ' lbs' : ''} ($${v.price})`;
                variantSelect.appendChild(optEl);
            });
        }
    }

    onProductSelectChange();

    // Dynamically recalculate delivery fee & total when speed or pro plan changes
    function recalculateSummary() {
        const deliveryType = document.getElementById('select-delivery-type').value;
        const isPro = document.getElementById('toggle-pro').checked;
        const baseStoreFee = 4.99;
        let fee = 0.0;
        let feeText = '';

        const labelStatus = document.getElementById('label-pro-status');
        if (isPro) {
            labelStatus.textContent = '👑 Rivo Pro ($0 Delivery)';
            labelStatus.className = 'text-xs font-bold text-emerald-400';
        } else {
            labelStatus.textContent = '👤 Regular User (Standard Fees)';
            labelStatus.className = 'text-xs font-bold text-slate-400';
        }

        if (deliveryType === 'pickup') {
            fee = 0.0;
            feeText = '$0.00 (Store Pickup)';
            document.getElementById('display-delivery-fee').className = 'font-bold text-slate-300';
        } else if (deliveryType === 'standard') {
            if (isPro) {
                fee = 0.0;
                feeText = '$0.00 (Pro Free)';
                document.getElementById('display-delivery-fee').className = 'font-bold text-emerald-400';
            } else {
                fee = baseStoreFee;
                feeText = `$${fee.toFixed(2)}`;
                document.getElementById('display-delivery-fee').className = 'font-bold text-white';
            }
        } else { // express priority
            if (isPro) {
                fee = 0.0;
                feeText = '$0.00 (Pro Free Priority)';
                document.getElementById('display-delivery-fee').className = 'font-bold text-emerald-400';
            } else {
                fee = baseStoreFee + 2.99; // Express priority fee
                feeText = `$${fee.toFixed(2)} (+$2.99 Priority)`;
                document.getElementById('display-delivery-fee').className = 'font-bold text-orange-400';
            }
        }

        const tax = Math.round(currentSubtotal * 0.10 * 100) / 100;
        const total = Math.max(0, Math.round((currentSubtotal + fee + tax) * 100) / 100);

        document.getElementById('display-delivery-fee').textContent = feeText;
        document.getElementById('display-tax').textContent = `$${tax.toFixed(2)}`;
        document.getElementById('display-total').textContent = `$${total.toFixed(2)}`;

        const payBtn = document.getElementById('btn-pay');
        if (payBtn && !payBtn.disabled && currentOrder) {
            payBtn.textContent = `2. Pay $${total.toFixed(2)} with Stripe`;
        }
    }

    recalculateSummary();

    // Dynamically Add Item to Cart
    async function addItemToCart() {
        const productId = document.getElementById('picker-product').value;
        const variantId = document.getElementById('picker-variant').value || null;
        const qty = parseInt(document.getElementById('picker-qty').value) || 1;
        const btn = document.getElementById('btn-add-item');

        btn.disabled = true;
        btn.innerHTML = `<span class="animate-spin inline-block w-3 h-3 border-2 border-white border-t-transparent rounded-full mr-1"></span> Adding...`;

        log(`Adding product #${productId} (qty: ${qty}) to cart via POST /api/v1/marketplace/cart/items...`);

        try {
            const res = await fetch(`${BASE_URL}/api/v1/marketplace/cart/items`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${BEARER_TOKEN}`
                },
                body: JSON.stringify({
                    product_id: productId,
                    variant_id: variantId,
                    quantity: qty
                })
            });

            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Could not add to cart');
            }

            log(`✓ Cart updated! Items: ${data.data.items_count}, Subtotal: $${data.data.summary.subtotal}`);
            currentSubtotal = data.data.summary.subtotal;
            
            // Re-render items preview
            const container = document.getElementById('cart-items-container');
            container.innerHTML = '';
            data.data.items.forEach(item => {
                const el = document.createElement('div');
                el.className = 'py-3 flex items-center justify-between gap-3 first:pt-0 last:pb-0';
                el.innerHTML = `
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-slate-800 flex items-center justify-center text-lg overflow-hidden border border-slate-700">
                            ${item.primary_image ? `<img src="${item.primary_image}" class="w-full h-full object-cover">` : '📦'}
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-white truncate max-w-[160px]">${item.name}</p>
                            <p class="text-xs text-slate-400">Qty: ${item.quantity} × $${item.unit_price}</p>
                        </div>
                    </div>
                    <span class="text-sm font-bold text-white">$${item.item_total}</span>
                `;
                container.appendChild(el);
            });

            document.getElementById('cart-item-count-badge').textContent = `${data.data.items_count} items`;
            recalculateSummary();

            // Reset Checkout button if previously used
            const chkBtn = document.getElementById('btn-initiate-checkout');
            chkBtn.disabled = false;
            chkBtn.className = 'w-full py-3.5 px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm shadow-lg shadow-orange-500/25 transition-all duration-200 flex items-center justify-center gap-2 cursor-pointer';
            chkBtn.innerHTML = `<span>1. Place Order & Create Stripe Intent</span>`;

            btn.disabled = false;
            btn.innerHTML = `<span>✓ Added</span>`;
            setTimeout(() => { btn.innerHTML = `<span>+ Set in Cart</span>`; }, 1500);

        } catch (err) {
            log(`❌ Add to cart error: ${err.message}`);
            alert(err.message);
            btn.disabled = false;
            btn.innerHTML = `<span>+ Set in Cart</span>`;
        }
    }

    // Step 1: Place Order & Get Real PaymentIntent
    async function initiateCheckout() {
        const btn = document.getElementById('btn-initiate-checkout');
        btn.disabled = true;
        btn.innerHTML = `<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span> Placing Order...`;

        const addressId = document.getElementById('select-address').value || null;
        const deliveryType = document.getElementById('select-delivery-type').value || 'express';

        log(`Calling POST ${BASE_URL}/api/v1/marketplace/checkout (delivery_type: ${deliveryType})...`);

        try {
            const payload = {
                payment_method: 'stripe',
                delivery_type: deliveryType,
                notes: 'Live Stripe Test Order via Blade Simulator'
            };
            if (addressId) payload.address_id = addressId;

            const res = await fetch(`${BASE_URL}/api/v1/marketplace/checkout`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${BEARER_TOKEN}`
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (!res.ok) {
                throw new Error(data.message || 'Checkout failed');
            }

            log(`✓ Order placed successfully! Order #${data.data.order.order_number}`);
            currentOrder = data.data.order;
            clientSecret = data.data.payment.client_secret;

            log(`✓ Delivery fee applied: $${currentOrder.summary.delivery_fee}, Total: $${currentOrder.summary.total}`);
            log(`✓ Stripe PaymentIntent ID: ${data.data.payment.transaction_id}`);
            log(`✓ Client Secret received: ${clientSecret.substring(0, 18)}...`);

            document.getElementById('meta-order-number').textContent = currentOrder.order_number;
            document.getElementById('meta-intent-id').textContent = data.data.payment.transaction_id;
            document.getElementById('order-meta-card').classList.remove('hidden');

            document.getElementById('badge-step').textContent = 'Ready for Payment';
            document.getElementById('badge-step').className = 'text-xs px-2.5 py-1 rounded-md bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';

            btn.classList.remove('bg-orange-500', 'hover:bg-orange-600', 'cursor-pointer');
            btn.classList.add('bg-slate-800', 'text-slate-400', 'cursor-not-allowed');
            btn.innerHTML = `✓ Order Created (#${currentOrder.order_number})`;

            // Mount Stripe Payment Element
            mountStripePaymentElement(clientSecret);

        } catch (err) {
            log(`❌ Error: ${err.message}`);
            alert(err.message);
            btn.disabled = false;
            btn.textContent = '1. Place Order & Create Stripe Intent';
        }
    }

    // Step 2: Mount Stripe Elements
    function mountStripePaymentElement(secret) {
        if (!stripe) {
            alert('Stripe JS is not initialized. Please verify your STRIPE_KEY.');
            return;
        }

        elements = stripe.elements({
            clientSecret: secret,
            appearance: {
                theme: 'night',
                variables: {
                    colorPrimary: '#f97316',
                    colorBackground: '#020617',
                    colorText: '#f8fafc',
                    colorDanger: '#fb7185',
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    spacingUnit: '4px',
                    borderRadius: '10px'
                }
            }
        });

        paymentElement = elements.create('payment');
        document.getElementById('payment-element').innerHTML = '';
        paymentElement.mount('#payment-element');

        paymentElement.on('ready', () => {
            log(`✓ Stripe Payment Element ready for card entry.`);
            const payBtn = document.getElementById('btn-pay');
            payBtn.disabled = false;
            payBtn.className = 'w-full py-3.5 px-4 rounded-xl bg-orange-500 hover:bg-orange-600 text-white font-bold text-sm shadow-lg shadow-orange-500/25 transition-all duration-200 cursor-pointer flex items-center justify-center gap-2';
            payBtn.textContent = `2. Pay $${currentOrder.summary.total} with Stripe`;
        });

        paymentElement.on('change', (event) => {
            const errDiv = document.getElementById('payment-errors');
            if (event.error) {
                errDiv.textContent = event.error.message;
                errDiv.classList.remove('hidden');
            } else {
                errDiv.classList.add('hidden');
            }
        });
    }

    // Step 3: Handle Payment Submission & Confirmation
    async function handlePaymentSubmit(e) {
        e.preventDefault();

        if (!stripe || !elements || !currentOrder) return;

        const payBtn = document.getElementById('btn-pay');
        payBtn.disabled = true;
        payBtn.innerHTML = `<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span> Processing Stripe Payment...`;

        log(`Authorizing payment with Stripe...`);

        const { error, paymentIntent } = await stripe.confirmPayment({
            elements,
            redirect: 'if_required'
        });

        if (error) {
            log(`❌ Stripe Payment Error: ${error.message}`);
            const errDiv = document.getElementById('payment-errors');
            errDiv.textContent = error.message;
            errDiv.classList.remove('hidden');
            payBtn.disabled = false;
            payBtn.textContent = `2. Pay $${currentOrder.summary.total} with Stripe`;
            return;
        }

        log(`✓ Stripe Payment confirmed! PaymentIntent status: ${paymentIntent.status}`);

        // Step 4: Confirm Payment on Backend API
        log(`Calling POST ${BASE_URL}/api/v1/marketplace/orders/${currentOrder.id}/confirm-payment...`);
        try {
            const confirmRes = await fetch(`${BASE_URL}/api/v1/marketplace/orders/${currentOrder.id}/confirm-payment`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${BEARER_TOKEN}`
                },
                body: JSON.stringify({
                    payment_intent_id: paymentIntent.id
                })
            });

            const confirmData = await confirmRes.json();
            if (!confirmRes.ok) {
                throw new Error(confirmData.message || 'Server payment confirmation failed');
            }

            log(`✓ Backend payment confirmed! Order payment status: ${confirmData.data.payment.status}`);
            log(`✓ Database Transaction ID: ${confirmData.data.payment.transaction_id}`);

            // Show Success Screen
            document.getElementById('payment-form').classList.add('hidden');
            document.getElementById('success-card').classList.remove('hidden');

        } catch (confirmErr) {
            log(`⚠️ Backend confirm error: ${confirmErr.message}`);
            alert(confirmErr.message);
        }
    }
</script>
</body>
</html>
