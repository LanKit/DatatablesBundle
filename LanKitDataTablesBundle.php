<?php

namespace LanKit\DatatablesBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use LanKit\DatatablesBundle\DependencyInjection\LanKitDatatablesExtension;

class LanKitDatatablesBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new LanKitDatatablesExtension();
    }
}
