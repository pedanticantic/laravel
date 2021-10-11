<?php

namespace App\Http\Controllers;

use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    public $storeId;

    /**
     * This shouldn't really be here; it should be in some image service class. I'm leaving it here for now just to
     * make things easier.
     *
     * @var string
     */
    private $imagesDomain;

    public function __construct()
    {
        /* As the system manages multiple stores a storeBuilder instance would
        normally be passed here with a store object. The id of the example
        store is being set here for the purpose of the test */
        $this->storeId = 3;

        $this->imagesDomain = "https://img.tmstor.es/";
    }

    public function show(string $sectionName = null)
    {
        // @TODO: If a section name is supplied, validate it.

        // @TODO: Build the query. Do all this in a service method.
        $query = StoreProduct::where('store_id', $this->storeId)->where('deleted', '0')->where('available', '1')
            ->with('artist');

        // @TODO: specify the sort order, depending on whether a section name was supplied or not.
        // If a section name is supplied, add an appropriate filter to the query.
        if (!is_null($sectionName)) {
            $query = $query->whereHas('sections', function (Builder $query) use ($sectionName) {
                $query->where('description', $sectionName);
            });
        }

        // @TODO: Add code to exclude products based on $launch_date, $remove_date, disabled countries, etc.

        // Determine the total number of rows that match the criteria, and therefore how many pages of rows there are.
        $rows = $query->get();

        // @TODO: Add any pagination in here. Be careful of it breaking the subsequent code.
        $page = 1;
        $number = 8;

        // @TODO: get the actual results of the query. IE put the LIMIT/OFFSET stuff in.
        $pages = ceil(count($rows)/$number);
        $result = array_merge(
            ['pages' => $pages],
            $this->presentProducts($query->get())
        );

        // Output the results.
        // @TODO: Use the JSON converter built in to the Response object.
        return json_encode($result);
    }

    /**
     * I thought presenters were built in to Laravel. I could install and use a package, but for speed, I'm just
     * emulating a presenter here. It would be relatively easy to convert it to use a presenter.
     *
     * @param Collection $storeProducts
     *
     * @return array
     */
    private function presentProducts(Collection $storeProducts): array
    {
        $result = [];
        foreach($storeProducts as $storeProduct) {
            $image = $this->imagesDomain;
            if (strlen($storeProduct->image_format) > 2) {
                $image .= sprintf('/%d.%s', $storeProduct->main_id, $storeProduct->image_format);
            } else {
                $image .= 'noimage.jpg';
            }

            $price = $storeProduct->price;
            switch (session(['currency'])) {
                case "USD":
                    $price = $storeProduct->dollar_price;
                    break;
                case "EUR":
                    $price = $storeProduct->euro_price;
                    break;
            }


            $result[] = [
                'image' => $image,
                'id' => $storeProduct->id,
                'artist' => $storeProduct->artist->name,
                'title' => strlen($storeProduct->display_name) > 3 ? $storeProduct->display_name : $storeProduct->name,
                'description' => $storeProduct->description,
                'price' => $price,
                'format' => $storeProduct->type,
                'release_date' => $storeProduct->release_date,
            ];
        }

        return $result;
    }
}
