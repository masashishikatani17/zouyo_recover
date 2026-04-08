<?php
namespace Database\Seeders;

use App\Models\ZouyoGeneralRate;
use Illuminate\Database\Seeder;

class ZouyoGeneralRateSeeder extends Seeder
{
    public function run(): void
    {
        // ★税率は2026年以降固定で共通利用するため、kihu_yearは固定にする
        $year = 2026;
        $version = 1;
        $rows = [
            // seq, lower, upper, rate%, deduction
            [1,       0,      2_000_000, 10.000,       0],
            [2, 2_000_001,    3_000_000, 15.000,  100_000],
            [3, 3_000_001,    4_000_000, 20.000,  250_000],
            [4, 4_000_001,    6_000_000, 30.000,  650_000],
            [5, 6_000_001,   10_000_000, 40.000,1_250_000],
            [6,10_000_001,   15_000_000, 45.000,1_750_000],
            [7,15_000_001,   30_000_000, 50.000,2_500_000],
            [8,30_000_001,            null,55.000,4_000_000],
        ];
        foreach ($rows as [$seq,$lower,$upper,$rate,$ded]) {
            ZouyoGeneralRate::updateOrCreate(
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
