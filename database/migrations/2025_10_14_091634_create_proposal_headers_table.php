 <?php
  
  use Illuminate\Database\Migrations\Migration;
  use Illuminate\Database\Schema\Blueprint;
  use Illuminate\Support\Facades\Schema;

 return new class extends Migration
 {
       public function up(): void
       {
  
        // このファイルは proposal_headers / proposal_family_members を先に作成します。
        // 親 'datas' が未整備でも通るよう、FKは“後付け”にします（別マイグレーションを用意）。
  
         Schema::create('proposal_headers', function (Blueprint $table) {
             $table->engine = 'InnoDB';               // FK安定化（MariaDB 10.5）
             $table->id();                             // BIGINT UNSIGNED
            // ★ まずは親が無くても作れるように INT UNSIGNED で作成（後で必要に応じて型/ FKを調整）
            $table->unsignedInteger('data_id');
             $table->index('data_id');
             $table->timestamps();
  
             // 1:1 を保証
             $table->unique('data_id');
  
            // ※ 外部キーは後続マイグレーションで追加します
         });
  
  
         /**
         * 提案書の家族・資産明細（1:N）
         * 行番号 row_no = 1..10 を保存
         */
         Schema::create('proposal_family_members', function (Blueprint $table) {
             $table->engine = 'InnoDB';
             $table->id();
            // ★ こちらもまずは INT UNSIGNED で作成
            $table->unsignedInteger('data_id');
             $table->index('data_id');
             $table->unsignedTinyInteger('row_no'); // 1..10

            // 氏名・性別・続柄ほか
            $table->string('name', 100)->nullable();
            $table->string('gender', 2)->nullable(); // '男' / '女'
            // 続柄はコード保存（UIの $relationships 配列のキーを想定）
            $table->unsignedTinyInteger('relationship_code')->nullable(); // 0..41

            // 養子縁組（任意文字列）
            $table->string('adoption_note', 100)->nullable();

            // 相続人区分：0=被相続人,1=法定相続人,2=法定相続人以外
            $table->unsignedTinyInteger('heir_category')->nullable();

            // 法定相続割合（分子・分母）
            $table->unsignedSmallInteger('share_numerator')->nullable();
            $table->unsignedSmallInteger('share_denominator')->nullable();

            // 2割加算・特例贈与（チェックボックス）
            $table->boolean('surcharge_twenty_percent')->default(false);
            $table->boolean('tokurei_zouyo')->default(false);

            // 生年月日（年・月・日）と年齢
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedTinyInteger('birth_month')->nullable();
            $table->unsignedTinyInteger('birth_day')->nullable();
            $table->unsignedTinyInteger('age')->nullable();

            // 金額（千円単位）
            $table->unsignedBigInteger('property_thousand')->nullable();
            $table->unsignedBigInteger('cash_thousand')->nullable();

            $table->timestamps();


             // 同一 data_id で row_no の重複を禁止（1データ内の行一意）
             $table->unique(['data_id', 'row_no']);
             $table->index(['relationship_code', 'heir_category']);
             

         });
  
     }





       public function down(): void
       {
           Schema::dropIfExists('proposal_family_members');
           Schema::dropIfExists('proposal_headers');
       }
  
  
  };
