<?php

namespace Tejadong\DatatablesBundle\Datatables;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;

class DatatableManager
{
    /**
     * @var object The Doctrine service
     */
    protected $doctrine;

    /**
     * @var object The Symfony2 container to grab the Request object from
     */
    protected $container;

    /**
     * @var boolean Whether or not to use the Doctrine Paginator utility by default
     */
    protected $useDoctrinePaginator;

    public function __construct(DoctrineRegistry $doctrine, ContainerInterface $container, $useDoctrinePaginator)
    {
        $this->doctrine = $doctrine;
        $this->container = $container;
        $this->useDoctrinePaginator = $useDoctrinePaginator;
    }

    /**
     * Given an entity class name or possible alias, convert it to the full class name
     *
     * @param string The entity class name or alias
     * @return string The entity class name
     */
    protected function getClassName($className) {
        if (strpos($className, ':') !== false) {
           list($namespaceAlias, $simpleClassName) = explode(':', $className);
           $className = $this->doctrine->getManager()->getConfiguration()
               ->getEntityNamespace($namespaceAlias) . '\\' . $simpleClassName;
        }
        return $className;
    }

    /**
     * @param string An entity class name or alias 
     * @return object Get a DataTable instance for the given entity
     */
    public function getDatatable($class)
    {
        $symfony_version = \Symfony\Component\HttpKernel\Kernel::VERSION;
        $request = $symfony_version >= 3
            ?  $this->container->get('request_stack')->getCurrentRequest()->query->all()
            : $this->container->get('request')->query->all();

        $class = $this->getClassName($class);

        $metadata = $this->doctrine->getManager()->getClassMetadata($class);
        $repository = $this->doctrine->getRepository($class);

        $datatable = new Datatable(
            $request,
            $this->doctrine->getRepository($class),
            $this->doctrine->getManager()->getClassMetadata($class),
            $this->doctrine->getManager(),
            $this->container->get('tejadong_datatables.serializer')
        );
        return $datatable->useDoctrinePaginator($this->useDoctrinePaginator);
    }
}

