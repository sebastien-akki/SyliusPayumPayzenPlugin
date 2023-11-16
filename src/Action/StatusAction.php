<?php

namespace Akki\SyliusPayumPayzenPlugin\Action;

use ArrayAccess;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;

/**
 * Class StatusAction
 * @package Akki\SyliusPayumPayzenPlugin\Action
 */
class StatusAction implements ActionInterface
{
    /**
     * {@inheritdoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if($model['order']) {
            $order = $model['order'];
            $orderStatus = $order['detailedStatus'];
            switch (true) {
                case $orderStatus === "PRE_AUTHORISED" || $orderStatus === "CAPTURED" || $orderStatus === "ACCEPTED" || $orderStatus === "AUTHORISED" :
                    $request->markCaptured();
                    break;
                case $orderStatus === "CANCELLED":
                    $request->markCanceled();
                    break;
                case $orderStatus === "PENDING":
                    $request->markRefunded();
                    break;
                case $orderStatus === 'AUTHORISED_TO_VALIDATE'  || $orderStatus === "WAITING_FOR_PAYMENT"  || $orderStatus === "WAITING_AUTHORISATION" || $orderStatus === "WAITING_AUTHORISATION_TO_VALIDATE":
                    $request->markNew();
                    break;
                case $orderStatus === "CAPTURE_FAILED" || $orderStatus === "EXPIRED" || $orderStatus === "REFUSED" || $orderStatus === "ERROR" :
                    $request->markFailed();
                    break;
                default :
                    $request->markUnknown();
            }

            return;
        }

        $request->markNew();
    }

    /**
     * {@inheritdoc}
     */
    public function supports($request): bool
    {
        return $request instanceof GetStatusInterface
            && $request->getModel() instanceof ArrayAccess;
    }
}
