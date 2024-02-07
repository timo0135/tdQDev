<?php

declare(strict_types=1);

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PrivateBin;

/**
 * Configuration.
 *
 * parses configuration file, ensures default values present
 */
class Configuration
{
    /**
     * parsed configuration.
     *
     * @var array
     */
    private $_configuration;

    /**
     * default configuration.
     *
     * @var array
     */
    private static $_defaults = [
        'main' => [
            'name' => 'CharleBin',
            'basepath' => '',
            'discussion' => true,
            'opendiscussion' => false,
            'password' => true,
            'fileupload' => false,
            'burnafterreadingselected' => false,
            'defaultformatter' => 'plaintext',
            'syntaxhighlightingtheme' => '',
            'sizelimit' => 10_485_760,
            'template' => 'bootstrap',
            'info' => 'More information on the <a href=\'https://privatebin.info/\'>project page</a>.',
            'notice' => '',
            'languageselection' => false,
            'languagedefault' => '',
            'urlshortener' => '',
            'qrcode' => true,
            'icon' => 'identicon',
            'cspheader' => 'default-src \'none\'; base-uri \'self\'; form-action \'none\'; manifest-src \'self\'; connect-src * blob:; script-src \'self\' \'unsafe-eval\'; style-src \'self\'; font-src \'self\'; frame-ancestors \'none\'; img-src \'self\' data: blob:; media-src blob:; object-src blob:; sandbox allow-same-origin allow-scripts allow-forms allow-popups allow-modals allow-downloads',
            'zerobincompatibility' => false,
            'httpwarning' => true,
            'compression' => 'zlib',
        ],
        'expire' => [
            'default' => '1week',
        ],
        'expire_options' => [
            '5min' => 300,
            '10min' => 600,
            '1hour' => 3_600,
            '1day' => 86_400,
            '1week' => 604_800,
            '1month' => 2_592_000,
            '1year' => 31_536_000,
            'never' => 0,
        ],
        'formatter_options' => [
            'plaintext' => 'Plain Text',
            'syntaxhighlighting' => 'Source Code',
            'markdown' => 'Markdown',
        ],
        'traffic' => [
            'limit' => 10,
            'header' => '',
            'exempted' => '',
            'creators' => '',
        ],
        'purge' => [
            'limit' => 300,
            'batchsize' => 10,
        ],
        'model' => [
            'class' => 'Filesystem',
        ],
        'model_options' => [
            'dir' => 'data',
        ],
        'yourls' => [
            'signature' => '',
            'apiurl' => '',
        ],
    ];

