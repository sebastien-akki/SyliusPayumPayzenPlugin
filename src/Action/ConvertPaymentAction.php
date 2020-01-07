<?php

namespace Akki\SyliusPayumPayzenPlugin\Action;


use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\PaymentInterface;
use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\GatewayAwareInterface;
use Payum\Core\GatewayAwareTrait;
use Payum\Core\Request\Convert;
use Payum\Core\Request\GetCurrency;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;

/**
 * Class ConvertPaymentAction
 * @package Akki\SyliusPayumPayzenPlugin\Action
 */
class ConvertPaymentAction implements ActionInterface, GatewayAwareInterface
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

        $this->setAmount($model, $payment);
        $this->setDonneesCommande($model, $payment);
        $this->setDonneesAcheteur($model, $payment);

        /** @var PaymentMethodInterface $payment_method */
        $payment_method = $payment->getMethod();

        $config = $payment_method->getGatewayConfig()->getConfig();

        $model['vads_payment_cards'] = $config['payment_cards'];

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
    protected function setAmount(ArrayObject $model, PaymentInterface $payment)
    {
        $this->gateway->execute($currency = new GetCurrency($payment->getCurrencyCode()));

        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $hasOffresADL = $order->hasOffresADL();
        $fraisDePortKMStandard = $order->getFraisPortKMStandard()*100;
        $fraisPortIdefix = $order->getFraisPortIdefix();
        $amount = $order->montantProductsNotOffresADL() + $fraisDePortKMStandard + $fraisPortIdefix;

        if ($amount > 0){
            $model['vads_page_action'] = $hasOffresADL ? 'REGISTER_PAY' : 'PAYMENT';
            $model['vads_amount'] = (string)$amount;
            $model['vads_currency'] = $currency->numeric;
            $model['vads_payment_config'] = 'SINGLE';
        }else {
            $model['vads_page_action'] = 'REGISTER';
        }
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setDonneesCommande(ArrayObject $model, PaymentInterface $payment)
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        $model['vads_order_id'] = $order->getId();

        $comment = "Order: {$order->getNumber()}";
        if (null !== $customer = $order->getCustomer()) {
            $comment .= ", Customer: {$customer->getId()}";
        }
        $model['vads_order_info'] = $comment;

        $model['vads_nb_products'] = $order->getTotalQuantity();

        /** @var OrderItemInterface $orderItem */
        foreach (array_values($order->getItems()->toArray()) as $index => $orderItem){
            /** @var Product $product */
            $product = $orderItem->getProduct();
            $model["vads_product_ext_id{$index}"] = $product->getSlug();
            $model["vads_product_label{$index}"] = $this->specialChars($product->getName());
            $model["vads_product_amount{$index}"] = $product->getPrixKMBrut();
            $model["vads_product_type{$index}"] = 'ENTERTAINMENT';
            $model["vads_product_ref{$index}"] = $product->getCode();
            $model["vads_product_qty{$index}"] = $orderItem->getQuantity();
        }
    }

    /**
     * @param ArrayObject $model
     * @param PaymentInterface $payment
     */
    protected function setDonneesAcheteur(ArrayObject $model, PaymentInterface $payment)
    {
        $order = $payment->getOrder();
        /** @var Customer $customer */
        if (null !== $customer = $order->getCustomer()) {
            $defaultAddress = $customer->getDefaultAddress();
            $model['vads_cust_email'] = $customer->getEmail();
            $model['vads_cust_id'] = $customer->getId();
            $model['vads_cust_title'] = $customer->getGender() === 'm' ? 'Mr' : 'Mme';
            $model['vads_cust_status'] = $defaultAddress !== null && !empty($defaultAddress->getCompany()) ? 'COMPANY' : 'PRIVATE';
            $model['vads_cust_first_name'] = $this->specialChars($customer->getFirstName());
            $model['vads_cust_last_name'] = $this->specialChars($customer->getLastName());
            $model['vads_cust_legal_name'] = $defaultAddress !== null && !empty($defaultAddress->getCompany()) ? $this->specialChars($defaultAddress->getCompany()) : '';
            $model['vads_cust_cell_phone'] = '';
            $model['vads_cust_phone'] = $customer->getPhoneNumber();
            $model['vads_cust_address_number'] = '';
            $model['vads_cust_address'] = $defaultAddress !== null && !empty($defaultAddress->getStreet()) ? $this->specialChars($defaultAddress->getStreet()) : '';
            $model['vads_cust_district'] = '';
            $model['vads_cust_zip'] = $defaultAddress !== null && !empty($defaultAddress->getPostcode()) ? $defaultAddress->getPostcode() : '';
            $model['vads_cust_city'] = $defaultAddress !== null && !empty($defaultAddress->getCity()) ? $this->specialChars($defaultAddress->getCity()) : '';
            $model['vads_cust_state'] = '';
            $model['vads_cust_country'] = $defaultAddress !== null && !empty($defaultAddress->getCountryCode()) ? $defaultAddress->getCountryCode() : '';

            $billingAddress = $order->getBillingAddress();
            $model['vads_ship_to_city'] = $billingAddress !== null && !empty($billingAddress->getCity()) ? $this->specialChars($billingAddress->getCity()) : '';
            $model['vads_ship_to_country'] = $billingAddress !== null && !empty($billingAddress->getCountryCode()) ? $billingAddress->getCountryCode() : '';
            $model['vads_ship_to_district'] = '';
            $model['vads_ship_to_first_name'] = $billingAddress !== null && !empty($billingAddress->getFirstName()) ? $this->specialChars($billingAddress->getFirstName()) : '';
            $model['vads_ship_to_last_name'] = $billingAddress !== null && !empty($billingAddress->getLastName()) ? $this->specialChars($billingAddress->getLastName()) : '';
            $model['vads_ship_to_legal_name'] = $billingAddress !== null && !empty($billingAddress->getCompany()) ? $this->specialChars($billingAddress->getCompany()) : '';
            $model['vads_ship_to_phone_num'] = $billingAddress !== null && !empty($billingAddress->getPhoneNumber()) ? $billingAddress->getPhoneNumber() : '';
            $model['vads_ship_to_state'] = '';
            $model['vads_ship_to_status'] = $billingAddress !== null && !empty($billingAddress->getCompany()) ? 'COMPANY' : 'PRIVATE';
            $model['vads_ship_to_street_number'] = '';
            $model['vads_ship_to_street'] = $billingAddress !== null && !empty($billingAddress->getStreet()) ? $this->specialChars($billingAddress->getStreet()) : '';
            $model['vads_ship_to_street2'] = $billingAddress !== null && !empty($billingAddress->getStreetComplement()) ? $this->specialChars($billingAddress->getStreetComplement()) : '';
            $model['vads_ship_to_zip'] = $billingAddress !== null && !empty($billingAddress->getPostcode()) ? $billingAddress->getPostcode() : '';
        }
    }

    function specialChars($str)
    {
        $translit = array(
            'Á' => 'A', 'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ã' => 'A', 'Å' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Í' => 'I', 'Ï' => 'I', 'Î' => 'I', 'Ì' => 'I', 'Ñ' => 'N', 'Ó' => 'O', 'Ò' => 'O', 'Ô' => 'O', 'Ö' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y',
            "\xC2\x82" => "'", // U+0082⇒U+201A single low-9 quotation mark
            "\xC2\x84" => '"', // U+0084⇒U+201E double low-9 quotation mark
            "\xC2\x8B" => "'", // U+008B⇒U+2039 single left-pointing angle quotation mark
            "\xC2\x91" => "'", // U+0091⇒U+2018 left single quotation mark
            "\xC2\x92" => "'", // U+0092⇒U+2019 right single quotation mark
            "\xC2\x93" => '"', // U+0093⇒U+201C left double quotation mark
            "\xC2\x94" => '"', // U+0094⇒U+201D right double quotation mark
            "\xC2\x9B" => "'", // U+009B⇒U+203A single right-pointing angle quotation mark
            // Regular Unicode     // U+0022 quotation mark (")
            // U+0027 apostrophe     (')
            "\xC2\xAB"     => '"', // U+00AB left-pointing double angle quotation mark
            "\xC2\xBB"     => '"', // U+00BB right-pointing double angle quotation mark
            "\xE2\x80\x98" => "'", // U+2018 left single quotation mark
            "\xE2\x80\x99" => "'", // U+2019 right single quotation mark
            "\xE2\x80\x9A" => "'", // U+201A single low-9 quotation mark
            "\xE2\x80\x9B" => "'", // U+201B single high-reversed-9 quotation mark
            "\xE2\x80\x9C" => '"', // U+201C left double quotation mark
            "\xE2\x80\x9D" => '"', // U+201D right double quotation mark
            "\xE2\x80\x9E" => '"', // U+201E double low-9 quotation mark
            "\xE2\x80\x9F" => '"', // U+201F double high-reversed-9 quotation mark
            "\xE2\x80\xB9" => "'", // U+2039 single left-pointing angle quotation mark
            "\xE2\x80\xBA" => "'",
        );
        $strModified = strtr($str, $translit);
        return preg_replace("/[^A-Za-z0-9 ]/", ' ', $strModified);;
    }
}
