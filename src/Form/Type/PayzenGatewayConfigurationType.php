<?php

namespace Akki\SyliusPayumPayzenPlugin\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

final class PayzenGatewayConfigurationType extends AbstractType
{

    private const MODE_PRODUCTION = 'PRODUCTION';
    private const MODE_TEST = 'TEST';
    private const ENDPOINT_PAYZEN = null;
    private const ENDPOINT_SYSTEMPAY = 'SYSTEMPAY';

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('endpoint', ChoiceType::class, [
                'label' => 'akki.payzen.fields.endpoint.label',
                'help' => 'akki.payzen.fields.endpoint.help',
                'choices' => [
                    'akki.payzen.endpoint.payzen' => self::ENDPOINT_PAYZEN,
                    'akki.payzen.endpoint.systempay' => self::ENDPOINT_SYSTEMPAY
                ],
            ])
            ->add('site_id', TextType::class, [
                'label' => 'akki.payzen.fields.site_id.label',
                'help' => 'akki.payzen.fields.site_id.help',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.payzen.site_id.not_blank',
                        'groups' => ['sylius']
                    ]),
                ],
            ])
            ->add('certificate', TextType::class, [
                'label' => 'akki.payzen.fields.certificate.label',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.payzen.certificate.not_blank',
                        'groups' => ['sylius']
                    ]),
                ],
            ])
            ->add('ctx_mode', ChoiceType::class, [
                'label' => 'akki.payzen.fields.ctx_mode.label',
                'choices' => [
                    'akki.payzen.ctx_mode.production' => self::MODE_PRODUCTION,
                    'akki.payzen.ctx_mode.test' => self::MODE_TEST
                ],
            ])
            ->add('directory', TextType::class, [
                'label' => 'akki.payzen.fields.directory.label',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.payzen.directory.not_blank',
                        'groups' => ['sylius']
                    ]),
                ],
            ])
            ->add('payment_cards', TextType::class, [
                'label' => 'akki.payzen.fields.payment_cards.label',
                'constraints' => [
                    new NotBlank([
                        'message' => 'akki.payzen.payment_cards.not_blank',
                        'groups' => ['sylius']
                    ]),
                ],
            ])
            ->add('debug', ChoiceType::class, [
                'label' => 'akki.payzen.fields.debug.label',
                'choices' => [
                    'akki.payzen.debug.no' => false,
                    'akki.payzen.debug.yes' => true
                ],
            ])
        ;
    }
}
