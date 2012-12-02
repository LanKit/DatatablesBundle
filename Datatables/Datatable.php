<?php
/**
 * Recognizes mData sent from DataTables where dotted notations represent a related
 * entity. For example, defining the following in DataTables...
 *
 * "aoColumns": [
 *     { "mData": "id" },
 *     { "mData": "description" },
 *     { "mData": "customer.first_name" },
 *     { "mData": "customer.last_name" }
 * ]
 *
 * ...will result in a a related Entity called customer to be retrieved, and the
 * first and last name will be returned, respectively, from the customer entity.
 *
 * There are no entity depth limitations. You could just as well define nested
 * entity relations, such as...
 *
 *     { "mData": "customer.location.address" }
 *
 * Félix-Antoine Paradis is the author of the original implementation this is
 * built off of, see: https://gist.github.com/1638094 
 */

namespace LanKit\DatatablesBundle\Datatables;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\EntityRepository;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Config\Definition\Exception\Exception;

class Datatable
{
    /**
     * Doctrine innerJoin type
     */
    const JOIN_INNER = 'inner';

    /**
     * Doctrine leftJoin type
     */
    const JOIN_LEFT = 'left';

    /**
     * @var string The default join type to use
     */
    protected $defaultJoinType;

    /**
     * @var object The Doctrine Entity Repository
     */
    protected $repository;

    /**
     * @var object The Doctrine Entity Manager
     */
    protected $em;

    /**
     * @var string  Used as the query builder identifier value
     */
    protected $tableName;

    /**
     * @var array All the request variables as an array
     */
    protected $request;

    /**
     * @var array The parsed request variables for the DataTable
     */
    protected $parameters;

    /**
     * @var array Information relating to the specific columns requested
     */
    protected $associations;

    /**
     * @var array SQL joins used to construct the QueryBuilder query
     */
    protected $assignedJoins = array();

    /**
     * @var array The SQL join type to use for a column
     */
    protected $joinTypes = array();

    /**
     * @var object The QueryBuilder instance
     */
    protected $qb;

    /**
     * @var array The results of the QueryBuilder instance
     */
    protected $fresults;

    /**
     * @var integer The number of records the DataTable can display in the current draw
     */
    protected $offset;

    /**
     * @var string Information for DataTables to use for rendering
     */
    protected $echo;

    /**
     * @var integer The display start point in the current DataTables data set
     */
    protected $amount;

    /**
     * @var string The DataTables global search string
     */
    protected $search;

    /**
     * @var array The primary/unique ID for an Entity. Needed to pull partial objects
     */
    protected $identifiers = array();

    /**
     * @var string The primary/unique ID for the root entity
     */
    protected $rootEntityIdentifier;

    /**
     * @var integer The total amount of results to get from the database
     */
    protected $limit;

    public function __construct(array $request, EntityRepository $repository, ClassMetadata $metadata, EntityManager $em)
    {
        $this->em = $em;
        $this->request = $request;
        $this->repository = $repository;
        $this->metadata = $metadata;
        $this->tableName = Container::camelize($metadata->getTableName());
        $this->defaultJoinType = self::JOIN_INNER;
        $this->setParameters();
        $this->qb = $em->createQueryBuilder();
        $this->echo = $this->request['sEcho'];
        $this->search = $this->request['sSearch'];
        $this->offset = $this->request['iDisplayStart'];
        $this->amount = $this->request['iDisplayLength'];

        $identifiers = $this->metadata->getIdentifierFieldNames();
        $this->rootEntityIdentifier = array_shift($identifiers);
    }

    /**
     * @return array All the paramaters (columns) used for this request
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * Parse and configure parameter/association information for this DataTable request
     */
    public function setParameters()
    {
        if (is_numeric($this->request['iColumns'])) {
            $params = array();
            $associations = array();
            for ($i=0; $i < intval($this->request['iColumns']); $i++) {
                $fields = explode('.', $this->request['mDataProp_' . $i]);
                $params[] = $this->request['mDataProp_' . $i];
                $associations[] = array('containsCollections' => false);

                if (count($fields) > 1)
                    $this->setRelatedEntityColumnInfo($associations[$i], $fields);
                else
                    $this->setSingleFieldColumnInfo($associations[$i], $fields[0]);
            }
            $this->parameters = $params;
            $this->associations = $associations;
        }
    }

