<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Exception;

class OrderController extends Controller
{
    /**
     * Procesar el carrito y generar la factura (Para Clientes)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'required|exists:payment_methods,id',
            'detalles' => 'required|array|min:1',
            'detalles.*.product_id' => 'required|exists:products,id',
            'detalles.*.quantity' => 'required|numeric|min:0.1' // Permite decimales si venden por peso
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errores' => $validator->errors()], 422);
        }

        // INICIAMOS LA TRANSACCIÓN: O se guarda la orden completa, o no se guarda nada.
        DB::beginTransaction();

        try {
            $detallesProcesados = [];
            $granTotal = 0;

            // 1. Recorrer el carrito y calcular precios reales con promociones
            foreach ($request->detalles as $item) {
                // Obtenemos el producto fresco de la base de datos con sus promociones activas
                $product = Product::with(['promotions' => function($query) {
                    $now = now();
                    $query->where('is_active', true)
                          ->where('start_date', '<=', $now)
                          ->where('end_date', '>=', $now);
                }])->lockForUpdate()->find($item['product_id']); // lockForUpdate evita que otro cliente compre el mismo stock al mismo tiempo

                $cantidad = $item['quantity'];

                if ($cantidad > $product->max_quantity) {
                    // Detenemos todo si no hay stock suficiente para este producto
                    throw new Exception("Stock insuficiente para el producto: {$product->name}. Disponibles: {$product->max_quantity}");
                }
                
                // Calculamos el subtotal base
                $precioUnitarioFinal = $product->price;
                $subtotal = $precioUnitarioFinal * $cantidad;

                // 2. Lógica de Promociones (Si tiene alguna promoción activa)
                // Nota: Para combos multiproducto se requiere un motor de reglas más complejo. 
                // Aquí procesamos promociones directas al producto (Descuentos, 2x1, Monto fijo)
                $promoAplicada = $product->promotions->first(); 

                if ($promoAplicada) {
                    // Verificamos si cumple la cantidad requerida
                    $cantidadRequerida = $promoAplicada->pivot->required_quantity;

                    if ($cantidad >= $cantidadRequerida) {
                        switch ($promoAplicada->type) {
                            case 'porcentaje':
                                // Ej: 20% de descuento
                                $descuento = $precioUnitarioFinal * ($promoAplicada->value / 100);
                                $precioUnitarioFinal -= $descuento;
                                $subtotal = $precioUnitarioFinal * $cantidad;
                                break;
                            
                            case 'monto_fijo':
                                // Ej: Descuento de $1.00 por unidad
                                $precioUnitarioFinal = max(0, $precioUnitarioFinal - $promoAplicada->value);
                                $subtotal = $precioUnitarioFinal * $cantidad;
                                break;

                            case '2x1':
                                // Lógica matemática para 2x1 (Pagas la mitad de los productos, redondeado hacia arriba)
                                $pares = floor($cantidad / 2);
                                $sobrante = fmod($cantidad, 2); // fmod soporta decimales
                                $subtotal = ($pares * $product->price) + ($sobrante * $product->price);
                                // El precio unitario reflejado será un promedio para la factura
                                $precioUnitarioFinal = $subtotal / $cantidad; 
                                break;
                        }
                    }
                }

                $granTotal += $subtotal;

                // Preparamos el array del detalle para guardarlo después
                $detallesProcesados[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'unit_price' => $precioUnitarioFinal,
                    'quantity' => $cantidad,
                    'subtotal' => $subtotal
                ];
            }

            // 3. Crear la Orden principal
            $order = Order::create([
                'user_id' => auth()->id(), // Obtiene el ID del usuario logueado por JWT
                'date' => now(),
                'total' => $granTotal,
                'status' => 'pendiente', // Estados: pendiente, pagado, entregado, cancelado
                'payment_method_id' => $request->payment_method_id
            ]);

            // 4. Guardar todos los detalles de golpe usando la relación
            $order->details()->createMany($detallesProcesados);

            // TODO SALIÓ BIEN: Confirmamos cambios en BD
            DB::commit();

            return response()->json([
                'status' => 'exito',
                'mensaje' => 'Factura generada y orden procesada correctamente',
                'orden' => $order->load('details') // Devolvemos la orden con su desglose
            ], 201);

        } catch (Exception $e) {
            // SI HUBO UN ERROR: Revertimos todos los cambios
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'mensaje' => 'Error al procesar la orden',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver el historial de órdenes (Cliente ve las suyas, Admin ve todas)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Order::with(['details', 'paymentMethod']);

        // Si no es administrador, solo puede ver sus propias órdenes
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json($orders, 200);
    }

    /**
     * Actualizar el estado de la orden (Ej. Pasar de pendiente a completado) - Solo Admin
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pendiente,pagado,preparando,entregado,cancelado'
        ]);

        if ($validator->fails()) {
            return response()->json(['errores' => $validator->errors()], 422);
        }

        $order = Order::find($id);
        
        if (!$order) return response()->json(['message' => 'Orden no encontrada'], 404);

        $order->status = $request->status;
        $order->save();

        return response()->json([
            'status' => 'exito',
            'mensaje' => 'Estado de la orden actualizado',
            'orden' => $order
        ], 200);
    }
}