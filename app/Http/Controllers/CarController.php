<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCarRequest;
use App\Models\Car;
use App\Models\User;
use Illuminate\Container\Attributes\Storage as AttributesStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CarController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $cars = User::find(1)
            ->cars()
            ->with('primaryImage', 'maker', 'model')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('car.index', ['cars' => $cars]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('car.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCarRequest $request)
    {
        $data = $request->validated();

        $featuresData = $data['features'] ?? [];
        $images = $request->file('images') ?: [];

        $data['user_id'] = 1;
        $car = Car::create($data);

        $car->features()->create($featuresData);

        foreach ($images as $i => $image) {
            $path = $image->store('images', 'public');
            $car->images()->create(['image_path' => $path, 'position' => $i + 1]);
        }

        return redirect()->route('car.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Car $car)
    {
        if (!$car->published_at) {
            abort(404);
        }

        return view('car.show', ['car' => $car]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Car $car)
    {
        return view('car.edit', ['car' => $car]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreCarRequest $request, Car $car)
    {
        $data = $request->validated();

        $features = array_merge([
            'abs' => 0,
            'air_conditioning' => 0,
            'power_windows' => 0,
            'power_door_locks' => 0,
            'cruise_control' => 0,
            'bluetooth_connectivity' => 0,
            'remote_start' => 0,
            'gps_navigation' => 0,
            'heated_seats' => 0,
            'climate_control' => 0,
            'rear_parking_sensors' => 0,
            'leather_seats' => 0,
        ], $data['features'] ?? []);

        $car->update($data);
        $car->features()->update($features);

        return redirect()->route('car.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Car $car)
    {
        $car->delete();

        return redirect()->route('car.index');
    }

    public function search(Request $request)
    {
        $maker = $request->integer('maker_id');
        $model = $request->integer('model_id');
        $state = $request->integer('state_id');
        $city = $request->integer('city_id');
        $carType = $request->integer('car_type_id');
        $yearFrom = $request->integer('year_from');
        $yearTo = $request->integer('year_to');
        $priceFrom = $request->integer('price_from');
        $priceTo = $request->integer('price_to');
        $fuelType = $request->integer('fuel_type_id');
        $mileage = $request->integer('mileage');
        $sort = $request->input('sort', '-published_at');

        $query = Car::with('primaryImage', 'city', 'maker', 'model', 'carType', 'fuelType')->where('published_at', '<', now());

        if ($maker) {
            $query->where('maker_id', $maker);
        }
        if ($model) {
            $query->where('model_id', $model);
        }
        if ($state) {
            $query->join('cities', 'cars.city_id', '=', 'cities.id')->where('state_id', $state);
        }
        if ($city) {
            $query->where('city_id', $city);
        }
        if ($carType) {
            $query->where('car_type_id', $carType);
        }
        if ($yearFrom) {
            $query->where('year', '>=', $yearFrom);
        }
        if ($yearTo) {
            $query->where('year', '<=', $yearTo);
        }
        if ($priceFrom) {
            $query->where('price', '>=', $priceFrom);
        }
        if ($priceTo) {
            $query->where('price', '<=', $priceTo);
        }
        if ($fuelType) {
            $query->where('fuel_type_id', $fuelType);
        }
        if ($mileage) {
            $query->where('mileage', '<=', $mileage);
        }
        if (str_starts_with($sort, '-')) {
            $sort = substr($sort, 1);
            $query->orderBy($sort, 'desc');
        } else {
            $query->orderBy($sort);
        }

        $cars = $query->paginate(15)->withQueryString();

        return view('car.search', ['cars' => $cars]);
    }

    public function watchlist()
    {
        $cars = User::find(4)->favouriteCars()->with('primaryImage', 'city', 'maker', 'model', 'carType', 'fuelType')->paginate(15);

        return view('car.watchlist', ['cars' => $cars]);
    }

    public function carImages(Car $car)
    {

        return view('car.images', ['car' => $car]);
    }

    public function updateImages(Request $request, Car $car)
    {
        $data = $request->validate([
            'delete_images' => 'array',
            'delete_images.*' => 'integer',
            'positions' => 'array',
            'positions.*' => 'integer'
        ]);

        $deleteImages = $data['delete_images'] ?? [];
        $positions = $data['positions'] ?? [];

        $imagesToDelete = $car->images()->whereIn('id', $deleteImages)->get();

        foreach ($imagesToDelete as $img) {
            if (Storage::exists($img->image_path)) {
                Storage::delete($img->image_path);
            }
        }

        $car->images()->whereIn('id', $deleteImages)->delete();

        foreach ($positions as $id => $pos) {
            $car->images()->where('id', $id)->update(['position' => $pos]);
        }

        return redirect()->back();
    }
}
