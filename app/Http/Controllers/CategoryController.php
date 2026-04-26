<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class CategoryController extends Controller
{
    /**
     * Muestra todas las categorías (Público)
     */
    public function index()
    {
        // Traemos las categorías y de paso contamos cuántos productos tiene cada una
        $categories = Category::withCount('products')->get();

        if ($categories->isEmpty()) {
            return response()->json(['message' => 'No hay categorías registradas'], 404);
        }

        return response()->json($categories, 200);
    }

    /**
     * Crea una nueva categoría 
     */
    public function store(Request $request)
    {
        try {
            $validatedData = Validator::make($request->all(), [
                // Validamos que el nombre sea único en la tabla categories
                'name' => 'bail|required|string|max:255|unique:categories,name',
            ], [
                'name.unique' => 'Ya existe una categoría con este nombre.'
            ]);

            if ($validatedData->fails()) {
                return response()->json([
                    'status' => "error",
                    'mensaje' => "Datos inválidos",
                    'errores' => $validatedData->errors()
                ], 422);
            }

            $category = Category::create($validatedData->validated());

            return response()->json([
                'status' => "exito",
                'mensaje' => 'Categoría creada exitosamente',
                'category' => $category
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
     * Muestra una categoría específica con sus productos
     */
    public function show(string $id)
    {
        try {
            // Cargamos la categoría junto con todos los productos que le pertenecen
            $category = Category::with('products')->findOrFail($id);
            return response()->json($category, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'No existe una categoría con el ID: ' . $id
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Ocurrió un error en el servidor'
            ], 500);
        }
    }

    /**
     * Actualiza el nombre de una categoría 
     */
    public function update(Request $request, string $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'La categoría no existe'], 404);
        }

        $validatedData = Validator::make($request->all(), [
            // Ignoramos el ID actual para que no marque error si enviamos el mismo nombre
            'name' => 'required|string|max:255|unique:categories,name,' . $category->id,
        ]);

        if ($validatedData->fails()) {
            return response()->json([
                'status' => "error",
                'mensaje' => "Datos inválidos para actualizar",
                'errores' => $validatedData->errors()
            ], 422);
        }

        $category->update($validatedData->validated());

        return response()->json([
            'status' => 'exito',
            'message' => 'La categoría ha sido actualizada',
            'categoria' => $category
        ], 200);
    }

    /**
     * Elimina una categoría (Solo Admin) - CON PROTECCIÓN
     */
    public function destroy(string $id)
    {
        $category = Category::find($id);
        
        if (!$category) {
            return response()->json(['message' => 'No existe esta categoría'], 404);
        }

        // --- PROTECCIÓN CRÍTICA ---
        // Si recuerdas, en tus migraciones pusimos 'onDelete cascade'. 
        // Si borras la categoría "Cafés", ¡se borrarán todos los cafés de la base de datos!
        // Esta validación evita que un admin borre una categoría que aún tiene productos.
        if ($category->products()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'No puedes eliminar esta categoría porque aún tiene productos asignados. Mueve los productos a otra categoría primero.'
            ], 400); // 400 Bad Request
        }

        $category->delete();
        
        return response()->json([
            'status' => 'exito',
            'message' => 'La categoría ha sido eliminada correctamente'
        ], 200);
    }
}