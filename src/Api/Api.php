<?php

namespace Akki\SyliusPayumPayzenPlugin\Api;

use DateTime;
use Exception;
use Payum\Core\Exception\LogicException;
use Payum\Core\Exception\RuntimeException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Api
 * @package Akki\SyliusPayumPayzenPlugin\Api
 */
class Api
{
    public const MODE_TEST       = 'TEST';
    public const MODE_PRODUCTION = 'PRODUCTION';

    public const HASH_MODE_SHA1   = 'SHA1';
    public const HASH_MODE_SHA256 = 'SHA256';

    public const ENDPOINT_LYRA  = 'LYRA';
    public const ENDPOINT_PAYZEN  = 'PAYZEN';
    public const ENDPOINT_SCELLIUS  = 'SCELLIUS';
    public const ENDPOINT_SYSTEMPAY = 'SYSTEMPAY';

    /**
     * @var OptionsResolver|null
     */
    private $configResolver;

    /**
     * @var OptionsResolver|null
     */
    private $requestOptionsResolver;

    /**
     * @var array
     */
    private $config;

    /**
     * @param $endpoint
     * @return string
     */
    public static function getUrlFromEndpoint($endpoint): string
    {
        if (self::ENDPOINT_SYSTEMPAY === $endpoint) {
            return 'https://paiement.systempay.fr/vads-payment/';
        }

        if (self::ENDPOINT_SCELLIUS === $endpoint) {
            return 'https://scelliuspaiement.labanquepostale.fr/vads-payment/';
        }

        if (self::ENDPOINT_LYRA === $endpoint) {
            return 'https://secure.lyra.com/vads-payment/';
        }

        return 'https://secure.payzen.eu/vads-payment/';
    }


    /**
     * Configures the api.
     *
     * @param array $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $this
            ->getConfigResolver()
            ->resolve($config);
    }

    /**
     * Returns the next transaction id.
     *
     * @return string
     * @throws Exception
     */
    public function getTransactionId(): string
    {
        $path = $this->getDirectoryPath() . 'transaction_id';

        // Create file if not exists
        if (!file_exists($path)) {
            touch($path);
            chmod($path, 0600);
        }

        $date = (new DateTime())->format('Ymd');
        $fileDate = date('Ymd', filemtime($path));
        $isDailyFirstAccess = ($date != $fileDate);

        // Open file
        $handle = fopen($path, 'rb+');
        if (false === $handle) {
            throw new RuntimeException('Failed to open the transaction ID file.');
        }
        // Lock File
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Failed to lock the transaction ID file.');
        }

        $id = 1;
        // If not daily first access, read and increment the id
        if (!$isDailyFirstAccess) {
            $id = (int)fread($handle, 6);
            $id++;
        }

        // Truncate, write, unlock and close.
        fseek($handle, 0);
        ftruncate($handle, 0);
        fwrite($handle, (string)$id);
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        if ($this->config['debug']) {
            $id += 89000;
        }

