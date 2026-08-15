<?php

namespace App\Services;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class StripeWebhookVerifier
{
    public function verify(Request $request): array
    {
        $secret = config('hopeworks.stripe.webhook_secret');

        if (empty($secret)) {
            throw new AccessDeniedHttpException('Stripe webhook secret is not configured.');
        }

        $signature = $request->header('Stripe-Signature');

        if (! is_string($signature) || $signature === '') {
            throw new AccessDeniedHttpException('Missing Stripe signature.');
        }

        $payload = $request->getContent();
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $element) {
            [$key, $value] = array_pad(explode('=', trim($element), 2), 2, null);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== null) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new AccessDeniedHttpException('Invalid Stripe signature header.');
        }

        if (abs(time() - (int) $timestamp) > 300) {
            throw new AccessDeniedHttpException('Stripe signature timestamp outside tolerance.');
        }

        $signedPayload = $timestamp.'.'.$payload;
        $expected = hash_hmac('sha256', $signedPayload, $secret);
        $valid = false;

        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                $valid = true;
                break;
            }
        }

        if (! $valid) {
            throw new AccessDeniedHttpException('Invalid Stripe webhook signature.');
        }

        $decoded = json_decode($payload, true);

        if (! is_array($decoded)) {
            throw new AccessDeniedHttpException('Invalid webhook payload.');
        }

        return $decoded;
    }
}
