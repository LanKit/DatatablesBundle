Getting Started With LanKitDatatablesBundle
===========================================

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
for JSON encoding. If you do not have that bundle registered you will need to supply a different
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
    $dataTable->setDefaultJoinType(Datatable::JOIN_LEFT);

    // Can set JOIN_LEFT or JOIN_INNER on a per-column basis
    $dataTable->setJoinType('customer', Datatable::JOIN_INNER);

    return $datatable->getSearchResults();
}
```

## Search Result Response Types

By default, when you execute the `getSearchResults` method, a Symfony `Resoponse` object will be returned.
If you need a different format for the respose, you could also specify the result type manually using the
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