    /**
     * parse configuration file and ensure default configuration values are present.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        $basePaths = [];
        $config = [];
        $configPath = getenv('CONFIG_PATH');
        if (false !== $configPath && !empty($configPath)) {
            $basePaths[] = $configPath;
        }
        $basePaths[] = PATH.'cfg';
        foreach ($basePaths as $basePath) {
            $configFile = $basePath.\DIRECTORY_SEPARATOR.'conf.php';
            if (is_readable($configFile)) {
                $config = parse_ini_file($configFile, true);
                foreach (['main', 'model', 'model_options'] as $section) {
                    if (!\array_key_exists($section, $config)) {
                        throw new \Exception(I18n::_('PrivateBin requires configuration section [%s] to be present in configuration file.', $section), 2);
                    }
                }

                break;
            }
        }

        $opts = '_options';
        foreach (self::getDefaults() as $section => $values) {
            // fill missing sections with default values
            if (!\array_key_exists($section, $config) || 0 === \count($config[$section])) {
                $this->_configuration[$section] = $values;
                if (\array_key_exists('dir', $this->_configuration[$section])) {
                    $this->_configuration[$section]['dir'] = PATH.$this->_configuration[$section]['dir'];
                }

                continue;
            }
            // provide different defaults for database model
            if (
                'model_options' === $section && \in_array(
                    $this->_configuration['model']['class'],
                    ['Database', 'privatebin_db', 'zerobin_db'],
                    true
                )
            ) {
                $values = [
                    'dsn' => 'sqlite:'.PATH.'data'.\DIRECTORY_SEPARATOR.'db.sq3',
                    'tbl' => null,
                    'usr' => null,
                    'pwd' => null,
                    'opt' => [\PDO::ATTR_PERSISTENT => true],
                ];
            } elseif (
                'model_options' === $section && \in_array(
                    $this->_configuration['model']['class'],
                    ['GoogleCloudStorage'],
                    true
                )
            ) {
                $values = [
                    'bucket' => getenv('PRIVATEBIN_GCS_BUCKET') ? getenv('PRIVATEBIN_GCS_BUCKET') : null,
                    'prefix' => 'pastes',
                    'uniformacl' => false,
                ];
            } elseif (
                'model_options' === $section && \in_array(
                    $this->_configuration['model']['class'],
                    ['S3Storage'],
                    true
                )
            ) {
                $values = [
                    'region' => null,
                    'version' => null,
                    'endpoint' => null,
                    'accesskey' => null,
                    'secretkey' => null,
                    'use_path_style_endpoint' => null,
                    'bucket' => null,
                    'prefix' => '',
                ];
            }

            // "*_options" sections don't require all defaults to be set
            if (
                'model_options' !== $section
                && ($from = \strlen($section) - \strlen($opts)) >= 0
                && false !== strpos($section, $opts, $from)
            ) {
                if (\is_int(current($values))) {
                    $config[$section] = array_map('intval', $config[$section]);
                }
                $this->_configuration[$section] = $config[$section];
            }
            // check for missing keys and set defaults if necessary
            else {
                foreach ($values as $key => $val) {
                    if ('dir' === $key) {
                        $val = PATH.$val;
                    }
                    $result = $val;
                    if (\array_key_exists($key, $config[$section])) {
                        if (null === $val) {
                            $result = $config[$section][$key];
                        } elseif (\is_bool($val)) {
                            $val = strtolower($config[$section][$key]);
                            if (\in_array($val, ['true', 'yes', 'on'], true)) {
                                $result = true;
                            } elseif (\in_array($val, ['false', 'no', 'off'], true)) {
                                $result = false;
                            } else {
                                $result = (bool) $config[$section][$key];
                            }
                        } elseif (\is_int($val)) {
                            $result = (int) $config[$section][$key];
                        } elseif (\is_string($val) && !empty($config[$section][$key])) {
                            $result = (string) $config[$section][$key];
                        }
                    }
                    $this->_configuration[$section][$key] = $result;
                }
            }
        }

        // support for old config file format, before the fork was renamed and PSR-4 introduced
        $this->_configuration['model']['class'] = str_replace(
            'zerobin_',
            'privatebin_',
            $this->_configuration['model']['class']
        );

        $this->_configuration['model']['class'] = str_replace(
            ['privatebin_data', 'privatebin_db'],
            ['Filesystem', 'Database'],
            $this->_configuration['model']['class']
        );

        // ensure a valid expire default key is set
        if (!\array_key_exists($this->_configuration['expire']['default'], $this->_configuration['expire_options'])) {
            $this->_configuration['expire']['default'] = key($this->_configuration['expire_options']);
        }

        // ensure the basepath ends in a slash, if one is set
        if (
            \strlen($this->_configuration['main']['basepath'])
            && 0 !== substr_compare($this->_configuration['main']['basepath'], '/', -1)
        ) {
            $this->_configuration['main']['basepath'] .= '/';
        }
    }

    /**
     * get configuration as array.
     *
     * @return array
     */
    public function get()
    {
        return $this->_configuration;
    }

    /**
     * get default configuration as array.
     *
     * @return array
     */
    public static function getDefaults()
    {
        return self::$_defaults;
    }

    /**
     * get a key from the configuration, typically the main section or all keys.
     *
     * @param string $key
     * @param string $section defaults to main
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getKey($key, $section = 'main')
    {
        $options = $this->getSection($section);
        if (!\array_key_exists($key, $options)) {
            throw new \Exception(I18n::_('Invalid data.')." {$section} / {$key}", 4);
        }

        return $this->_configuration[$section][$key];
    }

    /**
     * get a section from the configuration, must exist.
     *
     * @param string $section
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public function getSection($section)
    {
        if (!\array_key_exists($section, $this->_configuration)) {
            throw new \Exception(I18n::_('%s requires configuration section [%s] to be present in configuration file.', I18n::_($this->getKey('name')), $section), 3);
        }

        return $this->_configuration[$section];
    }
}
