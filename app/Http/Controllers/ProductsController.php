<?php

namespace App\Http\Controllers;

use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public $storeId;

    public function __construct()
    {
        /* As the system manages multiple stores a storeBuilder instance would
        normally be passed here with a store object. The id of the example
        store is being set here for the purpose of the test */
        $this->storeId = 3;
    }

    public function show(string $sectionName = null)
    {
        // @TODO: If a section name is supplied, validate it.

        // @TODO: Build the query. Do this in a service method.
        $storeID = 3;
        $query = StoreProduct::where('store_id', $storeID)->where('deleted', '0')->where('available', '1');

        // @TODO: If a section name is supplied, add an appropriate filter to the query.
        if (!is_null($sectionName)) {
            $query = $query->whereHas('sections', function (Builder $query) use ($sectionName) {
                $query->where('description', $sectionName);
            });
        }

        // @TODO: Add any pagination in here.

        // @TODO: get the actual results of the query. Determine the number of rows returned.
        $rows = $query->get();
//        echo '$rows count: '.count($rows);

        // @TODO: Output the results. We need the number of pages and then the results.
        return json_encode($rows);
    }
}
