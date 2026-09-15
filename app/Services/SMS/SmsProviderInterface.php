<?php

namespace App\Services\SMS;

/**
 * Contract for OTP / rental number providers (Getatext, 5sim, HeroSMS/VirtSMS,
 * SMSPool, OnlineSIM). Each provider normalises its own responses to the
 * shapes below — critically, `check()`'s `status` MUST use the shared
 * OtpStatus vocabulary (PollSmsOtpJob only ever matches OtpStatus::RECEIVED)
 * — so the SmsNumberRouter and OTP jobs never depend on a concrete provider.
 * Bound in the container as number.getatext / number.fivesim / number.herosms /
 * number.virtsms / number.smspool / number.onlinesim.
 */
interface SmsProviderInterface
{
    /**
     * Live wholesale cost (USD) for a country+service. Never hard-coded.
     * Throws OutOfStockException when there is no stock.
     */
    public function priceFor(string $country, string $service, ?string $operator = null): float;

    /**
     * Buy a one-time OTP (activation) number.
     *
     * @return array{provider_ref: string, number: string, cost: float, status: string}
     */
    public function buyOtp(string $country, string $service, array $options = []): array;

    /**
     * Buy a rental (hosting / long-rental) number. A rental receives UNLIMITED
     * SMS for its period — from the chosen service, or (when $service is
     * NumberRequest::SERVICE_ANY and the provider supportsFullRent()) from ANY
     * service. So a user can subscribe to one number and use it broadly.
     *
     * @return array{provider_ref: string, number: string, cost: float, status: string}
     */
    public function buyRental(string $country, string $service, array $options = []): array;

    /**
     * Whether this provider offers "full rent" — a rented number that receives
     * SMS from ANY service, not just one (e.g. SMS-Activate's `service=full`).
     * 5sim hosting is per-service, so it returns false.
     */
    public function supportsFullRent(): bool;

    /**
     * Poll an order for its code.
     *
     * @return array{status: string, code: ?string}
     */
    public function check(string $providerRef): array;

    /** Mark an order finished/completed (protects 5sim rating). */
    public function finish(string $providerRef): void;

    /** Cancel an order (before any SMS) so cost is credited back. */
    public function cancel(string $providerRef): void;

    /** NaaraSim's prepaid balance with this provider (USD). */
    public function balance(): float;
}
