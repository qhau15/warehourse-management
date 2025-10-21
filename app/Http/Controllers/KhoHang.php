<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class KhoHang extends Controller
{
    // Lấy danh sách kho hàng theo role
    public function getKhoHang(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1; // role 1 = Bắc, 2 = Trung, 3 = Nam
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;
        $table = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };
        $MaKho = $request->query('MaKho');
        $TenKho = $request->query('TenKho');

        $query = DB::table($table)->where('IsDeleted', 0);

        if ($MaKho) $query->where('MaKho', $MaKho);
        if ($TenKho) $query->where('TenKho', 'like', "%$TenKho%");

        $total = $query->count();
        $data = $query->offset($offset)->limit($limit)->get();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'page' => $page,
            'per_page' => $limit,
            'total' => $total
        ]);
    }

    // Thêm kho hàng
    public function addKhoHang(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        $table = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };

        $validator = Validator::make($request->all(), [
            'MaKho' => "required|integer|unique:$table,MaKho",
            'TenKho' => "required|string|max:100|unique:$table,TenKho",
            'DiaDiem' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::table($table)->insert([
            'MaKho' => $request->MaKho,
            'TenKho' => $request->TenKho,
            'DiaDiem' => $request->DiaDiem,
            'IsDeleted' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm kho hàng thành công'
        ]);
    }

    // Cập nhật kho hàng
    public function updateKhoHang(Request $request, $MaKho)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        $table = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };

        // Kiểm tra tồn tại MaKho
        $exists = DB::table($table)->where('MaKho', $MaKho)->where('MaKhuVuc',(int)$role)->exists();
        if (!$exists) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'MaKho' => ['Mã kho không tồn tại.']
                ]
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'TenKho' => "required|string|max:100|unique:$table,TenKho,$MaKho,MaKho",
            'DiaDiem' => 'nullable|string|max:255'
        ], [
            'TenKho.required' => 'Tên kho không được để trống.',
            'TenKho.string' => 'Tên kho phải là chuỗi ký tự.',
            'TenKho.max' => 'Tên kho tối đa 100 ký tự.',
            'TenKho.unique' => 'Tên kho đã tồn tại trong khu vực này.',
            'DiaDiem.string' => 'Địa điểm phải là chuỗi ký tự.',
            'DiaDiem.max' => 'Địa điểm tối đa 255 ký tự.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::table($table)->where('MaKho', $MaKho)->update([
            'TenKho' => $request->TenKho,
            'DiaDiem' => $request->DiaDiem
        ]);

        DB::table('KhoHang')->where('MaKho', $MaKho)->update([
            'TenKho' => $request->TenKho,
            'DiaDiem' => $request->DiaDiem
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật kho hàng thành công'
        ]);
    }


    // Xóa kho hàng
    public function deleteKhoHang($MaKho)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        $table = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };

        // Kiểm tra kho có thuộc khu vực của user không
        $exists = DB::table($table)
            ->where('MaKho', $MaKho)
            ->where('MaKhuVuc', $role)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'error',
                'errors' => [
                    'MaKho' => ['Kho hàng không tồn tại trong khu vực của bạn.']
                ]
            ], 404);
        }

        DB::table($table)->where('MaKho', $MaKho)
            ->where('MaKhuVuc', $role)
            ->update([
                'IsDeleted' => 1,
                'DeletedAt' => Carbon::now()
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đánh dấu kho hàng là xóa'
        ]);
    }


    // Lấy toàn bộ kho hàng qua procedure, chỉ admin (role = 0)
    public function getAllKhoHang(Request $request)
    {
        $user = Auth::user();
        if (($user->role ?? 1) != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ admin mới được phép truy cập'
            ], 403);
        }

        $MaKho = $request->query('MaKho');
        $TenKho = $request->query('TenKho');
        $MaKhuVuc = $request->query('MaKhuVuc');
        $Limit = $request->query('per_page', 100);

        $data = DB::select('EXEC sp_GetKhoHang @MaKho = ?, @TenKho = ?, @MaKhuVuc = ?, @Limit = ?', [
            $MaKho,
            $TenKho,
            $MaKhuVuc,
            $Limit
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

}
