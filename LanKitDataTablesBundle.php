<?php

namespace LanKit\DataTablesBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use LanKit\DataTablesBundle\DependencyInjection\LanKitDataTablesExtension;

class LanKitDataTablesBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new LanKitDataTablesExtension();
    }
}
