<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class NhapKho extends Controller
{
    // Lấy danh sách nhập kho
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        $table = match((int)$role) {
            1 => 'NhapKho_Bac',
            2 => 'NhapKho_Trung',
            3 => 'NhapKho_Nam',
            default => 'NhapKho_Bac',
        };

        $query = DB::table($table.' as nk')
            ->leftJoin('KhoHang as kh', 'nk.MaKho', '=', 'kh.MaKho')
            ->leftJoin('NhaCungCap as ncc', 'nk.MaNCC', '=', 'ncc.MaNCC')
            ->select(
                'nk.MaNhap',
                'nk.MaKho',
                'kh.TenKho',
                'nk.MaNCC',
                'ncc.TenNCC',
                'nk.NgayNhap',
                'nk.GhiChu',
                'nk.IsDeleted'
            )
            ->where('nk.IsDeleted', 0);

        if ($request->filled('MaNhap')) {
            $query->where('nk.MaNhap', $request->MaNhap);
        }

        if ($request->filled('MaKho')) {
            $query->where('nk.MaKho', $request->MaKho);
        }

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


    // Thêm nhập kho
    public function store(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        $tableKho = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };
        // Validate
        $validator = Validator::make($request->all(), [
            'MaKho' => "required|integer|exists:$tableKho,MaKho",
            'MaNCC' => "required|integer",
            'items' => 'required|array|min:1',
            'items.*.MaSP' => 'required|integer',
            'items.*.SoLuong' => 'required|integer|min:1',
            'items.*.GiaNhap' => 'required|numeric|min:0',
        ], [
            'MaKho.required' => 'Mã kho không được để trống.',
            'MaKho.exists' => 'Mã kho không tồn tại.',
            'items.required' => 'Danh sách sản phẩm không được để trống.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'=>'error',
                'errors'=>$validator->errors()
            ],422);
        }

        DB::beginTransaction();
        try {
            // Tạo mã nhập tự động
            $MaNhap = DB::table('NhapKho')->max('MaNhap') + 1;

            $NgayNhap = Carbon::now();

            // Thêm vào bảng tổng NhapKho
            DB::table('NhapKho')->insert([
                'MaNhap' => $MaNhap,
                'MaKho' => $request->MaKho,
                'MaNCC' => $request->MaNCC,
                'NgayNhap' => $NgayNhap,
                'GhiChu' => $request->GhiChu ?? null,
                'IsDeleted' => 0
            ]);



            foreach($request->items as $item){
                // Thêm chi tiết nhập tổng
                DB::table('ChiTietNhap')->insert([
                    'MaNhap' => $MaNhap,
                    'MaSP' => $item['MaSP'],
                    'SoLuong' => $item['SoLuong'],
                    'GiaNhap' => $item['GiaNhap']
                ]);
            }

            DB::commit();

            return response()->json([
                'status'=>'success',
                'message'=>'Thêm nhập kho thành công',
                'MaNhap' => $MaNhap
            ]);
        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
        }
    }

    // Xóa nhập kho (đánh dấu, trả lại tồn kho)
    public function destroy($MaNhap)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        // Bảng khu vực theo role
        $tableNhap = match((int)$role) {
            1 => 'NhapKho_Bac',
            2 => 'NhapKho_Trung',
            3 => 'NhapKho_Nam',
            default => 'NhapKho_Bac',
        };

        $tableChiTiet = match((int)$role) {
            1 => 'ChiTietNhap_Bac',
            2 => 'ChiTietNhap_Trung',
            3 => 'ChiTietNhap_Nam',
            default => 'ChiTietNhap_Bac',
        };

        DB::beginTransaction();
        try {
            // Lấy MaKho của phiếu nhập
            $MaKho = DB::table($tableNhap)->where('MaNhap', $MaNhap)->value('MaKho');

            if (!$MaKho) {
                return response()->json([
                    'status'=>'error',
                    'message'=>'Phiếu nhập không tồn tại hoặc không thuộc role của bạn'
                ], 404);
            }

            // Lấy chi tiết nhập để trừ tồn kho
            $items = DB::table('ChiTietNhap')->where('MaNhap', $MaNhap)->get();

            foreach($items as $item){
                DB::table('TonKho')->where('MaSP', $item->MaSP)->where('MaKho', $MaKho)
                    ->update([
                        'SoLuongTon' => DB::raw("CASE WHEN SoLuongTon - {$item->SoLuong} < 0 THEN 0 ELSE SoLuongTon - {$item->SoLuong} END")
                    ]);
            }

            $now = Carbon::now();

            // Đánh dấu xóa bảng tổng NhapKho
            DB::table('NhapKho')->where('MaNhap', $MaNhap)->update([
                'IsDeleted' => 1,
                'DeletedAt' => $now
            ]);

            // Đánh dấu xóa bảng khu vực
            DB::table($tableNhap)->where('MaNhap', $MaNhap)->update([
                'IsDeleted' => 1,
                'DeletedAt' => $now
            ]);

            // Đánh dấu xóa chi tiết tổng
            DB::table('ChiTietNhap')->where('MaNhap', $MaNhap)->update([
                'IsDeleted' => 1,
                'DeletedAt' => $now
            ]);

            // Đánh dấu xóa chi tiết khu vực
            DB::table($tableChiTiet)->where('MaNhap', $MaNhap)->update([
                'IsDeleted' => 1,
                'DeletedAt' => $now
            ]);

            DB::commit();

            return response()->json([
                'status'=>'success',
                'message'=>'Đã xóa nhập kho thành công'
            ]);
        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
        }
    }
    public function chiTietNhap($MaNhap)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        // Chọn bảng theo role
        $tableChiTiet = match((int)$role) {
            1 => 'ChiTietNhap_Bac',
            2 => 'ChiTietNhap_Trung',
            3 => 'ChiTietNhap_Nam',
            default => 'ChiTietNhap_Bac',
        };

        $tableNhap = match((int)$role) {
            1 => 'NhapKho_Bac',
            2 => 'NhapKho_Trung',
            3 => 'NhapKho_Nam',
            default => 'NhapKho_Bac',
        };

        // Kiểm tra phiếu tồn tại và chưa xóa
        $exists = DB::table($tableNhap)
            ->where('MaNhap', $MaNhap)
            ->where('IsDeleted', 0)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiếu nhập không tồn tại hoặc đã bị xóa'
            ], 404);
        }

        // Lấy chi tiết nhập kèm tên sản phẩm
        $items = DB::table($tableChiTiet.' as ctn')
            ->leftJoin('SanPham as sp', 'ctn.MaSP', '=', 'sp.MaSP')
            ->select('ctn.MaSP', 'sp.TenSP', 'ctn.SoLuong', 'ctn.GiaNhap', 'ctn.Note')
            ->where('ctn.MaNhap', $MaNhap)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $items
        ]);
    }

    public function getAllNhapKho(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        // Chỉ cho phép role = 0
        if ($role != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền truy cập API này'
            ], 403);
        }

        $query = DB::table('NhapKho')
            ->leftJoin('KhoHang', 'NhapKho.MaKho', '=', 'KhoHang.MaKho')
            ->leftJoin('NhaCungCap', 'NhapKho.MaNCC', '=', 'NhaCungCap.MaNCC')
            ->select(
                'NhapKho.MaNhap',
                'NhapKho.MaKho',
                'KhoHang.TenKho',
                'NhapKho.MaNCC',
                'NhaCungCap.TenNCC',
                'NhapKho.NgayNhap',
                'NhapKho.GhiChu',
                'NhapKho.IsDeleted',
                'NhapKho.DeletedAt'
            )
            ->where('NhapKho.IsDeleted', 0);

        // Lọc theo query param
        if ($request->filled('MaNhap')) {
            $query->where('NhapKho.MaNhap', $request->MaNhap);
        }
        if ($request->filled('MaKho')) {
            $query->where('NhapKho.MaKho', $request->MaKho);
        }
        if ($request->filled('MaNCC')) {
            $query->where('NhapKho.MaNCC', $request->MaNCC);
        }
        if ($request->filled('FromDate')) {
            $query->whereDate('NhapKho.NgayNhap', '>=', $request->FromDate);
        }
        if ($request->filled('ToDate')) {
            $query->whereDate('NhapKho.NgayNhap', '<=', $request->ToDate);
        }

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


}
