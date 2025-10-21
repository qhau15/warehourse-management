<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TonKho extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        // Chọn bảng kho theo role
        $tableKho = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };

        // Lấy danh sách MaKho của role
        $maKhoList = DB::table($tableKho)->where('IsDeleted', 0)->pluck('MaKho')->toArray();

        $query = DB::table('TonKho as tk')
            ->leftJoin('SanPham as sp', 'tk.MaSP', '=', 'sp.MaSP')
            ->leftJoin($tableKho . ' as kh', 'tk.MaKho', '=', 'kh.MaKho')
            ->select(
                'tk.MaSP',
                'sp.TenSP',
                'tk.MaKho',
                'kh.TenKho',
                'tk.SoLuongTon'
            )
            ->whereIn('tk.MaKho', $maKhoList)
            ->where('tk.IsDeleted', 0);

        if ($request->filled('MaSP')) {
            $query->where('tk.MaSP', $request->MaSP);
        }
        if ($request->filled('MaKho')) {
            $query->where('tk.MaKho', $request->MaKho);
        }
        if ($request->filled('SoLuongTon')) {
            $query->where('tk.SoLuongTon', '>=',$request->SoLuongTon);
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

    // API tồn kho chi tiết all (admin)
    public function getAll(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        if ($role != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ admin mới được lấy tất cả tồn kho'
            ], 403);
        }

        $query = DB::table('TonKho as tk')
            ->leftJoin('SanPham as sp', 'tk.MaSP', '=', 'sp.MaSP')
            ->leftJoin('KhoHang as kh', 'tk.MaKho', '=', 'kh.MaKho')
            ->select(
                'tk.MaSP',
                'sp.TenSP',
                'tk.MaKho',
                'kh.TenKho',
                'tk.SoLuongTon'
            )
            ->where('tk.IsDeleted', 0);

        if ($request->filled('MaSP')) {
            $query->where('tk.MaSP', $request->MaSP);
        }
        if ($request->filled('MaKho')) {
            $query->where('tk.MaKho', $request->MaKho);
        }
        if ($request->filled('SoLuongTon')) {
            $query->where('tk.SoLuongTon', '>=',$request->SoLuongTon);
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

    // API đếm số lượng sản phẩm
    public function countTonKho(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        // Chọn bảng kho theo role
        $tableKho = match((int)$role) {
            1 => 'KhoHang_Bac',
            2 => 'KhoHang_Trung',
            3 => 'KhoHang_Nam',
            default => 'KhoHang_Bac',
        };

        // Lấy danh sách MaKho của role
        $maKhoList = DB::table($tableKho)->where('IsDeleted', 0)->pluck('MaKho')->toArray();
        $query = DB::table('TonKho as tk')
            ->whereIn('tk.MaKho', $maKhoList)
            ->where('tk.IsDeleted', 0);

        // Filter theo MaSP nếu có
        if ($request->filled('MaSP')) {
            $query->where('tk.MaSP', $request->MaSP);
        }

        // Filter theo MaKho nếu có
        if ($request->filled('MaKho')) {
            $query->where('tk.MaKho', $request->MaKho);
        }

        // Lấy tổng số lượng
        $total = $query->sum('tk.SoLuongTon');

        return response()->json([
            'status' => 'success',
            'total' => $total
        ]);
    }

    public function countTonKhoAll(Request $request)
    {
        $user = Auth::user();
        $role = $user->role ?? 1;

        if ($role != 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chỉ admin mới được truy cập.'
            ], 403);
        }

        $query = DB::table('TonKho as tk')
            ->where('tk.IsDeleted', 0)
            ->leftJoin('SanPham as sp', 'tk.MaSP', '=', 'sp.MaSP')
            ->leftJoin('KhoHang as k', 'tk.MaKho', '=', 'k.MaKho')
            ->select('tk.*', 'sp.TenSP', 'k.TenKho');

        if ($request->filled('MaSP')) {
            $query->where('tk.MaSP', $request->MaSP);
        }

        if ($request->filled('MaKho')) {
            $query->where('tk.MaKho', $request->MaKho);
        }

        $total = $query->sum('tk.SoLuongTon');

        return response()->json([
            'status' => 'success',
            'total' => $total
        ]);
    }

}

