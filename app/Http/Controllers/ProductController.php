<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class ProductController extends Controller
{

    public function index(Request $request)
    {
        // Iniciamos la consulta incluyendo la información de su categoría (Eager Loading)
        $query = Product::with('category');

        // Filtro por categoría (ej. /productos?category_id=2)
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por estado (ej. /productos?is_active=1 para la landing de clientes)
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No hay productos registrados con esos criterios'], 404);
        }

        return response()->json($products, 200);
    }

    /**
     * Crea un nuevo producto (Solo Admin)
     */
    public function store(Request $request)
    {
        try {
            $validatedData = Validator::make($request->all(), [
                'name' => 'bail|required|string|max:255',
                'description' => 'bail|required|string|max:255',
                'price' => 'bail|required|numeric|min:0',
                'category_id' => 'bail|required|exists:categories,id', // Verifica que la categoría exista en la BD
                'url_image' => 'bail|required|string|url',
                'max_quantity' => 'bail|required|integer|min:0',
                'is_active' => 'boolean'
            ], [
                'category_id.exists' => 'La categoría seleccionada no existe en la base de datos.'
            ]);

            if ($validatedData->fails()) {
                return response()->json([
                    'status' => "error",
                    'mensaje' => "Datos de producto inválidos",
                    'errores' => $validatedData->errors()
                ], 422); // 422 es el estándar para errores de validación
            }

            $productData = $validatedData->validated();
            // Si no envían is_active, por defecto lo hacemos true
            $productData['is_active'] = $request->input('is_active', true);

            $product = Product::create($productData);

            return response()->json([
                'status' => "exito",
                'mensaje' => 'Producto creado exitosamente',
                'product' => $product->load('category') // Devolvemos el producto ya con los datos de su categoría
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => "error",
                'mensaje' => 'Ha habido un error en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Muestra un producto en específico
     */
    public function show(string $id)
    {
        try {
            $product = Product::with('category')->findOrFail($id);
            return response()->json($product, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No existe un producto con el ID: ' . $id
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error en el servidor'
            ], 500);
        }
    }

    /**
     * Actualiza un producto existente (Solo Admin)
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'El producto no existe'], 404);
        }

        // Usamos 'sometimes' para que solo valide los campos que realmente se envían en la petición
        $validatedData = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'category_id' => 'sometimes|exists:categories,id',
            'url_image' => 'sometimes|string|url',
            'max_quantity' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean'
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => "error",
                'mensaje' => "Datos inválidos para actualizar",
                'errores' => $validatedData->errors()
            ], 422);
        }

        $product->update($validatedData->validated());

        return response()->json([
            'status' => 'exito',
            'message' => 'El producto ha sido actualizado',
            'producto' => $product->load('category')
        ], 200);
    }

    /**
     * Elimina un producto físicamente (Solo Admin)
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json(['message' => 'No existe este producto'], 404);
        }

        $product->delete();
        
        return response()->json([
            'status' => 'exito',
            'message' => 'El producto ha sido eliminado correctamente'
        ], 200);
    }
}