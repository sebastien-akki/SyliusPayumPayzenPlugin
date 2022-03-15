<?php

namespace Akki\SyliusPayumPayzenPlugin\Exception;

/**
 * Interface ResourceExceptionInterface
 * @package Akki\SyliusPayumPayzenPlugin\Exception
 */
interface ResourceExceptionInterface
{
    /**
     * Returns the message.
     *
     * @return string
     */
    public function getMessage(): string;
}