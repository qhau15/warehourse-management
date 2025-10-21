<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SanPham;
use App\Http\Controllers\KhuVuc;
use App\Http\Controllers\NhaCungCap;
use App\Http\Controllers\KhoHang;
use App\Http\Controllers\NhapKho;
use App\Http\Controllers\XuatKho;
use App\Http\Controllers\TonKho;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
// Các route lấy data, dùng middleware auth:sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/sanpham', [SanPham::class, 'getSanPham']);
    Route::put('/sanpham/{id}', [SanPham::class, 'updateSanPham']);
    Route::delete('/sanpham/delete/{id}', [SanPham::class, 'deleteSanPham']);
    Route::post('/sanpham', [SanPham::class, 'createSanPham']);



    Route::get('/khuvuc', [KhuVuc::class, 'index']);
    Route::put('/khuvuc/{MaKhuVuc}', [KhuVuc::class, 'update']);
    Route::delete('/khuvuc/{MaKhuVuc}', [KhuVuc::class, 'destroy']);


    Route::get('/nhacungcap', [NhaCungCap::class, 'getNhaCungCap']);
    Route::post('/nhacungcap', [NhaCungCap::class, 'addNhaCungCap']);
    Route::put('/nhacungcap/{MaNCC}', [NhaCungCap::class, 'updateNhaCungCap']);
    Route::delete('/nhacungcap/{MaNCC}', [NhaCungCap::class, 'deleteNhaCungCap']);

    Route::get('/khohang', [KhoHang::class, 'getKhoHang']);
    Route::get('/khohang/all', [KhoHang::class, 'getAllKhoHang']);
    Route::post('/khohang', [KhoHang::class, 'storeKhoHang']);
    Route::put('/khohang/{MaKho}', [KhoHang::class, 'updateKhoHang']);
    Route::delete('/khohang/{MaKho}', [KhoHang::class, 'deleteKhoHang']);


    Route::get('/nhapkho', [NhapKho::class, 'index']);
    Route::get('/nhapkho/all', [NhapKho::class, 'getAllNhapKho']);
    Route::get('/nhapkho/chi-tiet/{MaNhap}', [NhapKho::class, 'chiTietNhap']);
    Route::post('/nhapkho', [NhapKho::class, 'store']);
    Route::delete('/nhapkho/{MaNhap}', [NhapKho::class, 'destroy']);


    Route::get('/xuatkho', [XuatKho::class,'index']);
    Route::get('/xuatkho/all', [XuatKho::class,'getAllXuatKho']);
    Route::get('/xuatkho/chi-tiet/{MaXuat}', [XuatKho::class, 'chiTietXuat']);
    Route::post('/xuatkho', [XuatKho::class,'store']);
    Route::delete('/xuatkho/{MaXuat}', [XuatKho::class,'destroy']);


    Route::get('/tonkho', [TonKho::class,'index']);
    Route::get('/tonkho/all', [TonKho::class,'getAll']);
    Route::get('/tonkho/count', [TonKho::class,'countTonKho']);
    Route::get('/tonkho/count-all', [TonKho::class,'countTonKhoAll']);

});
