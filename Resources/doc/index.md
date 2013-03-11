Getting Started With LanKitDatatablesBundle
===========================================

* [Prerequisites](#prerequisites)
* [Installation](#installation)
* [Usage](#usage)
* [Entity Associations and Join Types](#entity-associations-and-join-types)
* [Using server side control](#server-side-control)
* [Search Result Response Types](#search-result-response-types)
* [Pre-Filtering Search Results](#pre-filtering-search-results)
* [DateTime Formatting](#datetime-formatting)
* [DT_RowId and DT_RowClass](#dt_rowid-and-dt_rowclass)
* [The Doctrine Paginator and MS SQL](#the-doctrine-paginator-and-ms-sql)

This bundle provides an intuitive way to process DataTables.js requests by
using mData. The mData from the DataTables request corresponds to fields and
associations on a specific entity. You can access related entities off the 
base entity by using dotted notation.

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

This bundle supports two kind of approaches: the default one is totally controlled
by the front-end application, it means that it will parse any required information
that your datatables.js would require through the `aoColumns` array of `mData`
properties. The second one is called `serverSideControl`, using this approach you
can restrict the information available for a user. It will throw an error if the 
user injects a malicious javascript code to require restricted information, like:

``` js
    "aoColumns": [
        { "mData": "email" },
        { "mData": "password" }
    ]
```

Behind the scenes `aoColumns` still in control of the information exchange with 
the bundle, but its the bundle who will provided datatable.js the information of
which columns are available.

## Prerequisites

This version of the bundle requires Symfony 2.1+. This bundle also needs the JMSSerializerBundle
for JSON encoding. For information on installing the JMSSerializerBundle please look [here](http://jmsyst.com/bundles/JMSSerializerBundle/master/installation).

If you do not have that bundle registered you will need to supply a different
serializer service in your config file...

```yml
// app/config.yml

lankit_datatables:
    services:
        serializer: some_other_serializer # Defaults to jms_serializer.serializer
```

## Installation

### Step 1: Download LanKitDatatablesBundle using composer

Add LanKitDatatablesBundle to your composer.json:

```js
{
    "require": {
        "lankit/datatables-bundle": "*"
    }
}
```

Use composer to download the bundle using the following command:

``` bash
$ php composer.phar update lankit/datatables-bundle
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
        new LanKit\DatatablesBundle\LanKitDatatablesBundle(),
    );
}
```

## Usage

To respond to a DataTables.js request from a controller, you can do the following:

``` php

public function getDatatableAction()
{
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer');

    return $datatable->getSearchResults();
}
```

## Entity Associations and Join Types

By default an entity association is inner joined. This can be changed as a default, or it
can be set on a per columm basis:

``` php
use LanKit\DatatablesBundle\Datatables\DataTable;
...

public function getDatatableAction()
{
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer');

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
use LanKit\DatatablesBundle\Datatables\DataTable;
...

public function getDatatableAction()
{
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer');

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
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer');

    /* 
     * This is automatically for any coloumn of your dataTable, but on this case customer.isActive is
     * a filtering criteria to be used inside a callback, so you need to add it manually. If the 
     * filtered variable is a column then you don't need to do anything.
     */
    $dataTable->addColumnDQLPartName('customer.isActive'); 

    // Add the $datatable variable, or other needed variables, to the callback scope
    $datatable->addWhereBuilderCallback(function($qb) use ($datatable) {
            $andExpr = $qb->expr()->andX();

            // The entity is referred using a helper method of Datatable object
            $andExpr->add($qb->expr()->eq($dataTable->getColumnDQLPartName('customer.isActive'), '1'));

            // Important to use 'andWhere' here...
            $qb->andWhere($andExpr);

    });

    return $datatable->getSearchResults();
}
```

As noted above, you get join names using `getColumnDQLPartName` method of your $datable object. You just need
to pass as an argument the key used on your `mData` property or the key used by `addColumn` method (if you are
using `serverSideControl` to control columns).
So an entity relation on `Customer` called `Location` with a field name called `city`, would be retrieved in
QueryBuilder with `getColumnDQLPartName` method of `$datatable` object using `customer.location.city` as its 
key.

By default, pre-filtered results will return a total count back to DataTables.js with the filtering applied.
If you would like the total count to reflect the total number of entities before the pre-filtering was applied
then you can toggle it with the `hideFilteredCount` method.

``` php

public function getDatatableAction()
{
    $datatable = $this->get('lankit_datatables')
        ->getDatatable('AcmeDemoBundle:Customer')
        ->addWhereBuilderCallback(function($qb) use ($datatable) {
            // ...
        })
        ->hideFilteredCount(false);

    return $datatable->getSearchResults();
}
```

A shortcut method called `addWhereCollectionCallback` is also available to add a callback function with
`where` instructions collections in the end of `setWhere`, the main difference to the previous one
(`addWhereBuilderCallback`) is that the developer don't need to worry about the QueryBuilder and also reduces
the risk of disrupting something while its working with more developers.

``` php

public function getDatatableAction()
{
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer');

    $dataTable->addColumnDQLPartName('customer.isActive');
    $dataTable->addColumnDQLPartName('customer.area');

    $datatable->addWhereCollectionCallback(function($expr) use ($datatable) {
        return array( // Important to return an array here...
            $expr->eq(
                $dataTable->getColumnDQLPartName('customer.isActive'),
                '1'
            ), 
            $expr->neq(
                $dataTable->getColumnDQLPartName('customer.area'),
                $expr->literal(Customer::AREA_AGRICULTURE)
            )
        );
    });

    return $datatable->getSearchResults();
}
```

As noted above this simplifies the way of extending the query filtering. All you need to do is to return an
array of `Doctrine\ORM\Query\Expr` objects in your callback function. All this objects will be automatically
added to a `andX` collection and then inserted into the Datatable QueryBuilder using a `andWhere` clause. 

## Using server side control

By default the server side control is off, it means that the bundle will answer to every field request
from datatables.js. To change this behavior you first need to activate the server side control by providing
a second argument to `getDatatable` service method. The second step is to call `addColumn` method for every
desired information to be retrieved by datatables.js, let's consider the following: `description`, 
`customer.lastname` and `customer.location.address`; So, the first argument is the attribute name using the
same entity relationship and property dotted notation as before. The second argument is an array map with
key => value pairs that represent some options of your column. It supports the raw notation of datables.js
`aoColumns` variable, but its recommended the use of some constant values to avoid mistakes. 

``` php

public function getDatatableAction()
{
    // Notice the second argument, this will create a Datatable object server-side controlled
    $datatable = $this->get('lankit_datatables')->getDatatable('AcmeDemoBundle:Customer', true);
    $dataTable
        ->addColumn(
            'customer.lastname', 
            array(
                Datatable::COLUMN_TITLE => 'Last name'
            )
        )
        ->addColumn(
            'description', 
            array(
                Datatable::COLUMN_TITLE => 'Full description',
                Datatable::COLUMN_SORTABLE => false
            )
        )
        ->addColumn(
            'customer.location.address', 
            array(
                Datatable::COLUMN_TITLE => 'Address',
                Datatable::COLUMN_SORTABLE => false,
                Datatable::COLUMN_SEARCHABLE => false
            )
        )
    ;

    return $datatable->getSearchResults();
}
```

The above code will not work without a minor modification in your front-end application, as explained before
this bundle uses the `mData` values of `aoColumns` to process all reqired data. Now, instead of forcing the 
front-end developer to provide an entity level information, this data will be automatically returned through
`aoColumns` json object if the `serverSideControl` is set to true and if your columns are properly defined 
inside your controllers actions. To make all of this happen just add a javascript on your code to make a
request to `sAjaxSource` before creating datatables.js instance, if you are using jQuery it will be something
like this:

``` js
$(document).ready(function(){
    var sAjaxSource = 'http://URL_TO_GET_DATATABLE_ACTION';
    $.getJSON(sAjaxSource, function(dataTable){
        $('#example').dataTable({
            'bProcessing': true,
            'bServerSide': true,
            'sAjaxSource': sAjaxSource,
            'aoColumns': dataTable.aoColumns
        });
    });
});
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
    $datatable = $this->get('lankit_datatables')
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
lankit_datatables:
    datatable:
        use_doctrine_paginator: false
```

However, please note that by disabling the use of the paginator you may not get the full results from DataTables that you would
expect.
