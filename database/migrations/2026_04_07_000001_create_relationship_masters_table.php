<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationship_masters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedInteger('relation_no');
            $table->string('name', 50)->nullable();
            $table->boolean('is_editable')->default(false);
            $table->timestamps();

            $table->unique(
                ['company_id', 'relation_no'],
                'relationship_masters_company_relation_unique'
            );
            $table->index(
                ['company_id', 'is_editable'],
                'relationship_masters_company_editable_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_masters');
    }
};