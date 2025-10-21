<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;


class SanPham extends Controller
{
    /**
     * Lấy danh sách sản phẩm
     */
    public function getSanPham(Request $request)
    {
        $maSP = $request->input('MaSP', null);
        $tenSP = $request->input('TenSP', null);
        $maNCC = $request->input('MaNCC', null);
        $limit = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $offset = ($page - 1) * $limit;

        $query = DB::table('SanPham as sp')
            ->leftJoin('NhaCungCap as ncc', 'sp.MaNCC', '=', 'ncc.MaNCC')
            ->select('sp.MaSP', 'sp.TenSP', 'sp.DonVi', 'sp.MaNCC', 'sp.MoTa', 'ncc.TenNCC');

        if ($maSP !== null) {
            $query->where('sp.MaSP', $maSP);
        }

        if ($tenSP !== null) {
            $query->where('sp.TenSP', 'like', "%$tenSP%");
        }

        if ($maNCC !== null) {
            $query->where('sp.MaNCC', $maNCC);
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
    public function updateSanPham(Request $request, $MaSP)
    {
        // Validate dữ liệu nhập
        $validator = Validator::make($request->all(), [
            'TenSP' => 'required|string|max:100',
            'DonVi' => 'required|string|max:50',
            'MaNCC' => 'required|integer|exists:NhaCungCap,MaNCC',
            'MoTa'  => 'nullable|string|max:255'
        ], [
            'TenSP.required' => 'Tên sản phẩm là bắt buộc.',
            'TenSP.string'   => 'Tên sản phẩm phải là chuỗi ký tự.',
            'TenSP.max'      => 'Tên sản phẩm không được vượt quá 100 ký tự.',

            'DonVi.required' => 'Đơn vị tính là bắt buộc.',
            'DonVi.string'   => 'Đơn vị tính phải là chuỗi ký tự.',
            'DonVi.max'      => 'Đơn vị tính không được vượt quá 50 ký tự.',

            'MaNCC.required' => 'Nhà cung cấp là bắt buộc.',
            'MaNCC.integer'  => 'Nhà cung cấp phải là số nguyên.',
            'MaNCC.exists'   => 'Nhà cung cấp không tồn tại trong hệ thống.',

            'MoTa.string'    => 'Mô tả phải là chuỗi ký tự.',
            'MoTa.max'       => 'Mô tả không được vượt quá 255 ký tự.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Cập nhật dữ liệu
        $updated = DB::table('SanPham')
            ->where('MaSP', $MaSP)
            ->update([
                'TenSP' => $request->TenSP,
                'DonVi' => $request->DonVi,
                'MaNCC' => $request->MaNCC,
                'MoTa'  => $request->MoTa,
            ]);

        if ($updated) {
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cập nhật sản phẩm thất bại hoặc không có thay đổi.'
        ], 400);
    }
    public function deleteSanPham($id)
    {
        $sanpham = DB::table('SanPham')->where('MaSP', $id)->first();

        if (!$sanpham || $sanpham->IsDeleted) {
            return response()->json([
                'success' => false,
                'message' => 'Sản phẩm không tồn tại hoặc đã bị xóa.'
            ], 404);
        }

        DB::table('SanPham')
            ->where('MaSP', $id)
            ->update([
                'IsDeleted' => 1,
                'DeletedAt' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Sản phẩm đã được đánh dấu xóa.'
        ]);
    }

    public function createSanPham(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'MaSP'   => 'required|integer|unique:SanPham,MaSP',
            'TenSP'  => 'required|string|max:100',
            'DonVi'  => 'required|string|max:50',
            'MaNCC'  => 'required|integer|exists:NhaCungCap,MaNCC',
            'MoTa'   => 'nullable|string|max:255'
        ], [
            'MaSP.required'  => 'Mã sản phẩm là bắt buộc.',
            'MaSP.integer'   => 'Mã sản phẩm phải là số nguyên.',
            'MaSP.unique'    => 'Mã sản phẩm đã tồn tại.',
            'TenSP.required' => 'Tên sản phẩm là bắt buộc.',
            'TenSP.string'   => 'Tên sản phẩm phải là chuỗi ký tự.',
            'TenSP.max'      => 'Tên sản phẩm tối đa 100 ký tự.',
            'DonVi.required' => 'Đơn vị tính là bắt buộc.',
            'DonVi.string'   => 'Đơn vị tính phải là chuỗi ký tự.',
            'DonVi.max'      => 'Đơn vị tính tối đa 50 ký tự.',
            'MaNCC.required' => 'Nhà cung cấp là bắt buộc.',
            'MaNCC.integer'  => 'Mã nhà cung cấp phải là số nguyên.',
            'MaNCC.exists'   => 'Nhà cung cấp không tồn tại.',
            'MoTa.string'    => 'Mô tả phải là chuỗi ký tự.',
            'MoTa.max'       => 'Mô tả tối đa 255 ký tự.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Insert dữ liệu
        DB::table('SanPham')->insert([
            'MaSP'     => $request->MaSP,
            'TenSP'    => $request->TenSP,
            'DonVi'    => $request->DonVi,
            'MaNCC'    => $request->MaNCC,
            'MoTa'     => $request->MoTa,
            'IsDeleted'=> 0,
            'DeletedAt'=> null
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Thêm sản phẩm thành công'
        ]);
    }

}
