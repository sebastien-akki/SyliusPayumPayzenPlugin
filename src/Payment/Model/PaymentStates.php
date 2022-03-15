<?php

namespace Akki\SyliusPayumPayzenPlugin\Payment\Model;

use Akki\SyliusPayumPayzenPlugin\Exception\InvalidArgumentException;

/**
 * Class PaymentStates
 * @package Akki\SyliusPayumPayzenPlugin\Payment\Model
 */
final class PaymentStates
{
    public const STATE_NEW         = 'new';
    public const STATE_PENDING     = 'pending';
    public const STATE_CAPTURED    = 'captured';
    public const STATE_FAILED      = 'failed';
    public const STATE_CANCELED    = 'canceled';
    public const STATE_REFUNDED    = 'refunded';
    public const STATE_AUTHORIZED  = 'authorized';
    public const STATE_SUSPENDED   = 'suspended';
    public const STATE_EXPIRED     = 'expired';
    public const STATE_UNKNOWN     = 'unknown';

    // For sale
    public const STATE_OUTSTANDING = 'outstanding';
    public const STATE_DEPOSIT     = 'deposit';
    public const STATE_COMPLETED   = 'completed';


    /**
     * Returns all the states.
     *
     * @return array
     */
    public static function getStates(): array
    {
        return [
            self::STATE_NEW,
            self::STATE_PENDING,
            self::STATE_CAPTURED,
            self::STATE_FAILED,
            self::STATE_CANCELED,
            self::STATE_REFUNDED,
            self::STATE_AUTHORIZED,
            self::STATE_SUSPENDED,
            self::STATE_EXPIRED,
            self::STATE_UNKNOWN,
            self::STATE_OUTSTANDING,
            self::STATE_DEPOSIT,
            self::STATE_COMPLETED,
        ];
    }

    /**
     * Returns whether the given state is valid.
     *
     * @param string $state
     * @param bool $throwException
     *
     * @return bool
     */
    public static function isValidState(string $state, bool $throwException = true): bool
    {
        if (in_array($state, self::getStates(), true)) {
            return true;
        }

        if ($throwException) {
            throw new InvalidArgumentException("Invalid payment states '$state'.");
        }

        return false;
    }

    /**
     * Returns the notifiable states.
     *
     * @return array
     */
    public static function getNotifiableStates(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_CAPTURED,
            self::STATE_AUTHORIZED,
            self::STATE_FAILED,
            self::STATE_REFUNDED,
        ];
    }

    /**
     * Returns whether the given state is a notifiable state.
     *
     * @param string $state
     *
     * @return bool
     */
    public static function isNotifiableState(string $state): bool
    {
        return in_array($state, self::getNotifiableStates(), true);
    }

    /**
     * Returns the deletable states.
     *
     * @return array
     */
    public static function getDeletableStates(): array
    {
        return [
            self::STATE_NEW,
            self::STATE_CANCELED,
            self::STATE_FAILED,
        ];
    }

    /**
     * Returns whether the given state is a deletable state.
     *
     * @param string|null $state
     *
     * @return bool
     */
    public static function isDeletableState(?string $state): bool
    {
        return is_null($state) || in_array($state, self::getDeletableStates(), true);
    }

    /**
     * Returns the paid states.
     *
     * @return array
     */
    public static function getPaidStates(): array
    {
        return [
            self::STATE_CAPTURED,
            self::STATE_AUTHORIZED,
        ];
    }

    /**
     * Returns whether the given state is a paid state.
     *
     * @param string $state
     *
     * @return bool
     */
    public static function isPaidState(string $state): bool
    {
        return in_array($state, self::getPaidStates(), true);
    }

    /**
     * Returns the canceled states.
     *
     * @return array
     */
    public static function getCanceledStates(): array
    {
        return [
            self::STATE_CANCELED,
            self::STATE_FAILED,
            self::STATE_REFUNDED,
        ];
    }

    /**
     * Returns whether the state has changed
     * from a non paid state to a paid state.
     *
     * @param array $cs The persistence change set
     *
     * @return bool
     */
    public static function hasChangedToPaid(array $cs): bool
    {
        return self::assertValidChangeSet($cs)
            && !self::isPaidState($cs[0])
            && self::isPaidState($cs[1]);
    }

    /**
     * Returns whether the state has changed
     * from a paid state to a non paid state.
     *
     * @param array $cs The persistence change set
     *
     * @return bool
     */
    public static function hasChangedFromPaid(array $cs): bool
    {
        return self::assertValidChangeSet($cs)
            && self::isPaidState($cs[0])
            && !self::isPaidState($cs[1]);
    }

    /**
     * Returns whether the change set is valid.
     *
     * @param array $cs
     *
     * @return bool
     *
     * @throws InvalidArgumentException
     */
    private static function assertValidChangeSet(array $cs): bool
    {
        if (
            array_key_exists(0, $cs) &&
            array_key_exists(1, $cs) &&
            (is_null($cs[0]) || in_array($cs[0], self::getStates(), true)) &&
            (is_null($cs[1]) || in_array($cs[1], self::getStates(), true))
        ) {
            return true;
        }

        throw new InvalidArgumentException("Unexpected order state change set.");
    }
}