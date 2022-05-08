<?php

declare(strict_types=1);

namespace MedleyBox\Plugins;

use Symfony\Component\HttpKernel\Bundle\{Bundle, BundleInterface};

/**
 * Base class for Symfony bundles written for Medleybox
 */
class AbstractPlugin extends Bundle implements BundleInterface
{
}