        return str_pad($id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Creates the request url.
     *
     * @param array $data
     *
     * @return string
     */
    public function createRequestUrl(array $data): string
    {
        $this->ensureApiIsConfigured();

        $data = $this->createRequestData($data);

        return $this->getUrl() . '?' .
            implode('&', array_map(static function ($key, $value) {
                return $key . '=' . rawurlencode($value);
            }, array_keys($data), $data));
    }

    /**
     * Creates the request data.
     *
     * @param array $data
     *
     * @return array
     */
    public function createRequestData(array $data): array
    {
        $data = $this
            ->getRequestOptionsResolver()
            ->resolve(array_replace($data, [
//                'vads_page_action' => 'PAYMENT',
                'vads_version'     => 'V2',
            ]));

        $data = array_filter($data, static function ($value) {
            return null !== $value;
        });

        $data['vads_site_id'] = $this->config['site_id'];
        $data['vads_ctx_mode'] = $this->config['ctx_mode'];

        $data['signature'] = $this->generateSignature($data);

        return $data;
    }

    /**
     * Checks the response signature.
     *
     * @param array $data
     *
     * @return bool
     */
    public function checkResponseIntegrity(array $data): bool
    {
        if (!isset($data['signature'])) {
            return false;
        }

        return $data['vads_site_id'] === (string)$this->config['site_id']
            && $data['vads_ctx_mode'] === (string)$this->config['ctx_mode']
            && $data['signature'] === $this->generateSignature($data);
    }

    /**
     * Generates the signature.
     *
     * @param array $data
     * @param bool $hashed
     *
     * @return string
     */
    public function generateSignature(array $data, bool $hashed = true): string
    {
        ksort($data);

        $content = "";
        foreach ($data as $key => $value) {
            if (strpos($key, 'vads_') === 0) {
                $content .= $value . '+';
            }
        }

        $content .= $this->config['certificate'];

        if ($hashed) {
            return $this->hash($content);
        }

        return $content;
    }

    /**
     * Returns the directory path and creates it if not exists.
     *
     * @return string
     */
    private function getDirectoryPath(): string
    {
        $path = $this->config['directory'];

        // Create directory if not exists
        if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
            throw new RuntimeException('Failed to create cache directory');
        }

        return $path . DIRECTORY_SEPARATOR;
    }

    /**
     * Check that the API has been configured.
     *
     * @throws LogicException
     */
    private function ensureApiIsConfigured(): void
    {
        if (null === $this->config) {
            throw new LogicException('You must first configure the API.');
        }
    }

    /**
     * Returns the config option resolver.
     *
     * @return OptionsResolver
     */
    private function getConfigResolver(): OptionsResolver
    {
        if (null !== $this->configResolver) {
            return $this->configResolver;
        }

        $resolver = new OptionsResolver();
        $resolver
            ->setRequired([
                'site_id',
                'certificate',
                'ctx_mode',
                'directory',
            ])
            ->setDefaults([
                'endpoint' => null,
                'hash_mode' => self::HASH_MODE_SHA256,
                'debug'    => false,
            ])
            ->setAllowedTypes('site_id', 'string')
            ->setAllowedTypes('certificate', 'string')
            ->setAllowedValues('ctx_mode', $this->getModes())
            ->setAllowedTypes('directory', 'string')
            ->setAllowedValues('endpoint', $this->getEndPoints())
            ->setAllowedValues('hash_mode', $this->getHashModes())
            ->setAllowedTypes('debug', 'bool')
            ->setNormalizer('directory', function (Options $options, $value) {
                return rtrim($value, DIRECTORY_SEPARATOR);
            });

        return $this->configResolver = $resolver;
    }

