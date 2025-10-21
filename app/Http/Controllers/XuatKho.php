<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class XuatKho extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        $table = match((int)$role) {
            1 => 'XuatKho_Bac',
            2 => 'XuatKho_Trung',
            3 => 'XuatKho_Nam',
            default => 'XuatKho_Bac',
        };

        $query = DB::table($table.' as xk')
            ->leftJoin('KhoHang as kh', 'xk.MaKho', '=', 'kh.MaKho')
            ->select(
                'xk.MaXuat',
                'xk.MaKho',
                'kh.TenKho',
                'xk.NgayXuat',
                'xk.GhiChu',
                'xk.IsDeleted'
            )
            ->where('xk.IsDeleted', 0);

        if ($request->filled('MaXuat')) {
            $query->where('xk.MaXuat', $request->MaXuat);
        }

        if ($request->filled('MaKho')) {
            $query->where('xk.MaKho', $request->MaKho);
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

        $validator = Validator::make($request->all(), [
            'MaKho' => "required|integer|exists:$tableKho,MaKho",
            'GhiChu' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.MaSP' => 'required|integer',
            'items.*.SoLuong' => 'required|integer|min:1',
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
            // Tạo mã xuất tự động
            $MaXuat = DB::table('XuatKho')->max('MaXuat') + 1;
            $NgayXuat = Carbon::now();

            // Thêm vào bảng tổng XuatKho
            DB::table('XuatKho')->insert([
                'MaXuat' => $MaXuat,
                'MaKho' => $request->MaKho,
                'NgayXuat' => $NgayXuat,
                'GhiChu' => $request->GhiChu ?? null,
                'IsDeleted' => 0
            ]);


            foreach ($request->items as $item) {
                // Thêm chi tiết xuất
                DB::table('ChiTietXuat')->insert([
                    'MaXuat' => $MaXuat,
                    'MaSP' => $item['MaSP'],
                    'SoLuong' => $item['SoLuong'],
                    'GiaXuat' => $item['GiaXuat'] ?? 0
                ]);
            }

            DB::commit();
            return response()->json([
                'status'=>'success',
                'message'=>'Thêm phiếu xuất kho thành công',
                'MaXuat' => $MaXuat
            ]);

        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
        }
    }

    public function destroy($MaXuat)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        $tableXuat = match((int)$role) {
            1 => 'XuatKho_Bac',
            2 => 'XuatKho_Trung',
            3 => 'XuatKho_Nam',
            default => 'XuatKho_Bac',
        };

        $tableChiTiet = match((int)$role) {
            1 => 'ChiTietXuat_Bac',
            2 => 'ChiTietXuat_Trung',
            3 => 'ChiTietXuat_Nam',
            default => 'ChiTietXuat_Bac',
        };

        DB::beginTransaction();
        try {
            $MaKho = DB::table($tableXuat)->where('MaXuat',$MaXuat)->value('MaKho');
            $items = DB::table('ChiTietXuat')->where('MaXuat',$MaXuat)->get();

            // Trả lại tồn kho
            foreach ($items as $item){
                DB::table('TonKho')->where('MaSP',$item->MaSP)
                    ->where('MaKho',$MaKho)
                    ->increment('SoLuongTon', $item->SoLuong);
            }

            // Đánh dấu phiếu xuất kho là xóa
            DB::table('XuatKho')->where('MaXuat',$MaXuat)->update([
                'IsDeleted'=>1,
                'DeletedAt'=>Carbon::now()
            ]);

            DB::table($tableXuat)->where('MaXuat',$MaXuat)->update([
                'IsDeleted'=>1,
                'DeletedAt'=>Carbon::now()
            ]);

            DB::commit();
            return response()->json([
                'status'=>'success',
                'message'=>'Đã hủy phiếu xuất kho thành công'
            ]);

        } catch (\Exception $e){
            DB::rollBack();
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
        }
    }

    public function chiTietXuat($MaXuat)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        // Bảng chi tiết xuất theo role
        $tableChiTiet = match((int)$role) {
            1 => 'ChiTietXuat_Bac',
            2 => 'ChiTietXuat_Trung',
            3 => 'ChiTietXuat_Nam',
            default => 'ChiTietXuat_Bac',
        };

        $tableXuat = match((int)$role) {
            1 => 'XuatKho_Bac',
            2 => 'XuatKho_Trung',
            3 => 'XuatKho_Nam',
            default => 'XuatKho_Bac',
        };

        // Lấy thông tin phiếu xuất
        $exists = DB::table($tableXuat)
            ->where('MaXuat', $MaXuat)
            ->where('IsDeleted', 0)
            ->exists();

        if (!$exists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phiếu xuất không tồn tại hoặc đã bị xóa'
            ], 404);
        }

        // Lấy chi tiết sản phẩm chưa xóa
        $items = DB::table($tableChiTiet.' as ctk')
            ->leftJoin('SanPham as sp', 'ctk.MaSP', '=', 'sp.MaSP')
            ->select(
                'ctk.MaSP',
                'sp.TenSP',
                'ctk.SoLuong',
                'ctk.GiaXuat',
                'ctk.Note'
            )
            ->where('ctk.MaXuat', $MaXuat)
            ->where('ctk.IsDeleted', 0)
            ->get();

        return response()->json([
            'status' => 'success',
            'items' => $items
        ]);
    }

    public function getAllXuatKho(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        if ($role != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bạn không có quyền truy cập API này'
            ], 403);
        }

        $limit = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        $query = DB::table('XuatKho')
            ->leftJoin('KhoHang', 'XuatKho.MaKho', '=', 'KhoHang.MaKho')
            ->select(
                'XuatKho.MaXuat',
                'XuatKho.MaKho',
                'KhoHang.TenKho',
                'XuatKho.NgayXuat',
                'XuatKho.GhiChu',
                'XuatKho.IsDeleted',
                'XuatKho.DeletedAt'
            )
            ->where('XuatKho.IsDeleted', 0);

        if ($request->filled('MaXuat')) {
            $query->where('XuatKho.MaXuat', $request->MaXuat);
        }
        if ($request->filled('MaKho')) {
            $query->where('XuatKho.MaKho', $request->MaKho);
        }
        if ($request->filled('FromDate')) {
            $query->whereDate('XuatKho.NgayXuat', '>=', $request->FromDate);
        }
        if ($request->filled('ToDate')) {
            $query->whereDate('XuatKho.NgayXuat', '<=', $request->ToDate);
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
