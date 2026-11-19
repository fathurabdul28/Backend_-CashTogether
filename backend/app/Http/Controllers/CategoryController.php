<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $r)
    {
        return Category::where('household_id', $this->hid($r))->where('archived', false)->orderBy('sort_order')->get();
    }

    public function store(Request $r)
    {
        $d = $r->validate([
            'name' => 'required|string|max:40', 'kind' => 'required|in:income,expense',
            'icon' => 'required|string|max:40', 'color' => 'required|in:sage,clay,teal,mustard,plum,stone',
        ]);
        return response(Category::create($d + ['household_id' => $this->hid($r)]), 201);
    }
}
