<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ChartSetting;
use Auth;
use Illuminate\Http\Request;

class ChartSettingController extends Controller
{
    private const SCOPES = ['board', 'admin'];

    public function show(string $scope)
    {
        abort_unless(in_array($scope, self::SCOPES, true), 404);

        $row = ChartSetting::where('user_id', Auth::id())->where('scope', $scope)->first();

        return response()->json(['settings' => $row?->settings]);
    }

    public function update(Request $request, string $scope)
    {
        abort_unless(in_array($scope, self::SCOPES, true), 404);

        $data = $request->validate([
            'settings' => 'required|array',
        ]);

        ChartSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'scope' => $scope],
            ['settings' => $data['settings']],
        );

        return response()->json(['success' => true]);
    }
}
