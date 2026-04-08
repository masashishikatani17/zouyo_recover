<?php

namespace App\Providers;

use App\Models\PastGiftRecipient;
use App\Observers\PastGiftRecipientObserver;

use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\URL;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Auth;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        // PastGiftRecipient の誤 create を強制検知
        PastGiftRecipient::observe(PastGiftRecipientObserver::class);

        // ★ 本番のみ https を強制（ローカル/C9は無効にして起動不能を防ぐ）
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        if (config('app.debug')) {
            DB::listen(function ($query) {
                $sql = strtolower($query->sql ?? '');
                if (str_contains($sql, 'insert into `past_gift_recipients`')) {
                    $trace = collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20))
                        ->map(fn($t,$i)=>sprintf('#%d %s:%s', $i, $t['file']??'?', $t['line']??'?'))
                        ->implode("\n");
                    Log::warning('[SQL-TRACE] insert into past_gift_recipients', [
                        'sql'       => $query->sql,
                        'bindings'  => $query->bindings,
                        'trace'     => $trace,
                    ]);
                }
            });
        }


        // ★ Cloud9ローカル等で未ログインでも通せるようにする（任意）
        //   ※ composer install 中（CLI）に package:discover が走るため、
        //     ここで DB に触ると migrate 前に落ちる。HTTPリクエスト時だけに限定する。
        if (app()->isLocal() && !app()->runningInConsole()) {
            try {
                if (!Auth::check()) {
                    // users テーブルが存在し、id=1 がいる場合のみ擬似ログイン
                    if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
                        $exists = \Illuminate\Support\Facades\DB::table('users')->where('id', 1)->exists();
                        if ($exists) {
                        Auth::loginUsingId(1);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // DB未準備・接続不可などは無害化（ローカル起動を優先）
            }
        }

        // Cloud9/AWS ELB等のプロキシ配下で http→https へ正しく昇格させる
        // APP_URL が https なら無条件、あるいは実際のリクエストが https と判定された場合に強制
        try {
            $appUrl = (string) config('app.url');
            if (Str::startsWith($appUrl, 'https://') || request()->isSecure()) {
                URL::forceScheme('https');
            }
        } catch (\Throwable $e) {
            // 起動直後のCLI等で request() が無いケースを無害化
        }
    
        
        
        
        
    }

}

