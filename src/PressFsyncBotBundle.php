<?php

declare(strict_types=1);

/**
 * Press Fsync Bot Bundle for Contao Open Source CMS
 * Copyright (c) 2023 Web ex Machina
 *
 * @category ContaoBundle
 * @package  Web-Ex-Machina/press-fsync-bot-bundle
 * @author   Web ex Machina <contact@webexmachina.fr>
 * @link     https://github.com/Web-Ex-Machina/press-fsync-bot-bundle/
 */

namespace WEM\PressFsyncBotBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Configures the bundle.
 *
 * @author Web ex Machina <https://www.webexmachina.fr>
 */
class PressFsyncBotBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
