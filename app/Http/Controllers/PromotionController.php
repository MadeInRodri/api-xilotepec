<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
       //Le quite with products
        $promotions = $query->with('products:id,name,price')->get();

        if ($promotions->isEmpty()) {
            return response()->json(['message' => 'No hay promociones disponibles'], 404);
        }

        return response()->json($promotions, 200);
    }


    /**
     * Crear la promoción y asociar sus productos
     */
    public function store(Request $request)
    {
        try {
            // Iniciamos transacción para asegurar que todo se guarde o nada
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string',
                'image_url' => 'required|url',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'type' => 'required|string|in:porcentaje,monto_fijo,2x1,combo_fijo',
                'value' => 'required|numeric|min:0',
                'is_active' => 'boolean',
                // Validamos que si viene el array de productos, traiga IDs válidos
                'products' => 'nullable|array',
                'products.*.id' => 'required_with:products|exists:products,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
            }

            // Creamos la promoción excluyendo el arreglo de productos para no causar error de columna inexistente
            $promotion = Promotion::create($request->except('products'));

            // Si vienen productos en el request, armamos la data para la tabla pivote
            if ($request->has('products') && is_array($request->products)) {
                $syncData = [];
                foreach ($request->products as $item) {
                    // Si el payload trae 'required_quantity' lo usamos, si no, por defecto es 1
                    $quantity = isset($item['required_quantity']) ? $item['required_quantity'] : 1;
                    
                    // Armamos el array asociativo: [ product_id => ['required_quantity' => X] ]
                    $syncData[$item['id']] = [
                        'required_quantity' => $quantity
                    ];
                }
                
                // Guardamos en la tabla pivote
                $promotion->products()->sync($syncData);
            }

            // Confirmamos los cambios en la BD
            DB::commit();

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Promoción y productos creados exitosamente.',
                'promotion' => $promotion->load('products') // Cargamos la relación para devolverla completa
            ], 201);

        } catch (Exception $e) {
            DB::rollBack(); // Si algo falla, deshacemos todo
            return response()->json(['status' => 'error', 'mensaje' => 'Error en el servidor: ' . $e->getMessage()], 500);
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
     * Actualizar metadatos de la promoción y sincronizar productos
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $promotion = Promotion::find($id);
            if (!$promotion) {
                return response()->json(['status' => 'error', 'mensaje' => 'Promoción no encontrada'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'image_url' => 'sometimes|url',
                'start_date' => 'sometimes|required|date',
                'end_date' => 'sometimes|required|date|after:start_date',
                'type' => 'sometimes|required|string|in:porcentaje,monto_fijo,2x1,combo_fijo',
                'value' => 'sometimes|required|numeric|min:0',
                'is_active' => 'boolean',
                'products' => 'nullable|array',
                'products.*.id' => 'required_with:products|exists:products,id'
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
            }

            // Actualizamos solo los datos de la tabla promotions
            $promotion->update($request->except('products'));

            // Sincronizamos la tabla pivote de productos si vienen en la petición
            if ($request->has('products') && is_array($request->products)) {
                $syncData = [];
                foreach ($request->products as $item) {
                    $quantity = isset($item['required_quantity']) ? $item['required_quantity'] : 1;
                    $syncData[$item['id']] = [
                        'required_quantity' => $quantity
                    ];
                }
                
                // sync() es perfecto aquí porque eliminará los productos que ya no vengan en el arreglo
                $promotion->products()->sync($syncData);
            }

            DB::commit();

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Datos de promoción actualizados correctamente',
                'promotion' => $promotion->load('products')
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'mensaje' => 'Error en el servidor: ' . $e->getMessage()], 500);
        }
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

    public function promotions_active(){
        $query = Promotion::query();

        $promotions = $query->where('is_active', 1)->get();

        if(!$promotions) return response()->json(['data' => 'Caca']); 

        return response()->json([
            'data' => $promotions,
        ],200);
    }
}