    /**
     * Parse a dotted-notation column format from the mData, and sets association
     * information
     *
     * @param array Association information for a column (by reference)
     * @param array The column fields from dotted notation
     */
    protected function setRelatedEntityColumnInfo(array &$association, array $fields) {
        $mdataName = implode('.', $fields);
        $lastField = Container::camelize(array_pop($fields));
        $joinName = $this->tableName;
        $entityName = '';
        $columnName = '';

        // loop through the related entities, checking the associations as we go
        $metadata = $this->metadata;
        while ($field = array_shift($fields)) {
            $columnName .= empty($columnName) ? $field : ".$field";
            $entityName = lcfirst(Container::camelize($field));
            if ($metadata->hasAssociation($entityName)) {
                $joinOn = "$joinName.$entityName";
                if (!empty($fields) && $metadata->isCollectionValuedAssociation($entityName)) {
                    $association['containsCollections'] = true;
                }
                $metadata = $this->em->getClassMetadata(
                    $metadata->getAssociationTargetClass($entityName)
                );
                $joinName .= '_' . $this->getJoinName(
                    $metadata,
                    Container::camelize($metadata->getTableName()),
                    $entityName
                );
                // The join required to get to the entity in question
                if (!isset($this->assignedJoins[$joinName])) {
                    $this->assignedJoins[$joinName]['joinOn'] = $joinOn;
                    $this->assignedJoins[$joinName]['mdataColumn'] = $columnName;
                    $this->identifiers[$joinName] = $metadata->getIdentifierFieldNames();
                }
            }
            else {
                throw new Exception(
                    "Association  '$entityName' not found ($mdataName)",
                    '404'
                );
            }
        }

        // Check the last field on the last related entity of the dotted notation
        if (!$metadata->hasField(lcfirst($lastField))) {
            throw new Exception(
                "Field '$lastField' on association '$entityName' not found ($mdataName)",
                '404'
            );
        }
        $association['entityName'] = $entityName;
        $association['fieldName'] = $lastField;
        $association['joinName'] = $joinName;
        $association['fullName'] = $this->getFullName($association);
    }

    /**
     * Configures association information for a single field request from the main entity
     *
     * @param array  The association information as a reference
     * @param string The field name on the main entity
     */
    protected function setSingleFieldColumnInfo(array &$association, $fieldName) {
        $fieldName = Container::camelize($fieldName);

        if (!$this->metadata->hasField(lcfirst($fieldName))) {
            throw new Exception(
                "Field '$fieldName' not found.)",
                '404'
            );
        }

        $association['fieldName'] = $fieldName;
        $association['entityName'] = $this->tableName;
        $association['fullName'] = $this->tableName . '.' . lcfirst($fieldName);
    }

    /**
     * Based on association information and metadata, construct the join name
     *
     * @param ClassMetadata Doctrine metadata for an association
     * @param string The table name for the join
     * @param string The entity name of the table
     */
    protected function getJoinName(ClassMetadata $metadata, $tableName, $entityName)
    {
        $joinName = $tableName;

        // If it is self-referencing then we must avoid collisions
        if ($metadata->getName() == $this->metadata->getName()) {
            $joinName .= "_$entityName";   
        }

        return $joinName;
    }

    /**
     * Based on association information, construct the full name to refer to in queries
     *
     * @param array Association information for the column
     * @return string The full name to refer to this column as in QueryBuilder statements
     */
    protected function getFullName(array $associationInfo)
    {
        return $associationInfo['joinName'] . '.' . lcfirst($associationInfo['fieldName']);
    }

