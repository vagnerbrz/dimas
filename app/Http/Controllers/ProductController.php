<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::orderBy('position')->get();
        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_daily_special' => 'boolean',
            'is_additional_offer' => 'boolean',
            'position' => 'integer',
        ]);

        Product::create($validated);

        return redirect()->route('products.index')->with('success', 'Produto criado com sucesso!');
    }

    public function show(Product $product)
    {
        return view('products.show', compact('product'));
    }

    public function edit(Product $product)
    {
        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        // Verificação robusta para Toggle de ativação
        if ($request->has('is_active') && !$request->has('name') && !$request->has('price')) {
            $product->update(['is_active' => $request->is_active]);
            return redirect()->back()->with('success', 'Status do produto atualizado!');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($product->id)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'is_daily_special' => 'boolean',
            'is_additional_offer' => 'boolean',
            'position' => 'integer',
        ]);

        $product->update($validated);

        return redirect()->route('products.index')->with('success', 'Produto atualizado com sucesso!');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Produto removido com sucesso!');
    }
}
