<?php

namespace App\Services\Banking\Feed;

/**
 * What the app needs from a bank feed provider. One class per provider implements this,
 * so changing provider never changes the screens or the controller.
 */
interface BankFeed
{
    /** Short name stored with each connection, e.g. 'acsiss'. */
    public function name(): string;

    /** False until the provider's keys are in place. */
    public function configured(): bool;

    /** Banks the person can choose from. @return array<int, array{id: string, name: string}> */
    public function institutions(string $search): array;

    /**
     * Begin a connection. 'url' is where the person signs in to their bank (null when the provider
     * needs no redirect). @return array{connection_ref: string, url: ?string}
     */
    public function start(string $institutionId, string $returnUrl): array;

    /**
     * The accounts found once the person has connected. Types are 'everyday', 'savings' or 'credit_card'.
     * @return array{consent_expires: ?string, accounts: array<int, array{ref: string, name: string, mask: ?string, bsb: ?string, type: ?string, balance: ?float}>}
     */
    public function accounts(string $connectionRef): array;

    /**
     * Transactions from $since (Y-m-d). Money in is positive and money out is negative.
     * @return array<int, array{id: string, date: string, description: string, amount: float, balance: ?float}>
     */
    public function transactions(string $connectionRef, string $accountRef, string $since): array;

    /** Stop the provider collecting data for this connection. */
    public function revoke(string $connectionRef): void;
}
