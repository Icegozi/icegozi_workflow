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
            'settings.range' => 'sometimes|integer|min:1|max:366',
            'settings.charts' => 'sometimes|array|max:50',
        ]);

        // Chặn payload phình to (settings chỉ là cấu hình UI nhỏ).
        abort_if(strlen(json_encode($data['settings'])) > 8000, 422, 'Thiết lập quá lớn.');

        ChartSetting::updateOrCreate(
            ['user_id' => Auth::id(), 'scope' => $scope],
            ['settings' => $data['settings']],
        );

        return response()->json(['success' => true]);
    }
}
