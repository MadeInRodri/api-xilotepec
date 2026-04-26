<?php
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PromotionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Controllers
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentMethodController;


//Middleware
use App\Http\Middleware\IsUserAdmin;
use App\Http\Middleware\IsUserAuth;

// =========================================================
// RUTAS PÚBLICAS (No requieren autenticación)
// =========================================================

// Entrada de API de Usuarios
Route::post('/registro', [UserController::class, 'store']);
Route::post('/login', [UserController::class, 'login']);


// Catálogo de Productos y Categorías
Route::get('/productos', [ProductController::class, 'index']);
Route::get('/productos/{id}', [ProductController::class, 'show']);

Route::get('/categorias', [CategoryController::class, 'index']);
Route::get('/categorias/{id}', [CategoryController::class, 'show']);

// Metodos de pago
Route::get('/metodos-pago', [PaymentMethodController::class, 'index']);

//Promociones
Route::get('/promociones', [PromotionController::class, 'index']);
Route::get('/promociones/{id}', [PromotionController::class, 'show']);



// =========================================================
// RUTAS PROTEGIDAS (Requieren Token JWT válido)
// =========================================================
Route::middleware([IsUserAuth::class])->group(function (){
    
    // --- Acciones de Usuarios Autenticados ---
    Route::get('/usuarios', [UserController::class, 'index']);
    Route::get('/usuarios/{id}', [UserController::class, 'show']);
    Route::get('/logout', [UserController::class, 'logout']);

    //Ordenes
    Route::post('/ordenes', [OrderController::class, 'store']);
    Route::get('/ordenes', [OrderController::class, 'index']);

    // --- Acciones Exclusivas del Administrador ---
    // Para acceder aquí, la petición debe tener el Token JWT Y el rol adecuado
    Route::middleware([IsUserAdmin::class])->group(function (){
        
        // Gestión de Categorías
        Route::post('/categorias', [CategoryController::class, 'store']);
        Route::patch('/categorias/{id}', [CategoryController::class, 'update']);
        Route::delete('/categorias/{id}', [CategoryController::class, 'destroy']);

        // Gestión de Usuarios
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
        Route::patch('/usuarios/{id}', [UserController::class, 'update']);
        
        // Gestión de Productos
        Route::post('/productos', [ProductController::class, 'store']);
        Route::patch('/productos/{id}', [ProductController::class, 'update']);
        Route::delete('/productos/{id}', [ProductController::class, 'destroy']);

        // Gestión de Metodos de pago
        Route::post('/metodos-pago', [PaymentMethodController::class, 'store']);
        Route::patch('/metodos-pago/{id}', [PaymentMethodController::class, 'update']);
        Route::delete('/metodos-pago/{id}', [PaymentMethodController::class, 'destroy']);

        //Gestión de las promociones
        Route::post('/promociones', [PromotionController::class, 'store']);
        Route::patch('/promociones/{id}', [PromotionController::class, 'update']);
        Route::delete('/promociones/{id}', [PromotionController::class, 'destroy']);
        
        // Ruta para gestionar los productos de la promoción (Combos/Descuentos)
        Route::post('/promociones/{id}/productos', [PromotionController::class, 'syncProducts']);

        //Cambiar estado de una orden
        Route::patch('/ordenes/{id}/estado', [OrderController::class, 'updateStatus']);
    });
});