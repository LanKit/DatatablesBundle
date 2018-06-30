<?php

namespace Tejadong\DatatablesBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tejadong\DatatablesBundle\DependencyInjection\TejadongDatatablesExtension;

class TejadongDatatablesBundle extends Bundle
{
    public function getContainerExtension()
    {
        return new TejadongDatatablesExtension();
    }
}
