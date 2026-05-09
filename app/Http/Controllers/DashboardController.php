<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\Cache;
class DashboardController extends Controller
{
    //Encargado de cargar Ordenes en este sistema:
    //Los va a categorizar por hoy
    //Los ultimos 7 dias
    //Mensuales
    //Tambien va a cargar las promociones Activas
    //El estado de las ordenes (cantidad) 

   
    public function index()
    {
        return Cache::remember('dashboard_data', 60, function () {

            $now = now();
            $today = $now->copy()->startOfDay();
            $lastWeek = $now->copy()->subDays(7)->startOfDay();
            $lastMonth = $now->copy()->subDays(30)->startOfDay();
            $year = $now->copy()->startOfYear();

            // =========================
            // 🔹 SUMMARY (HOY)
            // =========================
            $summary = Order::selectRaw("
                    COUNT(*) as orders_count,
                    SUM(CASE WHEN status IN ('pendiente','en_proceso') THEN 1 ELSE 0 END) as orders_active,
                    COALESCE(SUM(total),0) as total_revenue
                ")
                ->where('date', '>=', $today)
                ->first();

            // =========================
            // 🔹 CATEGORÍAS CON VENTAS
            // =========================
            $categories = DB::table('categories as c')
                ->leftJoin('products as p', 'p.category_id', '=', 'c.id')
                ->leftJoin('order_details as od', 'od.product_id', '=', 'p.id')
                ->leftJoin('orders as o', 'od.order_id', '=', 'o.id')
                ->selectRaw('
                    c.id,
                    c.name,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as today_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as today_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as week_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as week_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as month_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as month_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as year_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as year_revenue
                ', [
                    $today, $today,
                    $lastWeek, $lastWeek,
                    $lastMonth, $lastMonth,
                    $year, $year
                ])
                ->groupBy('c.id', 'c.name')
                ->orderByDesc('year_quantity') 
                ->orderBy('c.name')
                ->limit(6)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sales' => [
                        'hoy' => [
                            'quantity' => (int) $item->today_quantity,
                            'revenue' => (float) $item->today_revenue,
                        ],
                        'semana anterior' => [
                            'quantity' => (int) $item->week_quantity,
                            'revenue' => (float) $item->week_revenue,
                        ],
                        'mes anterior' => [
                            'quantity' => (int) $item->month_quantity,
                            'revenue' => (float) $item->month_revenue,
                        ],
                        'año' => [
                            'quantity' => (int) $item->year_quantity,
                            'revenue' => (float) $item->year_revenue,
                        ]
                    ]
                ]);

            // =========================
            // 🔹 PRODUCTOS CON VENTAS
            // =========================
            $products = DB::table('products as p')
                ->leftJoin('order_details as od', 'od.product_id', '=', 'p.id')
                ->leftJoin('orders as o', 'od.order_id', '=', 'o.id')
                ->selectRaw('
                    p.id,
                    p.name,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as today_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as today_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as week_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as week_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as month_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as month_revenue,

                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.quantity ELSE 0 END),0) as year_quantity,
                    COALESCE(SUM(CASE WHEN o.date >= ? THEN od.subtotal ELSE 0 END),0) as year_revenue
                ', [
                    $today, $today,
                    $lastWeek, $lastWeek,
                    $lastMonth, $lastMonth,
                    $year, $year
                ])
                ->groupBy('p.id', 'p.name')
                ->orderByDesc('year_quantity') 
                ->orderBy('p.name')
                ->limit(6)
                ->get()
                ->map(fn($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'sales' => [
                        'hoy' => [
                            'quantity' => (int) $item->today_quantity,
                            'revenue' => (float) $item->today_revenue,
                        ],
                        'semana anterior' => [
                            'quantity' => (int) $item->week_quantity,
                            'revenue' => (float) $item->week_revenue,
                        ],
                        'mes anterior' => [
                            'quantity' => (int) $item->month_quantity,
                            'revenue' => (float) $item->month_revenue,
                        ],
                        'año' => [
                            'quantity' => (int) $item->year_quantity,
                            'revenue' => (float) $item->year_revenue,
                        ]
                    ]
                ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'orders_count' => (int) $summary->orders_count,
                        'orders_active' => (int) $summary->orders_active,
                        'total_revenue' => (float) $summary->total_revenue,
                    ],
                    'categories' => $categories,
                    'products' => $products, // <-- Se añade el arreglo de productos
                ],
                'meta' => [
                    'generated_at' => now(),
                ]
            ]);
        });
    }

    public function orders(Request $request)
    {
        // Se puede enviar 'today', 'last_week', 'last_month', 'year'
        $range = $request->query('range', 'today');
        $from = $request->query('from');
        $to = $request->query('to');

        [$fromDate, $toDate] = $this->resolveRange($range, $from, $to);

        // =========================
        // 🔹 AGRUPACIÓN DINÁMICA
        // =========================
        [$groupFormat, $type] = match ($range) {
            'today' => ["HOUR(date)", 'hour'],
            'last_week' => ["DATE(date)", 'day_name'], // Ej: Lunes, Martes
            'last_month' => ["DATE(date)", 'day_date'], // Ej: 15 Abr, 16 Abr
            'year' => ["MONTH(date)", 'month'], // Ej: enero, febrero
            default => ["DATE(date)", 'day_date'],
        };

        // =========================
        // 🔹 QUERY OPTIMIZADA
        // =========================
        $data = Order::selectRaw("
                {$groupFormat} as label,
                COUNT(*) as orders,
                COALESCE(SUM(total),0) as revenue
            ")
            ->whereBetween('date', [$fromDate, $toDate])
            ->groupByRaw($groupFormat)
            ->orderBy('label')
            ->get();

        // =========================
        // 🔹 FORMATEO DE RESPUESTA
        // =========================
        $formatted = $data->map(function ($item) use ($type) {

            // Label formateado según tipo
            $labelFormatted = match ($type) {
                'hour' => str_pad($item->label, 2, '0', STR_PAD_LEFT) . ':00',

                'month' => $this->getMonthName((int) $item->label),

                'day_name' => \Carbon\Carbon::parse($item->label)
                            ->locale('es')
                            ->isoFormat('dddd'), // Devuelve el día de la semana

                'day_date' => \Carbon\Carbon::parse($item->label)
                            ->locale('es')
                            ->isoFormat('D MMM'), // Devuelve Ej: 15 may
            };

            return [
                'label' => ucfirst($labelFormatted), // Capitalizamos la primera letra

                // 🔥 Mantienes dato original (clave para frontend)
                'raw' => $item->label,

                'orders' => (int) $item->orders,
                'revenue' => (float) $item->revenue,
            ];
        });

        return response()->json([
            'success' => true,
            'range' => $range,
            'from' => $fromDate,
            'to' => $toDate,
            'data' => $formatted
        ]);
    }

    private function getMonthName(int $month)
    {
        $months = [
            1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
            5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
            9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'
        ];
        return $months[$month] ?? 'desconocido';
    }

    private function resolveRange($range, $from, $to)
    {
        $now = now();

        if ($from && $to) {
            return [$from, $to];
        }

        // 🔥 Ahora estos rangos cuadran exactamente con las variables de "index"
        return match ($range) {
            'today'      => [$now->copy()->startOfDay(), $now],
            'last_week'  => [$now->copy()->subDays(7)->startOfDay(), $now],
            'last_month' => [$now->copy()->subDays(30)->startOfDay(), $now], // Cambiado a -30 días
            'year'       => [$now->copy()->startOfYear(), $now],
            default      => [$now->copy()->startOfDay(), $now],
        };
    }
}

