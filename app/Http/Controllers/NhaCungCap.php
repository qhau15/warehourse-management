<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class NhaCungCap extends Controller
{
    // Lấy danh sách nhà cung cấp có phân trang
    public function getNhaCungCap(Request $request)
    {
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        $MaNCC = $request->query('MaNCC');
        $TenNCC = $request->query('TenNCC');

        $data = DB::select('EXEC sp_GetNhaCungCap @MaNCC = ?, @TenNCC = ?, @Limit = ?', [
            $MaNCC, $TenNCC, $offset + $limit
        ]);

        // Phân trang thủ công
        $paginated = array_slice($data, $offset, $limit);

        return response()->json([
            'status' => 'success',
            'data' => $paginated,
            'page' => $page,
            'per_page' => $limit,
            'total' => count($data)
        ]);
    }

    // Thêm nhà cung cấp
    public function addNhaCungCap(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'TenNCC' => 'required|string|max:100|unique:NhaCungCap,TenNCC',
            'DiaChi' => 'nullable|string|max:255',
            'SDT'    => 'nullable|string|max:15'
        ], [
            'TenNCC.required' => 'Tên nhà cung cấp không được để trống',
            'TenNCC.unique' => 'Tên nhà cung cấp đã tồn tại'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $MaNCC = DB::table('NhaCungCap')->max('MaNCC') + 1;

        DB::table('NhaCungCap')->insert([
            'MaNCC' => $MaNCC,
            'TenNCC' => $request->TenNCC,
            'DiaChi' => $request->DiaChi,
            'SDT'    => $request->SDT,
            'IsDeleted' => 0
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm nhà cung cấp thành công',
            'MaNCC' => $MaNCC
        ]);
    }

    // Cập nhật nhà cung cấp
    public function updateNhaCungCap(Request $request, $MaNCC)
    {
        $validator = Validator::make($request->all(), [
            'TenNCC' => 'required|string|max:100|unique:NhaCungCap,TenNCC,'.$MaNCC.',MaNCC',
            'DiaChi' => 'nullable|string|max:255',
            'SDT'    => 'nullable|string|max:15'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = DB::table('NhaCungCap')->where('MaNCC', $MaNCC)->update([
            'TenNCC' => $request->TenNCC,
            'DiaChi' => $request->DiaChi,
            'SDT'    => $request->SDT
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Cập nhật nhà cung cấp thành công'
        ]);
    }

    // Xóa nhà cung cấp (chỉ đánh dấu IsDeleted)
    public function deleteNhaCungCap($MaNCC)
    {
        DB::table('NhaCungCap')->where('MaNCC', $MaNCC)->update([
            'IsDeleted' => 1,
            'DeletedAt' => Carbon::now()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Đã đánh dấu nhà cung cấp là xóa'
        ]);
    }
}
