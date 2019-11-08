<?php

namespace Akki\SyliusPayumPayzenPlugin\Action;


use Sylius\Component\Core\Model\PaymentInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetCurrency;
use Sylius\Component\Core\Model\PaymentMethodInterface;

/**
 * Class SyliusConvertAction
 * @package Akki\SyliusPayumPayzenPlugin\Action
 */
class SyliusConvertAction implements ActionInterface, GatewayAwareInterface
{
    use GatewayAwareTrait;

    /**
     * {@inheritDoc}
     *
     * @param Convert $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        /** @var PaymentInterface $payment */
        $payment = $request->getSource();

        $model = ArrayObject::ensureArrayObject($payment->getDetails());

        if (false == $model['vads_amount']) {
            $this->setAmount($model, $payment);
        }

        if (false == $model['vads_order_id']) {
            $this->setReference($model, $payment);
        }

        if (false == $model['vads_order_info']) {
            $this->setComment($model, $payment);
        }

        if (false == $model['vads_cust_email']) {
            $this->setEmail($model, $payment);
        }

        /** @var PaymentMethodInterface $payment_method */
        $payment_method = $payment->getMethod();

        $config = $payment_method->getGatewayConfig()->getConfig();

        $model['vads_payment_cards'] = explode(';', $config['payment_cards']);

        $request->setResult((array)$model);
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return $request instanceof Convert
            && $request->getSource() instanceof PaymentInterface
            && $request->getTo() == 'array';
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setAmount(ArrayObject $model, PaymentInterface $payment): void
    {
        $this->gateway->execute($currency = new GetCurrency($payment->getCurrencyCode()));
        $amount = (string)$payment->getAmount();

        $model['vads_amount'] = $amount;
        $model['vads_currency'] = "978";
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setReference(ArrayObject $model, PaymentInterface $payment): void
    {
        $model['vads_order_id'] = $payment->getId();
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setComment(ArrayObject $model, PaymentInterface $payment): void
    {
        $order = $payment->getOrder();
        $comment = "Order: {$order->getNumber()}";
        if (null !== $customer = $order->getCustomer()) {
            $comment .= ", Customer: {$customer->getId()}";
        }
        $model['vads_order_info'] = $comment;
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setEmail(ArrayObject $model, PaymentInterface $payment): void
    {
        $order = $payment->getOrder();
        if (null !== $customer = $order->getCustomer()) {
            $model['vads_cust_email'] = $customer->getEmail();
        }
    }
}
