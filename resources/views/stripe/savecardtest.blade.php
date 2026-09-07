<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Card</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .StripeElement {
            box-sizing: border-box;
            height: 40px;
            padding: 10px 12px;
            border: 1px solid #ccd0d5;
            border-radius: 4px;
            background-color: white;
            box-shadow: 0 1px 3px 0 #e6ebf1;
            transition: box-shadow 150ms ease;
        }
        .StripeElement--focus {
            box-shadow: 0 1px 3px 0 #cfd7df;
        }
        .StripeElement--invalid {
            border-color: #fa755a;
        }
        .StripeElement--webkit-autofill {
            background-color: #fefde5 !important;
        }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-6">Add a New Payment Method</h2>

        <form id="payment-form" class="space-y-6">
            <div>
                <label for="card-holder-name" class="block text-sm font-medium text-gray-700">Card Holder Name</label>
                <input id="card-holder-name" type="text" placeholder="Jane Doe" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
            </div>

            <div>
                <label for="card-element" class="block text-sm font-medium text-gray-700">Credit or debit card</label>
                <div id="card-element" class="mt-1">
                    <!-- A Stripe Element will be inserted here. -->
                </div>
                <!-- Used to display form errors. -->
                <div id="card-errors" role="alert" class="text-red-600 text-sm mt-2"></div>
            </div>

            <button id="card-button" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Save Card
            </button>
            <div id="payment-message" class="text-center text-green-600 font-medium hidden"></div>
        </form>
    </div>

<script>
    // --- IMPORTANT ---
    // This bearer token is for demonstration. In a real application, you would get this
    // after the user logs in. You will need to replace this with a real token for a logged-in user.
    const bearerToken = '18|ztARxYsVySZ87cDeG5XFMMoT6XUv2Vu5bBTRkJNW28ebea2a';
    // Replace with your Stripe publishable key
    const stripe = Stripe('pk_test_51QKIOHE3HCOEW1L0h8Bh6ZDC5b0lDD886WQq3FZO82hnybhNWqzHhj3gDeEDcAocAywZf3A9Xh1FmDp6Kpipf1EI00GZOW8k0H');

    // Best Practice: Define your API base URL in one place.
    const apiBaseUrl = 'http://localhost:81';

    let elements;
    let setupIntentClientSecret; // Variable to store the client secret
    const cardHolderName = document.getElementById('card-holder-name');
    const cardButton = document.getElementById('card-button');
    const paymentMessage = document.getElementById('payment-message');

    // --- Step 1: Fetch the client_secret from your backend ---
    async function initialize() {
        try {
            const response = await fetch(`${apiBaseUrl}/api/v1/payment/payment-methods/setup-intent`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${bearerToken}`
                }
            });
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to fetch setup intent.');
            }

            const { data } = await response.json();
            setupIntentClientSecret = data.client_secret; // Store the client secret

            elements = stripe.elements({ clientSecret: setupIntentClientSecret });
            const cardElement = elements.create('card');
            cardElement.mount('#card-element');
        } catch (error) {
            console.error("Failed to initialize payment form:", error);
            document.getElementById('card-errors').textContent = "Could not load payment form. Please refresh the page.";
        }
    }

    initialize();

    // --- Step 2: Handle form submission and create the PaymentMethod ---
    document.getElementById('payment-form').addEventListener('submit', async (e) => {
        e.preventDefault();

        if (!elements || !setupIntentClientSecret) {
            document.getElementById('card-errors').textContent = "Payment form is not ready. Please wait a moment and try again.";
            return;
        }

        cardButton.disabled = true;
        cardButton.textContent = 'Saving...';

        const { setupIntent, error } = await stripe.confirmCardSetup(
            setupIntentClientSecret, // Pass the client secret here
            {
                payment_method: {
                    card: elements.getElement('card'),
                    billing_details: { name: cardHolderName.value }
                }
            }
        );

        if (error) {
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;
            cardButton.disabled = false;
            cardButton.textContent = 'Save Card';
        } else {
            await sendPaymentMethodToServer(setupIntent.payment_method);
        }
    });

    async function sendPaymentMethodToServer(paymentMethodId) {
        try {
            const response = await fetch(`${apiBaseUrl}/api/v1/payment/payment-methods`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${bearerToken}`
                },
                body: JSON.stringify({ payment_method_id: paymentMethodId })
            });

            const result = await response.json();

            if (result.ok) {
                paymentMessage.textContent = "Card saved successfully!";
                paymentMessage.classList.remove('hidden');
                document.getElementById('payment-form').reset();
                setTimeout(() => window.location.reload(), 2000);
            } else {
                throw new Error(result.message || 'Failed to save card.');
            }
        } catch (error) {
            document.getElementById('card-errors').textContent = error.message;
            cardButton.disabled = false;
            cardButton.textContent = 'Save Card';
        }
    }
</script>
</body>
</html>

