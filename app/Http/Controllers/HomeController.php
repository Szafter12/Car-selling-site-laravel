<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarImage;
use App\Models\CarType;

class HomeController extends Controller
{
    public function index()
    {
        return view('home.index');
    }
}
