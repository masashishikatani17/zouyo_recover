<?php

namespace App\Http\Controllers\Tax;

use App\Http\Controllers\Controller;
use App\Models\Data;
use App\Models\Guest;
use App\Services\Zouyo\CopyZouyoDataService;
use App\Services\Zouyo\DeleteZouyoDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;


class ZouyoDataController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $this->resolveCompanyId();

        $guests = Guest::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $requestedDataId = (int) $request->query('data_id', 0);
        $selectedGuestId = (int) $request->query('guest_id', 0);
        

        if ($selectedGuestId <= 0 && $requestedDataId > 0) {
            $selectedData = Data::query()
                ->where('company_id', $companyId)
                ->find($requestedDataId);

            if ($selectedData) {
                $guestFromData = $guests->firstWhere('group_id', $selectedData->group_id);
                if ($guestFromData) {
                    $selectedGuestId = (int) $guestFromData->id;
                }
            }
        }
        
        
        if ($selectedGuestId <= 0 && $guests->isNotEmpty()) {
            $selectedGuestId = (int) $guests->first()->id;
        }

        $selectedGuest = $guests->firstWhere('id', $selectedGuestId);

        $datasQuery = Data::query()
            ->with('guest')
            ->where('company_id', $companyId);

        if ($selectedGuest) {
            $datasQuery->where('group_id', $selectedGuest->group_id);
        } else {
            $datasQuery->whereRaw('1 = 0');
        }

        $datas = $datasQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();



        $selectedDataId = $requestedDataId;

        if ($selectedDataId <= 0 && $datas->isNotEmpty()) {
            $selectedDataId = (int) $datas->first()->id;
        }

        return view('zouyo.data.index', [
            'guests' => $guests,
            'datas' => $datas,
            'selectedGuestId' => $selectedGuestId,
            'selectedGuest' => $selectedGuest,
            'selectedDataId' => $selectedDataId,
        ]);
    }

    public function create(Request $request): View
    {
        $companyId = $this->resolveCompanyId();

        $guests = Guest::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $selectedGuestId = (int) $request->query('guest_id', 0);
        $selectedGuest = $guests->firstWhere('id', $selectedGuestId);

        return view('zouyo.data.create', [
            'guests' => $guests,
            'selectedGuest' => $selectedGuest,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = $this->resolveCompanyId();

        $validated = $request->validate([
            'guest_mode' => ['required', 'in:existing,new'],
            'guest_id' => ['nullable', 'integer'],
            'guest_name' => ['nullable', 'string', 'max:25'],
            'kihu_year' => ['required', 'integer', 'between:2025,2035'],
            'data_name' => ['required', 'string', 'max:25'],
        ]);

        $guestMode = (string) $validated['guest_mode'];
        $dataName = trim((string) $validated['data_name']);

        if ($dataName === '') {
            return back()->withErrors(['data_name' => 'データ名を入力してください。'])->withInput();
        }

        if ($guestMode === 'existing') {
            $guest = Guest::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) ($validated['guest_id'] ?? 0));
        } else {
            $guestName = trim((string) ($validated['guest_name'] ?? ''));
            if ($guestName === '') {
                return back()->withErrors(['guest_name' => 'お客様名を入力してください。'])->withInput();
            }

            $guest = new Guest();
            $guest->company_id = $companyId;
            $guest->group_id = $this->nextGroupId();
            $guest->name = $guestName;
            $guest->save();
        }

        $exists = Data::query()
            ->where('company_id', $companyId)
            ->where('group_id', $guest->group_id)
            ->where('kihu_year', (int) $validated['kihu_year'])
            ->where('data_name', $dataName)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'data_name' => '同じお客様・同じ年度・同じデータ名のデータは作成できません。',
            ])->withInput();
        }

        $data = new Data();
        $data->company_id = $companyId;
        $data->group_id = $guest->group_id;
        $data->kihu_year = (int) $validated['kihu_year'];
        $data->data_name = $dataName;
        $data->save();

        /*
        return redirect()->route('zouyo.data.index', [
            'guest_id' => $guest->id,
            'data_id' => $data->id,
        ])->with('success', '新規データを作成しました。');
        */


        return redirect()->route('zouyo.data.index', [
            'guest_id' => $guest->id,
            'data_id' => $data->id,
        ]);

    }




    public function edit(Request $request): View
    {
        $companyId = $this->resolveCompanyId();

        $dataId = (int) $request->query('data_id', 0);
        abort_unless($dataId > 0, 422, '編集対象データが指定されていません。');

        $data = Data::query()
            ->with('guest')
            ->where('company_id', $companyId)
            ->findOrFail($dataId);

        return view('zouyo.data.edit', [
            'data' => $data,
            'guest' => $data->guest,
            'years' => range(2035, 2025),
            'canDelete' => true,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $companyId = $this->resolveCompanyId();

        $validated = $request->validate([
            'data_id'    => ['required', 'integer'],
            'guest_name' => ['required', 'string', 'max:50'],
            'kihu_year'  => ['required', 'integer'],
            'data_name'  => ['required', 'string', 'max:25'],
        ]);

        $data = Data::query()
            ->with('guest')
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['data_id']);

        $dataName = trim((string) $validated['data_name']);
        if ($dataName === '') {
            return back()->withErrors(['data_name' => 'データ名を入力してください。'])->withInput();
        }



        $guestName = trim((string) $validated['guest_name']);
        if ($guestName === '') {
            return back()->withErrors(['guest_name' => 'お客様名を入力してください。'])->withInput();
        }


        $exists = Data::query()
            ->where('company_id', $companyId)
            ->where('group_id', $data->group_id)
            ->where('kihu_year', (int) $validated['kihu_year'])
            ->where('data_name', $dataName)
            ->where('id', '!=', $data->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'data_name' => '同じお客様・同じ年度・同じデータ名のデータは登録できません。',
            ])->withInput();
        }

        DB::transaction(function () use ($validated, $data, $dataName, $guestName) {            
            if ($data->guest) {
                $data->guest->name = $guestName;                
                $data->guest->save();
            }

            $data->kihu_year = (int) $validated['kihu_year'];
            $data->data_name = $dataName;
            $data->save();
        });

        /*
        return redirect()->route('zouyo.data.index', [
            'guest_id' => $data->guest?->id,
            'data_id' => $data->id,
        ])->with('success', 'データを更新しました。');
        */


        return redirect()->route('zouyo.data.index', [
            'guest_id' => $data->guest?->id,
            'data_id' => $data->id,
        ]);

    }




    public function copyForm(Request $request): View
    {
        $companyId = $this->resolveCompanyId();

        $selectedDataId = (int) $request->query('selected_data_id', 0);
        abort_unless($selectedDataId > 0, 422, 'コピー元データが指定されていません。');

        $source = Data::query()
            ->with('guest')
            ->where('company_id', $companyId)
            ->findOrFail($selectedDataId);

        $guests = Guest::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('zouyo.data.copy', [
            'source' => $source,
            'guests' => $guests,
            'suggestedCopyName' => ($source->data_name ?? 'default') . '_コピー',
        ]);
    }

    public function copy(Request $request, CopyZouyoDataService $copyService): RedirectResponse
    {
        $companyId = $this->resolveCompanyId();

        $validated = $request->validate([
            'selected_data_id' => ['required', 'integer'],
            'copy_mode' => ['required', 'in:same,existing,new'],
            'target_guest_id' => ['nullable', 'integer'],
            'target_guest_name' => ['nullable', 'string', 'max:25'],
            'kihu_year' => ['required', 'integer', 'between:2025,2035'],
            'data_name' => ['required', 'string', 'max:25'],
        ]);

        $source = Data::query()
            ->with('guest')
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['selected_data_id']);

        $copyMode = (string) $validated['copy_mode'];
        $dataName = trim((string) $validated['data_name']);

        if ($dataName === '') {
            return back()->withErrors(['data_name' => 'データ名を入力してください。'])->withInput();
        }

        if ($copyMode === 'same') {
            $targetGuest = Guest::query()
                ->where('company_id', $companyId)
                ->where('group_id', $source->group_id)
                ->firstOrFail();
        } elseif ($copyMode === 'existing') {
            $targetGuest = Guest::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) ($validated['target_guest_id'] ?? 0));
        } else {
            $guestName = trim((string) ($validated['target_guest_name'] ?? ''));
            if ($guestName === '') {
                return back()->withErrors(['target_guest_name' => 'コピー先のお客様名を入力してください。'])->withInput();
            }

            $targetGuest = new Guest();
            $targetGuest->company_id = $companyId;
            $targetGuest->group_id = $this->nextGroupId();
            $targetGuest->name = $guestName;
            $targetGuest->save();
        }

        $exists = Data::query()
            ->where('company_id', $companyId)
            ->where('group_id', $targetGuest->group_id)
            ->where('kihu_year', (int) $validated['kihu_year'])
            ->where('data_name', $dataName)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'data_name' => '同じお客様・同じ年度・同じデータ名のデータはコピーできません。',
            ])->withInput();
        }

        $newData = $copyService->copy(
            (int) $validated['selected_data_id'],
            $companyId,
            (int) $targetGuest->group_id,
            (int) $validated['kihu_year'],
            $dataName
        );

        /*
        return redirect()->route('zouyo.data.index', [
            'guest_id' => $targetGuest->id,
            'data_id' => $newData->id,
        ])->with('success', '既存データをコピーしました。');
        */


        return redirect()->route('zouyo.data.index', [
            'guest_id' => $targetGuest->id,
            'data_id' => $newData->id,
        ]);

    }



    public function destroyData(Request $request, DeleteZouyoDataService $deleteService): RedirectResponse
    {
        $companyId = $this->resolveCompanyId();

        $validated = $request->validate([
            'data_id' => ['required', 'integer'],
        ]);

        $data = Data::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['data_id']);

        $guest = Guest::query()
            ->where('company_id', $companyId)
            ->where('group_id', $data->group_id)
            ->first();

        $deleteService->deleteData($data);

        $nextData = Data::query()
            ->where('company_id', $companyId)
            ->where('group_id', $guest?->group_id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
        
        /*
        return redirect()->route('zouyo.data.index', array_filter([
            'guest_id' => $guest?->id,
            'data_id' => $nextData?->id,
        ]))->with('success', 'データ名を削除しました。');
        */

        return redirect()->route('zouyo.data.index', array_filter([
            'guest_id' => $guest?->id,
            'data_id' => $nextData?->id,
        ]));


    }

    public function destroyGuest(Request $request, DeleteZouyoDataService $deleteService): RedirectResponse
    {
        $companyId = $this->resolveCompanyId();

        $validated = $request->validate([
            'guest_id' => ['required', 'integer'],
        ]);

        $guest = Guest::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['guest_id']);

        $deleteService->deleteGuestAll($guest);

        $nextGuest = Guest::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->orderBy('id')
            ->first();

        /*
        return redirect()->route('zouyo.data.index', array_filter([
            'guest_id' => $nextGuest?->id,
        ]))->with('success', 'お客様データを削除しました。');
        */


        return redirect()->route('zouyo.data.index', array_filter([
            'guest_id' => $nextGuest?->id,
        ]));


    }



    protected function resolveCompanyId(): int
    {
        $user = Auth::user();
        $companyId = (int) ($user->company_id ?? 0);

        abort_unless($companyId > 0, 403, '会社情報を取得できません。');

        return $companyId;
    }

    protected function nextGroupId(): int
    {
        $guestMax = (int) Guest::query()->max('group_id');
        $dataMax = (int) Data::query()->max('group_id');

        return max($guestMax, $dataMax) + 1;
    }
}

