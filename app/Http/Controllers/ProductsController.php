<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\StoreProduct;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    /**
     * @param Request $request
     * @param string|null $sectionName
     *
     * @return JsonResponse|Response
     */
    public function show(Request $request, string $sectionName = null)
    {
        // If a section name is supplied, validate it.
        if (!is_null($sectionName)) {
            $found = Section::where('description', $sectionName)->count() > 0;
            if (!$found) {
                return response(sprintf('Sorry, that section ("%s") does not exist', $sectionName), 404);
            }
        }

        // @TODO: Build the query. Do all this in a service method.
        $query = StoreProduct::where('store_id', $this->storeId)->where('deleted', '0')->where('available', '1')
            ->with('artist');

        // If a section name is supplied, add an appropriate filter to the query.
        if (!is_null($sectionName)) {
            $query = $query->whereHas('sections', function (Builder $query) use ($sectionName) {
                $query->where('description', $sectionName);
            });
        }

        // Specify the sort order, depending on whether a sort order and/or a section name was supplied or not.
        $sort = $request->get('sort', 'position');
        switch ($sort) {
            case 'az':
                $query->orderBy('name', 'asc');
                break;
            case 'za':
                $query->orderBy('name' ,'desc');
                break;
            case 'low':
                $query->orderBy('price', 'asc');
                break;
            case 'high':
                $query->orderBy('price', 'desc');
                break;
            case 'old':
                $query->orderBy('release_date', 'asc');
                break;
            case 'new':
                $query->orderBy('release_date', 'desc');
                break;
            case 'position':
            default:
                if (is_null($sectionName)) {
                    $query->orderBy('position', 'asc')->orderBy('release_date', 'desc');
                } else {
                    // @TODO: Get the sorting on section position working. Might have to refactor the filter on section name.
                    $query->/*orderBy('store_products_section.position', 'asc')->*/orderBy('release_date', 'desc');
                }
                break;
        }

        // Add code to exclude products based on $launch_date, $remove_date, disabled countries, etc.
        // If we're not in preview mode, exclude products that are launching in the future.
        if (!isset($_SESSION['preview_mode'])) {
            // We need to be careful to make sure the "OR" doesn't interfere with anything else.
            // This is a bit yucky because '0000-00-00 00:00:00' is not a valid date/time and MySQL complains if you try and
            // compare a date/time value to it; you have to convert the date/time to a string and compare them as strings.
            $query->whereRaw('(CAST(launch_date AS char) = "0000-00-00 00:00:00" OR launch_date <= CURRENT_TIMESTAMP)');
        }
        // Similar with "remove date", but we always do it. Again, comparing to that invalid date is yucky.
        $query->whereRaw('(CAST(remove_date AS char) = "0000-00-00 00:00:00" OR remove_date > CURRENT_TIMESTAMP)');
        // Handle any disabled countries.
        $country_code = 'GB'; // Hard-coded for now.
        $query->whereRaw('(disabled_countries = "" OR FIND_IN_SET("'.$country_code.'", disabled_countries) = 0)');

        // Determine the total number of rows that match the criteria, and therefore how many pages of rows there are.
        $rowCount = $query->count();

        // Add any pagination in here. This must be done after we get the "full" row count.
        // @TODO: Ideally we should validate that these parameters (number and page) are integers and greater than zero.
        $number = $request->has('number') ? (int) $request->get('number') : 8;
        $number = max(1, $number); // Don't allow negative or zero page lengths.
        $page = $request->has('page') ? (int) $request->get('page') : 1;
        $page = max(1, $page); // Don't allow negative or zero page numbers.

        $query->skip(($page-1)*$number)->take($number); // Tell the query to splice the rows at the right place.

        // Get the actual results of the query, and build the output from this endpoint.
        $pages = ceil($rowCount/$number);
        $result = array_merge(
            ['pages' => $pages],
            $this->presentProducts($query->get())
        );

        // Output the results. Use the JSON converter built in to the Response object.
        return response()->json($result);
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
                $image .= sprintf('/%d.%s', $storeProduct->id, $storeProduct->image_format);
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