    /**
     * Returns request options resolver.
     *
     * @return OptionsResolver
     */
    private function getRequestOptionsResolver(): OptionsResolver
    {
        if (null !== $this->requestOptionsResolver) {
            return $this->requestOptionsResolver;
        }

        $resolver = new OptionsResolver();

        $resolver
            ->setDefaults([
                'vads_action_mode'              => 'INTERACTIVE',
                'vads_available_languages'      => null,
                'vads_capture_delay'            => null,
                'vads_card_info'                => null,
                'vads_card_options'             => null,
                'vads_card_number'              => null,
                'vads_contracts'                => function (Options $options) {
                    /* TODO
                    Obligatoire si le numéro de contrat commerçant à utiliser n’est pas celui configuré par défaut
                    sur la plateforme de paiement
                    */
                    return null;
                },
                'vads_contrib'                  => null,
                'vads_cust_address'             => null,
                'vads_cust_cell_phone'          => null,
                'vads_cust_city'                => null,
                'vads_cust_country'             => null,
                'vads_cust_email'               => function (Options $options) {
                    /* TODO
                    Obligatoire si souscription à l'envoi d'e-mail de confirmation de paiement au client
                    */
                    return null;
                },
                'vads_cust_id'                  => null,
                'vads_cust_name'                => null,
                'vads_cust_phone'               => null,
                'vads_cust_title'               => null,
                'vads_cust_zip'                 => null,
                'vads_cvv'                      => null,
                'vads_expiry_month'             => null,
                'vads_expiry_year'              => null,
                'vads_language'                 => null,
                'vads_order_id'                 => null, // [a-zA-Z0-9-]+
                'vads_order_info'               => null,
                'vads_order_info2'              => null,
                'vads_order_info3'              => null,
                'vads_page_action'              => 'PAYMENT',
                'vads_payment_cards'            => null, // Obligatoire si acquisition de la carte par commerçant
                'vads_payment_config'           => null, //'SINGLE',
                'vads_payment_src'              => null, // Obligatoire pour vente à distance
                'vads_redirect_error_message'   => null,
                'vads_redirect_error_timeout'   => null,
                'vads_redirect_success_message' => null,
                'vads_redirect_success_timeout' => null,
                'vads_return_get_params'        => null,
                'vads_return_mode'              => function (Options $options) {
                    /* TODO
                    Obligatoire si souhait du commerçant de recevoir la réponse à la demande sur l’URL internet
                    de retour boutique en formulaire GET ou POST (après clic internaute sur bouton retour
                    boutique).
                    Ce paramétrage n’impacte pas la transmission, ni les paramètres de transfert, de la réponse
                    de serveur à serveur (URL serveur commerçant).
                    */
                    return 'POST';
                },
                'vads_return_post_params'       => null,
                'vads_ship_to_city'             => null,
                'vads_ship_to_country'          => null,
                'vads_ship_to_name'             => null,
                'vads_ship_to_phone_num'        => null,
                'vads_ship_to_state'            => null,
                'vads_ship_to_street'           => null,
                'vads_ship_to_street2'          => null,
                'vads_ship_to_zip'              => null,
                'vads_shop_name'                => null,
                'vads_shop_url'                 => null,
                'vads_theme_config'             => null,
                'vads_threeds_cavv'             => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_cavvAlgorithm'    => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_eci'              => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_enrolled'         => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_mpi'              => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_status'           => null, // Obligatoire si 3DS à la charge du client
                'vads_threeds_xid'              => null, // Obligatoire si 3DS à la charge du client
                'vads_validation_mode'          => null,
                'vads_url_cancel'               => null,
                'vads_url_check'                => null,
                'vads_url_error'                => null,
                'vads_url_referral'             => null,
                'vads_url_refused'              => null,
                'vads_url_success'              => null,
                'vads_url_return'               => null, // Obligatoire si acquisition de la carte par commerçant
                'vads_user_info'                => null,
                'vads_version'                  => 'V2',
                'vads_cust_status'              => null,
                'vads_cust_first_name'          => null,
                'vads_cust_last_name'           => null,
                'vads_cust_legal_name'          => null,
                'vads_cust_address_number'      => null,
                'vads_cust_district'            => null,
                'vads_cust_state'               => null,
                'vads_ship_to_district'         => null,
                'vads_ship_to_first_name'       => null,
                'vads_ship_to_last_name'        => null,
                'vads_ship_to_legal_name'       => null,
                'vads_ship_to_status'           => null,
                'vads_ship_to_street_number'    => null,
                'vads_nb_products'              => null,
                'vads_amount'                   => null,
                'vads_currency'                 => null,
            ])
            ->setRequired([
                //'vads_amount',
                //'vads_currency',
                //'vads_payment_config',
                'vads_trans_date',
                'vads_trans_id',
                'vads_version',
            ])
            ->setAllowedValues('vads_action_mode', ['SILENT', 'INTERACTIVE'])
            ->setAllowedValues('vads_currency', $this->getCurrencyCodes())
            ->setAllowedValues('vads_language', $this->getLanguageCodes())
            ->setAllowedValues('vads_page_action', ['PAYMENT', 'REGISTER','REGISTER_PAY'])
            //->setAllowedValues('vads_payment_cards', $this->getCardsCodes())
            ->setAllowedValues('vads_payment_config', function ($value) {
                if ($value === null) {
                    return true;
                }

                if ($value === 'SINGLE') {
                    return true;
                }

                // Ex: MULTI:first=5000;count=3;period=30
                if (preg_match('~^MULTI:first=\d+;count=\d+;period=\d+$~', $value)) {
                    return true;
                }

                // Ex: MULTI_EXT:20120601=5000;20120701=2500;20120808=2500
                if (preg_match('MULTI_EXT:\d+=\d+;\d+=\d+;\d+=\d+', $value)) {
                    return true;
                }

                return false;
            })
            ->setAllowedValues('vads_payment_src', [null, 'BO', 'MOTO', 'CC', 'OTHER'])
            ->setAllowedValues('vads_return_mode', [null, 'NONE', 'GET', 'POST'])
            ->setAllowedValues('vads_validation_mode', [null, '0', '1'])
            ->setAllowedValues('vads_version', 'V2');

        for ($index = 0; $index <= 100; $index++){
            $resolver->setDefault("vads_product_ext_id$index",null);
            $resolver->setDefault("vads_product_label$index",null);
            $resolver->setDefault("vads_product_amount$index",null);
            $resolver->setDefault("vads_product_type$index",null);
            $resolver->setDefault("vads_product_ref$index",null);
            $resolver->setDefault("vads_product_qty$index",null);
        }


        return $this->requestOptionsResolver = $resolver;
    }

