<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('promotion_product', function (Blueprint $table) {
    $table->id();
    $table->foreignId('promotion_id')->constrained('promotions')->onDelete('cascade');
    $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
    
    // NUEVO: ¿Cuántos productos de este tipo necesito agregar al carrito para activar la promo?
    $table->integer('required_quantity')->default(1); 
    
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_product');
    }
};