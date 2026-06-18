<?php
use Illuminate\Support\Facades\Redis;

test('valid webhook signature is accepted', function () {
    $body   = json_encode(['event' => 'test']);
    $secret = config('services.webhook_secret');
    $sig    = hash_hmac('sha256', $body, $secret);

    $response = $this->postJson('/webhooks/instagram', ['event' => 'test'], [
        'X-Webhook-Signature' => $sig,
        'X-Webhook-Nonce'     => 'unique-nonce-1',
    ]);

    $response->assertStatus(200);
});

test('invalid signature is rejected', function () {
    $response = $this->postJson('/webhooks/instagram', ['event' => 'test'], [
        'X-Webhook-Signature' => 'bad-signature',
        'X-Webhook-Nonce'     => 'unique-nonce-2',
    ]);

    $response->assertStatus(401);
});

test('replayed request is rejected', function () {
    $body   = json_encode(['event' => 'test']);
    $secret = config('services.webhook_secret');
    $sig    = hash_hmac('sha256', $body, $secret);
    $nonce  = 'replay-nonce-xyz';

    Redis::setex("webhook:nonce:{$nonce}", 86400, 1); // pre-set as seen

    $response = $this->postJson('/webhooks/instagram', ['event' => 'test'], [
        'X-Webhook-Signature' => $sig,
        'X-Webhook-Nonce'     => $nonce,
    ]);

    $response->assertStatus(409);
});