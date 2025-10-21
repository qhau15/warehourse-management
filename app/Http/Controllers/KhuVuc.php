<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KhuVuc extends Controller
{
    // Lấy danh sách KhuVuc
    public function index(Request $request)
    {
        $page = max((int) $request->query('page', 1), 1);
        $perPage = (int) $request->query('per_page', 10);

        $MaKhuVuc = $request->query('MaKhuVuc');
        $TenKhuVuc = $request->query('TenKhuVuc');

        // Gọi SP, lấy nhiều hơn perPage để tránh thiếu dữ liệu
        $all = DB::select('EXEC sp_GetKhuVuc @MaKhuVuc = ?, @TenKhuVuc = ?, @Limit = ?', [
            $MaKhuVuc,
            $TenKhuVuc,
            1000
        ]);

        // Lọc IsDeleted = 0
        $all = array_filter($all, fn($kv) => $kv->IsDeleted == 0);

        $total = count($all);
        $items = array_slice($all, ($page-1)*$perPage, $perPage);

        return response()->json([
            'status' => 'success',
            'data' => array_values($items),
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => ceil($total / $perPage)
            ]
        ]);
    }

    // Cập nhật KhuVuc
    public function update(Request $request, $MaKhuVuc)
    {
        $validator = Validator::make($request->all(), [
            'TenKhuVuc' => 'required|string|max:50'
        ], [
            'TenKhuVuc.required' => 'Tên khu vực là bắt buộc.',
            'TenKhuVuc.string'   => 'Tên khu vực phải là chuỗi ký tự.',
            'TenKhuVuc.max'      => 'Tên khu vực tối đa 50 ký tự.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = DB::table('KhuVuc')
            ->where('MaKhuVuc', $MaKhuVuc)
            ->update([
                'TenKhuVuc' => $request->TenKhuVuc
            ]);

        if (!$updated) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khu vực không tồn tại hoặc không có gì thay đổi.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật khu vực thành công.'
        ]);
    }

    // Xóa mềm KhuVuc
    public function destroy($MaKhuVuc)
    {
        $deleted = DB::table('KhuVuc')
            ->where('MaKhuVuc', $MaKhuVuc)
            ->update([
                'IsDeleted' => 1,
                'DeletedAt' => now()
            ]);

        if (!$deleted) {
            return response()->json([
                'status' => 'error',
                'message' => 'Khu vực không tồn tại.'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Xóa khu vực thành công.'
        ]);
    }
}
