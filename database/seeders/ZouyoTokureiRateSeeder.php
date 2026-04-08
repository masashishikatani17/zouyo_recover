<?php
namespace Database\Seeders;

use App\Models\ZouyoTokureiRate;
use Illuminate\Database\Seeder;

class ZouyoTokureiRateSeeder extends Seeder
{
    public function run(): void
    {
        // ★税率は2026年以降固定で共通利用するため、kihu_yearは固定にする
        $year = 2026;
        $version = 1;
        $rows = [
            [1,       0,      2_000_000, 10.000,       0],
            [2, 2_000_001,    4_000_000, 15.000,  100_000],
            [3, 4_000_001,    6_000_000, 20.000,  300_000],
            [4, 6_000_001,   10_000_000, 30.000,  900_000],
            [5,10_000_001,   15_000_000, 40.000,1_900_000],
            [6,15_000_001,   30_000_000, 45.000,2_650_000],
            [7,30_000_001,   45_000_000, 50.000,4_150_000],
            [8,45_000_001,            null,55.000,6_400_000],
        ];
        foreach ($rows as [$seq,$lower,$upper,$rate,$ded]) {
            ZouyoTokureiRate::updateOrCreate(
                [
                    'company_id' => null,
                    'kihu_year'  => $year,
                    'version'    => $version,
                    'seq'        => $seq,
                ],
                [
                    'lower'            => $lower,
                    'upper'            => $upper,
                    'rate'             => $rate,
                    'deduction_amount' => $ded,
                    'note'             => null,
                ]
            );
        }
    }
}