    private function getCurrencyCodes(): array
    {
        return [
            '36', // Dollar australien
            '036', // Dollar australien
            '124', // Dollar canadien
            '156', // Yuan chinois
            '208', // Couronne danoise
            '392', // Yen japonais
            '578', // Couronne norvégienne
            '752', // Couronne suédoise
            '756', // Franc suisse
            '826', // Livre sterling
            '840', // Dollar américain
            '953', // Franc pacifique
            '978', // Euro
            null,
        ];
    }

    private function getLanguageCodes(): array
    {
        return [
            null,
            'de', // Allemand
            'en', // Anglais
            'zh', // Chinois
            'es', // Espagnol
            'fr', // Français
            'it', // Italien
            'jp', // Japonais
            'pt', // Portugais
            'nl', // Néerlandais
        ];
    }

    private function getCardsCodes(): array
    {
        return [
            null,
            'AMEX',         // American Express
            'AURORE-MULTI', // Aurore
            'BUYSTER',      // Buyster
            'CB',           // CB
            'COFINOGA',     // Cofinoga
            'E-CARTEBLEUE', // E-Carte bleue
            'MASTERCARD',   // Eurocard / Mastercard
            'JCB',          // JCB
            'MAESTRO',      // Maestro
            'ONEY',         // Oney
            'ONEY_SANDBOX', // Oney (sandbox)
            'PAYPAL',       // Paypal
            'PAYPAL_SB',    // Paypal (sandbox)
            'PAYSAFECARD',  // Paysafe card
            'VISA',         // Visa
        ];
    }

    private function getModes(): array
    {
        return [self::MODE_TEST, self::MODE_PRODUCTION];
    }

    private function getEndPoints(): array
    {
        return [null, self::ENDPOINT_LYRA, self::ENDPOINT_PAYZEN, self::ENDPOINT_SCELLIUS, self::ENDPOINT_SYSTEMPAY];
    }

    private function getHashModes(): array
    {
        return [self::HASH_MODE_SHA1, self::HASH_MODE_SHA256];
    }

    private function getUrl(): string
    {
        $endpoint = $this->config['endpoint'];
        return self::getUrlFromEndpoint($endpoint);
    }

    private function hash(string $content): string
    {
        if ($this->config['hash_mode'] === self::HASH_MODE_SHA1) {
            return sha1($content);
        }

        return base64_encode(hash_hmac('sha256', $content, $this->config['certificate'], true));
    }
}
