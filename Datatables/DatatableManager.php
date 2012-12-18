<?php

namespace LanKit\DatatablesBundle\Datatables;

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

    public function __construct(DoctrineRegistry $doctrine, ContainerInterface $container)
    {
        $this->doctrine = $doctrine;
        $this->container = $container;
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
           $className = $this->doctrine->getEntityManager()->getConfiguration()
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
        $class = $this->getClassName($class);

        $metadata = $this->doctrine->getEntityManager()->getClassMetadata($class);
        $repository = $this->doctrine->getRepository($class);

        return new Datatable(
            $this->container->get('request')->query->all(),
            $this->doctrine->getRepository($class),
            $this->doctrine->getEntityManager()->getClassMetadata($class),
            $this->doctrine->getEntityManager()
        );
    }
}

