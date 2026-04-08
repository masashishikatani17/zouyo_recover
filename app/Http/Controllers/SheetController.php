<?php


// app/Http/Controllers/SheetController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SheetController extends Controller
{
    public function submitSheet013(Request $request)
    {
        // バリデーションや保存処理を書く（例）
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'proposal_title' => 'nullable|string|max:255',
            'advisor_name' => 'nullable|string|max:255',
            'proposal_date' => 'nullable|date',
        ]);

        // 保存処理するならここに記述（DBに入れるならモデル使う）
        // 例：Proposal::create($validated);

        /*
        return redirect()->back()->with('success', '提案書データを保存しました！');
        */
        return redirect()->back();

    }

    
    public function submitSheet014(Request $request)
    {
        // バリデーションや保存処理を書く（例）
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'proposal_title' => 'nullable|string|max:255',
            'advisor_name' => 'nullable|string|max:255',
            'proposal_date' => 'nullable|date',
        ]);

        // 保存処理するならここに記述（DBに入れるならモデル使う）
        // 例：Proposal::create($validated);

        /*
        return redirect()->back()->with('success', '提案書データを保存しました！');
        */
        return redirect()->back();
    }



    public function submitSheet015(Request $request)
    {
        // バリデーションや保存処理を書く（例）
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'proposal_title' => 'nullable|string|max:255',
            'advisor_name' => 'nullable|string|max:255',
            'proposal_date' => 'nullable|date',
        ]);

        // 保存処理するならここに記述（DBに入れるならモデル使う）
        // 例：Proposal::create($validated);

        /*
        return redirect()->back()->with('success', '提案書データを保存しました！');
        */
        return redirect()->back();
    }

    
    
    public function submitSheet016(Request $request)
    {
        // バリデーションや保存処理を書く（例）
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'proposal_title' => 'nullable|string|max:255',
            'advisor_name' => 'nullable|string|max:255',
            'proposal_date' => 'nullable|date',
        ]);

        // 保存処理するならここに記述（DBに入れるならモデル使う）
        // 例：Proposal::create($validated);

        /*
        return redirect()->back()->with('success', '提案書データを保存しました！');
        */
        return redirect()->back();
    }
    
}
