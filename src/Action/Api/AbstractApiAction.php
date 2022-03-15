<?php

namespace Akki\SyliusPayumPayzenPlugin\Action\Api;

use Akki\SyliusPayumPayzenPlugin\Api\Api;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractApiAction
 * @package Akki\SyliusPayumPayzenPlugin\Action\Api
 */
abstract class AbstractApiAction implements ActionInterface, GatewayAwareInterface, ApiAwareInterface, LoggerAwareInterface
{
    use GatewayAwareTrait;

    /**
     * @var Api|null
     */
    protected ?Api $api;

    /**
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;


    /**
     * @inheritDoc
     */
    public function setApi($api): void
    {
        if (false === $api instanceof Api) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->api = $api;
    }

    /**
     * {@inheritDoc}
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Logs the given message.
     *
     * @param string $message
     */
    protected function log(string $message): void
    {
        if (!$this->logger) {
            return;
        }

        $this->logger->debug($message);
    }

    /**
     * Logs the given message and data.
     *
     * @param string $message
     * @param array  $data
     * @param array  $filterKeys
     */
    protected function logData(string $message, array $data, array $filterKeys = []): void
    {
        if (!$this->logger) {
            return;
        }

        if (!empty($filterKeys)) {
            $data = array_intersect_key($data, array_flip($filterKeys));
        }

        $data = array_map(static function($key, $value) {
            return "$key: $value";
        }, array_keys($data), $data);

        $this->logger->debug($message . ': ' . implode(', ', $data));
    }
}
