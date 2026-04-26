<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class PaymentMethodController extends Controller
{
    /**
     * Listar todos los métodos de pago (Público/Cliente)
     */
    public function index()
    {
        $methods = PaymentMethod::all();

        if ($methods->isEmpty()) {
            return response()->json(['message' => 'No hay métodos de pago configurados'], 404);
        }

        return response()->json($methods, 200);
    }

    /**
     * Crear un nuevo método (Solo Admin)
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:payment_methods,name',
            ], [
                'name.unique' => 'Este método de pago ya existe.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'mensaje' => 'Datos inválidos',
                    'errores' => $validator->errors()
                ], 422);
            }

            $paymentMethod = PaymentMethod::create($validator->validated());

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Método de pago creado',
                'payment_method' => $paymentMethod
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error en el servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver un método en específico
     */
    public function show(string $id)
    {
        try {
            $method = PaymentMethod::findOrFail($id);
            return response()->json($method, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Método no encontrado'], 404);
        }
    }

    /**
     * Actualizar un método (Solo Admin)
     */
    public function update(Request $request, string $id)
    {
        $method = PaymentMethod::find($id);

        if (!$method) {
            return response()->json(['message' => 'El método no existe'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:payment_methods,name,' . $method->id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Datos inválidos',
                'errores' => $validator->errors()
            ], 422);
        }

        $method->update($validator->validated());

        return response()->json([
            'status' => 'exito',
            'mensaje' => 'Método actualizado',
            'payment_method' => $method
        ], 200);
    }

    /**
     * Eliminar un método de pago (Solo Admin)
     */
    public function destroy(string $id)
    {
        $method = PaymentMethod::find($id);

        if (!$method) {
            return response()->json(['message' => 'Método no encontrado'], 404);
        }

        // Protección: No borrar si hay órdenes que usaron este método
        if ($method->orders()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'mensaje' => 'No se puede eliminar este método porque hay órdenes registradas con él.'
            ], 400);
        }

        $method->delete();

        return response()->json([
            'status' => 'exito',
            'mensaje' => 'Método de pago eliminado correctamente'
        ], 200);
    }
}