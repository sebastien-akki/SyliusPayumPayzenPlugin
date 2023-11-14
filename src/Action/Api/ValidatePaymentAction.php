<?php
declare(strict_types=1);

namespace Akki\SyliusPayumPayzenPlugin\Action\Api;

use Akki\SyliusPayumPayzenPlugin\Request\Api\ValidatePayment;
use Akki\SyliusPayumPayzenPlugin\Request\GetHumanStatus;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;

final class ValidatePaymentAction extends AbstractApiAction
{
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $model->validateNotEmpty(['uuid']);

        $this->api->validatePayment($model['uuid']);

        $model['order'] = $this->api->readOrder($model['uuid'])->__toString();

        $this->gateway->execute(new GetHumanStatus($model));
    }

    public function supports($request): bool
    {
        return $request instanceof ValidatePayment;
    }

}