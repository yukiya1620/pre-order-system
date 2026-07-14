<?php

namespace App\Http\Controllers\Api\V1\Farmer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    /**
     * カテゴリー一覧(id・nameのみ)。F6の商品登録・編集フォームでカテゴリーを
     * 選ぶために使う。カテゴリー自体の登録・編集機能はまだ無い(シード投入のみ)。
     */
    public function index(): JsonResponse
    {
        $categories = Category::orderBy('id')->get(['id', 'name']);

        return response()->json(['categories' => $categories]);
    }
}
