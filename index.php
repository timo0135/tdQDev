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

use PrivateBin\Controller;

/**
 * PrivateBin.
 *
 * a zero-knowledge paste bin
 *
 * @see      https://github.com/PrivateBin/PrivateBin
 *
 * @copyright 2012 Sébastien SAUVAGE (sebsauvage.net)
 * @license   https://www.opensource.org/licenses/zlib-license.php The zlib/libpng License
 *
 * @version   1.5.1
 */

// change this, if your php files and data is outside of your webservers document root
define('PATH', '');

define('PUBLIC_PATH', __DIR__);

require PATH.'vendor'.DIRECTORY_SEPARATOR.'autoload.php';
new Controller();
