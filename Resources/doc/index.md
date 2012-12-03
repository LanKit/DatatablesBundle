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

## Prerequisites

This version of the bundle requires Symfony 2.1+.

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
