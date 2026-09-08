<?php

namespace App\Services\SMS;

/**
 * Contract for PERMANENT number providers with voice + 2-way SMS
 * (Twilio primary, Telnyx backup). Bound as number.twilio / number.telnyx.
 * These are the only lane that provides calls.
 */
interface NumberProviderInterface
{
    /** Search available numbers in a country/region. */
    public function searchNumbers(string $country, array $options = []): array;

    /**
     * Provision a number.
     *
     * @return array{provider_ref: string, number: string, monthly_cost: float}
     */
    public function buyNumber(string $country, array $options = []): array;

    /**
     * Send an outbound SMS/MMS from a provisioned number. A non-null $mediaUrl
     * (a public URL the carrier can fetch) sends it as an MMS attachment.
     */
    public function sendSms(string $from, string $to, string $body, ?string $mediaUrl = null): array;

    /** Wholesale cost (USD) to send ONE outbound SMS segment to a destination. */
    public function outboundSmsCost(string $to): float;

    /** Release a number back to the provider (stops monthly billing). */
    public function releaseNumber(string $providerRef): void;

    /** Monthly wholesale cost (USD) for a number in this country. */
    public function monthlyCost(string $country): float;
}