    /**
     * Set the default join type to use for associations. Defaults to JOIN_INNER
     *
     * @param string The join type to use, should be of either constant: JOIN_INNER, JOIN_LEFT
     */
    public function setDefaultJoinType($joinType)
    {
        if (defined('self::JOIN_' . strtoupper($joinType))) {
            $this->defaultJoinType = constant('self::JOIN_' . strtoupper($joinType));
        }
    }

    /**
     * Set the type of join for a specific column/parameter
     *
     * @param string The column/parameter name
     * @param string The join type to use, should be of either constant: JOIN_INNER, JOIN_LEFT
     */
    public function setJoinType($column, $joinType)
    {
        if (defined('self::JOIN_' . strtoupper($joinType))) {
            $this->joinTypes[$column] = constant('self::JOIN_' . strtoupper($joinType));
        }
    }

    /**
     * Set the scope of the result set
     */
    public function setLimit()
    {
        if (isset($this->offset) && $this->amount != '-1') {
            $this->qb->setFirstResult($this->offset)->setMaxResults($this->amount);
        }
    }

    /**
     * Set any column ordering that has been requested
     */
    public function setOrderBy()
    {
        if (isset($this->request['iSortCol_0'])) {
            for ($i = 0; $i < intval($this->request['iSortingCols']); $i++) {
                if ($this->request['bSortable_'.intval($this->request['iSortCol_'. $i])] == "true") {
                    $this->qb->addOrderBy(
                        $this->associations[$this->request['iSortCol_'.$i]]['fullName'],
                        $this->request['sSortDir_'.$i]
                    );
                }
            }
        }
    }

    /**
     * Configure the WHERE clause for the Doctrine QueryBuilder if any searches are specified
     *
     * @param QueryBuilder The Doctrine QueryBuilder object
     */
    public function setWhere(QueryBuilder $qb)
    {
        // Global filtering
        if (isset($this->search) && !empty($this->search)) {
            $orExpr = $qb->expr()->orX();
            for ($i=0 ; $i < count($this->parameters); $i++) {
                if (isset($this->request['bSearchable_'.$i]) && $this->request['bSearchable_'.$i] == "true") {
                    $qbParam = "sSearch_global_{$this->associations[$i]['entityName']}_{$this->associations[$i]['fieldName']}";
                    $orExpr->add($qb->expr()->like(
                        $this->associations[$i]['fullName'],
                        ":$qbParam"
                    ));
                    $qb->setParameter($qbParam, "%" . $this->request['sSearch'] . "%");
                }
            }
            $qb->where($orExpr);
        }

        // Individual column filtering
        $andExpr = $qb->expr()->andX();
        for ($i=0 ; $i < count($this->parameters); $i++) {
            if (isset($this->request['bSearchable_'.$i]) && $this->request['bSearchable_'.$i] == "true" && !empty($this->request['sSearch_'.$i])) {
                $qbParam = "sSearch_single_{$this->associations[$i]['entityName']}_{$this->associations[$i]['fieldName']}";
                $andExpr->add($qb->expr()->like(
                    $this->associations[$i]['fullName'],
                    ":$qbParam"
                ));
                $qb->setParameter($qbParam, "%" . $this->request['sSearch_'.$i] . "%");
            }
        }
        if ($andExpr->count() > 0) {
            $qb->andWhere($andExpr);
        }
    }

    /**
     * Configure joins for entity associations
     *
     * @param QueryBuilder The Doctrine QueryBuilder object
     */
    public function setAssociations(QueryBuilder $qb)
    {
        foreach ($this->assignedJoins as $joinName => $joinInfo) {
            $joinType = isset($this->joinTypes[$joinInfo['mdataColumn']]) ?
                $this->joinTypes[$joinInfo['mdataColumn']] :  $this->defaultJoinType;
            call_user_func_array(array($qb, $joinType . 'Join'), array(
                $joinInfo['joinOn'],
                $joinName
            ));
        }
    }

