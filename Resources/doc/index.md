Getting Started With TejadongDatatablesBundle
===========================================
*¡¡Attention!!, This bundle is a copy adapted to add compatibility with Symfony 3, the real repository belongs to [LanKit/DatatablesBundle](https://github.com/LanKit/DatatablesBundle).*

* [Prerequisites](#prerequisites)
* [Installation](#installation)
* [Usage](#usage)
* [Entity Associations and Join Types](#entity-associations-and-join-types)
* [Search Result Response Types](#search-result-response-types)
* [Pre-Filtering Search Results](#pre-filtering-search-results)
* [DateTime Formatting](#datetime-formatting)
* [DT_RowId and DT_RowClass](#dt_rowid-and-dt_rowclass)
* [The Doctrine Paginator and MS SQL](#the-doctrine-paginator-and-ms-sql)

This bundle provides an intuitive way to process DataTables.js requests by
using mData. The mData from the DataTables request corresponds to fields and
associations on a specific entity. You can access related entities off the 
base entity by using dottted notation.

For example, a mData structure to query an entity may look like the following:

``` js
    "aoColumns": [
        { "mData": "id" },
        { "mData": "description" },
        { "mData": "customer.firstName" },
        { "mData": "customer.lastName" },
        { "mData": "customer.location.address" }
    ]
```

`id` and `description` are fields on the entity, and `customer` is an associated
entity with another associated entity called `location`. There are no depth
limitations with entity associations.

If an association is a collection (ie. many associated records), then an array 
of values are returned for the final field in question.

## Prerequisites

This version of the bundle requires Symfony 2.1+. This bundle also needs the JMSSerializerBundle
for JSON encoding. For information on installing the JMSSerializerBundle please look [here](http://jmsyst.com/bundles/JMSSerializerBundle/master/installation).

If you do not have that bundle registered you will need to supply a different
serializer service in your config file...

```yml
// app/config.yml

tejadong_datatables:
    services:
        serializer: some_other_serializer # Defaults to jms_serializer.serializer
```

## Installation

### Step 1: Download TejadongDatatablesBundle using composer


Use composer to download the bundle using the following command:

``` bash
$ php composer require tejadong/datatables-bundle
```

### Step 2: Enable the bundle

Enable the bundle in the kernel:

``` php
<?php
// app/AppKernel.php

public function registerBundles()
{
    $bundles = array(
        // ...
        new Tejadong\DatatablesBundle\TejadongDatatablesBundle(),
    );
}
```

### Step 3: Add the config format


Add settings to support date filtering d/m/Y H:i:s :

```yml
doctrine:
    orm:
        dql:
            datetime_functions:
                date_format: DoctrineExtensions\Query\Mysql\DateFormat
```

## Usage

To respond to a DataTables.js request from a controller, you can do the following:

``` php

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')->getDatatable('AcmeDemoBundle:Customer');

    return $datatable->getSearchResults();
}
```

## Entity Associations and Join Types

By default an entity association is inner joined. This can be changed as a default, or it
can be set on a per columm basis:

``` php
use Tejadong\DatatablesBundle\Datatables\DataTable;
...

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')->getDatatable('AcmeDemoBundle:Customer');

     // The default type for all joins is inner. Change it to left if desired.
    $datatable->setDefaultJoinType(Datatable::JOIN_LEFT);

    // Can set JOIN_LEFT or JOIN_INNER on a per-column basis
    $datatable->setJoinType('customer', Datatable::JOIN_INNER);

    return $datatable->getSearchResults();
}
```

## Search Result Response Types

By default, when you execute the `getSearchResults` method, a Symfony `Response` object will be returned.
If you need a different format for the response, you can specify the result type manually using the
constants `Datatable::RESULT_ARRAY` and `Datatable::RESULT_JSON`:

``` php
use Tejadong\DatatablesBundle\Datatables\DataTable;
...

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')->getDatatable('AcmeDemoBundle:Customer');

    // Get the results as an array
    $datatableArray = $datatable->getSearchResults(Datatable::RESULT_ARRAY);
}
```

## Pre-Filtering Search Results

In many cases you may want to pre-filter which entities the datatables response will return, which 
would then be further filtered through a user global or individual column search. To accomplish
this you can add callbacks with the `addWhereBuilderCallback` method. The callback will execute at the
end of the `setWhere` method which builds the WHERE clause for the QueryBuilder object. The callback
is passed the QueryBuilder instance as an argument.

``` php

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')->getDatatable('AcmeDemoBundle:Customer');

    // Add the $datatable variable, or other needed variables, to the callback scope
    $datatable->addWhereBuilderCallback(function($qb) use ($datatable) {
            $andExpr = $qb->expr()->andX();

            // The entity is always referred to using the CamelCase of its table name
            $andExpr->add($qb->expr()->eq('Customer.isActive','1'));

            // Important to use 'andWhere' here...
            $qb->andWhere($andExpr);

    });

    return $datatable->getSearchResults();
}
```

As noted above, all join names are done by using CamelCase on the table name of the entity. Related 
entities are separated out from the main entity with an underscore. So an entity relation on `Customer` 
called `Location` with a field name called `city`, would be referenced in QueryBuilder as 
`Customer_Location.city`

By default, pre-filtered results will return a total count back to DataTables.js with the filtering applied.
If you would like the total count to reflect the total number of entities before the pre-filtering was applied
then you can toggle it with the `hideFilteredCount` method.

``` php

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')
        ->getDatatable('AcmeDemoBundle:Customer')
        ->addWhereBuilderCallback(function($qb) use ($datatable) {
            // ...
        })
        ->hideFilteredCount(false);

    return $datatable->getSearchResults();
}
```

## DateTime Formatting

All formatting is handled by the serializer service in use (likely JMSSerializer). To change the DateTime
formatting when using the JMSSerializer you can either use annotation or define a default format in
your `app/config/config.yml` file.

```yml
jms_serializer:
    handlers:
        datetime:
            default_format: "m-d-Y @ H:i:s"
```

If you only want a format for a specific field you would use the annotation strategy, such as...

```php
namespace Acme\DemoBundle\Entity;

use JMS\Serializer\Annotation as Serializer;
use Doctrine\ORM\Mapping as ORM;

// ...

    /**
     * @var \DateTime
     *
     * @Serializer\Type("DateTime<'Y-m-d'>")
     * @ORM\Column(name="created", type="datetime")
     */

// ...
```

For more details on formatting output, please refer to [this document](http://jmsyst.com/libs/serializer/master/reference/annotations).

## DT_RowId and DT_RowClass

The properties DT_RowId and DT_RowClass are special DataTables.js properties. See the following article...

http://datatables.net/release-datatables/examples/server_side/ids.html

You can toggle and modify these properties with the methods `setDtRowClass`, `useDtRowClass`, and `useDtRowId`...

``` php

public function getDatatableAction()
{
    $datatable = $this->get('tejadong_datatables')
        ->getDatatable('AcmeDemoBundle:Customer')
        ->setDtRowClass('special-class') // Add whatever class(es) you want. Separate classes with a space.
        ->useDtRowId(true);

    return $datatable->getSearchResults();
}
```

By default neither properties are added to the output

## The Doctrine Paginator and MS SQL

By default, the Doctrine Paginator utility is used to correctly set limits and offsets. However, using it may cause issues with
MS SQL. You may receive an error like...

`SQL Error - The ORDER BY clause is invalid in views, inline functions, derived tables, subqueries, and common table expressions, unless TOP, OFFSET or FOR XML is also specified`

To get around this you can disable the use of the Paginator by doing the following...

```yml
tejadong_datatables:
    datatable:
        use_doctrine_paginator: false
```

However, please note that by disabling the use of the paginator you may not get the full results from DataTables that you would
expect.
