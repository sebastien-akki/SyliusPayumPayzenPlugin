<?php

namespace Akki\SyliusPayumPayzenPlugin\Gateway;

use Akki\SyliusPayumPayzenPlugin\Action\Api\ApiRequestAction;
use Akki\SyliusPayumPayzenPlugin\Action\Api\ApiResponseAction;
use Akki\SyliusPayumPayzenPlugin\Action\Api\ValidatePaymentAction;
use Akki\SyliusPayumPayzenPlugin\Action\CancelAction;
use Akki\SyliusPayumPayzenPlugin\Action\CaptureAction;
use Akki\SyliusPayumPayzenPlugin\Action\ConvertPaymentAction;
use Akki\SyliusPayumPayzenPlugin\Action\NotifyAction;
use Akki\SyliusPayumPayzenPlugin\Action\RefundAction;
use Akki\SyliusPayumPayzenPlugin\Action\StatusAction;
use Akki\SyliusPayumPayzenPlugin\Action\SyncAction;
use Akki\SyliusPayumPayzenPlugin\Api\Api;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactory;
use Payum\Core\GatewayFactoryInterface;

/**
 * Class PayzenGatewayFactory
 * @package Akki\SyliusPayumPayzenPlugin
 */
class PayzenGatewayFactory extends GatewayFactory
{
    /**
     * Builds a new factory.
     *
     * @param array $defaultConfig
     * @param GatewayFactoryInterface|null $coreGatewayFactory
     *
     * @return PayzenGatewayFactory
     */
    public static function build(array $defaultConfig, GatewayFactoryInterface $coreGatewayFactory = null): PayzenGatewayFactory
    {
        return new static($defaultConfig, $coreGatewayFactory);
    }

    /**
     * @inheritDoc
     */
    protected function populateConfig(ArrayObject $config): void
    {
        $config->defaults([
            'payum.factory_name'  => 'payzen',
            'payum.factory_title' => 'Payzen',

            'payum.action.capture'         => new CaptureAction(),
            'payum.action.convert_payment' => new ConvertPaymentAction(),
            'payum.action.sync'            => new SyncAction(),
            'payum.action.cancel'          => new CancelAction(),
            'payum.action.refund'          => new RefundAction(),
            'payum.action.status'          => new StatusAction(),
            'payum.action.notify'          => new NotifyAction(),
            'payum.action.api.request'     => new ApiRequestAction(),
            'payum.action.api.response'    => new ApiResponseAction(),
            'payum.action.api.validate_payment' => new ValidatePaymentAction()
        ]);

        if (false == $config['payum.api']) {
            $config['payum.default_options'] = [
                'site_id'     => null,
                'certificate' => null,
                'ctx_mode'    => null,
                'directory'   => null,
                'endpoint'    => null,
                'hash_mode'   => Api::HASH_MODE_SHA256,
                'debug'       => false,
                'password'    => null,
                'sha256key'   => null,
                'public_key'  => null,
                'ipn'         => null,
            ];

            $config->defaults($config['payum.default_options']);

            $config['payum.required_options'] = ['site_id', 'certificate', 'ctx_mode', 'directory', 'password', 'sha256key', 'public_key', 'ipn'];

            $config['payum.api'] = static function (ArrayObject $config) {
                $config->validateNotEmpty($config['payum.required_options']);

                $payzenConfig = [
                    'endpoint'    => $config['endpoint'],
                    'site_id'     => $config['site_id'],
                    'certificate' => $config['certificate'],
                    'ctx_mode'    => $config['ctx_mode'],
                    'directory'   => $config['directory'],
                    'hash_mode'   => $config['hash_mode'],
                    'debug'       => $config['debug'],
                    'password'    => $config['password'],
                    'sha256key'   => $config['sha256key'],
                    'public_key'  => $config['public_key'],
                    'ipn'         => $config['ipn'],
                ];

                $api = new Api();
                $api->setConfig($payzenConfig);

                return $api;
            };
        }
    }
}
