<?php

namespace Akki\SyliusPayumPayzenPlugin\Extension;

use Akki\SyliusPayumPayzenPlugin\Action\ConvertPaymentAction;
use Payum\Core\Extension\Context;
use Payum\Core\Extension\ExtensionInterface;
use Payum\Core\Request\Convert;
use Sylius\Component\Core\Model\PaymentInterface;

final class FromForeignGatewayExtension implements ExtensionInterface
{

    public const VADS = "vads_";

    public function onPreExecute(Context $context): void
    {
    }

    public function onExecute(Context $context): void
    {
        if (null !== $context->getException()) {
            return;
        }

        $action = $context->getAction();
        if (false === $action instanceof ConvertPaymentAction) {
            return;
        }

        $request = $context->getRequest();
        if (false === $request instanceof Convert) {
            return;
        }

        $paiement = $request->getSource();
        if (false === $paiement instanceof PaymentInterface) {
            return;
        }

        $details = $paiement->getDetails();
        if (false === is_array($details)) {
            return;
        }

//        $found = false;
//        foreach ($details as $key => $value) {
//            if (0 === strpos($key, self::VADS)) {
//                $found = true;
//            }
//        }

        // Reset the details we are coming from a foreign gateway
//        if (false === $found) {
            $paiement->setDetails([]);
//        }
    }

    public function onPostExecute(Context $context): void
    {
    }
}