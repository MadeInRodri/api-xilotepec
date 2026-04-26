<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PromotionController extends Controller
{
    /**
     * Listar promociones (Filtro para clientes: solo activas y en fecha)
     */
    public function index(Request $request)
    {
        $query = Promotion::query();

        // Si es cliente, solo mostramos las vigentes y activas
        if ($request->has('active_only')) {
            $now = now();
            $query->where('is_active', true)
                  ->where('start_date', '<=', $now)
                  ->where('end_date', '>=', $now);
        }

        $promotions = $query->with('products')->get();

        if ($promotions->isEmpty()) {
            return response()->json(['message' => 'No hay promociones disponibles'], 404);
        }

        return response()->json($promotions, 200);
    }

    /**
     * Crear la promoción (Metadatos básicos)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'image_url' => 'required|url',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'type' => 'required|string|in:porcentaje,monto_fijo,2x1,combo_fijo',
                'value' => 'required|numeric|min:0',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
            }

            $promotion = Promotion::create($validator->validated());

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Promoción creada. Ahora puedes agregarle productos.',
                'promotion' => $promotion
            ], 201);

        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'mensaje' => 'Error en el servidor'], 500);
        }
    }

    /**
     * AGREGAR o ACTUALIZAR productos en la promoción (Manejo de la tabla pivote)
     */
    public function syncProducts(Request $request, $id)
    {
        try {
            $promotion = Promotion::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'products' => 'required|array',
                'products.*.product_id' => 'required|exists:products,id',
                'products.*.required_quantity' => 'required|integer|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
            }

            // Preparamos los datos para el método sync()
            // Formato esperado: [ id => ['pivot_col' => valor], ... ]
            $syncData = [];
            foreach ($request->products as $item) {
                $syncData[$item['product_id']] = [
                    'required_quantity' => $item['required_quantity']
                ];
            }

            // sync() elimina los que no estén en el array y agrega/actualiza los nuevos
            $promotion->products()->sync($syncData);

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Productos de la promoción actualizados correctamente',
                'promotion' => $promotion->load('products')
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Promoción no encontrada'], 404);
        }
    }

    /**
     * Ver detalle de una promoción con sus productos y cantidades requeridas
     */
    public function show($id)
    {
        try {
            $promotion = Promotion::with('products')->findOrFail($id);
            return response()->json($promotion, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Promoción no encontrada'], 404);
        }
    }

    /**
     * Actualizar metadatos de la promoción
     */
    public function update(Request $request, $id)
    {
        $promotion = Promotion::find($id);
        if (!$promotion) return response()->json(['message' => 'No existe'], 404);

        $promotion->update($request->all());

        return response()->json([
            'status' => 'exito',
            'mensaje' => 'Datos de promoción actualizados',
            'promotion' => $promotion
        ], 200);
    }

    /**
     * Eliminar promoción (Se borra la relación en la pivote automáticamente por el cascade)
     */
    public function destroy($id)
    {
        $promotion = Promotion::find($id);
        if (!$promotion) return response()->json(['message' => 'No existe'], 404);

        $promotion->delete();
        return response()->json(['mensaje' => 'Promoción eliminada'], 200);
    }
}