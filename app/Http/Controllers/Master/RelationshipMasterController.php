<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Services\Master\RelationshipMasterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RelationshipMasterController extends Controller
{
    public function edit(Request $request, RelationshipMasterService $service): View
    {
        $companyId = $this->companyIdOrFail($request);

        return view('master.relationships.edit', [
            'rows'   => $service->getAll($companyId),
            'dataId' => $request->input('data_id'),            
        ]);
        
        
    }

    public function update(Request $request, RelationshipMasterService $service): RedirectResponse
    {
        $companyId = $this->companyIdOrFail($request);

        $validated = $request->validate($this->rules(), $this->messages());

        $service->saveEditable($companyId, $validated['relations'] ?? []);

        return redirect()
            ->route('zouyo.master', array_filter([
                'data_id' => $request->input('data_id'),
            ]));


    }

    /**
     * @return array<string, array<int, string>>
     */
    private function rules(): array
    {
        $rules = [
            'data_id'   => ['nullable', 'integer'],            
            'relations' => ['array'],
        ];

        foreach (range(42, 50) as $no) {
            $rules["relations.{$no}.name"] = $no <= 45
                ? ['bail', 'required', 'string', 'max:50']
                : ['bail', 'nullable', 'string', 'max:50'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        $messages = [];

        foreach (range(42, 45) as $no) {
            $messages["relations.{$no}.name.required"] = "No{$no}は空欄にできません。";
        }

        foreach (range(42, 50) as $no) {
            $messages["relations.{$no}.name.max"] = "No{$no}は50文字以内で入力してください。";
            $messages["relations.{$no}.name.string"] = "No{$no}は文字で入力してください。";
        }

        return $messages;
    }

    private function companyIdOrFail(Request $request): int
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        abort_if($companyId <= 0, 403);

        return $companyId;
    }
}