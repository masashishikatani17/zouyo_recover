<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('zouyo_general_rates', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedInteger('kihu_year');
            $table->unsignedInteger('version');
            $table->unsignedInteger('seq');
            $table->unsignedBigInteger('lower');
            $table->unsignedBigInteger('upper')->nullable();
            $table->decimal('rate', 6, 3);
            $table->unsignedBigInteger('deduction_amount');
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id','kihu_year','version','seq'],'zouyo_general_rates_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('zouyo_general_rates');
    }
};
