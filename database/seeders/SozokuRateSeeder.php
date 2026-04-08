<?php
namespace Database\Seeders;

use App\Models\SozokuRate;
use Illuminate\Database\Seeder;

class SozokuRateSeeder extends Seeder
{
    public function run(): void
    {
        // ★税率は2026年以降固定で共通利用するため、kihu_yearは固定にする
        $year = 2026;
        $version = 1;
        $rows = [
            [1,        0,     10_000_000, 10.000,        0],
            [2, 10_000_001,   30_000_000, 15.000,  500_000],
            [3, 30_000_001,   50_000_000, 20.000,2_000_000],
            [4, 50_000_001,  100_000_000, 30.000,7_000_000],
            [5,100_000_001,  200_000_000, 40.000,17_000_000],
            [6,200_000_001,  300_000_000, 45.000,27_000_000],
            [7,300_000_001,  600_000_000, 50.000,42_000_000],
            [8,600_000_001,            null,55.000,72_000_000],
        ];
        foreach ($rows as [$seq,$lower,$upper,$rate,$ded]) {
            SozokuRate::updateOrCreate(
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