    /**
     * Configure the specific columns to select for the query
     *
     * @param QueryBuilder The Doctrine QueryBuilder object
     */
    public function setSelect(QueryBuilder $qb)
    {
        $columns = array();
        $partials = array();

        // Combine all columns to pull
        foreach ($this->associations as $column) {
            $parts = explode('.', $column['fullName']);
            $columns[$parts[0]][] = $parts[1];
        }

        // Partial column results on entities require that we include the identifier as part of the selection
        foreach ($this->identifiers as $joinName => $identifiers) {
            if (!in_array($identifiers[0], $columns[$joinName])) {
                array_unshift($columns[$joinName], $identifiers[0]);
            }
        }

        // Make sure to include the identifier for the main entity
        if (!in_array($this->rootEntityIdentifier, $columns[$this->tableName])) {
            array_unshift($columns[$this->tableName], $this->rootEntityIdentifier);
        }

        foreach ($columns as $columnName => $fields) {
            $partials[] = 'partial ' . $columnName . '.{' . implode(',', $fields) . '}';
        }

        $qb->select(implode(',', $partials));
        $qb->from($this->metadata->getName(), $this->tableName);
    }

    /**
     * Method to execute after constructing this object. Configures the object before
     * executing getSearchResults()
     */
    public function makeSearch() 
    {
        $this->setSelect($this->qb);
        $this->setAssociations($this->qb);
        $this->setWhere($this->qb);
        $this->setOrderBy();
        $this->setLimit();
    }

    /**
     * Check if an array is associative or not.
     *
     * @link http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-numeric
     * @param array An arrray to check
     * @return bool true if associative
     */
    protected function isAssocArray(array $array) {
        return (bool)count(array_filter(array_keys($array), 'is_string'));
    }

    /**
     * Execute the QueryBuilder object, parse the results, and send back the
     * DataTable data
     *
     * @return array Data results for DataTables.js
     */
    public function getSearchResults()
    {
        $this->fresults = $this->qb->getQuery()->getResult(Query::HYDRATE_ARRAY);
        $output = array("aaData" => array());

        foreach ($this->fresults as $item) {
            // Go through each requested column, transforming the array as needed for DataTables
            for ($i = 0 ; $i < count($this->parameters); $i++) {
                // Results are already correctly formatted if this is the case...
                if (!$this->associations[$i]['containsCollections']) {
                    continue;
                }

                $rowRef = &$item;
                $fields = explode('.', $this->parameters[$i]);

                // Check for collection based entities and format the array as needed
                while ($field = array_shift($fields)) {
                    $rowRef = &$rowRef[$field];
                    // We ran into a collection based entity. Combine, merge, and continue on...
                    if (!empty($fields) && !$this->isAssocArray($rowRef)) {
                        $children = array();
                        while ($childItem = array_shift($rowRef)) {
                            $children = array_merge_recursive($children, $childItem);
                        }
                        $rowRef = $children;
                    }
                }
            }
            $output['aaData'][] = $item;
        }

        $outputHeader = array(
            "sEcho" => (int) $this->echo,
            "iTotalRecords" => $this->getCountAllResults(),
            "iTotalDisplayRecords" => (string) $this->getCountFilteredResults()
        );

        return array_merge($outputHeader, $output);
    }

    /**
     * @return int Total query results before searches/filtering
     */
    public function getCountAllResults()
    {
        return (int) $this->repository->createQueryBuilder($this->tableName)
            ->select('count(' . $this->tableName . '.' . $this->rootEntityIdentifier . ')')
            ->getQuery()
            ->getSingleScalarResult();
    }
    
    /**
     * @return int Total query results after searches/filtering
     */
    public function getCountFilteredResults()
    {
        $qb = $this->repository->createQueryBuilder($this->tableName);
        $qb->select('count(' . $this->tableName . '.' . $this->rootEntityIdentifier . ')');
        $this->setAssociations($qb);
        $this->setWhere($qb);
        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function getOffset()
    {
        return $this->offset;
    }

    public function getEcho()
    {
        return $this->echo;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    public function getSearch()
    {
        return  "%" . $this->search . "%";
    }

    public function getQueryBuilder()
    {
        return  $this->qb;
    }
}
