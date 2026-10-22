<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\CashOnDeliveryGateway;
use App\Services\Payment\Gateways\StripePaymentGateway;
use Closure;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * The array of resolved payment gateway instances.
     */
    protected array $gateways = [];

    /**
     * The registered custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * The default gateway driver.
     */
    protected string $defaultDriver = 'stripe';

    /**
     * Get a payment gateway instance by name.
     */
    public function gateway(?string $name = null): PaymentGatewayInterface
    {
        $name = strtolower(trim($name ?: $this->defaultDriver));
        $resolvedName = $this->resolveDriverAlias($name);

        if (!isset($this->gateways[$resolvedName])) {
            $this->gateways[$resolvedName] = $this->resolve($resolvedName);
        }

        return $this->gateways[$resolvedName];
    }

    /**
     * Resolve the given gateway.
     */
    protected function resolve(string $name): PaymentGatewayInterface
    {
        if (isset($this->customCreators[$name])) {
            return $this->customCreators[$name]($this);
        }

        $method = 'create' . \Illuminate\Support\Str::studly($name) . 'Driver';
        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        throw new InvalidArgumentException("Payment gateway driver [{$name}] is not supported.");
    }

    /**
     * Map friendly payment method aliases to driver names.
     */
    protected function resolveDriverAlias(string $alias): string
    {
        return match ($alias) {
            'card', 'visa', 'mastercard', 'apple_pay', 'google_pay', 'stripe' => 'stripe',
            'cash', 'cod', 'cash_on_delivery' => 'cash_on_delivery',
            default => $alias,
        };
    }

    /**
     * Create the Stripe gateway driver.
     */
    protected function createStripeDriver(): PaymentGatewayInterface
    {
        return app(StripePaymentGateway::class);
    }

    /**
     * Create the Cash on Delivery gateway driver.
     */
    protected function createCashOnDeliveryDriver(): PaymentGatewayInterface
    {
        return app(CashOnDeliveryGateway::class);
    }

    /**
     * Register a custom driver creator Closure.
     */
    public function extend(string $driver, Closure $callback): self
    {
        $this->customCreators[strtolower(trim($driver))] = $callback;
        return $this;
    }

    /**
     * Set the default payment gateway driver name.
     */
    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;
        return $this;
    }

    /**
     * Dynamically call methods on the default gateway.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->gateway()->{$method}(...$parameters);
    }
}